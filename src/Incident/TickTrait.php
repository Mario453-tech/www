<?php

/**
 * TickTrait - main incident tick logic and worker error-rate calculation.
 * TickTrait - glowna logika ticku incydentow i obliczanie error-rate pracownika.
 */
trait IncidentTickTrait
{
 /**
 * Main entry point called from tick.php per well and per player.
 * Glowna metoda wywolywana z tick.php per odwiert i per gracz.
 *
 * @param int $wellId
 * @param int $playerId
 * @param float $deltaHours
 * @param array<string, mixed> $wellData
 * @param array<string, mixed> $staffData
 * @param array<string, mixed> $hseBonus
 * @return array<string, mixed>
 */
    public function processTick(
        int $wellId,
        int $playerId,
        float $deltaHours,
        array $wellData,
        array $staffData = [],
        array $hseBonus = []
    ): array {
        $cond = (float) ($wellData['technical_condition'] ?? 100);
        $riskScore = (float) ($wellData['risk_score'] ?? 0);
        $hseActive = !empty($hseBonus['active_hse']);

 // Calculate human error rates per role.
 // Oblicz error-rate ludzi per rola.
        $opSkill = isset($staffData['operator_skill']) ? (int) $staffData['operator_skill'] : null;
        $techSkill = isset($staffData['technician_skill']) ? (int) $staffData['technician_skill'] : null;

        $opErrorRate = $this->calcErrorRate($opSkill, !empty($staffData['no_technician']));
        $techErrorRate = $this->calcErrorRate($techSkill, false);

 // Spiral and wear modifiers.
 // Modyfikatory spirali i zuzycia.
        $spiralBoost = (float) ($wellData['post_incident_risk_boost'] ?? 0.0);
        $spiralMult = isset($staffData['spiral_mult'])
            ? (float) $staffData['spiral_mult']
            : (1.0 + $spiralBoost / 100.0);
        $wearMult = (float) ($staffData['wear_mult'] ?? 1.0);

 // Spiral increases human error under pressure.
 // Spirala zwieksza chaos ludzi pod presja.
        $errorSpiralMult = 1.0 + ($spiralBoost / 200.0);

 // Equipment tier incident multiplier.
 // Mnoznik awarii od tieru sprzetu.
        $eqTier = $wellData['equipment_tier'] ?? 'standard';
        $eqMults = WellService::getEquipmentMultipliers(
            $eqTier,
            (int) ($wellData['equipment_upgrade_level'] ?? 0)
        );
        $equipIncidentMult = $eqMults['incident'];

 // Geological layer incident risk multiplier.
 // Mnoznik ryzyka awarii od warstwy geologicznej.
        $layerRiskMult = 1.0;
        if (class_exists('GeologicalLayerService')) {
            $layerSvc = new GeologicalLayerService();
            $layerMults = $layerSvc->getLayerMultipliers($wellId, $eqTier);
            $layerRiskMult = $layerMults['risk_mult'];
        }

 // Reservoir depletion raises incident risk.
 // Wyczerpanie zloza podnosi ryzyko awarii.
        $depletionIncidentMult = 1.0;
        if (class_exists('WellService')) {
            $deplData = WellService::getEffectivePressure($wellData);
            $depletion = $deplData['depletion'];
            if ($depletion < 0.30) {
                $depletionIncidentMult = 1.40;
            } elseif ($depletion < 0.50) {
                $depletionIncidentMult = 1.20;
            }
        }

 // Chance modifiers.
 // Modyfikatory szans.
        $condMult = 1.0 + ((100 - $cond) / 100) * 2.0;
        $riskMult = 1.0 + ($riskScore / 100);
        $hseMult = $hseActive ? ($hseBonus['failure_reduction'] ?? 1.0) : 1.0;

        GameLog::step('IncidentService', 'processTick', 1, 'start', [
            'well_id' => $wellId,
            'cond' => round($cond, 1),
            'risk_score' => round($riskScore, 1),
            'op_skill' => $opSkill,
            'tech_skill' => $techSkill,
            'op_error_rate' => round($opErrorRate, 4),
            'hse_active' => $hseActive,
            'hse_mult' => round($hseMult, 3),
            'cond_mult' => round($condMult, 3),
            'risk_mult' => round($riskMult, 3),
            'spiral_mult' => round($spiralMult, 3),
            'wear_mult' => round($wearMult, 3),
            'error_spiral' => round($errorSpiralMult, 3),
            'eq_tier' => $wellData['equipment_tier'] ?? 'standard',
            'eq_incident_mult' => round($equipIncidentMult, 3),
            'layer_risk_mult' => round($layerRiskMult, 3),
            'depletion_inc_mult' => round($depletionIncidentMult, 3),
            'delta_h' => round($deltaHours, 4),
        ]);

        foreach ($this->levelCfg as $level => $cfg) {
            $baseChance = $this->baseChance[$level] * $deltaHours;

 // Apply condition, risk, wear, spiral, equipment, layer, and transport modifiers.
 // Zastosuj modyfikatory: stan, risk, wear, spirala, sprzet, warstwa i transport.
            $transportMult = (float) ($staffData['transport_incident_mult'] ?? 1.0);
            $chance = $baseChance * $condMult * $riskMult * $wearMult * $spiralMult * $equipIncidentMult * $layerRiskMult * $transportMult * $depletionIncidentMult;

 // Add human error rate; spiral amplifies mistakes.
 // Dodaj error-rate ludzi; spirala wzmacnia bledy.
            if ($opSkill !== null) {
                $chance += $opErrorRate * $errorSpiralMult * $baseChance * 0.5;
            }
            if ($techSkill !== null) {
                $chance += $techErrorRate * $errorSpiralMult * $baseChance * 0.3;
            }

 // Perks reduce incident and catastrophe exposure.
 // Perki zmniejszaja ekspozycje na incydenty i katastrofy.
            $perkIncRed = (float) ($staffData['perk_incident_reduction'] ?? 0.0);
            if ($perkIncRed > 0) {
                $chance *= max(0.1, 1.0 - $perkIncRed);
            }

            $perkCatRed = (float) ($staffData['perk_catastrophe_reduction'] ?? 0.0);
            if ($perkCatRed > 0 && in_array($level, ['major', 'medium'], true)) {
                $chance *= max(0.1, 1.0 - $perkCatRed);
            }

 // HSE reduces chance; the floor applies only to micro incidents.
 // BHP redukuje szanse; floor obowiazuje tylko dla micro.
            $chance *= $hseMult;
            GameLog::step('IncidentService', 'processTick', 2, "chance_{$level}", [
                'well_id' => $wellId,
                'chance' => round($chance * 100, 4) . '%',
            ]);

 // Roll the chance.
 // Losowanie.
            if ((mt_rand(1, 100000) / 100000.0) > $chance) {
                continue;
            }

 // Incident happened.
 // Zdarzenie wystapilo.
            $incident = $this->generateIncident($level, $cfg, $wellId, $playerId, $wellData, $staffData, $hseBonus);
            $this->saveIncident($incident);
            $this->applyEffects($incident, $wellId, $playerId);

            GameLog::info('IncidentService', "incident_{$level}", [
                'well_id' => $wellId,
                'player_id' => $playerId,
                'type' => $incident['cause_type'],
                'drop' => $incident['prod_drop'],
                'cost' => $incident['cost'],
            ]);

            return ['incident' => array_merge($incident, ['spiral_boost' => $spiralBoost])];
        }

        GameLog::step('IncidentService', 'processTick', 3, 'no_incident', ['well_id' => $wellId]);
        return ['incident' => null];
    }

 /**
 * Calculate worker error rate.
 * Liczy error-rate pracownika.
 *
 * skill 1 -> 12%, skill 5 -> 6%, skill 10 -> 2%
 * Brak technika -> +30% do error-rate operatora
 */
    public function calcErrorRate(?int $skill, bool $noTechnician = false): float
    {
        if ($skill === null) {
            return 0.0;
        }

 // Linear interpolation: skill 1 -> 0.12, skill 10 -> 0.02.
 // Interpolacja liniowa: skill 1 -> 0.12, skill 10 -> 0.02.
        $rate = 0.12 - ($skill - 1) * (0.10 / 9);
        $rate = max(0.02, min(0.12, $rate));

        if ($noTechnician) {
            $rate += 0.30;
        }

        return $rate;
    }
}
