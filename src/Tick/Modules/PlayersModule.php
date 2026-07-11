<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/TickModule.php';
require_once dirname(__DIR__) . '/TickContext.php';
require_once dirname(__DIR__) . '/PlayersSection.php';

final class PlayersModule implements TickModule
{
    private int $playersProcessed = 0;
    private int $wellsActive = 0;
    private float $totalBbl = 0.0;
    private float $totalRevenue = 0.0;
    private float $totalOpex = 0.0;
    private int $disastersTriggered = 0;
    private int $incidentsTriggered = 0;

    public function key(): string { return 'players'; }
    public function order(): int { return 40; }
    public function failurePolicy(): TickFailurePolicy { return TickFailurePolicy::STOP; }

    public function run(TickContext $ctx): void
    {
        $ctx->loadBalanceMults();
        $section = new PlayersSection($ctx->db, $ctx->mutableNow(), $ctx->newPrice, $ctx->balanceMults);
        $section->run();
        $this->playersProcessed = $section->playersProcessed;
        $this->wellsActive = $section->wellsActive;
        $this->totalBbl = $section->totalBbl;
        $this->totalRevenue = $section->totalRevenue;
        $this->totalOpex = $section->totalOpex;
        $this->disastersTriggered = $section->disastersTriggered;
        $this->incidentsTriggered = $section->incidentsTriggered;
    }

    /** @return array<string,int|float> */
    public function stats(): array
    {
        return [
            'players_processed' => $this->playersProcessed,
            'wells_active' => $this->wellsActive,
            'total_production_bbl' => $this->totalBbl,
            'total_revenue_pln' => $this->totalRevenue,
            'total_opex_pln' => $this->totalOpex,
            'disasters_triggered' => $this->disastersTriggered,
            'incidents_triggered' => $this->incidentsTriggered,
        ];
    }
}
