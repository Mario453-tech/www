<?php
declare(strict_types=1);

/**
 * SabotageSchema - database schema and P1 seed for the universal sabotage module.
 */
class SabotageSchema
{
    /** @var WeakMap<PDO, bool>|null */
    private static ?WeakMap $ensured = null;

    public static function ensure(PDO $db): void
    {
        self::$ensured ??= new WeakMap();
        if (isset(self::$ensured[$db])) {
            return;
        }

        try {
            if ($db->inTransaction()) {
                return;
            }
        } catch (Throwable) {
        }

        try {
            $driver = (string)$db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                self::createSqlite($db);
            } else {
                self::createMysql($db);
            }
            self::seedDefaults($db, $driver);
            self::$ensured[$db] = true;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('SabotageSchema', 'ensure FAILED', $e);
            }
        }
    }

    private static function createMysql(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS sabotage_options (
                id                    INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                code                  VARCHAR(64) NOT NULL,
                name                  VARCHAR(128) NOT NULL,
                description           VARCHAR(512) NOT NULL DEFAULT '',
                target_type           VARCHAR(32) NOT NULL,
                context               VARCHAR(64) NOT NULL,
                is_active             TINYINT(1) NOT NULL DEFAULT 1,
                base_chance_pct       DECIMAL(6,3) NOT NULL DEFAULT 0.000,
                cost_type             ENUM('fixed','percent_reference','per_bbl') NOT NULL DEFAULT 'fixed',
                cost_value            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                cost_currency         ENUM('cash','bank','black_market') NOT NULL DEFAULT 'cash',
                severity              ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
                cooldown_minutes      INT UNSIGNED NOT NULL DEFAULT 0,
                min_region_risk       TINYINT UNSIGNED NOT NULL DEFAULT 0,
                requires_black_market TINYINT(1) NOT NULL DEFAULT 0,
                sort_order            INT NOT NULL DEFAULT 0,
                created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sabotage_code (code),
                KEY idx_sabotage_target (target_type, context, is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS sabotage_effects (
                id                 INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                sabotage_option_id INT NOT NULL,
                effect_key         VARCHAR(64) NOT NULL,
                effect_type        ENUM('mult','delta','set') NOT NULL DEFAULT 'delta',
                effect_value       DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sabotage_effect (sabotage_option_id, effect_key),
                KEY idx_sabotage_effect_option (sabotage_option_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS sabotage_attempts (
                id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                player_id           INT NULL,
                source_type         VARCHAR(32) NOT NULL DEFAULT 'system',
                source_id           INT NULL,
                target_player_id    INT NOT NULL,
                target_type         VARCHAR(32) NOT NULL,
                target_id           INT NOT NULL,
                sabotage_option_id  INT NOT NULL,
                context             VARCHAR(64) NOT NULL,
                status              ENUM('success','failed','blocked_by_protection','partially_blocked','detected','cancelled') NOT NULL DEFAULT 'success',
                chance_pct          DECIMAL(6,3) NOT NULL DEFAULT 0.000,
                roll_value          DECIMAL(6,3) NOT NULL DEFAULT 0.000,
                cost                DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                paid_from           VARCHAR(32) NOT NULL DEFAULT 'system',
                detected            TINYINT(1) NOT NULL DEFAULT 0,
                protection_applied  TINYINT(1) NOT NULL DEFAULT 0,
                created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at         DATETIME NULL DEFAULT NULL,
                meta_json           TEXT NULL,
                KEY idx_sabotage_attempt_target (target_player_id, target_type, target_id, created_at),
                KEY idx_sabotage_attempt_option (sabotage_option_id),
                KEY idx_sabotage_attempt_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS sabotage_logs (
                id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                sabotage_attempt_id INT NULL,
                player_id           INT NULL,
                target_player_id    INT NOT NULL,
                target_type         VARCHAR(32) NOT NULL,
                target_id           INT NOT NULL,
                event_key           VARCHAR(64) NOT NULL,
                message             VARCHAR(512) NOT NULL DEFAULT '',
                meta_json           TEXT NULL,
                created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sabotage_logs_attempt (sabotage_attempt_id),
                KEY idx_sabotage_logs_target (target_player_id, target_type, target_id),
                KEY idx_sabotage_logs_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function createSqlite(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS sabotage_options (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT '',
                target_type TEXT NOT NULL,
                context TEXT NOT NULL,
                is_active INTEGER NOT NULL DEFAULT 1,
                base_chance_pct REAL NOT NULL DEFAULT 0.0,
                cost_type TEXT NOT NULL DEFAULT 'fixed',
                cost_value REAL NOT NULL DEFAULT 0.0,
                cost_currency TEXT NOT NULL DEFAULT 'cash',
                severity TEXT NOT NULL DEFAULT 'low',
                cooldown_minutes INTEGER NOT NULL DEFAULT 0,
                min_region_risk INTEGER NOT NULL DEFAULT 0,
                requires_black_market INTEGER NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT,
                updated_at TEXT
            )"
        );
        $db->exec(
            "CREATE TABLE IF NOT EXISTS sabotage_effects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sabotage_option_id INTEGER NOT NULL,
                effect_key TEXT NOT NULL,
                effect_type TEXT NOT NULL DEFAULT 'delta',
                effect_value REAL NOT NULL DEFAULT 0.0,
                created_at TEXT,
                updated_at TEXT,
                UNIQUE (sabotage_option_id, effect_key)
            )"
        );
        $db->exec(
            "CREATE TABLE IF NOT EXISTS sabotage_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NULL,
                source_type TEXT NOT NULL DEFAULT 'system',
                source_id INTEGER NULL,
                target_player_id INTEGER NOT NULL,
                target_type TEXT NOT NULL,
                target_id INTEGER NOT NULL,
                sabotage_option_id INTEGER NOT NULL,
                context TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'success',
                chance_pct REAL NOT NULL DEFAULT 0.0,
                roll_value REAL NOT NULL DEFAULT 0.0,
                cost REAL NOT NULL DEFAULT 0.0,
                paid_from TEXT NOT NULL DEFAULT 'system',
                detected INTEGER NOT NULL DEFAULT 0,
                protection_applied INTEGER NOT NULL DEFAULT 0,
                created_at TEXT,
                resolved_at TEXT,
                meta_json TEXT
            )"
        );
        $db->exec(
            "CREATE TABLE IF NOT EXISTS sabotage_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sabotage_attempt_id INTEGER NULL,
                player_id INTEGER NULL,
                target_player_id INTEGER NOT NULL,
                target_type TEXT NOT NULL,
                target_id INTEGER NOT NULL,
                event_key TEXT NOT NULL,
                message TEXT NOT NULL DEFAULT '',
                meta_json TEXT,
                created_at TEXT
            )"
        );
    }

    private static function seedDefaults(PDO $db, string $driver): void
    {
        $nowExpr = $driver === 'sqlite' ? "datetime('now')" : 'NOW()';
        $options = [
            [
                'road_ambush', 'Przejecie transportu',
                'Transport zostaje przejety. Caly kurs przepada.',
                'road_transport', 'road_transport_sabotage', 1, 35.0, 'fixed', 0.0, 'cash',
                'high', 0, 0, 0, 10,
                [
                    ['transport_loss_pct', 'set', 100.0],
                    ['delay_minutes', 'delta', 0.0],
                ],
            ],
            [
                'road_partial_theft', 'Czesciowa kradziez transportu',
                'Czesc transportu ginie, ale pozostaly wolumen dociera.',
                'road_transport', 'road_transport_sabotage', 1, 75.0, 'fixed', 0.0, 'cash',
                'medium', 0, 0, 0, 20,
                [
                    ['transport_loss_pct', 'set', 30.0],
                    ['delay_minutes', 'delta', 0.0],
                ],
            ],
        ];

        foreach ($options as $row) {
            [$code, $name, $description, $targetType, $context, $active, $chance, $costType,
                $costValue, $currency, $severity, $cooldown, $minRisk, $blackMarket, $sort, $effects] = $row;

            if ($driver === 'sqlite') {
                $db->prepare(
                    "INSERT OR IGNORE INTO sabotage_options
                        (code, name, description, target_type, context, is_active, base_chance_pct,
                         cost_type, cost_value, cost_currency, severity, cooldown_minutes,
                         min_region_risk, requires_black_market, sort_order, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, {$nowExpr}, {$nowExpr})"
                )->execute([$code, $name, $description, $targetType, $context, $active, $chance, $costType,
                    $costValue, $currency, $severity, $cooldown, $minRisk, $blackMarket, $sort]);
            } else {
                $db->prepare(
                    "INSERT INTO sabotage_options
                        (code, name, description, target_type, context, is_active, base_chance_pct,
                         cost_type, cost_value, cost_currency, severity, cooldown_minutes,
                         min_region_risk, requires_black_market, sort_order)
                     SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                     FROM DUAL
                     WHERE NOT EXISTS (SELECT 1 FROM sabotage_options WHERE code = ?)"
                )->execute([$code, $name, $description, $targetType, $context, $active, $chance, $costType,
                    $costValue, $currency, $severity, $cooldown, $minRisk, $blackMarket, $sort, $code]);
            }

            $idStmt = $db->prepare("SELECT id FROM sabotage_options WHERE code = ? LIMIT 1");
            $idStmt->execute([$code]);
            $optionId = (int)$idStmt->fetchColumn();
            if ($optionId <= 0) {
                continue;
            }

            foreach ($effects as [$effectKey, $effectType, $effectValue]) {
                if ($driver === 'sqlite') {
                    $db->prepare(
                        "INSERT OR IGNORE INTO sabotage_effects
                            (sabotage_option_id, effect_key, effect_type, effect_value, created_at, updated_at)
                         VALUES (?, ?, ?, ?, {$nowExpr}, {$nowExpr})"
                    )->execute([$optionId, $effectKey, $effectType, $effectValue]);
                } else {
                    $db->prepare(
                        "INSERT INTO sabotage_effects
                            (sabotage_option_id, effect_key, effect_type, effect_value)
                         SELECT ?, ?, ?, ?
                         FROM DUAL
                         WHERE NOT EXISTS (
                            SELECT 1 FROM sabotage_effects
                             WHERE sabotage_option_id = ? AND effect_key = ?
                         )"
                    )->execute([$optionId, $effectKey, $effectType, $effectValue, $optionId, $effectKey]);
                }
            }
        }
    }
}
