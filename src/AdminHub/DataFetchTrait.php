<?php

/**
 * AdminHubDataFetchTrait - fetches view data for the admin logistics hubs panel.
 * PL: AdminHubDataFetchTrait - pobieranie danych do widoku panelu admina hubow.
 *
 * Used by: admin/logistics_hubs.php
 * PL: Uzywane przez: admin/logistics_hubs.php
 */
trait AdminHubDataFetchTrait
{
    private function tableExists(PDO $db, string $table): bool
    {
        return (bool)$db->query("SHOW TABLES LIKE " . $db->quote($table))->fetchColumn();
    }

 /**
 * Load full global hub config from DB.
 * PL: Wczytuje pelna konfiguracje globalna hubow z bazy.
 *
 * @return array<string, array<string, string>>
 */
    public function loadHubConfigMap(PDO $db): array
    {
        $rows = $db->query(
            "SELECT config_group, config_key, config_value
               FROM logistics_hub_config
              WHERE config_scope = 'global'
              ORDER BY config_group, config_key"
        )->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($rows as $row) {
            $map[$row['config_group']][$row['config_key']] = $row['config_value'];
        }
        return $map;
    }

 /**
 * Fetch all world regions.
 * PL: Pobiera liste wszystkich regionow.
 *
 * @return array<int, array<string, mixed>>
 */
    public function loadAllRegions(PDO $db): array
    {
        return $db->query("SELECT id, name FROM world_regions ORDER BY name")
            ->fetchAll(PDO::FETCH_ASSOC);
    }

 /**
 * Filter hub list by optional status and region values.
 * PL: Filtruje liste hubow po opcjonalnym statusie i regionie.
 *
 * @param array<int, array<string, mixed>> $allHubs
 * @return array<int, array<string, mixed>>
 */
    public function filterHubs(array $allHubs, string $filterStatus, int $filterRegion): array
    {
        if ($filterStatus !== '') {
            $allHubs = array_values(array_filter($allHubs, fn($h) => $h['status'] === $filterStatus));
        }
        if ($filterRegion > 0) {
            $allHubs = array_values(array_filter($allHubs, fn($h) => (int)$h['region_id'] === $filterRegion));
        }
        return $allHubs;
    }

 /**
 * Fetch hub detail, connected wells and the latest tick stats.
 * PL: Pobiera szczegoly huba, podpiete odwierty i ostatnie statystyki ticka.
 *
 * @return array{hub: array<string,mixed>|null, wells: array<int,mixed>, lastStats: array<string,mixed>|null}
 */
    public function loadHubDetail(HubService $hubSvc, int $viewHubId): array
    {
        if ($viewHubId <= 0) {
            return ['hub' => null, 'wells' => [], 'lastStats' => null];
        }

        return [
            'hub'       => $hubSvc->getHub($viewHubId),
            'wells'     => $hubSvc->getHubWells($viewHubId),
            'lastStats' => $hubSvc->getLastTickStats($viewHubId),
        ];
    }

 /**
 * Compute aggregate stats for the current hub list.
 * PL: Oblicza statystyki zbiorcze dla biezacej listy hubow.
 *
 * @param array<int, array<string, mixed>> $hubs
 * @return array{total: int, active: int, paused: int}
 */
    public function computeHubStats(array $hubs): array
    {
        return [
            'total'  => count($hubs),
            'active' => count(array_filter($hubs, fn($h) => $h['status'] === 'active')),
            'paused' => count(array_filter($hubs, fn($h) => $h['status'] === 'paused')),
        ];
    }

 /**
 * Fetch tick verification stats for admin diagnostics.
 * PL: Pobiera statystyki weryfikacyjne ticka do diagnostyki admina.
 *
 * @return array<string, mixed>
 */
    public function getTickVerificationStats(PDO $db): array
    {
        $stats = [
            'unassigned_wells'      => 0,
            'unassigned_production' => 0.0,
            'total_opex_charged'    => 0.0,
            'hub_assignments'       => 0,
            'fallback_losses_bbl'   => 0.0,
            'fallback_losses_value' => 0.0,
            'last_tick_at'          => null,
        ];

        try {
 // Check whether the assignments table exists.
 // PL: Sprawdza, czy istnieje tabela assignments.
            if (!$this->tableExists($db, 'logistics_hub_assignments')) {
                GameLog::warn('AdminHub', 'Table logistics_hub_assignments does not exist');
                return $stats;
            }

 // Wells without an active hub assignment, regardless of well status.
 // PL: Odwierty bez aktywnego przypisania do huba, bez wzgledu na status odwiertu.
            $unassigned = $db->query("
                SELECT COUNT(*) as cnt, COALESCE(SUM(w.base_production_per_hour), 0) as prod
                FROM wells w
                LEFT JOIN logistics_hub_assignments a ON a.well_id = w.id AND a.status = 'active'
                WHERE a.id IS NULL
            ")->fetch(PDO::FETCH_ASSOC);
            $stats['unassigned_wells'] = (int)$unassigned['cnt'];
            $stats['unassigned_production'] = (float)$unassigned['prod'];

 // Active assignments from the assignments table.
 // PL: Aktywne przypisania z tabeli assignments.
            $assignments = $db->query("
                SELECT COUNT(*) as cnt FROM logistics_hub_assignments WHERE status = 'active'
            ")->fetchColumn();
            $stats['hub_assignments'] = (int)($assignments ?: 0);

 // Last tick timestamp from tick_stats.
 // PL: Czas ostatniego ticka z tick_stats.
            if ($this->tableExists($db, 'tick_stats')) {
                $lastTick = $db->query("SELECT ran_at FROM tick_stats ORDER BY id DESC LIMIT 1")->fetchColumn();
                $stats['last_tick_at'] = $lastTick ?: null;
            } else {
                GameLog::info('AdminHub', 'Table tick_stats missing, skipping last tick timestamp');
            }

 // Fallback losses from finance logs for the most recent tick.
 // PL: Straty fallback z finance_logs dla najnowszego ticka.
            if ($this->tableExists($db, 'finance_logs')) {
                $lastFinanceTick = $db->query("SELECT MAX(tick_at) FROM finance_logs")->fetchColumn();
                if ($lastFinanceTick) {
                    $stats['last_tick_at'] = $stats['last_tick_at'] ?? $lastFinanceTick;
                    $losses = $db->prepare("
                        SELECT
                            COALESCE(SUM(fallback_loss_bbl), 0) AS bbl,
                            COALESCE(SUM(fallback_loss_value), 0) AS val
                        FROM finance_logs
                        WHERE tick_at = ?
                    ");
                    $losses->execute([$lastFinanceTick]);
                    $lossRow = $losses->fetch(PDO::FETCH_ASSOC) ?: ['bbl' => 0, 'val' => 0];
                    $stats['fallback_losses_bbl'] = (float)($lossRow['bbl'] ?? 0);
                    $stats['fallback_losses_value'] = (float)($lossRow['val'] ?? 0);
                }
            } else {
                GameLog::info('AdminHub', 'Table finance_logs missing, skipping fallback loss stats');
            }

 // Approximate OPEX linked to hub usage.
 // PL: Przyblizony OPEX powiazany z wykorzystaniem hubow.
            $opex = $db->query("
                SELECT COALESCE(SUM(
                    h.opex_per_tick * (SELECT COUNT(*) FROM logistics_hub_assignments a2 WHERE a2.hub_id = h.id AND a2.status = 'active') / h.slot_limit
                ), 0) as opex
                FROM logistics_hubs h
                WHERE h.status = 'active'
                HAVING opex > 0
            ")->fetchColumn() ?: 0;
            $stats['total_opex_charged'] = (float)$opex;

 // Debug summary for assignment visibility.
 // PL: Debugowe podsumowanie dla widocznosci przypisan.
            $totalAssignments = $db->query("SELECT COUNT(*) FROM logistics_hub_assignments LIMIT 5")->fetchColumn();
            GameLog::info('AdminHub', 'Tick verification stats', [
                'total_assignments' => (int)($totalAssignments ?: 0),
                'unassigned_wells'  => $stats['unassigned_wells'],
                'hub_assignments'   => $stats['hub_assignments'],
                'last_tick_at'      => $stats['last_tick_at'],
            ]);

            $sample = $db->query("SELECT status, COUNT(*) as cnt FROM logistics_hub_assignments GROUP BY status LIMIT 10")
                ->fetchAll(PDO::FETCH_ASSOC);
            GameLog::info('AdminHub', 'Assignment status breakdown', ['statuses' => $sample]);
        } catch (Throwable $e) {
            GameLog::error('AdminHub', 'getTickVerificationStats failed', $e);
        }

        return $stats;
    }
}
