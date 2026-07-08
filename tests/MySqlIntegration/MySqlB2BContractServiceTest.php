<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';
require_once dirname(__DIR__, 2) . '/src/B2BContractService.php';

/**
 * MySQL regression tests for B2B contracts.
 * Testy regresyjne MySQL dla kontraktow B2B.
 */
final class MySqlB2BContractServiceTest extends MySqlIntegrationTestCase
{
    protected function tearDown(): void
    {
        $ids = $this->getB2BIds();
        try {
            $this->db->prepare('DELETE FROM b2b_contract_logs WHERE player_id IN (?, ?)')->execute([$ids['buyer'], $ids['seller']]);
            $this->db->prepare('DELETE FROM b2b_contract_offers WHERE buyer_player_id IN (?, ?) OR seller_player_id IN (?, ?)')->execute([
                $ids['buyer'], $ids['seller'], $ids['buyer'], $ids['seller'],
            ]);
            $this->db->prepare('DELETE FROM bank_transactions WHERE from_player_id IN (?, ?) OR to_player_id IN (?, ?)')->execute([
                $ids['buyer'], $ids['seller'], $ids['buyer'], $ids['seller'],
            ]);
            $this->db->prepare('DELETE FROM storage WHERE player_id IN (?, ?)')->execute([$ids['buyer'], $ids['seller']]);
            $this->db->prepare('DELETE FROM players WHERE id IN (?, ?)')->execute([$ids['buyer'], $ids['seller']]);
        } catch (Throwable) {
        }

        parent::tearDown();
    }

    public function testSameOfferCanBeAcceptedOnlyOnce(): void
    {
        $ids = $this->getB2BIds();
        $this->seedB2BPlayer($ids['buyer'], 0.0, 50000.0);
        $this->seedB2BPlayer($ids['seller'], 0.0, 100.0);
        $this->db->prepare('INSERT INTO storage (player_id, capacity, used) VALUES (?, ?, ?)')
            ->execute([$ids['seller'], 1000.0, 250.0]);

        $service = new B2BContractService($this->db);
        $created = $service->createBuyOffer($ids['buyer'], 100.0, 100.0, 120);
        $this->assertTrue($created['success'], (string)($created['status'] ?? 'create_failed'));
        $offerId = (int)$created['offer_id'];

        $first = $service->acceptAndDeliver($ids['seller'], $offerId);
        $second = $service->acceptAndDeliver($ids['seller'], $offerId);

        $this->assertTrue($first['success'], json_encode($first, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'first accept failed');
        $this->assertFalse($second['success']);
        $this->assertSame('not_open', $second['status']);
        $this->assertSame(10100.0, $this->bankOf($ids['seller']));
        $this->assertSame(1, $this->countTx(FinancialTransactionService::TYPE_B2B_TRADE_REVENUE, $ids['seller']));
    }

    /** @return array{buyer:int,seller:int} */
    private function getB2BIds(): array
    {
        $base = $this->getTrackedIds()['playerId'];
        return ['buyer' => $base + 80, 'seller' => $base + 81];
    }

    private function seedB2BPlayer(int $id, float $cash, float $bank): void
    {
        $username = 'phpunit_b2b_' . $id;
        $this->db->prepare(
            'INSERT INTO players (id, username, email, password_hash, cash, bank_balance, status, created_at, last_tick_at)
             VALUES (?, ?, ?, ?, ?, ?, \'active\', NOW(), NOW())'
        )->execute([$id, $username, $username . '@example.test', password_hash('secret', PASSWORD_BCRYPT), $cash, $bank]);
    }

    private function bankOf(int $playerId): float
    {
        $stmt = $this->db->prepare('SELECT bank_balance FROM players WHERE id = ?');
        $stmt->execute([$playerId]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    private function countTx(string $type, int $toPlayerId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM bank_transactions WHERE transaction_type = ? AND to_player_id = ?');
        $stmt->execute([$type, $toPlayerId]);
        return (int)$stmt->fetchColumn();
    }
}
