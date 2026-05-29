<?php

/**
 * HubViewSummaryTrait - region-level summary and well grouping helpers.
 * Used by HubViewService.
 */
trait HubViewSummaryTrait
{
    /**
     * Returns a summary per region for the player's logistics overview.
     *
     * @return list<array{
     *   region_id: int, region_name: string,
     *   hubs: list<array<string, mixed>>, hub_count: int,
     *   total_wells: int, unassigned_wells: int,
     *   total_nominal_bph: float, total_real_bph: float,
     *   avg_load_pct: float, total_buffer_bbl: float,
     *   total_buffer_used_bbl: float, total_lost_bbl: float,
     *   overloaded: bool, alerts: list<string>
     * }>
     */
    public function getRegionSummary(int $playerId): array
    {
        $hubs = $this->hubSvc->getPlayerHubs($playerId);

        $byRegion        = [];
        $allWells        = $this->getPlayerWellsByRegion($playerId);
        $unassignedWells = $this->getUnassignedWellsByRegion($playerId);

        foreach ($hubs as $hub) {
            $byRegion[(int)$hub['region_id']][] = $hub;
        }

        $result = [];

        foreach ($byRegion as $regionId => $regionHubs) {
            $totalNominal = 0.0;
            $totalReal    = 0.0;
            $totalBuffer  = 0.0;
            $totalUsed    = 0.0;
            $totalLost    = 0.0;
            $loadPcts     = [];
            $overloaded   = false;
            $alerts       = [];

            foreach ($regionHubs as $hub) {
                $totalNominal += (float)$hub['nominal_capacity_bph'];
                $totalReal    += (float)$hub['real_capacity_bph'];
                $totalBuffer  += (float)$hub['buffer_capacity_bbl'];
                $totalUsed    += (float)$hub['buffer_current_bbl'];

                $lastStats = $this->hubSvc->getLastTickStats((int)$hub['id']);
                if ($lastStats) {
                    $loadPcts[] = (float)$lastStats['load_pct'];
                    $totalLost += (float)$lastStats['lost_volume_bbl'];
                }

                if ($hub['status'] === 'overloaded') {
                    $overloaded = true;
                    $alerts[]   = 'overloaded';
                }
                if ((float)$hub['condition_pct'] < 50.0) {
                    $alerts[] = 'low_condition';
                }
                if ((float)$hub['buffer_capacity_bbl'] > 0
                    && (float)$hub['buffer_current_bbl'] >= (float)$hub['buffer_capacity_bbl'] * 0.9) {
                    $alerts[] = 'buffer_near_full';
                }
            }

            $unassigned = count($unassignedWells[$regionId] ?? []);
            if ($unassigned > 0) {
                $alerts[] = 'wells_without_hub';
            }

            $avgLoad    = count($loadPcts) > 0 ? round(array_sum($loadPcts) / count($loadPcts), 1) : 0.0;
            $regionName = $regionHubs[0]['region_name'] ?? "Region #{$regionId}";

            $result[] = [
                'region_id'             => $regionId,
                'region_name'           => $regionName,
                'hubs'                  => $regionHubs,
                'hub_count'             => count($regionHubs),
                'total_wells'           => count($allWells[$regionId] ?? []),
                'unassigned_wells'      => $unassigned,
                'total_nominal_bph'     => round($totalNominal, 2),
                'total_real_bph'        => round($totalReal, 2),
                'avg_load_pct'          => $avgLoad,
                'total_buffer_bbl'      => round($totalBuffer, 2),
                'total_buffer_used_bbl' => round($totalUsed, 2),
                'total_lost_bbl'        => round($totalLost, 2),
                'overloaded'            => $overloaded,
                'alerts'                => array_unique($alerts),
            ];
        }

        // Regions that have wells but no hubs
        foreach ($allWells as $regionId => $wells) {
            if (!isset($byRegion[$regionId])) {
                $unassigned  = count($unassignedWells[$regionId] ?? []);
                $regionName  = $wells[0]['region_name'] ?? "Region #{$regionId}";
                $result[]    = [
                    'region_id'             => $regionId,
                    'region_name'           => $regionName,
                    'hubs'                  => [],
                    'hub_count'             => 0,
                    'total_wells'           => count($wells),
                    'unassigned_wells'      => $unassigned,
                    'total_nominal_bph'     => 0.0,
                    'total_real_bph'        => 0.0,
                    'avg_load_pct'          => 0.0,
                    'total_buffer_bbl'      => 0.0,
                    'total_buffer_used_bbl' => 0.0,
                    'total_lost_bbl'        => 0.0,
                    'overloaded'            => false,
                    'alerts'                => $unassigned > 0 ? ['wells_without_hub'] : [],
                ];
            }
        }

        usort($result, fn($a, $b) => $a['region_id'] <=> $b['region_id']);
        return $result;
    }

    /**
     * Returns all player wells grouped by region_id.
     * @return array<int, list<array<string, mixed>>>
     */
    private function getPlayerWellsByRegion(int $playerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT w.id, w.location_name AS name, w.region_id, w.zone_key, w.status,
                    wr.name AS region_name
               FROM wells w
               LEFT JOIN world_regions wr ON wr.id = w.region_id
              WHERE w.player_id = ? AND w.status NOT IN ('sold','seized')
              ORDER BY w.region_id, w.location_name"
        );
        $stmt->execute([$playerId]);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $w) {
            $grouped[(int)$w['region_id']][] = $w;
        }
        return $grouped;
    }

    /**
     * Returns unassigned player wells grouped by region_id.
     * @return array<int, list<array<string, mixed>>>
     */
    private function getUnassignedWellsByRegion(int $playerId): array
    {
        $grouped = [];
        foreach ($this->hubSvc->getUnassignedWells($playerId) as $w) {
            $grouped[(int)$w['region_id']][] = $w;
        }
        return $grouped;
    }
}
