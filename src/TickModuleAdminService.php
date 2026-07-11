<?php
declare(strict_types=1);

require_once __DIR__ . '/Tick/TickModuleCatalog.php';
require_once __DIR__ . '/Tick/TickModuleConfigRepository.php';
require_once __DIR__ . '/Tick/TickRegistry.php';
require_once __DIR__ . '/Tick/TickStatsRepository.php';

final class TickModuleAdminService
{
    private TickModuleConfigRepository $configRepository;

    public function __construct(private readonly PDO $db)
    {
        $this->configRepository = new TickModuleConfigRepository($db);
    }

    /** @return list<array<string,mixed>> */
    public function modules(): array
    {
        $modules = TickRegistry::discover();
        $this->configRepository->syncModules($modules);

        $configs = [];
        foreach ($this->configRepository->all() as $row) {
            $configs[(string)$row['module_key']] = $row;
        }

        $rows = [];
        foreach ($modules as $module) {
            $key = $module->key();
            $config = $configs[$key] ?? [];
            $rows[] = [
                'key' => $key,
                'label_key' => $this->labelKey($key),
                'order' => $module->order(),
                'policy' => $module->failurePolicy()->value,
                'critical' => TickModuleCatalog::isCritical($key),
                'enabled' => (int)($config['enabled'] ?? 1) === 1,
                'interval_ticks' => (int)($config['interval_ticks'] ?? TickModuleCatalog::recommendedInterval($key)),
                'max_items_per_run' => (int)($config['max_items_per_run'] ?? TickModuleCatalog::recommendedLimit($key)),
                'recommended_interval' => TickModuleCatalog::recommendedInterval($key),
                'recommended_limit' => TickModuleCatalog::recommendedLimit($key),
                'last_run_tick' => (int)($config['last_run_tick'] ?? 0),
                'last_run_at' => $config['last_run_at'] ?? null,
                'last_duration_ms' => $config['last_duration_ms'] ?? null,
                'last_status' => (string)($config['last_status'] ?? TickModuleConfigRepository::STATUS_NEVER),
                'last_error' => $config['last_error'] ?? null,
            ];
        }

        return $rows;
    }

    public function updateSettings(string $moduleKey, bool $enabled, int $intervalTicks, int $maxItems): void
    {
        $module = $this->findModule($moduleKey);
        if (TickModuleCatalog::isCritical($module->key())) {
            $enabled = true;
        }

        $this->configRepository->update(
            $module->key(),
            $enabled,
            max(1, min(100000, $intervalTicks)),
            max(1, min(1000000, $maxItems))
        );
    }

    public function restoreRecommended(string $moduleKey): void
    {
        $module = $this->findModule($moduleKey);
        $this->configRepository->restoreRecommended($module->key());
    }

    public function assertModuleExists(string $moduleKey): void
    {
        $this->findModule($moduleKey);
    }

    /** @return list<array<string,mixed>> */
    public function recentLogs(?string $moduleKey = null, int $limit = 80): array
    {
        $rows = $this->configRepository->recentLogs($moduleKey, $limit);
        foreach ($rows as &$row) {
            $row['label_key'] = $this->labelKey((string)$row['module_key']);
            $row['stats'] = $this->decodeJson($row['stats_json'] ?? null);
        }
        unset($row);
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    public function recentTickStats(int $limit = 12): array
    {
        new TickStatsRepository();

        $stmt = $this->db->prepare(
            'SELECT id, ran_at, tick_sequence, source, duration_ms, module_stats_data, module_runs_data
               FROM tick_stats
              WHERE module_stats_data IS NOT NULL OR module_runs_data IS NOT NULL
              ORDER BY id DESC
              LIMIT ?'
        );
        $stmt->bindValue(1, max(1, min(50, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['module_stats'] = $this->decodeJson($row['module_stats_data'] ?? null);
            $row['module_runs'] = $this->decodeJson($row['module_runs_data'] ?? null);
        }
        unset($row);

        return $rows;
    }

    public function labelKey(string $moduleKey): string
    {
        return 'admin.tick_modules.module.' . $moduleKey;
    }

    private function findModule(string $moduleKey): TickModule
    {
        $module = TickRegistry::find($moduleKey);
        if ($module === null) {
            throw new InvalidArgumentException("Unknown tick module: {$moduleKey}");
        }
        return $module;
    }

    /** @return array<string,mixed> */
    private function decodeJson(mixed $json): array
    {
        if (!is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
