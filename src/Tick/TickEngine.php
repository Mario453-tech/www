<?php
declare(strict_types=1);

require_once __DIR__ . '/TickRegistry.php';
require_once __DIR__ . '/TickRunResult.php';

final class TickEngine
{
    public function __construct(private readonly ?string $modulesDir = null)
    {
    }

    public function runAll(TickContext $ctx): TickRunResult
    {
        $result = new TickRunResult($ctx);

        try {
            $modules = TickRegistry::discover($this->modulesDir);
        } catch (Throwable $e) {
            $result->addConfigurationFailure($e->getMessage());
            $this->logRegistryFailure($e);
            return $result->finish($ctx);
        }

        if ($modules === []) {
            $result->addConfigurationFailure('No tick modules discovered.');
            return $result->finish($ctx);
        }

        foreach ($modules as $module) {
            $this->execute($module, $ctx, $result);
            if ($result->hasCriticalFailure()) {
                break;
            }
        }

        return $result->finish($ctx);
    }

    public function runOne(string $key, TickContext $ctx, ?TickRunResult $result = null): TickRunResult
    {
        $result ??= new TickRunResult($ctx);

        try {
            $module = TickRegistry::find($key, $this->modulesDir);
        } catch (Throwable $e) {
            $result->addConfigurationFailure($e->getMessage());
            $this->logRegistryFailure($e);
            return $result->finish($ctx);
        }

        if ($module === null) {
            $message = "Tick module not found: {$key}";
            $result->addConfigurationFailure($message);
            if (class_exists('GameLog', false)) {
                GameLog::error('TickEngine', $message);
            }
            return $result->finish($ctx);
        }

        $this->execute($module, $ctx, $result);
        return $result->finish($ctx);
    }

    private function execute(TickModule $module, TickContext $ctx, TickRunResult $result): void
    {
        $startedAt = microtime(true);
        if (class_exists('GameLog', false)) {
            GameLog::info('TickEngine', 'module START', [
                'module' => $module->key(),
                'order' => $module->order(),
                'policy' => $module->failurePolicy()->value,
            ]);
        }

        try {
            $module->run($ctx);
            $stats = $module->stats();
            $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
            $ctx->mergeStats($module->key(), $stats);
            $ctx->recordModuleRun($module->key(), $module->order(), TickRunResult::STATUS_SUCCESS, $durationMs, $stats);
            $result->addSuccess($module, $durationMs, $stats);

            if (class_exists('GameLog', false)) {
                GameLog::info('TickEngine', 'module OK', [
                    'module' => $module->key(),
                    'duration_ms' => $durationMs,
                    'stats_keys' => array_keys($stats),
                ]);
            }
        } catch (Throwable $e) {
            $durationMs = (int)round((microtime(true) - $startedAt) * 1000);
            if ($ctx->db->inTransaction()) {
                try {
                    $ctx->db->rollBack();
                } catch (Throwable) {
                }
            }

            $ctx->recordModuleRun(
                $module->key(),
                $module->order(),
                TickRunResult::STATUS_FAILED,
                $durationMs,
                [],
                mb_substr($e->getMessage(), 0, 500)
            );
            $result->addFailure($module, $durationMs, $e);

            if (class_exists('GameLog', false)) {
                GameLog::error('TickEngine', 'module FAILED', $e, [
                    'module' => $module->key(),
                    'order' => $module->order(),
                    'policy' => $module->failurePolicy()->value,
                    'duration_ms' => $durationMs,
                ]);
            }
        }
    }

    private function logRegistryFailure(Throwable $e): void
    {
        if (class_exists('GameLog', false)) {
            GameLog::error('TickEngine', 'registry FAILED', $e);
        }
    }
}
