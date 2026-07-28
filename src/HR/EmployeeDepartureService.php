<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Employee/EmployeeAssignmentService.php';
require_once dirname(__DIR__) . '/Employee/EmployeeRef.php';
require_once dirname(__DIR__) . '/Employee/EmployeeSystemConfigService.php';
require_once __DIR__ . '/EmployeeRelationLifecycleService.php';

final class EmployeeDepartureService
{
    private readonly EmployeeSystemConfigService $config;
    private readonly EmployeeAssignmentService $assignments;

    public function __construct(private readonly PDO $db)
    {
        $this->config = new EmployeeSystemConfigService($db);
        $this->assignments = new EmployeeAssignmentService($db);
    }

    public function processCycle(int $cycleId, DateTimeInterface $now, int $limit = 100): int
    {
        $limit = max(1, min(1000, $limit));
        $departureCode = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "('departure:' || employee_state.id)"
            : "CONCAT('departure:', employee_state.id)";
        $stmt = $this->db->prepare(
            "SELECT id, player_id, source_type, source_id, leave_risk, leave_risk_streak, relation_status
               FROM employee_state
              WHERE last_morale_cycle_id=?
                AND relation_status NOT IN ('inactive','leaving')
                AND NOT EXISTS (
                    SELECT 1 FROM employee_cycle_department_claims c
                     WHERE c.cycle_id=? AND c.player_id=employee_state.player_id
                       AND c.department_code={$departureCode}
                       AND c.completed_at IS NOT NULL
                )
              ORDER BY id LIMIT " . $limit
        );
        $stmt->execute([$cycleId, $cycleId]);
        $started = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $state) {
            if ($this->processStateCycle($state, $cycleId, $now)) {
                $started++;
            }
        }
        return $started;
    }

    public function hasPendingCycle(int $cycleId): bool
    {
        if ($cycleId <= 0) {
            return false;
        }
        $departureCode = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "('departure:' || es.id)"
            : "CONCAT('departure:', es.id)";
        $stmt = $this->db->prepare(
            "SELECT 1 FROM employee_state es
              WHERE es.last_morale_cycle_id=?
                AND es.relation_status NOT IN ('inactive','leaving')
                AND NOT EXISTS (
                    SELECT 1 FROM employee_cycle_department_claims c
                     WHERE c.cycle_id=? AND c.player_id=es.player_id
                       AND c.department_code={$departureCode}
                       AND c.completed_at IS NOT NULL
                )
              LIMIT 1"
        );
        $stmt->execute([$cycleId, $cycleId]);
        return (bool)$stmt->fetchColumn();
    }

    public function processDue(DateTimeInterface $now, int $limit = 100): int
    {
        $limit = max(1, min(1000, $limit));
        $deadline = DateTimeImmutable::createFromInterface($now)
            ->modify('-' . $this->config->getInt('leave_notice_hours') . ' hours')
            ->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            "SELECT id, player_id, source_type, source_id
               FROM employee_state
              WHERE relation_status='leaving' AND leaving_at IS NOT NULL AND leaving_at<=?
              ORDER BY id LIMIT " . $limit
        );
        $stmt->execute([$deadline]);
        $completed = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $state) {
            if ($this->finalize($state, $now)) {
                $completed++;
            }
        }
        return $completed;
    }

    /** @param array<string,mixed> $state */
    private function processStateCycle(array $state, int $cycleId, DateTimeInterface $now): bool
    {
        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            if ($cycleId > 0
                && !$this->claim($cycleId, (int)$state['player_id'], 'departure:' . (int)$state['id'])) {
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return false;
            }
            $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $lock = $this->db->prepare(
                "SELECT * FROM employee_state WHERE id=? AND player_id=? LIMIT 1{$suffix}"
            );
            $lock->execute([(int)$state['id'], (int)$state['player_id']]);
            $current = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($current) || in_array((string)$current['relation_status'], ['inactive', 'leaving'], true)) {
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return false;
            }
            $streak = (float)$current['leave_risk'] >= $this->config->getFloat('leave_risk_threshold')
                ? (int)$current['leave_risk_streak'] + 1
                : 0;
            $leaving = $streak >= $this->config->getInt('leave_risk_cycles_required');
            if ($leaving) {
                $this->db->prepare(
                    "UPDATE employee_state
                        SET leave_risk_streak=?, relation_status='leaving', leaving_at=?,
                            version=version+1, updated_at=CURRENT_TIMESTAMP
                      WHERE id=? AND player_id=? AND relation_status NOT IN ('inactive','leaving')"
                )->execute([
                    $streak,
                    $now->format('Y-m-d H:i:s'),
                    (int)$current['id'],
                    (int)$current['player_id'],
                ]);
            } else {
                $this->db->prepare(
                    'UPDATE employee_state SET leave_risk_streak=?, version=version+1,
                            updated_at=CURRENT_TIMESTAMP
                      WHERE id=? AND player_id=? AND relation_status NOT IN (\'inactive\',\'leaving\')'
                )->execute([$streak, (int)$current['id'], (int)$current['player_id']]);
            }
            if ($leaving) {
                (new EmployeeRelationLifecycleService($this->db))->leaveOpenStrikes(
                    new EmployeeRef(
                        (string)$current['source_type'],
                        (int)$current['source_id'],
                        (int)$current['player_id']
                    ),
                    $now
                );
                $this->event($current, 'employee_leaving', 'hr.event.leaving.title', 'hr.event.leaving.message', [
                    'leaving_at' => $now->format('Y-m-d H:i:s'),
                    'notice_hours' => $this->config->getInt('leave_notice_hours'),
                ], 'employee-leaving:' . (int)$current['id']);
            }
            if ($cycleId > 0) {
                $this->completeClaim($cycleId, (int)$current['player_id'], 'departure:' . (int)$current['id']);
            }
            if ($ownTransaction) {
                $this->db->commit();
            }
            return $leaving;
        } catch (Throwable $exception) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @param array<string,mixed> $state */
    private function finalize(array $state, DateTimeInterface $now): bool
    {
        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $lock = $this->db->prepare(
                "SELECT * FROM employee_state WHERE id=? AND player_id=? AND relation_status='leaving' LIMIT 1{$suffix}"
            );
            $lock->execute([(int)$state['id'], (int)$state['player_id']]);
            $current = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($current)) {
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return false;
            }
            $ref = new EmployeeRef(
                (string)$current['source_type'],
                (int)$current['source_id'],
                (int)$current['player_id']
            );
            $this->assignments->releaseEmployeeAssignments($ref);
            if ($ref->sourceType === EmployeeRef::SOURCE_BOARD_MEMBER) {
                $this->db->prepare(
                    "UPDATE board_members SET status='fired'
                      WHERE id=? AND player_id=? AND status<>'fired'"
                )->execute([$ref->sourceId, $ref->playerId]);
                $this->db->prepare(
                    "UPDATE employee_contracts
                        SET status='terminated'
                      WHERE member_id=? AND status='active'
                        AND EXISTS (
                            SELECT 1 FROM board_members bm
                             WHERE bm.id=employee_contracts.member_id AND bm.player_id=?
                        )"
                )->execute([$ref->sourceId, $ref->playerId]);
            } else {
                $this->db->prepare(
                    "UPDATE technical_staff SET status='fired'
                      WHERE id=? AND player_id=? AND status<>'fired'"
                )->execute([$ref->sourceId, $ref->playerId]);
                if ($this->tableExists('well_staff_assignments')) {
                    $this->db->prepare(
                        'UPDATE well_staff_assignments SET unassigned_at=?
                          WHERE staff_id=? AND player_id=? AND unassigned_at IS NULL'
                    )->execute([$now->format('Y-m-d H:i:s'), $ref->sourceId, $ref->playerId]);
                }
                if ($this->tableExists('wells')) {
                    $this->db->prepare(
                        'UPDATE wells
                            SET operator_id=CASE WHEN operator_id=? THEN NULL ELSE operator_id END,
                                technician_id=CASE WHEN technician_id=? THEN NULL ELSE technician_id END
                          WHERE player_id=? AND (operator_id=? OR technician_id=?)'
                    )->execute([$ref->sourceId, $ref->sourceId, $ref->playerId, $ref->sourceId, $ref->sourceId]);
                }
            }
            (new EmployeeRelationLifecycleService($this->db))->deactivate($ref, $now);
            $this->event($current, 'employee_departed', 'hr.event.departed.title', 'hr.event.departed.message', [
                'inactive_at' => $now->format('Y-m-d H:i:s'),
            ], 'employee-departed:' . (int)$current['id']);
            if ($ownTransaction) {
                $this->db->commit();
            }
            return true;
        } catch (Throwable $exception) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    private function claim(int $cycleId, int $playerId, string $code): bool
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO employee_cycle_department_claims (cycle_id, player_id, department_code) VALUES (?, ?, ?)'
            : 'INSERT IGNORE INTO employee_cycle_department_claims (cycle_id, player_id, department_code) VALUES (?, ?, ?)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$cycleId, $playerId, $code]);
        if ($stmt->rowCount() === 1) {
            return true;
        }
        $suffix = $driver === 'mysql' ? ' FOR UPDATE' : '';
        $existing = $this->db->prepare(
            "SELECT completed_at FROM employee_cycle_department_claims
              WHERE cycle_id=? AND player_id=? AND department_code=? LIMIT 1{$suffix}"
        );
        $existing->execute([$cycleId, $playerId, $code]);
        return $existing->fetchColumn() === null;
    }

    private function completeClaim(int $cycleId, int $playerId, string $code): void
    {
        $this->db->prepare(
            'UPDATE employee_cycle_department_claims SET completed_at=CURRENT_TIMESTAMP
              WHERE cycle_id=? AND player_id=? AND department_code=?'
        )->execute([$cycleId, $playerId, $code]);
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $meta
     */
    private function event(array $state, string $key, string $title, string $message, array $meta, string $dedupe): void
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO employee_events
                (player_id, source_type, source_id, event_key, title_key, message_key, meta_json, dedupe_key)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            : 'INSERT IGNORE INTO employee_events
                (player_id, source_type, source_id, event_key, title_key, message_key, meta_json, dedupe_key)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $this->db->prepare($sql)->execute([
            (int)$state['player_id'],
            (string)$state['source_type'],
            (int)$state['source_id'],
            $key,
            $title,
            $message,
            json_encode($meta, JSON_THROW_ON_ERROR),
            $dedupe,
        ]);
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->db->query("SELECT 1 FROM {$table} WHERE 1=0");
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
