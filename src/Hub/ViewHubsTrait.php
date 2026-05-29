<?php

/**
 * HubViewHubsTrait hub cards, alerts, detail view and assignable hub list.
 * Used by HubViewService.
 */
trait HubViewHubsTrait
{
 /**
 * Returns hub cards with enriched data for the hub list view.
 * @return list<array<string, mixed>>
 */
    public function getHubCards(int $playerId): array
    {
        $cards = [];

        foreach ($this->hubSvc->getPlayerHubs($playerId) as $hub) {
            $lastStats = $this->hubSvc->getLastTickStats((int)$hub['id']);
            $cards[]   = [
                'hub'          => $hub,
                'wells'        => $this->hubSvc->getHubWells((int)$hub['id']),
                'last_stats'   => $lastStats,
                'opex'         => $this->econSvc->getOpex($hub),
                'repair_cost'  => $this->econSvc->getRepairCost($hub),
                'upgrade_cost' => $this->econSvc->getUpgradeCost($hub),
                'status_class' => $this->getStatusCssClass($hub['status']),
                'load_class'   => $this->getLoadCssClass((float)($lastStats['load_pct'] ?? 0)),
            ];
        }

        return $cards;
    }

 /**
 * Returns hub cards for hubs the player OWNS or RENTS (private ownership model).
 * Unlike getHubCards (assignment-based), this includes acquired hubs with no wells yet.
 * Each card carries an 'ownership' flag: 'owned' or 'rented'.
 * Zwraca karty hubow nalezacych do gracza (kupione) lub wynajmowanych.
 * @return list<array<string, mixed>>
 */
    public function getMyHubCards(int $playerId): array
    {
        $cards = [];
        $seen  = [];

        $owned  = $this->hubSvc->getMyOwnedHubs($playerId);
        $rented = $this->hubSvc->getMyRentedHubs($playerId);

        foreach ([['owned', $owned], ['rented', $rented]] as [$ownership, $hubs]) {
            foreach ($hubs as $hub) {
                $hubId = (int)$hub['id'];
                if (isset($seen[$hubId])) {
                    continue;
                }
                $seen[$hubId] = true;

                $lastStats = $this->hubSvc->getLastTickStats($hubId);
                $cards[]   = [
                    'hub'          => $hub,
                    'wells'        => $this->hubSvc->getHubWellsForPlayer($hubId, $playerId),
                    'last_stats'   => $lastStats,
                    'opex'         => $this->econSvc->getOpex($hub),
                    'repair_cost'  => $this->econSvc->getRepairCost($hub),
                    'upgrade_cost' => $this->econSvc->getUpgradeCost($hub),
                    'status_class' => $this->getStatusCssClass($hub['status']),
                    'load_class'   => $this->getLoadCssClass((float)($lastStats['load_pct'] ?? 0)),
                    'ownership'    => $ownership,
                ];
            }
        }

        return $cards;
    }

 /**
 * Returns active logistics alerts for a player.
 * @return list<array{type: string, severity: string, hub_id: ?int, region_id: int, message: string}>
 */
    public function getAlerts(int $playerId): array
    {
        $alerts = [];

        foreach ($this->hubSvc->getPlayerHubs($playerId) as $hub) {
            $hubId     = (int)$hub['id'];
            $lastStats = $this->hubSvc->getLastTickStats($hubId);
            $regionId  = (int)$hub['region_id'];

            if ($hub['status'] === 'overloaded') {
                $alerts[] = ['type' => 'hub_overloaded',       'severity' => 'critical', 'hub_id' => $hubId, 'region_id' => $regionId,
                             'message' => t('logistics.hub.alert_overloaded', ['name' => (string)$hub['name']])];
            }

            $cond = (float)$hub['condition_pct'];
            if ($cond < 30.0) {
                $alerts[] = ['type' => 'hub_critical_condition', 'severity' => 'critical', 'hub_id' => $hubId, 'region_id' => $regionId,
                             'message' => t('logistics.hub.alert_critical', ['name' => (string)$hub['name'], 'pct' => number_format((float)$hub['condition_pct'], 1, ',', ' ')])];
            } elseif ($cond < 60.0) {
                $alerts[] = ['type' => 'hub_low_condition',      'severity' => 'warning',  'hub_id' => $hubId, 'region_id' => $regionId,
                             'message' => t('logistics.hub.alert_low_cond', ['name' => (string)$hub['name'], 'pct' => number_format((float)$hub['condition_pct'], 1, ',', ' ')])];
            }

            if ($lastStats && (float)$lastStats['lost_volume_bbl'] > 0) {
                $alerts[] = ['type' => 'hub_loss', 'severity' => 'warning', 'hub_id' => $hubId, 'region_id' => $regionId,
                             'message' => t('logistics.hub.alert_loss', ['name' => (string)$hub['name'], 'bbl' => number_format((float)$lastStats['lost_volume_bbl'], 1, ',', ' ')])];
            }

            if ((float)$hub['buffer_capacity_bbl'] > 0
                && (float)$hub['buffer_current_bbl'] >= (float)$hub['buffer_capacity_bbl'] * 0.9) {
                $alerts[] = ['type' => 'buffer_near_full', 'severity' => 'warning', 'hub_id' => $hubId, 'region_id' => $regionId,
                             'message' => t('logistics.hub.alert_buffer_near_full', ['name' => (string)$hub['name']])];
            }
        }

        $unassigned = $this->hubSvc->getUnassignedWells($playerId);
        if (!empty($unassigned)) {
            $alerts[] = ['type' => 'wells_without_hub', 'severity' => 'warning', 'hub_id' => null, 'region_id' => 0,
                         'message' => t('logistics.hub.alert_no_hubs', ['count' => count($unassigned)])];
        }

        return $alerts;
    }

 /**
 * Returns data for a single hub detail page (player-facing).
 * @return array<string, mixed>|null
 */
    public function getHubDetail(int $hubId, int $playerId): ?array
    {
        $hub = $this->hubSvc->getHub($hubId);
        if (!$hub) {
            return null;
        }

        return [
            'hub'             => $hub,
            'wells'           => $this->hubSvc->getHubWellsForPlayer($hubId, $playerId),
            'all_wells_count' => (int)$hub['assigned_count'],
            'last_stats'      => $this->hubSvc->getLastTickStats($hubId),
            'stats_history'   => $this->hubSvc->getTickStatsHistory($hubId, 24),
            'opex'            => $this->econSvc->getOpex($hub),
            'repair_cost'     => $this->econSvc->getRepairCost($hub),
            'upgrade_cost'    => $this->econSvc->getUpgradeCost($hub),
            'can_upgrade'     => $this->canUpgrade($hub),
            'status_class'    => $this->getStatusCssClass($hub['status']),
        ];
    }

 /**
 * Returns assignable hubs for a well (same region, annotated with slot and zone data).
 * @return list<array<string, mixed>>
 */
    public function getAssignableHubs(int $playerId, int $wellId): array
    {
        $stmt = $this->db->prepare("SELECT region_id, zone_key FROM wells WHERE id = ? AND player_id = ? LIMIT 1");
        $stmt->execute([$wellId, $playerId]);
        $well = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$well) {
            return [];
        }

        $list = [];
        foreach ($this->hubSvc->getRegionHubs((int)$well['region_id']) as $hub) {
 // Private ownership: only hubs the player owns or rents are assignable.
            $hubOwner  = (int)($hub['player_id']        ?? 0);
            $hubTenant = (int)($hub['tenant_player_id'] ?? 0);
            if ($hubOwner !== $playerId && $hubTenant !== $playerId) {
                continue;
            }

            $slotsUsed   = (int)$hub['assigned_count'];
            $slotLimit   = (int)$hub['slot_limit'];
            $zonePenalty = ($well['zone_key'] !== $hub['zone_key'] && $hub['zone_key'] !== '') ? 10.0 : 0.0;

 // Oplata per-slot jak gracz zaplaci za JEDN studnie w tym hubie.
 // Per-slot fee the player would pay for ONE well in this hub.
            $modeMultipliers = $this->hubSvc->getWorkModeMultipliers($hub['work_mode'] ?? 'standard');
            $opexMult  = (float)($modeMultipliers['opex_mult'] ?? 1.0);
            $condPct   = (float)($hub['condition_pct'] ?? 100.0);
            $condMult  = match(true) {
                $condPct <= 20.0 => 1.80,
                $condPct <= 30.0 => 1.50,
                $condPct <= 50.0 => 1.25,
                $condPct <  70.0 => 1.10,
                default          => 1.00,
            };
            $hubOpex   = $this->econSvc->getOpex($hub);
            $slotCost  = $hubOpex / max(1, $slotLimit);
            $usageFee  = round($slotCost, 2);

            $acqType     = (string)($hub['acquisition_type'] ?? 'new');
            $acqDefaults = $this->hubSvc->getAcquisitionDefaults($acqType);
            $leaseFee    = max(0.0, (float)($hub['lease_fee_per_tick'] ?? $acqDefaults['lease_fee_per_tick']));

 // One-time access fee shown before assignment (mirrors HubAssignmentService logic).
 // new : 5 ticks of per-slot opex
 // used : 8 ticks of per-slot opex
 // rental: 3 x lease_fee_per_slot (deposit)
            $accessFee = match($acqType) {
                'used'   => round($usageFee * 8.0, 2),
                'rental' => round($leaseFee * 3.0, 2),
                default  => round($usageFee * 5.0, 2),
            };

            $list[] = [
                'hub'          => $hub,
                'slots_used'   => $slotsUsed,
                'slots_avail'  => $slotLimit - $slotsUsed,
                'slots_full'   => $slotsUsed >= $slotLimit,
                'zone_penalty' => $zonePenalty,
                'status_class' => $this->getStatusCssClass($hub['status']),
                'usage_fee'    => $usageFee,
                'cond_mult'    => $condMult,
 // Acquisition type info for player UI
                'acq_type'      => $acqType,
                'acq_wear_mult' => (float)$acqDefaults['wear_mult'],
                'acq_risk_mult' => (float)$acqDefaults['risk_mult'],
                'acq_opex_mult' => (float)$acqDefaults['opex_mult'],
                'acq_start_min' => (int)$acqDefaults['start_condition_min'],
                'acq_start_max' => (int)$acqDefaults['start_condition_max'],
                'acq_lease_fee' => $leaseFee,
                'acq_access_fee'=> $accessFee,
            ];
        }

        return $list;
    }

 /**
 * Returns system hubs grouped by region for a player (well assignment browser).
 * @return list<array{region_id: int, region_name: string, hubs: list<array<string, mixed>>}>
 */
    public function getAvailableHubsByRegion(int $playerId): array
    {
        $result = [];

        foreach ($this->hubSvc->getPlayerRegionIds($playerId) as $regionId) {
            $hubs = $this->hubSvc->getRegionHubs($regionId);
            if (empty($hubs)) {
                continue;
            }

            $annotated = [];
            foreach ($hubs as $hub) {
                $slotsUsed   = (int)$hub['assigned_count'];
                $annotated[] = $hub + [
                    'slots_avail'  => max(0, (int)$hub['slot_limit'] - $slotsUsed),
                    'slots_full'   => $slotsUsed >= (int)$hub['slot_limit'],
                    'status_class' => $this->getStatusCssClass($hub['status']),
                ];
            }

            $result[] = [
                'region_id'   => $regionId,
                'region_name' => $hubs[0]['region_name'] ?? "Region #{$regionId}",
                'hubs'        => $annotated,
            ];
        }

        return $result;
    }

 /**
 * Returns MARKET hubs (player_id=0, tenant_player_id=0) grouped by the player's regions,
 * annotated with buy price and rent deposit for the acquisition UI ("rynek").
 * Zwraca huby rynkowe (do kupna/wynajmu) pogrupowane po regionach gracza.
 * @return list<array{region_id: int, region_name: string, hubs: list<array<string, mixed>>}>
 */
    public function getMarketHubsByRegion(int $playerId): array
    {
        $result = [];

        foreach ($this->hubSvc->getPlayerRegionIds($playerId) as $regionId) {
            $hubs = $this->hubSvc->getMarketHubs($regionId);
            if (empty($hubs)) {
                continue;
            }

            $regionName = $hubs[0]['region_name'] ?? "Region #{$regionId}";
            $annotated  = [];
            foreach ($hubs as $hub) {
                $slotsUsed   = (int)($hub['assigned_count'] ?? 0);
                $slotLimit   = (int)($hub['slot_limit'] ?? 0);
                $leaseFee    = (float)($hub['lease_fee_per_tick'] ?? 0.0);
                $buyPrice    = (float)(($hub['acquisition_price'] ?? 0) > 0
                                ? $hub['acquisition_price']
                                : $hub['build_cost']);

                $annotated[] = $hub + [
                    'slots_avail'  => max(0, $slotLimit - $slotsUsed),
                    'slots_full'   => $slotLimit > 0 && $slotsUsed >= $slotLimit,
                    'status_class' => $this->getStatusCssClass($hub['status']),
                    'buy_price'    => round($buyPrice, 2),
                    'rent_deposit' => round($leaseFee * 3.0, 2),
                    'lease_fee'    => round($leaseFee, 2),
                ];
            }

            $result[] = [
                'region_id'   => $regionId,
                'region_name' => $regionName,
                'hubs'        => $annotated,
            ];
        }

        return $result;
    }

 // CSS helpers

    private function getStatusCssClass(string $status): string
    {
        return match($status) {
            'active'     => 'badge-green',
            'overloaded' => 'badge-orange',
            'damaged'    => 'badge-red',
            'paused'     => 'badge-yellow',
            'building'   => 'badge-blue',
            'disabled'   => 'badge-red',
            default      => '',
        };
    }

    private function getLoadCssClass(float $loadPct): string
    {
        if ($loadPct > 100.0) return 'text-red';
        if ($loadPct > 80.0)  return 'text-orange';
        if ($loadPct > 50.0)  return 'text-yellow';
        return 'text-green';
    }

 /** @param array<string, mixed> $hub */
    private function canUpgrade(array $hub): bool
    {
        $defaults = $this->hubSvc->getHubTypeDefaults($hub['hub_type'], (int)$hub['level']);
        return (int)$hub['level'] < $defaults['max_level'];
    }
}
