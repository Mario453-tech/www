<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';
require_once __DIR__ . '/tts_test_service.php';

$cfg = require dirname(__DIR__, 2) . '/config/database.php';
$db = new PDO('mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=utf8mb4', $cfg['user'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
try {
    $service = ttsTestService($db, (int)$argv[1]);
    new FinancialTransactionService($db);
    $db->beginTransaction();
    $db->query('SELECT COUNT(*) FROM technical_tasks')->fetchColumn();
    echo "READY\n";
    flush();
    fgets(STDIN);
    $result = $service->assignTask((int)$argv[2], 'safety_audit');
    if (!$db->inTransaction()) {
        throw new RuntimeException('TTS committed the parent transaction');
    }
    $db->commit();
    echo json_encode($result, JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    fwrite(STDERR, $e->getMessage());
    exit(1);
}
