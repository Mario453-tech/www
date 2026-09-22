<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';

final class EmployeeCompensationAtomicityTest extends SqliteIntegrationTestCase
{
    /** @dataProvider salaryCases */
    public function testSalaryRefreshDoesNotRunAnotherMoraleCycle(float $expected, float $salary, float $satisfaction): void
    {
        $db = $this->createSqlitePdo();
        $db->exec('CREATE TABLE technical_staff (id INTEGER, player_id INTEGER, salary REAL, status TEXT)');
        $db->exec('CREATE TABLE board_members (id INTEGER, player_id INTEGER, salary REAL, status TEXT)');
        $db->exec('CREATE TABLE employee_contracts (member_id INTEGER, salary REAL, status TEXT)');
        $db->exec('CREATE TABLE employee_source_links (player_id INTEGER, board_member_id INTEGER, technical_staff_id INTEGER)');
        $db->exec('CREATE TABLE employee_state (player_id INTEGER, source_type TEXT, source_id INTEGER, expected_salary REAL, salary_satisfaction REAL, morale REAL, last_morale_cycle_id INTEGER)');
        $db->exec("INSERT INTO technical_staff VALUES (10,1,100,'active')");
        $db->exec("INSERT INTO board_members VALUES (20,1,100,'active')");
        $db->exec("INSERT INTO employee_contracts VALUES (20,100,'active')");
        $db->exec('INSERT INTO employee_source_links VALUES (1,20,10)');
        $insert = $db->prepare('INSERT INTO employee_state VALUES (1,?,?,?,5,62,77)');
        $insert->execute(['technical_staff',10,$expected]);
        $insert->execute(['board_member',20,$expected]);
        $db->beginTransaction();
        (new EmployeeCompensationService($db))->setSalary(new EmployeeRef('technical_staff',10,1), $salary);
        foreach ($db->query('SELECT * FROM employee_state')->fetchAll() as $row) {
            self::assertSame($satisfaction, (float)$row['salary_satisfaction']);
            self::assertSame(62.0, (float)$row['morale']);
            self::assertSame(77, (int)$row['last_morale_cycle_id']);
        }
        self::assertSame($salary, (float)$db->query('SELECT salary FROM employee_contracts')->fetchColumn());
        $db->rollBack();
        self::assertSame(5.0, (float)$db->query('SELECT salary_satisfaction FROM employee_state LIMIT 1')->fetchColumn());
    }

    public static function salaryCases(): array
    {
        return [[300.0, 250.0, 83.33], [100.0, 200.0, 120.0], [0.0, 200.0, 100.0]];
    }
}
