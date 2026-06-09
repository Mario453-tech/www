<?php
declare(strict_types=1);

require_once __DIR__ . '/FinancialTransactionService.php';

/**
 * PlayerPaymentService - cienka warstwa oplat gracza.
 * PlayerPaymentService - thin layer for player-facing payments.
 *
 * Cel: moduly gry nie powinny same skladac wywolan debit/credit za kazdym razem.
 * Goal: game modules should not hand-build debit/credit calls every time.
 */
final class PlayerPaymentService
{
    private FinancialTransactionService $financial;

    public function __construct(?PDO $db = null)
    {
        $this->financial = new FinancialTransactionService($db);
    }

    /**
     * Pobiera oplate od gracza i zapisuje historie bankowa.
     * Charges a player and writes bank history.
     *
     * @return array{success:bool,transaction_id:?int,error:?string,amount:float}
     */
    public function charge(
        int $playerId,
        float $amount,
        string $type,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): array {
        return $this->financial->debit($playerId, $amount, $type, $description, $referenceType, $referenceId);
    }

    /**
     * Zwraca srodki graczowi i zapisuje historie bankowa.
     * Refunds a player and writes bank history.
     *
     * @return array{success:bool,transaction_id:?int,error:?string,amount:float}
     */
    public function refund(
        int $playerId,
        float $amount,
        string $type,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): array {
        return $this->financial->credit($playerId, $amount, $type, $description, $referenceType, $referenceId);
    }
}
