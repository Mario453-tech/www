<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/src/Database.php';
require_once $root . '/src/GameLog.php';
require_once $root . '/src/Employee/EmployeeRef.php';
require_once $root . '/src/Employee/EmployeeRepository.php';
require_once $root . '/src/Employee/EmployeeSystemSchema.php';
require_once $root . '/src/EmployeeSystemBootstrap.php';
require_once $root . '/src/Employee/EmployeeStateService.php';
require_once $root . '/src/HR/EmployeeLegacyMigrationService.php';
require_once $root . '/src/HR/EmployeeDialogueTemplateService.php';

$arguments = array_slice($_SERVER['argv'] ?? [], 1);
$apply = in_array('--apply', $arguments, true);
$applySchema = in_array('--apply-schema', $arguments, true);
$playerId = null;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--player=')) {
        $value = substr($argument, strlen('--player='));
        if ($value === '' || !ctype_digit($value) || (int)$value <= 0) {
            fwrite(STDERR, "Invalid --player value.
");
            exit(2);
        }
        $playerId = (int)$value;
    }
}

try {
    $db = Database::getInstance()->getConnection();
    if ($applySchema) {
        EmployeeSystemBootstrap::ensure($db);
        (new EmployeeDialogueTemplateService($db))->ensureSeededDefaults();
        $result = [
            'schema_applied' => true,
            'schema_version' => EmployeeSystemSchema::currentVersion($db),
        ];
    } else {
        if (EmployeeSystemSchema::currentVersion($db) < EmployeeSystemSchema::VERSION) {
            throw new RuntimeException('Employee schema is not ready. Run with --apply-schema first.');
        }
        EmployeeSystemSchema::verifyCurrent($db);
        $result = (new EmployeeLegacyMigrationService($db))->run($apply, $playerId);
    }
    fwrite(STDOUT, json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) . PHP_EOL);
    exit(!isset($result['state_backfill']) || $result['state_backfill']['errors'] === [] ? 0 : 3);
} catch (Throwable $exception) {
    GameLog::error('migrate_employee_system.php', 'Employee legacy migration failed', $exception);
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
