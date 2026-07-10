<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);

require_once $root . '/vendor/autoload.php';
require_once $root . '/src/init.php';
require_once $root . '/src/B2BContractService.php';

GameLog::setEnabled(false);

if ($argc < 5) {
    fwrite(STDERR, "Usage: php b2b_concurrent_worker.php <action> <sellerId> <offerId> <bbl> [readyFile]\n");
    exit(2);
}

$action = (string)$argv[1];
$sellerId = (int)$argv[2];
$offerId = (int)$argv[3];
$bbl = (float)$argv[4];
$readyFile = isset($argv[5]) ? (string)$argv[5] : '';

try {
    $cfg = require $root . '/config/database.php';
    $dsn = 'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=' . $cfg['charset'];
    $db = new PDO($dsn, $cfg['user'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    if ($readyFile !== '') {
        file_put_contents($readyFile, (string)getmypid());
    }

    $service = new B2BContractService($db);
    $result = match ($action) {
        'accept' => $service->acceptOffer($sellerId, $offerId, $bbl),
        'deliver' => $service->deliverPartial($sellerId, $offerId, $bbl),
        default => ['success' => false, 'status' => 'invalid_action'],
    };

    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e::class . ': ' . $e->getMessage() . "\n");
    echo json_encode(['success' => false, 'status' => 'worker_exception']);
    exit(1);
}
