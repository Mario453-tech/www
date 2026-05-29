<?php

/**
 * HubEconomyService build costs, OPEX, repairs, upgrades, and economic viability.
 */
class HubEconomyService
{
    private HubService $hubSvc;

    public function __construct(HubService $hubSvc)
    {
        $this->hubSvc = $hubSvc;
    }

 /**
 * Returns full build cost with regional modifier applied.
 *
 * @param string $hubType small|medium|large
 * @param int $regionId world_regions.id
 * @param int $level starting level (always 1 for new hubs)
 */
    public function getBuildCost(string $hubType, int $regionId = 0, int $level = 1): float
    {
        $defaults    = $this->hubSvc->getHubTypeDefaults($hubType, $level);
        $base        = $defaults['build_cost'];
        $regionMult  = $this->getRegionBuildCostMult($regionId);
        return round($base * $regionMult, 2);
    }

 /**
 * Returns upgrade cost for the next level of a hub.
 * @param array<string, mixed> $hub
 */
    public function getUpgradeCost(array $hub): float
    {
        $defaults = $this->hubSvc->getHubTypeDefaults($hub['hub_type'], (int)$hub['level']);
        return (float)$defaults['upgrade_cost'];
    }

 /**
 * Returns current repair cost for a hub.
 * @param array<string, mixed> $hub
 */
    public function getRepairCost(array $hub): float
    {
        return $this->hubSvc->getRepairCost($hub);
    }

 /**
 * Returns OPEX per tick for a hub, accounting for work mode multiplier
 * and condition penalty (bad condition = higher maintenance cost).
 *
 * Condition multipliers:
 * 70% 1.00 (normal)
 * 5070% 1.10
 * 3050% 1.25
 * 2030% 1.50
 * 20% 1.80 (critical frequent breakdowns, emergency repairs)
 *
 * @param array<string, mixed> $hub
 */
    public function getOpex(array $hub): float
    {
        $modeMultipliers = $this->hubSvc->getWorkModeMultipliers($hub['work_mode'] ?? 'standard');
        $opexMult        = $modeMultipliers['opex_mult'] ?? 1.0;
        $condPct         = (float)($hub['condition_pct'] ?? 100.0);
        $acqType         = (string)($hub['acquisition_type'] ?? 'new');
        $acqDefaults     = $this->hubSvc->getAcquisitionDefaults($acqType);

        $condOpexMult = match(true) {
            $condPct <= 20.0 => 1.80,
            $condPct <= 30.0 => 1.50,
            $condPct <= 50.0 => 1.25,
            $condPct <  70.0 => 1.10,
            default          => 1.00,
        };

        $baseOpex = (float)$hub['opex_per_tick'] * $opexMult * $condOpexMult * (float)$acqDefaults['opex_mult'];
        $leaseFee = max(0.0, (float)($hub['lease_fee_per_tick'] ?? $acqDefaults['lease_fee_per_tick']));

        return round($baseOpex + $leaseFee, 2);
    }

 /**
 * Returns a summary of costs for a hub type before building.
 *
 * @return array{
 * hub_type: string,
 * build_cost: float,
 * opex_per_tick: float,
 * opex_per_day: float,
 * nominal_bph: float,
 * buffer_bbl: float,
 * slot_limit: int,
 * upgrade_cost: float,
 * max_level: int
 * }
 */
    public function getBuildSummary(string $hubType, int $regionId = 0): array
    {
        $defaults = $this->hubSvc->getHubTypeDefaults($hubType, 1);
        return [
            'hub_type'      => $hubType,
            'build_cost'    => $this->getBuildCost($hubType, $regionId),
            'opex_per_tick' => $defaults['opex_per_tick'],
            'opex_per_day'  => $defaults['opex_per_tick'] * 24.0,
            'nominal_bph'   => $defaults['nominal_bph'],
            'buffer_bbl'    => $defaults['buffer_bbl'],
            'slot_limit'    => $defaults['slot_limit'],
            'upgrade_cost'  => $defaults['upgrade_cost'],
            'max_level'     => $defaults['max_level'],
        ];
    }

 /**
 * Checks if a hub is economically viable (earns more than OPEX from throughput).
 * Returns a ratio: >1.0 = profitable, <1.0 = losing money on logistics.
 *
 * @param array<string, mixed> $hub
 * @param list<array<string, mixed>> $recentStats last N tick stats rows
 * @param float $oilPricePln current oil price in PLN/bbl
 */
    public function getViabilityRatio(array $hub, array $recentStats, float $oilPricePln): float
    {
        if (empty($recentStats)) {
            return 0.0;
        }

        $avgProcessed = 0.0;
        foreach ($recentStats as $stat) {
            $avgProcessed += (float)$stat['processed_volume_bbl'];
        }
        $avgProcessed /= count($recentStats);

        $opex = $this->getOpex($hub);
        if ($opex <= 0.0) {
            return 99.0;
        }

        $revenue = $avgProcessed * $oilPricePln;
        return round($revenue / $opex, 3);
    }

    private function getRegionBuildCostMult(int $regionId): float
    {
        if ($regionId <= 0) {
            return 1.0;
        }
 // Per-region multiplier stored in logistics_hub_config
 // group='region', key='{region_id}.build_cost_mult', scope='global'
        $val = $this->hubSvc->cfg('region', $regionId . '.build_cost_mult', '1.0');
        return max(0.1, (float)$val);
    }
}
