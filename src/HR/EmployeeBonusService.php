<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__) . '/Employee/EmployeeRef.php';
require_once dirname(__DIR__) . '/FinancialTransactionService.php';
require_once __DIR__ . '/MoraleServiceV2.php';

final class EmployeeBonusService
{
    public function __construct(private readonly PDO $db)
    {
        EmployeeSystemBootstrap::ensure($db);
    }

    /** @return array{success:bool,amount:float,new_morale?:float,error?:string} */
    public function grantTechnicalBonus(
        int $playerId,
        int $staffId,
        float $amount = 15000.0,
        float $moraleGain = 15.0
    ): array {
        if ($playerId <= 0 || $staffId <= 0 || $amount <= 0.0 || $moraleGain <= 0.0) {
            throw new InvalidArgumentException('Employee bonus parameters are invalid.');
        }
        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $stmt = $this->db->prepare(
                "SELECT id FROM technical_staff WHERE id=? AND player_id=? LIMIT 1{$suffix}"
            );
            $stmt->execute([$staffId, $playerId]);
            if ((int)($stmt->fetchColumn() ?: 0) !== $staffId) {
                throw new RuntimeException('Technical employee does not belong to this player.');
            }
            $payment = (new FinancialTransactionService($this->db))->debitCombined(
                $playerId,
                $amount,
                FinancialTransactionService::TYPE_HR_BONUS,
                'Employee recognition bonus',
                'technical_staff',
                $staffId
            );
            if (empty($payment['success'])) {
                if ($ownTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return [
                    'success' => false,
                    'amount' => $amount,
                    'error' => (string)($payment['error'] ?? 'payment_failed'),
                ];
            }
            $newMorale = (new MoraleService($this->db))->changeMorale(
                new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, $staffId, $playerId),
                $moraleGain,
                'bonus.granted'
            );
            if ($ownTransaction) {
                $this->db->commit();
            }
            return ['success' => true, 'amount' => $amount, 'new_morale' => $newMorale];
        } catch (Throwable $exception) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}