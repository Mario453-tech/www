<?php
declare(strict_types=1);

final class EmployeeSystemBootstrap
{
    /** @var list<array{code:string,name_key:string,department:string,rarity:string,salary_min:float,salary_max:float,description_key:string}> */
    private const HR_SPECIALIZATION_SEED = [
        [
            'code' => 'transport_dispatcher',
            'name_key' => 'hr.spec.transport_dispatcher',
            'department' => 'logistics',
            'rarity' => 'common',
            'salary_min' => 7800.0,
            'salary_max' => 10800.0,
            'description_key' => 'hr.spec_desc.transport_dispatcher',
        ],
        [
            'code' => 'warehouse_coordinator',
            'name_key' => 'hr.spec.warehouse_coordinator',
            'department' => 'logistics',
            'rarity' => 'common',
            'salary_min' => 7600.0,
            'salary_max' => 10500.0,
            'description_key' => 'hr.spec_desc.warehouse_coordinator',
        ],
        [
            'code' => 'pipeline_logistics_specialist',
            'name_key' => 'hr.spec.pipeline_logistics_specialist',
            'department' => 'logistics',
            'rarity' => 'uncommon',
            'salary_min' => 8800.0,
            'salary_max' => 12800.0,
            'description_key' => 'hr.spec_desc.pipeline_logistics_specialist',
        ],
        [
            'code' => 'b2b_delivery_coordinator',
            'name_key' => 'hr.spec.b2b_delivery_coordinator',
            'department' => 'logistics',
            'rarity' => 'uncommon',
            'salary_min' => 8600.0,
            'salary_max' => 12400.0,
            'description_key' => 'hr.spec_desc.b2b_delivery_coordinator',
        ],
        [
            'code' => 'terminal_operator',
            'name_key' => 'hr.spec.terminal_operator',
            'department' => 'logistics',
            'rarity' => 'common',
            'salary_min' => 7900.0,
            'salary_max' => 11200.0,
            'description_key' => 'hr.spec_desc.terminal_operator',
        ],
        [
            'code' => 'oil_flow_analyst',
            'name_key' => 'hr.spec.oil_flow_analyst',
            'department' => 'logistics',
            'rarity' => 'rare',
            'salary_min' => 9800.0,
            'salary_max' => 14500.0,
            'description_key' => 'hr.spec_desc.oil_flow_analyst',
        ],
    ];

    /** @var list<array{specialization_code:string,effect_key:string,effect_type:string,effect_value:float,target_scope:string,skill_weights_json:string,description_key:string,is_active:int}> */
    private const ROLE_EFFECT_SEED = [
        [
            'specialization_code' => 'hub_operator',
            'effect_key' => 'hub_throughput_pct',
            'effect_type' => 'percent',
            'effect_value' => 5.0,
            'target_scope' => 'hub',
            'skill_weights_json' => '{"organization":0.40,"analysis":0.35,"stress":0.25}',
            'description_key' => 'hr.effect_desc.hub_throughput_pct',
            'is_active' => 1,
        ],
        [
            'specialization_code' => 'transport_dispatcher',
            'effect_key' => 'road_delay_risk_pct',
            'effect_type' => 'percent',
            'effect_value' => -8.0,
            'target_scope' => 'road_transport',
            'skill_weights_json' => '{"organization":0.45,"analysis":0.30,"stress":0.25}',
            'description_key' => 'hr.effect_desc.road_delay_risk_pct',
            'is_active' => 1,
        ],
        [
            'specialization_code' => 'warehouse_coordinator',
            'effect_key' => 'warehouse_buffer_efficiency_pct',
            'effect_type' => 'percent',
            'effect_value' => 6.0,
            'target_scope' => 'warehouse',
            'skill_weights_json' => '{"organization":0.50,"analysis":0.30,"stress":0.20}',
            'description_key' => 'hr.effect_desc.warehouse_buffer_efficiency_pct',
            'is_active' => 1,
        ],
        [
            'specialization_code' => 'pipeline_logistics_specialist',
            'effect_key' => 'pipeline_loss_pct',
            'effect_type' => 'percent',
            'effect_value' => -7.5,
            'target_scope' => 'pipeline',
            'skill_weights_json' => '{"analysis":0.45,"organization":0.30,"stress":0.25}',
            'description_key' => 'hr.effect_desc.pipeline_loss_pct',
            'is_active' => 1,
        ],
        [
            'specialization_code' => 'b2b_delivery_coordinator',
            'effect_key' => 'b2b_delay_risk_pct',
            'effect_type' => 'percent',
            'effect_value' => -10.0,
            'target_scope' => 'b2b',
            'skill_weights_json' => '{"organization":0.35,"analysis":0.30,"negotiation":0.25,"stress":0.10}',
            'description_key' => 'hr.effect_desc.b2b_delay_risk_pct',
            'is_active' => 1,
        ],
        [
            'specialization_code' => 'terminal_operator',
            'effect_key' => 'port_turnaround_time_pct',
            'effect_type' => 'percent',
            'effect_value' => -6.0,
            'target_scope' => 'port',
            'skill_weights_json' => '{"organization":0.45,"stress":0.30,"analysis":0.25}',
            'description_key' => 'hr.effect_desc.port_turnaround_time_pct',
            'is_active' => 1,
        ],
        [
            'specialization_code' => 'oil_flow_analyst',
            'effect_key' => 'department_transport_cost_pct',
            'effect_type' => 'percent',
            'effect_value' => -4.5,
            'target_scope' => 'department',
            'skill_weights_json' => '{"analysis":0.55,"organization":0.25,"negotiation":0.20}',
            'description_key' => 'hr.effect_desc.department_transport_cost_pct',
            'is_active' => 1,
        ],
    ];

    /** @var WeakMap<PDO, bool>|null */
    private static ?WeakMap $initialized = null;

    public static function ensure(PDO $db): void
    {
        self::$initialized ??= new WeakMap();
        if (isset(self::$initialized[$db])) {
            return;
        }

        $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            self::createSqliteSchema($db);
        } else {
            self::createMySqlSchema($db);
        }

        self::$initialized[$db] = true;
    }

    private static function createMySqlSchema(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS `employee_state` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `player_id` INT UNSIGNED NOT NULL,
            `source_type` ENUM('board_member','technical_staff') NOT NULL,
            `source_id` INT UNSIGNED NOT NULL,
            `department_code` VARCHAR(50) NOT NULL,
            `morale` DECIMAL(5,2) NOT NULL DEFAULT 65.00,
            `salary_satisfaction` DECIMAL(5,2) NOT NULL DEFAULT 70.00,
            `expected_salary` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
            `leave_risk` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `strike_support` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `workload` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            `relation_status` ENUM('normal','unhappy','raise_requested','dispute','strike_threat','on_strike','leaving','inactive') NOT NULL DEFAULT 'normal',
            `last_raise_at` DATETIME NULL,
            `last_raise_request_at` DATETIME NULL,
            `last_morale_tick_at` DATETIME NULL,
            `version` INT UNSIGNED NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_employee_state_source` (`source_type`, `source_id`),
            KEY `idx_employee_state_player` (`player_id`, `department_code`, `relation_status`),
            KEY `idx_employee_state_risk` (`player_id`, `leave_risk`, `strike_support`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `employee_source_links` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `player_id` INT UNSIGNED NOT NULL,
            `board_member_id` INT UNSIGNED NOT NULL,
            `technical_staff_id` INT UNSIGNED NOT NULL,
            `link_type` ENUM('legacy_headhunter_mirror') NOT NULL DEFAULT 'legacy_headhunter_mirror',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_employee_source_link_board` (`board_member_id`),
            UNIQUE KEY `uq_employee_source_link_technical` (`technical_staff_id`),
            KEY `idx_employee_source_link_player` (`player_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `employee_role_effects` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `specialization_code` VARCHAR(80) NOT NULL,
            `effect_key` VARCHAR(100) NOT NULL,
            `effect_type` ENUM('percent','flat','multiplier','bool') NOT NULL DEFAULT 'percent',
            `effect_value` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
            `target_scope` ENUM('department','hub','pipeline','warehouse','road_transport','port','b2b','well','global') NOT NULL DEFAULT 'department',
            `skill_weights_json` JSON NULL,
            `description_key` VARCHAR(190) NOT NULL DEFAULT '',
            `description_pl` VARCHAR(255) NOT NULL DEFAULT '',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_employee_role_effect` (`specialization_code`, `effect_key`, `target_scope`),
            KEY `idx_employee_role_effect_scope` (`target_scope`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `employee_assignments` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `player_id` INT UNSIGNED NOT NULL,
            `source_type` ENUM('board_member','technical_staff') NOT NULL,
            `source_id` INT UNSIGNED NOT NULL,
            `target_type` VARCHAR(32) NOT NULL,
            `target_id` INT UNSIGNED NOT NULL,
            `allocation_pct` DECIMAL(5,2) NOT NULL DEFAULT 100.00,
            `status` ENUM('active','released') NOT NULL DEFAULT 'active',
            `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `released_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_employee_assignment_employee` (`player_id`, `source_type`, `source_id`, `status`),
            KEY `idx_employee_assignment_target` (`player_id`, `target_type`, `target_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::ensureMySqlDepartmentWidth($db);
        self::ensureMySqlRoleEffectColumns($db);
        self::seedHrSpecializations($db);
        self::seedRoleEffects($db);
    }

    private static function createSqliteSchema(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS employee_state (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            player_id INTEGER NOT NULL,
            source_type TEXT NOT NULL,
            source_id INTEGER NOT NULL,
            department_code TEXT NOT NULL,
            morale REAL NOT NULL DEFAULT 65.0,
            salary_satisfaction REAL NOT NULL DEFAULT 70.0,
            expected_salary REAL NOT NULL DEFAULT 0.0,
            leave_risk REAL NOT NULL DEFAULT 0.0,
            strike_support REAL NOT NULL DEFAULT 0.0,
            workload REAL NOT NULL DEFAULT 0.0,
            relation_status TEXT NOT NULL DEFAULT 'normal',
            last_raise_at TEXT NULL,
            last_raise_request_at TEXT NULL,
            last_morale_tick_at TEXT NULL,
            version INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (source_type, source_id)
        )");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_employee_state_player ON employee_state (player_id, department_code, relation_status)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_employee_state_risk ON employee_state (player_id, leave_risk, strike_support)');
        $db->exec("CREATE TABLE IF NOT EXISTS employee_source_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            player_id INTEGER NOT NULL,
            board_member_id INTEGER NOT NULL UNIQUE,
            technical_staff_id INTEGER NOT NULL UNIQUE,
            link_type TEXT NOT NULL DEFAULT 'legacy_headhunter_mirror',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_employee_source_link_player ON employee_source_links (player_id)');

        $db->exec("CREATE TABLE IF NOT EXISTS employee_role_effects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            specialization_code TEXT NOT NULL,
            effect_key TEXT NOT NULL,
            effect_type TEXT NOT NULL DEFAULT 'percent',
            effect_value REAL NOT NULL DEFAULT 0.0,
            target_scope TEXT NOT NULL DEFAULT 'department',
            skill_weights_json TEXT NULL,
            description_key TEXT NOT NULL DEFAULT '',
            description_pl TEXT NOT NULL DEFAULT '',
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_employee_role_effect ON employee_role_effects (specialization_code, effect_key, target_scope)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_employee_role_effect_scope ON employee_role_effects (target_scope, is_active)');

        $db->exec("CREATE TABLE IF NOT EXISTS employee_assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            player_id INTEGER NOT NULL,
            source_type TEXT NOT NULL,
            source_id INTEGER NOT NULL,
            target_type TEXT NOT NULL,
            target_id INTEGER NOT NULL,
            allocation_pct REAL NOT NULL DEFAULT 100.0,
            status TEXT NOT NULL DEFAULT 'active',
            assigned_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            released_at TEXT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_employee_assignment_employee ON employee_assignments (player_id, source_type, source_id, status)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_employee_assignment_target ON employee_assignments (player_id, target_type, target_id, status)');

        self::ensureSqliteRoleEffectColumns($db);
        self::seedHrSpecializations($db);
        self::seedRoleEffects($db);
    }

    private static function ensureMySqlDepartmentWidth(PDO $db): void
    {
        $stmt = $db->prepare(
            "SELECT CHARACTER_MAXIMUM_LENGTH
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'employee_state'
                AND COLUMN_NAME = 'department_code'
              LIMIT 1"
        );
        $stmt->execute();
        $length = $stmt->fetchColumn();
        if ($length !== false && (int)$length < 50) {
            $db->exec('ALTER TABLE employee_state MODIFY department_code VARCHAR(50) NOT NULL');
        }
    }

    private static function ensureMySqlRoleEffectColumns(PDO $db): void
    {
        if (!self::hasColumns($db, 'employee_role_effects', ['description_key'])) {
            $db->exec("ALTER TABLE employee_role_effects ADD COLUMN description_key VARCHAR(190) NOT NULL DEFAULT '' AFTER skill_weights_json");
        }
        if (!self::hasColumns($db, 'employee_role_effects', ['description_pl'])) {
            $db->exec("ALTER TABLE employee_role_effects ADD COLUMN description_pl VARCHAR(255) NOT NULL DEFAULT '' AFTER description_key");
        }
    }

    private static function ensureSqliteRoleEffectColumns(PDO $db): void
    {
        if (!self::hasColumns($db, 'employee_role_effects', ['description_key'])) {
            $db->exec("ALTER TABLE employee_role_effects ADD COLUMN description_key TEXT NOT NULL DEFAULT ''");
        }
        if (!self::hasColumns($db, 'employee_role_effects', ['description_pl'])) {
            $db->exec("ALTER TABLE employee_role_effects ADD COLUMN description_pl TEXT NOT NULL DEFAULT ''");
        }
    }

    private static function seedHrSpecializations(PDO $db): void
    {
        if (!self::hrSpecializationSeedSupported($db)) {
            return;
        }

        $select = $db->prepare('SELECT id FROM hr_specializations WHERE code = ? LIMIT 1');
        $insert = $db->prepare(
            'INSERT INTO hr_specializations
                (code, name, department, rarity, base_salary_min, base_salary_max, min_age, max_age, description)
             VALUES
                (?, ?, ?, ?, ?, ?, 25, 58, ?)'
        );

        foreach (self::HR_SPECIALIZATION_SEED as $row) {
            $name = self::langValue('hr', $row['name_key']);
            $description = self::langValue('hr', $row['description_key']);
            $select->execute([$row['code']]);
            $existingId = $select->fetchColumn();
            if ($existingId !== false) {
                continue;
            }

            $insert->execute([
                $row['code'],
                $name,
                $row['department'],
                $row['rarity'],
                $row['salary_min'],
                $row['salary_max'],
                $description,
            ]);
        }
    }

    private static function seedRoleEffects(PDO $db): void
    {
        if (!self::hasTable($db, 'employee_role_effects')) {
            return;
        }

        $select = $db->prepare(
            'SELECT id, description_key, description_pl
               FROM employee_role_effects
              WHERE specialization_code = ?
                AND effect_key = ?
                AND target_scope = ?
              LIMIT 1'
        );
        $insert = $db->prepare(
            'INSERT INTO employee_role_effects
                (specialization_code, effect_key, effect_type, effect_value, target_scope, skill_weights_json, description_key, description_pl, is_active)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $updateMetadata = $db->prepare(
            'UPDATE employee_role_effects
                SET description_key = ?,
                    description_pl = ?
              WHERE id = ?'
        );

        foreach (self::ROLE_EFFECT_SEED as $row) {
            $select->execute([
                $row['specialization_code'],
                $row['effect_key'],
                $row['target_scope'],
            ]);
            $existingRow = $select->fetch(PDO::FETCH_ASSOC);
            $translatedDescription = self::langValue('hr', $row['description_key']);
            if (is_array($existingRow)) {
                $descriptionKey = trim((string)($existingRow['description_key'] ?? ''));
                $descriptionPl = trim((string)($existingRow['description_pl'] ?? ''));
                if ($descriptionKey === '' || $descriptionPl === '') {
                    $updateMetadata->execute([
                        $descriptionKey !== '' ? $descriptionKey : $row['description_key'],
                        $descriptionPl !== '' ? $descriptionPl : $translatedDescription,
                        (int)$existingRow['id'],
                    ]);
                }
                continue;
            }

            $insert->execute([
                $row['specialization_code'],
                $row['effect_key'],
                $row['effect_type'],
                $row['effect_value'],
                $row['target_scope'],
                $row['skill_weights_json'],
                $row['description_key'],
                $translatedDescription,
                $row['is_active'],
            ]);
        }
    }

    private static function langValue(string $file, string $key): string
    {
        static $cache = [];

        if (!isset($cache[$file])) {
            $path = dirname(__DIR__) . '/lang/pl/' . $file . '.php';
            $cache[$file] = is_file($path) ? (require $path) : [];
        }

        $map = is_array($cache[$file]) ? $cache[$file] : [];
        $value = $map[$key] ?? $key;

        return is_string($value) ? $value : $key;
    }

    private static function hrSpecializationSeedSupported(PDO $db): bool
    {
        return self::hasColumns($db, 'hr_specializations', [
            'code',
            'name',
            'department',
            'rarity',
            'base_salary_min',
            'base_salary_max',
            'description',
        ]);
    }

    private static function hasTable(PDO $db, string $tableName): bool
    {
        return self::tableColumns($db, $tableName) !== [];
    }

    /**
     * @param list<string> $columns
     */
    private static function hasColumns(PDO $db, string $tableName, array $columns): bool
    {
        $existing = self::tableColumns($db, $tableName);
        if ($existing === []) {
            return false;
        }

        foreach ($columns as $column) {
            if (!isset($existing[$column])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, true>
     */
    private static function tableColumns(PDO $db, string $tableName): array
    {
        $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $db->query("PRAGMA table_info(" . $tableName . ")");
            if ($stmt === false) {
                return [];
            }

            $columns = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (isset($row['name'])) {
                    $columns[(string)$row['name']] = true;
                }
            }

            return $columns;
        }

        $stmt = $db->prepare(
            'SELECT COLUMN_NAME
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = ?'
        );
        $stmt->execute([$tableName]);

        $columns = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $columns[(string)$row['COLUMN_NAME']] = true;
        }

        return $columns;
    }
}

if (!function_exists('ensureEmployeeSystemSchema')) {
    function ensureEmployeeSystemSchema(): void
    {
        EmployeeSystemBootstrap::ensure(Database::getInstance()->getConnection());
    }
}
