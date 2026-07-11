<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/TickModule.php';
require_once dirname(__DIR__) . '/TickContext.php';
require_once dirname(__DIR__) . '/MarketSection.php';

final class MarketModule implements TickModule
{
    private float $oilPrice = 0.0;
    private ?string $trendName = null;
    private bool $trendNew = false;

    public function key(): string { return 'market'; }
    public function order(): int { return 10; }
    public function failurePolicy(): TickFailurePolicy { return TickFailurePolicy::STOP; }

    public function run(TickContext $ctx): void
    {
        $section = new MarketSection();
        $section->run();

        $this->oilPrice = $section->newPrice;
        if ($this->oilPrice <= 0.0) {
            $this->oilPrice = $this->fallbackPrice($ctx->db);
            if (class_exists('GameLog', false)) {
                GameLog::warn('tick', 'fallback oil price', ['price' => $this->oilPrice]);
            }
        }

        $this->trendName = isset($section->activeTrend['trend_name'])
            ? (string)$section->activeTrend['trend_name']
            : null;
        $this->trendNew = $section->isNewTrend;
        $ctx->setMarketState($this->oilPrice, $section->activeTrend, $this->trendNew);
    }

    /** @return array<string,float|string|bool|null> */
    public function stats(): array
    {
        return ['oil_price' => $this->oilPrice, 'trend_name' => $this->trendName, 'trend_new' => $this->trendNew];
    }

    private function fallbackPrice(PDO $db): float
    {
        if (class_exists('GameLog', false)) {
            GameLog::error('tick', 'oil price is zero after MarketSection; using fallback');
        }
        try {
            $stmt = $db->prepare("SELECT `value` FROM well_config WHERE `key` = ? LIMIT 1");
            $stmt->execute(['last_tick_oil_price']);
            $price = $stmt->fetchColumn();
            return $price !== false && (float)$price > 0.0 ? (float)$price : 70.0;
        } catch (Throwable) {
            return 70.0;
        }
    }
}
