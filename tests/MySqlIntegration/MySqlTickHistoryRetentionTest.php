<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Tick/TickCoordinator.php';

final class MySqlTickHistoryRetentionTest extends PHPUnit\Framework\TestCase
{
    /** @dataProvider cleanupSchedule */
    public function testAutomaticCleanupPreservesRecentHistory(string $lastCleanup, bool $shouldClean): void
    {
        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        $db = new PDO('mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=utf8mb4', $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        (new ReflectionProperty(TickStatsRepository::class, 'schemaEnsured'))->setValue(null, []);
        new TickStatsRepository($db);
        new TickModuleConfigRepository($db);
        // Isolate fixture records using connection-local tables.
        // Odizoluj dane testowe w tabelach tymczasowych tego polaczenia.
        foreach (['tick_stats', 'tick_module_run_logs', 'well_config'] as $table) {
            $schema = $db->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
            $db->exec(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $schema));
        }
        $lastValue = $lastCleanup === 'invalid' ? 'invalid' : (new DateTimeImmutable($lastCleanup))->format('Y-m-d H:i:s');
        $db->prepare('INSERT INTO well_config (`key`, `value`, label, category) VALUES (?, ?, ?, ?)')
            ->execute(['tick_history_cleanup_at', $lastValue, 'Test', 'system']);
        foreach ([72, 49, 47, 1] as $hours) {
            $db->exec("INSERT INTO tick_stats (ran_at, source) VALUES (DATE_SUB(NOW(), INTERVAL {$hours} HOUR), 'cron')");
            $db->exec("INSERT INTO tick_module_run_logs (module_key, tick_sequence, source, status, duration_ms, created_at) VALUES ('market', {$hours}, 'cron', 'success', 1, DATE_SUB(NOW(), INTERVAL {$hours} HOUR))");
        }
        $coordinator = new TickCoordinator($db);
        $cleanup = new ReflectionMethod(TickCoordinator::class, 'cleanupTickHistoryIfDue');
        $cleanup->invoke($coordinator);
        foreach (['tick_stats', 'tick_module_run_logs'] as $table) {
            self::assertSame($shouldClean ? 2 : 4, (int)$db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn());
        }
        $saved = (string)$db->query("SELECT value FROM well_config WHERE `key` = 'tick_history_cleanup_at'")->fetchColumn();
        if ($shouldClean) {
            self::assertNotSame($lastValue, $saved);
            self::assertLessThan(10, abs(time() - strtotime($saved)));
        } else {
            self::assertSame($lastValue, $saved);
        }
    }

    public static function cleanupSchedule(): array
    {
        return [
            'older than one hour' => ['-2 hours', true],
            'recent cleanup' => ['-30 minutes', false],
            'future timestamp must not block cleanup' => ['+7 days', true],
            'invalid timestamp' => ['invalid', true],
        ];
    }
}
