<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__) . '/Employee/EmployeeRef.php';
require_once dirname(__DIR__) . '/Employee/EmployeeStateService.php';

final class EmployeeLegacyMigrationService
{
    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array<string,mixed> */
    public function run(bool $apply = false, ?int $playerId = null): array
    {
        if ($playerId !== null && $playerId <= 0) {
            throw new InvalidArgumentException('Player id must be positive.');
        }
        $stateService = new EmployeeStateService($this->db, new EmployeeRepository($this->db), false);
        $preflight = $stateService->backfillEmployeeState(false, $playerId);
        if ($preflight['errors'] !== []) {
            throw new RuntimeException('Employee state preflight failed.');
        }
        if ($apply) {
            $this->db->beginTransaction();
        }
        try {
            $stateReport = $apply ? $stateService->backfillEmployeeState(true, $playerId) : $preflight;
            if ($stateReport['errors'] !== []) {
                throw new RuntimeException('Employee state backfill failed.');
            }
            if ($apply) {
                $verification = $stateService->backfillEmployeeState(false, $playerId);
                if ($verification['errors'] !== [] || (int)$verification['would_create'] !== 0) {
                    throw new RuntimeException('Canonical employee state verification failed after backfill.');
                }
            }
            $report = [
                'applied'=>$apply,
                'player_id'=>$playerId,
                'state_backfill'=>$stateReport,
                'morale_checked'=>0,
                'morale_would_copy'=>0,
                'morale_copied'=>0,
                'morale_preserved'=>0,
                'active_strike_groups'=>0,
                'strikes_created'=>0,
                'members_created'=>0,
                'ambiguities'=>[],
            ];
            $this->migrateMorale($report, $apply, $playerId);
            $this->migrateActiveStrikes($report, $apply, $playerId);
            if ($apply) {
                $this->db->commit();
            }
        } catch (Throwable $exception) {
            if ($apply && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
        return $report;
    }

    /** @param array<string,mixed> $report */
    private function migrateMorale(array &$report, bool $apply, ?int $playerId): void
    {
        if (!$this->hasColumn('technical_staff', 'current_morale')) {
            return;
        }
        $hasLogs = $this->hasTable('staff_morale_logs');
        $logJoin = $hasLogs
            ? 'LEFT JOIN (SELECT technical_staff_id, MAX(created_at) AS last_log_at FROM staff_morale_logs GROUP BY technical_staff_id) ml ON ml.technical_staff_id=ts.id'
            : '';
        $sql = "SELECT ts.id, ts.player_id, ts.current_morale, es.id AS state_id, es.morale,
                       es.version, es.last_morale_tick_at, es.updated_at" .
            ($hasLogs ? ', ml.last_log_at' : ', NULL AS last_log_at') .
            " FROM technical_staff ts
               JOIN employee_state es ON es.player_id=ts.player_id
                AND es.source_type='technical_staff' AND es.source_id=ts.id
               {$logJoin}";
        $params = [];
        if ($playerId !== null) {
            $sql .= ' WHERE ts.player_id = ?';
            $params[] = $playerId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $report['morale_checked']++;
            $legacy = max(0.0, min(100.0, (float)$row['current_morale']));
            $canonical = (float)$row['morale'];
            $pristine = (int)$row['version'] <= 1 && abs($canonical - 65.0) < 0.001;
            $legacyChanged = abs($legacy - 100.0) > 0.001;
            $legacyTime = strtotime((string)($row['last_log_at'] ?? '')) ?: 0;
            $stateTime = max(
                strtotime((string)($row['last_morale_tick_at'] ?? '')) ?: 0,
                strtotime((string)($row['updated_at'] ?? '')) ?: 0
            );
            $copy = ($pristine && $legacyChanged) || ($legacyTime > 0 && $legacyTime > $stateTime);
            if (!$copy) {
                $report['morale_preserved']++;
                continue;
            }
            $report['morale_would_copy']++;
            if (!$apply) {
                continue;
            }
            $update = $this->db->prepare(
                "UPDATE employee_state SET morale=?, version=version+1, updated_at=CURRENT_TIMESTAMP
                  WHERE id=? AND player_id=? AND source_type='technical_staff' AND source_id=?"
            );
            $update->execute([$legacy, (int)$row['state_id'], (int)$row['player_id'], (int)$row['id']]);
            if ($update->rowCount() === 1) {
                $report['morale_copied']++;
                $this->insertMigrationEvent((int)$row['player_id'], (int)$row['id'], 'legacy_morale', [
                    'legacy_morale'=>$legacy,
                    'previous_morale'=>$canonical,
                ]);
            }
        }
    }

    /** @param array<string,mixed> $report */
    private function migrateActiveStrikes(array &$report, bool $apply, ?int $playerId): void
    {
        if (!$this->hasTable('staff_strikes')) {
            return;
        }
        $sql = "SELECT ts.player_id, ts.id AS staff_id, MIN(ss.start_time) AS started_at
                  FROM staff_strikes ss
                  JOIN technical_staff ts ON ts.id=ss.technical_staff_id
                 WHERE ss.end_time IS NULL";
        $params = [];
        if ($playerId !== null) {
            $sql .= ' AND ts.player_id=?';
            $params[] = $playerId;
        }
        $sql .= ' GROUP BY ts.player_id, ts.id ORDER BY ts.player_id, ts.id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $groups = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $groups[(int)$row['player_id']][] = $row;
        }
        foreach ($groups as $ownerId => $members) {
            $report['active_strike_groups']++;
            if (!$apply) {
                continue;
            }
            $openKey = $ownerId . ':technical';
            $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $insertSql = $driver === 'sqlite'
                ? "INSERT INTO employee_strikes
                    (player_id, department_code, status, open_key, support_pct, started_at)
                   VALUES (?, 'technical', 'active', ?, 100, ?) ON CONFLICT(open_key) DO NOTHING"
                : "INSERT IGNORE INTO employee_strikes
                    (player_id, department_code, status, open_key, support_pct, started_at)
                   VALUES (?, 'technical', 'active', ?, 100, ?)";
            $startedAt = min(array_map(static fn(array $row): string => (string)$row['started_at'], $members));
            $insert = $this->db->prepare($insertSql);
            $insert->execute([$ownerId, $openKey, $startedAt]);
            $report['strikes_created'] += $insert->rowCount() === 1 ? 1 : 0;
            $strikeStmt = $this->db->prepare('SELECT id FROM employee_strikes WHERE open_key=? AND player_id=? LIMIT 1');
            $strikeStmt->execute([$openKey, $ownerId]);
            $strikeId = (int)($strikeStmt->fetchColumn() ?: 0);
            foreach ($members as $member) {
                $memberSql = $driver === 'sqlite'
                    ? "INSERT INTO employee_strike_members
                        (strike_id, player_id, source_type, source_id, support_pct)
                       VALUES (?, ?, 'technical_staff', ?, 100)
                       ON CONFLICT(strike_id, source_type, source_id) DO NOTHING"
                    : "INSERT IGNORE INTO employee_strike_members
                        (strike_id, player_id, source_type, source_id, support_pct)
                       VALUES (?, ?, 'technical_staff', ?, 100)";
                $memberStmt = $this->db->prepare($memberSql);
                $memberStmt->execute([$strikeId, $ownerId, (int)$member['staff_id']]);
                $report['members_created'] += $memberStmt->rowCount() === 1 ? 1 : 0;
                $state = $this->db->prepare(
                    "UPDATE employee_state SET relation_status='on_strike', version=version+1
                      WHERE player_id=? AND source_type='technical_staff' AND source_id=?"
                );
                $state->execute([$ownerId, (int)$member['staff_id']]);
            }
        }
    }

    /** @param array<string,mixed> $meta */
    private function insertMigrationEvent(int $playerId, int $sourceId, string $key, array $meta): void
    {
        $dedupe = 'employee_migration:' . $key . ':' . $playerId . ':technical_staff:' . $sourceId;
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? "INSERT INTO employee_events
                (player_id, source_type, source_id, event_key, title_key, message_key, meta_json, dedupe_key)
               VALUES (?, 'technical_staff', ?, 'legacy_migrated', 'hr.event.migration.title',
                       'hr.event.migration.message', ?, ?) ON CONFLICT(dedupe_key) DO NOTHING"
            : "INSERT IGNORE INTO employee_events
                (player_id, source_type, source_id, event_key, title_key, message_key, meta_json, dedupe_key)
               VALUES (?, 'technical_staff', ?, 'legacy_migrated', 'hr.event.migration.title',
                       'hr.event.migration.message', ?, ?)";
        $this->db->prepare($sql)->execute([
            $playerId,
            $sourceId,
            json_encode($meta, JSON_THROW_ON_ERROR),
            $dedupe,
        ]);
    }

    private function hasTable(string $table): bool
    {
        try {
            $this->db->query("SELECT 1 FROM {$table} WHERE 1=0");
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $rows = $this->db->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
            return in_array($column, array_column($rows, 'name'), true);
        }
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
