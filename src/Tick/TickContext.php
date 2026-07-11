<?php
declare(strict_types=1);

/**
 * TickContext - wspolny stan ticka przekazywany do modulow.
 * TickContext - shared tick state passed to modules.
 */
class TickContext
{
    public PDO $db;
    public DateTimeInterface $now;
    public string $source;
    public float $startTime;
    public int $runSequence = 0;
    public float $newPrice = 0.0;

    /**
     * Aktywny trend rynku / Active market trend.
     *
     * @var array<string, mixed>|null
     */
    public ?array $activeTrend = null;
    public bool $isNewTrend = false;

    /**
     * Globalne mnozniki balansu / Global balance multipliers.
     *
     * @var array<string, float>
     */
    public array $balanceMults = [
        'incident' => 1.0,
        'disaster' => 1.0,
        'wear' => 1.0,
        'degradation' => 1.0,
        'loss' => 1.0,
        'opex' => 1.0,
        'production' => 1.0,
        'tax' => 1.0,
    ];

    public bool $bankNegAvailable = false;
    public bool $bankruptcyAvailable = false;

    /**
     * Statystyki modulow / Module stats.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $moduleStats = [];

    /** @var array<string,array{order:int,status:string,duration_ms:int,stats:array<string,mixed>,error:?string}> */
    private array $moduleRuns = [];
    /** @var array<string,int> */
    private array $moduleLimits = [];
    private bool $balanceMultsLoaded = false;

    public function __construct(PDO $db, DateTimeInterface $now, string $source = 'cron', ?float $startTime = null)
    {
        $this->db = $db;
        $this->now = $now;
        $this->source = $source;
        $this->startTime = $startTime ?? microtime(true);
    }

    public function setNewPrice(float $price): void
    {
        $this->newPrice = max(0.0, $price);
    }

    public function mutableNow(): DateTime
    {
        return DateTime::createFromInterface($this->now);
    }

    public function setModuleLimit(string $moduleKey, int $limit): void
    {
        $this->moduleLimits[$moduleKey] = max(1, $limit);
    }

    public function moduleLimit(string $moduleKey, int $fallback = 200): int
    {
        return $this->moduleLimits[$moduleKey] ?? max(1, $fallback);
    }

    /**
     * Ustaw stan rynku / Set market state.
     *
     * @param array<string, mixed>|null $trend
     */
    public function setMarketState(float $price, ?array $trend, bool $isNewTrend): void
    {
        $this->setNewPrice($price);
        $this->activeTrend = $trend;
        $this->isNewTrend = $isNewTrend;
    }

    public function loadBalanceMults(): void
    {
        if ($this->balanceMultsLoaded) {
            return;
        }
        $this->balanceMultsLoaded = true;

        $keys = [
            'global_incident_multiplier' => 'incident',
            'global_disaster_multiplier' => 'disaster',
            'global_wear_multiplier' => 'wear',
            'global_degradation_mult' => 'degradation',
            'global_loss_multiplier' => 'loss',
            'global_opex_multiplier' => 'opex',
            'global_production_mult' => 'production',
            'global_tax_multiplier' => 'tax',
        ];

        try {
            $placeholders = implode(',', array_fill(0, count($keys), '?'));
            $stmt = $this->db->prepare("SELECT `key`, `value` FROM well_config WHERE `key` IN ({$placeholders})");
            $stmt->execute(array_keys($keys));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $shortKey = $keys[(string)($row['key'] ?? '')] ?? null;
                if ($shortKey !== null) {
                    $this->balanceMults[$shortKey] = max(0.1, min(10.0, (float)($row['value'] ?? 1.0)));
                }
            }
            $nonDefault = array_filter($this->balanceMults, static fn(float $value): bool => abs($value - 1.0) > 0.001);
            if ($nonDefault !== [] && class_exists('GameLog', false)) {
                GameLog::info('tick', 'global balance multipliers active', $this->balanceMults);
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('TickContext', 'loadBalanceMults FAILED', $e);
            }
        }
    }

    /**
     * Scal statystyki modulu / Merge module stats.
     *
     * @param array<string, mixed> $data
     */
    public function mergeStats(string $moduleKey, array $data): void
    {
        $moduleKey = trim($moduleKey);
        if ($moduleKey === '') {
            return;
        }
        $this->moduleStats[$moduleKey] = array_merge($this->moduleStats[$moduleKey] ?? [], $data);
    }

    /**
     * Zbierz statystyki modulow / Collect module stats.
     *
     * @return array<string, array<string, mixed>>
     */
    public function collectStats(): array
    {
        return $this->moduleStats;
    }

    /** @param array<string,mixed> $stats */
    public function recordModuleRun(
        string $moduleKey,
        int $order,
        string $status,
        int $durationMs,
        array $stats,
        ?string $error = null
    ): void
    {
        $this->moduleRuns[$moduleKey] = [
            'order' => $order,
            'status' => $status,
            'duration_ms' => max(0, $durationMs),
            'stats' => $stats,
            'error' => $error,
        ];
    }

    /** @return array<string,array{order:int,status:string,duration_ms:int,stats:array<string,mixed>,error:?string}> */
    public function collectModuleRuns(): array
    {
        return $this->moduleRuns;
    }
}
