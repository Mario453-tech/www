<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/ProtectionService.php';

/**
 * Testy uniwersalnego silnika ochrony (ProtectionService).
 * Tests for the universal protection engine (ProtectionService).
 */
final class ProtectionServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
    }

    public function testQuoteComputesCostPerCostType(): void
    {
        $this->seedPlayer(1, 1000000.00);
        $svc = new ProtectionService($this->db);

        // fixed: 75000 niezaleznie od referencji
        $q = $svc->quote(1, 'basic_escort', 200000.00);
        $this->assertTrue($q['success']);
        $this->assertSame(75000.00, $q['cost']);

        // fixed: 500000 niezaleznie od referencji
        $q = $svc->quote(1, 'armed_convoy', 200000.00);
        $this->assertSame(500000.00, $q['cost']);

        // per_hour: 50000 * (120 min / 60) = 100000
        $q = $svc->quote(1, 'drone_patrol', 0.0);
        $this->assertSame(100000.00, $q['cost']);

        // percent_reference: 5% z 200000 = 10000
        $this->db->exec("UPDATE protection_options SET cost_type = 'percent_reference', cost_value = 5 WHERE code = 'basic_escort'");
        $q = $svc->quote(1, 'basic_escort', 200000.00);
        $this->assertSame(10000.00, $q['cost']);
    }

    public function testActivateChargesCashAndStoresActiveProtection(): void
    {
        $this->seedPlayer(1, 100000.00);
        $svc = new ProtectionService($this->db);

        $res = $svc->activate(1, 'basic_escort', 'road_transport', 7, 200000.00);

        $this->assertTrue($res['success'], $res['message'] ?? '');
        $this->assertSame('success', $res['outcome']);
        $this->assertSame(75000.00, $res['cost']);
        $this->assertSame(25000.00, $this->cashOf(1), 'Koszt schodzi z gotowki.');

        $tx = $this->db->query("SELECT * FROM bank_transactions ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertSame('protection', $tx['transaction_type']);

        $active = $this->db->query("SELECT * FROM active_protections WHERE player_id = 1")->fetch();
        $this->assertSame('active', $active['status']);
        $this->assertSame(7, (int)$active['target_id']);
        $this->assertSame('road_transport_guard', $active['context']);

        $log = $this->db->query("SELECT * FROM protection_logs WHERE event_key = 'protection_activated'")->fetch();
        $this->assertNotFalse($log, 'Wpis protection_activated w historii.');

        $notif = $this->db->query("SELECT COUNT(*) FROM director_notifications WHERE player_id = 1")->fetchColumn();
        $this->assertSame(1, (int)$notif, 'Powiadomienie dyrektora o aktywacji.');
    }

    public function testActivateNoFundsChangesNothing(): void
    {
        $this->seedPlayer(1, 5000.00);
        $svc = new ProtectionService($this->db);

        $res = $svc->activate(1, 'basic_escort', 'road_transport', 7, 200000.00); // koszt 75000 > 5000

        $this->assertFalse($res['success']);
        $this->assertSame('no_funds', $res['outcome']);
        $this->assertSame(5000.00, $this->cashOf(1), 'Saldo bez zmian.');
        $count = $this->db->query("SELECT COUNT(*) FROM active_protections")->fetchColumn();
        $this->assertSame(0, (int)$count, 'Brak aktywnej ochrony.');
    }

    public function testActivateBlocksSecondProtectionOnSameTarget(): void
    {
        $this->seedPlayer(1, 1000000.00);
        $svc = new ProtectionService($this->db);

        $first = $svc->activate(1, 'basic_escort', 'road_transport', 7, 200000.00);
        $this->assertTrue($first['success']);

        $second = $svc->activate(1, 'drone_patrol', 'road_transport', 7, 0.0);
        $this->assertFalse($second['success']);
        $this->assertSame('already_active', $second['outcome']);

        // Inny cel (inny odwiert) - dozwolone / Another target (another well) - allowed
        $other = $svc->activate(1, 'drone_patrol', 'road_transport', 8, 0.0);
        $this->assertTrue($other['success']);
    }

    public function testActivateRejectsWrongExpectedContext(): void
    {
        $this->seedPlayer(1, 1000000.00);
        $svc = new ProtectionService($this->db);
        $this->db->exec("UPDATE protection_options SET context = 'other_context' WHERE code = 'basic_escort'");

        $res = $svc->activate(1, 'basic_escort', 'road_transport', 7, 200000.00, [], 'road_transport_guard');

        $this->assertFalse($res['success']);
        $this->assertSame('context_mismatch', $res['outcome']);
        $this->assertSame(1000000.00, $this->cashOf(1));
        $count = $this->db->query("SELECT COUNT(*) FROM active_protections")->fetchColumn();
        $this->assertSame(0, (int)$count);
    }

    public function testSchemaBlocksDuplicateActiveProtectionRows(): void
    {
        $this->seedPlayer(1, 1000000.00);
        $svc = new ProtectionService($this->db);
        $first = $svc->activate(1, 'basic_escort', 'road_transport', 7, 200000.00);
        $this->assertTrue($first['success']);

        $optionId = (int)$this->db->query("SELECT id FROM protection_options WHERE code = 'drone_patrol'")->fetchColumn();

        $this->expectException(PDOException::class);
        $this->db->prepare(
            "INSERT INTO active_protections
                (player_id, protection_option_id, target_type, target_id, context,
                 paid_from, cost, starts_at, ends_at, status, created_at, updated_at)
             VALUES (1, ?, 'road_transport', 7, 'road_transport_guard',
                     'cash', 1000, datetime('now'), datetime('now', '+1 hour'), 'active',
                     datetime('now'), datetime('now'))"
        )->execute([$optionId]);
    }

    public function testActivateRequiresLegalLevel(): void
    {
        // Gracz bez dyrektora prawnego ma poziom 0, armed_convoy wymaga 3.
        // A player without a legal director has level 0, armed_convoy requires 3.
        $this->seedPlayer(1, 1000000.00);
        $svc = new ProtectionService($this->db);

        $res = $svc->activate(1, 'armed_convoy', 'road_transport', 7, 0.0);

        $this->assertFalse($res['success']);
        $this->assertSame('requirements_not_met', $res['outcome']);
        $this->assertSame(1000000.00, $this->cashOf(1));
    }

    public function testActivateRequiresCredibility(): void
    {
        $this->seedPlayer(1, 1000000.00, 10);
        $svc = new ProtectionService($this->db);
        $this->db->exec("UPDATE protection_options SET min_company_credibility = 40 WHERE code = 'basic_escort'");

        $res = $svc->activate(1, 'basic_escort', 'road_transport', 7, 200000.00);

        $this->assertFalse($res['success']);
        $this->assertSame('requirements_not_met', $res['outcome']);
    }

    public function testGetActiveEffectsReturnsClampedMultipliers(): void
    {
        $this->seedPlayer(1, 1000000.00);
        $svc = new ProtectionService($this->db);
        // Obnizamy wymog, by aktywacja przeszla / Lower the requirement so activation passes
        $this->db->exec("UPDATE protection_options SET min_legal_level = 0 WHERE code = 'armed_convoy'");
        $res = $svc->activate(1, 'armed_convoy', 'road_transport', 7, 0.0);
        $this->assertTrue($res['success'], $res['message'] ?? '');

        $effects = $svc->getActiveEffects(1, 'road_transport', 7, 'road_transport_guard');

        $this->assertSame(0.55, $effects['theft_risk_mult']['value']);
        $this->assertSame(0.60, $effects['raid_risk_mult']['value']);
        $this->assertSame(0.85, $effects['sabotage_risk_mult']['value']);
        $this->assertSame('mult', $effects['theft_risk_mult']['type']);

        // Mnoznik 0 w bazie nie zeruje ryzyka - przyciety do 0.05
        // A 0 multiplier in the DB never zeroes the risk - clamped to 0.05
        $this->db->exec("UPDATE protection_effects SET effect_value = 0 WHERE effect_key = 'theft_risk_mult'");
        $effects = $svc->getActiveEffects(1, 'road_transport', 7, 'road_transport_guard');
        $this->assertSame(0.05, $effects['theft_risk_mult']['value']);
    }

    public function testLazyExpiryMarksOverdueProtections(): void
    {
        $this->seedPlayer(1, 1000000.00);
        $svc = new ProtectionService($this->db);
        $svc->activate(1, 'basic_escort', 'road_transport', 7, 200000.00);

        $past = date('Y-m-d H:i:s', time() - 60);
        $this->db->exec("UPDATE active_protections SET ends_at = '{$past}' WHERE player_id = 1");

        // Nowa instancja = nowe zadanie (wygaszanie raz na instancje serwisu).
        // New instance = new request (expiry runs once per service instance).
        $svc = new ProtectionService($this->db);
        $effects = $svc->getActiveEffects(1, 'road_transport', 7, 'road_transport_guard');
        $this->assertSame([], $effects, 'Przeterminowana ochrona nie daje efektow.');

        $status = $this->db->query("SELECT status FROM active_protections WHERE player_id = 1")->fetchColumn();
        $this->assertSame('expired', $status);

        $log = $this->db->query("SELECT COUNT(*) FROM protection_logs WHERE event_key = 'protection_expired'")->fetchColumn();
        $this->assertSame(1, (int)$log, 'Wpis protection_expired w historii.');
    }

    public function testApplyEffectsMultDeltaAndUnknownKeys(): void
    {
        $svc = new ProtectionService($this->db);

        $base = ['theft_risk_mult' => 1.0, 'raid_risk_mult' => 0.5, 'untouched' => 2.0];
        $effects = [
            'theft_risk_mult' => ['type' => 'mult', 'value' => 0.55],
            'raid_risk_mult'  => ['type' => 'delta', 'value' => 0.1],
            'unknown_key'     => ['type' => 'mult', 'value' => 0.1],
        ];

        $out = $svc->applyEffects($base, $effects);

        $this->assertSame(0.55, $out['theft_risk_mult']);
        $this->assertSame(0.6, $out['raid_risk_mult']);
        $this->assertSame(2.0, $out['untouched'], 'Klucz bez efektu bez zmian.');
        $this->assertArrayNotHasKey('unknown_key', $out, 'Nieznany klucz efektu ignorowany.');
    }

    public function testGetAvailableOptionsMarksLockedAndAffordable(): void
    {
        $this->seedPlayer(1, 80000.00);
        $svc = new ProtectionService($this->db);

        $options = $svc->getAvailableOptions(1, 'road_transport', 'road_transport_guard', 200000.00);

        $byCode = [];
        foreach ($options as $opt) {
            $byCode[$opt['code']] = $opt;
        }

        $this->assertNull($byCode['basic_escort']['locked_reason']);
        $this->assertTrue($byCode['basic_escort']['affordable']); // 75000 <= 80000
        $this->assertSame('legal_level', $byCode['armed_convoy']['locked_reason']);
        $this->assertFalse($byCode['armed_convoy']['affordable']); // 500000 > 80000
        $this->assertFalse($byCode['drone_patrol']['affordable']); // 100000 > 80000
    }

    public function testCancelStopsEffectsAndLogs(): void
    {
        $this->seedPlayer(1, 1000000.00);
        $svc = new ProtectionService($this->db);
        $svc->activate(1, 'basic_escort', 'road_transport', 7, 200000.00);
        $activeId = (int)$this->db->query("SELECT id FROM active_protections WHERE player_id = 1")->fetchColumn();

        $res = $svc->cancel($activeId);

        $this->assertTrue($res['success']);
        $this->assertSame([], $svc->getActiveEffects(1, 'road_transport', 7, 'road_transport_guard'));
        $log = $this->db->query("SELECT COUNT(*) FROM protection_logs WHERE event_key = 'protection_cancelled'")->fetchColumn();
        $this->assertSame(1, (int)$log);
    }

    // ------------------------------------------------------------- helpers

    private function seedPlayer(int $id, float $cash, int $credibility = 50): void
    {
        $this->db->prepare("INSERT INTO players (id, cash, bank_balance, company_credibility) VALUES (?, ?, 0, ?)")
                 ->execute([$id, $cash, $credibility]);
    }

    private function cashOf(int $id): float
    {
        $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
        $stmt->execute([$id]);
        return (float)$stmt->fetchColumn();
    }

    private function createSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE players (
                id INTEGER PRIMARY KEY,
                cash REAL NOT NULL DEFAULT 0,
                bank_balance REAL NOT NULL DEFAULT 0,
                company_credibility INTEGER NOT NULL DEFAULT 50
            )'
        );
        $this->db->exec(
            'CREATE TABLE bank_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                from_player_id INTEGER NULL,
                to_player_id INTEGER NULL,
                amount REAL NOT NULL,
                transaction_type TEXT NOT NULL,
                description TEXT NULL,
                reference_type TEXT NULL,
                reference_id INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->db->exec(
            'CREATE TABLE director_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                priority TEXT NOT NULL,
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                icon TEXT NULL,
                requires_action INTEGER NOT NULL DEFAULT 0,
                action_url TEXT NULL,
                action_label TEXT NULL,
                expires_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->db->exec(
            'CREATE TABLE company_credibility_log (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                event_key TEXT NOT NULL,
                delta INTEGER NOT NULL,
                score_before INTEGER NOT NULL,
                score_after INTEGER NOT NULL,
                note TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }
}
