<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__) . '/Employee/EmployeeRef.php';
require_once dirname(__DIR__) . '/Employee/EmployeeRepository.php';
require_once dirname(__DIR__) . '/Employee/EmployeeSystemConfigService.php';
require_once __DIR__ . '/EmployeeRaiseRequest/DecisionTrait.php';
require_once __DIR__ . '/EmployeeRaiseRequest/NegotiationTrait.php';
require_once __DIR__ . '/EmployeeRaiseRequest/PersistenceTrait.php';
require_once __DIR__ . '/EmployeeNegotiationEffectivenessService.php';
require_once __DIR__ . '/EmployeeStrikeService.php';
require_once __DIR__ . '/EmployeeCompensationService.php';
require_once __DIR__ . '/EmployeeDeadlockRetry.php';

final class EmployeeRaiseRequestService
{
    use EmployeeRaiseRequestDecisionTrait;
    use EmployeeRaiseRequestNegotiationTrait;
    use EmployeeRaiseRequestPersistenceTrait;

    private readonly EmployeeRepository $employees;
    private readonly EmployeeSystemConfigService $config;
    private readonly EmployeeStrikeService $strikes;
    private readonly Closure $randomRoll;

    public function __construct(PDO $db, ?callable $randomRoll = null)
    {
        $this->db = $db;
        EmployeeSystemBootstrap::ensure($db);
        $this->employees = new EmployeeRepository($db);
        $this->config = new EmployeeSystemConfigService($db);
        $this->strikes = new EmployeeStrikeService($db);
        $this->randomRoll = $randomRoll !== null
            ? Closure::fromCallable($randomRoll)
            : static fn(): float => random_int(1, 10000) / 100.0;
    }

    private readonly PDO $db;

    /** @return list<array<string,mixed>> */
    public function listForPlayer(int $playerId, int $limit = 100): array
    {
        $this->assertPositiveIds($playerId);
        $limit = max(1, min(200, $limit));
        $stmt = $this->db->prepare(
            'SELECT rr.*, es.department_code, es.morale, es.salary_satisfaction,
                    es.strike_support, es.relation_status
               FROM employee_raise_requests rr
               JOIN employee_state es
                 ON es.player_id=rr.player_id
                AND es.source_type=rr.source_type
                AND es.source_id=rr.source_id
              WHERE rr.player_id=?
                AND rr.status IN (\'open\',\'postponed\')
              ORDER BY rr.created_at DESC, rr.id DESC
              LIMIT ' . $limit
        );
        $stmt->execute([$playerId]);

        $employeeMap = [];
        foreach ($this->employees->listForPlayer($playerId, null, false) as $employee) {
            $employeeMap[(string)$employee['source_type'] . ':' . (int)$employee['source_id']] = $employee;
        }

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $employee = $employeeMap[(string)$row['source_type'] . ':' . (int)$row['source_id']] ?? null;
            $liveSalary = is_array($employee) ? (float)$employee['salary'] : 0.0;
            $currentSalary = $liveSalary > 0.0
                ? $liveSalary
                : (float)($row['current_salary'] ?? 0.0);
            $requestedSalary = (float)($row['requested_salary'] ?? 0.0);
            if ($requestedSalary <= 0.0) {
                $requestedSalary = $this->requestedSalary($currentSalary, (float)$row['requested_raise_pct']);
            }
            $row['current_salary'] = $currentSalary;
            $row['requested_salary'] = $requestedSalary;
            $row['employee'] = $employee;
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<string,mixed> */
    public function acceptFull(int $playerId, int $requestId, string $token): array
    {
        return $this->runAction($playerId, $requestId, $token, 'accept_full');
    }

    /** @return array<string,mixed> */
    public function negotiate(
        int $playerId,
        int $requestId,
        float $offeredSalary,
        string $token
    ): array {
        if (!is_finite($offeredSalary) || $offeredSalary <= 0.0) {
            throw new InvalidArgumentException('Offered salary must be a positive finite number.');
        }

        return $this->runAction($playerId, $requestId, $token, 'negotiate', round($offeredSalary, 2));
    }

    /** @return array<string,mixed> */
    public function reject(int $playerId, int $requestId, string $token): array
    {
        return $this->runAction($playerId, $requestId, $token, 'reject');
    }

    /** @return array<string,mixed> */
    public function postpone(int $playerId, int $requestId, string $token): array
    {
        return $this->runAction($playerId, $requestId, $token, 'postpone');
    }

    /** @return array<string,mixed>|null */
    public function resultByToken(int $playerId, int $requestId, string $token): ?array
    {
        $this->assertPositiveIds($playerId, $requestId);
        $dedupeKey = $this->dedupeKey($playerId, $requestId, $this->normalizeToken($token));
        $suffix = $this->isMySql() && $this->db->inTransaction() ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            'SELECT meta_json FROM employee_events
              WHERE player_id=? AND dedupe_key=? LIMIT 1' . $suffix
        );
        $stmt->execute([$playerId, $dedupeKey]);
        $json = $stmt->fetchColumn();
        if (!is_string($json) || $json === '') {
            return null;
        }
        $meta = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($meta) || (int)($meta['request_id'] ?? 0) !== $requestId
            || !is_array($meta['public_result'] ?? null)) {
            return null;
        }

        /** @var array<string,mixed> $result */
        $result = $meta['public_result'];
        return $result + ['idempotent' => true];
    }

    /** @return array<string,mixed> */
    private function runAction(
        int $playerId,
        int $requestId,
        string $token,
        string $action,
        ?float $offeredSalary = null
    ): array {
        return EmployeeDeadlockRetry::run(
            $this->db,
            fn(): array => $this->runActionOnce(
                $playerId,
                $requestId,
                $token,
                $action,
                $offeredSalary
            )
        );
    }

    /** @return array<string,mixed> */
    private function runActionOnce(
        int $playerId,
        int $requestId,
        string $token,
        string $action,
        ?float $offeredSalary = null
    ): array {
        $this->assertPositiveIds($playerId, $requestId);
        $token = $this->normalizeToken($token);
        $existing = $this->resultByToken($playerId, $requestId, $token);
        if ($existing !== null) {
            if (!$this->matchesIdempotentAction($existing, $action, $offeredSalary)) {
                throw new InvalidArgumentException('Idempotency token was already used for another action.');
            }
            return $existing;
        }

        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $this->lockPlayer($playerId);
            $request = $this->lockRequest($playerId, $requestId);
            $existing = $this->resultByToken($playerId, $requestId, $token);
            if ($existing !== null) {
                if (!$this->matchesIdempotentAction($existing, $action, $offeredSalary)) {
                    throw new InvalidArgumentException('Idempotency token was already used for another action.');
                }
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return $existing;
            }
            if (!in_array((string)$request['status'], ['open', 'postponed'], true)) {
                throw new RuntimeException('Active raise request does not exist for this player.');
            }
            if (!empty($request['deadline_at'])
                && strtotime((string)$request['deadline_at']) < time()) {
                $this->strikes->expireRaiseRequest($playerId, $requestId, new DateTimeImmutable('now'));
                if ($ownTransaction) {
                    $this->db->commit();
                }
                throw new RuntimeException('Raise request deadline has passed.');
            }
            $ref = new EmployeeRef((string)$request['source_type'], (int)$request['source_id'], $playerId);
            $employee = $this->employees->find($ref);
            if ($employee === null) {
                throw new RuntimeException('Employee does not exist for this raise request.');
            }
            $state = $this->lockState($ref);
            if (in_array((string)$state['relation_status'], ['on_strike', 'leaving', 'inactive'], true)) {
                throw new RuntimeException('Raise request employee is not available for a decision.');
            }
            $employee = array_replace($employee, $this->lockEmployee($ref));
            $currentSalary = round((float)$employee['salary'], 2);
            $requestedSalary = (float)$request['requested_salary'] > 0.0
                ? round((float)$request['requested_salary'], 2)
                : $this->requestedSalary($currentSalary, (float)$request['requested_raise_pct']);
            $acceptedSalary = max($currentSalary, $requestedSalary);

            $formula = [];
            $result = match ($action) {
                'accept_full' => $this->applyAccepted(
                    $request,
                    $ref,
                    $acceptedSalary,
                    $this->config->getFloat('raise_accept_morale_gain'),
                    'accepted',
                    $this->config->getFloat('raise_accept_loyalty_gain'),
                    -$this->config->getFloat('raise_accept_leave_risk_reduction')
                ),
                'negotiate' => $this->applyNegotiation(
                    $request,
                    $ref,
                    $employee,
                    $state,
                    $currentSalary,
                    $requestedSalary,
                    (float)$offeredSalary,
                    $formula
                ),
                'reject' => $this->applyRejected($request, $ref),
                'postpone' => $this->applyPostponed($request, $ref),
                default => throw new LogicException('Unsupported raise request action.'),
            };

            $publicResult = [
                'request_id' => $requestId,
                'action' => $action,
                'result' => $result['result'],
                'status' => $result['status'],
                'salary' => $result['salary'],
                'morale' => $result['morale'],
                'deadline_at' => $result['deadline_at'],
            ];
            if ($action === 'negotiate') {
                $publicResult['chance'] = $formula['chance'];
                $publicResult['offered_salary'] = (float)$offeredSalary;
            }

            $this->insertEvent(
                $playerId,
                $ref,
                $requestId,
                $token,
                $action,
                $publicResult,
                $formula
            );

            if ($ownTransaction) {
                $this->db->commit();
            }
            return $publicResult + ['idempotent' => false];
        } catch (Throwable $exception) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function lockPlayer(int $playerId): void
    {
        if ($this->isMySql() === false) {
            $exists = $this->db->prepare(
                "SELECT 1 FROM sqlite_master WHERE type='table' AND name='players' LIMIT 1"
            );
            $exists->execute();
            if (!$exists->fetchColumn()) {
                return;
            }
        }
        $suffix = $this->isMySql() ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare("SELECT id FROM players WHERE id=? LIMIT 1{$suffix}");
        $stmt->execute([$playerId]);
        if ((int)($stmt->fetchColumn() ?: 0) !== $playerId) {
            throw new RuntimeException('Player does not exist for raise request decision.');
        }
    }

    /** @return array<string,mixed> */
    private function lockRequest(int $playerId, int $requestId): array
    {
        $suffix = $this->isMySql() ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT * FROM employee_raise_requests
              WHERE id=? AND player_id=?
              LIMIT 1{$suffix}"
        );
        $stmt->execute([$requestId, $playerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Active raise request does not exist for this player.');
        }
        return $row;
    }

    /** @return array{salary:float,status:string} */
    private function lockEmployee(EmployeeRef $ref): array
    {
        $table = $ref->sourceType === EmployeeRef::SOURCE_TECHNICAL_STAFF
            ? 'technical_staff'
            : 'board_members';
        $suffix = $this->isMySql() ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT salary, status FROM {$table} WHERE id=? AND player_id=? LIMIT 1{$suffix}"
        );
        $stmt->execute([$ref->sourceId, $ref->playerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || !$this->isActiveEmployeeStatus($ref->sourceType, (string)$row['status'])) {
            throw new RuntimeException('Raise request employee is not active.');
        }
        return ['salary' => (float)$row['salary'], 'status' => (string)$row['status']];
    }

    /** @return array<string,mixed> */
    private function lockState(EmployeeRef $ref): array
    {
        $suffix = $this->isMySql() ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT * FROM employee_state
              WHERE player_id=? AND source_type=? AND source_id=? LIMIT 1{$suffix}"
        );
        $stmt->execute([$ref->playerId, $ref->sourceType, $ref->sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Canonical employee state does not exist.');
        }
        return $row;
    }



    /**
     * @param array<string,mixed> $publicResult
     * @param array<string,float> $formula
     */
    private function insertEvent(
        int $playerId,
        EmployeeRef $ref,
        int $requestId,
        string $token,
        string $action,
        array $publicResult,
        array $formula
    ): void {
        $meta = [
            'request_id' => $requestId,
            'action' => $action,
            'public_result' => $publicResult,
            'formula' => $formula,
        ];
        $stmt = $this->db->prepare(
            'INSERT INTO employee_events
                (player_id, source_type, source_id, event_key, title_key, message_key, meta_json, dedupe_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $playerId,
            $ref->sourceType,
            $ref->sourceId,
            'raise_request_' . $action,
            'hr.event.raise_response.title',
            'hr.event.raise_response.' . $action,
            json_encode($meta, JSON_THROW_ON_ERROR),
            $this->dedupeKey($playerId, $requestId, $token),
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Raise request event insert did not affect exactly one row.');
        }
    }

    private function postponeLimit(): int
    {
        return max(0, $this->config->getInt('raise_max_postponements'));
    }

    private function currentSalary(EmployeeRef $ref): float
    {
        $table = $ref->sourceType === EmployeeRef::SOURCE_TECHNICAL_STAFF
            ? 'technical_staff'
            : 'board_members';
        $stmt = $this->db->prepare("SELECT salary FROM {$table} WHERE id=? AND player_id=?");
        $stmt->execute([$ref->sourceId, $ref->playerId]);
        $salary = $stmt->fetchColumn();
        if ($salary === false) {
            throw new RuntimeException('Employee salary cannot be loaded.');
        }
        return round((float)$salary, 2);
    }

    private function requestedSalary(float $currentSalary, float $raisePct): float
    {
        return round($currentSalary * (1.0 + max(0.0, $raisePct) / 100.0), 2);
    }

    private function normalizeToken(string $token): string
    {
        $token = trim($token);
        $length = strlen($token);
        if ($length < 16 || $length > 128) {
            throw new InvalidArgumentException('Raise request idempotency token must contain 16 to 128 characters.');
        }
        return $token;
    }

    /** @param array<string,mixed> $existing */
    private function matchesIdempotentAction(array $existing, string $action, ?float $offeredSalary): bool
    {
        if ((string)($existing['action'] ?? '') !== $action) {
            return false;
        }
        if ($action !== 'negotiate' || !array_key_exists('offered_salary', $existing)) {
            return true;
        }
        return $offeredSalary !== null
            && abs((float)$existing['offered_salary'] - $offeredSalary) <= 0.009;
    }

    private function dedupeKey(int $playerId, int $requestId, string $token): string
    {
        return 'raise-request:' . $playerId . ':' . $requestId . ':' . hash('sha256', $token);
    }

    private function assertPositiveIds(int ...$ids): void
    {
        foreach ($ids as $id) {
            if ($id <= 0) {
                throw new InvalidArgumentException('Player and request identifiers must be positive.');
            }
        }
    }

    private function isActiveEmployeeStatus(string $sourceType, string $status): bool
    {
        return $sourceType === EmployeeRef::SOURCE_TECHNICAL_STAFF
            ? in_array($status, ['active', 'busy'], true)
            : $status === 'active';
    }

    private function isMySql(): bool
    {
        return $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }
}
