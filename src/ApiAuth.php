<?php

/**
 * ApiAuth: weryfikacja Bearer tokenu dla aplikacji mobilnej.
 * ApiAuth: Bearer token verification for the mobile app.
 *
 * Tokeny sa przechowywane w tabeli api_tokens.
 * Aplikacja mobilna wysyla naglowek: Authorization: Bearer <token>
 * Tokens are stored in the api_tokens table.
 * The mobile app sends the header: Authorization: Bearer <token>
 */
class ApiAuth
{
    private const TOKEN_BYTES     = 32; // 64 znaki hex / 64 hex chars
    private const EXPIRES_DAYS    = 90;

    /**
     * Pobiera gracza na podstawie Bearer tokenu z naglowka Authorization.
     * Returns player row based on Bearer token from Authorization header.
     *
     * @return array<string,mixed>|null  null gdy token nieobecny lub nieprawidlowy
     */
    public static function getPlayerFromRequest(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? getallheaders()['Authorization']
            ?? getallheaders()['authorization']
            ?? '';

        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return null;
        }
        $token = $m[1];
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null; // oczywiscie bledny format / obviously wrong format
        }

        $db   = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT p.id, p.username, p.email, p.cash, p.status, p.financial_state,
                   p.crisis_ticks, p.credit_score, p.offline_mode, p.last_tick_at,
                   p.safety_procedures_level, p.procedure_integrity
              FROM api_tokens t
              JOIN players    p ON p.id = t.player_id
             WHERE t.token = ?
               AND p.status = 'active'
               AND (t.expires_at IS NULL OR t.expires_at > NOW())
             LIMIT 1
        ");
        $stmt->execute([$token]);
        $player = $stmt->fetch() ?: null;

        if ($player) {
            // Aktualizuj last_used_at asynchronicznie (best-effort, brak wylatku)
            // Update last_used_at asynchronously (best-effort, no throw)
            try {
                $db->prepare("UPDATE api_tokens SET last_used_at = NOW() WHERE token = ?")
                   ->execute([$token]);
            } catch (Throwable) {}
        }

        return $player;
    }

    /**
     * Generuje nowy token dla gracza i zapisuje go w DB.
     * Generates a new token for the player and saves it to DB.
     */
    public static function generateToken(int $playerId, ?string $device = null): string
    {
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        Database::getInstance()->getConnection()
            ->prepare("
                INSERT INTO api_tokens (player_id, token, device, created_at, expires_at)
                VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL :days DAY))
            ")
            ->execute([$playerId, $token, $device, self::EXPIRES_DAYS]);

        return $token;
    }

    /**
     * Uniewa zniony token.
     * Revokes a token.
     */
    public static function revokeToken(string $token): void
    {
        Database::getInstance()->getConnection()
            ->prepare("DELETE FROM api_tokens WHERE token = ?")
            ->execute([$token]);
    }

    /**
     * Usuwa wszystkie tokeny gracza (np. przy zmianie hasla).
     * Removes all tokens for a player (e.g. on password change).
     */
    public static function revokeAllForPlayer(int $playerId): void
    {
        Database::getInstance()->getConnection()
            ->prepare("DELETE FROM api_tokens WHERE player_id = ?")
            ->execute([$playerId]);
    }

    /**
     * Tworzy tabele api_tokens jesli nie istnieje (raz na proces).
     * Creates the api_tokens table if it does not exist (once per process).
     */
    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            $db = Database::getInstance()->getConnection();

            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                return;
            }

            $db->exec("CREATE TABLE IF NOT EXISTS `api_tokens` (
                `id`           INT          NOT NULL AUTO_INCREMENT,
                `player_id`    INT          NOT NULL,
                `token`        VARCHAR(64)  NOT NULL,
                `device`       VARCHAR(200) NULL COMMENT 'opcjonalny opis urzadzenia / optional device description',
                `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `last_used_at` DATETIME     NULL,
                `expires_at`   DATETIME     NULL COMMENT 'NULL = nigdy nie wygasa / NULL = never expires',
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_token`   (`token`),
                KEY        `idx_player` (`player_id`),
                CONSTRAINT `fk_api_tokens_player`
                    FOREIGN KEY (`player_id`) REFERENCES `players` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::warn('ApiAuth', 'ensureSchema skipped', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Wyciaga surowy token z naglowka Authorization (bez walidacji DB).
     * Extracts raw token from Authorization header (no DB validation).
     */
    public static function getRawToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? getallheaders()['Authorization']
            ?? getallheaders()['authorization']
            ?? '';
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return null;
        }
        return $m[1];
    }
}
