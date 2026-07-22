<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/HubService.php';

/**
 * Regression tests for player-controlled hub work modes.
 * PL: Testy regresyjne trybow pracy huba sterowanych przez gracza.
 */
final class HubWorkModeTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private HubService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = $this->createSqlitePdo();
        $this->db->exec('CREATE TABLE world_regions (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE logistics_hub_assignments (
            id INTEGER PRIMARY KEY,
            hub_id INTEGER NOT NULL,
            status TEXT NOT NULL
        )');
        $this->db->exec("CREATE TABLE logistics_hubs (
            id INTEGER PRIMARY KEY,
            player_id INTEGER NOT NULL DEFAULT 0,
            tenant_player_id INTEGER NOT NULL DEFAULT 0,
            region_id INTEGER NOT NULL DEFAULT 1,
            work_mode TEXT NOT NULL DEFAULT 'standard',
            updated_at TEXT NULL
        )");
        $this->db->exec("INSERT INTO world_regions (id, name) VALUES (1, 'Region')");
        $this->db->exec("INSERT INTO logistics_hubs (id, player_id, tenant_player_id, work_mode) VALUES
            (10, 1, 0, 'standard'),
            (20, 0, 2, 'standard'),
            (30, 3, 0, 'standard')");

        $this->service = (new ReflectionClass(HubService::class))->newInstanceWithoutConstructor();
        $this->setPrivateProperty($this->service, HubService::class, 'db', $this->db);
    }

    public function testOwnerCanChangeWorkMode(): void
    {
        $result = $this->service->setWorkMode(10, 1, 'eco');

        $this->assertTrue($result['success']);
        $this->assertSame('eco', $this->workMode(10));
    }

    public function testTenantCanChangeWorkMode(): void
    {
        $result = $this->service->setWorkMode(20, 2, 'max');

        $this->assertTrue($result['success']);
        $this->assertSame('max', $this->workMode(20));
    }

    public function testForeignPlayerCannotChangeWorkMode(): void
    {
        $result = $this->service->setWorkMode(30, 1, 'max');

        $this->assertFalse($result['success']);
        $this->assertSame('access_denied', $result['error']);
        $this->assertSame('standard', $this->workMode(30));
    }

    public function testAdminOverrideCanChangeAnyHub(): void
    {
        $result = $this->service->setWorkMode(30, 99, 'max', true);

        $this->assertTrue($result['success']);
        $this->assertSame('max', $this->workMode(30));
    }

    private function workMode(int $hubId): string
    {
        $stmt = $this->db->prepare('SELECT work_mode FROM logistics_hubs WHERE id = ?');
        $stmt->execute([$hubId]);
        return (string)$stmt->fetchColumn();
    }
}