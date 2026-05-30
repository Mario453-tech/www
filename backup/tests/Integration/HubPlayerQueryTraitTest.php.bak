<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Hub/PlayerQueryTrait.php';

final class HubPlayerQueryTraitTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();

        $this->db->exec('CREATE TABLE world_regions (id INTEGER PRIMARY KEY, name TEXT)');
        $this->db->exec('CREATE TABLE wells (
            id INTEGER PRIMARY KEY,
            player_id INTEGER NOT NULL,
            name TEXT,
            location_name TEXT,
            region_id INTEGER,
            zone_key TEXT,
            status TEXT NOT NULL,
            base_production_per_hour REAL
        )');
        // Table includes cooldown_until needed by P1.3 subquery
        $this->db->exec('CREATE TABLE logistics_hub_assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            hub_id INTEGER NOT NULL,
            well_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            access_fee_paid REAL NOT NULL DEFAULT 0,
            assigned_at TEXT NULL,
            detached_at TEXT NULL,
            cooldown_until TEXT NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )');

        $this->db->exec("INSERT INTO world_regions (id, name) VALUES (7, 'Bliski Wschód')");
    }

    // Helper: anonymous object that uses the trait
    private function makeSvc(): object
    {
        $db = $this->db;
        return new class($db) {
            use HubPlayerQueryTrait;
            public function __construct(private PDO $db) {}
        };
    }

    public function testGetUnassignedWellsIncludesOperationallyPausedWell(): void
    {
        $this->db->exec("INSERT INTO wells VALUES (1, 1, 'W1', 'Pole 1', 7, 'A1', 'no_operator', 100)");
        $this->db->exec("INSERT INTO wells VALUES (2, 1, 'W2', 'Pole 2', 7, 'A1', 'sold', 100)");
        $this->db->exec("INSERT INTO wells VALUES (3, 1, 'W3', 'Pole 3', 7, 'A1', 'active', 100)");
        $this->db->exec("INSERT INTO logistics_hub_assignments (hub_id, well_id, status) VALUES (10, 3, 'active')");

        $rows = $this->makeSvc()->getUnassignedWells(1);

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int)$rows[0]['id']);
        $this->assertSame('no_operator', $rows[0]['status']);
    }

    public function testGetUnassignedWellsDoesNotReturnAssignedWell(): void
    {
        $this->db->exec("INSERT INTO wells VALUES (1, 1, 'W1', 'Pole 1', 7, 'A1', 'active', 100)");
        $this->db->exec("INSERT INTO wells VALUES (2, 1, 'W2', 'Pole 2', 7, 'A1', 'active', 100)");
        $this->db->exec("INSERT INTO logistics_hub_assignments (hub_id, well_id, status) VALUES (10, 1, 'active')");

        $rows     = $this->makeSvc()->getUnassignedWells(1);
        $foundIds = array_map('intval', array_column($rows, 'id'));

        $this->assertNotContains(1, $foundIds, 'Assigned well must NOT appear in unassigned list');
        $this->assertContains(2, $foundIds, 'Unassigned well MUST appear in unassigned list');
    }

    public function testGetUnassignedWellsReturnsNullCooldownWhenNotInCooldown(): void
    {
        $this->db->exec("INSERT INTO wells VALUES (1, 1, 'W1', 'Pole 1', 7, 'A1', 'active', 100)");

        $rows = $this->makeSvc()->getUnassignedWells(1);

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['cooldown_until']);
    }

    public function testGetUnassignedWellsReturnsCooldownUntilWhenDetachCooldownActive(): void
    {
        $future = date('Y-m-d H:i:s', time() + 7200); // 2 hours from now
        $this->db->exec("INSERT INTO wells VALUES (1, 1, 'W1', 'Pole 1', 7, 'A1', 'active', 100)");
        $this->db->exec("INSERT INTO logistics_hub_assignments
            (hub_id, well_id, status, cooldown_until) VALUES (10, 1, 'detached', '{$future}')");

        $rows = $this->makeSvc()->getUnassignedWells(1);

        $this->assertCount(1, $rows);
        $this->assertSame($future, $rows[0]['cooldown_until'],
            'cooldown_until must be passed through from detached assignment subquery');
    }

    public function testGetUnassignedWellsReturnsNullCooldownWhenCooldownExpired(): void
    {
        $past = date('Y-m-d H:i:s', time() - 3600); // 1 hour ago (expired)
        $this->db->exec("INSERT INTO wells VALUES (1, 1, 'W1', 'Pole 1', 7, 'A1', 'active', 100)");
        $this->db->exec("INSERT INTO logistics_hub_assignments
            (hub_id, well_id, status, cooldown_until) VALUES (10, 1, 'detached', '{$past}')");

        $rows = $this->makeSvc()->getUnassignedWells(1);

        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]['cooldown_until'],
            'Expired cooldown must not appear in results');
    }

    public function testGetUnassignedWellsPicksMostRecentCooldownWhenMultipleDetached(): void
    {
        $earlier = date('Y-m-d H:i:s', time() + 1000);
        $later   = date('Y-m-d H:i:s', time() + 9000);
        $this->db->exec("INSERT INTO wells VALUES (1, 1, 'W1', 'Pole 1', 7, 'A1', 'active', 100)");
        $this->db->exec("INSERT INTO logistics_hub_assignments
            (hub_id, well_id, status, cooldown_until) VALUES (10, 1, 'detached', '{$earlier}')");
        $this->db->exec("INSERT INTO logistics_hub_assignments
            (hub_id, well_id, status, cooldown_until) VALUES (11, 1, 'detached', '{$later}')");

        $rows = $this->makeSvc()->getUnassignedWells(1);

        $this->assertSame($later, $rows[0]['cooldown_until'],
            'Must return the latest (furthest) cooldown_until');
    }
}
