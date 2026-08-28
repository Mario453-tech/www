<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/AdminLogs/GameLogReader.php';
require_once dirname(__DIR__, 2) . '/src/AdminLogs/LogRetentionService.php';

final class LogRetentionServiceTest extends BaseTestCase
{
    public function testDeletesOnlyAdminLogsOlderThanConfiguredDays(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE admin_logs (id INTEGER PRIMARY KEY, created_at TEXT NOT NULL)');
        $db->exec("INSERT INTO admin_logs (id, created_at) VALUES
            (1, '2026-07-01 10:00:00'),
            (2, '2026-08-20 10:00:00')");

        $service = new LogRetentionService($db, new GameLogReader());
        $deleted = $service->cleanupAdminLogs(
            30,
            new DateTimeImmutable('2026-08-28 12:00:00')
        );

        self::assertSame(1, $deleted);
        self::assertSame([2], $db->query('SELECT id FROM admin_logs')->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testZeroDaysDisablesAdminCleanup(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE admin_logs (id INTEGER PRIMARY KEY, created_at TEXT NOT NULL)');
        $db->exec("INSERT INTO admin_logs (id, created_at) VALUES (1, '2020-01-01 00:00:00')");

        $service = new LogRetentionService($db, new GameLogReader());

        self::assertSame(0, $service->cleanupAdminLogs(0));
        self::assertSame(1, (int)$db->query('SELECT COUNT(*) FROM admin_logs')->fetchColumn());
    }
}
