<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/GameLog.php';
require_once dirname(__DIR__) . '/src/WellNaming.php';
require_once dirname(__DIR__) . '/src/WellNamingMigrationService.php';

$arguments = array_slice($_SERVER['argv'] ?? [], 1);
$applySchema = in_array('--apply-schema', $arguments, true);
$apply = in_array('--apply', $arguments, true);
if ($applySchema && $apply) {
    fwrite(STDERR, "Choose --apply-schema or --apply.\n");
    exit(2);
}
try {
    $db = Database::getInstance()->getConnection();
    if ($applySchema) {
        $db->exec((string)file_get_contents(dirname(__DIR__) . '/migrations/well_name_counters.sql'));
        fwrite(STDOUT, "Well name counter schema is ready.\n");
        exit(0);
    }
    $result = (new WellNamingMigrationService($db))->run($apply);
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    GameLog::error('migrate_well_names.php', 'Well name migration failed', $e);
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
