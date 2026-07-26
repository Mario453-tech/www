<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__) . '/Employee/EmployeeSystemConfigService.php';

final class EmployeeStrikeService
{
    private readonly EmployeeSystemConfigService $config;

    public function __construct(private readonly PDO $db)
    {
        EmployeeSystemBootstrap::ensure($db);
        $this->config = new EmployeeSystemConfigService($db);
    }

    /** @return array{raise_requests:int,threats_started:int,strikes_started:int,threats_closed:int} */
    public function processEscalations(DateTimeInterface $now): array
    {
        $stats = ['raise_requests'=>0,'threats_started'=>0,'strikes_started'=>0,'threats_closed'=>0];
        $this->expireRaiseRequests($now);
        $stats['raise_requests'] = $this->createRaiseRequests($now);
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
            $this->evaluateDepartment($department, $now, $stats);
        }
        return $stats;
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

    private function expireRaiseRequests(DateTimeInterface $now): void
    {
        $stmt = $this->db->prepare(
            "SELECT id, player_id, source_type, source_id FROM employee_raise_requests
              WHERE status IN ('open','postponed') AND deadline_at < ?"
        );
        $stmt->execute([$now->format('Y-m-d H:i:s')]);
        $expire = $this->db->prepare(
            "UPDATE employee_raise_requests SET status='expired', resolved_at=CURRENT_TIMESTAMP,
                    updated_at=CURRENT_TIMESTAMP
              WHERE id=? AND player_id=? AND status IN ('open','postponed')"
        );
        $stateUpdate = $this->db->prepare(
            "UPDATE employee_state SET relation_status='dispute', dispute_ticks=dispute_ticks+1,
                    version=version+1, updated_at=CURRENT_TIMESTAMP
              WHERE player_id=? AND source_type=? AND source_id=? AND relation_status='raise_requested'"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $expire->execute([(int)$row['id'], (int)$row['player_id']]);
            if ($expire->rowCount() !== 1) {
                continue;
            }
            $stateUpdate->execute([(int)$row['player_id'], (string)$row['source_type'], (int)$row['source_id']]);
            $this->event(
                $row,
                'raise_request_expired',
                'hr.event.raise_expired.title',
                'hr.event.raise_expired.message',
                ['request_id' => (int)$row['id']],
                'raise-expired:' . (int)$row['player_id'] . ':' . (int)$row['id']
            );
        }
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
}
