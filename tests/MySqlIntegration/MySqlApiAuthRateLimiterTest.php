<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/ApiAuthRateLimiter.php';

final class MySqlApiAuthRateLimiterTest extends TestCase
{
    public function testAtomicLimitsOnMySqlWithIndependentSessions(): void
    {
        $dsn = getenv('API_TEST_MYSQL_DSN');
        if (!$dsn) {
            self::markTestSkipped('Set API_TEST_MYSQL_DSN to a dedicated test database');
        }
        if (!preg_match('/dbname=([^;]*(?:test|ci)[^;]*)/i', $dsn)) {
            self::fail('A dedicated test database is required');
        }
        $db = new PDO($dsn, getenv('API_TEST_DB_USER') ?: 'root', getenv('API_TEST_DB_PASS') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        putenv('API_TEST_DB_USER=' . (getenv('API_TEST_DB_USER') ?: 'root'));
        $limiter = new ApiAuthRateLimiter($db);
        $ip = '2001:db8:' . bin2hex(random_bytes(2)) . ':' . bin2hex(random_bytes(2)) . '::1';
        $ipHex = ApiAuthRateLimiter::normalizeIp($ip);
        $keys = [hash('sha256', 'ip:' . $ipHex)];
        $gate = tempnam(sys_get_temp_dir(), 'api-gate-');
        unlink($gate);
        try {
            $now = time();
            $insert = $db->prepare('INSERT INTO api_auth_rate_limits (bucket_key, attempts, expires_at) VALUES (?, ?, ?)');
            for ($i = 0; $i < 128; $i++) {
                $key = hash('sha256', $ip . ':expired:' . $i);
                $keys[] = $key;
                $insert->execute([$key, json_encode([$now - 901]), $now - 1]);
            }
            $activeKey = hash('sha256', $ip . ':active');
            $keys[] = $activeKey;
            $insert->execute([$activeKey, json_encode([$now]), $now + 900]);
            $db->beginTransaction();
            try {
                ensureApiAuthRateLimitSchema($db);
                self::fail('DDL was allowed inside a transaction');
            } catch (LogicException $error) {
                self::assertTrue($db->inTransaction());
            } finally {
                $db->rollBack();
            }
            foreach ([['same_user', 12, 5], ['different_', 40, 25]] as [$prefix, $count, $expected]) {
                $workers = [];
                for ($i = 0; $i < $count; $i++) {
                    $login = $prefix === 'same_user' ? $prefix : $prefix . $i;
                    $keys[] = hash('sha256', 'login:' . $login . '|ip:' . $ipHex);
                    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/fixtures/api_auth_security_worker.php',
                        'consume', $dsn, $login, $ip, $gate],
                        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                    self::assertIsResource($process);
                    fclose($pipes[0]);
                    $workers[] = [$process, $pipes];
                }
                touch($gate);
                $allowed = 0;
                foreach ($workers as [$process, $pipes]) {
                    $output = stream_get_contents($pipes[1]);
                    $error = stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    self::assertSame(0, proc_close($process), $error);
                    self::assertMatchesRegularExpression('/^\d+$/', $output);
                    $allowed += (int) ($output === '0');
                }
                self::assertSame($expected, $allowed);
                unlink($gate);
            }
            self::assertGreaterThan(0, $limiter->consume('same_user', $ip));
            $stmt = $db->prepare('SELECT attempts FROM api_auth_rate_limits WHERE bucket_key = ?');
            $stmt->execute([$activeKey]);
            self::assertSame([$now], json_decode((string) $stmt->fetchColumn(), true));
            foreach (array_slice($keys, 1, 128) as $key) {
                $stmt->execute([$key]);
                self::assertFalse($stmt->fetchColumn());
            }
        } finally {
            @unlink($gate);
            $delete = $db->prepare('DELETE FROM api_auth_rate_limits WHERE bucket_key = ?');
            foreach (array_unique($keys) as $key) {
                $delete->execute([$key]);
            }
        }
    }
}
