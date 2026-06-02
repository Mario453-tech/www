<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/LegalService.php';

/**
 * Etap 1 działu prawnego: schemat + seed konfiguracji + odczyt statusu zezwoleń.
 * Działa na SQLite in-memory (własny schemat, jak TechnicalHubTasksTest).
 */
final class LegalServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private LegalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        // Konstruktor woła ensureSchema(), które na SQLite jest no-op.
        $this->service = new LegalService($this->db);
    }

    public function testRiskLevelFromPoliticalMapsAllBands(): void
    {
        $this->assertSame('low', LegalService::riskLevelFromPolitical(1));
        $this->assertSame('low', LegalService::riskLevelFromPolitical(0));
        $this->assertSame('medium', LegalService::riskLevelFromPolitical(2));
        $this->assertSame('high', LegalService::riskLevelFromPolitical(3));
        $this->assertSame('critical', LegalService::riskLevelFromPolitical(4));
        $this->assertSame('critical', LegalService::riskLevelFromPolitical(9));
    }

    public function testSeedRegionConfigCreatesRowsMappedFromPoliticalRisk(): void
    {
        $this->seedRegions();

        $seeded = $this->service->seedRegionConfig();
        $this->assertSame(3, $seeded);

        $low      = $this->service->getRegionConfig(1);
        $high     = $this->service->getRegionConfig(2);
        $critical = $this->service->getRegionConfig(3);

        $this->assertNotNull($low);
        $this->assertSame('low', $low['risk_level']);
        $this->assertSame('Region Niski', $low['region_name']);

        $this->assertSame('high', $high['risk_level']);
        $this->assertSame('critical', $critical['risk_level']);

        // Domyślne parametry skalują się z ryzykiem.
        $this->assertGreaterThan((float)$low['application_cost'], (float)$critical['application_cost']);
        $this->assertGreaterThan((float)$low['required_capital'], (float)$high['required_capital']);
    }

    public function testSeedRegionConfigIsIdempotent(): void
    {
        $this->seedRegions();

        $this->assertSame(3, $this->service->seedRegionConfig());
        // Drugi przebieg nie dubluje wpisów.
        $this->assertSame(0, $this->service->seedRegionConfig());

        $count = (int) $this->db->query("SELECT COUNT(*) FROM legal_region_config")->fetchColumn();
        $this->assertSame(3, $count);
    }

    public function testGetAllRegionConfigsReturnsJoinedNames(): void
    {
        $this->seedRegions();
        $this->service->seedRegionConfig();

        $all = $this->service->getAllRegionConfigs();
        $this->assertCount(3, $all);
        $this->assertSame('Region Niski', $all[0]['region_name']);
    }

    public function testGetPermitStatusNoneWhenNoApplication(): void
    {
        $this->seedRegions();
        $this->service->seedRegionConfig();

        $status = $this->service->getPermitStatus(100, 1);
        $this->assertSame('none', $status['status']);
        $this->assertFalse($status['has_active']);
        $this->assertNull($status['application']);
    }

    public function testHasActivePermitTrueForGrantedAndTransitional(): void
    {
        $this->seedRegions();
        $this->insertApplication(100, 1, LegalService::STATUS_GRANTED);
        $this->insertApplication(100, 2, LegalService::STATUS_TRANSITIONAL);
        $this->insertApplication(100, 3, LegalService::STATUS_PENDING);

        $this->assertTrue($this->service->hasActivePermit(100, 1));
        $this->assertTrue($this->service->hasActivePermit(100, 2));
        $this->assertFalse($this->service->hasActivePermit(100, 3));
        $this->assertFalse($this->service->hasActivePermit(100, 999));
    }

    public function testGetPermitStatusReflectsPendingApplication(): void
    {
        $this->seedRegions();
        $this->insertApplication(100, 3, LegalService::STATUS_PENDING);

        $status = $this->service->getPermitStatus(100, 3);
        $this->assertSame('pending', $status['status']);
        $this->assertFalse($status['has_active']);
        $this->assertNotNull($status['application']);
        $this->assertSame(100, (int)$status['application']['player_id']);
    }

    // --------------------------------------------------------------- Helpers

    private function createSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE world_regions (
                id INTEGER PRIMARY KEY,
                code TEXT,
                name TEXT,
                political_risk INTEGER DEFAULT 1
            )'
        );
        $this->db->exec(
            "CREATE TABLE legal_region_config (
                region_id INTEGER PRIMARY KEY,
                enabled INTEGER DEFAULT 1,
                is_offshore INTEGER DEFAULT 0,
                risk_level TEXT DEFAULT 'low',
                application_cost REAL DEFAULT 100000,
                base_review_minutes INTEGER DEFAULT 60,
                delay_risk_pct REAL DEFAULT 0,
                delay_min_minutes INTEGER DEFAULT 10,
                delay_max_minutes INTEGER DEFAULT 30,
                no_decision_risk_pct REAL DEFAULT 0,
                refusal_risk_pct REAL DEFAULT 0,
                refusal_cooldown_minutes INTEGER DEFAULT 120,
                required_capital REAL DEFAULT 0,
                required_legal_level INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->db->exec(
            "CREATE TABLE drilling_permit_applications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                region_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                cost REAL DEFAULT 0,
                submitted_at TEXT NULL,
                decision_due_at TEXT NULL,
                decided_at TEXT NULL,
                refusal_cooldown_until TEXT NULL,
                delay_count INTEGER DEFAULT 0,
                source TEXT DEFAULT 'player',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    private function seedRegions(): void
    {
        $this->db->exec("INSERT INTO world_regions (id, code, name, political_risk) VALUES (1, 'low', 'Region Niski', 1)");
        $this->db->exec("INSERT INTO world_regions (id, code, name, political_risk) VALUES (2, 'high', 'Region Wysoki', 3)");
        $this->db->exec("INSERT INTO world_regions (id, code, name, political_risk) VALUES (3, 'crit', 'Region Krytyczny', 4)");
    }

    private function insertApplication(int $playerId, int $regionId, string $status): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO drilling_permit_applications (player_id, region_id, status, submitted_at)
             VALUES (?, ?, ?, datetime('now'))"
        );
        $stmt->execute([$playerId, $regionId, $status]);
    }
}
