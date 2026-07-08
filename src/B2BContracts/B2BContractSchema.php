<?php
declare(strict_types=1);

/**
 * B2BContractSchema - schema and default config for company-to-company oil offers.
 * B2BContractSchema - schemat i domyslna konfiguracja ofert firma-firma.
 */
final class B2BContractSchema
{
    /** @var WeakMap<PDO,bool>|null */
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
                self::migrateMysql();
            }
            self::seedConfig($db, $driver);
            self::$ensured[$db] = true;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('B2BContractSchema', 'ensure FAILED', $e);
            }
        }
    }

    private static function createMysql(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS b2b_contract_offers (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                buyer_player_id INT NOT NULL,
                seller_player_id INT NULL,
                status ENUM('open','completed','cancelled','expired','failed','flagged') NOT NULL DEFAULT 'open',
                total_bbl DECIMAL(14,2) NOT NULL,
                delivered_bbl DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                price_per_bbl DECIMAL(12,2) NOT NULL,
                total_value DECIMAL(14,2) NOT NULL,
                escrow_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                escrow_status ENUM('none','locked','released','refunded','partial_refund','forfeited') NOT NULL DEFAULT 'none',
                cancel_penalty_pct DECIMAL(8,4) NOT NULL DEFAULT 10.0000,
                cancel_penalty_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                refunded_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                min_seller_reputation INT NOT NULL DEFAULT 0,
                expires_at DATETIME NOT NULL,
                completed_at DATETIME NULL,
                cancelled_at DATETIME NULL,
                cancel_reason VARCHAR(255) NULL,
                is_flagged TINYINT(1) NOT NULL DEFAULT 0,
                flag_reason VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL,
                KEY idx_b2b_offers_status (status, expires_at),
                KEY idx_b2b_offers_buyer (buyer_player_id, status),
                KEY idx_b2b_offers_seller (seller_player_id, status),
                KEY idx_b2b_offers_price (price_per_bbl),
                KEY idx_b2b_offers_flagged (is_flagged, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS b2b_contract_terms (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                offer_id INT NOT NULL,
                term_key VARCHAR(80) NOT NULL,
                term_type ENUM('number','percent','minutes','text','bool') NOT NULL DEFAULT 'number',
                term_value DECIMAL(14,4) NULL,
                term_text VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_b2b_contract_term (offer_id, term_key),
                KEY idx_b2b_contract_terms_offer (offer_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS b2b_contract_logs (
                id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                offer_id INT NOT NULL,
                player_id INT NULL,
                event_key VARCHAR(64) NOT NULL,
                message VARCHAR(512) NOT NULL DEFAULT '',
                meta_json TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_b2b_logs_offer (offer_id, created_at),
                KEY idx_b2b_logs_player (player_id, created_at),
                KEY idx_b2b_logs_event (event_key, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS b2b_contract_config (
                config_key VARCHAR(80) NOT NULL PRIMARY KEY,
                config_value VARCHAR(255) NOT NULL,
                label VARCHAR(160) NOT NULL DEFAULT '',
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private static function migrateMysql(): void
    {
        Database::addColumnIfMissing('b2b_contract_offers', 'delivered_bbl', "DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER total_bbl");
        Database::addColumnIfMissing('b2b_contract_offers', 'is_flagged', "TINYINT(1) NOT NULL DEFAULT 0 AFTER cancel_reason");
        Database::addColumnIfMissing('b2b_contract_offers', 'flag_reason', "VARCHAR(255) NULL AFTER is_flagged");
    }

    private static function createSqlite(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS b2b_contract_offers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                buyer_player_id INTEGER NOT NULL,
                seller_player_id INTEGER NULL,
                status TEXT NOT NULL DEFAULT 'open',
                total_bbl REAL NOT NULL,
                delivered_bbl REAL NOT NULL DEFAULT 0.0,
                price_per_bbl REAL NOT NULL,
                total_value REAL NOT NULL,
                escrow_amount REAL NOT NULL DEFAULT 0.0,
                escrow_status TEXT NOT NULL DEFAULT 'none',
                cancel_penalty_pct REAL NOT NULL DEFAULT 10.0,
                cancel_penalty_amount REAL NOT NULL DEFAULT 0.0,
                refunded_amount REAL NOT NULL DEFAULT 0.0,
                min_seller_reputation INTEGER NOT NULL DEFAULT 0,
                expires_at TEXT NOT NULL,
                completed_at TEXT NULL,
                cancelled_at TEXT NULL,
                cancel_reason TEXT NULL,
                is_flagged INTEGER NOT NULL DEFAULT 0,
                flag_reason TEXT NULL,
                created_at TEXT,
                updated_at TEXT
            )"
        );
        $db->exec("CREATE INDEX IF NOT EXISTS idx_b2b_offers_status ON b2b_contract_offers (status, expires_at)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_b2b_offers_buyer ON b2b_contract_offers (buyer_player_id, status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_b2b_offers_seller ON b2b_contract_offers (seller_player_id, status)");

        $db->exec(
            "CREATE TABLE IF NOT EXISTS b2b_contract_terms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                offer_id INTEGER NOT NULL,
                term_key TEXT NOT NULL,
                term_type TEXT NOT NULL DEFAULT 'number',
                term_value REAL NULL,
                term_text TEXT NULL,
                created_at TEXT,
                UNIQUE (offer_id, term_key)
            )"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS b2b_contract_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                offer_id INTEGER NOT NULL,
                player_id INTEGER NULL,
                event_key TEXT NOT NULL,
                message TEXT NOT NULL DEFAULT '',
                meta_json TEXT NULL,
                created_at TEXT
            )"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS b2b_contract_config (
                config_key TEXT PRIMARY KEY,
                config_value TEXT NOT NULL,
                label TEXT NOT NULL DEFAULT '',
                updated_at TEXT NULL
            )"
        );
    }

    private static function seedConfig(PDO $db, string $driver): void
    {
        $defaults = [
            'module_enabled' => ['1', 'Modul B2B wlaczony'],
            'min_price_market_pct' => ['70', 'Minimalna cena wzgledem rynku'],
            'max_price_market_pct' => ['130', 'Maksymalna cena wzgledem rynku'],
            'min_bbl_per_offer' => ['100', 'Minimalna ilosc ropy w zleceniu'],
            'max_bbl_per_offer' => ['50000', 'Maksymalna ilosc ropy w zleceniu'],
            'max_open_offers_per_player' => ['5', 'Limit aktywnych zlecen na gracza'],
            'default_expiry_minutes' => ['1440', 'Domyslny czas waznosci zlecenia'],
            'min_expiry_minutes' => ['60', 'Minimalny czas waznosci'],
            'max_expiry_minutes' => ['10080', 'Maksymalny czas waznosci'],
            'buyer_cancel_penalty_pct' => ['10', 'Kara za anulowanie aktywnego zlecenia'],
            'admin_review_threshold_value' => ['5000000', 'Prog kontroli admina'],
            'flag_price_near_limit' => ['1', 'Oznaczaj ceny przy limicie'],
        ];

        foreach ($defaults as $key => [$value, $label]) {
            if ($driver === 'sqlite') {
                $db->prepare(
                    "INSERT OR IGNORE INTO b2b_contract_config (config_key, config_value, label, updated_at)
                     VALUES (?, ?, ?, ?)"
                )->execute([$key, $value, $label, date('Y-m-d H:i:s')]);
                continue;
            }
            $db->prepare(
                "INSERT INTO b2b_contract_config (config_key, config_value, label, updated_at)
                 SELECT ?, ?, ?, NOW()
                 FROM DUAL
                 WHERE NOT EXISTS (SELECT 1 FROM b2b_contract_config WHERE config_key = ?)"
            )->execute([$key, $value, $label, $key]);
        }
    }
}
