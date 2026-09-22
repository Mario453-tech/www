<?php
declare(strict_types=1);

/** @phpstan-impure */
function apiAuthRateLimitHasMetadata(PDO $db, string $query, string $field, string $name): bool
{
    return in_array($name, array_column($db->query($query)->fetchAll(PDO::FETCH_ASSOC), $field), true);
}

function ensureApiAuthRateLimitSchema(PDO $db): void
{
    if ($db->inTransaction()) {
        throw new LogicException('API rate limit schema must be initialized before transactions');
    }
    $mysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $db->exec('CREATE TABLE IF NOT EXISTS api_auth_rate_limits (
        bucket_key CHAR(64) NOT NULL PRIMARY KEY,
        attempts TEXT NOT NULL,
        expires_at BIGINT NULL
    )' . ($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin' : ''));
    $columnQuery = $mysql ? 'SHOW COLUMNS FROM api_auth_rate_limits' : 'PRAGMA table_info(api_auth_rate_limits)';
    $columnField = $mysql ? 'Field' : 'name';
    if (!apiAuthRateLimitHasMetadata($db, $columnQuery, $columnField, 'expires_at')) {
        try {
            $db->exec('ALTER TABLE api_auth_rate_limits ADD COLUMN expires_at BIGINT NULL');
        } catch (PDOException $error) {
            if (!apiAuthRateLimitHasMetadata($db, $columnQuery, $columnField, 'expires_at')) {
                throw $error;
            }
        }
    }
    $indexQuery = $mysql ? 'SHOW INDEX FROM api_auth_rate_limits' : 'PRAGMA index_list(api_auth_rate_limits)';
    $indexField = $mysql ? 'Key_name' : 'name';
    if (!apiAuthRateLimitHasMetadata($db, $indexQuery, $indexField, 'idx_api_auth_rate_limits_expiry')) {
        try {
            $db->exec('CREATE INDEX idx_api_auth_rate_limits_expiry ON api_auth_rate_limits (expires_at)');
        } catch (PDOException $error) {
            if (!apiAuthRateLimitHasMetadata($db, $indexQuery, $indexField, 'idx_api_auth_rate_limits_expiry')) {
                throw $error;
            }
        }
    }
}
