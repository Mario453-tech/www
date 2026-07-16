<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/PlayersSection.php';

final class PlayersSectionLimitTest extends BaseTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec("
            CREATE TABLE players (
                id INTEGER PRIMARY KEY,
                status TEXT NOT NULL DEFAULT 'active',
                last_tick_at TEXT NULL,
                cash REAL NOT NULL DEFAULT 0,
                financial_state TEXT NULL,
                crisis_ticks INTEGER NULL,
                last_crisis_tick_at TEXT NULL,
                credit_score INTEGER NULL,
                bankruptcy_status TEXT NULL,
                last_active_at TEXT NULL,
                offline_mode INTEGER NULL,
                offline_since TEXT NULL
            )
        ");
        $this->db->exec("
            CREATE TABLE storage (
                player_id INTEGER PRIMARY KEY,
                capacity REAL NOT NULL DEFAULT 10000,
                used REAL NOT NULL DEFAULT 0
            )
        ");
    }

    public function testPlayersSectionFetchesOldestActivePlayersWithinLimit(): void
    {
        $this->insertPlayer(1, '2026-07-16 10:00:00');
        $this->insertPlayer(2, '2026-07-16 08:00:00');
        $this->insertPlayer(3, '2026-07-16 09:00:00');
        $this->insertPlayer(4, '2026-07-16 07:00:00', 'bankrupt');
        $this->insertPlayer(5, null);

        $section = new PlayersSection(
            $this->db,
            new DateTime('2026-07-16 11:00:00'),
            100.0,
            [],
            2
        );

        $rows = $this->invokeFetchActivePlayers($section);

        $this->assertSame([5, 2], array_map(static fn(array $row): int => (int)$row['id'], $rows));
        $this->assertSame(4, $this->invokeCountActivePlayers($section));
    }

    public function testPlayersWithoutStorageAreExcludedWithoutAdvancingTheirTick(): void
    {
        $this->insertPlayer(1, '2026-07-16 08:00:00', 'active', false);
        $this->insertPlayer(2, '2026-07-16 09:00:00');

        $section = new PlayersSection(
            $this->db,
            new DateTime('2026-07-16 11:00:00'),
            100.0,
            [],
            10
        );

        $rows = $this->invokeFetchActivePlayers($section);

        $this->assertSame([2], array_map(static fn(array $row): int => (int)$row['id'], $rows));
        $this->assertSame(1, $this->invokeCountPlayersMissingStorage($section));
        $this->assertSame(
            '2026-07-16 08:00:00',
            $this->db->query('SELECT last_tick_at FROM players WHERE id = 1')->fetchColumn()
        );
    }

    public function testPlayersSectionLimitIsClampedToAtLeastOne(): void
    {
        $this->insertPlayer(1, '2026-07-16 10:00:00');
        $this->insertPlayer(2, '2026-07-16 08:00:00');

        $section = new PlayersSection(
            $this->db,
            new DateTime('2026-07-16 11:00:00'),
            100.0,
            [],
            0
        );

        $rows = $this->invokeFetchActivePlayers($section);

        $this->assertCount(1, $rows);
        $this->assertSame(2, (int)$rows[0]['id']);
    }

    private function insertPlayer(
        int $id,
        ?string $lastTickAt,
        string $status = 'active',
        bool $withStorage = true
    ): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO players (id, status, last_tick_at, cash)
            VALUES (?, ?, ?, 1000)
        ");
        $stmt->execute([$id, $status, $lastTickAt]);
        if ($withStorage) {
            $this->db->prepare('INSERT INTO storage (player_id) VALUES (?)')->execute([$id]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function invokeFetchActivePlayers(PlayersSection $section): array
    {
        $method = new ReflectionMethod($section, 'fetchActivePlayers');
        return $method->invoke($section);
    }

    private function invokeCountActivePlayers(PlayersSection $section): int
    {
        $method = new ReflectionMethod($section, 'countActivePlayers');
        return (int)$method->invoke($section);
    }

    private function invokeCountPlayersMissingStorage(PlayersSection $section): int
    {
        $method = new ReflectionMethod($section, 'countPlayersMissingStorage');
        return (int)$method->invoke($section);
    }
}
