<?php

/**
 * HubTickPersistTrait - persists tick results to DB.
 * Used by HubTickService.
 */
trait HubTickPersistTrait
{
 /**
 * Persists tick results back to logistics_hubs and logistics_hub_tick_stats.
 *
 * @param array<string, mixed> $hub
 * @param array<string, mixed> $result from processTick()
 */
    public function persistTickResult(array $hub, array $result, DateTime $now): bool
    {
        $hubId      = (int)$hub['id'];
        $tickTime   = $now->format('Y-m-d H:i:s');
        $condBefore = (float)$hub['condition_pct'];

 // Bufor i staty musza sie zapisac atomowo — inaczej caller moze skredytowac zdrenowana
 // rope, a bufor pozostanie niezmniejszony (duplikacja barylek w kolejnym ticku).
 // Buffer and stats must persist atomically — otherwise the caller may credit drained oil
 // while the buffer stays undecremented (barrel duplication on the next tick).
        $ownTransaction = !$this->db->inTransaction();
        $savepoint = 'hub_tick_persist_' . $hubId;
        $savepointActive = false;
        try {
            if ($ownTransaction) {
                $this->db->beginTransaction();
            } else {
                $this->db->exec("SAVEPOINT {$savepoint}");
                $savepointActive = true;
            }

            $this->db->prepare(
                "UPDATE logistics_hubs
                    SET buffer_current_bbl   = ?,
                        real_capacity_bph    = ?,
                        condition_pct        = ?,
                        wear_level           = wear_level + ?,
                        efficiency_pct       = ?,
                        status               = ?,
                        repair_cost_estimate = ?,
                        last_processed_at    = ?,
                        updated_at           = ?
                  WHERE id = ?"
            )->execute([
                $result['new_buffer'],
                $this->calculateRealCapacity($hub, $result),
                $result['new_condition'],
                $result['wear_added'],
                $result['new_efficiency'],
                $result['new_status'],
                $this->hubSvc->getRepairCost(array_merge($hub, ['condition_pct' => $result['new_condition']])),
                $tickTime,
                $tickTime,
                $hubId,
            ]);

            $this->db->prepare(
                "INSERT INTO logistics_hub_tick_stats
                    (hub_id, tick_time, input_volume_bbl, processed_volume_bbl,
                     buffered_volume_bbl, lost_volume_bbl, load_pct,
                     condition_before_pct, condition_after_pct, wear_added,
                     overload_flag, incident_flag, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $hubId,
                $tickTime,
                $result['input_bbl'] ?? ($result['processed_bbl'] + $result['buffered_bbl'] + $result['lost_bbl']),
                $result['processed_bbl'],
                $result['buffered_bbl'],
                $result['lost_bbl'],
                $result['load_pct'],
                $condBefore,
                $result['new_condition'],
                $result['wear_added'],
                $result['overloaded'] ? 1 : 0,
                $result['incident_flag'] ? 1 : 0,
                $tickTime,
            ]);

            if ($ownTransaction) {
                $this->db->commit();
            } elseif ($savepointActive) {
                $this->db->exec("RELEASE SAVEPOINT {$savepoint}");
            }
            return true;
        } catch (Throwable $e) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            } elseif ($savepointActive && $this->db->inTransaction()) {
                try {
                    $this->db->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
                    $this->db->exec("RELEASE SAVEPOINT {$savepoint}");
                } catch (Throwable $rollbackError) {
                    GameLog::error('HubTickService', 'persistTickResult savepoint rollback failed', $rollbackError, ['hub_id' => $hubId]);
                }
            }
            GameLog::error('HubTickService', 'persistTickResult failed', $e, ['hub_id' => $hubId]);
            return false;
        }
    }

 /**
 * Dodaje barylki z powrotem do bufora hubu (np. nadmiar ponad przepustowosc rurociagu
 * wylotowego, ktory nie zmiescil sie w tym ticku i przechodzi na kolejny).
 * Bufor jest capowany do buffer_capacity_bbl — jak przy normalnym wejsciu huba
 * (HubTickService liczy bufferSpace); zwraca faktycznie dodana ilosc, nadmiar
 * to strata po stronie callera.
 * Adds barrels back to the hub buffer (e.g. outbound-pipeline over-capacity excess that
 * did not fit this tick and carries over to the next).
 * The buffer is capped at buffer_capacity_bbl — same as regular hub intake
 * (HubTickService computes bufferSpace); returns the amount actually added, the
 * overflow is the caller's loss.
 */
    public function addBufferBbl(int $hubId, float $bbl): float
    {
        if ($bbl <= 0.001) {
            return 0.0;
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT buffer_current_bbl, buffer_capacity_bbl FROM logistics_hubs WHERE id = ?"
            );
            $stmt->execute([$hubId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return 0.0;
            }
            $space = max(0.0, (float)$row['buffer_capacity_bbl'] - (float)$row['buffer_current_bbl']);
            $added = round(min($bbl, $space), 2);
            if ($added <= 0.001) {
                return 0.0;
            }
 // LEAST(...) w samym UPDATE jest atomowym strażnikiem pojemności: nawet jeśli równoległy
 // przebieg (ADMIN_FORCE_TICK omija GET_LOCK) zmieni buffer_current_bbl między SELECT a UPDATE,
 // bufor nigdy nie przekroczy buffer_capacity_bbl. $added (z SELECT) to best-effort do księgowania strat.
 // LEAST(...) inside the UPDATE is an atomic capacity guard: even if a concurrent run
 // (ADMIN_FORCE_TICK bypasses GET_LOCK) changes buffer_current_bbl between SELECT and UPDATE,
 // the buffer never exceeds buffer_capacity_bbl. $added (from the SELECT) is best-effort for loss accounting.
            $this->db->prepare(
                "UPDATE logistics_hubs
                    SET buffer_current_bbl = LEAST(buffer_capacity_bbl, buffer_current_bbl + ?),
                        updated_at = NOW()
                  WHERE id = ?"
            )->execute([$added, $hubId]);
            return $added;
        } catch (Throwable $e) {
            GameLog::error('HubTickService', 'addBufferBbl failed', $e, ['hub_id' => $hubId]);
            return 0.0;
        }
    }

    public function markLatestTickIncident(int $hubId, DateTime $now): void
    {
        try {
            $this->db->prepare(
                "UPDATE logistics_hub_tick_stats
                    SET incident_flag = 1
                  WHERE hub_id = ? AND tick_time = ?"
            )->execute([$hubId, $now->format('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            GameLog::error('HubTickService', 'markLatestTickIncident failed', $e, ['hub_id' => $hubId]);
        }
    }

 /**
 * Prunes hub tick stats older than 7 days. Wywolywac rzadko (raz na tick globalnie,
 * nie per hub) — poza hot-path per-hub. Call rarely (once per global tick, not per hub).
 */
    public function pruneHubTickStats(DateTime $now): void
    {
        try {
            $this->db->prepare(
                "DELETE FROM logistics_hub_tick_stats
                  WHERE tick_time < DATE_SUB(?, INTERVAL 7 DAY)"
            )->execute([$now->format('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            GameLog::error('HubTickService', 'pruneHubTickStats failed', $e, []);
        }
    }
}
