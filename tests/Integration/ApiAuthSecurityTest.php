<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/ApiAuthRateLimiter.php';
require_once dirname(__DIR__, 2) . '/src/ApiErrorHandler.php';

final class ApiAuthSecurityTest extends TestCase
{
    private string $path;
    private PDO $db;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'api-limit-');
        $this->db = new PDO('sqlite:' . $this->path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    protected function tearDown(): void
    {
        unset($this->db);
        @unlink($this->path);
    }

    public function testSlidingWindowAndSharedAccountAliases(): void
    {
        $limiter = new ApiAuthRateLimiter($this->db);
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(0, $limiter->consume(' Player_1 ', '127.0.0.1', 42, 1000 + $i * 100));
        }
        self::assertSame(400, $limiter->consume('USER@example.test', '::ffff:127.0.0.1', 42, 1500));
        self::assertSame(0, $limiter->consume('player_1', '127.0.0.2', 42, 1500));
        self::assertSame(0, $limiter->consume('player_1', '127.0.0.1', 42, 1900));
        self::assertSame(100, $limiter->consume('player_1', '127.0.0.1', 42, 1900));
    }

    public function testIpLimitAcrossAccountsAndNewConnections(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $_SESSION = ['new_session' => $i];
            $db = new PDO('sqlite:' . $this->path);
            self::assertSame(0, (new ApiAuthRateLimiter($db))->consume('user_' . $i, '::1', null, 1000));
        }
        self::assertSame(899, (new ApiAuthRateLimiter($this->db))->consume('another', '0:0:0:0:0:0:0:1', null, 1001));
        self::assertSame(31, (int) $this->db->query('SELECT COUNT(*) FROM api_auth_rate_limits')->fetchColumn());
        self::assertSame(0, (new ApiAuthRateLimiter($this->db))->consume('another', '::1', null, 1900));
        $stored = json_encode($this->db->query('SELECT * FROM api_auth_rate_limits')->fetchAll());
        self::assertStringNotContainsString('user_', $stored);
        self::assertStringNotContainsString('::1', $stored);
    }

    public function testRetryAfterUsesLongestOfBothBlockedBuckets(): void
    {
        $limiter = new ApiAuthRateLimiter($this->db);
        for ($i = 0; $i < 25; $i++) {
            self::assertSame(0, $limiter->consume('other_' . $i, '192.0.2.8', null, 1000));
        }
        for ($i = 0; $i < 5; $i++) {
            self::assertSame(0, $limiter->consume('target', '192.0.2.8', 42, 1200));
        }
        self::assertSame(500, $limiter->consume('alias@example.test', '192.0.2.8', 42, 1600));
        self::assertSame(300, $limiter->consume('unknown', '192.0.2.8', null, 1600));
        self::assertSame(27, (int) $this->db->query('SELECT COUNT(*) FROM api_auth_rate_limits')->fetchColumn());
        self::assertSame(0, $limiter->consume('target', '192.0.2.9', 42, 1600));
    }

    public function testRetentionIsBoundedAndPreservesActiveAndLegacyBuckets(): void
    {
        $this->db->exec('CREATE TABLE api_auth_rate_limits (bucket_key CHAR(64) PRIMARY KEY, attempts TEXT NOT NULL)');
        $legacyKey = hash('sha256', 'legacy-active');
        $this->db->prepare('INSERT INTO api_auth_rate_limits VALUES (?, ?)')->execute([$legacyKey, '[1500]']);
        $limiter = new ApiAuthRateLimiter($this->db);
        ensureApiAuthRateLimitSchema($this->db);
        $insert = $this->db->prepare('INSERT INTO api_auth_rate_limits (bucket_key, attempts, expires_at) VALUES (?, ?, ?)');
        for ($i = 0; $i < 150; $i++) {
            $insert->execute([hash('sha256', 'expired-' . $i), '[1000]', 1900]);
        }
        $activeKey = hash('sha256', 'active');
        $insert->execute([$activeKey, '[1000,1500]', 2400]);
        self::assertSame(63, $limiter->pruneExpired(2000));
        self::assertSame(89, (int) $this->db->query('SELECT COUNT(*) FROM api_auth_rate_limits')->fetchColumn());
        self::assertSame(64, $limiter->pruneExpired(2000));
        self::assertSame(23, $limiter->pruneExpired(2000));
        self::assertSame(0, $limiter->pruneExpired(2000));
        self::assertSame(2, (int) $this->db->query('SELECT COUNT(*) FROM api_auth_rate_limits WHERE expires_at = 2400')->fetchColumn());
        self::assertSame(2, $limiter->pruneExpired(2400));
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM api_auth_rate_limits')->fetchColumn());
    }

    public function testRetentionCannotCommitCallerTransaction(): void
    {
        $limiter = new ApiAuthRateLimiter($this->db);
        $this->db->beginTransaction();
        try {
            $limiter->pruneExpired();
            self::fail('Retention accepted an active transaction');
        } catch (LogicException $error) {
            self::assertTrue($this->db->inTransaction());
        } finally {
            $this->db->rollBack();
        }
    }

    public function testInvalidIdentifiersAreRejected(): void
    {
        foreach ([null, [], 123, '', 'ab', 'a b', "abc\0def", 'user@', '<script>', str_repeat('a', 255), "\xff"] as $value) {
            self::assertNull(ApiAuthRateLimiter::normalizeIdentifier($value));
        }
        self::assertSame('user@example.test', ApiAuthRateLimiter::normalizeIdentifier(' USER@Example.Test '));
    }

    public function testBootstrapCannotCommitCallerTransaction(): void
    {
        $this->db->beginTransaction();
        try {
            new ApiAuthRateLimiter($this->db);
            self::fail('Bootstrap accepted an active transaction');
        } catch (LogicException $error) {
            self::assertTrue($this->db->inTransaction());
        } finally {
            $this->db->rollBack();
        }
    }

    public function testConcurrentRequestsWithIndependentSessions(): void
    {
        new ApiAuthRateLimiter($this->db);
        $now = time();
        $insert = $this->db->prepare('INSERT INTO api_auth_rate_limits (bucket_key, attempts, expires_at) VALUES (?, ?, ?)');
        for ($i = 0; $i < 128; $i++) {
            $insert->execute([hash('sha256', 'expired-' . $i), json_encode([$now - 901]), $now - 1]);
        }
        $activeKey = hash('sha256', 'active');
        $insert->execute([$activeKey, json_encode([$now]), $now + 900]);
        $gate = $this->path . '.gate';
        $workers = [];
        try {
            for ($i = 0; $i < 12; $i++) {
                $workers[] = $this->startWorker(['consume', 'sqlite:' . $this->path, 'User_1', '192.0.2.1', $gate]);
            }
            touch($gate);
            $allowed = 0;
            foreach ($workers as [$process, $pipes]) {
                $out = stream_get_contents($pipes[1]);
                $err = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                self::assertSame(0, proc_close($process), $err);
                self::assertMatchesRegularExpression('/^\d+$/', $out);
                $allowed += (int) ($out === '0');
            }
            self::assertSame(5, $allowed);
            self::assertSame(3, (int) $this->db->query('SELECT COUNT(*) FROM api_auth_rate_limits')->fetchColumn());
            $stmt = $this->db->prepare('SELECT attempts FROM api_auth_rate_limits WHERE bucket_key = ?');
            $stmt->execute([$activeKey]);
            self::assertSame([$now], json_decode((string) $stmt->fetchColumn(), true));
        } finally {
            @unlink($gate);
        }
    }

    public function testSafeExceptionWarningAndFatalResponsesAndLogs(): void
    {
        foreach (['json', 'html', 'init', 'warning', 'fatal'] as $mode) {
            foreach (['en', 'pl'] as $locale) {
                $log = $this->path . '.log';
                [$process, $pipes] = $this->startWorker([$mode, $locale, $log]);
                $output = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                $logs = is_file($log) ? file_get_contents($log) : '';
                @unlink($log);
                foreach (['SECRET_PASSWORD', 'SELECT token', '/private/config.php', 'apiSecretDuplicate'] as $secret) {
                    self::assertStringNotContainsString($secret, $output . $logs . $stderr);
                }
                self::assertNotSame('', $logs);
                $translations = require dirname(__DIR__, 2) . '/lang/' . $locale . '/api.php';
                if ($mode === 'warning') {
                    self::assertSame('ok', $output);
                } elseif (in_array($mode, ['html', 'init'], true)) {
                    self::assertSame('<p role="alert">' . $translations['error'] . '</p>', $output);
                } else {
                    self::assertSame(['error' => $translations['error']], json_decode($output, true));
                }
            }
        }
    }

    public function testTechnicalNotificationErrorsAreGenericAndLogged(): void
    {
        foreach (['pl', 'en'] as $locale) {
            $log = $this->path . '.log';
            $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/fixtures/tech_notif_error_worker.php', $locale, $log],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            self::assertSame(0, proc_close($process));
            self::assertSame('STATUS=500', $stderr);
            $translations = require dirname(__DIR__, 2) . '/lang/' . $locale . '/api.php';
            self::assertSame(['success' => false, 'error' => $translations['error']], json_decode($output, true));
            $logs = file_get_contents($log);
            self::assertStringContainsString('Notification update failed', $logs);
            self::assertStringContainsString('PDOException', $logs);
            self::assertStringNotContainsString('technical_notifications', $output . $logs);
            self::assertStringNotContainsString('SQLSTATE', $output . $logs);
            unlink($log);
        }
    }

    public function testHttpLoginLimitAliasesValidationAndRetryHeader(): void
    {
        $this->db->exec('CREATE TABLE players (id INTEGER PRIMARY KEY, username TEXT, email TEXT, password_hash TEXT, status TEXT, email_verified INTEGER)');
        $this->db->prepare('INSERT INTO players VALUES (1, ?, ?, ?, ?, 1)')->execute(['user_1', 'user@example.test', password_hash('correct', PASSWORD_BCRYPT), 'active']);
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertNotFalse($socket, $error);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        putenv('API_TEST_SQLITE=' . $this->path);
        $process = proc_open([PHP_BINARY, '-S', $address, dirname(__DIR__) . '/fixtures/api_auth_security_router.php'],
            [0 => ['pipe', 'r'], 1 => ['file', 'NUL', 'a'], 2 => ['file', 'NUL', 'a']], $pipes);
        fclose($pipes[0]);
        try {
            for ($i = 0; $i < 100; $i++) {
                $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
                if ($ready) {
                    fclose($ready);
                    break;
                }
                usleep(20000);
            }
            $send = static function (array $body, string $locale = 'en') use ($address): array {
                $context = stream_context_create(['http' => ['method' => 'POST', 'ignore_errors' => true,
                    'header' => "Content-Type: application/json\r\nAccept-Language: $locale\r\nCookie: PHPSESSID=" . bin2hex(random_bytes(8)),
                    'content' => json_encode($body)]]);
                $stream = fopen('http://' . $address . '/', 'r', false, $context);
                $response = stream_get_contents($stream);
                $headers = stream_get_meta_data($stream)['wrapper_data'];
                fclose($stream);
                return [$headers, json_decode($response, true)];
            };
            [$headers] = $send(['login' => [], 'password' => 'bad']);
            self::assertStringContainsString('400', $headers[0]);
            for ($i = 0; $i < 5; $i++) {
                [$headers, $body] = $send(['login' => $i % 2 ? 'USER@example.test' : ' USER_1 ', 'password' => 'bad']);
                self::assertStringContainsString('401', $headers[0]);
                self::assertSame('Invalid login or password.', $body['error']);
            }
            [$headers, $body] = $send(['login' => 'user_1', 'password' => 'correct'], 'pl');
            self::assertStringContainsString('429', $headers[0]);
            self::assertMatchesRegularExpression('/Retry-After: [1-9][0-9]{0,2}/', implode("\n", $headers));
            self::assertStringContainsString('Zbyt wiele', $body['error']);
            $this->db->exec('ALTER TABLE players RENAME TO hidden_players');
            [$headers, $body] = $send(['login' => 'user_1', 'password' => 'bad']);
            self::assertStringContainsString('500', $headers[0]);
            self::assertSame(['error' => 'An unexpected error occurred. Please try again later.'], $body);
        } finally {
            proc_terminate($process);
            proc_close($process);
            putenv('API_TEST_SQLITE');
        }
    }

    private function startWorker(array $args): array
    {
        $process = proc_open(array_merge([PHP_BINARY, dirname(__DIR__) . '/fixtures/api_auth_security_worker.php'], $args),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        return [$process, $pipes];
    }
}
