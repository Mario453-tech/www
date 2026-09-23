<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/AdminPlayerListQuery.php';

final class AdminPlayerListQueryTest extends TestCase
{
    /** @dataProvider invalidDates */
    public function testRejectsInvalidDates(array $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        AdminPlayerListQuery::filters($input);
    }

    public static function invalidDates(): array
    {
        return [
            [['login_from' => '2026-02-30']],
            [['login_to' => '2026-9-1']],
            [['login_from' => ['2026-09-01']]],
            [['login_from' => '2026-09-24', 'login_to' => '2026-09-23']],
            [['login_to' => "2026-09-23' OR 1=1"]],
        ];
    }

    public function testDateFiltersAndOrderingOnMySql(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';
        $db = new PDO('mysql:host=' . $config['host'] . ';dbname=' . $config['dbname'] . ';charset=utf8mb4', $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        // Temporary tables isolate fixtures from game data. / Tabele tymczasowe izoluja dane testowe od gry.
        $db->exec('CREATE TEMPORARY TABLE players (id INT, email VARCHAR(255), cash DECIMAL(15,2), status VARCHAR(30), last_login_at DATETIME)');
        $db->exec('CREATE TEMPORARY TABLE storage (player_id INT, used INT, capacity INT)');
        $db->exec('CREATE TEMPORARY TABLE wells (player_id INT)');
        $insert = $db->prepare('INSERT INTO players VALUES (?, ?, 100, ?, ?)');
        foreach ([
            [1, 'active', '2026-09-22 23:59:59'],
            [2, 'active', '2026-09-23 00:00:00'],
            [3, 'active', '2026-09-23 23:59:59'],
            [4, 'active', '2026-09-24 00:00:00'],
            [5, 'bankrupt', '2026-09-23 12:00:00'],
            [6, 'active', null],
        ] as [$id, $status, $login]) {
            $insert->execute([$id, 'player' . $id . '@example.test', $status, $login]);
        }
        $ids = static fn(array $input): array => array_map('intval', array_column(AdminPlayerListQuery::fetch($db, $input), 'id'));
        self::assertSame([3, 5, 2], $ids(['login_from' => '2026-09-23', 'login_to' => '2026-09-23']));
        self::assertSame([3, 2], $ids(['filter' => 'active', 'login_from' => '2026-09-23', 'login_to' => '2026-09-23']));
        self::assertSame([4, 3, 5, 2], $ids(['login_from' => '2026-09-23']));
        self::assertSame([3, 5, 2, 1], $ids(['login_to' => '2026-09-23']));
        self::assertSame([1, 2, 5, 3, 4, 6], $ids(['sort' => 'login_asc']));
        self::assertSame([4, 3, 5, 2, 1, 6], $ids([]));
        self::assertSame([1, 2, 3, 4, 5, 6], $ids(['sort' => 'id']));
        self::assertSame([], $ids(['login_from' => '2026-10-01']));
        self::assertSame([4, 3, 5, 2, 1, 6], $ids(['filter' => "active' OR 1=1", 'sort' => 'DROP TABLE players']));
    }

    public function testFilterTranslationsExistInBothLanguages(): void
    {
        foreach (['pl', 'en'] as $language) {
            $translations = require dirname(__DIR__, 2) . '/lang/' . $language . '/admin/players.php';
            foreach (['login_from', 'login_to', 'sort', 'sort_id', 'sort_login_asc', 'sort_login_desc', 'apply', 'reset', 'login_help', 'invalid_dates'] as $key) {
                self::assertNotEmpty($translations['admin.players.' . $key]);
            }
        }
    }
}
