<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Database.php';
require_once dirname(__DIR__, 2) . '/src/Auth.php';
require_once dirname(__DIR__, 2) . '/src/MobileWebBridge.php';

final class MobileWebBridgeTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->installDatabase($this->db);
        $this->db->exec("
            CREATE TABLE players (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                email TEXT NULL,
                status TEXT NOT NULL,
                email_verified INTEGER NOT NULL DEFAULT 1,
                last_login_at TEXT NULL
            )
        ");
        $this->db->exec("
            INSERT INTO players (id, username, email, status, email_verified)
            VALUES (1, 'mobile_user', 'mobile@example.test', 'active', 1)
        ");
    }

    protected function tearDown(): void
    {
        $this->installDatabase(null);
        parent::tearDown();
    }

    public function testBridgeTokenCanBeConsumedOnlyOnce(): void
    {
        $url = MobileWebBridge::createForPlayer(1, 'https://oilempire.pl');
        $token = $this->extractToken($url);

        $player = MobileWebBridge::consume($token);
        $this->assertSame(1, $player['id']);
        $this->assertSame('mobile_user', $player['username']);

        $this->assertNull(MobileWebBridge::consume($token));
    }

    public function testExpiredBridgeTokenDoesNotCreatePlayerContext(): void
    {
        $url = MobileWebBridge::createForPlayer(1, 'https://oilempire.pl');
        $token = $this->extractToken($url);
        $this->db->exec("UPDATE mobile_web_bridge_tokens SET expires_at = '2000-01-01 00:00:00'");

        $this->assertNull(MobileWebBridge::consume($token));
    }

    public function testInactiveOrUnverifiedPlayerCannotUseBridgeToken(): void
    {
        $this->db->exec("
            INSERT INTO players (id, username, email, status, email_verified)
            VALUES
                (2, 'blocked_user', 'blocked@example.test', 'banned', 1),
                (3, 'unverified_user', 'unverified@example.test', 'active', 0)
        ");

        $blockedUrl = MobileWebBridge::createForPlayer(2, 'https://oilempire.pl');
        $unverifiedUrl = MobileWebBridge::createForPlayer(3, 'https://oilempire.pl');

        $this->assertNull(MobileWebBridge::consume($this->extractToken($blockedUrl)));
        $this->assertNull(MobileWebBridge::consume($this->extractToken($unverifiedUrl)));
    }

    public function testConsumeRechecksExpiryDuringAtomicUpdate(): void
    {
        $url = MobileWebBridge::createForPlayer(1, 'https://oilempire.pl');
        $token = $this->extractToken($url);
        $hash = hash('sha256', $token);

        $this->db->prepare("UPDATE mobile_web_bridge_tokens SET expires_at = ? WHERE token_hash = ?")
            ->execute([date('Y-m-d H:i:s', time() + 1), $hash]);
        sleep(2);

        $this->assertNull(MobileWebBridge::consume($token));
    }

    public function testLoginByPlayerIdCreatesRegularWebSession(): void
    {
        $this->assertTrue(Auth::loginByPlayerId(1));
        $this->assertSame(1, $_SESSION['user_id']);
        $this->assertSame('mobile_user', $_SESSION['username']);
    }

    public function testBridgeEndpointRequiresBearerToken(): void
    {
        $result = $this->runEndpoint('api_no_auth');

        $this->assertSame(401, $result['status']);
        $this->assertSame(
            'Unauthorized: pass token via "Authorization: Bearer <token>"',
            $result['body']['error'] ?? null
        );
    }

    public function testBridgeEndpointRejectsWrongMethod(): void
    {
        $result = $this->runEndpoint('api_wrong_method');

        $this->assertSame(405, $result['status']);
        $this->assertSame('Method Not Allowed - use POST', $result['body']['error'] ?? null);
    }

    public function testBridgeEndpointReturnsTrustedBridgeUrl(): void
    {
        $result = $this->runEndpoint('api_success', [
            'HTTP_HOST' => 'attacker.example',
        ]);

        $this->assertSame(200, $result['status']);
        $this->assertSame(60, $result['body']['expires_in_seconds'] ?? null);

        $bridgeUrl = (string)($result['body']['bridge_url'] ?? '');
        $this->assertStringStartsWith('https://oilempire.pl/mobile-bridge-login?token=', $bridgeUrl);
        $this->assertStringNotContainsString('attacker.example', $bridgeUrl);
    }

    public function testPublicBridgeLoginCreatesWebSessionForValidToken(): void
    {
        $result = $this->runEndpoint('public_success');

        $this->assertSame(1, $result['session']['user_id'] ?? null);
        $this->assertSame('mobile_user', $result['session']['username'] ?? null);
    }

    public function testPublicBridgeLoginDoesNotCreateSessionForInvalidToken(): void
    {
        $result = $this->runEndpoint('public_invalid');

        $this->assertArrayNotHasKey('user_id', $result['session']);
    }

    private function extractToken(string $url): string
    {
        parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('token', $query);
        return (string)$query['token'];
    }

    private function installDatabase(?PDO $pdo): void
    {
        $ref = new ReflectionClass(Database::class);
        if ($pdo === null) {
            $instanceProp = $ref->getProperty('instance');
            $instanceProp->setAccessible(true);
            $instanceProp->setValue(null, null);
            return;
        }

        $database = $ref->newInstanceWithoutConstructor();
        $pdoProp = $ref->getProperty('pdo');
        $pdoProp->setAccessible(true);
        $pdoProp->setValue($database, $pdo);

        $instanceProp = $ref->getProperty('instance');
        $instanceProp->setAccessible(true);
        $instanceProp->setValue(null, $database);
    }

    /**
     * @param array<string,string> $serverOverrides
     * @return array{status:int,body:array<string,mixed>|null,session:array<string,mixed>,raw:string,stderr:string}
     */
    private function runEndpoint(string $mode, array $serverOverrides = []): array
    {
        $root = dirname(__DIR__, 2);
        $payload = base64_encode(serialize([
            'mode' => $mode,
            'root' => $root,
            'server' => $serverOverrides,
        ]));

        $runner = tempnam(sys_get_temp_dir(), 'bridge_endpoint_');
        $this->assertIsString($runner);
        file_put_contents($runner, $this->endpointRunnerSource($payload));

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open([PHP_BINARY, $runner], $descriptors, $pipes, $root);
        $this->assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        @unlink($runner);

        $this->assertSame(0, $exitCode, $stderr ?: $stdout);
        $marker = "\n__ENDPOINT_META__";
        $markerPos = strrpos($stdout, $marker);
        $this->assertNotFalse($markerPos, $stdout);

        $bodyRaw = trim(substr($stdout, 0, (int)$markerPos));
        $metaRaw = substr($stdout, (int)$markerPos + strlen($marker));
        $meta = json_decode($metaRaw, true);
        $this->assertIsArray($meta, $metaRaw);

        $body = null;
        if ($bodyRaw !== '') {
            $decodedBody = json_decode($bodyRaw, true);
            if (is_array($decodedBody)) {
                $body = $decodedBody;
            }
        }

        return [
            'status' => (int)($meta['status'] ?? 200),
            'body' => $body,
            'session' => is_array($meta['session'] ?? null) ? $meta['session'] : [],
            'raw' => $stdout,
            'stderr' => $stderr,
        ];
    }

    private function endpointRunnerSource(string $payload): string
    {
        return str_replace('__PAYLOAD__', $payload, <<<'PHP'
<?php
declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

$payload = unserialize(base64_decode('__PAYLOAD__'), ['allowed_classes' => false]);
$root = $payload['root'];
$mode = $payload['mode'];
$serverOverrides = $payload['server'];
$dbPath = tempnam(sys_get_temp_dir(), 'bridge_endpoint_db_');

if (!function_exists('getallheaders')) {
    function getallheaders(): array
    {
        return [];
    }
}

register_shutdown_function(static function () use (&$dbPath): void {
    echo "\n__ENDPOINT_META__" . json_encode([
        'status' => http_response_code() ?: 200,
        'session' => $_SESSION ?? [],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (is_string($dbPath) && is_file($dbPath)) {
        @unlink($dbPath);
    }
});

require_once $root . '/src/GameLog.php';
require_once $root . '/src/Database.php';
GameLog::setEnabled(false);

$db = new PDO('sqlite:' . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'), 0);

$db->exec("
    CREATE TABLE players (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        email TEXT NULL,
        cash REAL NOT NULL DEFAULT 0,
        status TEXT NOT NULL,
        financial_state TEXT NOT NULL DEFAULT 'normal',
        crisis_ticks INTEGER NOT NULL DEFAULT 0,
        credit_score INTEGER NOT NULL DEFAULT 0,
        offline_mode INTEGER NOT NULL DEFAULT 0,
        last_tick_at TEXT NULL,
        safety_procedures_level INTEGER NOT NULL DEFAULT 0,
        procedure_integrity REAL NOT NULL DEFAULT 1,
        email_verified INTEGER NOT NULL DEFAULT 1,
        last_login_at TEXT NULL
    )
");
$db->exec("
    CREATE TABLE api_tokens (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        player_id INTEGER NOT NULL,
        token TEXT NOT NULL UNIQUE,
        device TEXT NULL,
        created_at TEXT NULL,
        last_used_at TEXT NULL,
        expires_at TEXT NULL
    )
");
$db->exec("
    INSERT INTO players
        (id, username, email, cash, status, email_verified, last_tick_at)
    VALUES
        (1, 'mobile_user', 'mobile@example.test', 1000, 'active', 1, '2026-06-29 00:00:00')
");
$mobileToken = str_repeat('a', 64);
$db->prepare("INSERT INTO api_tokens (player_id, token, expires_at) VALUES (1, ?, ?)")
    ->execute([$mobileToken, date('Y-m-d H:i:s', time() + 3600)]);

$ref = new ReflectionClass(Database::class);
$database = $ref->newInstanceWithoutConstructor();
$pdoProp = $ref->getProperty('pdo');
$pdoProp->setAccessible(true);
$pdoProp->setValue($database, $db);
$instanceProp = $ref->getProperty('instance');
$instanceProp->setAccessible(true);
$instanceProp->setValue(null, $database);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$_SESSION = [];
$_GET = [];
$_POST = [];
$_SERVER = array_merge($_SERVER, [
    'REMOTE_ADDR' => '127.0.0.1',
    'HTTP_HOST' => 'oilempire.pl',
    'HTTPS' => 'on',
    'REQUEST_URI' => '/',
    'SCRIPT_NAME' => '/endpoint-test.php',
], $serverOverrides);

if ($mode === 'api_no_auth') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    unset($_SERVER['HTTP_AUTHORIZATION']);
    require $root . '/api/v1/auth/webview-bridge.php';
    exit;
}

if ($mode === 'api_wrong_method') {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $mobileToken;
    require $root . '/api/v1/auth/webview-bridge.php';
    exit;
}

if ($mode === 'api_success') {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $mobileToken;
    require $root . '/api/v1/auth/webview-bridge.php';
    exit;
}

if ($mode === 'public_success') {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    require_once $root . '/src/MobileWebBridge.php';
    $bridgeUrl = MobileWebBridge::createForPlayer(1, 'https://oilempire.pl');
    parse_str((string)parse_url($bridgeUrl, PHP_URL_QUERY), $_GET);
    require $root . '/public/mobile_bridge_login.php';
    exit;
}

if ($mode === 'public_invalid') {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET['token'] = str_repeat('f', 64);
    require $root . '/public/mobile_bridge_login.php';
    exit;
}

throw new RuntimeException('Unknown endpoint test mode: ' . $mode);
PHP
        );
    }
}
