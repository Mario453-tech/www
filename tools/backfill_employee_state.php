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

$arguments = array_slice($_SERVER['argv'] ?? [], 1);
$apply = in_array('--apply', $arguments, true);
$playerId = null;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--player=')) {
        $value = substr($argument, strlen('--player='));
        if ($value === '' || !ctype_digit($value) || (int)$value <= 0) {
            fwrite(STDERR, "Invalid --player value.\n");
            exit(2);
        }
        $playerId = (int)$value;
    }
}

try {
    $db = Database::getInstance()->getConnection();
    if ($apply) {
        EmployeeSystemBootstrap::ensure($db);
    } elseif (EmployeeSystemSchema::currentVersion($db) < EmployeeSystemSchema::VERSION) {
        throw new RuntimeException('Employee schema is not ready. Run migrate_employee_system.php --apply-schema first.');
    }
    $service = new EmployeeStateService($db, new EmployeeRepository($db), false);
    $result = $service->backfillEmployeeState($apply, $playerId);

    fwrite(STDOUT, json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    ) . PHP_EOL);
    exit($result['errors'] === [] ? 0 : 3);
} catch (Throwable $exception) {
    GameLog::error('backfill_employee_state.php', 'employee state backfill FAILED', $exception);
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
