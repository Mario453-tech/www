<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/HubService.php';
require_once dirname(__DIR__, 2) . '/src/HubAssignmentService.php';

final class HubAssignmentServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();

        $this->db->exec('
            CREATE TABLE players (
                id INTEGER PRIMARY KEY,
                cash REAL NOT NULL DEFAULT 0
            );
        ');

        $this->db->exec('
            CREATE TABLE wells (
                id INTEGER PRIMARY KEY,
                player_id INTEGER NOT NULL,
                region_id INTEGER,
                zone_key TEXT,
                status TEXT NOT NULL
            );
        ');

        $this->db->exec('
            CREATE TABLE logistics_hub_assignments (
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
            );
        ');
    }

    // Helper: hub mock stub with all fields assignWell() needs
    private function makeHubStub(array $overrides = []): array
    {
        return array_merge([
            'id'               => 10,
            'name'             => 'Hub Testowy',
            'status'           => 'active',
            'region_id'        => 7,
            'assigned_count'   => 0,
            'slot_limit'       => 4,
            'condition_pct'    => 90.0,
            'zone_key'         => 'A1',
            'acquisition_type' => 'new',
            'opex_per_tick'    => 400.0,
            'lease_fee_per_tick'=> 0.0,
        ], $overrides);
    }

    public function testAssignWellCreatesActiveAssignment(): void
    {
        $this->db->exec("INSERT INTO players (id, cash) VALUES (1, 999999)");
        $this->db->exec("INSERT INTO wells (id, player_id, region_id, zone_key, status) VALUES (100, 1, 7, 'A1', 'active')");

        $hubSvc = $this->createMock(HubService::class);
        $hubSvc->method('getHub')->willReturn($this->makeHubStub());
        $hubSvc->method('getWellAssignment')->with(100)->willReturn(null);
        $hubSvc->expects($this->once())->method('createEvent');

        $service = new HubAssignmentService($this->db, $hubSvc);
        $result  = $service->assignWell(1, 10, 100);

        $this->assertTrue($result['success']);
        $row = $this->db->query('SELECT hub_id, well_id, status FROM logistics_hub_assignments')->fetch();
        $this->assertSame(['hub_id' => 10, 'well_id' => 100, 'status' => 'active'], $row);
    }

    public function testAssignWellDeductsAccessFeeFromPlayer(): void
    {
        // new hub: fee = opex/slot * 5 = (400/4)*5 = 500
        $this->db->exec("INSERT INTO players (id, cash) VALUES (1, 999999)");
        $this->db->exec("INSERT INTO wells (id, player_id, region_id, zone_key, status) VALUES (101, 1, 7, 'A1', 'active')");

        $hubSvc = $this->createMock(HubService::class);
        $hubSvc->method('getHub')->willReturn($this->makeHubStub([
            'id' => 10, 'acquisition_type' => 'new', 'opex_per_tick' => 400.0, 'slot_limit' => 4,
        ]));
        $hubSvc->method('getWellAssignment')->willReturn(null);
        $hubSvc->method('createEvent');

        $service = new HubAssignmentService($this->db, $hubSvc);
        $result  = $service->assignWell(1, 10, 101);

        $this->assertTrue($result['success']);
        $this->assertSame(500.0, (float)$result['access_fee']);

        $cash = (float)$this->db->query('SELECT cash FROM players WHERE id=1')->fetchColumn();
        $this->assertEqualsWithDelta(999999 - 500.0, $cash, 0.01);

        $row = $this->db->query('SELECT access_fee_paid FROM logistics_hub_assignments')->fetchColumn();
        $this->assertEqualsWithDelta(500.0, (float)$row, 0.01);
    }

    public function testAssignWellDeductsHigherFeeForUsedHub(): void
    {
        // used hub: fee = opex/slot * 8 = (400/4)*8 = 800
        $this->db->exec("INSERT INTO players (id, cash) VALUES (1, 999999)");
        $this->db->exec("INSERT INTO wells (id, player_id, region_id, zone_key, status) VALUES (102, 1, 7, 'A1', 'active')");

        $hubSvc = $this->createMock(HubService::class);
        $hubSvc->method('getHub')->willReturn($this->makeHubStub([
            'acquisition_type' => 'used', 'opex_per_tick' => 400.0, 'slot_limit' => 4,
        ]));
        $hubSvc->method('getWellAssignment')->willReturn(null);
        $hubSvc->method('createEvent');

        $service = new HubAssignmentService($this->db, $hubSvc);
        $result  = $service->assignWell(1, 10, 102);

        $this->assertTrue($result['success']);
        $this->assertSame(800.0, (float)$result['access_fee']);
    }

    public function testAssignWellDeductsRentalDepositForRentalHub(): void
    {
        // rental hub: fee = lease_fee_per_tick * 3 = 120 * 3 = 360
        $this->db->exec("INSERT INTO players (id, cash) VALUES (1, 999999)");
        $this->db->exec("INSERT INTO wells (id, player_id, region_id, zone_key, status) VALUES (103, 1, 7, 'A1', 'active')");

        $hubSvc = $this->createMock(HubService::class);
        $hubSvc->method('getHub')->willReturn($this->makeHubStub([
            'acquisition_type' => 'rental', 'lease_fee_per_tick' => 120.0,
        ]));
        $hubSvc->method('getWellAssignment')->willReturn(null);
        $hubSvc->method('createEvent');

        $service = new HubAssignmentService($this->db, $hubSvc);
        $result  = $service->assignWell(1, 10, 103);

        $this->assertTrue($result['success']);
        $this->assertSame(360.0, (float)$result['access_fee']);
    }

    public function testAssignWellBlocksWhenInsufficientFunds(): void
    {
        // Player has only 10 PLN, fee would be 500 PLN
        $this->db->exec("INSERT INTO players (id, cash) VALUES (1, 10)");
        $this->db->exec("INSERT INTO wells (id, player_id, region_id, zone_key, status) VALUES (104, 1, 7, 'A1', 'active')");

        $hubSvc = $this->createMock(HubService::class);
        $hubSvc->method('getHub')->willReturn($this->makeHubStub([
            'acquisition_type' => 'new', 'opex_per_tick' => 400.0, 'slot_limit' => 4,
        ]));
        $hubSvc->method('getWellAssignment')->willReturn(null);
        $hubSvc->expects($this->never())->method('createEvent');

        $service = new HubAssignmentService($this->db, $hubSvc);
        $result  = $service->assignWell(1, 10, 104);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_funds', $result['error']);

        // Cash must be unchanged
        $cash = (float)$this->db->query('SELECT cash FROM players WHERE id=1')->fetchColumn();
        $this->assertEqualsWithDelta(10.0, $cash, 0.01);
    }

    public function testAssignWellReturnsConditionWarningForWeakHub(): void
    {
        $this->db->exec("INSERT INTO players (id, cash) VALUES (1, 999999)");
        $this->db->exec("INSERT INTO wells (id, player_id, region_id, zone_key, status) VALUES (105, 1, 7, 'A1', 'active')");

        $hubSvc = $this->createMock(HubService::class);
        $hubSvc->method('getHub')->willReturn($this->makeHubStub(['condition_pct' => 35.0]));
        $hubSvc->method('getWellAssignment')->willReturn(null);
        $hubSvc->expects($this->once())->method('createEvent');

        $service = new HubAssignmentService($this->db, $hubSvc);
        $result  = $service->assignWell(1, 10, 105);

        $this->assertTrue($result['success']);
        $this->assertSame('condition_low', $result['warning']);
    }

    public function testAssignWellBlocksCooldown(): void
    {
        $future = date('Y-m-d H:i:s', time() + 3600);
        $this->db->exec("INSERT INTO players (id, cash) VALUES (1, 999999)");
        $this->db->exec("INSERT INTO wells (id, player_id, region_id, zone_key, status) VALUES (106, 1, 7, 'A1', 'active')");
        $this->db->exec("INSERT INTO logistics_hub_assignments (hub_id, well_id, status, cooldown_until) VALUES (99, 106, 'detached', '{$future}')");

        $hubSvc = $this->createMock(HubService::class);
        $hubSvc->method('getHub')->willReturn($this->makeHubStub(['id' => 12]));
        $hubSvc->method('getWellAssignment')->willReturn(null);
        $hubSvc->expects($this->never())->method('createEvent');

        $service = new HubAssignmentService($this->db, $hubSvc);
        $result  = $service->assignWell(1, 12, 106);

        $this->assertFalse($result['success']);
        $this->assertSame('cooldown_active', $result['error']);
        // remaining seconds must be returned
        $this->assertGreaterThan(0, $result['cooldown_remaining_s'] ?? 0);
    }

    public function testDetachWellMarksAssignmentDetachedAndSetsCooldown(): void
    {
        $this->db->exec("INSERT INTO wells (id, player_id, region_id, zone_key, status) VALUES (107, 1, 7, 'A1', 'active')");
        $this->db->exec("INSERT INTO logistics_hub_assignments (id, hub_id, well_id, status) VALUES (1, 13, 107, 'active')");

        $hubSvc = $this->createMock(HubService::class);
        $hubSvc->method('getWellAssignment')->with(107)->willReturn([
            'id' => 1, 'hub_id' => 13, 'status' => 'active',
        ]);
        $hubSvc->method('getHub')->with(13)->willReturn([
            'id' => 13, 'name' => 'Hub Odpięcie',
        ]);
        $hubSvc->expects($this->once())->method('createEvent');

        $service = new HubAssignmentService($this->db, $hubSvc);
        $result  = $service->detachWell(1, 107);

        $this->assertTrue($result['success']);
        $row = $this->db->query("SELECT status, cooldown_until FROM logistics_hub_assignments WHERE id = 1")->fetch();
        $this->assertSame('detached', $row['status']);
        $this->assertNotEmpty($row['cooldown_until']);
    }

    public function testTransferWellDetachesOldAndCreatesNew(): void
    {
        $this->db->exec("INSERT INTO players (id, cash) VALUES (1, 999999)");
        $this->db->exec("INSERT INTO wells (id, player_id, region_id, zone_key, status) VALUES (108, 1, 7, 'A1', 'active')");
        $this->db->exec("INSERT INTO logistics_hub_assignments (id, hub_id, well_id, status) VALUES (1, 20, 108, 'active')");

        $hubSvc = $this->createMock(HubService::class);
        $hubSvc->method('getWellAssignment')->with(108)->willReturn([
            'id' => 1, 'hub_id' => 20, 'status' => 'active',
        ]);
        $hubSvc->method('getHub')->willReturnCallback(static function (int $id): array {
            return $id === 20
                ? ['id' => 20, 'name' => 'Stary', 'status' => 'active', 'region_id' => 7,
                   'assigned_count' => 1, 'slot_limit' => 4, 'condition_pct' => 80.0, 'zone_key' => 'A1',
                   'acquisition_type' => 'new', 'opex_per_tick' => 400.0, 'lease_fee_per_tick' => 0.0]
                : ['id' => 21, 'name' => 'Nowy',  'status' => 'active', 'region_id' => 7,
                   'assigned_count' => 0, 'slot_limit' => 4, 'condition_pct' => 92.0, 'zone_key' => 'A1',
                   'acquisition_type' => 'new', 'opex_per_tick' => 400.0, 'lease_fee_per_tick' => 0.0];
        });
        $hubSvc->expects($this->once())->method('createEvent');

        $service = new HubAssignmentService($this->db, $hubSvc);
        $result  = $service->transferWell(1, 108, 21);

        $this->assertTrue($result['success']);

        $old = $this->db->query("SELECT status FROM logistics_hub_assignments WHERE id = 1")->fetchColumn();
        $new = $this->db->query("SELECT hub_id, well_id, status FROM logistics_hub_assignments WHERE id != 1")->fetch();

        $this->assertSame('detached', $old);
        $this->assertSame(['hub_id' => 21, 'well_id' => 108, 'status' => 'active'], $new);
    }
}
