<?php
declare(strict_types=1);

/**
 * ContractSchema - schemat i seed kontraktow dlugoterminowych.
 * ContractSchema - schema and seed for long-term contracts.
 */
class ContractSchema
{
    /** @var WeakMap<PDO, bool>|null */
    private static ?WeakMap $ensured = null;

    public static function ensure(PDO $db): void
    {
        self::$ensured ??= new WeakMap();
        if (isset(self::$ensured[$db])) {
            return;
        }

        $inTransaction = false;
        try {
            $inTransaction = $db->inTransaction();
        } catch (Throwable) {
        }
        if ($inTransaction) {
            throw new RuntimeException('Contract schema cannot be ensured inside an active transaction.');
        }

        try {
            $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                self::createSqlite($db);
            } else {
                self::createMysql($db);
                self::migrateMysql($db);
            }
            self::seedDefaults($db, $driver);
            self::$ensured[$db] = true;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractSchema', 'ensure FAILED', $e);
            }
        }
    }

    private static function createMysql(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS contract_options (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(64) NOT NULL,
                name VARCHAR(128) NOT NULL,
                description VARCHAR(512) NOT NULL DEFAULT '',
                buyer_name VARCHAR(128) NOT NULL DEFAULT 'Odbiorca kontraktowy',
                target_type VARCHAR(32) NOT NULL,
                context VARCHAR(64) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                price_mode ENUM('fixed','market_multiplier','market_plus_bonus') NOT NULL DEFAULT 'market_plus_bonus',
                fixed_price DECIMAL(12,2) NULL,
                price_multiplier DECIMAL(8,4) NOT NULL DEFAULT 1.0000,
                severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
                min_credibility INT NOT NULL DEFAULT 0,
                requires_legal_level INT NOT NULL DEFAULT 0,
                max_active_per_player INT NOT NULL DEFAULT 3,
                expires_at DATETIME NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_contract_code (code),
                KEY idx_contract_target (target_type, context, is_active),
                KEY idx_contract_expires (is_active, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS contract_terms (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                contract_option_id INT NOT NULL,
                term_key VARCHAR(64) NOT NULL,
                term_type ENUM('number','percent','minutes','text','bool') NOT NULL DEFAULT 'number',
                term_value DECIMAL(14,4) NULL,
                term_text VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_contract_term (contract_option_id, term_key),
                KEY idx_contract_term_option (contract_option_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS player_contracts (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                player_id INT NOT NULL,
                contract_option_id INT NOT NULL,
                target_type VARCHAR(32) NOT NULL,
                target_id INT NULL,
                context VARCHAR(64) NOT NULL,
                buyer_name VARCHAR(128) NOT NULL,
                contract_name VARCHAR(128) NOT NULL,
                status ENUM('active','completed','failed','cancelled','expired') NOT NULL DEFAULT 'active',
                total_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                delivered_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                missed_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                next_delivery_at DATETIME NOT NULL,
                starts_at DATETIME NOT NULL,
                ends_at DATETIME NOT NULL,
                completed_at DATETIME NULL,
                cancelled_at DATETIME NULL,
                terms_json TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_player_contracts_player (player_id, status),
                KEY idx_player_contracts_due (status, next_delivery_at),
                KEY idx_player_contracts_context (target_type, context),
                KEY idx_player_contracts_end (status, ends_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS contract_deliveries (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                player_contract_id INT NOT NULL,
                player_id INT NOT NULL,
                due_at DATETIME NOT NULL,
                required_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                delivered_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                missed_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                price_per_bbl DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                revenue DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                penalty DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                status ENUM('delivered','partial','missed','cancelled') NOT NULL DEFAULT 'delivered',
                meta_json TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_contract_delivery_due (player_contract_id, due_at),
                KEY idx_contract_deliveries_contract (player_contract_id),
                KEY idx_contract_deliveries_player (player_id, created_at),
                KEY idx_contract_deliveries_status (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS contract_logs (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                player_contract_id INT NULL,
                player_id INT NOT NULL,
                target_type VARCHAR(32) NOT NULL,
                target_id INT NULL,
                context VARCHAR(64) NOT NULL,
                event_key VARCHAR(64) NOT NULL,
                message VARCHAR(512) NOT NULL DEFAULT '',
                meta_json TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_contract_logs_contract (player_contract_id),
                KEY idx_contract_logs_player (player_id, created_at),
                KEY idx_contract_logs_event (event_key, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function migrateMysql(PDO $db): void
    {
        try {
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'contract_deliveries'
                    AND INDEX_NAME = 'uq_contract_delivery_due'"
            );
            $stmt->execute();
            if ((int)$stmt->fetchColumn() === 0) {
                $db->exec("ALTER TABLE contract_deliveries ADD UNIQUE KEY uq_contract_delivery_due (player_contract_id, due_at)");
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractSchema', 'migrateMysql FAILED', $e);
            }
        }
    }

    private static function createSqlite(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS contract_options (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                buyer_name TEXT NOT NULL DEFAULT 'Odbiorca kontraktowy',
                target_type TEXT NOT NULL,
                context TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                price_mode TEXT NOT NULL DEFAULT 'market_plus_bonus',
                fixed_price REAL NULL,
                price_multiplier REAL NOT NULL DEFAULT 1.0,
                severity TEXT NOT NULL DEFAULT 'low',
                min_credibility INTEGER NOT NULL DEFAULT 0,
                requires_legal_level INTEGER NOT NULL DEFAULT 0,
                max_active_per_player INTEGER NOT NULL DEFAULT 3,
                expires_at TEXT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT,
                updated_at TEXT
            )"
        );
        $db->exec(
            "CREATE TABLE IF NOT EXISTS contract_terms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                contract_option_id INTEGER NOT NULL,
                term_key TEXT NOT NULL,
                term_type TEXT NOT NULL DEFAULT 'number',
                term_value REAL NULL,
                term_text TEXT NULL,
                created_at TEXT,
                updated_at TEXT,
                UNIQUE (contract_option_id, term_key)
            )"
        );
        $db->exec(
            "CREATE TABLE IF NOT EXISTS player_contracts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                contract_option_id INTEGER NOT NULL,
                target_type TEXT NOT NULL,
                target_id INTEGER NULL,
                context TEXT NOT NULL,
                buyer_name TEXT NOT NULL,
                contract_name TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'active',
                total_bbl REAL NOT NULL DEFAULT 0.0,
                delivered_bbl REAL NOT NULL DEFAULT 0.0,
                missed_bbl REAL NOT NULL DEFAULT 0.0,
                next_delivery_at TEXT NOT NULL,
                starts_at TEXT NOT NULL,
                ends_at TEXT NOT NULL,
                completed_at TEXT NULL,
                cancelled_at TEXT NULL,
                terms_json TEXT NULL,
                created_at TEXT,
                updated_at TEXT
            )"
        );
        $db->exec(
            "CREATE TABLE IF NOT EXISTS contract_deliveries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_contract_id INTEGER NOT NULL,
                player_id INTEGER NOT NULL,
                due_at TEXT NOT NULL,
                required_bbl REAL NOT NULL DEFAULT 0.0,
                delivered_bbl REAL NOT NULL DEFAULT 0.0,
                missed_bbl REAL NOT NULL DEFAULT 0.0,
                price_per_bbl REAL NOT NULL DEFAULT 0.0,
                revenue REAL NOT NULL DEFAULT 0.0,
                penalty REAL NOT NULL DEFAULT 0.0,
                status TEXT NOT NULL DEFAULT 'delivered',
                meta_json TEXT NULL,
                created_at TEXT,
                UNIQUE (player_contract_id, due_at)
            )"
        );
        $db->exec(
            "CREATE TABLE IF NOT EXISTS contract_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_contract_id INTEGER NULL,
                player_id INTEGER NOT NULL,
                target_type TEXT NOT NULL,
                target_id INTEGER NULL,
                context TEXT NOT NULL,
                event_key TEXT NOT NULL,
                message TEXT NOT NULL DEFAULT '',
                meta_json TEXT NULL,
                created_at TEXT
            )"
        );
    }

    private static function seedDefaults(PDO $db, string $driver): void
    {
        $now = $driver === 'sqlite' ? "datetime('now')" : 'NOW()';
        $options = [
            [
                'small_local_refinery', 'Lokalna rafineria', 'BalticFuel Local', 'low', 0, 0, 10,
                ['total_bbl' => 5000, 'delivery_bbl' => 1250, 'delivery_interval_minutes' => 360, 'duration_minutes' => 1440, 'bonus_pct' => 5, 'penalty_pct' => 5],
            ],
            [
                'medium_fuel_network', 'Siec paliwowa', 'NorthPetrol Network', 'medium', 35, 0, 20,
                ['total_bbl' => 30000, 'delivery_bbl' => 5000, 'delivery_interval_minutes' => 720, 'duration_minutes' => 4320, 'bonus_pct' => 10, 'penalty_pct' => 8],
            ],
            [
                'large_industrial_buyer', 'Koncern przemyslowy', 'Baltic Heavy Industry', 'high', 60, 3, 30,
                ['total_bbl' => 100000, 'delivery_bbl' => 10000, 'delivery_interval_minutes' => 1440, 'duration_minutes' => 14400, 'bonus_pct' => 18, 'penalty_pct' => 12],
            ],
        ];

        foreach ($options as [$code, $name, $buyer, $severity, $cred, $legal, $sort, $terms]) {
            self::insertOption($db, $driver, $now, $code, $name, $buyer, $severity, $cred, $legal, $sort);
            $optionId = self::optionId($db, $code);
            if ($optionId > 0) {
                foreach ($terms as $key => $value) {
                    self::insertTerm($db, $driver, $now, $optionId, (string)$key, (float)$value);
                }
            }
        }
    }

    private static function insertOption(PDO $db, string $driver, string $now, string $code, string $name, string $buyer, string $severity, int $cred, int $legal, int $sort): void
    {
        if ($driver === 'sqlite') {
            $db->prepare(
                "INSERT OR IGNORE INTO contract_options
                    (code, name, description, buyer_name, target_type, context, is_active, price_mode,
                     price_multiplier, severity, min_credibility, requires_legal_level,
                     max_active_per_player, sort_order, created_at, updated_at)
                 VALUES (?, ?, '', ?, 'storage', 'storage_oil_delivery', 1, 'market_plus_bonus',
                         1.0000, ?, ?, ?, 3, ?, {$now}, {$now})"
            )->execute([$code, $name, $buyer, $severity, $cred, $legal, $sort]);
            return;
        }

        $db->prepare(
            "INSERT IGNORE INTO contract_options
                (code, name, description, buyer_name, target_type, context, is_active, price_mode,
                 price_multiplier, severity, min_credibility, requires_legal_level,
                 max_active_per_player, sort_order, created_at, updated_at)
             VALUES (?, ?, '', ?, 'storage', 'storage_oil_delivery', 1, 'market_plus_bonus',
                     1.0000, ?, ?, ?, 3, ?, {$now}, {$now})"
        )->execute([$code, $name, $buyer, $severity, $cred, $legal, $sort]);
    }

    private static function insertTerm(PDO $db, string $driver, string $now, int $optionId, string $key, float $value): void
    {
        $type = str_contains($key, 'minutes') ? 'minutes' : (str_contains($key, 'pct') ? 'percent' : 'number');
        $verb = $driver === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $db->prepare(
            "{$verb} INTO contract_terms
                (contract_option_id, term_key, term_type, term_value, term_text, created_at, updated_at)
             VALUES (?, ?, ?, ?, NULL, {$now}, {$now})"
        )->execute([$optionId, $key, $type, $value]);
    }

    private static function optionId(PDO $db, string $code): int
    {
        $stmt = $db->prepare("SELECT id FROM contract_options WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        return (int)$stmt->fetchColumn();
    }
}
