<?php
trait WellCostsTrait
{
    /**
     * Koszt zakupu nowego odwiertu
     * Cost of purchasing a new well.
     */
    public function getDrillingCost(string $type = 'onshore'): float
    {
        return $type === 'offshore'
            ? $this->cfg('drilling_cost_offshore', 40_000_000)
            : $this->cfg('drilling_cost_onshore',   8_000_000);
    }

    /**
     * OPEX  koszt operacyjny na godzine
     * OPEX  operating cost per hour.
     */
    public function getOpexPerHour(array $well): float
    {
        $base = (float)($well['upkeep_cost_per_hour'] ?? $this->cfg('opex_per_hour', 1458.33));

        // Regionalny mnoznik OPEX  logistyka, infrastruktura, dostepnosc serwisow
        // Regional OPEX multiplier  logistics, infrastructure, service availability.
        // Dane: region_opex_mult zapisany w well przy zakupie LUB pobierany z regionu
        // Data: region_opex_mult saved on well at purchase OR fetched from region.
        $regionOpexMult = (float)($well['region_opex_mult'] ?? 1.0);
        if ($regionOpexMult > 0 && $regionOpexMult !== 1.0) {
            $base *= $regionOpexMult;
        }

        return round($base, 2);
    }

    /**
     * Koszt konserwacji: (100 - stan_techniczny)  mnoznik
     * Maintenance cost: (100 - technical_condition)  multiplier.
     */
    public function getMaintenanceCost(array $well): float
    {
        $condition = (int)($well['technical_condition'] ?? 100);
        $mult      = $this->cfg('maintenance_multiplier', 15000);
        return max(0, (100 - $condition)) * $mult;
    }

    /**
     * Koszt naprawy awarii: (100 - stan_techniczny)  mnoznik
     * Repair cost: (100 - technical_condition)  multiplier.
     */
    public function getRepairCost(array $well): float
    {
        $condition = (int)($well['technical_condition'] ?? 100);
        $mult      = $this->cfg('repair_multiplier', 60000);
        return max(0, (100 - $condition)) * $mult;
    }

    /**
     * Oblicza efektywne cisnienie odwiertu.
     * Calculates effective well pressure.
     *
     * effective_pressure = base_pressure  depletion_factor  equipment_factor
     *
     * depletion_factor = reservoir_remaining / reservoir_max (1.0  0.1)
     * equipment_factor:
     *   premium         10% wolniejszy spadek ( 0.90)
     *   black_market    +20% szybszy spadek ( 1.20)
     *
     * Nie zapisywane w DB  zawsze liczone w locie.
     * Not stored in DB  always computed on the fly.
     *
     * @return array{effective: float, depletion: float, base: float}
     */
    public static function getEffectivePressure(array $well): array
    {
        $base      = (float)($well['pressure'] ?? 1.0);
        $remaining = (float)($well['reservoir_remaining'] ?? 0);
        $resMax    = (float)($well['reservoir_max']       ?? 800_000);

        // depletion_factor: min 0.1 eby odwiert nigdy w peni nie zamarl
        // depletion_factor: min 0.1 so well never fully dies from depletion alone
        $depletion = $resMax > 0
            ? max(0.10, min(1.0, $remaining / $resMax))
            : 1.0;

        // equipment_factor  sprzet zwalnia lub przyspiesza degradacj wydobycia
        // equipment_factor  gear slows or speeds up production decline
        $tier = $well['equipment_tier'] ?? 'standard';
        $eqFactor = match($tier) {
            'premium'      => 1.0 + (1.0 - $depletion) * 0.10,  // premium lagodzi spadek / premium softens decline
            'black_market' => 1.0 - (1.0 - $depletion) * 0.20,  // czarny rynek przyspiesza / black market accelerates
            default        => 1.0,
        };
        $eqFactor = max(0.50, min(1.20, $eqFactor));

        $effective = $base * $depletion * $eqFactor;

        return [
            'effective' => round($effective, 4),
            'depletion' => round($depletion, 4),
            'base'      => $base,
        ];
    }

    /**
     * Efektywna produkcja z uwzglednieniem modernizacji.
     * Effective production including all modifiers.
     *
     * Kolejnosc mnoznikow (wg README sekcja 4  NIE zmienia kolejnosci): / Multiplier order (per README section 4  do NOT change order):
     *   base_production_per_hour
     *    equipment tier + upgrade level
     *    effective_pressure (base_pressure  depletion_factor  equipment_factor)
     *    production_boost_pct
     *    oil_richness (soft cap > 2.0)
     *    region_production_bonus (max +10%)
     *    production_mode (eco/normal/boost)
     *    global_production_multiplier (z well_config)
     */
    public function getEffectiveProduction(array $well): float
    {
        try {
            $base = (float)$well['base_production_per_hour'];

            // 1. Equipment tier + upgrade level
            $tier    = $well['equipment_tier']              ?? 'standard';
            $upgLvl  = (int)($well['equipment_upgrade_level'] ?? 0);
            $eqMults = self::getEquipmentMultipliers($tier, $upgLvl);
            $base   *= $eqMults['prod'];

            // 2. Effective pressure (base  depletion  equipment depletion factor)
            // Zastepuje raw pressure  uwzeglednia zuzycie zoza.
            // Replaces raw pressure  accounts for reservoir depletion.
            $effPressure = self::getEffectivePressure($well)['effective'];
            if ($effPressure !== 1.0) $base *= $effPressure;
            
            // 3. Tymczasowy boost produkcji / Temporary production boost
            $boostPct = (float)($well['production_boost_pct'] ?? 0.0);
            if ($boostPct > 0) $base *= (1 + $boostPct / 100);
            
            // 4. Oil richness  SOFT CAP powyzej 2.0 / above 2.0
            $richness = (float)($well['oil_richness'] ?? 1.0);
            if ($richness > 0) {
            $richnessMult = $richness <= 2.0
            ? $richness
            : 2.0 + ($richness - 2.0) * 0.5;
            $base *= $richnessMult;
            }
            
            // 5. Bonus produkcyjny regionu (np. Middle East +40%)  soft cap 10% / Regional production bonus (e.g. Middle East +40%)  soft cap 10%
            $regionBonus = (float)($well['region_production_bonus'] ?? 0.0);
            if ($regionBonus > 0) {
            $base *= (1 + min($regionBonus, 0.10));
            }
            
            // 6. Tryb produkcji / Production mode
            $mode = $well['production_mode'] ?? 'normal';
            $base *= match($mode) {
            'eco'   => 0.70,
            'boost' => 1.40,
            default => 1.00,
            };
            
            // 7. Globalny mnoznik produkcji (z well_config, admin/balance.php) / Global production multiplier (from well_config, admin/balance.php)
            $globalMult = $this->cfg('global_production_multiplier', 1.0);
            if ($globalMult !== 1.0) $base *= $globalMult;
            
            return max(0.0, $base);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('WellService', 'getEffectiveProduction FAILED', $e);
            }
            return 0.0;
        }
    }
}