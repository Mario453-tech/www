<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/MarketOffer.php';

final class MySqlMarketOfferOwnershipTest extends MySqlIntegrationTestCase
{
    private int $offerId = 0;

    protected function tearDown(): void
    {
        if ($this->offerId > 0) {
            $this->db->prepare('DELETE FROM market_sale_history WHERE offer_id = ?')
                ->execute([$this->offerId]);
            $this->db->prepare(
                "DELETE FROM bank_transactions
                  WHERE reference_type = 'market_offer' AND reference_id = ?"
            )->execute([$this->offerId]);
            $this->db->prepare('DELETE FROM market_offers WHERE id = ?')
                ->execute([$this->offerId]);
        }

        parent::tearDown();
    }

    public function testStaleOfferCannotBePaidTwice(): void
    {
        $playerId = $this->seedPlayer();
        $bankBefore = $this->playerBankBalance($playerId);

        $this->db->prepare(
            "INSERT INTO market_offers
                (player_id, amount, locked_amount, limit_price, status, auto_execute, created_at)
             VALUES (?, 10, 10, 30, 'pending', 1, NOW())"
        )->execute([$playerId]);
        $this->offerId = (int)$this->db->lastInsertId();

        $offer = $this->db->prepare('SELECT * FROM market_offers WHERE id = ?');
        $offer->execute([$this->offerId]);
        $staleOffer = $offer->fetch(PDO::FETCH_ASSOC);
        $this->assertIsArray($staleOffer);

        $service = new MarketOffer();
        $method = new ReflectionMethod(MarketOffer::class, 'executeOffer');
        $method->invoke($service, $staleOffer, 100);
        $method->invoke($service, $staleOffer, 100);

        $this->assertEqualsWithDelta(
            $bankBefore + 1000.0,
            $this->playerBankBalance($playerId),
            0.01
        );

        $history = $this->db->prepare(
            'SELECT COUNT(*) FROM market_sale_history WHERE offer_id = ?'
        );
        $history->execute([$this->offerId]);
        $this->assertSame(1, (int)$history->fetchColumn());

        $transaction = $this->db->prepare(
            "SELECT COUNT(*) FROM bank_transactions
              WHERE reference_type = 'market_offer' AND reference_id = ?"
        );
        $transaction->execute([$this->offerId]);
        $this->assertSame(1, (int)$transaction->fetchColumn());
    }

    private function playerBankBalance(int $playerId): float
    {
        $stmt = $this->db->prepare('SELECT bank_balance FROM players WHERE id = ?');
        $stmt->execute([$playerId]);
        return (float)$stmt->fetchColumn();
    }
}
