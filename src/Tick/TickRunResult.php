<?php
declare(strict_types=1);

final class TickRunResult
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_DISABLED = 'disabled';

    public string $status = self::STATUS_SUCCESS;
    public string $source;
    public float $oilPrice = 0.0;
    public int $playersProcessed = 0;
    public float $totalBbl = 0.0;
    public int $durationMs = 0;

    /** @var list<array{module:string,message:string,policy:string}> */
    public array $errors = [];

    /** @var array<string,array{order:int,status:string,duration_ms:int,stats:array<string,mixed>,error:?string}> */
    public array $moduleRuns = [];

    private float $startTime;

    public function __construct(TickContext $ctx)
    {
        $this->source = $ctx->source;
        $this->startTime = $ctx->startTime;
        $this->refresh($ctx);
    }

    /** @param array<string,mixed> $stats */
    public function addSuccess(TickModule $module, int $durationMs, array $stats): void
    {
        $this->moduleRuns[$module->key()] = [
            'order' => $module->order(),
            'status' => self::STATUS_SUCCESS,
            'duration_ms' => $durationMs,
            'stats' => $stats,
            'error' => null,
        ];
    }

    public function addFailure(TickModule $module, int $durationMs, Throwable $error): void
    {
        $message = mb_substr($error->getMessage(), 0, 500);
        $this->errors[] = [
            'module' => $module->key(),
            'message' => $message,
            'policy' => $module->failurePolicy()->value,
        ];
        $this->moduleRuns[$module->key()] = [
            'order' => $module->order(),
            'status' => self::STATUS_FAILED,
            'duration_ms' => $durationMs,
            'stats' => [],
            'error' => $message,
        ];

        $this->status = $module->failurePolicy() === TickFailurePolicy::STOP
            ? self::STATUS_FAILED
            : self::STATUS_PARTIAL;
    }

    public function addSkipped(TickModule $module, string $status): void
    {
        if (!in_array($status, [self::STATUS_SKIPPED, self::STATUS_DISABLED], true)) {
            throw new InvalidArgumentException("Invalid skipped module status: {$status}");
        }
        $this->moduleRuns[$module->key()] = [
            'order' => $module->order(),
            'status' => $status,
            'duration_ms' => 0,
            'stats' => [],
            'error' => null,
        ];
    }

    public function addConfigurationFailure(string $message): void
    {
        $this->errors[] = ['module' => 'registry', 'message' => $message, 'policy' => TickFailurePolicy::STOP->value];
        $this->status = self::STATUS_FAILED;
    }

    public function hasCriticalFailure(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function assertCanContinue(): void
    {
        if (!$this->hasCriticalFailure()) {
            return;
        }

        $lastError = $this->errors !== [] ? $this->errors[array_key_last($this->errors)] : null;
        $module = (string)($lastError['module'] ?? 'unknown');
        $message = (string)($lastError['message'] ?? 'unknown critical tick failure');
        throw new RuntimeException("Tick stopped by {$module}: {$message}");
    }

    public function finish(TickContext $ctx): self
    {
        $this->refresh($ctx);
        $this->durationMs = (int)round((microtime(true) - $this->startTime) * 1000);
        return $this;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'source' => $this->source,
            'oil_price' => $this->oilPrice,
            'players_processed' => $this->playersProcessed,
            'total_bbl' => $this->totalBbl,
            'duration_ms' => $this->durationMs,
            'errors' => $this->errors,
            'modules' => $this->moduleRuns,
        ];
    }

    private function refresh(TickContext $ctx): void
    {
        $this->oilPrice = $ctx->newPrice;
        $playerStats = $ctx->collectStats()['players'] ?? [];
        $this->playersProcessed = (int)($playerStats['players_processed'] ?? 0);
        $this->totalBbl = (float)($playerStats['total_production_bbl'] ?? 0.0);
    }
}
