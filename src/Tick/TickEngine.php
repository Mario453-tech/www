<?php
declare(strict_types=1);

require_once __DIR__ . '/TickRegistry.php';
require_once __DIR__ . '/TickRunResult.php';

final class TickEngine
{
    public function __construct(
        private readonly ?string $modulesDir = null,
        private readonly ?TickModuleScheduler $scheduler = null
    ) {
    }

    public function runAll(TickContext $ctx, bool $force = false): TickRunResult
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

        if ($this->scheduler !== null) {
            if ($ctx->runSequence <= 0) {
                $result->addConfigurationFailure('Tick scheduler requires a positive run sequence.');
                return $result->finish($ctx);
            }

            try {
                $this->scheduler->sync($modules);
            } catch (Throwable $e) {
                $result->addConfigurationFailure($e->getMessage());
                $this->logRegistryFailure($e);
                return $result->finish($ctx);
            }
        }

        foreach ($modules as $module) {
            $ran = $this->runScheduled($module, $ctx, $result, $force);
            if ($result->hasCriticalFailure()) {
                break;
            }
            if (!$ran) {
                continue;
            }
        }

        return $result->finish($ctx);
    }

    public function runOne(
        string $key,
        TickContext $ctx,
        ?TickRunResult $result = null,
        bool $force = true
    ): TickRunResult
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

        if ($this->scheduler !== null) {
            try {
                $this->scheduler->sync([$module]);
            } catch (Throwable $e) {
                $result->addConfigurationFailure($e->getMessage());
                $this->logRegistryFailure($e);
                return $result->finish($ctx);
            }
        }

        $this->runScheduled($module, $ctx, $result, $force);
        return $result->finish($ctx);
    }

    private function runScheduled(
        TickModule $module,
        TickContext $ctx,
        TickRunResult $result,
        bool $forced
    ): bool {
        if ($this->scheduler === null) {
            $this->execute($module, $ctx, $result);
            return true;
        }

        try {
            $decision = $this->scheduler->decision($module, $ctx, $forced);
            $ctx->setModuleLimit($module->key(), (int)$decision['limit']);
            if (!$decision['run']) {
                $status = (string)$decision['status'];
                $this->scheduler->markNotRun($module, $ctx, $status);
                if ($module->failurePolicy() === TickFailurePolicy::STOP) {
                    $this->recordSchedulingFailure(
                        $module,
                        $ctx,
                        $result,
                        new RuntimeException("Critical tick module cannot be {$status}: {$module->key()}")
                    );
                    return false;
                }
                $result->addSkipped($module, $status);
                $ctx->recordModuleRun($module->key(), $module->order(), $status, 0, []);
                return false;
            }

            $this->scheduler->markStarted($module, $ctx);
        } catch (Throwable $e) {
            $this->recordSchedulingFailure($module, $ctx, $result, $e);
            return false;
        }

        $this->execute($module, $ctx, $result);
        $run = $result->moduleRuns[$module->key()] ?? null;
        if ($run !== null) {
            try {
                $this->scheduler->markFinished($module, $ctx, $run, $forced);
            } catch (Throwable $e) {
                if (class_exists('GameLog', false)) {
                    GameLog::error('TickEngine', 'module result persistence FAILED', $e, ['module' => $module->key()]);
                }
            }
        }
        return true;
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

    private function recordSchedulingFailure(
        TickModule $module,
        TickContext $ctx,
        TickRunResult $result,
        Throwable $error
    ): void {
        $ctx->recordModuleRun(
            $module->key(),
            $module->order(),
            TickRunResult::STATUS_FAILED,
            0,
            [],
            mb_substr($error->getMessage(), 0, 500)
        );
        $result->addFailure($module, 0, $error);
        if ($this->scheduler !== null) {
            try {
                $this->scheduler->markFinished($module, $ctx, [
                    'status' => TickRunResult::STATUS_FAILED,
                    'duration_ms' => 0,
                    'stats' => [],
                    'error' => mb_substr($error->getMessage(), 0, 500),
                ], false);
            } catch (Throwable) {
            }
        }
        if (class_exists('GameLog', false)) {
            GameLog::error('TickEngine', 'module scheduling FAILED', $error, ['module' => $module->key()]);
        }
    }
}
