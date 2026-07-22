<?php
declare(strict_types=1);

final class EmployeeSystemSchema
{
    public const VERSION = 2;

    public static function ensure(PDO $db): void
    {
        $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        self::createVersionTable($db, $driver);
        if (self::currentVersion($db) >= self::VERSION) {
            return;
        }
        foreach ($driver === 'sqlite' ? self::sqliteStatements() : self::mysqlStatements() as $sql) {
            $db->exec($sql);
        }
        self::ensureStateColumns($db, $driver);
        self::verify($db);
        self::storeVersion($db, $driver);
    }

    public static function currentVersion(PDO $db): int
    {
        try {
            $stmt = $db->prepare('SELECT version FROM employee_schema_versions WHERE module_key = ? LIMIT 1');
            $stmt->execute(['employee_system']);
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable) {
            return 0;
        }
    }

    private static function createVersionTable(PDO $db, string $driver): void
    {
        $id = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $suffix = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $db->exec("CREATE TABLE IF NOT EXISTS employee_schema_versions (
            id {$id}, module_key VARCHAR(80) NOT NULL, version INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE (module_key)
        ){$suffix}");
    }

    /** @return list<string> */
    private static function mysqlStatements(): array
    {
        $s = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        return [
            "CREATE TABLE IF NOT EXISTS employee_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, player_id INT UNSIGNED NOT NULL,
                source_type VARCHAR(32) NULL, source_id INT UNSIGNED NULL, strike_id BIGINT UNSIGNED NULL,
                event_key VARCHAR(100) NOT NULL, title_key VARCHAR(190) NOT NULL, message_key VARCHAR(190) NOT NULL,
                meta_json TEXT NULL, dialogue_template_id BIGINT UNSIGNED NULL, dedupe_key VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_employee_event_dedupe (dedupe_key),
                KEY idx_employee_event_player (player_id, created_at)
            ){$s}",
            "CREATE TABLE IF NOT EXISTS employee_raise_requests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, player_id INT UNSIGNED NOT NULL,
                source_type VARCHAR(32) NOT NULL, source_id INT UNSIGNED NOT NULL, request_no INT UNSIGNED NOT NULL DEFAULT 1,
                requested_raise_pct DECIMAL(7,4) NOT NULL DEFAULT 0, status VARCHAR(32) NOT NULL DEFAULT 'open',
                deadline_at DATETIME NULL, resolved_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_employee_raise_request (player_id, source_type, source_id, request_no)
            ){$s}",
            "CREATE TABLE IF NOT EXISTS employee_strikes (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, player_id INT UNSIGNED NOT NULL,
                department_code VARCHAR(50) NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'threat',
                open_key VARCHAR(100) NULL, support_pct DECIMAL(7,4) NOT NULL DEFAULT 0,
                threat_cycles INT UNSIGNED NOT NULL DEFAULT 0, negotiation_cooldown_until DATETIME NULL,
                started_at DATETIME NULL, resolved_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_employee_strike_open (open_key), KEY idx_employee_strike_player (player_id, department_code, status)
            ){$s}",
            "CREATE TABLE IF NOT EXISTS employee_strike_members (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, strike_id BIGINT UNSIGNED NOT NULL,
                player_id INT UNSIGNED NOT NULL, source_type VARCHAR(32) NOT NULL, source_id INT UNSIGNED NOT NULL,
                support_pct DECIMAL(7,4) NOT NULL DEFAULT 0, joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                left_at DATETIME NULL, UNIQUE KEY uq_employee_strike_member (strike_id, source_type, source_id)
            ){$s}",
            "CREATE TABLE IF NOT EXISTS employee_strike_negotiations (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, strike_id BIGINT UNSIGNED NOT NULL,
                player_id INT UNSIGNED NOT NULL, status VARCHAR(32) NOT NULL DEFAULT 'open',
                current_round INT UNSIGNED NOT NULL DEFAULT 1, max_rounds INT UNSIGNED NOT NULL DEFAULT 3,
                round_deadline_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_employee_strike_negotiation (strike_id)
            ){$s}",
            "CREATE TABLE IF NOT EXISTS employee_strike_negotiation_rounds (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, negotiation_id BIGINT UNSIGNED NOT NULL,
                strike_id BIGINT UNSIGNED NOT NULL, player_id INT UNSIGNED NOT NULL, round_no INT UNSIGNED NOT NULL,
                idempotency_token VARCHAR(128) NOT NULL, raise_pct DECIMAL(7,4) NOT NULL DEFAULT 0,
                bonus_per_member DECIMAL(14,2) NOT NULL DEFAULT 0, counter_raise_pct DECIMAL(7,4) NULL,
                counter_bonus_per_member DECIMAL(14,2) NULL, random_roll DECIMAL(7,4) NOT NULL,
                formula_json TEXT NOT NULL, dialogue_template_id BIGINT UNSIGNED NULL, result VARCHAR(32) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_employee_round_token (idempotency_token),
                UNIQUE KEY uq_employee_round_no (negotiation_id, round_no)
            ){$s}",
            "CREATE TABLE IF NOT EXISTS employee_dialogue_templates (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, seed_key VARCHAR(160) NULL,
                context_key VARCHAR(80) NOT NULL, department_code VARCHAR(50) NULL, round_no INT UNSIGNED NULL,
                tone VARCHAR(32) NOT NULL, text_pl TEXT NOT NULL, text_en TEXT NOT NULL,
                weight DECIMAL(8,3) NOT NULL DEFAULT 1, is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_employee_dialogue_seed (seed_key),
                KEY idx_employee_dialogue_lookup (context_key, department_code, round_no, tone, is_active)
            ){$s}",
            "CREATE TABLE IF NOT EXISTS employee_module_cycles (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, module_key VARCHAR(80) NOT NULL,
                cycle_key VARCHAR(100) NOT NULL, run_sequence BIGINT UNSIGNED NOT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'open', processed_count INT UNSIGNED NOT NULL DEFAULT 0,
                error_count INT UNSIGNED NOT NULL DEFAULT 0, started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME NULL, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_employee_module_cycle (module_key, cycle_key),
                KEY idx_employee_module_cycle_status (module_key, status, started_at)
            ){$s}",
            "CREATE TABLE IF NOT EXISTS employee_system_config (
                config_key VARCHAR(100) NOT NULL PRIMARY KEY, config_value VARCHAR(255) NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ){$s}",
        ];
    }

    /** @return list<string> */
    private static function sqliteStatements(): array
    {
        $statements = [];
        foreach (self::mysqlStatements() as $sql) {
            $sql = preg_replace('/BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            $sql = preg_replace('/\b(?:BIGINT|INT) UNSIGNED\b/', 'INTEGER', (string)$sql);
            $sql = preg_replace('/DECIMAL\(\d+,\d+\)/', 'REAL', (string)$sql);
            $sql = str_replace('TINYINT(1)', 'INTEGER', (string)$sql);
            $sql = preg_replace('/DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP/', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP', (string)$sql);
            $sql = preg_replace('/,\s*(?:UNIQUE KEY|KEY) [a-zA-Z0-9_]+ \([^)]+\)/', '', (string)$sql);
            $sql = preg_replace('/\) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci$/', ')', (string)$sql);
            $statements[] = (string)$sql;
        }
        return array_merge($statements, [
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_event_dedupe ON employee_events (dedupe_key)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_raise_request ON employee_raise_requests (player_id, source_type, source_id, request_no)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_strike_open ON employee_strikes (open_key)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_strike_member ON employee_strike_members (strike_id, source_type, source_id)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_strike_negotiation ON employee_strike_negotiations (strike_id)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_round_token ON employee_strike_negotiation_rounds (idempotency_token)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_round_no ON employee_strike_negotiation_rounds (negotiation_id, round_no)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_dialogue_seed ON employee_dialogue_templates (seed_key)',
            'CREATE INDEX IF NOT EXISTS idx_employee_dialogue_lookup ON employee_dialogue_templates (context_key, department_code, round_no, tone, is_active)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_module_cycle ON employee_module_cycles (module_key, cycle_key)',
        ]);
    }

    private static function ensureStateColumns(PDO $db, string $driver): void
    {
        $columns = self::columns($db, $driver);
        $defs = [
            'last_morale_tick_sequence' => $driver === 'sqlite' ? 'INTEGER NULL' : 'BIGINT UNSIGNED NULL',
            'last_morale_cycle_id' => $driver === 'sqlite' ? 'INTEGER NULL' : 'BIGINT UNSIGNED NULL',
            'low_morale_streak' => 'INT NOT NULL DEFAULT 0',
            'dispute_ticks' => 'INT NOT NULL DEFAULT 0',
        ];
        foreach ($defs as $name => $definition) {
            if (!isset($columns[$name])) {
                $db->exec("ALTER TABLE employee_state ADD COLUMN {$name} {$definition}");
            }
        }
    }

    /** @return array<string, true> */
    private static function columns(PDO $db, string $driver): array
    {
        if ($driver === 'sqlite') {
            $rows = $db->query('PRAGMA table_info(employee_state)')->fetchAll(PDO::FETCH_ASSOC);
            return array_fill_keys(array_map(static fn(array $row): string => (string)$row['name'], $rows), true);
        }
        $stmt = $db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employee_state'");
        $stmt->execute();
        return array_fill_keys(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    private static function verify(PDO $db): void
    {
        foreach (['employee_events', 'employee_raise_requests', 'employee_strikes', 'employee_strike_members',
            'employee_strike_negotiations', 'employee_strike_negotiation_rounds', 'employee_dialogue_templates',
            'employee_module_cycles', 'employee_system_config'] as $table) {
            $db->query("SELECT 1 FROM {$table} WHERE 1 = 0");
        }
    }

    private static function storeVersion(PDO $db, string $driver): void
    {
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO employee_schema_versions (module_key, version, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON CONFLICT(module_key) DO UPDATE SET version = excluded.version, updated_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO employee_schema_versions (module_key, version, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE version = VALUES(version), updated_at = CURRENT_TIMESTAMP';
        $db->prepare($sql)->execute(['employee_system', self::VERSION]);
    }
}
