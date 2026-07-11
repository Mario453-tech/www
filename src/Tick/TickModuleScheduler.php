<?php
declare(strict_types=1);

require_once __DIR__ . '/TickModuleConfigRepository.php';

final class TickModuleScheduler
{
    public function __construct(private readonly TickModuleConfigRepository $repository)
    {
    }

    /** @param list<TickModule> $modules */
    public function sync(array $modules): void
    {
        $this->repository->syncModules($modules);
    }

    /** @return array{run:bool,status:string,limit:int} */
    public function decision(TickModule $module, TickContext $ctx, bool $forced = false): array
    {
        $config = $this->repository->find($module->key());
        if ($config === null) {
            throw new RuntimeException("Missing tick module configuration: {$module->key()}");
        }

        $limit = max(1, (int)($config['max_items_per_run'] ?? 200));
        if ((int)($config['enabled'] ?? 0) !== 1) {
            return ['run' => false, 'status' => TickModuleConfigRepository::STATUS_DISABLED, 'limit' => $limit];
        }

        $interval = max(1, (int)($config['interval_ticks'] ?? 1));
        $lastRun = max(0, (int)($config['last_run_tick'] ?? 0));
        $due = $forced || $lastRun === 0 || $ctx->runSequence <= 0
            || ($ctx->runSequence - $lastRun) >= $interval;
        return [
            'run' => $due,
            'status' => $due ? TickModuleConfigRepository::STATUS_RUNNING : TickModuleConfigRepository::STATUS_SKIPPED,
            'limit' => $limit,
        ];
    }

    public function markStarted(TickModule $module, TickContext $ctx): void
    {
        $this->repository->markStarted($module->key(), $ctx->runSequence, $ctx->now);
    }

    /** @param array{status:string,duration_ms:int,stats:array<string,mixed>,error:?string} $run */
    public function markFinished(TickModule $module, TickContext $ctx, array $run, bool $forced): void
    {
        $status = $run['status'] === TickRunResult::STATUS_SUCCESS
            ? TickModuleConfigRepository::STATUS_SUCCESS
            : TickModuleConfigRepository::STATUS_ERROR;
        $this->repository->markFinished(
            $module->key(),
            $ctx->runSequence,
            $ctx->source,
            $status,
            (int)$run['duration_ms'],
            $run['stats'],
            $run['error'],
            $forced
        );
    }

    public function markNotRun(TickModule $module, TickContext $ctx, string $status): void
    {
        $this->repository->markNotRun($module->key(), $ctx->runSequence, $ctx->source, $status);
    }
}
