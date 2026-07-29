<?php
require_once __DIR__ . '/HR/EmployeeDeadlockRetry.php';
require_once __DIR__ . '/Employee/TechnicalStaffProfile.php';
require_once __DIR__ . '/EmployeeSystemBootstrap.php';
require_once __DIR__ . '/Employee/EmployeeSystemConfigService.php';
require_once __DIR__ . '/HR/StrikeEffectService.php';
require_once __DIR__ . '/HR/EmployeeActionReceiptService.php';
/**
 * HeadhunterService - recruit specialists from competitors.
 * PL: HeadhunterService - rekrutacja specjalistow od konkurencji.
 */
class HeadhunterService
{
    private PDO $db;
    private int $playerId;
    private StrikeEffectService $strikeEffects;

    public const COST_MIN = 500_000;
    public const COST_MAX = 2_000_000;

 // 24-72 minutes in game time shortcut.
 // PL: 24-72 minuty w skrocie czasu gry.
    public const DURATION_MIN_SEC = 24 * 60;
    public const DURATION_MAX_SEC = 72 * 60;

 /** @var array<int, array<int, int>> */
    private static array $skillDist = [
        [5, 6, 30],
        [7, 7, 30],
        [8, 8, 25],
        [9, 9, 12],
        [10, 10, 3],
    ];

 /** @var list<string> */
    private static array $companies = [
        'Shell Polska', 'BP Eastern Europe', 'Total Energies PL',
        'PGNiG Upstream', 'Lotos Exploration', 'Orlen Upstream',
        'ExxonMobil Polska', 'Chevron Eastern', 'Equinor Poland',
        'ConocoPhillips CE', 'Repsol Polska', 'ENI Poland',
    ];

    public function __construct(int $playerId)
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            EmployeeSystemBootstrap::ensure($this->db);
            $this->playerId = $playerId;
            $this->strikeEffects = new StrikeEffectService(
                $this->db,
                new EmployeeSystemConfigService($this->db)
            );
            GameLog::info('HeadhunterService', 'Service initialized', ['player_id' => $playerId]);
        } catch (Throwable $e) {
            GameLog::error('HeadhunterService', 'Initialization failed', $e);
            throw $e;
        }
    }

 /** @return array<string, mixed> */
    public function startSearch(int $specializationId): array
    {
        try {
            $specStmt = $this->db->prepare("SELECT * FROM hr_specializations WHERE id = ?");
            $specStmt->execute([$specializationId]);
            $spec = $specStmt->fetch();
            if (!$spec) {
                return ['success' => false, 'message' => t('hr_headhunter.err_unknown_specialization')];
            }

            $cost = rand((int)(self::COST_MIN / 1000), (int)(self::COST_MAX / 1000)) * 1000;

            $cashStmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
            $cashStmt->execute([$this->playerId]);
            if ((float)$cashStmt->fetchColumn() < $cost) {
                return ['success' => false, 'message' => t('hr_headhunter.err_insufficient_funds', ['cost' => self::fmt($cost)])];
            }

            $effects = $this->strikeEffects->forPlayer($this->playerId);
            $strikeMultiplier = (float)($effects['hr']['recruitment_time_mult'] ?? 1.0);
            $duration = (int)round(
                rand(self::DURATION_MIN_SEC, self::DURATION_MAX_SEC) * $strikeMultiplier
            );
            $finishedAt = date('Y-m-d H:i:s', time() + $duration);

            $this->db->beginTransaction();
            try {
                $playerLock = $this->db->prepare("SELECT id FROM players WHERE id = ? LIMIT 1 FOR UPDATE");
                $playerLock->execute([$this->playerId]);

                $activeStmt = $this->db->prepare(
                    "SELECT id FROM headhunter_searches WHERE player_id = ? AND status = 'searching' LIMIT 1 FOR UPDATE"
                );
                $activeStmt->execute([$this->playerId]);
                if ($activeStmt->fetch()) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => t('hr_headhunter.err_search_active')];
                }

 // Oplata za wyszukiwanie przez centralne API finansowe (ruch + wpis bankowy; w transakcji).
 // Search fee via the central finance API (movement + bank entry; inside a transaction).
                $feeRes = (new FinancialTransactionService($this->db))->debit(
                    $this->playerId, (float)$cost,
                    FinancialTransactionService::TYPE_HR_FEE,
                    tPlain('bank.tx_hr_headhunter_search'),
                    'headhunter_search', null
                );
                if (empty($feeRes['success'])) {
                    $this->db->rollBack();
                    GameLog::error('HeadhunterService', 'startSearch: fee debit FAILED', null, ['player_id' => $this->playerId, 'error' => $feeRes['error'] ?? 'unknown']);
                    return ['success' => false, 'message' => t('hr_headhunter.err_insufficient_funds', ['cost' => self::fmt($cost)])];
                }

                $this->db->prepare("
                    INSERT INTO headhunter_searches
                        (player_id, specialization_id, spec_code, finished_at, status)
                    VALUES (?, ?, ?, ?, 'searching')
                ")->execute([$this->playerId, $specializationId, $spec['code'] ?? null, $finishedAt]);

                $searchId = (int)$this->db->lastInsertId();
                $this->db->commit();
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                GameLog::error('HeadhunterService', 'startSearch transaction failed', $e, [
                    'player_id' => $this->playerId,
                    'specialization_id' => $specializationId,
                ]);
                return ['success' => false, 'message' => t('hr_headhunter.err_transaction', ['error' => $e->getMessage()])];
            }

            $mins = (int)round($duration / 60);
            GameLog::info('HeadhunterService', 'Search started', [
                'player_id' => $this->playerId,
                'search_id' => $searchId,
                'specialization_id' => $specializationId,
                'cost' => $cost,
                'duration_sec' => $duration,
                'strike_time_multiplier' => $strikeMultiplier,
            ]);

            return [
                'success' => true,
                'search_id' => $searchId,
                'cost' => $cost,
                'finished_at' => $finishedAt,
                'message' => t('hr_headhunter.msg_search_started', [
                    'spec' => $spec['name'],
                    'cost' => self::fmt($cost),
                    'mins' => $mins,
                ]),
            ];
        } catch (Throwable $e) {
            GameLog::error('HeadhunterService', 'startSearch failed', $e, [
                'player_id' => $this->playerId,
                'specialization_id' => $specializationId,
            ]);
            return ['success' => false, 'message' => t('hr_headhunter.err_start_failed')];
        }
    }

    public function processReady(int $limit = 100): int
    {
        return $this->processReadyBatch($this->playerId, $limit);
    }

    public function processReadyAll(int $limit = 100): int
    {
        return $this->processReadyBatch(null, $limit);
    }

    private function processReadyBatch(?int $playerId, int $limit): int
    {
        $processed = 0;
        try {
            $where = $playerId === null ? '' : 'AND hs.player_id = ?';
            $stmt = $this->db->prepare("
                SELECT hs.*, hsp.name AS spec_name,
                       hsp.base_salary_min, hsp.base_salary_max
                FROM headhunter_searches hs
                JOIN hr_specializations hsp ON hs.specialization_id = hsp.id
                WHERE hs.status = 'searching'
                  AND hs.finished_at <= NOW()
                  {$where}
                ORDER BY hs.finished_at, hs.id
                LIMIT " . max(1, min(1000, $limit)));
            $stmt->execute($playerId === null ? [] : [$playerId]);

            foreach ($stmt->fetchAll() as $search) {
                $this->db->beginTransaction();
                try {
                    $claim = $this->db->prepare("
                        UPDATE headhunter_searches
                        SET status = 'failed'
                        WHERE id = ?
                          AND player_id = ?
                          AND status = 'searching'
                          AND finished_at <= NOW()
                    ");
                    $claim->execute([(int)$search['id'], (int)$search['player_id']]);
                    if ($claim->rowCount() !== 1) {
                        $this->db->rollBack();
                        continue;
                    }
                    $this->generateCandidates($search);
                    $this->db->commit();
                    $processed++;
                } catch (Throwable $e) {
                    if ($this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    GameLog::error('HeadhunterService', 'processReady search failed', $e, [
                        'player_id' => $search['player_id'] ?? null,
                        'search_id' => $search['id'] ?? null,
                    ]);
                }
            }
        } catch (Throwable $e) {
            GameLog::error('HeadhunterService', 'processReady failed', $e, [
                'player_id' => $playerId,
            ]);
        }
        return $processed;
    }

    private function generateCandidates(array $search): void
    {
        $count = $this->rollCount();
        $expiresAt = date('Y-m-d H:i:s', time() + 48 * 3600);

        for ($i = 0; $i < $count; $i++) {
            $skill = $this->rollSkill();
            $loyalty = min(9, max(3, $skill - 1 + rand(-1, 2)));
            $company = self::$companies[array_rand(self::$companies)];
            $salary = (int)round(
                rand((int)$search['base_salary_min'], (int)$search['base_salary_max'])
 * (0.9 + $skill * 0.02)
            );

            $bonusMin = match (true) {
                $skill >= 9 => 800_000,
                $skill >= 7 => 300_000,
                default => 100_000,
            };

            $baseProb = max(20, 50 - ($loyalty * 3));
            $firstName = $this->randomName('first_name');
            $lastName = $this->randomName('last_name');

            $this->db->prepare("
                INSERT INTO headhunter_candidates
                    (search_id, player_id, first_name, last_name, specialization_id,
                     skill_level, current_company, salary_expectation,
                     signing_bonus, join_probability, trait_loyalty, expires_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
            ")->execute([
                $search['id'], $search['player_id'],
                $firstName, $lastName, $search['specialization_id'],
                $skill, $company, $salary, $bonusMin, $baseProb, $loyalty, $expiresAt,
            ]);
        }

        $this->db->prepare(
            "UPDATE headhunter_searches
                SET status = ?, result_count = ?
              WHERE id = ?
                AND player_id = ?
                AND status = 'failed'"
        )->execute([
            $count > 0 ? 'completed' : 'failed',
            $count,
            $search['id'],
            $search['player_id'],
        ]);

        $msg = $count > 0
            ? t('hr_headhunter.notify_candidates_found', ['count' => $count, 'spec' => $search['spec_name']])
            : t('hr_headhunter.notify_candidates_missing', ['spec' => $search['spec_name']]);

        $this->db->prepare(
            "INSERT INTO technical_notifications (player_id, well_id, type, message) VALUES (?,NULL,'task',?)"
        )->execute([$search['player_id'], $msg]);

        GameLog::info('HeadhunterService', 'Candidates generated', [
            'search_id' => $search['id'],
            'player_id' => $search['player_id'],
            'count' => $count,
        ]);
    }

    /** @return array<string,mixed> */
    public function makeOffer(
        int $candidateId,
        float $offeredSalary,
        float $signingBonus,
        string $idempotencyToken
    ): array
    {
        $this->assertValidOfferValues($candidateId, $offeredSalary, $signingBonus);
        return EmployeeDeadlockRetry::run(
            $this->db,
            fn(): array => $this->makeOfferOnce(
                $candidateId,
                $offeredSalary,
                $signingBonus,
                $idempotencyToken
            )
        );
    }

    /** @return array<string,mixed> */
    private function makeOfferOnce(
        int $candidateId,
        float $offeredSalary,
        float $signingBonus,
        string $idempotencyToken
    ): array
    {
        $ownTransaction = !$this->db->inTransaction();
        try {
            if ($ownTransaction) {
                $this->db->beginTransaction();
            }
            $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $playerLock = $this->db->prepare("SELECT id FROM players WHERE id=? LIMIT 1{$suffix}");
            $playerLock->execute([$this->playerId]);
            if ((int)($playerLock->fetchColumn() ?: 0) !== $this->playerId) {
                throw new RuntimeException('Player does not exist for headhunter offer.');
            }
            $request = [
                'candidate_id'=>$candidateId,
                'offered_salary'=>round($offeredSalary, 2),
                'signing_bonus'=>round($signingBonus, 2),
            ];
            $receipts = new EmployeeActionReceiptService($this->db);
            $receipt = $receipts->claim(
                $this->playerId,
                'headhunter_offer',
                $idempotencyToken,
                $request
            );
            if ($receipt['replayed']) {
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return $receipt['response'] ?? ['success'=>false, 'message'=>t('hr_headhunter.err_offer_failed')];
            }
            $stmt = $this->db->prepare("
                SELECT hc.*, hsp.name AS spec_name
                FROM headhunter_candidates hc
                JOIN hr_specializations hsp ON hc.specialization_id = hsp.id
                WHERE hc.id = ? AND hc.player_id = ?
                  AND hc.status IN ('available', 'offered') AND hc.expires_at > NOW()
                LIMIT 1{$suffix}
            ");
            $stmt->execute([$candidateId, $this->playerId]);
            $c = $stmt->fetch();
            if (!$c) {
                $result = ['success' => false, 'message' => t('hr_headhunter.err_candidate_unavailable')];
                $receipts->complete((int)$receipt['id'], $this->playerId, $result);
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return $result;
            }

            if ((string)$c['status'] === 'offered') {
                $counterSalary = (float)($c['counter_salary'] ?? 0);
                $counterBonus = (float)($c['counter_bonus'] ?? 0);
                if (abs($counterSalary - $offeredSalary) > 0.01
                    || abs($counterBonus - $signingBonus) > 0.01) {
                    $result = ['success'=>false, 'message'=>t('hr_headhunter.err_candidate_unavailable')];
                } else {
                    $result = $this->doHire($c, $counterSalary, $counterBonus, 100);
                }
                $receipts->complete((int)$receipt['id'], $this->playerId, $result);
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return $result;
            }

            $cashStmt = $this->db->prepare("SELECT cash FROM players WHERE id = ? LIMIT 1{$suffix}");
            $cashStmt->execute([$this->playerId]);
            if ((float)$cashStmt->fetchColumn() < $signingBonus) {
                $result = [
                    'success' => false,
                    'message' => t('hr_headhunter.err_bonus_funds', ['cost' => self::fmt($signingBonus)]),
                ];
                $receipts->complete((int)$receipt['id'], $this->playerId, $result);
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return $result;
            }

            $prob = $this->calcProbability($c, $offeredSalary, $signingBonus);
            $roll = mt_rand(1, 100);

            if ($roll <= $prob) {
                $result = $this->doHire($c, $offeredSalary, $signingBonus, $prob);
            } elseif ($roll <= $prob + 20) {
                $counterSalary = (int)round($offeredSalary * 1.15);
                $counterBonus = (int)round($signingBonus * 1.25);
                $update = $this->db->prepare("
                    UPDATE headhunter_candidates
                    SET status = 'offered', offer_round=offer_round+1,
                        counter_salary=?, counter_bonus=?
                    WHERE id = ?
                      AND player_id = ?
                      AND status = 'available'
                      AND expires_at > NOW()
                ");
                $update->execute([$counterSalary, $counterBonus, $candidateId, $this->playerId]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('Headhunter counteroffer could not be persisted.');
                }

                $result = [
                    'success' => true,
                    'decision' => 'negotiate',
                    'message' => t('hr_headhunter.msg_negotiate', [
                        'first' => $c['first_name'],
                        'last' => $c['last_name'],
                    ]),
                    'counter_salary' => $counterSalary,
                    'counter_bonus' => $counterBonus,
                    'probability' => $prob,
                ];
            } else {
                $update = $this->db->prepare("
                    UPDATE headhunter_candidates
                    SET status = 'rejected'
                    WHERE id = ? AND player_id = ? AND status = 'available' AND expires_at > NOW()
                ");
                $update->execute([$candidateId, $this->playerId]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('Headhunter rejection could not be persisted.');
                }
                $result = [
                    'success' => true,
                    'decision' => 'reject',
                    'message' => t('hr_headhunter.msg_offer_rejected', [
                        'first' => $c['first_name'],
                        'last' => $c['last_name'],
                    ]),
                    'probability' => $prob,
                ];
            }
            $receipts->complete((int)$receipt['id'], $this->playerId, $result);
            if ($ownTransaction) {
                $this->db->commit();
            }
            return $result;
        } catch (Throwable $e) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('HeadhunterService', 'makeOffer failed', $e, [
                'player_id' => $this->playerId,
                'candidate_id' => $candidateId,
            ]);
            return ['success' => false, 'message' => t('hr_headhunter.err_offer_failed')];
        }
    }

    private function assertValidOfferValues(int $candidateId, float $offeredSalary, float $signingBonus): void
    {
        if ($candidateId <= 0
            || !is_finite($offeredSalary)
            || !is_finite($signingBonus)
            || $offeredSalary <= 0.0
            || $signingBonus < 0.0) {
            throw new InvalidArgumentException('Headhunter offer values are invalid.');
        }
    }

    private function calcProbability(array $c, float $salary, float $bonus): int
    {
        try {
            $base = (int)$c['join_probability'];

            $ratio = $salary / max(1, (float)$c['salary_expectation']);
            if ($ratio >= 1.3) {
                $base += 20;
            } elseif ($ratio >= 1.1) {
                $base += 10;
            } elseif ($ratio < 0.8) {
                $base -= 30;
            } elseif ($ratio < 0.9) {
                $base -= 15;
            }

            $bMin = (float)($c['signing_bonus'] ?? 0);
            if ($bonus >= $bMin * 2) {
                $base += 20;
            } elseif ($bonus >= $bMin) {
                $base += 10;
            } elseif ($bonus < $bMin * 0.5) {
                $base -= 10;
            }

            $cashStmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
            $cashStmt->execute([$this->playerId]);
            $cash = (float)$cashStmt->fetchColumn();
            if ($cash < 1_000_000) {
                $base -= 20;
            } elseif ($cash > 50_000_000) {
                $base += 10;
            }

            $wStmt = $this->db->prepare("SELECT COUNT(*) FROM wells WHERE player_id = ? AND status = 'active'");
            $wStmt->execute([$this->playerId]);
            if ((int)$wStmt->fetchColumn() < 2) {
                $base -= 10;
            }

            return max(5, min(90, $base));
        } catch (Throwable $e) {
            GameLog::error('HeadhunterService', 'calcProbability failed', $e, [
                'player_id' => $this->playerId,
                'candidate_id' => $c['id'] ?? null,
            ]);
            return 5;
        }
    }

 /**
 * @param array<string, mixed> $c
 * @param float $salary
 * @param float $bonus
 * @param int $prob
 * @return array<string, mixed>
 */
    private function doHire(array $c, float $salary, float $bonus, int $prob): array
    {
        try {
            if (!$this->db->inTransaction()) {
                throw new LogicException('Headhunter hire requires an active transaction.');
            }
            $spec = $this->loadSpecializationMeta((int)$c['specialization_id']);
            if ($spec === null) {
                return ['success' => false, 'message' => t('hr_headhunter.err_unknown_specialization')];
            }

            // Hire bonus uses the central finance API inside the offer transaction.
            // Premia zatrudnienia korzysta z centralnego API finansowego w transakcji oferty.
            $bonusRes = (new FinancialTransactionService($this->db))->debit(
                $this->playerId, (float)$bonus,
                FinancialTransactionService::TYPE_HR_FEE,
                tPlain('bank.tx_hr_headhunter_bonus'),
                'employee', null
            );
            if (empty($bonusRes['success'])) {
                return ['success' => false, 'message' => t('hr_headhunter.err_bonus_funds', ['cost' => self::fmt($bonus)])];
            }

            $birthDate = date('Y-m-d', mktime(0, 0, 0, rand(1, 12), rand(1, 28), date('Y') - rand(28, 55)));
            $skill = (int)$c['skill_level'];
            if ($spec['department'] === 'technical') {
                $this->insertTechnicalHeadhunterHire($c, $spec, $salary, $skill);
            } else {
                $memberId = $this->insertBoardStaffHeadhunterHire($c, $spec, $salary, $skill, $birthDate);
                $this->db->prepare("
                    INSERT INTO employee_contracts
                        (member_id, contract_start, contract_end, salary, contract_type, status)
                    VALUES (?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), ?, '1y', 'active')
                ")->execute([$memberId, $salary]);
            }

            $acceptedUpdate = $this->db->prepare("
                UPDATE headhunter_candidates
                SET status = 'accepted'
                WHERE id = ? AND player_id = ? AND status IN ('available', 'offered')
            ");
            $acceptedUpdate->execute([$c['id'], $this->playerId]);
            if ($acceptedUpdate->rowCount() !== 1) {
                throw new RuntimeException('Headhunter candidate state changed during hire.');
            }

            return [
                'success' => true,
                'decision' => 'accept',
                'message' => t('hr_headhunter.msg_hire_accepted', [
                    'first' => $c['first_name'],
                    'last' => $c['last_name'],
                    'bonus' => self::fmt($bonus),
                ]),
                'probability' => $prob,
            ];
        } catch (Throwable $e) {
            GameLog::error('HeadhunterService', 'doHire failed', $e, [
                'player_id' => $this->playerId,
                'candidate_id' => $c['id'] ?? null,
            ]);
            throw $e;
        }
    }

    /**
     * @return array{code:string,name:string,department:string}|null
     */
    private function loadSpecializationMeta(int $specializationId): ?array
    {
        $stmt = $this->db->prepare("SELECT code, name, department FROM hr_specializations WHERE id = ? LIMIT 1");
        $stmt->execute([$specializationId]);
        $spec = $stmt->fetch();
        if (!$spec) {
            return null;
        }

        return [
            'code' => (string)$spec['code'],
            'name' => (string)$spec['name'],
            'department' => (string)$spec['department'],
        ];
    }

    private function findRoleIdByCode(string $roleCode): int
    {
        $stmt = $this->db->prepare("SELECT id FROM board_roles WHERE code = ? LIMIT 1");
        $stmt->execute([$roleCode]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    private function findActiveDirectorId(string $roleCode): int
    {
        $stmt = $this->db->prepare("
            SELECT bm.id
            FROM board_members bm
            JOIN board_roles br ON br.id = bm.role_id
            WHERE bm.player_id = ?
              AND bm.member_type = 'director'
              AND bm.status = 'active'
              AND br.code = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$this->playerId, $roleCode]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array{code:string,name:string,department:string} $spec
     */
    private function insertTechnicalHeadhunterHire(array $candidate, array $spec, float $salary, int $skill): void
    {
        $managerId = $this->findActiveDirectorId('technical');
        if ($managerId <= 0) {
            throw new RuntimeException(t('hr_headhunter.err_missing_technical_role'));
        }

        $staffPerk = $this->rollStaffSpecialization($spec['code'], $skill);
        $traits = TechnicalStaffProfile::fromCandidate($candidate, $skill);
        $this->db->prepare("
            INSERT INTO technical_staff
                (player_id, manager_id, first_name, last_name, spec_code, specialization, spec_name, skill_level,
                 salary, trait_loyalty, trait_corruption_risk, trait_ambition)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ")->execute([
            $this->playerId,
            $managerId,
            $candidate['first_name'],
            $candidate['last_name'],
            $spec['code'],
            $staffPerk,
            $spec['name'],
            $skill,
            $salary,
            $traits['loyalty'],
            $traits['corruption_risk'],
            $traits['ambition'],
        ]);
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array{code:string,name:string,department:string} $spec
     */
    private function insertBoardStaffHeadhunterHire(array $candidate, array $spec, float $salary, int $skill, string $birthDate): int
    {
        $roleId = $this->findRoleIdByCode($spec['department']);
        if ($roleId <= 0) {
            throw new RuntimeException(t('hr_headhunter.err_missing_department_role'));
        }

        $this->db->prepare("
            INSERT INTO board_members
                (player_id, member_type, role_id, first_name, last_name, gender, birth_date,
                 nationality, region_code, specialization_id, experience_years,
                 skill_organization, skill_negotiation, skill_analysis,
                 skill_stress, skill_ethics,
                 trait_loyalty, trait_corruption_risk, trait_ambition,
                 salary, hired_at, status)
            VALUES (?,'staff',?,?,?,'M',?,'INT','INT',?,?,?,?,?,?,?,?,?,?,?,NOW(),'active')
        ")->execute([
            $this->playerId,
            $roleId,
            $candidate['first_name'],
            $candidate['last_name'],
            $birthDate,
            $candidate['specialization_id'],
            rand(8, 25),
            $skill,
            $skill,
            $skill,
            $skill,
            $skill,
            (int)($candidate['trait_loyalty'] ?? max(1, 10 - $skill)),
            3,
            5,
            $salary,
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function rollStaffSpecialization(string $specCode, int $skillLevel): ?string
    {
        $operatorSpecs = ['drilling_engineer', 'petroleum_engineer', 'reservoir_engineer', 'rig_manager', 'production_engineer', 'well_operator'];
        $technicianSpecs = ['maintenance_engineer', 'safety_engineer', 'pipeline_engineer', 'safety_officer', 'well_technician'];

        if (in_array($specCode, $operatorSpecs, true)) {
            $role = 'operator';
        } elseif (in_array($specCode, $technicianSpecs, true)) {
            $role = 'technician';
        } else {
            return null;
        }

        $baseChance = 0.05 + max(0, $skillLevel - 5) * 0.01;
        if ((mt_rand(1, 1000) / 1000.0) > $baseChance) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT code, rarity FROM staff_specializations WHERE role = ?");
        $stmt->execute([$role]);
        $perks = $stmt->fetchAll();
        if (empty($perks)) {
            return null;
        }

        $weights = ['common' => 60, 'uncommon' => 30, 'rare' => 10];
        $pool = [];
        foreach ($perks as $perk) {
            $weight = $weights[$perk['rarity']] ?? 20;
            for ($i = 0; $i < $weight; $i++) {
                $pool[] = $perk['code'];
            }
        }

        return $pool[array_rand($pool)];
    }

 /**
 * @return array<string, mixed>|null
 */
    public function getActiveSearch(): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT hs.*, hsp.name AS spec_name,
                       GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), hs.finished_at)) AS seconds_remaining
                FROM headhunter_searches hs
                JOIN hr_specializations hsp ON hs.specialization_id = hsp.id
                WHERE hs.player_id = ? AND hs.status = 'searching'
                ORDER BY hs.started_at DESC LIMIT 1
            ");
            $stmt->execute([$this->playerId]);
            return $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            GameLog::error('HeadhunterService', 'getActiveSearch failed', $e, [
                'player_id' => $this->playerId,
            ]);
            return null;
        }
    }

 /**
 * @return array<array<string, mixed>>
 */
    public function getAvailableCandidates(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT hc.*, hsp.name AS spec_name,
                       TIMESTAMPDIFF(HOUR, NOW(), hc.expires_at) AS hours_remaining
                FROM headhunter_candidates hc
                JOIN hr_specializations hsp ON hc.specialization_id = hsp.id
                WHERE hc.player_id = ? AND hc.status IN ('available', 'offered') AND hc.expires_at > NOW()
                ORDER BY hc.skill_level DESC
            ");
            $stmt->execute([$this->playerId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('HeadhunterService', 'getAvailableCandidates failed', $e, [
                'player_id' => $this->playerId,
            ]);
            return [];
        }
    }

 /**
 * @return array<array<string, mixed>>
 */
    public function getRecentSearches(int $limit = 5): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT hs.*, hsp.name AS spec_name
                FROM headhunter_searches hs
                JOIN hr_specializations hsp ON hs.specialization_id = hsp.id
                WHERE hs.player_id = ?
                ORDER BY hs.started_at DESC LIMIT ?
            ");
            $stmt->execute([$this->playerId, $limit]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('HeadhunterService', 'getRecentSearches failed', $e, [
                'player_id' => $this->playerId,
                'limit' => $limit,
            ]);
            return [];
        }
    }

 /**
 * @return int
 */
    private function rollCount(): int
    {
        $r = mt_rand(1, 100);
        if ($r <= 20) {
            return 0;
        }
        if ($r <= 65) {
            return 1;
        }
        return 2;
    }

 /**
 * @return int
 */
    private function rollSkill(): int
    {
        $r = mt_rand(1, 100);
        $cum = 0;
        foreach (self::$skillDist as [$min, $max, $w]) {
            $cum += $w;
            if ($r <= $cum) {
                return rand($min, $max);
            }
        }
        return 7;
    }

    private function randomName(string $type): string
    {
        try {
            $pool = ['PL', 'US_CA', 'NO', 'EU'];
            $nat = $pool[array_rand($pool)];
            $gender = rand(0, 9) < 8 ? 'M' : 'F';
            $stmt = $this->db->prepare("
                SELECT value FROM name_pool
                WHERE type = ? AND nationality IN (?, 'PL')
                  AND (gender = ? OR gender = 'N')
                ORDER BY RAND() LIMIT 1
            ");
            $stmt->execute([$type, $nat, $gender]);
            $row = $stmt->fetch();
            return $row ? $row['value'] : ($type === 'first_name' ? 'John' : 'Smith');
        } catch (Throwable $e) {
            GameLog::error('HeadhunterService', 'randomName failed', $e, [
                'type' => $type,
            ]);
            return $type === 'first_name' ? 'John' : 'Smith';
        }
    }

    public static function fmt(float $n): string
    {
        return '$' . number_format($n, 0, '.', ' ');
    }
}
