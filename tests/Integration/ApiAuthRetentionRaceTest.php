<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/ApiAuthRateLimiter.php';

final class ApiRetentionInterleavingPdo extends PDO
{
    public ?Closure $beforeDelete = null;

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_starts_with($query, 'DELETE FROM api_auth_rate_limits') && $this->beforeDelete !== null) {
            $callback = $this->beforeDelete;
            $this->beforeDelete = null;
            $callback();
        }
        return parent::prepare($query, $options);
    }
}

final class ApiAuthRetentionRaceTest extends TestCase
{
    /** @return array<string, array{string, bool}> */
    public static function databases(): array
    {
        return ['sqlite' => ['sqlite', false], 'sqlite legacy' => ['sqlite', true],
            'mysql' => ['mysql', false], 'mysql legacy' => ['mysql', true]];
    }

    /** @dataProvider databases */
    public function testRenewalBetweenSnapshotAndDeleteSurvives(string $driver, bool $legacy): void
    {
        $path = null;
        if ($driver === 'mysql') {
            $dsn = getenv('API_TEST_MYSQL_DSN');
            if (!$dsn) {
                self::markTestSkipped('Set API_TEST_MYSQL_DSN to a dedicated test database');
            }
            self::assertMatchesRegularExpression('/dbname=([^;]*(?:test|ci)[^;]*)/i', $dsn);
        } else {
            $path = tempnam(sys_get_temp_dir(), 'api-retention-');
            $dsn = 'sqlite:' . $path;
        }
        $user = $driver === 'mysql' ? (getenv('API_TEST_DB_USER') ?: 'root') : null;
        $pass = $driver === 'mysql' ? (getenv('API_TEST_DB_PASS') ?: '') : null;
        $db = new ApiRetentionInterleavingPdo($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $other = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $limiter = new ApiAuthRateLimiter($db);
        $renewal = new ApiAuthRateLimiter($other);
        $ip = '2001:db8:' . bin2hex(random_bytes(2)) . ':' . bin2hex(random_bytes(2)) . '::1';
        $hex = ApiAuthRateLimiter::normalizeIp($ip);
        $keys = [hash('sha256', 'ip:' . $hex), hash('sha256', 'login:target|ip:' . $hex)];
        try {
            foreach ($keys as $key) {
                $db->prepare('INSERT INTO api_auth_rate_limits (bucket_key, attempts, expires_at) VALUES (?, ?, ?)')
                    ->execute([$key, '[1000,1000,1000,1000,1000]', $legacy ? null : 1900]);
            }
            $renewed = false;
            $db->beforeDelete = static function () use ($renewal, $ip, &$renewed): void {
                self::assertSame(0, $renewal->consume('target', $ip, null, 2000));
                $renewed = true;
            };
            $limiter->pruneExpired(2000);
            self::assertTrue($renewed);
            foreach ($keys as $key) {
                $stmt = $db->prepare('SELECT attempts, expires_at FROM api_auth_rate_limits WHERE bucket_key = ?');
                $stmt->execute([$key]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                self::assertIsArray($row);
                self::assertSame([2000], json_decode($row['attempts'], true));
                self::assertSame(2900, (int) $row['expires_at']);
            }
            for ($i = 0; $i < 4; $i++) {
                self::assertSame(0, $limiter->consume('target', $ip, null, 2000));
            }
            self::assertSame(900, $limiter->consume('target', $ip, null, 2000));
        } finally {
            $db->beforeDelete = null;
            foreach ($keys as $key) {
                $db->prepare('DELETE FROM api_auth_rate_limits WHERE bucket_key = ?')->execute([$key]);
            }
            unset($limiter, $renewal, $db, $other, $stmt);
            if ($path !== null) {
                @unlink($path);
            }
        }
    }
}
