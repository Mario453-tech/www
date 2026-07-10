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
    /** @var array<string, list<class-string<TickModule>>> */
    private static array $cache = [];

    /**
     * Wykryj wszystkie moduly / Discover all modules.
     *
     * @return list<TickModule>
     */
    public static function discover(?string $modulesDir = null): array
    {
        $modulesDir = self::normalizeDir($modulesDir ?? (__DIR__ . '/Modules'));
        $classes = self::$cache[$modulesDir] ??= self::discoverClasses($modulesDir);

        return array_map(
            static fn(string $className): TickModule => new $className(),
            $classes
        );
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

    /** @return list<class-string<TickModule>> */
    private static function discoverClasses(string $modulesDir): array
    {
        if (!is_dir($modulesDir)) {
            return [];
        }

        $files = glob($modulesDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        sort($files, SORT_STRING);

        $metadata = [];
        foreach ($files as $file) {
            $className = pathinfo($file, PATHINFO_FILENAME);
            require_once $file;

            if (!class_exists($className, false)) {
                throw new RuntimeException("Tick module file must define class {$className}: {$file}");
            }

            $classesInFile = array_values(array_filter(
                get_declared_classes(),
                static function (string $declaredClass) use ($file): bool {
                    $classFile = (new ReflectionClass($declaredClass))->getFileName();
                    return $classFile !== false && realpath($classFile) === realpath($file);
                }
            ));
            if ($classesInFile !== [$className]) {
                throw new RuntimeException("Tick module file must define exactly one matching class: {$file}");
            }

            $reflection = new ReflectionClass($className);
            if (!$reflection->isInstantiable() || !$reflection->implementsInterface(TickModule::class)) {
                throw new RuntimeException("Tick module class must implement TickModule: {$className}");
            }
            if (!self::isPathInsideDir((string)$reflection->getFileName(), $modulesDir)) {
                throw new RuntimeException("Tick module class is declared outside its module directory: {$className}");
            }

            $constructor = $reflection->getConstructor();
            if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
                throw new RuntimeException("Tick module constructor cannot require arguments: {$className}");
            }

            /** @var TickModule $module */
            $module = $reflection->newInstance();
            $key = trim($module->key());
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                throw new RuntimeException("Invalid tick module key: {$key}");
            }
            if ($module->order() < 1) {
                throw new RuntimeException("Tick module order must be positive: {$className}");
            }

            $metadata[] = [
                'class' => $className,
                'key' => $key,
                'order' => $module->order(),
            ];
        }

        usort($metadata, static fn(array $a, array $b): int => [$a['order'], $a['key']] <=> [$b['order'], $b['key']]);
        self::assertUniqueMetadata($metadata);

        return array_values(array_map(static fn(array $row): string => $row['class'], $metadata));
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /** @param list<array{class:string,key:string,order:int}> $metadata */
    private static function assertUniqueMetadata(array $metadata): void
    {
        $keys = [];
        $orders = [];
        foreach ($metadata as $row) {
            if (isset($keys[$row['key']])) {
                throw new RuntimeException("Duplicate tick module key: {$row['key']}");
            }
            if (isset($orders[$row['order']])) {
                throw new RuntimeException("Duplicate tick module order: {$row['order']}");
            }
            $keys[$row['key']] = true;
            $orders[$row['order']] = true;
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
