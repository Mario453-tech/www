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
            [['registered_from' => '2026-02-30']],
            [['registered_to' => '2026-9-1']],
            [['registered_from' => ['2026-09-01']]],
            [['registered_from' => '2026-09-24', 'registered_to' => '2026-09-23']],
            [['registered_to' => "2026-09-23' OR 1=1"]],
        ];
    }

    public function testFilterTranslationsExistInBothLanguages(): void
    {
        foreach (['pl', 'en'] as $language) {
            $translations = require dirname(__DIR__, 2) . '/lang/' . $language . '/admin/players.php';
            foreach (['login_from', 'login_to', 'registered_from', 'registered_to', 'col_registered', 'sort', 'sort_id', 'sort_login_asc', 'sort_login_desc', 'apply', 'reset', 'login_help', 'invalid_dates'] as $key) {
                self::assertNotEmpty($translations['admin.players.' . $key]);
            }
        }
    }
}
