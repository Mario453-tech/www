<?php
declare(strict_types=1);

/**
 * One-time bridge from mobile Bearer tokens to the regular web session.
 */
class MobileWebBridge
{
    private const TOKEN_BYTES = 32;
    private const TTL_SECONDS = 60;

    public static function ttlSeconds(): int
    {
        return self::TTL_SECONDS;
    }

    public static function ensureSchema(): void
    {
        $db = Database::getInstance()->getConnection();
        static $doneFor;
        if ($doneFor instanceof WeakMap && isset($doneFor[$db])) {
            return;
        }
        if (!($doneFor instanceof WeakMap)) {
            $doneFor = new WeakMap();
        }
        $doneFor[$db] = true;

        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $db->exec("CREATE TABLE IF NOT EXISTS mobile_web_bridge_tokens (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                token_hash TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                used_at TEXT NULL,
                created_ip TEXT NOT NULL DEFAULT '',
                used_ip TEXT NULL,
                user_agent TEXT NULL
            )");
            return;
        }

        $db->exec("CREATE TABLE IF NOT EXISTS `mobile_web_bridge_tokens` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `player_id` INT NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME NULL,
            `created_ip` VARCHAR(45) NOT NULL DEFAULT '',
            `used_ip` VARCHAR(45) NULL,
            `user_agent` VARCHAR(255) NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_mobile_web_bridge_hash` (`token_hash`),
            KEY `idx_mobile_web_bridge_player` (`player_id`),
            KEY `idx_mobile_web_bridge_expires` (`expires_at`),
            CONSTRAINT `fk_mobile_web_bridge_player`
                FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public static function createForPlayer(int $playerId, string $baseUrl): string
    {
        self::ensureSchema();
        self::deleteExpired();

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        $hash = hash('sha256', $token);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $createdAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + self::TTL_SECONDS);

        Database::getInstance()->getConnection()
            ->prepare("
                INSERT INTO mobile_web_bridge_tokens
                    (player_id, token_hash, created_at, expires_at, created_ip, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ")
            ->execute([$playerId, $hash, $createdAt, $expiresAt, $ip, $ua]);

        return rtrim($baseUrl, '/') . '/mobile-bridge-login?token=' . rawurlencode($token);
    }

    /** @return array<string,mixed>|null */
    public static function consume(string $token): ?array
    {
        self::ensureSchema();

        if (strlen($token) !== self::TOKEN_BYTES * 2 || !ctype_xdigit($token)) {
            return null;
        }

        $db = Database::getInstance()->getConnection();
        $hash = hash('sha256', $token);
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("
            SELECT b.id, p.id AS player_id, p.username, p.email
              FROM mobile_web_bridge_tokens b
              JOIN players p ON p.id = b.player_id
             WHERE b.token_hash = ?
               AND b.used_at IS NULL
               AND b.expires_at > ?
               AND p.status = 'active'
               AND COALESCE(p.email_verified, 1) = 1
             LIMIT 1
        ");
        $stmt->execute([$hash, $now]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $updated = $db->prepare("
            UPDATE mobile_web_bridge_tokens
               SET used_at = ?, used_ip = ?
             WHERE id = ? AND used_at IS NULL AND expires_at > ?
        ");
        $updated->execute([$now, $_SERVER['REMOTE_ADDR'] ?? '', $row['id'], $now]);
        if ($updated->rowCount() !== 1) {
            return null;
        }

        return [
            'id' => (int)$row['player_id'],
            'username' => (string)$row['username'],
            'email' => (string)($row['email'] ?? ''),
        ];
    }

    private static function deleteExpired(): void
    {
        try {
            Database::getInstance()->getConnection()
                ->prepare("DELETE FROM mobile_web_bridge_tokens WHERE expires_at < ?")
                ->execute([date('Y-m-d H:i:s', time() - 86400)]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::warn('MobileWebBridge', 'Expired bridge cleanup failed', ['error' => $e->getMessage()]);
            }
        }
    }
}
