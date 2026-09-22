<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/ApiAuthRateLimiter.php';
require_once dirname(__DIR__, 2) . '/src/ApiErrorHandler.php';
require_once dirname(__DIR__, 2) . '/src/GameLog.php';

if (($argv[1] ?? '') === 'consume') {
    $db = new PDO($argv[2], getenv('API_TEST_DB_USER') ?: null, getenv('API_TEST_DB_PASS') ?: null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $db->exec('PRAGMA busy_timeout = 15000');
    }
    $limiter = new ApiAuthRateLimiter($db);
    while (isset($argv[5]) && !is_file($argv[5])) {
        usleep(1000);
    }
    session_id(bin2hex(random_bytes(12)));
    session_start();
    echo $limiter->consume($argv[3], $argv[4]);
    session_destroy();
    exit;
}

$_COOKIE['locale'] = $argv[2] ?? 'en';
GameLog::init($argv[3]);
ini_set('error_log', $argv[3]);
$mode = $argv[1];
if ($mode === 'init') {
    $source = file_get_contents(dirname(__DIR__, 2) . '/src/init.php');
    $prefix = explode('// KLASY CORE', $source, 2)[0];
    $prefix = str_replace('__DIR__', var_export(dirname(__DIR__, 2) . '/src', true), $prefix);
    eval('?>' . $prefix);
    ini_set('error_log', $argv[3]);
} else {
    ApiErrorHandler::install($mode !== 'html');
}
if ($mode === 'warning') {
    trigger_error('SECRET_PASSWORD SQL SELECT token FROM players', E_USER_WARNING);
    echo 'ok';
} elseif ($mode === 'fatal') {
    eval('function apiSecretDuplicate() {} function apiSecretDuplicate() {}');
} else {
    throw new RuntimeException('SECRET_PASSWORD SQL SELECT token FROM players /private/config.php');
}
