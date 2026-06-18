<?php
/**
 * TTS/ProceduresTrait.php
 * HSE procedures system - purchase, integrity, decay, staff requirements.
 * System procedur BHP - zakup, integralnosc, degradacja, wymagania kadrowe.
 */
trait TTSProceduresTrait
{
 // HSE procedures.
 // Procedury BHP.

 /**
 * Return the current HSE procedure state for the player.
 * Zwraca aktualny stan procedur BHP gracza.
 */
    public function getProcedureStatus(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT safety_procedures_level, procedure_integrity, procedures_last_decay_at
                FROM players WHERE id = ?
            ");
            $stmt->execute([$this->playerId]);
            $row = $stmt->fetch();

            if (!$row) {
                return ['level' => 0, 'integrity' => 100.0, 'last_decay_at' => null];
            }

            return [
                'level'         => (int) (float) $row['safety_procedures_level'],
                'integrity'     => round((float) $row['procedure_integrity'], 1),
                'last_decay_at' => $row['procedures_last_decay_at'],
            ];
        } catch (Throwable $e) {
            GameLog::error('TTS', 'getProcedureStatus FAILED', $e, ['player_id' => $this->playerId]);
            return ['level' => 0, 'integrity' => 100.0, 'last_decay_at' => null];
        }
    }

 /**
 * Purchase the next HSE procedure level.
 * Kupuje kolejny poziom procedur BHP.
 */
    public function upgradeProcedures(): array
    {
        GameLog::step('TTS', 'upgradeProcedures', 1, "player={$this->playerId}");

        try {
            $proc = $this->getProcedureStatus();
            $currentLevel = $proc['level'];

            if ($currentLevel >= 5) {
                return ['success' => false, 'message' => t('technical.proc_msg.max_level')];
            }

            $nextLevel = $currentLevel + 1;
            $cost = self::PROCEDURE_UPGRADE_COSTS[$nextLevel] ?? 0;

 // Check required HSE staff.
 // Sprawdz wymagany personel BHP.
            $staffStmt = $this->db->prepare("
                SELECT spec_code FROM technical_staff
                WHERE player_id = ?
                  AND spec_code IN ('safety_officer','safety_engineer')
                  AND status IN ('active','busy')
                  AND (fired_at IS NULL OR fired_at > NOW())
            ");
            $staffStmt->execute([$this->playerId]);
            $hseStaff = array_column($staffStmt->fetchAll(), 'spec_code');
            $hasOfficer = in_array('safety_officer', $hseStaff, true);
            $hasEngineer = in_array('safety_engineer', $hseStaff, true);

            if (!$hasOfficer || !$hasEngineer) {
                $missing = [];
                if (!$hasOfficer) {
                    $missing[] = t('technical.spec.safety_officer');
                }
                if (!$hasEngineer) {
                    $missing[] = t('technical.spec.safety_engineer');
                }

                return [
                    'success' => false,
                    'message' => t('technical.proc_msg.missing_staff', [
                        'missing' => implode(' i ', $missing),
                    ]),
                ];
            }

 // Check the audit prerequisite.
 // Sprawdz wymaganie audytu.
            $auditStmt = $this->db->prepare("
                SELECT id FROM technical_tasks
                WHERE player_id = ?
                  AND task_type = 'safety_audit'
                  AND status = 'completed'
                LIMIT 1
            ");
            $auditStmt->execute([$this->playerId]);
            if (!$auditStmt->fetch()) {
                return [
                    'success' => false,
                    'message' => t('technical.proc_msg.audit_required'),
                ];
            }

 // Check available cash.
 // Sprawdz dostepna gotowke.
            $cashStmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
            $cashStmt->execute([$this->playerId]);
            $cash = (float) $cashStmt->fetchColumn();
            if ($cash < $cost) {
                return [
                    'success' => false,
                    'message' => t('technical.proc_msg.no_funds_upgrade', [
                        'level' => $nextLevel,
                        'cost' => number_format($cost, 0, '.', ' '),
                    ]),
                ];
            }

            $this->db->beginTransaction();
            $this->db->prepare("UPDATE players SET cash = cash - ? WHERE id = ?")->execute([$cost, $this->playerId]);
            $this->db->prepare("
                UPDATE players
                SET safety_procedures_level = ?,
                    procedure_integrity = 100,
                    procedures_last_decay_at = NOW()
                WHERE id = ?
            ")->execute([$nextLevel, $this->playerId]);
            $this->db->commit();
            try {
                if (class_exists('FinancialTransactionService', false)) {
                    (new FinancialTransactionService($this->db))->logTransaction(
                        $this->playerId, null, $cost,
                        FinancialTransactionService::TYPE_TTS_FEE,
                        'Ulepszenie procedur bezpieczenstwa do poziomu ' . $nextLevel
                    );
                }
            } catch (Throwable $le) { /* audit trail failure must not break the operation */ }

            GameLog::info('TTS', 'upgradeProcedures OK', [
                'player_id' => $this->playerId,
                'level_from' => $currentLevel,
                'level_to' => $nextLevel,
                'cost' => $cost,
            ]);

            return [
                'success' => true,
                'message' => t('technical.proc_msg.upgraded', [
                    'level' => $nextLevel,
                    'cost' => number_format($cost, 0, '.', ' '),
                ]),
                'level' => $nextLevel,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('TTS', 'upgradeProcedures FAILED', $e, ['player_id' => $this->playerId]);
            return ['success' => false, 'message' => t('technical.proc_msg.upgrade_failed', [
                'error' => $e->getMessage(),
            ])];
        }
    }

 /**
 * Review HSE procedures and restore integrity.
 * Wykonuje przeglad procedur BHP i przywraca integralnosc.
 */
    public function repairProcedureIntegrity(): array
    {
        GameLog::step('TTS', 'repairProcedureIntegrity', 1, "player={$this->playerId}");

        try {
            $proc = $this->getProcedureStatus();
            if ($proc['level'] === 0) {
                return ['success' => false, 'message' => t('technical.proc_msg.none_to_review')];
            }
            if ($proc['integrity'] >= 100.0) {
                return ['success' => false, 'message' => t('technical.proc_msg.integrity_max')];
            }

            $staffStmt = $this->db->prepare("
                SELECT id FROM technical_staff
                WHERE player_id = ?
                  AND spec_code IN ('safety_officer','safety_engineer')
                  AND status IN ('active','busy')
                  AND (fired_at IS NULL OR fired_at > NOW())
                LIMIT 1
            ");
            $staffStmt->execute([$this->playerId]);
            if (!$staffStmt->fetch()) {
                return [
                    'success' => false,
                    'message' => t('technical.proc_msg.review_requires_staff'),
                ];
            }

            $cost = 500_000 * $proc['level'];
            $cashStmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
            $cashStmt->execute([$this->playerId]);
            if ((float) $cashStmt->fetchColumn() < $cost) {
                return [
                    'success' => false,
                    'message' => t('technical.proc_msg.review_no_funds', [
                        'cost' => number_format($cost, 0, '.', ' '),
                    ]),
                ];
            }

            $newIntegrity = min(100.0, $proc['integrity'] + 30.0);

            $this->db->beginTransaction();
            $this->db->prepare("UPDATE players SET cash = cash - ? WHERE id = ?")->execute([$cost, $this->playerId]);
            $this->db->prepare("
                UPDATE players
                SET procedure_integrity = ?,
                    procedures_last_decay_at = NOW()
                WHERE id = ?
            ")->execute([$newIntegrity, $this->playerId]);
            $this->db->commit();
            try {
                if (class_exists('FinancialTransactionService', false)) {
                    (new FinancialTransactionService($this->db))->logTransaction(
                        $this->playerId, null, $cost,
                        FinancialTransactionService::TYPE_TTS_FEE,
                        'Przeglad/naprawa integralnosci procedur bezpieczenstwa'
                    );
                }
            } catch (Throwable $le) { /* audit trail failure must not break the operation */ }

            GameLog::info('TTS', 'repairProcedureIntegrity OK', [
                'player_id' => $this->playerId,
                'integrity_from' => $proc['integrity'],
                'integrity_to' => $newIntegrity,
                'cost' => $cost,
            ]);

            return [
                'success' => true,
                'message' => t('technical.proc_msg.review_done', [
                    'from' => $proc['integrity'],
                    'to' => $newIntegrity,
                    'cost' => number_format($cost, 0, '.', ' '),
                ]),
                'integrity' => $newIntegrity,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('TTS', 'repairProcedureIntegrity FAILED', $e, ['player_id' => $this->playerId]);
            return ['success' => false, 'message' => t('technical.proc_msg.review_failed', [
                'error' => $e->getMessage(),
            ])];
        }
    }

 /**
 * Apply hourly decay to procedure integrity.
 * Naklada godzinowa degradacje integralnosci procedur.
 */
    public function processProcedureDecay(float $deltaHours): void
    {
        GameLog::step('TTS', 'processProcedureDecay', 1, "player={$this->playerId} dh={$deltaHours}");

        try {
            $proc = $this->getProcedureStatus();
            if ($proc['level'] === 0 || $proc['integrity'] <= 0) {
                return;
            }

            $staffStmt = $this->db->prepare("
                SELECT id FROM technical_staff
                WHERE player_id = ?
                  AND spec_code IN ('safety_officer','safety_engineer')
                  AND status IN ('active','busy')
                  AND (fired_at IS NULL OR fired_at > NOW())
                LIMIT 1
            ");
            $staffStmt->execute([$this->playerId]);
            $hasPersonnel = (bool) $staffStmt->fetch();

            $decayRate = $hasPersonnel ? 0.5 : 2.0;
            $integrity = $proc['integrity'];
            $newIntegrity = max(0.0, round($integrity - ($decayRate * $deltaHours), 2));

            $this->db->prepare("
                UPDATE players
                SET procedure_integrity = ?,
                    procedures_last_decay_at = NOW()
                WHERE id = ?
            ")->execute([$newIntegrity, $this->playerId]);

            if ($integrity > 30 && $newIntegrity <= 30) {
                $this->notify('hse_warning', null, t('technical.proc_notify.warning', [
                    'integrity' => round($newIntegrity, 0),
                ]));
            } elseif ($integrity > 0 && $newIntegrity <= 0) {
                $this->notify('hse_critical', null, t('technical.proc_notify.critical'));
            }

            if (abs($newIntegrity - $integrity) >= 1.0) {
                GameLog::info('TTS', 'processProcedureDecay', [
                    'player_id' => $this->playerId,
                    'integrity' => $integrity,
                    'new_integrity' => $newIntegrity,
                    'decay_rate' => $decayRate,
                    'has_personnel' => $hasPersonnel,
                ]);
            }
        } catch (Throwable $e) {
            GameLog::error('TTS', 'processProcedureDecay FAILED', $e, ['player_id' => $this->playerId]);
        }
    }

 /**
 * Check minimum staffing required for wells.
 * Sprawdza minimalna obsade wymagana dla odwiertow.
 *
 * @return array{meets_minimum: bool, missing: array<int, string>, missing_labels: array<int, string>}
 */
    public function getStaffRequirementCheck(): array
    {
        $required = [
            'safety_officer' => t('technical.spec.safety_officer'),
            'safety_engineer' => t('technical.spec.safety_engineer'),
            'maintenance_engineer' => t('technical.spec.maintenance_engineer'),
            'production_engineer' => t('technical.spec.production_engineer'),
        ];

        $result = ['meets_minimum' => true, 'missing' => [], 'missing_labels' => []];

        try {
            $stmt = $this->db->prepare("
                SELECT spec_code
                FROM technical_staff
                WHERE player_id = ?
                  AND spec_code IN ('safety_officer','safety_engineer',
                                    'maintenance_engineer','production_engineer')
                  AND status IN ('active','busy')
                  AND (fired_at IS NULL OR fired_at > NOW())
            ");
            $stmt->execute([$this->playerId]);
            $present = array_column($stmt->fetchAll(), 'spec_code');

            foreach ($required as $code => $label) {
                if (!in_array($code, $present, true)) {
                    $result['missing'][] = $code;
                    $result['missing_labels'][] = $label;
                    $result['meets_minimum'] = false;
                }
            }

            GameLog::step(
                'TTS',
                'getStaffRequirementCheck',
                1,
                "player={$this->playerId} ok=" . ($result['meets_minimum'] ? '1' : '0') . ' missing=' . implode(',', $result['missing'])
            );
        } catch (Throwable $e) {
            GameLog::error('TTS', 'getStaffRequirementCheck FAILED', $e, ['player_id' => $this->playerId]);
 // Fail-safe: do not pause wells because of a read error.
 // Fail-safe: nie pauzuj odwiertow przez blad odczytu.
            $result['meets_minimum'] = true;
        }

        return $result;
    }
}
