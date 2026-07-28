<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/BankNegotiationService.php';

final class MySqlBankNegotiationOwnershipTest extends MySqlIntegrationTestCase
{
    /** @var list<int> */
    private array $createdMemberIds = [];

    protected function tearDown(): void
    {
        $playerId = $this->getTrackedIds()['playerId'];
        $this->db->prepare('DELETE FROM recruitment_requests WHERE player_id = ?')->execute([$playerId]);

        if ($this->createdMemberIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdMemberIds), '?'));
            $this->db->prepare("DELETE FROM employee_contracts WHERE member_id IN ({$placeholders})")
                ->execute($this->createdMemberIds);
            $this->db->prepare("DELETE FROM board_members WHERE id IN ({$placeholders})")
                ->execute($this->createdMemberIds);
        }

        parent::tearDown();
    }

    public function testNegotiationContextDoesNotUseAnotherPlayersDirectors(): void
    {
        $playerId = $this->seedPlayer();
        $foreignPlayerId = $playerId + 1000;
        $roles = $this->loadRoleIds();

        foreach (['finance', 'legal'] as $offset => $roleCode) {
            $memberId = $this->seed + 100 + $offset;
            $this->createdMemberIds[] = $memberId;

            $this->db->prepare(
                'INSERT INTO board_members
                    (id, player_id, member_type, role_id, first_name, last_name, gender, birth_date,
                     nationality, experience_years, skill_organization, skill_negotiation,
                     skill_analysis, skill_stress, skill_ethics, trait_loyalty,
                     trait_corruption_risk, trait_ambition, salary, status)
                 VALUES (?, ?, \'director\', ?, ?, ?, \'M\', \'1980-01-01\',
                         \'PL\', 15, 10, 10, 10, 10, 10, 10, 0, 5, 10000, \'active\')'
            )->execute([
                $memberId,
                $foreignPlayerId,
                $roles[$roleCode],
                'Foreign',
                ucfirst($roleCode),
            ]);

            $this->db->prepare(
                'INSERT INTO employee_contracts
                    (member_id, contract_start, contract_end, salary, bonus, contract_type, status)
                 VALUES (?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 10000, 0, \'1y\', \'active\')'
            )->execute([$memberId]);

            // The tested player completed recruitment for this role but has no matching director.
            // Testowany gracz zakonczyl rekrutacje tej roli, ale nie ma pasujacego dyrektora.
            $this->db->prepare(
                'INSERT INTO recruitment_requests
                    (role_id, player_id, initiated_by, recruitment_type, ready_at, status)
                 VALUES (?, ?, \'director\', \'local\', NOW(), \'completed\')'
            )->execute([$roles[$roleCode], $playerId]);
        }

        $service = new BankNegotiationService();
        $method = new ReflectionMethod(BankNegotiationService::class, 'buildContext');
        $context = $method->invoke($service, $playerId, [
            'principal_amount' => 100000.0,
            'remaining_amount' => 50000.0,
            'status' => 'active',
        ]);

        $this->assertNull($context['cfoName']);
        $this->assertSame(0, $context['cfoSkill']);
        $this->assertNull($context['lawyerName']);
    }

    public function testFinanceStrikeDisablesCfoNegotiationBonuses(): void
    {
        $playerId = $this->seedPlayer();
        $roles = $this->loadRoleIds();
        $memberId = $this->seed + 150;
        $this->createdMemberIds[] = $memberId;
        $this->db->prepare(
            'INSERT INTO board_members
                (id, player_id, member_type, role_id, first_name, last_name, gender, birth_date,
                 nationality, experience_years, skill_organization, skill_negotiation,
                 skill_analysis, skill_stress, skill_ethics, trait_loyalty,
                 trait_corruption_risk, trait_ambition, salary, status)
             VALUES (?, ?, \'director\', ?, \'Finance\', \'Director\', \'M\', \'1980-01-01\',
                     \'PL\', 15, 10, 10, 10, 10, 10, 10, 0, 5, 10000, \'active\')'
        )->execute([$memberId, $playerId, $roles['finance']]);
        $this->db->prepare(
            'INSERT INTO employee_contracts
                (member_id, contract_start, contract_end, salary, bonus, contract_type, status)
             VALUES (?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 10000, 0, \'1y\', \'active\')'
        )->execute([$memberId]);
        $this->db->prepare(
            'INSERT INTO recruitment_requests
                (role_id, player_id, initiated_by, recruitment_type, ready_at, status)
             VALUES (?, ?, \'director\', \'local\', NOW(), \'completed\')'
        )->execute([$roles['finance'], $playerId]);

        $config = new EmployeeSystemConfigService($this->db);
        $original = $config->getBool('feature_strike_effects');
        try {
            $config->save(['feature_strike_effects' => true]);
            $this->db->prepare(
                "INSERT INTO employee_strikes
                    (player_id, department_code, status, open_key, support_pct)
                 VALUES (?, 'finance', 'active', ?, 70)"
            )->execute([$playerId, $playerId . ':finance']);

            $method = new ReflectionMethod(BankNegotiationService::class, 'buildContext');
            $context = $method->invoke(new BankNegotiationService($this->db), $playerId, [
                'principal_amount' => 100000.0,
                'remaining_amount' => 50000.0,
                'status' => 'active',
            ]);

            $this->assertNull($context['cfoName']);
            $this->assertSame(0, $context['cfoSkill']);
            $this->assertSame(0.0, $context['cfo_fee_reduction']);
        } finally {
            $this->db->prepare('DELETE FROM employee_strikes WHERE player_id = ?')->execute([$playerId]);
            (new EmployeeSystemConfigService($this->db))->save([
                'feature_strike_effects' => $original,
            ]);
        }
    }

    /**
     * @return array{finance:int,legal:int}
     */
    private function loadRoleIds(): array
    {
        $stmt = $this->db->prepare(
            "SELECT code, id FROM board_roles WHERE code IN ('finance', 'legal')"
        );
        $stmt->execute();
        $roles = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $roles[(string)$row['code']] = (int)$row['id'];
        }

        $this->assertArrayHasKey('finance', $roles);
        $this->assertArrayHasKey('legal', $roles);

        return [
            'finance' => $roles['finance'],
            'legal' => $roles['legal'],
        ];
    }
}
