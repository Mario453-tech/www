<?php
trait WellTickTrait
{
    /**
     * Each active well degrades by 0.1% per hour and low condition raises failure risk.
     * Kazdy aktywny odwiert degraduje sie o 0.1% na godzine, a niski stan podnosi ryzyko awarii.
     *
     * @param array<string, mixed> $hseBonus Optional bonus array from getHSEBonus()
     * @return array<string, mixed>
     */
    public function processDegradation(int $wellId, float $deltaHours, array $hseBonus = [], float $techMult = 1.0): array
    {
        $stmt = $this->db->prepare("
            SELECT w.*, wr.political_risk AS region_political_risk
            FROM wells w
            LEFT JOIN world_regions wr ON wr.id = w.region_id
            WHERE w.id = ?
        ");
        $stmt->execute([$wellId]);
        $well = $stmt->fetch();

        // Degradation applies to active and paused operational states.
        // Degradacja dziala dla aktywnych i wstrzymanych stanow operacyjnych.
        // NIE dla: seized, blowout (juz zniszczony)
        if (!$well || in_array($well['status'], ['seized', 'blowout'])) {
            return [];
        }

        $upgrades = $this->getInstalledUpgrades($wellId);

        // Base degradation is 0.1% per hour; monitoring and HSE can reduce it.
        // Bazowa degradacja to 0.1% na godzine; monitoring i BHP moga ja zmniejszyc.
        $degradeRate = 0.10;
        if (in_array('monitoring', $upgrades, true)) {
            $degradeRate *= 0.70;
        }
        $degradeRate *= ($hseBonus['degrade_mult'] ?? 1.0);

        // Missing staff globally causes much faster degradation.
        // Brak personelu globalnie daje duzo szybsza degradacje.
        if ($well['status'] === 'paused_staff') {
            $degradeRate *= 10.0;
        }

        // Missing technician on the well adds extra degradation.
        // Brak technika odwiertu dodaje dodatkowa degradacje.
        $degradeRate *= $techMult;

        // Political risk accelerates degradation in harder operating regions.
        // Ryzyko polityczne przyspiesza degradacje w trudniejszych regionach.
        // political_risk 1->x1.0 | 2->x1.07 | 3->x1.15 | 4->x1.22 | 5->x1.30
        $politicalRisk = (int) ($well['region_political_risk'] ?? 1);
        $politMult = [1 => 1.00, 2 => 1.07, 3 => 1.15, 4 => 1.22, 5 => 1.30][$politicalRisk] ?? 1.0;
        $degradeRate *= $politMult;

        // Regional stability bonus can slow degradation.
        // Bonus stabilnosci regionu moze spowolnic degradacje.
        $stabilityBonus = (float) ($well['region_stability_bonus'] ?? 1.0);
        if ($stabilityBonus > 0 && $stabilityBonus !== 1.0) {
            $degradeRate *= $stabilityBonus;
        }

        // Post-disaster spiral increases future degradation.
        // Spirala po katastrofie zwieksza przyszla degradacje.
        $postDisasterBoost = (float) ($well['post_disaster_risk_boost'] ?? 0.0);
        if ($postDisasterBoost > 0) {
            // Check whether the temporary boost has expired.
            // Sprawdz, czy czasowy boost juz wygasl.
            $expiresAt = $well['post_disaster_expires_at'] ?? null;
            if ($expiresAt && strtotime($expiresAt) > time()) {
                $degradeRate *= (1.0 + $postDisasterBoost);
            } else {
                // Expired - clear stored values.
                // Wygasl - wyczysc zapisane wartosci.
                $this->db->prepare("
                    UPDATE wells
                    SET post_disaster_risk_boost = 0, post_disaster_expires_at = NULL
                    WHERE id = ?
                ")->execute([$wellId]);
            }
        }

        $condBefore = (int) $well['technical_condition'];
        $condAfter = max(0, $condBefore - ($degradeRate * $deltaHours));

        // Condition at 0% forces the well into broken state.
        // Stan 0% wymusza przejscie odwiertu w broken.
        if ($condAfter <= 0 && $well['status'] === 'active') {
            $this->db->prepare("UPDATE wells SET status = 'broken', technical_condition = 0 WHERE id = ?")
                ->execute([$wellId]);
            $this->logEvent(
                $wellId,
                $well['player_id'],
                'failure',
                0,
                t('well.tick_msg.condition_zero_broken'),
                $condBefore,
                0
            );
            return ['failure' => true, 'condition' => 0];
        }

        $failureOccurred = false;
        if ($condAfter < 70) {
            // Base failure chance grows for each percent below 70 condition.
            // Bazowa szansa awarii rosnie za kazdy procent ponizej 70 stanu.
            $failureChance = (70 - $condAfter) * 0.005;
            if (in_array('monitoring', $upgrades, true)) {
                $failureChance *= 0.70;
            }

            // HSE lowers the failure chance.
            // BHP zmniejsza szanse awarii.
            $failureChance *= ($hseBonus['failure_reduction'] ?? 1.0);

            if (mt_rand(1, 10000) <= (int) ($failureChance * 10000)) {
                $failureOccurred = true;

                // HSE can reduce repair cost.
                // BHP moze obnizyc koszt naprawy.
                $repairCostBase = (int) ($condBefore * 5000);
                $repairCost = (int) round($repairCostBase * ($hseBonus['repair_cost_mult'] ?? 1.0));

                // Failure removes condition and pauses the well.
                // Awaria zabiera stan i pauzuje odwiert.
                $condAfter = max(1, $condAfter - 20);
                $this->db->prepare("UPDATE wells SET status = 'paused_cash' WHERE id = ?")->execute([$wellId]);
                $this->logEvent(
                    $wellId,
                    $well['player_id'],
                    'failure',
                    $repairCost,
                    t('well.tick_msg.failure_condition', [
                        'from' => $condBefore,
                        'to' => round($condAfter),
                    ]) . (isset($hseBonus['active_hse']) && $hseBonus['active_hse'] > 0 ? t('well.tick_msg.failure_hse_suffix') : ''),
                    $condBefore,
                    (int) round($condAfter)
                );
            }
        }

        // Blowout chance appears only at very poor condition.
        // Szansa blowout pojawia sie tylko przy bardzo slabym stanie.
        if ($condAfter < 30 && !$failureOccurred) {
            $blowoutChance = (30 - $condAfter) * 0.0002; // 0.02% za kazdy % ponizej 30
            $blowoutChance *= ($hseBonus['catastrophe_mult'] ?? 1.0);

            if (mt_rand(1, 1000000) <= (int) ($blowoutChance * 1000000)) {
                // Blowout is catastrophic and nearly destroys the well.
                // Blowout jest katastrofalny i niemal niszczy odwiert.
                $condAfter = 1;
                $this->db->prepare("UPDATE wells SET status = 'paused_cash', technical_condition = 1 WHERE id = ?")->execute([$wellId]);
                $this->logEvent(
                    $wellId,
                    $well['player_id'],
                    'failure',
                    0,
                    t('well.tick_msg.blowout_shutdown')
                    . (isset($hseBonus['active_hse']) && $hseBonus['active_hse'] > 0 ? t('well.tick_msg.blowout_hse_suffix') : t('well.tick_msg.blowout_no_hse_suffix')),
                    $condBefore,
                    1
                );
                $failureOccurred = true;
            }
        }

        $this->db->prepare("UPDATE wells SET technical_condition = ? WHERE id = ?")->execute([round($condAfter, 1), $wellId]);

        return ['failure' => $failureOccurred, 'condition' => round($condAfter, 1)];
    }

    /**
     * Accumulate well risk_score during the tick.
     * Akumuluje risk_score odwiertu podczas ticka.
     *
     * Formula:
     * Wzor:
     *   delta_risk  = wear_factor x 0.2
     *   delta_risk += stress_factor x 0.3
     *   delta_risk += (1 - safety_level) x 0.5
     *   delta_risk *= deltaHours x mode_multiplier
     *
     * Natural decay when condition > 80 and HSE is active: -0.1/h
     * risk_score utrzymywany w zakresie 0-100.
     */
    public function updateRiskScore(int $wellId, float $deltaHours, array $hseBonus = []): void
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM wells WHERE id = ?");
            $stmt->execute([$wellId]);
            $well = $stmt->fetch();
            if (!$well || !in_array($well['status'], ['active', 'contaminated', 'paused_staff', 'paused_cash', 'paused_storage'], true)) {
                return;
            }

            $cond = (float) $well['technical_condition'];
            $pressure = (float) ($well['pressure'] ?? 1.0);
            $riskNow = (float) ($well['risk_score'] ?? 0);
            $mode = $well['production_mode'] ?? 'normal';
            $hseActive = ($hseBonus['active_hse'] ?? 0) > 0;

            // Risk components (0.0-1.0 each).
            // Skladowe ryzyka (0.0-1.0 kazda).
            $wearFactor = max(0, (100 - $cond) / 100);    // brak serwisu
            $stressFactor = max(0, min(1, $pressure - 1.0)); // nadcisnienie
            $safetyLevel = 1.0 - ($hseBonus['failure_reduction'] ?? 1.0); // BHP (0=brak, 1=max)

            $deltaRisk = $wearFactor * 0.2;
            $deltaRisk += $stressFactor * 0.3;
            $deltaRisk += (1 - $safetyLevel) * 0.5;
            $deltaRisk *= $deltaHours;

            // Production mode modifier.
            // Modyfikator trybu produkcji.
            $modeMult = match ($mode) {
                'eco' => 0.80,
                'boost' => 1.30,
                default => 1.00,
            };
            $deltaRisk *= $modeMult;

            // Natural decrease when the well is healthy and HSE is active.
            // Naturalny spadek, gdy odwiert jest zadbany i BHP aktywne.
            if ($cond > 80 && $hseActive) {
                $deltaRisk -= 0.10 * $deltaHours;
            }

            $newRisk = round(min(100, max(0, $riskNow + $deltaRisk)), 2);

            $this->db->prepare("UPDATE wells SET risk_score = ? WHERE id = ?")
                ->execute([$newRisk, $wellId]);

            if (abs($newRisk - $riskNow) >= 1.0) {
                GameLog::info('WellService', 'updateRiskScore', [
                    'well_id' => $wellId,
                    'risk_prev' => $riskNow,
                    'risk_new' => $newRisk,
                    'delta' => round($deltaRisk, 3),
                    'mode' => $mode,
                    'hse' => $hseActive,
                ]);
            }
        } catch (Throwable $e) {
            GameLog::error('WellService', 'updateRiskScore FAILED', $e, ['well_id' => $wellId]);
        }
    }

    /**
     * Add spiral boost after an incident.
     * Dodaje boost spirali po incydencie.
     *
     * minor +1, medium +6, major +15; cap=50; BHP redukuje gain.
     */
    public function addSpiralBoost(int $wellId, string $incidentLevel, array $hseBonus = [], float $transportSpiralMult = 1.0): float
    {
        $boostMap = ['micro' => 0.0, 'minor' => 1.0, 'medium' => 6.0, 'major' => 15.0];
        $add = (float) ($boostMap[$incidentLevel] ?? 0.0);
        if ($add <= 0.0) {
            return 0.0;
        }

        try {
            $stmt = $this->db->prepare("SELECT post_incident_risk_boost, equipment_tier, equipment_upgrade_level FROM wells WHERE id = ? LIMIT 1");
            $stmt->execute([$wellId]);
            $wellRow = $stmt->fetch();
            $current = (float) ($wellRow['post_incident_risk_boost'] ?? 0.0);
            $eqMults = self::getEquipmentMultipliers(
                $wellRow['equipment_tier'] ?? 'standard',
                (int) ($wellRow['equipment_upgrade_level'] ?? 0)
            );

            $hseActive = ($hseBonus['active_hse'] ?? 0) > 0;
            $hseProcFact = (float) ($hseBonus['proc_factor'] ?? 1.0);
            $hseReduction = $hseActive ? min(0.5, ($hseProcFact - 1.0) * 0.3 + 0.15) : 0.0;
            $add *= (1.0 - $hseReduction);
            $add *= $eqMults['spiral'];
            $add *= $transportSpiralMult; // transport: rurociag x0.95, tanker x1.05, ciezarowki x1.10

            $newBoost = min(50.0, round($current + $add, 3));

            $this->db->prepare("UPDATE wells SET post_incident_risk_boost = ? WHERE id = ?")
                ->execute([$newBoost, $wellId]);

            GameLog::step('WellService', 'addSpiralBoost', 1, 'boost_added', [
                'well_id' => $wellId,
                'level' => $incidentLevel,
                'add' => round($add, 2),
                'before' => round($current, 2),
                'after' => round($newBoost, 2),
            ]);

            return $newBoost;
        } catch (Throwable $e) {
            GameLog::error('WellService', 'addSpiralBoost FAILED', $e, ['well_id' => $wellId]);
            return 0.0;
        }
    }

    /**
     * Decay spiral boost on every tick.
     * Wygasza boost spirali na kazdym ticku.
     *
     * Bazowy decay: 0.4/h; BHP (proc_factor) przyspiesza powrot.
     */
    public function processSpiralDecay(int $wellId, float $deltaHours, array $hseBonus = []): float
    {
        try {
            $stmt = $this->db->prepare("SELECT post_incident_risk_boost FROM wells WHERE id = ? LIMIT 1");
            $stmt->execute([$wellId]);
            $boost = (float) ($stmt->fetchColumn() ?? 0.0);

            if ($boost <= 0.0) {
                return 0.0;
            }

            $decayPerHour = 0.4;
            $hseFactor = max(1.0, (float) ($hseBonus['proc_factor'] ?? 1.0));
            $decay = $decayPerHour * $deltaHours * $hseFactor;
            $newBoost = max(0.0, round($boost - $decay, 3));

            $this->db->prepare("UPDATE wells SET post_incident_risk_boost = ? WHERE id = ?")
                ->execute([$newBoost, $wellId]);

            if ($boost > 0 && $newBoost === 0.0) {
                GameLog::info('WellService', 'spiral_boost_cleared', ['well_id' => $wellId]);
            }

            return $newBoost;
        } catch (Throwable $e) {
            GameLog::error('WellService', 'processSpiralDecay FAILED', $e, ['well_id' => $wellId]);
            return 0.0;
        }
    }

    /**
     * Accumulate operational wear for the well.
     * Akumuluje zuzycie eksploatacyjne odwiertu.
     *
     * wear_level 0->100 (soft cap).
     * Wzrost: base x richness x incident_mult x spiral_mult
     */
    public function processWear(
        int $wellId,
        float $deltaHours,
        float $productionPerHour,
        float $oilRichness = 1.0,
        bool $hadIncident = false,
        float $spiralMult = 1.0
    ): float {
        try {
            // Use one SELECT instead of two for the same row.
            // Uzyj jednego SELECT zamiast dwoch dla tego samego wiersza.
            $stmt = $this->db->prepare("SELECT wear_level, equipment_tier, equipment_upgrade_level FROM wells WHERE id = ? LIMIT 1");
            $stmt->execute([$wellId]);
            $eqRow = $stmt->fetch();
            $current = (float) ($eqRow['wear_level'] ?? 0.0);
            $eqMults = self::getEquipmentMultipliers(
                $eqRow['equipment_tier'] ?? 'standard',
                (int) ($eqRow['equipment_upgrade_level'] ?? 0)
            );

            $base = $productionPerHour * $deltaHours * 0.0002;
            $richnessMult = max(1.0, $oilRichness);
            $incidentMult = $hadIncident ? 2.5 : 1.0;
            $delta = $base * $richnessMult * $incidentMult * $spiralMult * $eqMults['wear'];
            $newWear = min(100.0, $current + $delta);

            $this->db->prepare("UPDATE wells SET wear_level = ? WHERE id = ?")
                ->execute([round($newWear, 3), $wellId]);

            GameLog::step('WellService', 'processWear', 1, 'wear_update', [
                'well_id' => $wellId,
                'before' => round($current, 2),
                'delta' => round($delta, 4),
                'after' => round($newWear, 2),
                'incident' => $hadIncident,
                'spiral_mult' => round($spiralMult, 3),
                'eq_tier' => $eqRow['equipment_tier'] ?? 'standard',
                'eq_wear_mult' => round($eqMults['wear'], 3),
            ]);

            return $newWear;
        } catch (Throwable $e) {
            GameLog::error('WellService', 'processWear FAILED', $e, ['well_id' => $wellId]);
            return 0.0;
        }
    }
}
