<?php
declare(strict_types=1);

/**
 * Simple file cache for lightweight config payloads.
 * PL: Prosty cache plikowy dla lekkich danych konfiguracyjnych.
 */
class Cache
{
    private static string $dir = __DIR__ . '/../cache/';

    public static function get(string $key): mixed
    {
        $file = self::$dir . $key . '.php';
        if (!file_exists($file)) {
            return null;
        }

        $data = include $file;
        if (!isset($data['expires']) || $data['expires'] < time()) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    public static function set(string $key, mixed $value, int $ttl = 120): void
    {
        if (!is_dir(self::$dir)) {
            mkdir(self::$dir, 0755, true);
        }

        $file = self::$dir . $key . '.php';
        $expires = time() + $ttl;
        $export = var_export($value, true);
        file_put_contents($file, "<?php return ['expires' => $expires, 'value' => $export];");
    }

    public static function delete(string $key): void
    {
        $file = self::$dir . $key . '.php';
        if (file_exists($file)) {
            unlink($file);
        }
    }

    public static function flush(): void
    {
        foreach (glob(self::$dir . '*.php') as $file) {
            unlink($file);
        }
    }
}
