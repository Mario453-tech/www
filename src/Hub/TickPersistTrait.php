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
    public function persistTickResult(array $hub, array $result, DateTime $now): void
    {
        $hubId      = (int)$hub['id'];
        $tickTime   = $now->format('Y-m-d H:i:s');
        $condBefore = (float)$hub['condition_pct'];

        try {
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

 // Keep 7 days of stats (720 ticks at 1/h)
            $this->db->prepare(
                "DELETE FROM logistics_hub_tick_stats
                  WHERE hub_id = ? AND tick_time < DATE_SUB(?, INTERVAL 7 DAY)"
            )->execute([$hubId, $tickTime]);

        } catch (Throwable $e) {
            GameLog::error('HubTickService', 'persistTickResult failed', $e, ['hub_id' => $hubId]);
        }
    }
}
