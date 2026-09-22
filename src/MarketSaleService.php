<?php
declare(strict_types=1);

require_once __DIR__ . '/FinancialTransactionService.php';

final class MarketSaleService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /** @return array{success:bool,message:string,amount?:int,earnings?:float} */
    public function sellInstant(int $playerId, int $amount): array
    {
        if ($amount <= 0) {
            return ['success' => false, 'message' => t('market.error_amount')];
        }
        try {
            $fts = new FinancialTransactionService($this->db);
            $this->db->beginTransaction();
            $lock = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $player = $this->db->prepare("SELECT id FROM players WHERE id = ?{$lock}");
            $player->execute([$playerId]);
            if (!$player->fetchColumn()) {
                throw new RuntimeException('Market seller not found.');
            }
            $storage = $this->db->prepare("SELECT used FROM storage WHERE player_id = ?{$lock}");
            $storage->execute([$playerId]);
            $stock = $storage->fetch(PDO::FETCH_ASSOC);
            if (!$stock || (float)$stock['used'] < $amount) {
                return ['success' => false, 'message' => t('market.error_amount')];
            }
            $price = (float)$this->db->query('SELECT current_price FROM market_state WHERE id = 1')->fetchColumn();
            $earnings = round($amount * $price, 2);
            if (!is_finite($earnings) || $price <= 0 || $earnings <= 0) {
                throw new RuntimeException('Invalid instant market price.');
            }
            $debit = $this->db->prepare('UPDATE storage SET used = used - ? WHERE player_id = ? AND used >= ?');
            $debit->execute([$amount, $playerId, $amount]);
            if ($debit->rowCount() !== 1) {
                throw new RuntimeException('Instant market oil debit failed.');
            }
            $credit = $fts->credit($playerId, $earnings, FinancialTransactionService::TYPE_MARKET_SALE,
                tPlain('market.tx_instant_sale'), 'market_instant');
            if (empty($credit['success'])) {
                throw new RuntimeException('Instant market credit failed: ' . ($credit['error'] ?? 'unknown'));
            }
            $this->db->commit();
            return ['success' => true, 'amount' => $amount, 'earnings' => $earnings,
                'message' => sprintf(t('market.success_sold'), $amount, number_format($earnings))];
        } catch (Throwable $e) {
            GameLog::error('MarketSaleService', 'Instant sale failed', $e, ['player_id' => $playerId]);
            return ['success' => false, 'message' => t('common.app_error')];
        } finally {
            if ($this->db->inTransaction()) $this->db->rollBack();
        }
    }
}
