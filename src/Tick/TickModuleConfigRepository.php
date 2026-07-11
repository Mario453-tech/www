<?php
declare(strict_types=1);

require_once __DIR__ . '/TickModuleCatalog.php';

final class TickModuleConfigRepository
{
    public const STATUS_NEVER = 'never';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_DISABLED = 'disabled';
    public const STATUS_ERROR = 'error';

    public function __construct(private readonly PDO $db)
    {
        $this->ensureSchema();
    }

    /** @param list<TickModule> $modules */
    public function syncModules(array $modules): void
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'mysql'
            ? 'INSERT IGNORE INTO tick_module_config
                (module_key, enabled, interval_ticks, max_items_per_run, last_status)
               VALUES (?, 1, 1, ?, ?)'
            : 'INSERT OR IGNORE INTO tick_module_config
                (module_key, enabled, interval_ticks, max_items_per_run, last_status)
               VALUES (?, 1, 1, ?, ?)';
        $stmt = $this->db->prepare($sql);

        foreach ($modules as $module) {
            $stmt->execute([
                $module->key(),
                TickModuleCatalog::recommendedLimit($module->key()),
                self::STATUS_NEVER,
            ]);
        }
    }

    /** @return array<string,mixed>|null */
    public function find(string $moduleKey): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tick_module_config WHERE module_key = ? LIMIT 1');
        $stmt->execute([$moduleKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM tick_module_config ORDER BY module_key');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function update(string $moduleKey, bool $enabled, int $intervalTicks, int $maxItems): void
    {
        $stmt = $this->db->prepare(
            'UPDATE tick_module_config
                SET enabled = ?, interval_ticks = ?, max_items_per_run = ?, updated_at = CURRENT_TIMESTAMP
              WHERE module_key = ?'
        );
        $stmt->execute([
            $enabled ? 1 : 0,
            max(1, min(100000, $intervalTicks)),
            max(1, min(1000000, $maxItems)),
            $moduleKey,
        ]);
        if ($stmt->rowCount() === 0 && $this->find($moduleKey) === null) {
            throw new InvalidArgumentException("Unknown tick module: {$moduleKey}");
        }
    }

    public function restoreRecommended(string $moduleKey): void
    {
        $this->update(
            $moduleKey,
            true,
            TickModuleCatalog::recommendedInterval($moduleKey),
            TickModuleCatalog::recommendedLimit($moduleKey)
        );
    }

    public function markStarted(string $moduleKey, int $sequence, DateTimeInterface $now): void
    {
        $stmt = $this->db->prepare(
            'UPDATE tick_module_config
                SET last_run_at = ?, last_status = ?, last_error = NULL
              WHERE module_key = ?'
        );
        $stmt->execute([$now->format('Y-m-d H:i:s'), self::STATUS_RUNNING, $moduleKey]);
    }

    /** @param array<string,mixed> $stats */
    public function markFinished(
        string $moduleKey,
        int $sequence,
        string $source,
        string $status,
        int $durationMs,
        array $stats,
        ?string $error,
        bool $forced
    ): void {
        $error = $error !== null ? mb_substr($error, 0, 500) : null;
        if ($status === self::STATUS_SUCCESS) {
            $stmt = $this->db->prepare(
                'UPDATE tick_module_config
                    SET last_run_tick = ?, last_duration_ms = ?, last_status = ?, last_error = ?
                  WHERE module_key = ?'
            );
            $stmt->execute([$sequence, max(0, $durationMs), $status, $error, $moduleKey]);
        } else {
            $stmt = $this->db->prepare(
                'UPDATE tick_module_config
                    SET last_duration_ms = ?, last_status = ?, last_error = ?
                  WHERE module_key = ?'
            );
            $stmt->execute([max(0, $durationMs), $status, $error, $moduleKey]);
        }
        $this->insertLog($moduleKey, $sequence, $source, $status, $durationMs, $stats, $error, $forced);
    }

    public function markNotRun(string $moduleKey, int $sequence, string $source, string $status): void
    {
        $stmt = $this->db->prepare(
            'UPDATE tick_module_config SET last_status = ?, last_error = NULL WHERE module_key = ?'
        );
        $stmt->execute([$status, $moduleKey]);
    }

    /** @return list<array<string,mixed>> */
    public function logs(string $moduleKey, int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM tick_module_run_logs WHERE module_key = ? ORDER BY id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $moduleKey);
        $stmt->bindValue(2, max(1, min(200, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string,mixed> $stats */
    private function insertLog(
        string $moduleKey,
        int $sequence,
        string $source,
        string $status,
        int $durationMs,
        array $stats,
        ?string $error,
        bool $forced
    ): void {
        $json = json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $this->db->prepare(
            'INSERT INTO tick_module_run_logs
                (module_key, tick_sequence, source, status, duration_ms, stats_json, error_message, forced)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $moduleKey,
            max(0, $sequence),
            mb_substr($source, 0, 32),
            $status,
            max(0, $durationMs),
            $json === false ? '{}' : $json,
            $error,
            $forced ? 1 : 0,
        ]);
    }

    private function ensureSchema(): void
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $this->db->exec("CREATE TABLE IF NOT EXISTS tick_module_config (
                module_key VARCHAR(64) NOT NULL PRIMARY KEY,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                interval_ticks INT UNSIGNED NOT NULL DEFAULT 1,
                max_items_per_run INT UNSIGNED NOT NULL DEFAULT 200,
                last_run_tick BIGINT UNSIGNED NOT NULL DEFAULT 0,
                last_run_at DATETIME NULL,
                last_duration_ms INT UNSIGNED NULL,
                last_status VARCHAR(16) NOT NULL DEFAULT 'never',
                last_error VARCHAR(500) NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $this->db->exec("CREATE TABLE IF NOT EXISTS tick_module_run_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                module_key VARCHAR(64) NOT NULL,
                tick_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0,
                source VARCHAR(32) NOT NULL,
                status VARCHAR(16) NOT NULL,
                duration_ms INT UNSIGNED NOT NULL DEFAULT 0,
                stats_json JSON NULL,
                error_message VARCHAR(500) NULL,
                forced TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_tick_module_logs_key_created (module_key, created_at),
                KEY idx_tick_module_logs_sequence (tick_sequence)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            return;
        }

        $this->db->exec("CREATE TABLE IF NOT EXISTS tick_module_config (
            module_key TEXT PRIMARY KEY,
            enabled INTEGER NOT NULL DEFAULT 1,
            interval_ticks INTEGER NOT NULL DEFAULT 1,
            max_items_per_run INTEGER NOT NULL DEFAULT 200,
            last_run_tick INTEGER NOT NULL DEFAULT 0,
            last_run_at TEXT NULL,
            last_duration_ms INTEGER NULL,
            last_status TEXT NOT NULL DEFAULT 'never',
            last_error TEXT NULL,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $this->db->exec("CREATE TABLE IF NOT EXISTS tick_module_run_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key TEXT NOT NULL,
            tick_sequence INTEGER NOT NULL DEFAULT 0,
            source TEXT NOT NULL,
            status TEXT NOT NULL,
            duration_ms INTEGER NOT NULL DEFAULT 0,
            stats_json TEXT NULL,
            error_message TEXT NULL,
            forced INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
    }
}
