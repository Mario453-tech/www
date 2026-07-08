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
            $this->db->prepare('DELETE FROM b2b_reputation_logs WHERE player_id IN (?, ?)')->execute([$ids['buyer'], $ids['seller']]);
            $this->db->prepare('DELETE FROM b2b_reputation_scores WHERE player_id IN (?, ?)')->execute([$ids['buyer'], $ids['seller']]);
            $this->db->prepare('DELETE FROM b2b_contract_deliveries WHERE buyer_player_id IN (?, ?) OR seller_player_id IN (?, ?)')->execute([
                $ids['buyer'], $ids['seller'], $ids['buyer'], $ids['seller'],
            ]);
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

    /**
     * Weryfikuje ze FOR UPDATE blokuje double-delivery.
     * Test symuluje rase condition przez dwa sequentiale wywolania
     * na tle stanu ktory powinien pozwolic tylko jednej dostawie przejsc.
     * W produkcji SELECT FOR UPDATE zapewnia atomowosc — tutaj testujemy
     * poprawnosc state machine (drugi deliverPartial widzi completed).
     */
    public function testConcurrentDeliverPartialOnlyOneSucceedsMysql(): void
    {
        $ids = $this->getB2BIds();
        $this->seedB2BPlayer($ids['buyer'], 0.0, 50000.0);
        $this->seedB2BPlayer($ids['seller'], 0.0, 0.0);
        $this->db->prepare('INSERT INTO storage (player_id, capacity, used) VALUES (?, ?, ?)')
            ->execute([$ids['seller'], 2000.0, 500.0]);

        $service = new B2BContractService($this->db);
        $created = $service->createBuyOffer($ids['buyer'], 100.0, 100.0, 120);
        $this->assertTrue($created['success'], (string)($created['status'] ?? 'create_failed'));
        $offerId = (int)$created['offer_id'];

        // Seller accepts with 30 bbl first delivery
        $accepted = $service->acceptOffer($ids['seller'], $offerId, 30.0);
        $this->assertTrue($accepted['success'], json_encode($accepted) ?: 'accept_failed');

        // Simulated race: two deliveries for remaining 70 bbl
        // First call delivers exactly remaining 70 and completes the offer
        $first = $service->deliverPartial($ids['seller'], $offerId, 70.0);
        // Second call arrives after — should fail because offer is now 'completed'
        $second = $service->deliverPartial($ids['seller'], $offerId, 70.0);

        $this->assertTrue($first['success'], json_encode($first) ?: 'first_delivery_failed');
        $this->assertSame('completed', $first['status']);
        $this->assertFalse($second['success'], 'Second deliverPartial must fail after completion');
        $this->assertSame('not_accepted', $second['status']);

        // Total delivered must equal total_bbl (100), never more
        $stmt = $this->db->prepare('SELECT delivered_bbl, total_bbl FROM b2b_contract_offers WHERE id = ?');
        $stmt->execute([$offerId]);
        $data = $stmt->fetch();
        $this->assertSame(100.0, round((float)$data['delivered_bbl'], 2));

        // Exactly 2 delivery records: initial acceptOffer + one deliverPartial
        $stmt2 = $this->db->prepare('SELECT COUNT(*) FROM b2b_contract_deliveries WHERE offer_id = ?');
        $stmt2->execute([$offerId]);
        $this->assertSame(2, (int)$stmt2->fetchColumn());
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
        $this->assertSame(53, $this->b2bScore($ids['seller']));
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

    private function b2bScore(int $playerId): int
    {
        $stmt = $this->db->prepare('SELECT score FROM b2b_reputation_scores WHERE player_id = ?');
        $stmt->execute([$playerId]);
        return (int)$stmt->fetchColumn();
    }
}
