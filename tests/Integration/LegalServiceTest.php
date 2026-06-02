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
        $this->db->exec('CREATE TABLE players (id INTEGER PRIMARY KEY, cash REAL DEFAULT 0)');
        $this->db->exec(
            'CREATE TABLE wells (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                region_id INTEGER NULL,
                status TEXT NOT NULL DEFAULT \'active\'
            )'
        );
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

    private function seedPlayer(int $id, float $cash): void
    {
        $this->db->prepare("INSERT INTO players (id, cash) VALUES (?, ?)")->execute([$id, $cash]);
    }

    /** @param array<string,mixed> $over */
    private function seedConfig(int $regionId, array $over = []): void
    {
        $cfg = array_merge([
            'enabled' => 1, 'risk_level' => 'medium', 'application_cost' => 250000.0,
            'base_review_minutes' => 60, 'refusal_cooldown_minutes' => 120,
            'required_capital' => 0.0,
        ], $over);
        $this->db->prepare(
            "INSERT INTO legal_region_config
                (region_id, enabled, risk_level, application_cost, base_review_minutes,
                 refusal_cooldown_minutes, required_capital)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $regionId, $cfg['enabled'], $cfg['risk_level'], $cfg['application_cost'],
            $cfg['base_review_minutes'], $cfg['refusal_cooldown_minutes'], $cfg['required_capital'],
        ]);
    }

    private function cashOf(int $playerId): float
    {
        return (float)$this->db->query("SELECT cash FROM players WHERE id = {$playerId}")->fetchColumn();
    }

    // --------------------------------------------------- submitApplication

    public function testSubmitApplicationChargesCostAndCreatesPending(): void
    {
        $this->seedRegions();
        $this->seedConfig(1, ['application_cost' => 250000.0, 'base_review_minutes' => 60]);
        $this->seedPlayer(100, 1000000.0);

        $now = new DateTime('2026-06-02 12:00:00');
        $res = $this->service->submitApplication(100, 1, $now);

        $this->assertTrue($res['success']);
        $this->assertSame('submitted', $res['code']);
        $this->assertSame(750000.0, $this->cashOf(100)); // 1 000 000 - 250 000

        $status = $this->service->getPermitStatus(100, 1);
        $this->assertSame('pending', $status['status']);
        $this->assertSame('2026-06-02 13:00:00', $status['application']['decision_due_at']);
        $this->assertEqualsWithDelta(250000.0, (float)$status['application']['cost'], 0.001);
    }

    public function testSubmitBlockedWhenAlreadyActive(): void
    {
        $this->seedRegions();
        $this->seedConfig(1);
        $this->seedPlayer(100, 1000000.0);
        $this->insertApplication(100, 1, LegalService::STATUS_GRANTED);

        $res = $this->service->submitApplication(100, 1);
        $this->assertFalse($res['success']);
        $this->assertSame('already_active', $res['code']);
        $this->assertSame(1000000.0, $this->cashOf(100)); // bez pobrania opłaty
    }

    public function testSubmitBlockedWhenInProgress(): void
    {
        $this->seedRegions();
        $this->seedConfig(1);
        $this->seedPlayer(100, 1000000.0);
        $this->insertApplication(100, 1, LegalService::STATUS_PENDING);

        $res = $this->service->submitApplication(100, 1);
        $this->assertFalse($res['success']);
        $this->assertSame('in_progress', $res['code']);
    }

    public function testSubmitBlockedWhenCapitalRequirementNotMet(): void
    {
        $this->seedRegions();
        $this->seedConfig(2, ['risk_level' => 'high', 'required_capital' => 5000000.0, 'application_cost' => 500000.0]);
        $this->seedPlayer(100, 1000000.0); // < required_capital

        $res = $this->service->submitApplication(100, 2);
        $this->assertFalse($res['success']);
        $this->assertSame('region_locked', $res['code']);
        $this->assertSame(1000000.0, $this->cashOf(100)); // bez pobrania
    }

    public function testSubmitBlockedWhenInsufficientFunds(): void
    {
        $this->seedRegions();
        $this->seedConfig(1, ['application_cost' => 250000.0, 'required_capital' => 0.0]);
        $this->seedPlayer(100, 100000.0); // < application_cost

        $res = $this->service->submitApplication(100, 1);
        $this->assertFalse($res['success']);
        $this->assertSame('insufficient_funds', $res['code']);
        $this->assertSame(100000.0, $this->cashOf(100));
    }

    public function testSubmitBlockedDuringRefusalCooldown(): void
    {
        $this->seedRegions();
        $this->seedConfig(1);
        $this->seedPlayer(100, 1000000.0);
        // Odmowa z cooldownem do przyszłości.
        $this->db->prepare(
            "INSERT INTO drilling_permit_applications (player_id, region_id, status, refusal_cooldown_until)
             VALUES (100, 1, 'refused', datetime('now','+1 hour'))"
        )->execute();

        $res = $this->service->submitApplication(100, 1);
        $this->assertFalse($res['success']);
        $this->assertSame('cooldown', $res['code']);
    }

    public function testSubmitAllowedAfterRefusalCooldownElapsed(): void
    {
        $this->seedRegions();
        $this->seedConfig(1, ['application_cost' => 250000.0]);
        $this->seedPlayer(100, 1000000.0);
        // Odmowa z cooldownem w przeszłości -> ponowny wniosek dozwolony, aktualizuje wiersz.
        $this->db->prepare(
            "INSERT INTO drilling_permit_applications (player_id, region_id, status, refusal_cooldown_until)
             VALUES (100, 1, 'refused', datetime('now','-1 hour'))"
        )->execute();

        $res = $this->service->submitApplication(100, 1);
        $this->assertTrue($res['success']);
        $this->assertSame('pending', $this->service->getPermitStatus(100, 1)['status']);
        $this->assertSame(750000.0, $this->cashOf(100));

        // Nadal jeden wiersz (UPDATE, nie INSERT).
        $count = (int)$this->db->query("SELECT COUNT(*) FROM drilling_permit_applications WHERE player_id=100 AND region_id=1")->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function testSubmitUnknownRegionReturnsError(): void
    {
        $this->seedRegions();
        $this->seedPlayer(100, 1000000.0);
        // brak konfiguracji regionu 1
        $res = $this->service->submitApplication(100, 1);
        $this->assertFalse($res['success']);
        $this->assertSame('unknown_region', $res['code']);
    }

    // ------------------------------------------------ migrateTransitionalPermits

    public function testMigrateCreatesTransitionalForPlayerWithWell(): void
    {
        $this->seedRegions();
        $this->service->seedRegionConfig();
        $this->seedPlayer(200, 0.0);
        $this->insertWell(200, 1, 'active');

        $count = $this->service->migrateTransitionalPermits();

        $this->assertSame(1, $count);
        $status = $this->service->getPermitStatus(200, 1);
        $this->assertSame('transitional', $status['status']);
        $this->assertTrue($status['has_active']);
    }

    public function testMigrateIsIdempotent(): void
    {
        $this->seedRegions();
        $this->service->seedRegionConfig();
        $this->seedPlayer(201, 0.0);
        $this->insertWell(201, 1, 'active');

        $this->assertSame(1, $this->service->migrateTransitionalPermits());
        // Drugi przebieg nie dodaje duplikatu.
        $this->assertSame(0, $this->service->migrateTransitionalPermits());

        $appCount = (int)$this->db->query(
            "SELECT COUNT(*) FROM drilling_permit_applications WHERE player_id = 201 AND region_id = 1"
        )->fetchColumn();
        $this->assertSame(1, $appCount);
    }

    public function testMigrateSkipsPlayerWhoAlreadyHasPermit(): void
    {
        $this->seedRegions();
        $this->service->seedRegionConfig();
        $this->seedPlayer(202, 0.0);
        $this->insertWell(202, 1, 'active');
        $this->insertApplication(202, 1, LegalService::STATUS_GRANTED);

        $count = $this->service->migrateTransitionalPermits();

        $this->assertSame(0, $count);
        // Status pozostaje granted, nie nadpisany.
        $this->assertSame('granted', $this->service->getPermitStatus(202, 1)['status']);
    }

    public function testMigrateSkipsSeizedAndSoldWells(): void
    {
        $this->seedRegions();
        $this->service->seedRegionConfig();
        $this->seedPlayer(203, 0.0);
        $this->insertWell(203, 1, 'seized');
        $this->insertWell(203, 2, 'sold');

        $count = $this->service->migrateTransitionalPermits();

        $this->assertSame(0, $count);
    }

    public function testMigrateSkipsRegionWithoutConfig(): void
    {
        // Region 1 w wells, ale bez legal_region_config (seedRegionConfig nie wywołane)
        $this->seedRegions();
        $this->seedPlayer(204, 0.0);
        $this->insertWell(204, 1, 'active');

        $count = $this->service->migrateTransitionalPermits();

        $this->assertSame(0, $count);
    }

    public function testMigrateHandlesMultiplePlayersAndRegions(): void
    {
        $this->seedRegions();
        $this->service->seedRegionConfig();
        $this->seedPlayer(210, 0.0);
        $this->seedPlayer(211, 0.0);
        $this->insertWell(210, 1, 'active');
        $this->insertWell(210, 2, 'active'); // gracz 210: 2 regiony
        $this->insertWell(211, 1, 'active'); // gracz 211: 1 region, już ma zezwolenie
        $this->insertApplication(211, 1, LegalService::STATUS_PENDING); // in progress → skip

        $count = $this->service->migrateTransitionalPermits();

        // 210→region1, 210→region2 = 2 nowe; 211→region1 ma już pending → skip
        $this->assertSame(2, $count);
    }

    // ------------------------------------------------------ helpers (migration)

    private function insertWell(int $playerId, int $regionId, string $status): void
    {
        $this->db->prepare(
            "INSERT INTO wells (player_id, region_id, status) VALUES (?, ?, ?)"
        )->execute([$playerId, $regionId, $status]);
    }
}
