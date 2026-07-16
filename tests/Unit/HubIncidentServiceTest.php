<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';

final class HubIncidentServiceTest extends BaseTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = class_exists(\Pdo\Sqlite::class)
            ? new \Pdo\Sqlite('sqlite::memory:')
            : new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->db instanceof \Pdo\Sqlite) {
            $this->db->createFunction('GREATEST', static fn(...$values) => max($values));
            $this->db->createFunction('NOW', static fn() => date('Y-m-d H:i:s'));
        } else {
            $this->db->sqliteCreateFunction('GREATEST', static fn(...$values) => max($values));
            $this->db->sqliteCreateFunction('NOW', static fn() => date('Y-m-d H:i:s'));
        }
        $this->db->exec(
            'CREATE TABLE logistics_hubs (
                id INTEGER PRIMARY KEY,
                player_id INTEGER NOT NULL DEFAULT 0,
                tenant_player_id INTEGER NOT NULL DEFAULT 0,
                condition_pct REAL NOT NULL DEFAULT 100,
                updated_at TEXT NULL
            )'
        );
    }

    public function testRiskMultiplierIncludesAcquisitionModeLoadAndHseProtection(): void
    {
        $hubSvc = $this->getMockBuilder(HubService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAcquisitionDefaults'])
            ->getMock();

        $hubSvc->method('getAcquisitionDefaults')
            ->with('used')
            ->willReturn(['risk_mult' => 1.55]);

        $service = new HubIncidentService($this->db, $hubSvc);

        $method = new ReflectionMethod(HubIncidentService::class, 'calcRiskMultiplier');

        $hub = [
            'condition_pct' => 20.0,
            'work_mode' => 'max',
            'acquisition_type' => 'used',
        ];
        $tickResult = ['load_pct' => 130.0];

        $withoutHse = $method->invoke($service, $hub, $tickResult, []);
        $withHse = $method->invoke($service, $hub, $tickResult, [
            'active_hse' => 2,
            'failure_reduction' => 0.50,
            'catastrophe_mult' => 0.40,
            'proc_factor' => 0.50,
        ]);

        $this->assertSame(41.85, round($withoutHse, 4));
        $this->assertSame(7.8678, round($withHse, 4));
        $this->assertLessThan($withoutHse, $withHse);
    }

    public function testPausedHubDoesNotGenerateIncident(): void
    {
        $hubSvc = $this->getMockBuilder(HubService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAcquisitionDefaults'])
            ->getMock();

        $hubSvc->method('getAcquisitionDefaults')
            ->willReturn(['risk_mult' => 1.0]);

        $service = new HubIncidentService($this->db, $hubSvc);

        $result = $service->processTick(
            ['status' => 'paused', 'condition_pct' => 100.0, 'work_mode' => 'standard', 'acquisition_type' => 'new'],
            100.0,
            ['load_pct' => 120.0],
            1.0,
            1,
            []
        );

        $this->assertNull($result);
    }

    public function testDrainedBufferActivityCanGenerateIncidentWithoutNewInput(): void
    {
        $hubSvc = $this->getMockBuilder(HubService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAcquisitionDefaults'])
            ->getMock();
        $hubSvc->method('getAcquisitionDefaults')->willReturn(['risk_mult' => 1.0]);

        mt_srand(1234);
        $service = new HubIncidentService($this->db, $hubSvc);
        $this->db->exec(
            "INSERT INTO logistics_hubs
                (id, player_id, tenant_player_id, condition_pct)
             VALUES (10, 1, 0, 10)"
        );

        $result = $service->processTick(
            ['id' => 10, 'status' => 'active', 'condition_pct' => 10.0, 'work_mode' => 'max', 'acquisition_type' => 'new'],
            0.0,
            ['processed_bbl' => 200.0, 'lost_bbl' => 0.0, 'load_pct' => 150.0],
            100.0,
            1,
            []
        );

        $this->assertIsArray($result);
    }

    public function testIncidentMultiplierCanDisableHubIncidentRisk(): void
    {
        $hubSvc = $this->getMockBuilder(HubService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAcquisitionDefaults'])
            ->getMock();
        $hubSvc->method('getAcquisitionDefaults')->willReturn(['risk_mult' => 1.0]);

        mt_srand(1234);
        $service = new HubIncidentService($this->db, $hubSvc);

        $result = $service->processTick(
            ['id' => 10, 'status' => 'active', 'condition_pct' => 10.0, 'work_mode' => 'max', 'acquisition_type' => 'new'],
            200.0,
            ['processed_bbl' => 200.0, 'lost_bbl' => 0.0, 'load_pct' => 150.0],
            100.0,
            1,
            [],
            null,
            ['incident_mult' => 0.0]
        );

        $this->assertNull($result);
    }
}
