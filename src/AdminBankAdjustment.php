<?php
declare(strict_types=1);

require_once __DIR__ . '/FinancialTransactionService.php';

final class AdminBankAdjustment
{
    public function __construct(private PDO $db, private FinancialTransactionService $finance)
    {
    }

    public static function amount(mixed $raw): float
    {
        if (!is_string($raw) && !is_int($raw) && !is_float($raw)) {
            throw new InvalidArgumentException('Invalid amount');
        }
        $raw = str_replace(',', '.', preg_replace('/\s+/u', '', (string)$raw));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw new InvalidArgumentException('Invalid amount');
        }
        $amount = (float)$raw;
        if (!is_finite($amount) || $amount < 0.01 || $amount > 9999999999.99) {
            throw new InvalidArgumentException('Amount out of range');
        }
        return $amount;
    }

    /** @return array{success: bool, transaction_id: int|null, error: string|null, amount: float} */
    public function adjust(int $playerId, string $action, mixed $raw, string $note, string $actor, string $ip): array
    {
        $amount = self::amount($raw);
        $note = trim($note);
        if ($playerId <= 0 || !in_array($action, ['admin_credit', 'admin_debit'], true)
            || $note === '' || mb_strlen($note) > 255 || trim($actor) === '') {
            throw new InvalidArgumentException('Invalid adjustment');
        }
        if ($this->db->inTransaction()) {
            throw new LogicException('Adjustment requires its own transaction');
        }
        $this->db->beginTransaction();
        try {
            $method = $action === 'admin_credit' ? 'credit' : 'debit';
            $result = $this->finance->$method($playerId, $amount, FinancialTransactionService::TYPE_ADMIN_ADJUSTMENT, $note);
            if (empty($result['success']) || empty($result['transaction_id'])) {
                throw new RuntimeException('Financial adjustment failed');
            }
            // Strict audit in the same transaction; AdminLog::log swallows errors.
            // Scisly audyt w tej samej transakcji; AdminLog::log pomija bledy.
            $stmt = $this->db->prepare('INSERT INTO admin_logs
                (action, description, target_player_id, target_type, target_id, admin_user, admin_ip, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)');
            $stmt->execute([$action, 'Balance adjustment; transaction_id=' . $result['transaction_id'] . '; amount ' . $result['amount'] . '; note: ' . $note,
                $playerId, 'player', $playerId, $actor, $ip]);
            $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
