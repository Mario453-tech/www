<?php
/**
 * Odczyt i zapis ustawien modulu prywatnosci z tabeli privacy_settings.
 */
class PrivacySettingsService
{
    private array $cache = [];

    public function __construct(private readonly PDO $db) {}

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT setting_value, value_type FROM privacy_settings WHERE setting_key = ? LIMIT 1"
            );
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return $default;
        }
        if (!$row) {
            return $default;
        }
        $value = $this->cast($row['setting_value'], $row['value_type']);
        $this->cache[$key] = $value;
        return $value;
    }

    public function set(string $key, mixed $value, string $type = 'string'): void
    {
        $stored = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json'    => is_string($value) ? $value : json_encode($value),
            default   => (string)$value,
        };
        try {
            $this->db->prepare("
                INSERT INTO privacy_settings (setting_key, setting_value, value_type)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type)
            ")->execute([$key, $stored, $type]);
        } catch (Throwable $e) {
            GameLog::error('Privacy', 'PrivacySettingsService::set FAILED', $e);
        }
        unset($this->cache[$key]);
    }

    public function getMany(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key);
        }
        return $result;
    }

    /** Zwraca wszystkie ustawienia jako tablice klucz => wartosc. */
    public function all(): array
    {
        try {
            $rows = $this->db->query(
                "SELECT setting_key, setting_value, value_type FROM privacy_settings ORDER BY setting_key"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $this->cast($row['setting_value'], $row['value_type']);
        }
        return $result;
    }

    private function cast(string $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => (bool)(int)$value,
            'integer' => (int)$value,
            'json'    => json_decode($value, true),
            default   => $value,
        };
    }
}
