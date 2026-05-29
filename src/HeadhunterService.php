<?php
/**
 * HeadhunterService - recruit specialists from competitors.
 * PL: HeadhunterService - rekrutacja specjalistow od konkurencji.
 */
class HeadhunterService
{
    private PDO $db;
    private int $playerId;

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
            $this->playerId = $playerId;
            GameLog::info('HeadhunterService', 'Service initialized', ['player_id' => $playerId]);
        } catch (Throwable $e) {
            GameLog::error('HeadhunterService', 'Initialization failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

 /** @return array<string, mixed> */
    public function startSearch(int $specializationId): array
    {
        try {
            $activeStmt = $this->db->prepare(
                "SELECT id FROM headhunter_searches WHERE player_id = ? AND status = 'searching' LIMIT 1"
            );
            $activeStmt->execute([$this->playerId]);
            if ($activeStmt->fetch()) {
                return ['success' => false, 'message' => t('hr_headhunter.err_search_active')];
            }

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

            $duration = rand(self::DURATION_MIN_SEC, self::DURATION_MAX_SEC);
            $finishedAt = date('Y-m-d H:i:s', time() + $duration);

            $this->db->beginTransaction();
            try {
                $this->db->prepare("UPDATE players SET cash = cash - ? WHERE id = ?")
                         ->execute([$cost, $this->playerId]);

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
                GameLog::error('HeadhunterService', 'startSearch transaction failed', [
                    'player_id' => $this->playerId,
                    'specialization_id' => $specializationId,
                    'error' => $e->getMessage(),
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
            GameLog::error('HeadhunterService', 'startSearch failed', [
                'player_id' => $this->playerId,
                'specialization_id' => $specializationId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => t('hr_headhunter.err_start_failed')];
        }
    }

    public function processReady(): void
    {
        try {
            $stmt = $this->db->prepare("
                SELECT hs.*, hsp.name AS spec_name,
                       hsp.base_salary_min, hsp.base_salary_max
                FROM headhunter_searches hs
                JOIN hr_specializations hsp ON hs.specialization_id = hsp.id
                WHERE hs.status = 'searching' AND hs.finished_at <= NOW()
            ");
            $stmt->execute();

            foreach ($stmt->fetchAll() as $search) {
                $this->generateCandidates($search);
            }
        } catch (Throwable $e) {
            GameLog::error('HeadhunterService', 'processReady failed', [
                'player_id' => $this->playerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function generateCandidates(array $search): void
    {
        try {
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
                "UPDATE headhunter_searches SET status = ?, result_count = ? WHERE id = ?"
            )->execute([$count > 0 ? 'completed' : 'failed', $count, $search['id']]);

            $msg = $count > 0
                ? t('hr_headhunter.notify_candidates_found', ['count' => $count, 'spec' => $search['spec_name']])
                : t('hr_headhunter.notify_candidates_missing', ['spec' => $search['spec_name']]);

            $this->db->prepare(
                "INSERT INTO technical_notifications (player_id, well_id, type, message) VALUES (?,NULL,'headhunter',?)"
            )->execute([$search['player_id'], $msg]);

            GameLog::info('HeadhunterService', 'Candidates generated', [
                'search_id' => $search['id'],
                'player_id' => $search['player_id'],
                'count' => $count,
            ]);
        } catch (Throwable $e) {
            GameLog::error('HeadhunterService', 'generateCandidates failed', [
                'search_id' => $search['id'] ?? null,
                'player_id' => $search['player_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function makeOffer(int $candidateId, float $offeredSalary, float $signingBonus): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT hc.*, hsp.name AS spec_name
                FROM headhunter_candidates hc
                JOIN hr_specializations hsp ON hc.specialization_id = hsp.id
                WHERE hc.id = ? AND hc.player_id = ?
                  AND hc.status IN ('available', 'offered') AND hc.expires_at > NOW()
            ");
            $stmt->execute([$candidateId, $this->playerId]);
            $c = $stmt->fetch();
            if (!$c) {
                return ['success' => false, 'message' => t('hr_headhunter.err_candidate_unavailable')];
            }

            $cashStmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
            $cashStmt->execute([$this->playerId]);
            if ((float)$cashStmt->fetchColumn() < $signingBonus) {
                return [
                    'success' => false,
                    'message' => t('hr_headhunter.err_bonus_funds', ['cost' => self::fmt($signingBonus)]),
                ];
            }

            $prob = $this->calcProbability($c, $offeredSalary, $signingBonus);
            $roll = mt_rand(1, 100);

            if ($roll <= $prob) {
                return $this->doHire($c, $offeredSalary, $signingBonus, $prob);
            }

            if ($roll <= $prob + 20) {
                $counterSalary = (int)round($offeredSalary * 1.15);
                $counterBonus = (int)round($signingBonus * 1.25);
                $this->db->prepare("UPDATE headhunter_candidates SET status = 'offered' WHERE id = ?")
                         ->execute([$candidateId]);

                return [
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
            }

            $this->db->prepare("UPDATE headhunter_candidates SET status = 'rejected' WHERE id = ?")
                     ->execute([$candidateId]);
            return [
                'success' => true,
                'decision' => 'reject',
                'message' => t('hr_headhunter.msg_offer_rejected', [
                    'first' => $c['first_name'],
                    'last' => $c['last_name'],
                ]),
                'probability' => $prob,
            ];
        } catch (Throwable $e) {
            GameLog::error('HeadhunterService', 'makeOffer failed', [
                'player_id' => $this->playerId,
                'candidate_id' => $candidateId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => t('hr_headhunter.err_offer_failed')];
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
            GameLog::error('HeadhunterService', 'calcProbability failed', [
                'player_id' => $this->playerId,
                'candidate_id' => $c['id'] ?? null,
                'error' => $e->getMessage(),
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
            $roleStmt = $this->db->prepare("SELECT id FROM board_roles WHERE code = 'technical' LIMIT 1");
            $roleStmt->execute();
            $role = $roleStmt->fetch();
            if (!$role) {
                return ['success' => false, 'message' => t('hr_headhunter.err_missing_technical_role')];
            }

            $this->db->beginTransaction();
            try {
                $this->db->prepare("UPDATE players SET cash = cash - ? WHERE id = ?")
                         ->execute([$bonus, $this->playerId]);

                $birthDate = date('Y-m-d', mktime(0, 0, 0, rand(1, 12), rand(1, 28), date('Y') - rand(28, 55)));
                $skill = (int)$c['skill_level'];

                $this->db->prepare("
                    INSERT INTO board_members
                        (player_id, role_id, first_name, last_name, gender, birth_date,
                         nationality, region_code, specialization_id, experience_years,
                         skill_organization, skill_negotiation, skill_analysis,
                         skill_stress, skill_ethics,
                         trait_loyalty, trait_corruption_risk, trait_ambition,
                         salary, hired_at, status)
                    VALUES (?,?,?,?,'M',?,'INT','INT',?,?,?,?,?,?,?,?,3,5,?,NOW(),'active')
                ")->execute([
                    $this->playerId,
                    $role['id'],
                    $c['first_name'], $c['last_name'], $birthDate,
                    $c['specialization_id'], rand(8, 25),
                    $skill, $skill, $skill, $skill, $skill,
                    max(1, 10 - $skill),
                    $salary,
                ]);

                $memberId = (int)$this->db->lastInsertId();

                $specStmt = $this->db->prepare("SELECT code, name FROM hr_specializations WHERE id = ?");
                $specStmt->execute([$c['specialization_id']]);
                $spec = $specStmt->fetch();
                if ($spec) {
                    $this->db->prepare("
                        INSERT INTO technical_staff
                            (player_id, manager_id, first_name, last_name,
                             spec_code, spec_name, skill_level, salary)
                        VALUES (?,?,?,?,?,?,?,?)
                    ")->execute([
                        $this->playerId, $memberId,
                        $c['first_name'], $c['last_name'],
                        $spec['code'], $spec['name'], $skill, $salary,
                    ]);
                }

                $this->db->prepare("
                    INSERT INTO employee_contracts
                        (member_id, contract_start, contract_end, salary, contract_type, status)
                    VALUES (?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), ?, '1y', 'active')
                ")->execute([$memberId, $salary]);

                $this->db->prepare("UPDATE headhunter_candidates SET status = 'accepted' WHERE id = ?")
                         ->execute([$c['id']]);

                $this->db->commit();
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                GameLog::error('HeadhunterService', 'doHire transaction failed', [
                    'player_id' => $this->playerId,
                    'candidate_id' => $c['id'] ?? null,
                    'error' => $e->getMessage(),
                ]);
                return ['success' => false, 'message' => t('hr_headhunter.err_hire_transaction', ['error' => $e->getMessage()])];
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
            GameLog::error('HeadhunterService', 'doHire failed', [
                'player_id' => $this->playerId,
                'candidate_id' => $c['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => t('hr_headhunter.err_hire_failed')];
        }
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
            GameLog::error('HeadhunterService', 'getActiveSearch failed', [
                'player_id' => $this->playerId,
                'error' => $e->getMessage(),
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
            GameLog::error('HeadhunterService', 'getAvailableCandidates failed', [
                'player_id' => $this->playerId,
                'error' => $e->getMessage(),
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
            GameLog::error('HeadhunterService', 'getRecentSearches failed', [
                'player_id' => $this->playerId,
                'limit' => $limit,
                'error' => $e->getMessage(),
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
            GameLog::error('HeadhunterService', 'randomName failed', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return $type === 'first_name' ? 'John' : 'Smith';
        }
    }

    public static function fmt(float $n): string
    {
        return '$' . number_format($n, 0, '.', ' ');
    }
}
