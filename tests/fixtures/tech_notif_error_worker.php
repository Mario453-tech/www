<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/ApiErrorHandler.php';
require_once dirname(__DIR__, 2) . '/src/GameLog.php';
require_once dirname(__DIR__, 2) . '/src/Database.php';
require_once dirname(__DIR__, 2) . '/src/i18n.php';

class Auth
{
    public static function isLoggedIn(): bool { return true; }
    public static function getUserId(): int { return 42; }
}
class CSRF
{
    public static function validateToken(string $token): bool { return $token === 'valid'; }
}

$_COOKIE['locale'] = $argv[1];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['action' => 'mark_read', 'notif_id' => 1, '_token' => 'valid'];
GameLog::init($argv[2]);
ini_set('error_log', $argv[2]);
ApiErrorHandler::install(false);
$db = new PDO('sqlite::memory:');
$reflection = new ReflectionClass(Database::class);
$database = $reflection->newInstanceWithoutConstructor();
$reflection->getProperty('pdo')->setValue($database, $db);
$reflection->getProperty('instance')->setValue(null, $database);

// Isolate the endpoint from unrelated game bootstraps; keep its catch block intact.
// Odizoluj endpoint od bootstrapow gry; zachowaj oryginalny blok catch.
$source = file_get_contents(dirname(__DIR__, 2) . '/src/TechNotifApi.php');
$source = str_replace("require_once __DIR__ . '/init.php';", '', $source);
eval('?>' . $source);
fwrite(STDERR, 'STATUS=' . http_response_code());
