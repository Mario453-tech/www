<?php

/**
 * One-time bankruptcy schema migrations and request guards.
 * PL: Jednorazowe migracje schematu bankructwa i guardy zapytan.
 *
 * Called from init.php after all services are loaded.
 * PL: Wywolywane z init.php po zaladowaniu wszystkich serwisow.
 */

if (!function_exists('ensureBankruptcyRecoverySchema')) {
    function ensureBankruptcyRecoverySchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            $db = Database::getInstance()->getConnection();

            Database::addColumnIfMissing('players', 'bankruptcy_status', "ENUM('none','restructuring','liquidation','recovered') NOT NULL DEFAULT 'none' AFTER bankruptcy_at");
            Database::addColumnIfMissing('players', 'recovery_mode', "TINYINT(1) NOT NULL DEFAULT 0 AFTER bankruptcy_status");
            Database::addColumnIfMissing('players', 'last_crisis_tick_at', "DATETIME NULL DEFAULT NULL");

            $db->exec("CREATE TABLE IF NOT EXISTS bankruptcy_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                player_id INT NOT NULL,
                event_type VARCHAR(64) NOT NULL,
                message VARCHAR(500) NULL,
                severity ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
                is_critical TINYINT(1) NOT NULL DEFAULT 0,
                due_at DATETIME NULL,
                resolved_at DATETIME NULL,
                resolution_note VARCHAR(500) NULL,
                payload_json JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_player_created (player_id, created_at),
                INDEX idx_player_critical_open (player_id, is_critical, resolved_at, due_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $db->exec("UPDATE players SET recovery_mode = 1, bankruptcy_status = 'restructuring' WHERE status = 'bankrupt' AND (COALESCE(recovery_mode, 0) = 0 OR COALESCE(bankruptcy_status, 'none') = 'none')");
        } catch (Throwable $e) {
            GameLog::warn('init', 'Bankruptcy schema bootstrap skipped', ['error' => $e->getMessage()]);
        }
    }
}

if (!function_exists('isPlayerInBankruptcyMode')) {
    function isPlayerInBankruptcyMode(PDO $db, int $playerId): bool
    {
        try {
            $stmt = $db->prepare("
                SELECT status,
                       COALESCE(recovery_mode, 0) AS recovery_mode,
                       COALESCE(bankruptcy_status, 'none') AS bankruptcy_status
                FROM players WHERE id = ? LIMIT 1
            ");
            $stmt->execute([$playerId]);
            $row = $stmt->fetch();
            if (!$row) {
                return false;
            }

            $status = (string)($row['status'] ?? 'active');
            $bankruptcyStatus = (string)($row['bankruptcy_status'] ?? 'none');

            // Do not check bankruptcy_at, it is a historical timestamp kept after recovery.
            // PL: Nie sprawdzamy bankruptcy_at, bo to historyczny timestamp po recovery.
            return $status === 'bankrupt'
                || (int)($row['recovery_mode'] ?? 0) === 1
                || ($bankruptcyStatus !== 'none' && $bankruptcyStatus !== 'recovered');
        } catch (Throwable $e) {
            GameLog::error('init', 'isPlayerInBankruptcyMode failed', $e, ['player_id' => $playerId]);
            return false;
        }
    }
}

if (!function_exists('enforceBankruptcyPostGuards')) {
    function enforceBankruptcyPostGuards(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $playerId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
        if ($playerId <= 0) {
            return;
        }

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        try {
            $db = Database::getInstance()->getConnection();
            if (!isPlayerInBankruptcyMode($db, $playerId)) {
                return;
            }

            $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
            $blockedScripts = ['upgrade_storage.php', 'upgrade_well.php', 'well_shop.php', 'hr.php', 'technical.php'];
            if (in_array($script, $blockedScripts, true)) {
                $_SESSION['bankruptcy_notice'] = t('bankruptcy.guard_notice');
                GameLog::warn('init', 'Blocked risky POST during bankruptcy', ['player_id' => $playerId, 'script' => $script]);
                if (!headers_sent()) {
                    header('Location: /recovery');
                }
                exit();
            }
        } catch (Throwable $e) {
            GameLog::error('init', 'enforceBankruptcyPostGuards failed', $e, ['player_id' => $playerId]);
        }
    }
}
