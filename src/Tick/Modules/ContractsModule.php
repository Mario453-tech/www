<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/TickModule.php';
require_once dirname(__DIR__) . '/TickContext.php';
require_once dirname(__DIR__, 2) . '/ContractService.php';

/**
 * ContractsModule - rozliczanie dostaw kontraktow dlugoterminowych w ramach ticka.
 * ContractsModule - settles long-term contract deliveries as part of the tick cycle.
 */
final class ContractsModule implements TickModule
{
    private int $processed   = 0;
    private int $completed   = 0;
    private int $failed      = 0;
    private float $revenue   = 0.0;
    private float $penalties = 0.0;

    public function key(): string
    {
        return 'contracts';
    }

    public function order(): int
    {
        return 45;
    }

    public function run(TickContext $ctx): void
    {
        $service = new ContractService($ctx->db);
        $result  = $service->processDueContracts($ctx->newPrice);

        $this->processed  = (int)($result['processed']  ?? 0);
        $this->completed  = (int)($result['completed']  ?? 0);
        $this->failed     = (int)($result['failed']     ?? 0);
        $this->revenue    = (float)($result['revenue']   ?? 0.0);
        $this->penalties  = (float)($result['penalties'] ?? 0.0);

        if ($this->processed > 0 && class_exists('GameLog', false)) {
            GameLog::info('tick', "Kontrakty: {$this->processed} dostaw, "
                . 'przychod ' . round($this->revenue, 2)
                . ', kary ' . round($this->penalties, 2)
                . ", ukonczonych: {$this->completed}, nieudanych: {$this->failed}");
        }
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        return [
            'processed'  => $this->processed,
            'completed'  => $this->completed,
            'failed'     => $this->failed,
            'revenue'    => $this->revenue,
            'penalties'  => $this->penalties,
        ];
    }
}
