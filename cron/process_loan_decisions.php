<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('cron/process_loan_decisions.php') : microtime(true);
try {


// zapis logu zeby sprawdzic czy cron dziala
file_put_contents(__DIR__ . "/cron_log.txt", date("Y-m-d H:i:s") . "\n", FILE_APPEND);

/**
 * CRON JOB - Przetwarzanie decyzji kredytowych
 * uruchamiany co kilka minut
 */

require_once __DIR__ . '/../src/init.php';

$decisionService = new LoanDecisionService();

// polaczenie z baza
$db = Database::getInstance()->getConnection();

$stmt = $db->query("
    SELECT id 
    FROM loan_applications 
    WHERE status = 'pending' 
    AND decision_at <= NOW()
");

$applications = $stmt->fetchAll();

foreach ($applications as $app) {
    $decisionService->processApplication($app['id']);
}

echo "Processed " . count($applications) . " loan applications\n";
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('cron/process_loan_decisions.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo 'Wystapil blad aplikacji.';
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('cron/process_loan_decisions.php', $_codexGuardStart);
    }
}
