<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/BriberyService.php';

/**
 * Testy uniwersalnego silnika lapowek (BriberyService).
 * Tests for the universal bribery engine (BriberyService).
 *
 * Wynik (sukces/wpadka) jest deterministyczny: ustawiamy catch_pct=0 (zawsze
 * sukces) lub catch_pct=100 (zawsze wpadka) dla poziomu reputacji gracza.
 * The outcome is deterministic: we set catch_pct=0 (always success) or
 * catch_pct=100 (always caught) for the player's reputation level.
 */
final class BriberyServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
    }

    public function testQuoteAppliesBaseFractionAndPriceMultiplier(): void
    {
        $this->seedPlayer(1, 100000.00, 0.0, 50); // 50 -> poziom 'shaky'
        $this->setConfig(['base_cost_pct' => '50', 'price_mult_shaky' => '1.4']);

        $quote = (new BriberyService($this->db))->quote(1, 10000.00);

        // 10000 * 0.50 * 1.4 = 7000
        $this->assertTrue($quote['enabled']);
        $this->assertSame(7000, $quote['cost']);
        $this->assertSame('shaky', $quote['level']);
    }

    public function testSuccessChargesCashRunsOnSuccessAndLightPenalty(): void
    {
        $this->seedPlayer(1, 100000.00, 0.0, 50);
        $this->setConfig([
            'base_cost_pct'               => '50',
            'price_mult_shaky'            => '1.0',
            'catch_pct_shaky'             => '0',   // zawsze sukces
            'credibility_penalty_success' => '4',
        ]);

        $granted = false;
        $caughtRan = false;
        $res = (new BriberyService($this->db))->attempt(
            1, 'legal_permit', 10000.00,
            function () use (&$granted) { $granted = true; },
            ['on_caught' => function () use (&$caughtRan) { $caughtRan = true; }]
        );

        $this->assertTrue($res['success']);
        $this->assertSame('success', $res['outcome']);
        $this->assertSame(5000, $res['cost']);           // 10000 * 0.5 * 1.0
        $this->assertTrue($granted, 'onSuccess wykonane.');
        $this->assertFalse($caughtRan, 'onCaught NIE wykonane przy sukcesie.');
        $this->assertSame(95000.00, $this->cashOf(1), 'Koszt schodzi z gotowki.');
        $this->assertSame(46, $this->credibilityOf(1), 'Lekka kara reputacji przy sukcesie.');

        $tx = $this->lastTransaction();
        $this->assertSame('bribe', $tx['transaction_type']);
        $this->assertSame(1, (int)$tx['from_player_id']);
    }

    public function testCaughtChargesCashRunsOnCaughtHeavyPenaltyAndNotifies(): void
    {
        $this->seedPlayer(1, 100000.00, 0.0, 50);
        $this->setConfig([
            'base_cost_pct'              => '50',
            'price_mult_shaky'           => '1.0',
            'catch_pct_shaky'            => '100', // zawsze wpadka
            'credibility_penalty_caught' => '15',
        ]);

        $granted = false;
        $caughtRan = false;
        $res = (new BriberyService($this->db))->attempt(
            1, 'legal_permit', 10000.00,
            function () use (&$granted) { $granted = true; },
            [
                'on_caught' => function () use (&$caughtRan) { $caughtRan = true; },
                'meta' => ['label' => 'Region testowy', 'notif_type' => 'legal'],
            ]
        );

        $this->assertFalse($res['success']);
        $this->assertSame('caught', $res['outcome']);
        $this->assertTrue($res['caught']);
        $this->assertFalse($granted, 'onSuccess NIE wykonane przy wpadce.');
        $this->assertTrue($caughtRan, 'onCaught wykonane przy wpadce.');
        $this->assertSame(95000.00, $this->cashOf(1), 'Gotowka i tak przepada.');
        $this->assertSame(35, $this->credibilityOf(1), 'Mocna kara reputacji przy wpadce.');

        $notif = $this->db->query("SELECT COUNT(*) FROM director_notifications WHERE player_id = 1")->fetchColumn();
        $this->assertSame(1, (int)$notif, 'Powiadomienie dyrektora o wpadce.');

        $incident = $this->db->query("SELECT COUNT(*) FROM company_credibility_log WHERE event_key = 'bribe_caught'")->fetchColumn();
        $this->assertSame(1, (int)$incident, 'Incydent w historii reputacji.');
    }

    public function testNoFundsBlocksAttemptAndChangesNothing(): void
    {
        $this->seedPlayer(1, 1000.00, 0.0, 50);
        $this->setConfig(['base_cost_pct' => '50', 'price_mult_shaky' => '1.0', 'catch_pct_shaky' => '0']);

        $granted = false;
        $res = (new BriberyService($this->db))->attempt(
            1, 'legal_permit', 10000.00, // cost = 5000 > 1000
            function () use (&$granted) { $granted = true; }
        );

        $this->assertFalse($res['success']);
        $this->assertSame('no_funds', $res['outcome']);
        $this->assertFalse($granted, 'onSuccess nie wykonane bez srodkow.');
        $this->assertSame(1000.00, $this->cashOf(1), 'Saldo bez zmian.');
        $this->assertSame(50, $this->credibilityOf(1), 'Reputacja bez zmian.');
        $this->assertCount(0, $this->allTransactions(), 'Brak wpisu transakcji.');
    }

    public function testDisabledModuleRejectsAttempt(): void
    {
        $this->seedPlayer(1, 100000.00, 0.0, 50);
        $this->setConfig(['enabled' => '0']);

        $res = (new BriberyService($this->db))->attempt(
            1, 'legal_permit', 10000.00, function () {}
        );

        $this->assertFalse($res['success']);
        $this->assertSame('disabled', $res['outcome']);
        $this->assertSame(100000.00, $this->cashOf(1));
    }

    // ------------------------------------------------------------- helpers

    /** @param array<string,string|int|float> $values */
    private function setConfig(array $values): void
    {
        (new BriberyConfig($this->db))->save($values);
    }

    private function seedPlayer(int $id, float $cash, float $bank, int $credibility): void
    {
        $this->db->prepare("INSERT INTO players (id, cash, bank_balance, company_credibility) VALUES (?, ?, ?, ?)")
                 ->execute([$id, $cash, $bank, $credibility]);
    }

    private function cashOf(int $id): float
    {
        $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
        $stmt->execute([$id]);
        return (float)$stmt->fetchColumn();
    }

    private function credibilityOf(int $id): int
    {
        $stmt = $this->db->prepare("SELECT company_credibility FROM players WHERE id = ?");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }

    /** @return array<string,mixed> */
    private function lastTransaction(): array
    {
        $row = $this->db->query("SELECT * FROM bank_transactions ORDER BY id DESC LIMIT 1")->fetch();
        return $row ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    private function allTransactions(): array
    {
        return $this->db->query("SELECT * FROM bank_transactions ORDER BY id ASC")->fetchAll();
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
    }
}
