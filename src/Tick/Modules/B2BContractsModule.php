<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/TickModule.php';
require_once dirname(__DIR__) . '/TickContext.php';
require_once dirname(__DIR__, 2) . '/B2BContractService.php';

/**
 * B2BContractsModule - expires B2B offers and finalizes accepted offers past deadline.
 * B2BContractsModule - wygasza oferty B2B i rozlicza oferty w trakcie po terminie.
 */
final class B2BContractsModule implements TickModule
{
    private int $expired = 0;
    private float $refunded = 0.0;
    private int $finalized = 0;
    private int $partialDone = 0;
    private int $failed = 0;
    private float $penalties = 0.0;

    public function key(): string
    {
        return 'b2b_contracts';
    }

    public function order(): int
    {
        return 100;
    }

    public function failurePolicy(): TickFailurePolicy
    {
        return TickFailurePolicy::CONTINUE;
    }

    public function run(TickContext $ctx): void
    {
        $service = new B2BContractService($ctx->db);
        $limit = $ctx->moduleLimit($this->key(), 200);

        $expireResult = $service->expireOpenOffers($ctx->now, $limit);
        $this->expired = (int)($expireResult['expired'] ?? 0);
        $this->refunded = (float)($expireResult['refunded'] ?? 0.0);

        $remainingLimit = max(0, $limit - (int)($expireResult['processed'] ?? $this->expired));
        $finalizeResult = $remainingLimit > 0
            ? $service->finalizeExpiredAcceptedOffers($ctx->now, $remainingLimit)
            : ['finalized' => 0, 'partial_done' => 0, 'failed' => 0, 'penalties' => 0.0];
        $this->finalized = (int)($finalizeResult['finalized'] ?? 0);
        $this->partialDone = (int)($finalizeResult['partial_done'] ?? 0);
        $this->failed = (int)($finalizeResult['failed'] ?? 0);
        $this->penalties = (float)($finalizeResult['penalties'] ?? 0.0);

        if (($this->expired > 0 || $this->finalized > 0) && class_exists('GameLog', false)) {
            GameLog::info('tick', 'B2B contracts tick', [
                'expired' => $this->expired,
                'refunded' => round($this->refunded, 2),
                'finalized' => $this->finalized,
                'partial_done' => $this->partialDone,
                'failed' => $this->failed,
                'penalties' => round($this->penalties, 2),
            ]);
        }
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        return [
            'b2b_contracts_expired' => $this->expired,
            'b2b_contracts_refunded' => $this->refunded,
            'b2b_contracts_finalized' => $this->finalized,
            'b2b_contracts_partial_done' => $this->partialDone,
            'b2b_contracts_failed' => $this->failed,
            'b2b_seller_penalties' => $this->penalties,
        ];
    }
}
