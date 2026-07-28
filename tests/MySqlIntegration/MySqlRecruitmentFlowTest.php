<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/FinancePolicyService.php';
require_once dirname(__DIR__, 2) . '/src/CandidateGenerator.php';
require_once dirname(__DIR__, 2) . '/src/BankAccountService.php';
require_once dirname(__DIR__, 2) . '/src/WalletConfig.php';
require_once dirname(__DIR__, 2) . '/src/FinancialTransactionService.php';
require_once dirname(__DIR__, 2) . '/src/HRService.php';
require_once dirname(__DIR__, 2) . '/src/HeadhunterService.php';

final class MySqlRecruitmentFlowTest extends MySqlIntegrationTestCase
{
    private int $logisticsDirectorId;
    private int $candidateDirectorId;
    private int $candidateStaffId;
    private int $candidateTechnicalId;
    private int $headhunterTechnicalId;
    private int $headhunterStaffId;
    private int $technicalSpecId;
    private int $logisticsSpecId;
    private int $technicalRoleId;
    private int $logisticsRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $base = $this->seed + 100;
        $this->logisticsDirectorId = $base;
        $this->candidateDirectorId = $base + 1;
        $this->candidateStaffId = $base + 2;
        $this->candidateTechnicalId = $base + 3;
        $this->headhunterTechnicalId = $base + 4;
        $this->headhunterStaffId = $base + 5;
        $this->technicalSpecId = $base + 20;
        $this->logisticsSpecId = $base + 21;

        $this->cleanupRecruitmentFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanupRecruitmentFixtures();
        parent::tearDown();
    }

    public function testSpecializedDepartmentCandidateBypassesOccupiedDirectorSeat(): void
    {
        $playerId = $this->seedPlayer();
        $this->technicalRoleId = $this->ensureRole('technical', 'Technical');
        $this->logisticsRoleId = $this->ensureRole('logistics', 'Logistics');
        $this->insertDirector($this->logisticsDirectorId, $playerId, $this->logisticsRoleId, 'Logistics', 'Director');
        $this->insertSpecialization($this->logisticsSpecId, 'phpunit_logistics_staff_' . $playerId, 'Logistics Staff', 'logistics');
        $this->insertCandidate($this->candidateStaffId, $playerId, $this->logisticsRoleId, $this->logisticsSpecId, 'Anna', 'Logistics');

        $service = $this->makeHrService();
        $result = $service->hireCandidate($this->candidateStaffId, $playerId);

        $this->assertTrue($result['success']);
        $row = $this->fetchOne(
            "SELECT id, member_type, role_id, specialization_id
             FROM board_members
             WHERE player_id = ? AND member_type = 'staff' AND specialization_id = ?
             LIMIT 1",
            [$playerId, $this->logisticsSpecId]
        );
        $this->assertNotNull($row);
        $this->assertSame('staff', $row['member_type']);
        $this->assertSame($this->logisticsRoleId, (int)$row['role_id']);
        $this->assertSame($this->logisticsSpecId, (int)$row['specialization_id']);
        $this->assertSame(1, $this->countBySql("SELECT COUNT(*) FROM board_members WHERE player_id = ? AND member_type = 'director'", [$playerId]));
        $this->assertSame(1, $this->countBySql("SELECT COUNT(*) FROM employee_contracts WHERE member_id = ?", [(int)$row['id']]));
        $this->assertSame(0, $this->countBySql("SELECT COUNT(*) FROM technical_staff WHERE player_id = ? AND spec_code = ?", [$playerId, 'phpunit_logistics_staff_' . $playerId]));
        $this->assertSame(0, $this->countBySql("SELECT COUNT(*) FROM candidates WHERE id = ?", [$this->candidateStaffId]));
    }

    public function testDirectorCandidateStaysBlockedWhenDirectorSeatIsOccupied(): void
    {
        $playerId = $this->seedPlayer();
        $this->logisticsRoleId = $this->ensureRole('logistics', 'Logistics');
        $this->insertDirector($this->logisticsDirectorId, $playerId, $this->logisticsRoleId, 'Logistics', 'Director');
        $this->insertCandidate($this->candidateDirectorId, $playerId, $this->logisticsRoleId, null, 'Maria', 'Director');

        $service = $this->makeHrService();
        $result = $service->hireCandidate($this->candidateDirectorId, $playerId);

        $this->assertFalse($result['success']);
        $this->assertSame(1, $this->countBySql("SELECT COUNT(*) FROM board_members WHERE player_id = ? AND member_type = 'director'", [$playerId]));
        $this->assertSame(1, $this->countBySql("SELECT COUNT(*) FROM candidates WHERE id = ?", [$this->candidateDirectorId]));
    }

    public function testTechnicalCandidateFromHrCreatesOnlyTechnicalStaff(): void
    {
        $playerId = $this->seedPlayer();
        $this->technicalRoleId = $this->ensureRole('technical', 'Technical');
        $this->insertDirector($this->getTrackedIds()['managerId'], $playerId, $this->technicalRoleId, 'Tech', 'Director');
        $technicalCode = 'phpunit_technical_staff_' . $playerId;
        $this->insertSpecialization($this->technicalSpecId, $technicalCode, 'Technical Staff', 'technical');
        $this->insertCandidate($this->candidateTechnicalId, $playerId, $this->technicalRoleId, $this->technicalSpecId, 'Jan', 'Engineer');

        $service = $this->makeHrService();
        $result = $service->hireCandidate($this->candidateTechnicalId, $playerId);

        $this->assertTrue($result['success']);
        $staff = $this->fetchOne(
            "SELECT manager_id, spec_code
             FROM technical_staff
             WHERE player_id = ? AND spec_code = ?
             LIMIT 1",
            [$playerId, $technicalCode]
        );
        $this->assertNotNull($staff);
        $this->assertSame($this->getTrackedIds()['managerId'], (int)$staff['manager_id']);
        $this->assertSame(1, $this->countBySql("SELECT COUNT(*) FROM board_members WHERE player_id = ?", [$playerId]));
        $this->assertSame(0, $this->countBySql(
            "SELECT COUNT(*)
             FROM employee_contracts ec
             JOIN board_members bm ON bm.id = ec.member_id
             WHERE bm.player_id = ?",
            [$playerId]
        ));
        $this->assertSame(1, $this->countBySql("SELECT COUNT(*) FROM bank_transactions WHERE from_player_id = ? AND transaction_type = 'hr_fee'", [$playerId]));
    }

    public function testHeadhunterTechnicalHireCreatesOnlyTechnicalStaff(): void
    {
        $playerId = $this->seedPlayer();
        $this->technicalRoleId = $this->ensureRole('technical', 'Technical');
        $this->insertDirector($this->getTrackedIds()['managerId'], $playerId, $this->technicalRoleId, 'Tech', 'Director');
        $technicalCode = 'phpunit_hh_technical_' . $playerId;
        $this->insertSpecialization($this->technicalSpecId, $technicalCode, 'Technical Hunter', 'technical');
        $this->insertHeadhunterCandidate($this->headhunterTechnicalId, $playerId, $this->technicalSpecId, 'Ivar', 'Tech');

        $service = $this->makeHeadhunterService($playerId);
        $candidate = $this->fetchOne("SELECT * FROM headhunter_candidates WHERE id = ?", [$this->headhunterTechnicalId]);
        $result = $this->invokePrivateHeadhunterHire($service, $candidate, 15000.0, 50000.0, 90);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $this->countBySql("SELECT COUNT(*) FROM technical_staff WHERE player_id = ? AND spec_code = ?", [$playerId, $technicalCode]));
        $this->assertSame(1, $this->countBySql("SELECT COUNT(*) FROM board_members WHERE player_id = ?", [$playerId]));
        $this->assertSame(0, $this->countBySql(
            "SELECT COUNT(*)
             FROM employee_contracts ec
             JOIN board_members bm ON bm.id = ec.member_id
             WHERE bm.player_id = ?",
            [$playerId]
        ));
        $this->assertSame(1, $this->countBySql("SELECT COUNT(*) FROM headhunter_candidates WHERE id = ? AND status = 'accepted'", [$this->headhunterTechnicalId]));
    }

    public function testHeadhunterDepartmentHireCreatesBoardStaffOnly(): void
    {
        $playerId = $this->seedPlayer();
        $this->logisticsRoleId = $this->ensureRole('logistics', 'Logistics');
        $this->insertSpecialization($this->logisticsSpecId, 'phpunit_hh_logistics_' . $playerId, 'Logistics Hunter', 'logistics');
        $this->insertHeadhunterCandidate($this->headhunterStaffId, $playerId, $this->logisticsSpecId, 'Lena', 'Dispatch');

        $service = $this->makeHeadhunterService($playerId);
        $candidate = $this->fetchOne("SELECT * FROM headhunter_candidates WHERE id = ?", [$this->headhunterStaffId]);
        $result = $this->invokePrivateHeadhunterHire($service, $candidate, 14200.0, 75000.0, 90);

        $this->assertTrue($result['success']);
        $row = $this->fetchOne(
            "SELECT id, member_type, role_id, specialization_id
             FROM board_members
             WHERE player_id = ? AND member_type = 'staff' AND specialization_id = ?
             LIMIT 1",
            [$playerId, $this->logisticsSpecId]
        );
        $this->assertNotNull($row);
        $this->assertSame($this->logisticsRoleId, (int)$row['role_id']);
        $this->assertSame(1, $this->countBySql("SELECT COUNT(*) FROM employee_contracts WHERE member_id = ?", [(int)$row['id']]));
        $this->assertSame(0, $this->countBySql("SELECT COUNT(*) FROM technical_staff WHERE player_id = ?", [$playerId]));
        $this->assertSame(1, $this->countBySql("SELECT COUNT(*) FROM headhunter_candidates WHERE id = ? AND status = 'accepted'", [$this->headhunterStaffId]));
    }

    public function testHrStrikePersistsExtendedRecruitmentAndHeadhunterDeadlines(): void
    {
        $playerId = $this->seedPlayer();
        $this->logisticsRoleId = $this->ensureRole('logistics', 'Logistics');
        $this->insertSpecialization(
            $this->logisticsSpecId,
            'phpunit_hr_strike_' . $playerId,
            'HR Strike',
            'logistics'
        );
        $config = new EmployeeSystemConfigService($this->db);
        $original = [
            'feature_strike_effects' => $config->getBool('feature_strike_effects'),
            'strike_hr_recruitment_time_multiplier' => $config->getFloat('strike_hr_recruitment_time_multiplier'),
        ];

        try {
            $config->save([
                'feature_strike_effects' => true,
                'strike_hr_recruitment_time_multiplier' => 3.0,
            ]);
            $this->db->prepare(
                "INSERT INTO employee_strikes
                    (player_id, department_code, status, open_key, support_pct)
                 VALUES (?, 'hr', 'active', ?, 70)"
            )->execute([$playerId, $playerId . ':hr']);

            $recruitment = (new HRService())->startRecruitment(
                $playerId,
                $this->logisticsRoleId,
                'PL',
                null,
                'director',
                'local'
            );
            $headhunter = (new HeadhunterService($playerId))->startSearch($this->logisticsSpecId);

            $this->assertTrue($recruitment['success']);
            $this->assertGreaterThan(240, $recruitment['duration']);
            $this->assertTrue($headhunter['success']);
            $this->assertGreaterThanOrEqual(
                HeadhunterService::DURATION_MIN_SEC * 3 - 2,
                strtotime((string)$headhunter['finished_at']) - time()
            );
        } finally {
            $this->db->prepare('DELETE FROM employee_strikes WHERE player_id = ?')->execute([$playerId]);
            (new EmployeeSystemConfigService($this->db))->save($original);
        }
    }

    private function makeHrService(): HRService
    {
        $ref = new ReflectionClass(HRService::class);
        /** @var HRService $service */
        $service = $ref->newInstanceWithoutConstructor();
        $this->setPrivatePropertyLocal($service, HRService::class, 'db', $this->db);
        return $service;
    }

    private function makeHeadhunterService(int $playerId): HeadhunterService
    {
        $ref = new ReflectionClass(HeadhunterService::class);
        /** @var HeadhunterService $service */
        $service = $ref->newInstanceWithoutConstructor();
        $this->setPrivatePropertyLocal($service, HeadhunterService::class, 'db', $this->db);
        $this->setPrivatePropertyLocal($service, HeadhunterService::class, 'playerId', $playerId);
        return $service;
    }

    /**
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private function invokePrivateHeadhunterHire(HeadhunterService $service, array $candidate, float $salary, float $bonus, int $prob): array
    {
        $callable = \Closure::bind(
            function (array $c, float $s, float $b, int $p): array {
                return $this->doHire($c, $s, $b, $p);
            },
            $service,
            HeadhunterService::class
        );

        return $callable($candidate, $salary, $bonus, $prob);
    }

    private function ensureRole(string $code, string $name): int
    {
        $stmt = $this->db->prepare("SELECT id FROM board_roles WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            return (int)$existing;
        }

        $roleId = $this->seed + 200 + random_int(1, 20);
        $this->db->prepare("
            INSERT INTO board_roles (id, code, name, description, icon, sort_order, is_required, is_active, created_at)
            VALUES (?, ?, ?, '', '', 0, 0, 1, NOW())
        ")->execute([$roleId, $code, $name]);
        return $roleId;
    }

    private function insertDirector(int $memberId, int $playerId, int $roleId, string $firstName, string $lastName): void
    {
        $this->db->prepare("
            INSERT INTO board_members
                (id, player_id, member_type, role_id, first_name, last_name, gender, birth_date, nationality,
                 experience_years, skill_organization, skill_negotiation, skill_analysis, skill_stress,
                 skill_ethics, trait_loyalty, trait_corruption_risk, trait_ambition, salary, hired_at, status)
            VALUES (?, ?, 'director', ?, ?, ?, 'M', '1980-01-01', 'PL', 12, 7, 7, 7, 7, 7, 7, 3, 6, 12000, NOW(), 'active')
        ")->execute([$memberId, $playerId, $roleId, $firstName, $lastName]);
    }

    private function insertSpecialization(int $specId, string $code, string $name, string $department): void
    {
        $this->db->prepare("
            INSERT INTO hr_specializations
                (id, code, name, department, rarity, base_salary_min, base_salary_max, min_age, max_age, description)
            VALUES (?, ?, ?, ?, 'common', 8000, 16000, 25, 58, '')
        ")->execute([$specId, $code, $name, $department]);
    }

    private function insertCandidate(int $candidateId, int $playerId, int $roleId, ?int $specializationId, string $firstName, string $lastName): void
    {
        $this->db->prepare("
            INSERT INTO candidates
                (id, player_id, director_status, role_id, request_id, first_name, last_name, gender, birth_date,
                 nationality, region_code, specialization_id, experience_years, skill_organization,
                 skill_negotiation, skill_analysis, skill_stress, skill_ethics, trait_loyalty,
                 trait_corruption_risk, trait_ambition, expected_salary, expires_at)
            VALUES (?, ?, 'pending', ?, NULL, ?, ?, 'F', '1990-01-01', 'PL', 'PL', ?, 7, 8, 7, 8, 7, 6, 7, 3, 6, 9500, DATE_ADD(NOW(), INTERVAL 1 DAY))
        ")->execute([$candidateId, $playerId, $roleId, $firstName, $lastName, $specializationId]);
    }

    private function insertHeadhunterCandidate(int $candidateId, int $playerId, int $specializationId, string $firstName, string $lastName): void
    {
        $searchId = $candidateId + 1000;
        $specCodeStmt = $this->db->prepare("SELECT code FROM hr_specializations WHERE id = ? LIMIT 1");
        $specCodeStmt->execute([$specializationId]);
        $specCode = (string)$specCodeStmt->fetchColumn();
        $this->db->prepare("
            INSERT INTO headhunter_searches
                (id, player_id, specialization_id, spec_code, finished_at, status, result_count)
            VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY), 'completed', 1)
        ")->execute([$searchId, $playerId, $specializationId, $specCode]);

        $this->db->prepare("
            INSERT INTO headhunter_candidates
                (id, search_id, player_id, first_name, last_name, specialization_id, skill_level, current_company,
                 salary_expectation, signing_bonus, join_probability, trait_loyalty, status, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, 8, 'PHPUnit Energy', 12000, 40000, 90, 6, 'available', DATE_ADD(NOW(), INTERVAL 1 DAY))
        ")->execute([$candidateId, $searchId, $playerId, $firstName, $lastName, $specializationId]);
    }

    private function cleanupRecruitmentFixtures(): void
    {
        $playerId = $this->seed;
        $candidateIds = [
            $this->candidateDirectorId,
            $this->candidateStaffId,
            $this->candidateTechnicalId,
            $this->headhunterTechnicalId,
            $this->headhunterStaffId,
        ];
        $memberIds = $this->memberIdsForPlayer($playerId);
        if ($memberIds !== []) {
            $this->deleteByIdsLocal('employee_contracts', 'member_id', $memberIds);
            $this->deleteByIdsLocal('employment_history', 'member_id', $memberIds);
        }

        $this->deleteByIdsLocal('bank_transactions', 'from_player_id', [$playerId]);
        $this->deleteByIdsLocal('bank_transactions', 'to_player_id', [$playerId]);
        $this->deleteByIdsLocal('candidates', 'id', $candidateIds);
        $this->deleteByIdsLocal('headhunter_candidates', 'id', [$this->headhunterTechnicalId, $this->headhunterStaffId]);
        $this->deleteByIdsLocal('headhunter_candidates', 'player_id', [$playerId]);
        $this->deleteByIdsLocal('headhunter_searches', 'id', [$this->headhunterTechnicalId + 1000, $this->headhunterStaffId + 1000]);
        $this->deleteByIdsLocal('headhunter_searches', 'player_id', [$playerId]);
        $this->deleteByIdsLocal('recruitment_requests', 'player_id', [$playerId]);
        $this->deleteByIdsLocal('employee_strikes', 'player_id', [$playerId]);
        $this->deleteByIdsLocal('technical_staff', 'player_id', [$playerId]);
        $this->deleteByIdsLocal('board_members', 'player_id', [$playerId]);
        $this->deleteByIdsLocal('hr_specializations', 'id', [$this->technicalSpecId, $this->logisticsSpecId]);
    }

    /** @return list<int> */
    private function memberIdsForPlayer(int $playerId): array
    {
        $stmt = $this->db->prepare("SELECT id FROM board_members WHERE player_id = ?");
        $stmt->execute([$playerId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function countBySql(string $sql, array $params): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    private function deleteByIdsLocal(string $table, string $column, array $ids): void
    {
        $ids = array_values(array_unique(array_filter($ids, static fn($id): bool => is_int($id))));
        if ($ids === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE `{$column}` IN ({$placeholders})");
        $stmt->execute($ids);
    }

    private function setPrivatePropertyLocal(object $object, string $className, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($className, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
