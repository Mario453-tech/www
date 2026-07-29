<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__, 2) . '/src/HR/AdminHRConfigService.php';

final class AdminHRConfigServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private AdminHRConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        EmployeeSystemBootstrap::ensure($this->db);
        $this->service = new AdminHRConfigService($this->db);
    }

    public function testSettingsAllowlistAcceptsKnownKeysAndRejectsUnknownKeys(): void
    {
        $changes = $this->service->saveSettings('relations', ['raise_response_hours' => 72]);
        self::assertSame(72, $changes['raise_response_hours']['new']);

        $this->expectException(InvalidArgumentException::class);
        $this->service->saveSettings('relations', ['unknown_config_key' => 1]);
    }

    public function testSettingsExposeAllSafeGroups(): void
    {
        $groups = $this->service->groupedSettings();

        self::assertSame(
            ['morale', 'relations', 'strikes', 'negotiations', 'effects'],
            array_keys($groups)
        );
        self::assertArrayHasKey('leave_notice_hours', $groups['relations']['definitions']);
        self::assertArrayHasKey('strike_logistics_capacity_cap', $groups['effects']['definitions']);
        self::assertSame(
            count((new EmployeeSystemConfigService($this->db))->definitions()),
            array_sum(array_map(
                static fn(array $group): int => count($group['definitions']),
                $groups
            ))
        );
    }

    public function testDialogueCrudUsesValidatedService(): void
    {
        $id = $this->service->saveDialogue([
            'context_key' => 'raise_requested',
            'department_code' => 'hr',
            'tone' => 'formal',
            'text_pl' => 'Prosimy o rozmowe o wynagrodzeniu.',
            'text_en' => 'We request a compensation discussion.',
            'weight' => 1,
            'is_active' => 1,
        ], null);

        self::assertGreaterThan(0, $id);
        $copyId = $this->service->duplicateDialogue($id);
        self::assertNotSame($id, $copyId);
        $this->service->toggleDialogue($copyId, false);
        self::assertSame(
            0,
            (int)$this->db->query('SELECT is_active FROM employee_dialogue_templates WHERE id=' . $copyId)->fetchColumn()
        );
    }
}
