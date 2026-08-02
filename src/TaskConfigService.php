<?php

class TaskConfigService
{
    private const EDITABLE_KEYS = ['cost_min', 'cost_max', 'hours_min', 'hours_max'];

    public static function tableExists(PDO $db): bool
    {
        try {
            $db->query("SELECT 1 FROM task_config LIMIT 1");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function createTableIfNeeded(PDO $db): void
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $db->exec("
            CREATE TABLE IF NOT EXISTS `task_config` (
              `task_type`   VARCHAR(64) NOT NULL,
              `config_key`  VARCHAR(32) NOT NULL,
              `config_value` BIGINT     NOT NULL DEFAULT 0,
              `updated_at`  TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              `updated_by`  VARCHAR(64)          DEFAULT NULL,
              PRIMARY KEY (`task_type`, `config_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Edytowalne parametry zadan technicznych — admin/tasks.php'
        ");
    }

    /** @return array<string, array<string, int>> task_type => [config_key => value] */
    public static function loadAll(PDO $db): array
    {
        $out = [];
        if (!self::tableExists($db)) {
            return $out;
        }
        try {
            $rows = $db->query(
                "SELECT task_type, config_key, config_value FROM task_config"
            )->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $out[(string)$row['task_type']][(string)$row['config_key']] = (int)$row['config_value'];
            }
            foreach ($out as $taskType => $values) {
                if (!array_key_exists('cost_min', $values) && !array_key_exists('cost_max', $values)) {
                    continue;
                }
                $costMin = (int)($values['cost_min'] ?? 0);
                $costMax = (int)($values['cost_max'] ?? 0);
                if ($costMin < 1 || $costMax < $costMin) {
                    unset($out[$taskType]['cost_min'], $out[$taskType]['cost_max']);
                }
            }
        } catch (Throwable $e) {
            GameLog::error('TaskConfigService', 'loadAll failed', $e);
        }
        return $out;
    }

    /** @param array<string,mixed> $values */
    public static function save(PDO $db, string $taskType, array $values, string $updatedBy): void
    {
        $stmt = $db->prepare("
            INSERT INTO task_config (task_type, config_key, config_value, updated_by)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value), updated_by = VALUES(updated_by)
        ");
        $costMin = max(1, (int)($values['cost_min'] ?? 1));
        $normalized = [
            'cost_min' => $costMin,
            'cost_max' => max($costMin, (int)($values['cost_max'] ?? $costMin)),
            'hours_min' => max(1, (int)($values['hours_min'] ?? 1)),
            'hours_max' => max(1, (int)($values['hours_max'] ?? 1)),
        ];
        foreach (self::EDITABLE_KEYS as $key) {
            if (array_key_exists($key, $values)) {
                $stmt->execute([$taskType, $key, $normalized[$key], $updatedBy]);
            }
        }
    }

    public static function resetToDefault(PDO $db, string $taskType): void
    {
        $db->prepare("DELETE FROM task_config WHERE task_type = ?")->execute([$taskType]);
    }

    /** @return list<string> */
    public static function editableKeys(): array
    {
        return self::EDITABLE_KEYS;
    }
}
