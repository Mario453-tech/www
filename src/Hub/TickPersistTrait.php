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
        $playerId   = (int)($hub['player_id'] ?? 0);
        if ($playerId <= 0) {
            $playerId = (int)($hub['tenant_player_id'] ?? 0);
        }
        $tickTime   = $now->format('Y-m-d H:i:s');
        $condBefore = (float)$hub['condition_pct'];

        if ($hubId <= 0 || $playerId <= 0) {
            GameLog::warn('HubTickService', 'persistTickResult rejected unscoped hub', [
                'hub_id' => $hubId,
                'player_id' => $playerId,
            ]);
            return false;
        }

        // Buffer and statistics persist atomically to prevent barrel duplication.
        // Bufor i statystyki zapisuja sie atomowo, aby nie duplikowac barylek.
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

            $hubUpdate = $this->db->prepare(
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
                  WHERE id = ?
                    AND (player_id = ? OR tenant_player_id = ?)"
            );
            $hubUpdate->execute([
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
                $playerId,
                $playerId,
            ]);
            if ($hubUpdate->rowCount() !== 1) {
                throw new RuntimeException('Player-scoped hub tick update changed no rows.');
            }

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
 * Adds outbound excess back to the player-controlled hub buffer.
 * Dodaje nadmiar z transportu wyjsciowego do bufora huba kontrolowanego przez gracza.
 */
    public function addBufferBbl(int $hubId, int $playerId, float $bbl): float
    {
        if ($hubId <= 0 || $playerId <= 0 || $bbl <= 0.001) {
            return 0.0;
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT buffer_current_bbl, buffer_capacity_bbl
                   FROM logistics_hubs
                  WHERE id = ?
                    AND (player_id = ? OR tenant_player_id = ?)"
            );
            $stmt->execute([$hubId, $playerId, $playerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return 0.0;
            }
            $space = max(0.0, (float)$row['buffer_capacity_bbl'] - (float)$row['buffer_current_bbl']);
            $added = round(min($bbl, $space), 2);
            if ($added <= 0.001) {
                return 0.0;
            }
            // LEAST is an atomic capacity guard for concurrent buffer updates.
            // LEAST jest atomowa ochrona pojemnosci przy rownoleglych zapisach bufora.
            $bufferUpdate = $this->db->prepare(
                "UPDATE logistics_hubs
                    SET buffer_current_bbl = LEAST(buffer_capacity_bbl, buffer_current_bbl + ?),
                        updated_at = NOW()
                  WHERE id = ?
                    AND (player_id = ? OR tenant_player_id = ?)"
            );
            $bufferUpdate->execute([$added, $hubId, $playerId, $playerId]);
            if ($bufferUpdate->rowCount() !== 1) {
                return 0.0;
            }
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
