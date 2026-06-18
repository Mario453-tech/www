<?php

declare(strict_types=1);

/**
 * AdminPlayerDeletionService - irreversible player purge used by admin tools.
 * PL: Serwis nieodwracalnego usuwania gracza z panelu admina.
 */
class AdminPlayerDeletionService
{
    private PDO $db;

    /** @var array<string, array<int, string>> */
    private array $columnsByTable = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /**
     * Purges multiple players and their related game data.
     * PL: Usuwa wielu graczy i ich powiazane dane gry.
     *
     * @param int[] $playerIds
     * @return array{deleted:int, requested:int, missing:int, ids:int[]}
     */
    public function purgeMany(array $playerIds): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $playerIds),
            static fn(int $id): bool => $id > 0
        )));
        if ($ids === []) {
            return ['deleted' => 0, 'requested' => 0, 'missing' => 0, 'ids' => []];
        }

        $existing = $this->existingPlayerIds($ids);
        if ($existing === []) {
            return ['deleted' => 0, 'requested' => count($ids), 'missing' => count($ids), 'ids' => []];
        }

        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $foreignKeysDisabled = false;

        $this->db->beginTransaction();
        try {
            if ($driver !== 'sqlite') {
                $this->db->exec('SET FOREIGN_KEY_CHECKS=0');
                $foreignKeysDisabled = true;
            }

            foreach ($existing as $playerId) {
                $this->purgeOneInsideTransaction($playerId);
            }

            if ($foreignKeysDisabled) {
                $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
                $foreignKeysDisabled = false;
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($foreignKeysDisabled) {
                try {
                    $this->db->exec('SET FOREIGN_KEY_CHECKS=1');
                } catch (Throwable) {
                }
            }
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('AdminPlayerDeletionService', 'purgeMany FAILED', $e, ['ids' => $existing]);
            throw $e;
        }

        return [
            'deleted' => count($existing),
            'requested' => count($ids),
            'missing' => count($ids) - count($existing),
            'ids' => $existing,
        ];
    }

    /**
     * Deletes one player while an outer transaction is already open.
     * PL: Usuwa jednego gracza wewnatrz otwartej transakcji.
     */
    private function purgeOneInsideTransaction(int $playerId): void
    {
        $wellIds = $this->idsFrom('wells', 'id', 'player_id', $playerId);
        $ownedHubIds = $this->idsFrom('logistics_hubs', 'id', 'player_id', $playerId);
        $rentedHubIds = $this->idsFrom('logistics_hubs', 'id', 'tenant_player_id', $playerId);
        $hubIds = array_values(array_unique(array_merge($ownedHubIds, $rentedHubIds)));
        $pipelineIds = array_values(array_unique(array_merge(
            $this->idsFrom('well_pipelines', 'id', 'player_id', $playerId),
            $this->idsFrom('well_pipelines', 'id', 'well_id', $wellIds),
            $this->idsFrom('well_pipelines', 'id', 'hub_id', $hubIds)
        )));
        $loanIds = $this->idsFrom('loans', 'id', 'player_id', $playerId);
        $negotiationIds = $this->idsFrom('bank_negotiations', 'id', 'player_id', $playerId);
        $messageIds = array_values(array_unique(array_merge(
            $this->idsFrom('chat_messages', 'id', 'sender_id', $playerId),
            $this->idsFrom('chat_messages', 'id', 'receiver_id', $playerId)
        )));
        $sabotageAttemptIds = array_values(array_unique(array_merge(
            $this->idsFrom('sabotage_attempts', 'id', 'player_id', $playerId),
            $this->idsFrom('sabotage_attempts', 'id', 'target_player_id', $playerId)
        )));
        $staffIds = $this->idsFrom('technical_staff', 'id', 'player_id', $playerId);
        $memberIds = $this->idsFrom('board_members', 'id', 'player_id', $playerId);

        $this->deleteChatAttachmentFiles($messageIds);

        $this->deleteByColumn('chat_reports', 'message_id', $messageIds);
        $this->deleteByColumn('bank_negotiation_events', 'negotiation_id', $negotiationIds);
        $this->deleteByColumn('loan_payments', 'loan_id', $loanIds);
        $this->deleteByColumn('bailiff_proceedings', 'loan_id', $loanIds);
        $this->deleteByColumn('sabotage_logs', 'sabotage_attempt_id', $sabotageAttemptIds);

        foreach (['industrial_disasters', 'technical_task_queue', 'technical_tasks', 'well_pipeline_events', 'well_pipeline_tick_stats'] as $table) {
            $this->deleteByColumn($table, 'pipeline_id', $pipelineIds);
        }

        foreach (['hub_road_trips', 'logistics_hub_assignments', 'logistics_hub_events', 'logistics_hub_tick_stats', 'marine_deliveries', 'technical_task_queue', 'technical_tasks', 'well_pipelines', 'well_road_trips'] as $table) {
            $this->deleteByColumn($table, 'hub_id', $hubIds);
        }

        foreach (['failure_log', 'industrial_disasters', 'logistics_hub_assignments', 'logistics_hub_events', 'marine_deliveries', 'technical_notifications', 'technical_task_queue', 'technical_tasks', 'well_events', 'well_incidents', 'well_offshore_configs', 'well_offshore_incident_logs', 'well_pipeline_events', 'well_pipeline_tick_stats', 'well_pipelines', 'well_road_configs', 'well_road_incident_logs', 'well_road_trips', 'well_staff_assignments', 'well_upgrades'] as $table) {
            $this->deleteByColumn($table, 'well_id', $wellIds);
        }

        foreach (['technical_task_queue', 'technical_tasks', 'well_staff_assignments'] as $table) {
            $this->deleteByColumn($table, 'staff_id', $staffIds);
        }

        foreach (['employee_certificates', 'employee_contracts', 'employment_history', 'hr_events'] as $table) {
            $this->deleteByColumn($table, 'member_id', $memberIds);
        }

        $this->releaseRentedHubs($playerId);

        $this->deleteDynamicByColumn('player_id', [$playerId], ['players']);
        $this->deleteDynamicByColumn('from_player_id', [$playerId]);
        $this->deleteDynamicByColumn('to_player_id', [$playerId]);
        $this->deleteDynamicByColumn('sender_id', [$playerId]);
        $this->deleteDynamicByColumn('receiver_id', [$playerId]);
        $this->deleteDynamicByColumn('reporter_id', [$playerId]);
        $this->deleteDynamicByColumn('target_player_id', [$playerId]);

        $this->deleteByColumn('logistics_hubs', 'player_id', [$playerId]);
        $this->deleteByColumn('players', 'id', [$playerId]);
    }

    /** @param int[] $ids */
    private function deleteChatAttachmentFiles(array $ids): void
    {
        if ($ids === [] || !$this->tableHasColumn('chat_messages', 'attachment_path')) {
            return;
        }
        $rows = $this->selectColumnValues('chat_messages', 'attachment_path', 'id', $ids);
        foreach ($rows as $path) {
            $path = (string)$path;
            if ($path === '' || str_contains($path, '..')) {
                continue;
            }
            $full = dirname(__DIR__) . '/' . ltrim($path, '/\\');
            $filePath = realpath($full) ?: '';
            $uploadBase = realpath(dirname(__DIR__) . '/assets/uploads') ?: '';
            if (is_file($full) && $filePath !== '' && $uploadBase !== '' && str_starts_with($filePath, $uploadBase . DIRECTORY_SEPARATOR)) {
                @unlink($full);
            }
        }
    }

    private function releaseRentedHubs(int $playerId): void
    {
        if (!$this->tableHasColumn('logistics_hubs', 'tenant_player_id')) {
            return;
        }
        $set = ['tenant_player_id = 0'];
        if ($this->tableHasColumn('logistics_hubs', 'buffer_current_bbl')) {
            $set[] = 'buffer_current_bbl = 0';
        }
        if ($this->tableHasColumn('logistics_hubs', 'updated_at')) {
            $set[] = 'updated_at = NOW()';
        }
        $this->db->prepare(
            'UPDATE logistics_hubs SET ' . implode(', ', $set) . ' WHERE tenant_player_id = ? AND player_id <> ?'
        )->execute([$playerId, $playerId]);
    }

    /**
     * @param int[] $ids
     * @param string[] $skipTables
     */
    private function deleteDynamicByColumn(string $column, array $ids, array $skipTables = []): void
    {
        foreach ($this->tablesWithColumn($column) as $table) {
            if (in_array($table, $skipTables, true)) {
                continue;
            }
            if ($table === 'logistics_hubs' && $column === 'tenant_player_id') {
                continue;
            }
            $this->deleteByColumn($table, $column, $ids);
        }
    }

    /**
     * @param int[]|int $values
     * @return int[]
     */
    private function idsFrom(string $table, string $idColumn, string $whereColumn, array|int $values): array
    {
        if (!$this->tableHasColumn($table, $idColumn) || !$this->tableHasColumn($table, $whereColumn)) {
            return [];
        }
        return array_map('intval', $this->selectColumnValues($table, $idColumn, $whereColumn, (array)$values));
    }

    /**
     * @param int[] $values
     * @return array<int, mixed>
     */
    private function selectColumnValues(string $table, string $selectColumn, string $whereColumn, array $values): array
    {
        $values = array_values(array_unique(array_filter(array_map('intval', $values), static fn(int $v): bool => $v > 0)));
        if ($values === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $stmt = $this->db->prepare("SELECT `{$selectColumn}` FROM `{$table}` WHERE `{$whereColumn}` IN ({$placeholders})");
        $stmt->execute($values);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @param int[] $values */
    private function deleteByColumn(string $table, string $column, array $values): int
    {
        if (!$this->tableHasColumn($table, $column)) {
            return 0;
        }
        $values = array_values(array_unique(array_filter(array_map('intval', $values), static fn(int $v): bool => $v > 0)));
        if ($values === []) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE `{$column}` IN ({$placeholders})");
        $stmt->execute($values);
        return $stmt->rowCount();
    }

    /**
     * @param int[] $ids
     * @return int[]
     */
    private function existingPlayerIds(array $ids): array
    {
        return array_map('intval', $this->selectColumnValues('players', 'id', 'id', $ids));
    }

    /** @return array<int, string> */
    private function tablesWithColumn(string $column): array
    {
        $out = [];
        foreach ($this->columnsByTable() as $table => $columns) {
            if (in_array($column, $columns, true)) {
                $out[] = $table;
            }
        }
        return $out;
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columnsByTable()[$table] ?? [], true);
    }

    /** @return array<string, array<int, string>> */
    private function columnsByTable(): array
    {
        if ($this->columnsByTable !== []) {
            return $this->columnsByTable;
        }
        $stmt = $this->db->query(
            "SELECT TABLE_NAME, COLUMN_NAME
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME IN (
                    SELECT TABLE_NAME
                      FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_TYPE = 'BASE TABLE'
                )"
        );
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $this->columnsByTable[(string)$row['TABLE_NAME']][] = (string)$row['COLUMN_NAME'];
        }
        return $this->columnsByTable;
    }
}
