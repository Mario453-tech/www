<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__) . '/Employee/EmployeeSystemConfigService.php';
require_once __DIR__ . '/EmployeeDepartureService.php';

final class EmployeeStrikeService
{
    private readonly EmployeeSystemConfigService $config;

    public function __construct(private readonly PDO $db)
    {
        EmployeeSystemBootstrap::ensure($db);
        $this->config = new EmployeeSystemConfigService($db);
    }

    /** @return array<string,int> */
    public function processEscalations(DateTimeInterface $now): array
    {
        $this->processDeadlines($now);
        return $this->processCycleEscalations($now, 0);
    }

    /** @return array{raise_requests_expired:int,negotiations_expired:int,departures_completed:int} */
    public function processDeadlines(DateTimeInterface $now): array
    {
        return [
            'raise_requests_expired' => $this->expireRaiseRequests($now),
            'negotiations_expired' => $this->expireNegotiations($now),
            'departures_completed' => (new EmployeeDepartureService($this->db))->processDue($now),
        ];
    }

    public function expireRaiseRequest(int $playerId, int $requestId, DateTimeInterface $now): bool
    {
        if ($playerId <= 0 || $requestId <= 0) {
            throw new InvalidArgumentException('Player and raise request identifiers must be positive.');
        }

        return $this->expireRaiseRequestRecord([
            'id' => $requestId,
            'player_id' => $playerId,
        ], $now);
    }

    /** @return array<string,int> */
    public function processCycleEscalations(DateTimeInterface $now, int $cycleId): array
    {
        $stats = [
            'raise_requests'=>0,
            'threats_started'=>0,
            'strikes_started'=>0,
            'threats_closed'=>0,
            'departures'=>0,
        ];
        if ($this->withCycleClaim($cycleId, 0, 'raise_requests', function () use ($now, &$stats): void {
            $stats['raise_requests'] = $this->createRaiseRequests($now);
        })) {
            // Claim completion is handled in withCycleClaim.
            // Zakonczenie claimu obsluguje withCycleClaim.
        }
        $stmt = $this->db->query(
            "SELECT player_id, department_code,
                    AVG(morale) AS avg_morale, AVG(strike_support) AS avg_support,
                    AVG(workload) AS avg_workload,
                    SUM(CASE WHEN relation_status IN ('dispute','strike_threat') THEN 1 ELSE 0 END) AS disputes
               FROM employee_state
              WHERE relation_status NOT IN ('inactive','leaving')
              GROUP BY player_id, department_code"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $department) {
            $this->withCycleClaim(
                $cycleId,
                (int)$department['player_id'],
                (string)$department['department_code'],
                function () use ($department, $now, &$stats): void {
                    $this->evaluateDepartment($department, $now, $stats);
                }
            );
        }
        $this->withCycleClaim($cycleId, 0, 'departures', function () use ($cycleId, $now, &$stats): void {
            $stats['departures'] = (new EmployeeDepartureService($this->db))->processCycle($cycleId, $now);
        });
        return $stats;
    }

    private function expireNegotiations(DateTimeInterface $now): int
    {
        $stmt = $this->db->prepare(
            "SELECT n.id, n.player_id, n.strike_id
               FROM employee_strike_negotiations n
               JOIN employee_strikes s ON s.id=n.strike_id AND s.player_id=n.player_id
              WHERE n.status='open' AND n.round_deadline_at < ? AND s.open_key IS NOT NULL"
        );
        $stmt->execute([$now->format('Y-m-d H:i:s')]);
        $expired = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $expired += $this->expireNegotiationRecord($row, $now) ? 1 : 0;
        }
        return $expired;
    }

    /** @param array<string,mixed> $row */
    private function expireNegotiationRecord(array $row, DateTimeInterface $now): bool
    {
        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $lock = $this->db->prepare(
                "SELECT n.id, n.player_id, n.strike_id
                   FROM employee_strike_negotiations n
                   JOIN employee_strikes s ON s.id=n.strike_id AND s.player_id=n.player_id
                  WHERE n.id=? AND n.player_id=? AND n.status='open'
                    AND n.round_deadline_at<? AND s.open_key IS NOT NULL
                  LIMIT 1{$suffix}"
            );
            $lock->execute([(int)$row['id'], (int)$row['player_id'], $now->format('Y-m-d H:i:s')]);
            $current = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($current)) {
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return false;
            }
            $cooldown = date(
                'Y-m-d H:i:s',
                $now->getTimestamp() + $this->config->getInt('negotiation_cooldown_hours') * 3600
            );
            $this->db->prepare(
                "UPDATE employee_strike_negotiations SET status='expired', updated_at=CURRENT_TIMESTAMP
                  WHERE id=? AND player_id=? AND status='open'"
            )->execute([(int)$current['id'], (int)$current['player_id']]);
            $this->db->prepare(
                "UPDATE employee_strikes SET status='active', negotiation_cooldown_until=?, updated_at=CURRENT_TIMESTAMP
                  WHERE id=? AND player_id=? AND open_key IS NOT NULL"
            )->execute([$cooldown, (int)$current['strike_id'], (int)$current['player_id']]);
            $this->db->prepare(
                "UPDATE employee_state
                    SET dispute_ticks=dispute_ticks+1,
                        morale=CASE WHEN morale-2 < 0 THEN 0 ELSE morale-2 END,
                        version=version+1, updated_at=CURRENT_TIMESTAMP
                  WHERE player_id=? AND relation_status='on_strike'
                    AND EXISTS (
                        SELECT 1 FROM employee_strike_members sm
                         WHERE sm.player_id=employee_state.player_id
                           AND sm.source_type=employee_state.source_type
                           AND sm.source_id=employee_state.source_id
                           AND sm.strike_id=? AND sm.left_at IS NULL
                    )"
            )->execute([(int)$current['player_id'], (int)$current['strike_id']]);
            $this->strikeEvent(
                (int)$current['player_id'],
                (int)$current['strike_id'],
                'negotiation_expired',
                'hr.event.negotiation_expired.title',
                'hr.event.negotiation_expired.message',
                ['cooldown_until' => $cooldown],
                'negotiation-expired:' . (int)$current['id']
            );
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

    /** @param array<string,mixed> $row */
    private function expireRaiseRequestRecord(array $row, DateTimeInterface $now): bool
    {
        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $lock = $this->db->prepare(
                "SELECT id, player_id, source_type, source_id
                   FROM employee_raise_requests
                  WHERE id=? AND player_id=? AND status IN ('open','postponed') AND deadline_at<?
                  LIMIT 1{$suffix}"
            );
            $lock->execute([(int)$row['id'], (int)$row['player_id'], $now->format('Y-m-d H:i:s')]);
            $current = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($current)) {
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return false;
            }
            $this->db->prepare(
                "UPDATE employee_raise_requests SET status='expired', resolved_at=CURRENT_TIMESTAMP,
                        updated_at=CURRENT_TIMESTAMP
                  WHERE id=? AND player_id=? AND status IN ('open','postponed')"
            )->execute([(int)$current['id'], (int)$current['player_id']]);
            $penalty = $this->config->getFloat('raise_postpone_morale_penalty');
            $this->db->prepare(
                "UPDATE employee_state SET relation_status='dispute', dispute_ticks=dispute_ticks+1,
                        morale=CASE WHEN morale-:penalty_guard<0 THEN 0 ELSE morale-:penalty_value END,
                        leave_risk=CASE WHEN leave_risk+5>100 THEN 100 ELSE leave_risk+5 END,
                        strike_support=CASE WHEN strike_support+5>100 THEN 100 ELSE strike_support+5 END,
                        version=version+1, updated_at=CURRENT_TIMESTAMP
                  WHERE player_id=:player_id AND source_type=:source_type AND source_id=:source_id
                    AND relation_status='raise_requested'"
            )->execute([
                'penalty_guard' => $penalty,
                'penalty_value' => $penalty,
                'player_id' => (int)$current['player_id'],
                'source_type' => (string)$current['source_type'],
                'source_id' => (int)$current['source_id'],
            ]);
            $this->event(
                $current,
                'raise_request_expired',
                'hr.event.raise_expired.title',
                'hr.event.raise_expired.message',
                ['request_id' => (int)$current['id']],
                'raise-expired:' . (int)$current['player_id'] . ':' . (int)$current['id']
            );
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

    /** @return list<array<string,mixed>> */
    public function activeForPlayer(int $playerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*,
                    COALESCE(m.member_count, 0) AS member_count,
                    COALESCE(m.avg_morale, 0) AS avg_morale,
                    COALESCE(m.avg_satisfaction, 0) AS avg_satisfaction,
                    n.id AS negotiation_id,
                    n.status AS negotiation_status,
                    n.current_round,
                    n.max_rounds,
                    n.round_deadline_at
               FROM employee_strikes s
               LEFT JOIN (
                    SELECT sm.player_id, sm.strike_id, COUNT(*) AS member_count,
                           AVG(es.morale) AS avg_morale,
                           AVG(es.salary_satisfaction) AS avg_satisfaction
                      FROM employee_strike_members sm
                      JOIN employee_state es ON es.player_id=sm.player_id
                       AND es.source_type=sm.source_type AND es.source_id=sm.source_id
                     WHERE sm.left_at IS NULL
                     GROUP BY sm.player_id, sm.strike_id
               ) m ON m.player_id=s.player_id AND m.strike_id=s.id
               LEFT JOIN employee_strike_negotiations n
                 ON n.player_id=s.player_id AND n.strike_id=s.id
              WHERE s.player_id=? AND s.open_key IS NOT NULL
              ORDER BY s.created_at DESC"
        );
        $stmt->execute([$playerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string,mixed>> */
    public function members(int $playerId, int $strikeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT sm.*, es.morale, es.salary_satisfaction, es.workload
               FROM employee_strike_members sm
               JOIN employee_state es ON es.player_id=sm.player_id
                AND es.source_type=sm.source_type AND es.source_id=sm.source_id
              WHERE sm.strike_id=? AND sm.player_id=? AND sm.left_at IS NULL
              ORDER BY sm.id'
        );
        $stmt->execute([$strikeId, $playerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function closeByAgreement(int $playerId, int $strikeId, float $moraleGain): void
    {
        $members = $this->members($playerId, $strikeId);
        $stmt = $this->db->prepare(
            "UPDATE employee_strikes SET status='resolved', open_key=NULL,
                    resolved_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP
              WHERE id=? AND player_id=? AND open_key IS NOT NULL"
        );
        $stmt->execute([$strikeId, $playerId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Open strike does not exist for this player.');
        }
        $state = $this->db->prepare(
            "UPDATE employee_state SET relation_status='normal',
                    morale=CASE WHEN morale+:gain > 100 THEN 100 ELSE morale+:gain END, dispute_ticks=0,
                    low_morale_streak=0, version=version+1, updated_at=CURRENT_TIMESTAMP
              WHERE player_id=:player_id AND source_type=:source_type AND source_id=:source_id
                AND relation_status='on_strike'"
        );
        $memberClose = $this->db->prepare(
            'UPDATE employee_strike_members SET left_at=CURRENT_TIMESTAMP
              WHERE strike_id=? AND player_id=? AND source_type=? AND source_id=? AND left_at IS NULL'
        );
        foreach ($members as $member) {
            $state->execute([
                'gain'=>$moraleGain,
                'player_id'=>$playerId,
                'source_type'=>(string)$member['source_type'],
                'source_id'=>(int)$member['source_id'],
            ]);
            $memberClose->execute([
                $strikeId,$playerId,(string)$member['source_type'],(int)$member['source_id'],
            ]);
        }
    }

    /** @return array{strike_id:int,member_count:int,status:string} */
    public function forceActiveForTesting(
        int $playerId,
        string $department,
        ?DateTimeInterface $now = null,
        float $support = 80.0
    ): array {
        // Admin-only test hook: creates a real active strike using the canonical tables.
        // Hak testowy admina: tworzy prawdziwy aktywny strajk w kanonicznych tabelach.
        $department = trim($department);
        if ($playerId <= 0 || !in_array($department, ['hr', 'technical', 'finance', 'legal', 'logistics'], true)) {
            throw new InvalidArgumentException('Invalid player or department for test strike.');
        }
        $support = max(50.0, min(100.0, $support));
        $now ??= new DateTimeImmutable();
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) {
            $this->db->beginTransaction();
        }
        try {
            $player = $this->db->prepare('SELECT id FROM players WHERE id=? LIMIT 1');
            $player->execute([$playerId]);
            if ((int)($player->fetchColumn() ?: 0) !== $playerId) {
                throw new RuntimeException('Player does not exist.');
            }
            $states = $this->eligibleStatesForTestStrike($playerId, $department);
            if ($states === []) {
                throw new RuntimeException('No active employees in this department.');
            }

            $openKey = $playerId . ':' . $department;
            $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $insert = $driver === 'sqlite'
                ? "INSERT INTO employee_strikes
                    (player_id, department_code, status, open_key, support_pct, threat_cycles, started_at)
                   VALUES (?, ?, 'active', ?, ?, 1, ?)
                   ON CONFLICT(open_key) DO UPDATE SET
                     status='active', support_pct=excluded.support_pct, threat_cycles=1,
                     started_at=COALESCE(employee_strikes.started_at, excluded.started_at)"
                : "INSERT INTO employee_strikes
                    (player_id, department_code, status, open_key, support_pct, threat_cycles, started_at)
                   VALUES (?, ?, 'active', ?, ?, 1, ?)
                   ON DUPLICATE KEY UPDATE
                     status='active', support_pct=VALUES(support_pct), threat_cycles=1,
                     started_at=COALESCE(started_at, VALUES(started_at)),
                     updated_at=CURRENT_TIMESTAMP";
            $this->db->prepare($insert)->execute([
                $playerId,
                $department,
                $openKey,
                $support,
                $now->format('Y-m-d H:i:s'),
            ]);
            $strikeStmt = $this->db->prepare(
                "SELECT id, status FROM employee_strikes
                  WHERE player_id=? AND open_key=? AND status IN ('active','negotiating') LIMIT 1"
            );
            $strikeStmt->execute([$playerId, $openKey]);
            $strike = $strikeStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($strike)) {
                throw new RuntimeException('Test strike could not be created.');
            }
            $strikeId = (int)$strike['id'];
            $memberInsert = $driver === 'sqlite'
                ? "INSERT INTO employee_strike_members
                    (strike_id, player_id, source_type, source_id, support_pct)
                   VALUES (?, ?, ?, ?, ?) ON CONFLICT(strike_id, source_type, source_id) DO NOTHING"
                : "INSERT IGNORE INTO employee_strike_members
                    (strike_id, player_id, source_type, source_id, support_pct) VALUES (?, ?, ?, ?, ?)";
            $stateUpdate = $this->db->prepare(
                "UPDATE employee_state
                    SET relation_status='on_strike',
                        morale=CASE WHEN morale > 30 THEN 30 ELSE morale END,
                        salary_satisfaction=CASE WHEN salary_satisfaction > 60 THEN 60 ELSE salary_satisfaction END,
                        workload=CASE WHEN workload < 80 THEN 80 ELSE workload END,
                        strike_support=CASE WHEN strike_support < :support THEN :support ELSE strike_support END,
                        version=version+1,
                        updated_at=CURRENT_TIMESTAMP
                  WHERE id=:id AND player_id=:player_id AND source_type=:source_type AND source_id=:source_id"
            );
            foreach ($states as $state) {
                $this->db->prepare($memberInsert)->execute([
                    $strikeId,
                    $playerId,
                    (string)$state['source_type'],
                    (int)$state['source_id'],
                    $support,
                ]);
                $stateUpdate->execute([
                    'support' => $support,
                    'id' => (int)$state['id'],
                    'player_id' => $playerId,
                    'source_type' => (string)$state['source_type'],
                    'source_id' => (int)$state['source_id'],
                ]);
            }
            if ($ownTx) {
                $this->db->commit();
            }
            return [
                'strike_id' => $strikeId,
                'member_count' => count($states),
                'status' => (string)$strike['status'],
            ];
        } catch (Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string,mixed>> */
    private function eligibleStatesForTestStrike(int $playerId, string $department): array
    {
        $stmt = $this->db->prepare(
            "SELECT es.*
               FROM employee_state es
          LEFT JOIN technical_staff ts
                 ON es.source_type='technical_staff' AND ts.id=es.source_id AND ts.player_id=es.player_id
          LEFT JOIN board_members bm
                 ON es.source_type='board_member' AND bm.id=es.source_id AND bm.player_id=es.player_id
              WHERE es.player_id=? AND es.department_code=?
                AND es.relation_status NOT IN ('inactive','leaving')
                AND ((es.source_type='technical_staff' AND ts.status IN ('active','busy'))
                  OR (es.source_type='board_member' AND bm.status='active'))
              ORDER BY es.id"
        );
        $stmt->execute([$playerId, $department]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function expireRaiseRequests(DateTimeInterface $now): int
    {
        $stmt = $this->db->prepare(
            "SELECT id, player_id, source_type, source_id FROM employee_raise_requests
              WHERE status IN ('open','postponed') AND deadline_at < ?"
        );
        $stmt->execute([$now->format('Y-m-d H:i:s')]);
        $expired = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $expired += $this->expireRaiseRequestRecord($row, $now) ? 1 : 0;
        }
        return $expired;
    }

    private function createRaiseRequests(DateTimeInterface $now): int
    {
        $stmt = $this->db->prepare(
            "SELECT es.*,
                    COALESCE(ts.salary, bm.salary) AS current_salary
               FROM employee_state es
               LEFT JOIN technical_staff ts
                 ON es.source_type='technical_staff' AND ts.id=es.source_id AND ts.player_id=es.player_id
               LEFT JOIN board_members bm
                 ON es.source_type='board_member' AND bm.id=es.source_id AND bm.player_id=es.player_id
              WHERE es.relation_status='raise_requested'
                AND COALESCE(ts.salary, bm.salary) IS NOT NULL
                 AND ((es.source_type='technical_staff' AND ts.status IN ('active','busy'))
                   OR (es.source_type='board_member' AND bm.status='active'))
                 AND NOT EXISTS (
                    SELECT 1 FROM employee_raise_requests rr
                     WHERE rr.player_id=es.player_id AND rr.source_type=es.source_type
                       AND rr.source_id=es.source_id AND rr.status IN ('open','postponed')
                )
                AND (es.last_raise_request_at IS NULL OR es.last_raise_request_at < ?)"
        );
        $cooldown = $now->getTimestamp() - $this->config->getInt('raise_cooldown_hours') * 3600;
        $stmt->execute([date('Y-m-d H:i:s', $cooldown)]);
        $deadline = date('Y-m-d H:i:s', $now->getTimestamp() + $this->config->getInt('raise_response_hours') * 3600);
        $created = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $state) {
            $currentSalary = round(max(0.0, (float)$state['current_salary']), 2);
            $requestedSalary = round($currentSalary * 1.10, 2);
            $numberStmt = $this->db->prepare(
                'SELECT COALESCE(MAX(request_no),0)+1 FROM employee_raise_requests
                  WHERE player_id=? AND source_type=? AND source_id=?'
            );
            $numberStmt->execute([(int)$state['player_id'],(string)$state['source_type'],(int)$state['source_id']]);
            $requestNo = (int)$numberStmt->fetchColumn();
            $insert = $this->db->prepare(
                "INSERT INTO employee_raise_requests
                    (player_id, source_type, source_id, request_no, current_salary, requested_salary,
                     requested_raise_pct, reason_code, postponed_count, status, deadline_at)
                 VALUES (?, ?, ?, ?, ?, ?, 10, 'low_morale', 0, 'open', ?)"
            );
            try {
                $insert->execute([
                    (int)$state['player_id'],(string)$state['source_type'],(int)$state['source_id'],$requestNo,
                    $currentSalary,$requestedSalary,$deadline,
                ]);
            } catch (PDOException $exception) {
                if ((string)$exception->getCode() !== '23000') {
                    throw $exception;
                }
                continue;
            }
            $this->db->prepare(
                'UPDATE employee_state SET last_raise_request_at=?, version=version+1
                  WHERE id=? AND player_id=? AND source_type=? AND source_id=?'
            )->execute([
                $now->format('Y-m-d H:i:s'),(int)$state['id'],(int)$state['player_id'],
                (string)$state['source_type'],(int)$state['source_id'],
            ]);
            $this->event($state, 'raise_requested', 'hr.event.raise.title', 'hr.event.raise.message', [
                'deadline'=>$deadline,
                'request_no'=>$requestNo,
                'current_salary'=>$currentSalary,
                'requested_salary'=>$requestedSalary,
            ], 'raise:' . (int)$state['id'] . ':' . $requestNo);
            $created++;
        }
        return $created;
    }

    /**
     * @param array<string,mixed> $department
     * @param array<string,int> $stats
     */
    private function evaluateDepartment(array $department, DateTimeInterface $now, array &$stats): void
    {
        $playerId = (int)$department['player_id'];
        $code = (string)$department['department_code'];
        $openKey = $playerId . ':' . $code;
        $stmt = $this->db->prepare('SELECT * FROM employee_strikes WHERE open_key=? AND player_id=? LIMIT 1');
        $stmt->execute([$openKey, $playerId]);
        $strike = $stmt->fetch(PDO::FETCH_ASSOC);
        $qualifies = (float)$department['avg_morale'] < $this->config->getFloat('threat_morale_threshold')
            && (int)$department['disputes'] >= $this->config->getInt('threat_min_disputes')
            && (float)$department['avg_support'] >= $this->config->getFloat('threat_support_threshold')
            && (float)$department['avg_workload'] >= 70.0;

        if (is_array($strike) && (string)$strike['status'] === 'threat' && !$qualifies) {
            $this->db->prepare(
                "UPDATE employee_strikes SET status='resolved', open_key=NULL, resolved_at=CURRENT_TIMESTAMP
                  WHERE id=? AND player_id=? AND status='threat'"
            )->execute([(int)$strike['id'],$playerId]);
            $this->db->prepare(
                "UPDATE employee_state SET relation_status='dispute', version=version+1
                  WHERE player_id=? AND department_code=? AND relation_status='strike_threat'"
            )->execute([$playerId,$code]);
            $stats['threats_closed']++;
            return;
        }
        if (!$qualifies || !$this->config->getBool('feature_threats')) {
            return;
        }
        if (!is_array($strike)) {
            $this->insertThreat($playerId, $code, $openKey, (float)$department['avg_support']);
            $stats['threats_started']++;
            return;
        }
        if ((string)$strike['status'] !== 'threat') {
            return;
        }
        $cycles = (int)$strike['threat_cycles'] + 1;
        $activate = $cycles >= $this->config->getInt('threat_cycles_required')
            && (float)$department['avg_support'] >= $this->config->getFloat('strike_support_threshold')
            && $this->config->getBool('feature_strikes');
        $this->db->prepare(
            'UPDATE employee_strikes SET threat_cycles=?, support_pct=?,
                    status=?, started_at=CASE WHEN ?=1 THEN ? ELSE started_at END,
                    updated_at=CURRENT_TIMESTAMP WHERE id=? AND player_id=?'
        )->execute([
            $cycles,(float)$department['avg_support'],$activate ? 'active' : 'threat',
            $activate ? 1 : 0,$now->format('Y-m-d H:i:s'),(int)$strike['id'],$playerId,
        ]);
        if ($activate) {
            $this->activateMembers($playerId, (int)$strike['id'], $code);
            $stats['strikes_started']++;
        }
    }

    private function insertThreat(int $playerId, string $department, string $openKey, float $support): void
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? "INSERT INTO employee_strikes
                (player_id, department_code, status, open_key, support_pct, threat_cycles)
               VALUES (?, ?, 'threat', ?, ?, 1) ON CONFLICT(open_key) DO NOTHING"
            : "INSERT IGNORE INTO employee_strikes
                (player_id, department_code, status, open_key, support_pct, threat_cycles)
               VALUES (?, ?, 'threat', ?, ?, 1)";
        $this->db->prepare($sql)->execute([$playerId,$department,$openKey,$support]);
        $this->db->prepare(
            "UPDATE employee_state SET relation_status='strike_threat', version=version+1
              WHERE player_id=? AND department_code=? AND relation_status='dispute'"
        )->execute([$playerId,$department]);
    }

    private function activateMembers(int $playerId, int $strikeId, string $department): void
    {
        $minimum = $this->config->getFloat('strike_member_support');
        $stmt = $this->db->prepare(
            "SELECT * FROM employee_state WHERE player_id=? AND department_code=?
              AND relation_status NOT IN ('inactive','leaving') AND strike_support>=?"
        );
        $stmt->execute([$playerId,$department,$minimum]);
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $state) {
            $sql = $driver === 'sqlite'
                ? "INSERT INTO employee_strike_members
                    (strike_id, player_id, source_type, source_id, support_pct)
                   VALUES (?, ?, ?, ?, ?) ON CONFLICT(strike_id, source_type, source_id) DO NOTHING"
                : "INSERT IGNORE INTO employee_strike_members
                    (strike_id, player_id, source_type, source_id, support_pct) VALUES (?, ?, ?, ?, ?)";
            $this->db->prepare($sql)->execute([
                $strikeId,$playerId,(string)$state['source_type'],(int)$state['source_id'],(float)$state['strike_support'],
            ]);
            $this->db->prepare(
                "UPDATE employee_state SET relation_status='on_strike', version=version+1
                  WHERE id=? AND player_id=? AND source_type=? AND source_id=?"
            )->execute([
                (int)$state['id'],$playerId,(string)$state['source_type'],(int)$state['source_id'],
            ]);
        }
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $meta
     */
    private function event(array $state, string $eventKey, string $titleKey, string $messageKey, array $meta, string $dedupe): void
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO employee_events
                (player_id, source_type, source_id, event_key, title_key, message_key, meta_json, dedupe_key)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON CONFLICT(dedupe_key) DO NOTHING'
            : 'INSERT IGNORE INTO employee_events
                (player_id, source_type, source_id, event_key, title_key, message_key, meta_json, dedupe_key)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $this->db->prepare($sql)->execute([
            (int)$state['player_id'],(string)$state['source_type'],(int)$state['source_id'],
            $eventKey,$titleKey,$messageKey,json_encode($meta, JSON_THROW_ON_ERROR),$dedupe,
        ]);
    }

    /** @param callable():void $callback */
    private function withCycleClaim(int $cycleId, int $playerId, string $department, callable $callback): bool
    {
        if ($cycleId <= 0) {
            $callback();
            return true;
        }
        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql = $driver === 'sqlite'
                ? 'INSERT OR IGNORE INTO employee_cycle_department_claims
                    (cycle_id, player_id, department_code) VALUES (?, ?, ?)'
                : 'INSERT IGNORE INTO employee_cycle_department_claims
                    (cycle_id, player_id, department_code) VALUES (?, ?, ?)';
            $claim = $this->db->prepare($sql);
            $claim->execute([$cycleId, $playerId, $department]);
            if ($claim->rowCount() !== 1) {
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return false;
            }
            $callback();
            $this->db->prepare(
                'UPDATE employee_cycle_department_claims SET completed_at=CURRENT_TIMESTAMP
                  WHERE cycle_id=? AND player_id=? AND department_code=?'
            )->execute([$cycleId, $playerId, $department]);
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

    /** @param array<string,mixed> $meta */
    private function strikeEvent(
        int $playerId,
        int $strikeId,
        string $eventKey,
        string $titleKey,
        string $messageKey,
        array $meta,
        string $dedupe
    ): void {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO employee_events
                (player_id, strike_id, event_key, title_key, message_key, meta_json, dedupe_key)
               VALUES (?, ?, ?, ?, ?, ?, ?)'
            : 'INSERT IGNORE INTO employee_events
                (player_id, strike_id, event_key, title_key, message_key, meta_json, dedupe_key)
               VALUES (?, ?, ?, ?, ?, ?, ?)';
        $this->db->prepare($sql)->execute([
            $playerId,
            $strikeId,
            $eventKey,
            $titleKey,
            $messageKey,
            json_encode($meta, JSON_THROW_ON_ERROR),
            $dedupe,
        ]);
    }
}
