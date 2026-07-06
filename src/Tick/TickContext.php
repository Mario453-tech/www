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

    public function runCleanup(): void
    {
        // Zarezerwowane dla pozniejszego etapu migracji / Reserved for a later migration step.
    }

    public function summary(): string
    {
        $durationMs = (int)round((microtime(true) - $this->startTime) * 1000);
        return sprintf(
            'tick source=%s price=%.2f modules=%d duration_ms=%d',
            $this->source,
            $this->newPrice,
            count($this->moduleStats),
            $durationMs
        );
    }
}
