<?php
declare(strict_types=1);

require_once __DIR__ . '/TickModule.php';
require_once __DIR__ . '/TickContext.php';

/**
 * TickRegistry - odkrywa moduly ticka z katalogu Modules.
 * TickRegistry - discovers tick modules from the Modules directory.
 */
final class TickRegistry
{
    /**
     * Cache modulow po katalogu / Module cache by directory.
     *
     * @var array<string, list<TickModule>>
     */
    private static array $cache = [];

    /**
     * Wykryj wszystkie moduly / Discover all modules.
     *
     * @return list<TickModule>
     */
    public static function discover(?string $modulesDir = null): array
    {
        $modulesDir = self::normalizeDir($modulesDir ?? (__DIR__ . '/Modules'));
        if (isset(self::$cache[$modulesDir])) {
            return self::$cache[$modulesDir];
        }

        if (!is_dir($modulesDir)) {
            self::$cache[$modulesDir] = [];
            return [];
        }

        $files = glob($modulesDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            require_once $file;
        }

        $modules = [];
        foreach (get_declared_classes() as $className) {
            $module = self::instantiateModuleFromDir($className, $modulesDir);
            if ($module !== null) {
                $modules[] = $module;
            }
        }

        usort(
            $modules,
            static function (TickModule $a, TickModule $b): int {
                return [$a->order(), $a->key()] <=> [$b->order(), $b->key()];
            }
        );

        self::assertUniqueKeys($modules);

        self::$cache[$modulesDir] = $modules;
        return $modules;
    }

    public static function find(string $key, ?string $modulesDir = null): ?TickModule
    {
        foreach (self::discover($modulesDir) as $module) {
            if ($module->key() === $key) {
                return $module;
            }
        }
        return null;
    }

    /**
     * Wykryj wybrane moduly / Discover selected modules.
     *
     * @param list<string> $keys
     * @return list<TickModule>
     */
    public static function discoverKeys(array $keys, ?string $modulesDir = null): array
    {
        $wanted = array_fill_keys($keys, true);
        $result = [];
        foreach (self::discover($modulesDir) as $module) {
            if (isset($wanted[$module->key()])) {
                $result[] = $module;
            }
        }
        return $result;
    }

    /**
     * Sprawdz klucze modulow / Validate module keys.
     *
     * @param list<TickModule> $modules
     */
    private static function assertUniqueKeys(array $modules): void
    {
        $seen = [];
        foreach ($modules as $module) {
            $key = $module->key();
            if ($key === '') {
                throw new RuntimeException('Tick module key cannot be empty.');
            }
            if (isset($seen[$key])) {
                throw new RuntimeException("Duplicate tick module key: {$key}");
            }
            $seen[$key] = true;
        }
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    private static function instantiateModuleFromDir(string $className, string $modulesDir): ?TickModule
    {
        try {
            $ref = new ReflectionClass($className);
            $fileName = $ref->getFileName();
            if ($fileName === false || !self::isPathInsideDir($fileName, $modulesDir)) {
                return null;
            }
            if (!$ref->isInstantiable() || !$ref->implementsInterface(TickModule::class)) {
                return null;
            }
            $ctor = $ref->getConstructor();
            if ($ctor !== null && $ctor->getNumberOfRequiredParameters() > 0) {
                return null;
            }
            $instance = $ref->newInstance();
            return $instance instanceof TickModule ? $instance : null;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('TickRegistry', 'module discovery FAILED', $e, ['class' => $className]);
            }
            return null;
        }
    }

    private static function normalizeDir(string $dir): string
    {
        $real = realpath($dir);
        return rtrim($real !== false ? $real : $dir, "\\/");
    }

    private static function isPathInsideDir(string $path, string $dir): bool
    {
        $pathReal = realpath($path);
        $dirReal = realpath($dir);
        if ($pathReal === false || $dirReal === false) {
            return false;
        }
        $prefix = rtrim($dirReal, "\\/") . DIRECTORY_SEPARATOR;
        return strncmp($pathReal, $prefix, strlen($prefix)) === 0;
    }
}
