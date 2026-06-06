<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/CompanyCredibilityService.php';

/**
 * Brief §12: wiarygodnosc firmy — testy fundamentu.
 * Brief §12: company credibility — foundation tests.
 *
 * Pokrywa: zakres wyniku (0-100), logowanie zmian, zmiana pozytywna/negatywna,
 * progi poziomow, prog powiadomienia (|delta| >= 5) oraz guard braku tabeli.
 * Covers: score range, change logging, positive/negative change, level thresholds,
 * notification threshold and the missing-table guard.
 */
final class CompanyCredibilityServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private CompanyCredibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->service = new CompanyCredibilityService($this->db);
    }

    // ----------------------------------------------------- 12.1 Zakres wyniku

    public function testScoreNeverExceedsMax(): void
    {
        $this->seedPlayer(1, 95);
        $after = $this->service->changeScore(1, 20, 'admin_manual_adjustment', 'test');
        $this->assertSame(100, $after, 'Wynik nie moze przekroczyc 100.');
        $this->assertSame(100, $this->scoreOf(1));
    }

    public function testScoreNeverDropsBelowZero(): void
    {
        $this->seedPlayer(1, 10);
        $after = $this->service->changeScore(1, -50, 'bankruptcy_entered', 'test');
        $this->assertSame(0, $after, 'Wynik nie moze spasc ponizej 0.');
        $this->assertSame(0, $this->scoreOf(1));
    }

    public function testDefaultScoreForMissingPlayer(): void
    {
        $this->assertSame(50, $this->service->getScore(999));
    }

    // ---------------------------------------------------- 12.2 Logowanie zmian

    public function testEveryChangeWritesLog(): void
    {
        $this->seedPlayer(1, 50);
        $this->service->changeScore(1, -12, 'black_market_detected', 'wykrycie');

        $rows = $this->logOf(1);
        $this->assertCount(1, $rows, 'Po kazdej zmianie powstaje wpis w logu.');
        $this->assertSame('black_market_detected', $rows[0]['event_key']);
        $this->assertSame(-12, (int)$rows[0]['delta']);
        $this->assertSame(50, (int)$rows[0]['score_before']);
        $this->assertSame(38, (int)$rows[0]['score_after']);
        $this->assertSame('wykrycie', $rows[0]['note']);
    }

    public function testLogStoresEffectiveDeltaWhenClamped(): void
    {
        // Sufit: delta efektywna mniejsza niz nominalna / Ceiling: effective delta < nominal
        $this->seedPlayer(1, 95);
        $this->service->changeScore(1, 20, 'admin_manual_adjustment', 'cap');

        $rows = $this->logOf(1);
        $this->assertSame(5, (int)$rows[0]['delta'], 'Log zapisuje delte efektywna (95 -> 100 = +5).');
    }

    // -------------------------------------------------- 12.3 Zmiana pozytywna

    public function testPositiveChangeRaisesScore(): void
    {
        $this->seedPlayer(1, 50);
        $this->service->applyEvent(1, 'loan_fully_repaid');
        $this->assertSame(58, $this->scoreOf(1), 'Pelna splata kredytu (+8): 50 -> 58.');
        $this->assertCount(1, $this->logOf(1));
    }

    // -------------------------------------------------- 12.4 Zmiana negatywna

    public function testNegativeChangeLowersScore(): void
    {
        $this->seedPlayer(1, 50);
        $this->service->applyEvent(1, 'black_market_detected');
        $this->assertSame(38, $this->scoreOf(1), 'Wykrycie czarnego rynku (-12): 50 -> 38.');
        $this->assertCount(1, $this->logOf(1));
    }

    // ------------------------------------------------------------- Poziomy

    /** @dataProvider levelProvider */
    public function testGetLevelThresholds(int $score, string $expected): void
    {
        $this->assertSame($expected, $this->service->getLevel($score));
    }

    /** @return array<string,array{0:int,1:string}> */
    public static function levelProvider(): array
    {
        return [
            'min critical'  => [0, 'critical'],
            'max critical'  => [19, 'critical'],
            'min low'       => [20, 'low'],
            'max low'       => [39, 'low'],
            'min shaky'     => [40, 'shaky'],
            'max shaky'     => [59, 'shaky'],
            'min stable'    => [60, 'stable'],
            'max stable'    => [79, 'stable'],
            'min high'      => [80, 'high'],
            'max high'      => [100, 'high'],
        ];
    }

    // ---------------------------------------------------- Prog powiadomienia

    public function testLargeChangeCreatesNotification(): void
    {
        $this->seedPlayer(1, 50);
        $this->service->applyEvent(1, 'bailiff_activated'); // -20

        $notifs = $this->notificationsOf(1);
        $this->assertCount(1, $notifs, 'Zmiana |delta| >= 5 tworzy powiadomienie.');
        $this->assertSame('credibility', $notifs[0]['type']);
        $this->assertSame('high', $notifs[0]['priority'], 'Duzy spadek (<= -15) ma priorytet high.');
    }

    public function testSmallChangeDoesNotNotify(): void
    {
        $this->seedPlayer(1, 50);
        $this->service->applyEvent(1, 'loan_installment_paid_on_time'); // +2

        $this->assertSame(52, $this->scoreOf(1));
        $this->assertCount(0, $this->notificationsOf(1), 'Mala zmiana (|delta| < 5) nie tworzy powiadomienia.');
        $this->assertCount(1, $this->logOf(1), 'Ale log powstaje zawsze.');
    }

    public function testCleanOperationBonusAppliesOncePerPeriod(): void
    {
        $this->seedPlayer(1, 50);
        $now = new DateTime('2026-06-05 12:00:00');

        $this->assertTrue($this->service->applyCleanOperationBonus(1, 7, $now));
        $this->assertSame(53, $this->scoreOf(1));

        $this->assertFalse($this->service->applyCleanOperationBonus(1, 7, $now));
        $this->assertSame(53, $this->scoreOf(1));
        $this->assertCount(1, $this->logOf(1));
    }

    public function testCleanOperationBonusBlockedByRecentNegativeEvent(): void
    {
        $this->seedPlayer(1, 50);
        $this->insertLog(1, 'black_market_detected', -12, '2026-06-03 12:00:00');

        $this->assertFalse($this->service->applyCleanOperationBonus(1, 7, new DateTime('2026-06-05 12:00:00')));
        $this->assertSame(50, $this->scoreOf(1));
    }

    // ----------------------------------------------------------------- Guard

    public function testChangeSucceedsWhenNotificationsTableMissing(): void
    {
        $this->db->exec('DROP TABLE director_notifications');
        $this->seedPlayer(1, 50);

        $after = $this->service->changeScore(1, -20, 'bailiff_activated', 'test');
        $this->assertSame(30, $after, 'Zmiana udaje sie mimo braku tabeli powiadomien.');
        $this->assertCount(1, $this->logOf(1));
    }

    public function testUnknownEventIsIgnored(): void
    {
        $this->seedPlayer(1, 50);
        $this->service->applyEvent(1, 'totally_unknown_event');
        $this->assertSame(50, $this->scoreOf(1), 'Nieznane zdarzenie nie zmienia wyniku.');
        $this->assertCount(0, $this->logOf(1));
    }

    // --------------------------------------------------------------- Helpers

    private function scoreOf(int $playerId): int
    {
        return (int)$this->db->query("SELECT company_credibility FROM players WHERE id = {$playerId}")->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    private function logOf(int $playerId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM company_credibility_log WHERE player_id = ? ORDER BY id ASC");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    private function notificationsOf(int $playerId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM director_notifications WHERE player_id = ? ORDER BY id ASC");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }

    private function seedPlayer(int $id, int $score): void
    {
        $this->db->prepare("INSERT INTO players (id, company_credibility) VALUES (?, ?)")->execute([$id, $score]);
    }

    private function insertLog(int $playerId, string $eventKey, int $delta, string $createdAt): void
    {
        $this->db->prepare(
            "INSERT INTO company_credibility_log
                (player_id, event_key, delta, score_before, score_after, note, created_at)
             VALUES (?, ?, ?, 50, 50, 'test', ?)"
        )->execute([$playerId, $eventKey, $delta, $createdAt]);
    }

    private function createSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE players (
                id INTEGER PRIMARY KEY,
                company_credibility INTEGER NOT NULL DEFAULT 50
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
        // CHECK odzwierciedla produkcyjny ENUM director_notifications.type (wraz z 'credibility').
        // Chroni przed regresem: wstawienie typu spoza listy wywali test, tak jak strict MySQL.
        // CHECK mirrors the production director_notifications.type ENUM (incl. 'credibility').
        // Guards against regressions: inserting an out-of-list type fails the test, like strict MySQL.
        $this->db->exec(
            "CREATE TABLE director_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                type TEXT NOT NULL CHECK (type IN
                    ('bank','hr','technical','market','legal','urgent','info','credibility')),
                priority TEXT NOT NULL DEFAULT 'low',
                title TEXT NOT NULL,
                message TEXT NOT NULL,
                icon TEXT NULL,
                requires_action INTEGER NOT NULL DEFAULT 0,
                action_url TEXT NULL,
                action_label TEXT NULL,
                expires_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }
}
