<?php
declare(strict_types=1);

final class EmployeeSystemSchema
{
    public const VERSION = 8;

    public static function ensure(PDO $db): void
    {
        $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        self::createVersionTable($db, $driver);
        if (self::currentVersion($db) >= self::VERSION) {
            self::verify($db, $driver);
            return;
        }
        foreach ($driver === 'sqlite' ? self::sqliteStatements() : self::mysqlStatements() as $sql) {
            $db->exec($sql);
        }
        self::ensureStateColumns($db, $driver);
        self::ensureEventColumns($db, $driver);
        self::ensureRaiseRequestColumns($db, $driver);
        self::ensureRoundTokenIndex($db, $driver);
        self::ensureNegotiationAttemptColumns($db, $driver);
        self::ensureHeadhunterOfferColumns($db, $driver);
        self::ensureTechnicalStaffTraitColumns($db, $driver);
        self::verify($db, $driver);
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

    public static function verifyCurrent(PDO $db): void
    {
        self::verify($db, (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME));
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
                is_read TINYINT(1) NOT NULL DEFAULT 0, notified_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_employee_event_dedupe (dedupe_key),
                KEY idx_employee_event_player (player_id, created_at)
            ){$s}",
            "CREATE TABLE IF NOT EXISTS employee_raise_requests (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, player_id INT UNSIGNED NOT NULL,
                source_type VARCHAR(32) NOT NULL, source_id INT UNSIGNED NOT NULL, request_no INT UNSIGNED NOT NULL DEFAULT 1,
                current_salary DECIMAL(14,2) NOT NULL DEFAULT 0, requested_salary DECIMAL(14,2) NOT NULL DEFAULT 0,
                negotiated_salary DECIMAL(14,2) NULL DEFAULT NULL, requested_raise_pct DECIMAL(7,4) NOT NULL DEFAULT 0,
                reason_code VARCHAR(64) NOT NULL DEFAULT 'low_morale', postponed_count INT UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(32) NOT NULL DEFAULT 'open',
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
                attempt_no INT UNSIGNED NOT NULL DEFAULT 1,
                current_round INT UNSIGNED NOT NULL DEFAULT 1, max_rounds INT UNSIGNED NOT NULL DEFAULT 3,
                round_deadline_at DATETIME NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_employee_strike_negotiation (strike_id)
            ){$s}",
            "CREATE TABLE IF NOT EXISTS employee_strike_negotiation_rounds (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, negotiation_id BIGINT UNSIGNED NOT NULL,
                strike_id BIGINT UNSIGNED NOT NULL, player_id INT UNSIGNED NOT NULL,
                attempt_no INT UNSIGNED NOT NULL DEFAULT 1, round_no INT UNSIGNED NOT NULL,
                idempotency_token VARCHAR(128) NOT NULL, raise_pct DECIMAL(7,4) NOT NULL DEFAULT 0,
                bonus_per_member DECIMAL(14,2) NOT NULL DEFAULT 0, counter_raise_pct DECIMAL(7,4) NULL,
                counter_bonus_per_member DECIMAL(14,2) NULL, random_roll DECIMAL(7,4) NOT NULL,
                formula_json TEXT NOT NULL, dialogue_template_id BIGINT UNSIGNED NULL, result VARCHAR(32) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_employee_round_token (player_id, strike_id, idempotency_token),
                UNIQUE KEY uq_employee_round_no (negotiation_id, attempt_no, round_no)
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
            "CREATE TABLE IF NOT EXISTS employee_cycle_department_claims (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                cycle_id BIGINT UNSIGNED NOT NULL, player_id INT UNSIGNED NOT NULL,
                department_code VARCHAR(50) NOT NULL, claimed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME NULL,
                UNIQUE KEY uq_employee_cycle_department (cycle_id, player_id, department_code),
                KEY idx_employee_cycle_claim (cycle_id, completed_at)
            ){$s}",
            "CREATE TABLE IF NOT EXISTS employee_action_receipts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                player_id INT UNSIGNED NOT NULL, action_key VARCHAR(80) NOT NULL,
                idempotency_token VARCHAR(128) NOT NULL, request_hash CHAR(64) NOT NULL,
                response_json TEXT NULL, completed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_employee_action_receipt (player_id, action_key, idempotency_token)
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
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_state_source ON employee_state (source_type, source_id)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_source_link_board ON employee_source_links (board_member_id)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_source_link_technical ON employee_source_links (technical_staff_id)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_event_dedupe ON employee_events (dedupe_key)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_raise_request ON employee_raise_requests (player_id, source_type, source_id, request_no)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_strike_open ON employee_strikes (open_key)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_strike_member ON employee_strike_members (strike_id, source_type, source_id)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_strike_negotiation ON employee_strike_negotiations (strike_id)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_round_token ON employee_strike_negotiation_rounds (player_id, strike_id, idempotency_token)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_round_no ON employee_strike_negotiation_rounds (negotiation_id, attempt_no, round_no)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_dialogue_seed ON employee_dialogue_templates (seed_key)',
            'CREATE INDEX IF NOT EXISTS idx_employee_dialogue_lookup ON employee_dialogue_templates (context_key, department_code, round_no, tone, is_active)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_module_cycle ON employee_module_cycles (module_key, cycle_key)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_cycle_department ON employee_cycle_department_claims (cycle_id, player_id, department_code)',
            'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_action_receipt ON employee_action_receipts (player_id, action_key, idempotency_token)',
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
            'loyalty_modifier' => $driver === 'sqlite'
                ? 'REAL NOT NULL DEFAULT 0'
                : 'DECIMAL(5,2) NOT NULL DEFAULT 0.00',
            'leave_risk_streak' => 'INT NOT NULL DEFAULT 0',
            'leaving_at' => 'DATETIME NULL',
            'inactive_at' => 'DATETIME NULL',
        ];
        foreach ($defs as $name => $definition) {
            if (!isset($columns[$name])) {
                $db->exec("ALTER TABLE employee_state ADD COLUMN {$name} {$definition}");
            }
        }
    }

    private static function ensureEventColumns(PDO $db, string $driver): void
    {
        $columns = self::columns($db, $driver, 'employee_events');
        $defs = [
            'is_read' => 'TINYINT NOT NULL DEFAULT 0',
            'notified_at' => 'DATETIME NULL',
        ];
        foreach ($defs as $name => $definition) {
            if (!isset($columns[$name])) {
                $db->exec("ALTER TABLE employee_events ADD COLUMN {$name} {$definition}");
            }
        }
    }

    private static function ensureRoundTokenIndex(PDO $db, string $driver): void
    {
        if ($driver === 'sqlite') {
            $db->exec('DROP INDEX IF EXISTS uq_employee_round_token');
            $db->exec(
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_round_token
                   ON employee_strike_negotiation_rounds (player_id, strike_id, idempotency_token)'
            );
            return;
        }

        $stmt = $db->prepare(
            "SELECT COLUMN_NAME
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA=DATABASE()
                AND TABLE_NAME='employee_strike_negotiation_rounds'
                AND INDEX_NAME='uq_employee_round_token'
              ORDER BY SEQ_IN_INDEX"
        );
        $stmt->execute();
        $columns = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($columns === ['player_id', 'strike_id', 'idempotency_token']) {
            return;
        }
        if ($columns !== []) {
            $db->exec('ALTER TABLE employee_strike_negotiation_rounds DROP INDEX uq_employee_round_token');
        }
        $db->exec(
            'ALTER TABLE employee_strike_negotiation_rounds
             ADD UNIQUE KEY uq_employee_round_token (player_id, strike_id, idempotency_token)'
        );
    }

    private static function ensureNegotiationAttemptColumns(PDO $db, string $driver): void
    {
        $negotiationColumns = self::columns($db, $driver, 'employee_strike_negotiations');
        if (!isset($negotiationColumns['attempt_no'])) {
            $db->exec('ALTER TABLE employee_strike_negotiations ADD COLUMN attempt_no INT NOT NULL DEFAULT 1');
        }
        $roundColumns = self::columns($db, $driver, 'employee_strike_negotiation_rounds');
        if (!isset($roundColumns['attempt_no'])) {
            $db->exec('ALTER TABLE employee_strike_negotiation_rounds ADD COLUMN attempt_no INT NOT NULL DEFAULT 1');
        }

        if ($driver === 'sqlite') {
            $db->exec('DROP INDEX IF EXISTS uq_employee_round_no');
            $db->exec(
                'CREATE UNIQUE INDEX uq_employee_round_no
                   ON employee_strike_negotiation_rounds (negotiation_id, attempt_no, round_no)'
            );
            return;
        }

        $columns = self::indexColumns($db, 'employee_strike_negotiation_rounds', 'uq_employee_round_no');
        if ($columns === ['negotiation_id', 'attempt_no', 'round_no']) {
            return;
        }
        if ($columns !== []) {
            $db->exec('ALTER TABLE employee_strike_negotiation_rounds DROP INDEX uq_employee_round_no');
        }
        $db->exec(
            'ALTER TABLE employee_strike_negotiation_rounds
             ADD UNIQUE KEY uq_employee_round_no (negotiation_id, attempt_no, round_no)'
        );
    }

    private static function ensureHeadhunterOfferColumns(PDO $db, string $driver): void
    {
        if (!self::tableExists($db, $driver, 'headhunter_candidates')) {
            return;
        }
        $columns = self::columns($db, $driver, 'headhunter_candidates');
        $definitions = [
            'offer_round' => 'INT NOT NULL DEFAULT 0',
            'counter_salary' => $driver === 'sqlite' ? 'REAL NULL' : 'DECIMAL(14,2) NULL',
            'counter_bonus' => $driver === 'sqlite' ? 'REAL NULL' : 'DECIMAL(14,2) NULL',
        ];
        foreach ($definitions as $name => $definition) {
            if (!isset($columns[$name])) {
                $db->exec("ALTER TABLE headhunter_candidates ADD COLUMN {$name} {$definition}");
            }
        }
    }

    private static function ensureRaiseRequestColumns(PDO $db, string $driver): void
    {
        $columns = self::columns($db, $driver, 'employee_raise_requests');
        $defs = [
            'current_salary' => $driver === 'sqlite'
                ? 'REAL NOT NULL DEFAULT 0'
                : 'DECIMAL(14,2) NOT NULL DEFAULT 0',
            'requested_salary' => $driver === 'sqlite'
                ? 'REAL NOT NULL DEFAULT 0'
                : 'DECIMAL(14,2) NOT NULL DEFAULT 0',
            'negotiated_salary' => $driver === 'sqlite'
                ? 'REAL NULL DEFAULT NULL'
                : 'DECIMAL(14,2) NULL DEFAULT NULL',
            'reason_code' => "VARCHAR(64) NOT NULL DEFAULT 'low_morale'",
            'postponed_count' => $driver === 'sqlite'
                ? 'INTEGER NOT NULL DEFAULT 0'
                : 'INT UNSIGNED NOT NULL DEFAULT 0',
        ];
        foreach ($defs as $name => $definition) {
            if (!isset($columns[$name])) {
                $db->exec("ALTER TABLE employee_raise_requests ADD COLUMN {$name} {$definition}");
            }
        }
    }
    private static function ensureTechnicalStaffTraitColumns(PDO $db, string $driver): void
    {
        if (!self::tableExists($db, $driver, 'technical_staff')) {
            return;
        }

        $columns = self::columns($db, $driver, 'technical_staff');
        $defs = [
            'trait_loyalty' => 'TINYINT NOT NULL DEFAULT 5',
            'trait_corruption_risk' => 'TINYINT NOT NULL DEFAULT 5',
            'trait_ambition' => 'TINYINT NOT NULL DEFAULT 5',
        ];
        foreach ($defs as $name => $definition) {
            if (!isset($columns[$name])) {
                $db->exec("ALTER TABLE technical_staff ADD COLUMN {$name} {$definition}");
            }
        }
    }

    private static function tableExists(PDO $db, string $driver, string $table): bool
    {
        if ($driver === 'sqlite') {
            $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        }

        $stmt = $db->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    /** @return array<string, true> */
    private static function columns(PDO $db, string $driver, string $table = 'employee_state'): array
    {
        if ($driver === 'sqlite') {
            $rows = $db->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC);
            return array_fill_keys(array_map(static fn(array $row): string => (string)$row['name'], $rows), true);
        }
        $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        return array_fill_keys(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    /** @return list<string> */
    private static function indexColumns(PDO $db, string $table, string $index): array
    {
        $stmt = $db->prepare(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?
              ORDER BY SEQ_IN_INDEX'
        );
        $stmt->execute([$table, $index]);
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function verify(PDO $db, string $driver): void
    {
        $required = [
            'employee_state' => [
                'id', 'player_id', 'source_type', 'source_id', 'department_code', 'morale',
                'salary_satisfaction', 'expected_salary', 'leave_risk', 'strike_support',
                'workload', 'loyalty_modifier', 'relation_status', 'last_raise_at',
                'last_raise_request_at', 'last_morale_tick_at', 'last_morale_tick_sequence',
                'last_morale_cycle_id', 'low_morale_streak', 'dispute_ticks',
                'leave_risk_streak', 'leaving_at', 'inactive_at', 'version',
            ],
            'employee_source_links' => [
                'id', 'player_id', 'board_member_id', 'technical_staff_id', 'link_type',
            ],
            'employee_role_effects' => [
                'id', 'specialization_code', 'effect_key', 'effect_type', 'effect_value',
                'target_scope', 'skill_weights_json', 'description_key', 'is_active',
            ],
            'employee_assignments' => [
                'id', 'player_id', 'source_type', 'source_id', 'target_type', 'target_id',
                'allocation_pct', 'status', 'assigned_at', 'released_at',
            ],
            'employee_events' => [
                'id', 'player_id', 'source_type', 'source_id', 'strike_id', 'event_key',
                'title_key', 'message_key', 'meta_json', 'dedupe_key', 'is_read', 'notified_at',
            ],
            'employee_raise_requests' => [
                'id', 'player_id', 'source_type', 'source_id', 'request_no', 'current_salary',
                'requested_salary', 'negotiated_salary', 'requested_raise_pct', 'reason_code',
                'postponed_count', 'status', 'deadline_at', 'resolved_at',
            ],
            'employee_strikes' => [
                'id', 'player_id', 'department_code', 'status', 'open_key', 'support_pct',
                'threat_cycles', 'negotiation_cooldown_until', 'started_at', 'resolved_at',
            ],
            'employee_strike_members' => [
                'id', 'strike_id', 'player_id', 'source_type', 'source_id', 'support_pct',
                'joined_at', 'left_at',
            ],
            'employee_strike_negotiations' => [
                'id', 'strike_id', 'player_id', 'status', 'attempt_no', 'current_round',
                'max_rounds', 'round_deadline_at',
            ],
            'employee_strike_negotiation_rounds' => [
                'id', 'negotiation_id', 'strike_id', 'player_id', 'attempt_no', 'round_no',
                'idempotency_token', 'raise_pct', 'bonus_per_member', 'counter_raise_pct',
                'counter_bonus_per_member', 'random_roll', 'formula_json', 'result',
            ],
            'employee_dialogue_templates' => [
                'id', 'seed_key', 'context_key', 'department_code', 'round_no', 'tone',
                'text_pl', 'text_en', 'weight', 'is_active',
            ],
            'employee_module_cycles' => [
                'id', 'module_key', 'cycle_key', 'run_sequence', 'status', 'processed_count',
                'error_count', 'started_at', 'completed_at',
            ],
            'employee_system_config' => ['config_key', 'config_value', 'updated_at'],
            'employee_cycle_department_claims' => [
                'id', 'cycle_id', 'player_id', 'department_code', 'claimed_at', 'completed_at',
            ],
            'employee_action_receipts' => [
                'id', 'player_id', 'action_key', 'idempotency_token', 'request_hash',
                'response_json', 'completed_at',
            ],
        ];
        foreach ($required as $table => $columnNames) {
            $db->query("SELECT 1 FROM {$table} WHERE 1 = 0");
            $columns = self::columns($db, $driver, $table);
            foreach ($columnNames as $columnName) {
                if (!isset($columns[$columnName])) {
                    throw new RuntimeException("Employee schema verification failed: {$table}.{$columnName} is missing.");
                }
            }
        }

        $indexes = [
            ['employee_state', 'uq_employee_state_source', ['source_type', 'source_id']],
            ['employee_source_links', 'uq_employee_source_link_board', ['board_member_id']],
            ['employee_source_links', 'uq_employee_source_link_technical', ['technical_staff_id']],
            ['employee_role_effects', 'uq_employee_role_effect', ['specialization_code', 'effect_key', 'target_scope']],
            ['employee_events', 'uq_employee_event_dedupe', ['dedupe_key']],
            ['employee_raise_requests', 'uq_employee_raise_request', ['player_id', 'source_type', 'source_id', 'request_no']],
            ['employee_strikes', 'uq_employee_strike_open', ['open_key']],
            ['employee_strike_members', 'uq_employee_strike_member', ['strike_id', 'source_type', 'source_id']],
            ['employee_strike_negotiations', 'uq_employee_strike_negotiation', ['strike_id']],
            ['employee_strike_negotiation_rounds', 'uq_employee_round_no', ['negotiation_id', 'attempt_no', 'round_no']],
            ['employee_strike_negotiation_rounds', 'uq_employee_round_token', ['player_id', 'strike_id', 'idempotency_token']],
            ['employee_dialogue_templates', 'uq_employee_dialogue_seed', ['seed_key']],
            ['employee_module_cycles', 'uq_employee_module_cycle', ['module_key', 'cycle_key']],
            ['employee_cycle_department_claims', 'uq_employee_cycle_department', ['cycle_id', 'player_id', 'department_code']],
            ['employee_action_receipts', 'uq_employee_action_receipt', ['player_id', 'action_key', 'idempotency_token']],
        ];
        foreach ($indexes as [$table, $index, $expected]) {
            if (self::verificationIndexColumns($db, $driver, $table, $index) !== $expected) {
                throw new RuntimeException("Employee schema verification failed: {$index} is invalid.");
            }
        }

        self::verifyOptionalColumns(
            $db,
            $driver,
            'headhunter_candidates',
            ['offer_round', 'counter_salary', 'counter_bonus']
        );
        self::verifyOptionalColumns(
            $db,
            $driver,
            'technical_staff',
            ['trait_loyalty', 'trait_corruption_risk', 'trait_ambition']
        );
    }

    /** @return list<string> */
    private static function verificationIndexColumns(
        PDO $db,
        string $driver,
        string $table,
        string $index
    ): array {
        if ($driver === 'mysql') {
            return self::indexColumns($db, $table, $index);
        }

        $rows = $db->query('PRAGMA index_info(' . $index . ')')->fetchAll(PDO::FETCH_ASSOC);
        return array_map(static fn(array $row): string => (string)$row['name'], $rows);
    }

    /** @param list<string> $requiredColumns */
    private static function verifyOptionalColumns(
        PDO $db,
        string $driver,
        string $table,
        array $requiredColumns
    ): void {
        if (!self::tableExists($db, $driver, $table)) {
            return;
        }
        $columns = self::columns($db, $driver, $table);
        foreach ($requiredColumns as $columnName) {
            if (!isset($columns[$columnName])) {
                throw new RuntimeException("Employee schema verification failed: {$table}.{$columnName} is missing.");
            }
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
