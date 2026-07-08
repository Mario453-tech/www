<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/TickModule.php';
require_once dirname(__DIR__) . '/TickContext.php';
require_once dirname(__DIR__, 2) . '/B2BContractService.php';

/**
 * B2BContractsModule - expires B2B offers and refunds escrow.
 * B2BContractsModule - wygasza oferty B2B i zwraca depozyt.
 */
final class B2BContractsModule implements TickModule
{
    private int $expired = 0;
    private float $refunded = 0.0;

    public function key(): string
    {
        return 'b2b_contracts';
    }

    public function order(): int
    {
        return 36;
    }

    public function run(TickContext $ctx): void
    {
        $service = new B2BContractService($ctx->db);
        $result = $service->expireOpenOffers($ctx->now);

        $this->expired = (int)($result['expired'] ?? 0);
        $this->refunded = (float)($result['refunded'] ?? 0.0);

        if ($this->expired > 0 && class_exists('GameLog', false)) {
            GameLog::info('tick', 'B2B contracts expired', [
                'expired' => $this->expired,
                'refunded' => round($this->refunded, 2),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        return [
            'expired' => $this->expired,
            'refunded' => $this->refunded,
        ];
    }
}
