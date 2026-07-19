<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
require_once $root . '/src/init.php';

GameLog::setEnabled(false);

if ($argc < 7) {
    fwrite(
        STDERR,
        "Usage: php pipeline_assignment_concurrent_worker.php <playerId> <staffId> <pipelineId> <allocation> <readyFile> <gateFile>\n"
    );
    exit(2);
}

$playerId = (int)$argv[1];
$staffId = (int)$argv[2];
$pipelineId = (int)$argv[3];
$allocation = (float)$argv[4];
$readyFile = (string)$argv[5];
$gateFile = (string)$argv[6];

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
        throw new RuntimeException('Concurrent pipeline assignment gate timeout.');
    }

    $service = new EmployeeAssignmentService($db);
    $result = $service->assignToPipeline(
        new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, $staffId, $playerId),
        $pipelineId,
        $allocation
    );
    echo json_encode($result, JSON_UNESCAPED_SLASHES);
    exit(0);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES);
    exit(0);
}
