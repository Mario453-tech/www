<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
require_once $root . '/src/init.php';

GameLog::setEnabled(false);

if ($argc < 6) {
    fwrite(STDERR, "Usage: php hub_assignment_concurrent_worker.php <playerId> <hubId> <wellId> <readyFile> <gateFile>\n");
    exit(2);
}

$playerId = (int)$argv[1];
$hubId = (int)$argv[2];
$wellId = (int)$argv[3];
$readyFile = (string)$argv[4];
$gateFile = (string)$argv[5];

try {
    $cfg = require $root . '/config/database.php';
    $dsn = 'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=' . $cfg['charset'];
    $db = new PDO($dsn, $cfg['user'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    file_put_contents($readyFile, (string)getmypid());
    $deadline = microtime(true) + 10.0;
    while (!is_file($gateFile) && microtime(true) < $deadline) {
        usleep(10000);
    }
    if (!is_file($gateFile)) {
        throw new RuntimeException('Concurrent assignment gate timeout.');
    }

    $service = new HubAssignmentService($db, new HubService($db));
    echo json_encode($service->assignWell($playerId, $hubId, $wellId), JSON_UNESCAPED_SLASHES);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e::class . ': ' . $e->getMessage() . "\n");
    echo json_encode(['success' => false, 'error' => 'worker_exception']);
    exit(1);
}
