<?php

/**
 * MarketSection sekcja 1-2 ticka: trendy rynkowe + cena ropy.
 * MarketSection tick sections 1-2: market trends + oil price update.
 */
class MarketSection
{
    public float  $newPrice   = 0.0;
 /** @var array<string, mixed>|null */
    public ?array $activeTrend = null;
    public bool   $isNewTrend  = false;

    public function run(): void
    {
        $this->runTrends();
        $this->runPrice();
    }

    private function runTrends(): void
    {
        try {
            GameLog::step('tick', 'market', 1, 'trends');
            $marketTrend = new MarketTrend();

            $trendBefore      = $marketTrend->getActiveTrend();
            $idBefore         = $trendBefore ? (int)$trendBefore['id'] : 0;

            $marketTrend->deactivateExpiredTrends();
            $this->activeTrend = $marketTrend->checkAndActivateRandomTrend();
            $idAfter           = $this->activeTrend ? (int)$this->activeTrend['id'] : 0;
            $this->isNewTrend  = ($idAfter > 0 && $idAfter !== $idBefore);

            GameLog::info('tick', 'trend', [
                'active'   => $this->activeTrend['trend_name'] ?? 'none',
                'is_new'   => $this->isNewTrend,
                'modifier' => $this->activeTrend['price_modifier'] ?? null,
            ]);
        } catch (Throwable $e) {
            GameLog::error('tick', 'trends FAILED', $e);
        }
    }

    private function runPrice(): void
    {
        try {
            GameLog::step('tick', 'market', 2, 'updatePrices');
            $marketTick     = new MarketTick();
            $this->newPrice = (float)$marketTick->updatePrices($this->activeTrend);
            GameLog::info('tick', 'new oil price', ['price' => $this->newPrice]);
        } catch (Throwable $e) {
            GameLog::error('tick', 'MarketTick::updatePrices FAILED', $e);
        }
    }
}
