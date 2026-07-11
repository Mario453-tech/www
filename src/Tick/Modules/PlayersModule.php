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
    /** @var array<string,int> */
    private array $sectionTimingsMs = [];
    private int $slowestPlayerMs = 0;
    private int $slowestPlayerId = 0;

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
        $this->sectionTimingsMs = $section->sectionTimingsMs;
        $this->slowestPlayerMs = $section->slowestPlayerMs;
        $this->slowestPlayerId = $section->slowestPlayerId;
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
            'slowest_player_ms' => $this->slowestPlayerMs,
            'slowest_player_id' => $this->slowestPlayerId,
        ] + $this->sectionTimings();
    }

    /** @return array<string,int> */
    private function sectionTimings(): array
    {
        $timings = [];
        foreach ($this->sectionTimingsMs as $key => $durationMs) {
            $timings['section_ms_' . $key] = max(0, (int)$durationMs);
        }
        return $timings;
    }
}
