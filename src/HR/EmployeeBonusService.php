<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__) . '/Employee/EmployeeRef.php';
require_once dirname(__DIR__) . '/FinancialTransactionService.php';
require_once __DIR__ . '/MoraleServiceV2.php';
require_once __DIR__ . '/EmployeeActionReceiptService.php';
require_once __DIR__ . '/EmployeeDeadlockRetry.php';

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
        string $idempotencyToken,
        float $amount = 15000.0,
        float $moraleGain = 15.0
    ): array {
        return EmployeeDeadlockRetry::run(
            $this->db,
            fn(): array => $this->grantTechnicalBonusOnce(
                $playerId,
                $staffId,
                $idempotencyToken,
                $amount,
                $moraleGain
            )
        );
    }

    /** @return array{success:bool,amount:float,new_morale?:float,error?:string} */
    private function grantTechnicalBonusOnce(
        int $playerId,
        int $staffId,
        string $idempotencyToken,
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
            $playerLock = $this->db->prepare("SELECT id FROM players WHERE id=? LIMIT 1{$suffix}");
            $playerLock->execute([$playerId]);
            if ((int)($playerLock->fetchColumn() ?: 0) !== $playerId) {
                throw new RuntimeException('Player does not exist for employee bonus.');
            }
            $receipts = new EmployeeActionReceiptService($this->db);
            $receipt = $receipts->claim($playerId, 'grant_bonus', $idempotencyToken, [
                'staff_id'=>$staffId,
                'amount'=>$amount,
                'morale_gain'=>$moraleGain,
            ]);
            if ($receipt['replayed']) {
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return $receipt['response'];
            }
            $stmt = $this->db->prepare(
                "SELECT ts.id FROM technical_staff ts
                   JOIN employee_state es
                     ON es.player_id=ts.player_id
                    AND es.source_type='technical_staff'
                    AND es.source_id=ts.id
                  WHERE ts.id=? AND ts.player_id=? AND ts.status IN ('active','busy','on_leave')
                    AND es.relation_status NOT IN ('on_strike','leaving','inactive')
                  LIMIT 1{$suffix}"
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
                $result = [
                    'success' => false,
                    'amount' => $amount,
                    'error' => (string)($payment['error'] ?? 'payment_failed'),
                ];
                $receipts->complete((int)$receipt['id'], $playerId, $result);
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return $result;
            }
            $newMorale = (new MoraleService($this->db))->changeMorale(
                new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, $staffId, $playerId),
                $moraleGain,
                'bonus.granted'
            );
            $result = ['success' => true, 'amount' => $amount, 'new_morale' => $newMorale];
            $receipts->complete((int)$receipt['id'], $playerId, $result);
            if ($ownTransaction) {
                $this->db->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
