<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/init.php';

$db = Database::getInstance()->getConnection();
$source = php_sapi_name() === 'cli' ? 'cron' : 'http';

if (php_sapi_name() !== 'cli' && !defined('FORCE_TICK_INTERNAL')) {
    $cronKey = '';
    try {
        $stmt = $db->prepare("SELECT `value` FROM well_config WHERE `key` = ? LIMIT 1");
        $stmt->execute(['cron_secret_key']);
        $value = $stmt->fetchColumn();
        $cronKey = $value !== false ? (string)$value : '';
    } catch (Throwable) {
    }

    $provided = (string)($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '');
    if ($cronKey === '' || !hash_equals($cronKey, $provided)) {
        http_response_code(403);
        exit('Forbidden');
    }
    $source = 'cron_http';
}

$coordinator = new TickCoordinator($db);
$result = $coordinator->run($source);

$GLOBALS['OILCORP_TICK_BUSY'] = $coordinator->wasBusy();
$GLOBALS['OILCORP_TICK_LOCK_ERROR'] = $coordinator->hadLockError();
$GLOBALS['OILCORP_TICK_RESULT'] = $result;
$GLOBALS['OILCORP_TICK_SUMMARY'] = $coordinator->summary();

if ($result->status === TickRunResult::STATUS_FAILED && (!$coordinator->wasBusy() || $coordinator->hadLockError())) {
    http_response_code(500);
}

echo $coordinator->summary();
