<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/LegalService.php';
require_once dirname(__DIR__, 2) . '/src/Tick/LegalSection.php';
require_once dirname(__DIR__, 2) . '/src/WorldMap.php';

/**
 * Testy symulacyjne działu prawnego P1.
 *
 * Symulują pełne cykle życia zezwolenia używając kontrolowanego czasu:
 *  - gracz składa wniosek (LegalService::submitApplication)
 *  - "czas mija" (DateTime tick > decision_due_at)
 *  - tick rozpatruje wniosek (LegalSection::run)
 *  - bramka mapy reaguje poprawnie (WorldMap::regionPurchaseBlock)
 *
 * Wyniki są deterministyczne dzięki ustawieniu % ryzyk na 0 lub 100.
 */
final class LegalPermitSimulationTest extends SqliteIntegrationTestCase
{
    private PDO          $db;
    private LegalService $legal;

    private const PLAYER_A  = 1001;
    private const PLAYER_B  = 1002;
    private const REGION_LOW    = 10;
    private const REGION_HIGH   = 20;
    private const REGION_STRICT = 30;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db    = $this->createSqlitePdo();
        $this->db->sqliteCreateFunction('DATE_ADD', static fn() => '', 2);
        $this->createSchema();
        $this->seedBaseData();
        $this->legal = new LegalService($this->db);
    }

    // =====================================================================
    // SCENARIUSZ 1: Pełna szczęśliwa ścieżka — złożenie → granted → mapa odblokowana
    // =====================================================================

    public function testHappyPathSubmitToGrantedUnlocksMapGate(): void
    {
        // Gracz składa wniosek o 12:00
        $t0  = new DateTime('2026-06-01 12:00:00');
        $res = $this->legal->submitApplication(self::PLAYER_A, self::REGION_LOW, $t0);
        $this->assertTrue($res['success'], 'Złożenie wniosku powinno się udać: ' . ($res['message'] ?? ''));
        $this->assertSame('submitted', $res['code']);

        // Kasa pobrana natychmiast
        $cashAfter = $this->cashOf(self::PLAYER_A);
        $this->assertEqualsWithDelta(5_000_000 - 100_000, $cashAfter, 0.01);

        // Status: pending
        $this->assertSame('pending', $this->statusOf(self::PLAYER_A, self::REGION_LOW));

        // Mapa: zakup zablokowany (brak aktywnego zezwolenia)
        $map   = new WorldMap($this->db);
        $block = $map->regionPurchaseBlock(self::PLAYER_A, self::REGION_LOW);
        $this->assertIsArray($block);
        $this->assertTrue($block['no_permit']);

        // Tick za 31 minut (base_review_minutes = 30) — wniosek dojrzały
        $t1      = new DateTime('2026-06-01 12:31:00');
        $section = new LegalSection($this->db, $t1);
        $section->run();

        $this->assertSame(1, $section->decided);
        $this->assertSame(1, $section->notified);

        // Wynik: granted (delay=0, refusal=0, no_dec=0)
        $this->assertSame('granted', $this->statusOf(self::PLAYER_A, self::REGION_LOW));

        // Mapa: zakup odblokowany
        $this->assertNull($map->regionPurchaseBlock(self::PLAYER_A, self::REGION_LOW));

        // Drugi gracz nadal zablokowany (zezwolenie nie przenosi się między graczami)
        $this->assertIsArray($map->regionPurchaseBlock(self::PLAYER_B, self::REGION_LOW));
    }

    // =====================================================================
    // SCENARIUSZ 2: Opóźnienie → kolejny tick → granted
    // =====================================================================

    public function testDelayThenGrantedOnSecondTick(): void
    {
        // Region wymuszający 100% opóźnień (delay_risk_pct=100, no_dec=0, refusal=0)
        $t0  = new DateTime('2026-06-01 08:00:00');
        $res = $this->legal->submitApplication(self::PLAYER_A, self::REGION_HIGH, $t0);
        $this->assertTrue($res['success']);

        // Tick 1 — dojrzały wniosek: wynik = delayed (delay_risk=100)
        $t1      = new DateTime('2026-06-01 09:31:00'); // po base_review=90 min
        $section = new LegalSection($this->db, $t1);
        $section->run();

        $this->assertSame(1, $section->decided);
        $this->assertSame('delayed', $this->statusOf(self::PLAYER_A, self::REGION_HIGH));

        // delay_count = 1, nowy termin w przyszłości
        $app = $this->fetchApp(self::PLAYER_A, self::REGION_HIGH);
        $this->assertSame('1', (string)$app['delay_count']);
        $this->assertGreaterThan($t1->format('Y-m-d H:i:s'), $app['decision_due_at']);

        // Mapa nadal zablokowana
        $this->assertIsArray((new WorldMap($this->db))->regionPurchaseBlock(self::PLAYER_A, self::REGION_HIGH));

        // Tick 2 — zmiana konfiguracji na 0% opóźnień: wniosek powinien być granted
        $this->updateRegionRisks(self::REGION_HIGH, delay: 0, refusal: 0, noDec: 0);
        // Cofamy termin decyzji do przeszłości
        $this->db->prepare(
            "UPDATE drilling_permit_applications SET decision_due_at = ? WHERE player_id = ? AND region_id = ?"
        )->execute([$t1->format('Y-m-d H:i:s'), self::PLAYER_A, self::REGION_HIGH]);

        $t2      = new DateTime('2026-06-01 12:00:00');
        $section2 = new LegalSection($this->db, $t2);
        $section2->run();

        $this->assertSame(1, $section2->decided);
        $this->assertSame('granted', $this->statusOf(self::PLAYER_A, self::REGION_HIGH));
        $this->assertNull((new WorldMap($this->db))->regionPurchaseBlock(self::PLAYER_A, self::REGION_HIGH));
    }

    // =====================================================================
    // SCENARIUSZ 3: Odmowa → cooldown blokuje ponowny wniosek → cooldown mija → resubmit → granted
    // =====================================================================

    public function testRefusedCooldownThenResubmitGranted(): void
    {
        // Region: 100% odmów, cooldown 60 min
        $t0  = new DateTime('2026-06-01 10:00:00');
        $res = $this->legal->submitApplication(self::PLAYER_A, self::REGION_STRICT, $t0);
        $this->assertTrue($res['success']);

        // Tick po 31 min (base_review=30) — wynik: refused
        $t1      = new DateTime('2026-06-01 10:31:00');
        $section = new LegalSection($this->db, $t1);
        $section->run();

        $this->assertSame('refused', $this->statusOf(self::PLAYER_A, self::REGION_STRICT));

        // refusal_cooldown_until = t1 + 60 min
        $app = $this->fetchApp(self::PLAYER_A, self::REGION_STRICT);
        $this->assertNotNull($app['refusal_cooldown_until']);
        $this->assertGreaterThan($t1->format('Y-m-d H:i:s'), $app['refusal_cooldown_until']);

        // Próba złożenia w trakcie cooldownu — blokada
        $t_during = new DateTime('2026-06-01 11:00:00');
        $resBlock = $this->legal->submitApplication(self::PLAYER_A, self::REGION_STRICT, $t_during);
        $this->assertFalse($resBlock['success']);
        $this->assertSame('cooldown', $resBlock['code']);

        // Po cooldownie (t1 + 61 min) — resubmit dozwolony (0% odmowy tym razem)
        $this->updateRegionRisks(self::REGION_STRICT, delay: 0, refusal: 0, noDec: 0);
        $t_after = new DateTime('2026-06-01 11:32:00');
        $resOk   = $this->legal->submitApplication(self::PLAYER_A, self::REGION_STRICT, $t_after);
        $this->assertTrue($resOk['success'], 'Resubmit po cooldownie powinien się udać');
        $this->assertSame('pending', $this->statusOf(self::PLAYER_A, self::REGION_STRICT));

        // Tick — tym razem granted
        $t2 = new DateTime('2026-06-01 12:05:00');
        $this->db->prepare(
            "UPDATE drilling_permit_applications SET decision_due_at = ? WHERE player_id = ? AND region_id = ?"
        )->execute([$t_after->format('Y-m-d H:i:s'), self::PLAYER_A, self::REGION_STRICT]);

        $section2 = new LegalSection($this->db, $t2);
        $section2->run();

        $this->assertSame('granted', $this->statusOf(self::PLAYER_A, self::REGION_STRICT));
        $this->assertNull((new WorldMap($this->db))->regionPurchaseBlock(self::PLAYER_A, self::REGION_STRICT));
    }

    // =====================================================================
    // SCENARIUSZ 4: Brak decyzji → resubmit od razu → granted
    // =====================================================================

    public function testNoDecisionAllowsImmediateResubmit(): void
    {
        // Region: 100% no_decision
        $t0  = new DateTime('2026-06-02 09:00:00');
        $res = $this->legal->submitApplication(self::PLAYER_B, self::REGION_STRICT, $t0);
        $this->assertTrue($res['success']);

        // Tick — wynik: no_decision
        $this->updateRegionRisks(self::REGION_STRICT, delay: 0, refusal: 0, noDec: 100);
        $t1 = new DateTime('2026-06-02 09:31:00');
        $this->db->prepare(
            "UPDATE drilling_permit_applications SET decision_due_at = ? WHERE player_id = ? AND region_id = ?"
        )->execute([$t0->format('Y-m-d H:i:s'), self::PLAYER_B, self::REGION_STRICT]);

        $section = new LegalSection($this->db, $t1);
        $section->run();

        $this->assertSame('no_decision', $this->statusOf(self::PLAYER_B, self::REGION_STRICT));

        // no_decision traktowany jak in_progress — blokuje ponowny wniosek
        $resBlock = $this->legal->submitApplication(self::PLAYER_B, self::REGION_STRICT, $t1);
        $this->assertFalse($resBlock['success']);
        $this->assertSame('in_progress', $resBlock['code']);
    }

    // =====================================================================
    // SCENARIUSZ 5: Dwóch graczy, ten sam region — niezależne decyzje
    // =====================================================================

    public function testTwoPlayersInSameRegionIndependentDecisions(): void
    {
        $t0 = new DateTime('2026-06-01 14:00:00');

        $this->legal->submitApplication(self::PLAYER_A, self::REGION_LOW, $t0);
        $this->legal->submitApplication(self::PLAYER_B, self::REGION_LOW, $t0);

        $this->assertSame('pending', $this->statusOf(self::PLAYER_A, self::REGION_LOW));
        $this->assertSame('pending', $this->statusOf(self::PLAYER_B, self::REGION_LOW));

        // Tick rozpatruje oba jednocześnie — oba granted (risk=0)
        $t1      = new DateTime('2026-06-01 14:35:00');
        $section = new LegalSection($this->db, $t1);
        $section->run();

        $this->assertSame(2, $section->decided);
        $this->assertSame(2, $section->notified);

        $this->assertSame('granted', $this->statusOf(self::PLAYER_A, self::REGION_LOW));
        $this->assertSame('granted', $this->statusOf(self::PLAYER_B, self::REGION_LOW));

        $map = new WorldMap($this->db);
        $this->assertNull($map->regionPurchaseBlock(self::PLAYER_A, self::REGION_LOW));
        $this->assertNull($map->regionPurchaseBlock(self::PLAYER_B, self::REGION_LOW));
    }

    // =====================================================================
    // SCENARIUSZ 6: Tick nie rozpatruje wniosków przed terminem
    // =====================================================================

    public function testTickDoesNotProcessBeforeDeadline(): void
    {
        $t0  = new DateTime('2026-06-01 06:00:00');
        $res = $this->legal->submitApplication(self::PLAYER_A, self::REGION_LOW, $t0);
        $this->assertTrue($res['success']);

        // Tick tylko 10 min po złożeniu — za wcześnie (base_review=30 min)
        $t1      = new DateTime('2026-06-01 06:10:00');
        $section = new LegalSection($this->db, $t1);
        $section->run();

        $this->assertSame(0, $section->decided);
        $this->assertSame('pending', $this->statusOf(self::PLAYER_A, self::REGION_LOW));
        $this->assertIsArray((new WorldMap($this->db))->regionPurchaseBlock(self::PLAYER_A, self::REGION_LOW));
    }

    // =====================================================================
    // SCENARIUSZ 7: Migracja → status transitional → mapa od razu odblokowana
    // =====================================================================

    public function testMigratedTransitionalUnlocksMapImmediately(): void
    {
        // Gracz ma odwiert, ale nie ma żadnego zezwolenia
        $this->db->prepare(
            "INSERT INTO wells (player_id, region_id, status) VALUES (?, ?, 'active')"
        )->execute([self::PLAYER_B, self::REGION_LOW]);

        $migrated = $this->legal->migrateTransitionalPermits();
        $this->assertSame(1, $migrated);

        $status = $this->legal->getPermitStatus(self::PLAYER_B, self::REGION_LOW);
        $this->assertSame('transitional', $status['status']);
        $this->assertTrue($status['has_active']);

        // Mapa odblokowana bez składania wniosku
        $this->assertNull((new WorldMap($this->db))->regionPurchaseBlock(self::PLAYER_B, self::REGION_LOW));
    }

    // =====================================================================
    // SCENARIUSZ 8: Pełny cykl — złożenie, opóźnienie, potem granted, potem resubmit odrzucony
    // =====================================================================

    public function testAlreadyActiveBlocksResubmit(): void
    {
        $t0 = new DateTime('2026-06-03 07:00:00');
        $this->legal->submitApplication(self::PLAYER_A, self::REGION_LOW, $t0);

        // Tick — granted
        $t1 = new DateTime('2026-06-03 07:31:00');
        (new LegalSection($this->db, $t1))->run();
        $this->assertSame('granted', $this->statusOf(self::PLAYER_A, self::REGION_LOW));

        // Próba złożenia ponownego wniosku gdy zezwolenie aktywne
        $t2  = new DateTime('2026-06-03 08:00:00');
        $res = $this->legal->submitApplication(self::PLAYER_A, self::REGION_LOW, $t2);
        $this->assertFalse($res['success']);
        $this->assertSame('already_active', $res['code']);

        // Kasa bez zmian (nie pobrana drugi raz)
        $this->assertEqualsWithDelta(5_000_000 - 100_000, $this->cashOf(self::PLAYER_A), 0.01);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function statusOf(int $playerId, int $regionId): string
    {
        return $this->legal->getPermitStatus($playerId, $regionId)['status'];
    }

    /** @return array<string,mixed> */
    private function fetchApp(int $playerId, int $regionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM drilling_permit_applications WHERE player_id = ? AND region_id = ? LIMIT 1"
        );
        $stmt->execute([$playerId, $regionId]);
        $row = $stmt->fetch();
        $this->assertIsArray($row);
        return $row;
    }

    private function cashOf(int $playerId): float
    {
        return (float)$this->db->query(
            "SELECT cash FROM players WHERE id = {$playerId}"
        )->fetchColumn();
    }

    private function updateRegionRisks(int $regionId, float $delay, float $refusal, float $noDec): void
    {
        $this->db->prepare(
            "UPDATE legal_region_config
                SET delay_risk_pct = ?, refusal_risk_pct = ?, no_decision_risk_pct = ?
              WHERE region_id = ?"
        )->execute([$delay, $refusal, $noDec, $regionId]);
    }

    // ---------------------------------------------------------------- Schema

    private function createSchema(): void
    {
        $this->db->exec(
            "CREATE TABLE players (
                id INTEGER PRIMARY KEY,
                cash REAL NOT NULL DEFAULT 0
            )"
        );
        $this->db->exec(
            "CREATE TABLE wells (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                region_id INTEGER NULL,
                status TEXT NOT NULL DEFAULT 'active'
            )"
        );
        $this->db->exec(
            "CREATE TABLE world_regions (
                id INTEGER PRIMARY KEY,
                code TEXT NOT NULL,
                name TEXT NOT NULL,
                political_risk INTEGER NOT NULL DEFAULT 1
            )"
        );
        $this->db->exec(
            "CREATE TABLE legal_region_config (
                region_id INTEGER PRIMARY KEY,
                enabled INTEGER NOT NULL DEFAULT 1,
                is_offshore INTEGER NOT NULL DEFAULT 0,
                risk_level TEXT NOT NULL DEFAULT 'low',
                application_cost REAL NOT NULL DEFAULT 100000,
                base_review_minutes INTEGER NOT NULL DEFAULT 30,
                delay_risk_pct REAL NOT NULL DEFAULT 0,
                delay_min_minutes INTEGER NOT NULL DEFAULT 10,
                delay_max_minutes INTEGER NOT NULL DEFAULT 10,
                no_decision_risk_pct REAL NOT NULL DEFAULT 0,
                refusal_risk_pct REAL NOT NULL DEFAULT 0,
                refusal_cooldown_minutes INTEGER NOT NULL DEFAULT 60,
                required_capital REAL NOT NULL DEFAULT 0,
                required_legal_level INTEGER NOT NULL DEFAULT 0
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
                delay_count INTEGER NOT NULL DEFAULT 0,
                source TEXT DEFAULT 'player',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (player_id, region_id)
            )"
        );
        $this->db->exec(
            "CREATE TABLE director_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                type TEXT NOT NULL DEFAULT 'info',
                priority TEXT NOT NULL DEFAULT 'medium',
                title TEXT NOT NULL DEFAULT '',
                message TEXT NOT NULL DEFAULT '',
                icon TEXT NOT NULL DEFAULT '',
                requires_action INTEGER NOT NULL DEFAULT 0,
                action_url TEXT NULL,
                action_label TEXT NULL,
                expires_at TEXT NULL,
                is_read INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    private function seedBaseData(): void
    {
        // Gracze ze środkami
        $this->db->prepare("INSERT INTO players (id, cash) VALUES (?, ?)")
            ->execute([self::PLAYER_A, 5_000_000.0]);
        $this->db->prepare("INSERT INTO players (id, cash) VALUES (?, ?)")
            ->execute([self::PLAYER_B, 5_000_000.0]);

        // Regiony na mapie
        $this->db->prepare("INSERT INTO world_regions (id, code, name, political_risk) VALUES (?,?,?,?)")
            ->execute([self::REGION_LOW,    'lr', 'Region Niski',    1]);
        $this->db->prepare("INSERT INTO world_regions (id, code, name, political_risk) VALUES (?,?,?,?)")
            ->execute([self::REGION_HIGH,   'hr', 'Region Wysoki',   3]);
        $this->db->prepare("INSERT INTO world_regions (id, code, name, political_risk) VALUES (?,?,?,?)")
            ->execute([self::REGION_STRICT, 'sr', 'Region Surowy',   4]);

        // Konfiguracja regionów — wszystkie ryzyka = 0 (deterministyczny wynik: granted)
        // REGION_LOW: tani, szybki (30 min), zero ryzyk
        $this->db->prepare(
            "INSERT INTO legal_region_config
                (region_id, risk_level, application_cost, base_review_minutes,
                 delay_risk_pct, delay_min_minutes, delay_max_minutes,
                 no_decision_risk_pct, refusal_risk_pct, refusal_cooldown_minutes, required_capital)
             VALUES (?, 'low', 100000, 30, 0, 10, 10, 0, 0, 60, 0)"
        )->execute([self::REGION_LOW]);

        // REGION_HIGH: droższy, 90 min, delay=100 (testy opóźnienia)
        $this->db->prepare(
            "INSERT INTO legal_region_config
                (region_id, risk_level, application_cost, base_review_minutes,
                 delay_risk_pct, delay_min_minutes, delay_max_minutes,
                 no_decision_risk_pct, refusal_risk_pct, refusal_cooldown_minutes, required_capital)
             VALUES (?, 'high', 500000, 90, 100, 15, 15, 0, 0, 240, 0)"
        )->execute([self::REGION_HIGH]);

        // REGION_STRICT: refusal=100 (domyślnie), cooldown=60 min
        $this->db->prepare(
            "INSERT INTO legal_region_config
                (region_id, risk_level, application_cost, base_review_minutes,
                 delay_risk_pct, delay_min_minutes, delay_max_minutes,
                 no_decision_risk_pct, refusal_risk_pct, refusal_cooldown_minutes, required_capital)
             VALUES (?, 'critical', 1000000, 30, 0, 10, 10, 0, 100, 60, 0)"
        )->execute([self::REGION_STRICT]);
    }
}
