<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiAuthRateLimitBootstrap.php';

final class ApiAuthRateLimiter
{
    private const RETENTION_BATCH_SIZE = 64;

    public function __construct(private PDO $db)
    {
        ensureApiAuthRateLimitSchema($db);
    }

    public static function normalizeIdentifier(mixed $value): ?string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }
        $value = mb_strtolower(trim($value), 'UTF-8');
        if ($value === '' || strlen($value) > 254 || preg_match('/[\p{C}\s]/u', $value)) {
            return null;
        }
        if (str_contains($value, '@') && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        if (!str_contains($value, '@') && !preg_match('/^[a-z0-9_]{3,20}$/D', $value)) {
            return null;
        }
        return $value;
    }

    public static function normalizeIp(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            throw new InvalidArgumentException('Invalid client IP address');
        }
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            $packed = substr($packed, 12);
        }
        return bin2hex($packed);
    }

    public function pruneExpired(?int $now = null): int
    {
        if ($this->db->inTransaction()) {
            throw new LogicException('API rate limit retention requires autocommit');
        }
        $now ??= time();
        $stmt = $this->db->prepare('SELECT bucket_key, attempts FROM api_auth_rate_limits
            WHERE expires_at IS NULL OR expires_at <= ? ORDER BY expires_at LIMIT ' . self::RETENTION_BATCH_SIZE);
        $stmt->execute([$now]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $deleted = 0;
        foreach ($rows as $row) {
            $attempts = json_decode($row['attempts'], true, 512, JSON_THROW_ON_ERROR);
            $expires = $attempts === [] ? 0 : max($attempts) + 900;
            // Compare the snapshot as well as expiry: a concurrent renewal must survive.
            // Porownaj migawke oraz termin: rownolegle odnowiony wpis musi przetrwac.
            if ($expires <= $now) {
                $delete = $this->db->prepare('DELETE FROM api_auth_rate_limits WHERE bucket_key = ?
                    AND attempts = ? AND (expires_at IS NULL OR expires_at <= ?)');
                $delete->execute([$row['bucket_key'], $row['attempts'], $now]);
                $deleted += $delete->rowCount();
            } else {
                $this->db->prepare('UPDATE api_auth_rate_limits SET expires_at = ? WHERE bucket_key = ?
                    AND attempts = ? AND (expires_at IS NULL OR expires_at <= ?)')
                    ->execute([$expires, $row['bucket_key'], $row['attempts'], $now]);
            }
        }
        return $deleted;
    }

    public function consume(string $identifier, string $ip, ?int $playerId = null, ?int $now = null): int
    {
        $identifier = self::normalizeIdentifier($identifier) ?? throw new InvalidArgumentException('Invalid login identifier');
        $ip = self::normalizeIp($ip);
        if ($playerId !== null && $playerId <= 0) {
            throw new InvalidArgumentException('Invalid player ID');
        }
        if ($this->db->inTransaction()) {
            throw new LogicException('API rate limiter requires its own transaction');
        }
        $identity = $playerId === null ? 'login:' . $identifier : 'player:' . $playerId;
        $buckets = [hash('sha256', 'ip:' . $ip) => 30, hash('sha256', $identity . '|ip:' . $ip) => 5];
        $mysql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $now ??= time();
        $this->pruneExpired($now);
        $this->db->beginTransaction();
        try {
            $rows = [];
            $ipBlocked = false;
            // Lock IP first; SQLite's upsert obtains its write lock before reads.
            // Blokuj najpierw IP; upsert SQLite uzyskuje blokade zapisu przed odczytem.
            foreach ($buckets as $key => $limit) {
                $sql = $mysql
                    ? "INSERT INTO api_auth_rate_limits (bucket_key, attempts) VALUES (?, '[]') ON DUPLICATE KEY UPDATE bucket_key = bucket_key"
                    : "INSERT INTO api_auth_rate_limits (bucket_key, attempts) VALUES (?, '[]') ON CONFLICT(bucket_key) DO UPDATE SET bucket_key = excluded.bucket_key";
                if (!$ipBlocked) {
                    $this->db->prepare($sql)->execute([$key]);
                }
                $stmt = $this->db->prepare('SELECT attempts FROM api_auth_rate_limits WHERE bucket_key = ?' . ($mysql ? ' FOR UPDATE' : ''));
                $stmt->execute([$key]);
                $stored = $stmt->fetchColumn();
                $rows[$key] = $stored === false ? [] : json_decode((string) $stored, true, 512, JSON_THROW_ON_ERROR);
                $rows[$key] = array_values(array_filter($rows[$key], static fn(int $time): bool => $time > $now - 900));
                // A blocked IP must not create unlimited account buckets.
                // Zablokowane IP nie moze tworzyc nieograniczonej liczby koszykow kont.
                if ($limit === 30 && count($rows[$key]) >= $limit) {
                    $ipBlocked = true;
                }
            }
            $retry = 0;
            foreach ($buckets as $key => $limit) {
                if (count($rows[$key]) >= $limit) {
                    $retry = max($retry, min($rows[$key]) + 900 - $now);
                }
            }
            if ($retry === 0) {
                foreach ($rows as $key => $attempts) {
                    $attempts[] = $now;
                    $this->db->prepare('UPDATE api_auth_rate_limits SET attempts = ?, expires_at = ? WHERE bucket_key = ?')
                        ->execute([json_encode($attempts, JSON_THROW_ON_ERROR), max($attempts) + 900, $key]);
                }
            }
            $this->db->commit();
            return $retry;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }
}
