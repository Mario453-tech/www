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
require_once $root . '/src/EmployeeSystemBootstrap.php';
require_once $root . '/src/Employee/EmployeeStateService.php';
require_once $root . '/src/HR/EmployeeLegacyMigrationService.php';

$arguments = array_slice($_SERVER['argv'] ?? [], 1);
$apply = in_array('--apply', $arguments, true);
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
    $result = (new EmployeeLegacyMigrationService(
        Database::getInstance()->getConnection()
    ))->run($apply, $playerId);
    fwrite(STDOUT, json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) . PHP_EOL);
    exit($result['state_backfill']['errors'] === [] ? 0 : 3);
} catch (Throwable $exception) {
    GameLog::error('migrate_employee_system.php', 'Employee legacy migration failed', $exception);
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
