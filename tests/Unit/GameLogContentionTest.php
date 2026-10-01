<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';

final class GameLogContentionTest extends BaseTestCase
{
    public function testNormalWriteRetainsStructuredLogEntry(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'game-write-');
        try {
            GameLog::init($path);
            GameLog::setEnabled(true);
            GameLog::info('test', 'normal-probe', ['value' => 42]);
            self::assertStringContainsString('[INFO] [test] normal-probe | ctx={"value":42}', file_get_contents($path));
        } finally {
            GameLog::setEnabled(false);
            GameLog::init();
            unlink($path);
        }
    }

    public function testBusyLogDoesNotBlockRequestAndPreservesEntryInErrorLog(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'game-lock-');
        $fallback = tempnam(sys_get_temp_dir(), 'game-fallback-');
        $handle = fopen($path, 'ab');
        self::assertTrue(flock($handle, LOCK_EX));
        $code = 'require ' . var_export(dirname(__DIR__, 2) . '/src/GameLog.php', true) . ';'
            . 'ini_set("error_log", $argv[2]); GameLog::init($argv[1]); GameLog::info("test", "contention-probe");';
        $process = proc_open([PHP_BINARY, '-r', $code, $path, $fallback], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        try {
            $deadline = microtime(true) + 2;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) { break; }
                usleep(10000);
            } while (microtime(true) < $deadline);
            if ($status['running']) { proc_terminate($process); }
            self::assertFalse($status['running'], 'Logging must not wait for a maintenance lock');
            self::assertSame(0, $status['exitcode']);
            self::assertStringContainsString('contention-probe', file_get_contents($fallback));
            self::assertSame('', file_get_contents($path));
        } finally {
            foreach ($pipes as $pipe) { fclose($pipe); }
            proc_close($process);
            flock($handle, LOCK_UN);
            fclose($handle);
            unlink($path);
            unlink($fallback);
        }
    }
}
