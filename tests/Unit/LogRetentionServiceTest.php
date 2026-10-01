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

    public function testDeferredCleanupRunsOncePerHourAndRespondsToChangedSettings(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE admin_logs (id INTEGER PRIMARY KEY, created_at TEXT NOT NULL)');
        $path = tempnam(sys_get_temp_dir(), 'retention-');
        $service = new LogRetentionService($db, new GameLogReader());
        $now = new DateTimeImmutable('2026-10-01 12:00:00');
        try {
            file_put_contents($path, "[2020-01-01 00:00:00] old\n[2026-10-01 11:00:00] keep\n");
            self::assertSame(['admin' => 0, 'game' => 1], $service->cleanupIfDue($path, 2, 2, $now));
            $db->exec("INSERT INTO admin_logs VALUES (1, '2020-01-01 00:00:00')");
            self::assertNull($service->cleanupIfDue($path, 2, 2, $now->modify('+59 minutes')));
            self::assertSame(1, (int)$db->query('SELECT COUNT(*) FROM admin_logs')->fetchColumn());
            self::assertSame(['admin' => 1, 'game' => 0], $service->cleanupIfDue($path, 2, 2, $now->modify('+1 hour')));
            self::assertSame(['admin' => 0, 'game' => 0], $service->cleanupIfDue($path, 3, 2, $now->modify('+61 minutes')));
            self::assertSame("[2026-10-01 11:00:00] keep\n", file_get_contents($path));
        } finally {
            unlink($path);
            if (is_file($path . '.retention.log')) { unlink($path . '.retention.log'); }
        }
    }

    public function testConcurrentCleanupReturnsWithoutWaiting(): void
    {
        $db = new PDO('sqlite::memory:');
        $path = tempnam(sys_get_temp_dir(), 'retention-');
        $lock = fopen($path . '.retention.log', 'c+');
        flock($lock, LOCK_EX);
        try {
            self::assertNull((new LogRetentionService($db, new GameLogReader()))->cleanupIfDue($path, 2, 2));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            unlink($path);
            unlink($path . '.retention.log');
        }
    }

    public function testResponseAndSessionAreFinishedBeforeCleanup(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE admin_logs (id INTEGER PRIMARY KEY, created_at TEXT NOT NULL)');
        $db->exec("INSERT INTO admin_logs VALUES (1, '2020-01-01 00:00:00')");
        $path = tempnam(sys_get_temp_dir(), 'retention-');
        file_put_contents($path, "[2020-01-01 00:00:00] old\n");
        $sent = false;
        try {
            (new LogRetentionService($db, new GameLogReader()))->cleanupAfterResponse($path, 2, 2, function () use ($db, $path, &$sent): void {
                self::assertNotSame(PHP_SESSION_ACTIVE, session_status());
                self::assertSame(1, (int)$db->query('SELECT COUNT(*) FROM admin_logs')->fetchColumn());
                self::assertStringContainsString('old', file_get_contents($path));
                $sent = true;
            });
            self::assertTrue($sent);
            self::assertSame(0, (int)$db->query('SELECT COUNT(*) FROM admin_logs')->fetchColumn());
            self::assertSame('', file_get_contents($path));
        } finally {
            unlink($path);
            if (is_file($path . '.retention.log')) { unlink($path . '.retention.log'); }
        }
    }
}
