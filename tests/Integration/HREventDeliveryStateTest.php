<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/HR/EmployeeDashboardQueryService.php';

final class HREventDeliveryStateTest extends TestCase
{
    private PDO $db;
    private EmployeeDashboardQueryService $service;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec(
            'CREATE TABLE employee_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                is_read INTEGER NOT NULL DEFAULT 0,
                notified_at TEXT NULL
            )'
        );
        $this->db->exec(
            'INSERT INTO employee_events (player_id, is_read, notified_at) VALUES
                (7, 0, NULL),
                (7, 0, NULL),
                (8, 0, NULL)'
        );
        $this->service = new EmployeeDashboardQueryService($this->db);
    }

    public function testDeliveryUpdatesOnlySelectedPlayerEvents(): void
    {
        self::assertSame(2, $this->service->markEventsNotified(7, [1, 2, 3]));

        $rows = $this->rows();
        self::assertNotNull($rows[1]['notified_at']);
        self::assertNotNull($rows[2]['notified_at']);
        self::assertNull($rows[3]['notified_at']);
        self::assertSame(0, (int)$rows[1]['is_read']);
    }

    public function testReadAlsoConfirmsDeliveryAndRejectsForeignIds(): void
    {
        self::assertSame(1, $this->service->markEventsRead(7, [1, 3]));

        $rows = $this->rows();
        self::assertSame(1, (int)$rows[1]['is_read']);
        self::assertNotNull($rows[1]['notified_at']);
        self::assertSame(0, (int)$rows[3]['is_read']);
        self::assertNull($rows[3]['notified_at']);
    }

    /** @return array<int,array<string,mixed>> */
    private function rows(): array
    {
        $rows = [];
        foreach ($this->db->query('SELECT * FROM employee_events ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[(int)$row['id']] = $row;
        }
        return $rows;
    }
}
