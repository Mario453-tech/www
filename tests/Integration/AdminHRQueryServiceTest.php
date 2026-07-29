<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
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
        EmployeeSystemBootstrap::ensure($db);
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
        EmployeeSystemBootstrap::ensure($db);
        $service = new AdminHRQueryService($db);
        $seeded = (int)$db->query(
            "SELECT COUNT(*) FROM employee_dialogue_templates WHERE context_key='accepted'"
        )->fetchColumn();
        $insert = $db->prepare(
            "INSERT INTO employee_dialogue_templates
                (context_key, tone, text_pl, text_en, weight, is_active)
             VALUES ('accepted', 'calm', ?, ?, 1, 1)"
        );
        for ($index = 1; $index <= 65; $index++) {
            $insert->execute(['PL ' . $index, 'EN ' . $index]);
        }

        $page = $service->dialogues(['context_key'=>'accepted'], 2);

        self::assertSame($seeded + 65, $page['total']);
        self::assertSame(3, $page['pages']);
        self::assertSame(2, $page['page']);
        self::assertCount(30, $page['rows']);
    }

    public function testNegotiationHistoryKeepsAttemptOrder(): void
    {
        $db = $this->createSqlitePdo();
        EmployeeSystemBootstrap::ensure($db);
        $db->exec("INSERT INTO employee_strike_negotiation_rounds
            (negotiation_id, strike_id, player_id, attempt_no, round_no, idempotency_token,
             raise_pct, bonus_per_member, random_roll, formula_json, result)
            VALUES
            (1, 7, 1, 2, 1, 'attempt-2', 2, 0, 50, '{}', 'rejected'),
            (1, 7, 1, 1, 2, 'attempt-1', 3, 0, 50, '{}', 'rejected')");

        $rows = (new AdminHRQueryService($db))->negotiationRounds(7);

        self::assertSame(1, (int)$rows[0]['attempt_no']);
        self::assertSame(2, (int)$rows[0]['round_no']);
        self::assertSame(2, (int)$rows[1]['attempt_no']);
        self::assertSame(1, (int)$rows[1]['round_no']);
    }
}
