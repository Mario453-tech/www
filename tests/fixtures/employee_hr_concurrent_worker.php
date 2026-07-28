<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/vendor/autoload.php';
require_once $root . '/src/init.php';

GameLog::setEnabled(false);

if ($argc < 5) {
    fwrite(STDERR, "Usage: php employee_hr_concurrent_worker.php <action> <payloadBase64> <readyFile> <gateFile>\n");
    exit(2);
}

$action = (string)$argv[1];
$payload = json_decode((string)base64_decode((string)$argv[2], true), true);
$readyFile = (string)$argv[3];
$gateFile = (string)$argv[4];

if (!is_array($payload)) {
    fwrite(STDERR, "Invalid worker payload.\n");
    exit(2);
}

try {
    $cfg = require $root . '/config/database.php';
    $dsn = 'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=' . $cfg['charset'];
    $db = new PDO($dsn, $cfg['user'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    file_put_contents($readyFile, (string)getmypid());
    $deadline = microtime(true) + 15.0;
    while (!is_file($gateFile) && microtime(true) < $deadline) {
        usleep(5000);
    }
    if (!is_file($gateFile)) {
        throw new RuntimeException('Employee HR concurrency gate timeout.');
    }

    $result = match ($action) {
        'offer' => submitOffer($db, $payload),
        'negotiation_deadline' => expireNegotiation($db, $payload),
        'raise_accept' => (new EmployeeRaiseRequestService($db))->acceptFull(
            (int)$payload['player_id'],
            (int)$payload['request_id'],
            (string)$payload['token']
        ),
        'raise_deadline' => [
            'changed' => (new EmployeeStrikeService($db))->expireRaiseRequest(
                (int)$payload['player_id'],
                (int)$payload['request_id'],
                new DateTimeImmutable((string)$payload['now'])
            ),
        ],
        'tick_lock' => probeTickLock($db),
        default => throw new InvalidArgumentException('Unknown employee HR worker action.'),
    };

    echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error_class' => $exception::class,
        'error' => $exception->getMessage(),
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit(0);
}

/** @param array<string,mixed> $payload */
function submitOffer(PDO $db, array $payload): array
{
    $service = new EmployeeNegotiationService($db);
    $arguments = [
        (int)$payload['player_id'],
        (int)$payload['strike_id'],
        (float)$payload['raise_pct'],
        (float)$payload['bonus_per_member'],
        (string)$payload['token'],
        new DateTimeImmutable((string)$payload['now']),
    ];
    $method = new ReflectionMethod(EmployeeNegotiationService::class, 'submitOffer');
    if ($method->getNumberOfParameters() >= 7) {
        $arguments[] = (int)$payload['expected_round'];
    }

    /** @var array<string,mixed> $result */
    $result = $method->invokeArgs($service, $arguments);
    return $result;
}

/** @param array<string,mixed> $payload */
function expireNegotiation(PDO $db, array $payload): array
{
    $service = new EmployeeStrikeService($db);
    $method = new ReflectionMethod(EmployeeStrikeService::class, 'expireNegotiationRecord');
    $changed = $method->invoke(
        $service,
        [
            'id' => (int)$payload['negotiation_id'],
            'player_id' => (int)$payload['player_id'],
            'strike_id' => (int)$payload['strike_id'],
        ],
        new DateTimeImmutable((string)$payload['now'])
    );

    return ['changed' => (bool)$changed];
}

/** @return array{acquired:bool,busy:bool} */
function probeTickLock(PDO $db): array
{
    $coordinator = new TickCoordinator($db);
    $acquire = new ReflectionMethod(TickCoordinator::class, 'acquireLock');
    $release = new ReflectionMethod(TickCoordinator::class, 'releaseLock');
    $acquired = (bool)$acquire->invoke($coordinator);
    if ($acquired) {
        usleep(200000);
        $release->invoke($coordinator);
    }

    return ['acquired' => $acquired, 'busy' => $coordinator->wasBusy()];
}
