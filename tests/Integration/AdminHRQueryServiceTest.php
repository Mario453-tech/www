<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/HR/AdminHRQueryService.php';

final class AdminHRQueryServiceTest extends SqliteIntegrationTestCase
{
    public function testEmployeeFiltersAndPaginationKeepPlayerIsolation(): void
    {
        $db = $this->createSqlitePdo();
        $db->exec('CREATE TABLE players (id INTEGER PRIMARY KEY, email TEXT NOT NULL)');
        $db->exec('CREATE TABLE board_members (
            id INTEGER PRIMARY KEY, first_name TEXT NOT NULL, last_name TEXT NOT NULL
        )');
        $db->exec('CREATE TABLE technical_staff (
            id INTEGER PRIMARY KEY, player_id INTEGER NOT NULL,
            first_name TEXT NOT NULL, last_name TEXT NOT NULL
        )');
        $service = new AdminHRQueryService($db);
        $db->exec("INSERT INTO players (id, email) VALUES (1, 'one@example.test'), (2, 'two@example.test')");
        $db->exec("INSERT INTO technical_staff (id, player_id, first_name, last_name) VALUES (10, 1, 'Jan', 'Nowak')");
        $db->exec("INSERT INTO employee_state
            (player_id, source_type, source_id, department_code, relation_status)
            VALUES (1, 'technical_staff', 10, 'technical', 'normal')");

        $visible = $service->employees([
            'player_id' => 1,
            'department' => 'technical',
        ], 1);
        $hidden = $service->employees(['player_id' => 2], 1);

        self::assertSame(1, $visible['total']);
        self::assertSame('Jan Nowak', $visible['rows'][0]['employee_name']);
        self::assertSame(0, $hidden['total']);
    }

    public function testDialoguePaginationRunsInDatabase(): void
    {
        $db = $this->createSqlitePdo();
        $service = new AdminHRQueryService($db);
        $insert = $db->prepare(
            "INSERT INTO employee_dialogue_templates
                (context_key, tone, text_pl, text_en, weight, is_active)
             VALUES ('accepted', 'calm', ?, ?, 1, 1)"
        );
        for ($index = 1; $index <= 65; $index++) {
            $insert->execute(['PL ' . $index, 'EN ' . $index]);
        }

        $page = $service->dialogues(['context_key'=>'accepted'], 2);

        self::assertSame(65, $page['total']);
        self::assertSame(3, $page['pages']);
        self::assertSame(2, $page['page']);
        self::assertCount(30, $page['rows']);
    }
}
