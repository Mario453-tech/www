<?php
/**
 * TTS/ManagerTrait.php
 * Technical manager and HSE support calculations.
 * Kierownik techniczny i kalkulacje wsparcia BHP.
 */
trait TTSManagerTrait
{
    // Technical manager.
    // Kierownik techniczny.

    public function getManager(): ?array
    {
        $stmt = $this->db->prepare("
            SELECT bm.*, br.code as role_code,
                   hs.name as spec_name, hs.code as spec_code
            FROM board_members bm
            JOIN board_roles br ON bm.role_id = br.id
            LEFT JOIN hr_specializations hs ON bm.specialization_id = hs.id
            WHERE br.code = 'technical' AND bm.status = 'active'
            LIMIT 1
        ");
        $stmt->execute();
        return $stmt->fetch() ?: null;
    }

    public function getManagerBonus(?array $manager): array
    {
        if (!$manager) {
            return ['time_mult' => 1.0, 'cost_mult' => 1.0, 'skill' => 0];
        }

        $skill = (int) ($manager['skill_organization'] ?? 5);

        return [
            'skill' => $skill,
            'time_mult' => max(0.5, 1.0 - ($skill * 0.025)),
            'cost_mult' => max(0.6, 1.0 - ($skill * 0.015)),
            'label' => t('technical.manager_bonus.label', [
                'skill' => $skill,
                'time_pct' => $skill * 2.5,
                'cost_pct' => $skill * 1.5,
            ]),
        ];
    }

    // HSE bonus.
    // Bonus BHP.

    /**
     * Calculate the active HSE bonus for the player.
     * Oblicza aktywny bonus BHP dla gracza.
     *
     * @return array<string, mixed>
     */
    public function getHSEBonus(): array
    {
        GameLog::step('TTS', 'getHSEBonus', 1, "player={$this->playerId}");

        $base = [
            'failure_reduction' => 1.0,
            'repair_cost_mult' => 1.0,
            'catastrophe_mult' => 1.0,
            'uptime_bonus' => 0.0,
            'degrade_mult' => 1.0,
            'active_hse' => 0,
            'has_officer' => false,
            'has_engineer' => false,
            'both_hse' => false,
            'audit_bonus' => false,
            'proc_level' => 0,
            'proc_integrity' => 100.0,
            'proc_factor' => 0.0,
            'label' => t('technical.hse_label.none', [
                'wells' => 0,
                'hubs' => 0,
                'pipelines' => 0,
                'officer_need' => 0,
                'engineer_need' => 0,
            ]),
        ];

        try {
            // Layer 1: HSE staff coverage.
            // Warstwa 1: pokrycie personelem BHP.
            $wellCountStmt = $this->db->prepare("
                SELECT COUNT(*) FROM wells
                WHERE player_id = ?
                  AND status NOT IN ('sold','abandoned')
            ");
            $wellCountStmt->execute([$this->playerId]);
            $totalWells = (int) $wellCountStmt->fetchColumn();

            $hubCountStmt = $this->db->prepare("
                SELECT COUNT(DISTINCT a.hub_id)
                FROM logistics_hub_assignments a
                JOIN wells w ON w.id = a.well_id
                WHERE w.player_id = ?
                  AND a.status = 'active'
                  AND w.status NOT IN ('sold','abandoned')
            ");
            $hubCountStmt->execute([$this->playerId]);
            $totalHubs = (int) $hubCountStmt->fetchColumn();

            $pipelineCountStmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM well_pipelines wp
                JOIN wells w ON w.id = wp.well_id
                JOIN logistics_hub_assignments a ON a.well_id = wp.well_id AND a.status = 'active'
                JOIN logistics_hubs h ON h.id = wp.hub_id
                WHERE wp.player_id = ?
                  AND w.transport_type = 'rurociag'
                  AND w.status NOT IN ('sold','abandoned')
                  AND a.hub_id = wp.hub_id
                  AND h.status NOT IN ('disabled', 'building')
                  AND wp.status IN ('active','degraded','critical','damaged')
            ");
            $pipelineCountStmt->execute([$this->playerId]);
            $totalPipelines = (int) $pipelineCountStmt->fetchColumn();

            $supervisedUnits = $totalWells + ($totalHubs * 2) + $totalPipelines;

            $stmt = $this->db->prepare("
                SELECT ts.spec_code, ts.skill_level, ts.status
                FROM technical_staff ts
                WHERE ts.player_id = ?
                  AND ts.spec_code IN ('safety_officer','safety_engineer')
                  AND ts.status IN ('active','busy')
                  AND (ts.fired_at IS NULL OR ts.fired_at > NOW())
            ");
            $stmt->execute([$this->playerId]);
            $hseStaff = $stmt->fetchAll();

            $officerCount = 0;
            $engineerCount = 0;
            $officerSkillSum = 0;
            $engineerSkillSum = 0;

            foreach ($hseStaff as $member) {
                if ($member['spec_code'] === 'safety_officer') {
                    $officerCount++;
                    $officerSkillSum += (int) $member['skill_level'];
                } elseif ($member['spec_code'] === 'safety_engineer') {
                    $engineerCount++;
                    $engineerSkillSum += (int) $member['skill_level'];
                }
            }

            $officerCapacity = $officerCount * 3;
            $engineerCapacity = $engineerCount * 2;
            $officerCoverage = $supervisedUnits > 0 ? min(1.0, $officerCapacity / $supervisedUnits) : 0.0;
            $engineerCoverage = $supervisedUnits > 0 ? min(1.0, $engineerCapacity / $supervisedUnits) : 0.0;

            $hasOfficer = $officerCount > 0;
            $hasEngineer = $engineerCount > 0;
            $hasPersonnel = $hasOfficer || $hasEngineer;
            $hasBothHSE = $hasOfficer && $hasEngineer;

            $base['has_officer'] = $hasOfficer;
            $base['has_engineer'] = $hasEngineer;
            $base['both_hse'] = $hasBothHSE;
            $base['officer_coverage'] = round($officerCoverage * 100);
            $base['engineer_coverage'] = round($engineerCoverage * 100);
            $base['officer_needed'] = $supervisedUnits > 0 ? (int) ceil($supervisedUnits / 3) : 0;
            $base['engineer_needed'] = $supervisedUnits > 0 ? (int) ceil($supervisedUnits / 2) : 0;
            $base['officer_count'] = $officerCount;
            $base['engineer_count'] = $engineerCount;
            $base['total_wells'] = $totalWells;
            $base['total_hubs'] = $totalHubs;
            $base['total_pipelines'] = $totalPipelines;
            $base['supervised_units'] = $supervisedUnits;

            $effectiveSkill = 0.0;
            $maxSkill = 0;

            if ($hasPersonnel) {
                $base['active_hse'] = $officerCount + $engineerCount;

                $officerSkill = $officerCount > 0 ? $officerSkillSum / $officerCount : 0;
                $engineerSkill = $engineerCount > 0 ? $engineerSkillSum / $engineerCount : 0;

                if ($hasBothHSE) {
                    $rawSkill = ($officerSkill + $engineerSkill) / 2;
                    $coverageFactor = ($officerCoverage + $engineerCoverage) / 2;
                } else {
                    $rawSkill = $hasOfficer ? $officerSkill : $engineerSkill;
                    $coverageFactor = $hasOfficer ? $officerCoverage : $engineerCoverage;
                    $rawSkill *= 0.5;
                }

                $effectiveSkill = min(10, max(1, $rawSkill * $coverageFactor));
                $maxSkill = (int) round($effectiveSkill);
                $skillFactor = ($effectiveSkill - 1) / 9;

                $base['failure_reduction'] = round(0.95 - $skillFactor * 0.35, 3);
                $base['repair_cost_mult'] = round(1.0 - $skillFactor * 0.25, 3);
                $base['catastrophe_mult'] = round(1.0 - $skillFactor * 0.85, 3);
                $base['uptime_bonus'] = round($skillFactor * 0.04, 4);
                $base['degrade_mult'] = round(1.0 - $skillFactor * 0.30, 3);

                // Layer 2: recent safety audit.
                // Warstwa 2: swiezy audyt bezpieczenstwa.
                if ($hasBothHSE) {
                    $auditStmt = $this->db->prepare("
                        SELECT id FROM technical_tasks
                        WHERE player_id = ?
                          AND task_type = 'safety_audit'
                          AND status = 'completed'
                          AND end_time >= DATE_SUB(NOW(), INTERVAL 72 HOUR)
                        ORDER BY end_time DESC
                        LIMIT 1
                    ");
                    $auditStmt->execute([$this->playerId]);
                    if ($auditStmt->fetch()) {
                        $base['audit_bonus'] = true;
                        $base['failure_reduction'] = max(0.2, $base['failure_reduction'] - 0.15);
                        $base['catastrophe_mult'] = max(0.05, $base['catastrophe_mult'] - 0.10);
                        $base['degrade_mult'] = max(0.60, $base['degrade_mult'] - 0.10);
                    }
                }
            }

            // Layer 3: HSE procedures.
            // Warstwa 3: procedury BHP.
            $playerStmt = $this->db->prepare("
                SELECT safety_procedures_level, procedure_integrity
                FROM players WHERE id = ?
            ");
            $playerStmt->execute([$this->playerId]);
            $playerRow = $playerStmt->fetch();

            if ($playerRow) {
                $procLevel = (int) (float) $playerRow['safety_procedures_level'];
                $procIntegrity = max(0.0, min(100.0, (float) $playerRow['procedure_integrity']));
                $base['proc_level'] = $procLevel;
                $base['proc_integrity'] = round($procIntegrity, 1);

                if ($procLevel > 0 && $procIntegrity > 0) {
                    $procFactor = round(($procLevel / 5) * ($procIntegrity / 100), 4);
                    $base['proc_factor'] = $procFactor;
                    $base['failure_reduction'] = round($base['failure_reduction'] * (1.0 - 0.20 * $procFactor), 3);
                    $base['catastrophe_mult'] = round($base['catastrophe_mult'] * (1.0 - 0.40 * $procFactor), 3);
                    $base['degrade_mult'] = round($base['degrade_mult'] * (1.0 - 0.25 * $procFactor), 3);
                    $base['repair_cost_mult'] = round($base['repair_cost_mult'] * (1.0 - 0.10 * $procFactor), 3);
                }
            }

            // Clamp values into safe ranges.
            // Ogranicz wartosci do bezpiecznych zakresow.
            $base['failure_reduction'] = max(0.10, $base['failure_reduction']);
            $base['catastrophe_mult'] = max(0.03, $base['catastrophe_mult']);
            $base['degrade_mult'] = max(0.50, $base['degrade_mult']);
            $base['repair_cost_mult'] = max(0.60, $base['repair_cost_mult']);

            $redPct = (int) round((1 - $base['failure_reduction']) * 100);
            $catPct = (int) round((1 - $base['catastrophe_mult']) * 100);
            $procPct = (int) round($base['proc_factor'] * 100);
            $offNeed = $base['officer_needed'] ?? 0;
            $engNeed = $base['engineer_needed'] ?? 0;
            $offHave = $base['officer_count'] ?? 0;
            $engHave = $base['engineer_count'] ?? 0;
            $offCov = $base['officer_coverage'] ?? 0;
            $engCov = $base['engineer_coverage'] ?? 0;

            if (!$hasPersonnel) {
                $base['label'] = t('technical.hse_label.none', [
                    'wells' => $totalWells,
                    'hubs' => $totalHubs,
                    'pipelines' => $totalPipelines,
                    'officer_need' => $offNeed,
                    'engineer_need' => $engNeed,
                ]);
            } elseif (!$hasBothHSE) {
                $missing = !$hasOfficer
                    ? t('technical.hse_label.missing_engineer', ['count' => $engNeed])
                    : t('technical.hse_label.missing_officer', ['count' => $offNeed]);

                $base['label'] = t('technical.hse_label.incomplete', [
                    'missing' => $missing,
                    'wells' => $totalWells,
                    'hubs' => $totalHubs,
                    'pipelines' => $totalPipelines,
                    'failure_pct' => $redPct,
                ]);
            } else {
                $coverInfo = t('technical.hse_label.coverage', [
                    'officer_pct' => $offCov,
                    'engineer_pct' => $engCov,
                ]);

                if ($offCov < 100 || $engCov < 100) {
                    $base['label'] = t('technical.hse_label.partial', [
                        'coverage' => $coverInfo,
                        'wells' => $totalWells,
                        'hubs' => $totalHubs,
                        'pipelines' => $totalPipelines,
                        'failure_pct' => $redPct,
                        'officer_need' => $offNeed,
                        'engineer_need' => $engNeed,
                    ]);
                } else {
                    $base['label'] = t('technical.hse_label.full', [
                        'officer_have' => $offHave,
                        'engineer_have' => $engHave,
                        'skill' => $maxSkill,
                        'wells' => $totalWells,
                        'hubs' => $totalHubs,
                        'pipelines' => $totalPipelines,
                        'failure_pct' => $redPct,
                        'catastrophe_pct' => $catPct,
                    ]);
                }
            }

            if ($base['proc_level'] > 0) {
                $base['label'] .= t('technical.hse_label.proc_suffix', [
                    'level' => $base['proc_level'],
                    'proc_pct' => $procPct,
                ]);
            }
            if ($base['audit_bonus']) {
                $base['label'] .= t('technical.hse_label.audit_suffix');
            }

            GameLog::info('TTS', 'getHSEBonus', [
                'player_id' => $this->playerId,
                'active_hse' => $base['active_hse'],
                'has_officer' => $hasOfficer,
                'has_engineer' => $hasEngineer,
                'both_hse' => $hasBothHSE,
                'effective_skill' => $effectiveSkill,
                'failure_reduction' => $base['failure_reduction'],
                'catastrophe_mult' => $base['catastrophe_mult'],
                'audit_bonus' => $base['audit_bonus'],
                'proc_level' => $base['proc_level'],
                'proc_integrity' => $base['proc_integrity'],
                'proc_factor' => $base['proc_factor'],
                'total_hubs' => $base['total_hubs'],
                'total_pipelines' => $base['total_pipelines'],
                'supervised_units' => $base['supervised_units'],
            ]);
        } catch (Throwable $e) {
            GameLog::error('TTS', 'getHSEBonus FAILED', $e, ['player_id' => $this->playerId]);
        }

        return $base;
    }
}
