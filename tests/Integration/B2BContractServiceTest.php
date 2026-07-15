<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/B2BContractService.php';
require_once dirname(__DIR__, 2) . '/src/FinancialTransactionService.php';
require_once dirname(__DIR__, 2) . '/src/Tick/Modules/B2BContractsModule.php';

/**
 * B2B contract service tests - escrow, delivery, cancellation and expiry.
 * Testy serwisu B2B - depozyt, dostawa, anulowanie i wygasanie.
 */
final class B2BContractServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private B2BContractService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createBaseSchema();
        $this->service = new B2BContractService($this->db);
    }

    public function testSchemaSeedsDefaultConfig(): void
    {
        $this->assertTrue($this->service->isModuleEnabled());
        $this->assertSame(70.0, (float)$this->service->getConfig()['min_price_market_pct']);
        $this->assertSame(5.0, (float)$this->service->getConfig()['max_open_offers_per_player']);
    }

    public function testSaveConfigNormalizesMinMaxPairs(): void
    {
        $this->service->saveConfig([
            'min_price_market_pct' => 140,
            'max_price_market_pct' => 60,
            'min_bbl_per_offer' => 900,
            'max_bbl_per_offer' => 100,
        ]);

        $cfg = $this->service->getConfig();
        $this->assertSame(60.0, (float)$cfg['min_price_market_pct']);
        $this->assertSame(140.0, (float)$cfg['max_price_market_pct']);
        $this->assertSame(100.0, (float)$cfg['min_bbl_per_offer']);
        $this->assertSame(900.0, (float)$cfg['max_bbl_per_offer']);
    }

    public function testCreateBuyOfferLocksEscrowFromBuyer(): void
    {
        $this->seedPlayer(1, 1000.0, 50000.0);

        $result = $this->service->createBuyOffer(1, 100.0, 100.0, 120);

        $this->assertTrue($result['success']);
        $this->assertSame(1000.0, $this->cashOf(1));
        $this->assertSame(40000.0, $this->bankOf(1));
        $this->assertSame('open', $this->offerStatus((int)$result['offer_id']));
        $this->assertSame(1, $this->txCount(FinancialTransactionService::TYPE_B2B_ESCROW_LOCK));
    }

    public function testCreateBuyOfferRejectsInsufficientFunds(): void
    {
        $this->seedPlayer(1, 50.0, 100.0);

        $result = $this->service->createBuyOffer(1, 100.0, 100.0, 120);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_funds', $result['status']);
        $this->assertSame(0, $this->db->query('SELECT COUNT(*) FROM b2b_contract_offers')->fetchColumn());
        $this->assertSame(100.0, $this->bankOf(1));
    }

    public function testCreateBuyOfferRejectsPriceOutsideMarketBand(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);

        $low = $this->service->createBuyOffer(1, 100.0, 69.99, 120);
        $high = $this->service->createBuyOffer(1, 100.0, 130.01, 120);

        $this->assertFalse($low['success']);
        $this->assertSame('invalid_price', $low['status']);
        $this->assertFalse($high['success']);
        $this->assertSame('invalid_price', $high['status']);
    }

    public function testCreateBuyOfferEnforcesOpenOfferLimit(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);

        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->service->createBuyOffer(1, 100.0, 100.0, 120)['success']);
        }

        $result = $this->service->createBuyOffer(1, 100.0, 100.0, 120);

        $this->assertFalse($result['success']);
        $this->assertSame('open_limit', $result['status']);
    }

    public function testSellerCannotAcceptOwnOffer(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedStorage(1, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];

        $result = $this->service->acceptAndDeliver(1, $offerId);

        $this->assertFalse($result['success']);
        $this->assertSame('own_offer', $result['status']);
        $this->assertSame('open', $this->offerStatus($offerId));
    }

    public function testAcceptAndDeliverRejectsInsufficientOil(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 50.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];

        $result = $this->service->acceptAndDeliver(2, $offerId);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_oil', $result['status']);
        $this->assertSame(50.0, $this->storageOf(2));
    }

    public function testAcceptAndDeliverCompletesOfferAndPaysSeller(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 100.0);
        $this->seedStorage(2, 250.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];

        $result = $this->service->acceptAndDeliver(2, $offerId);

        $this->assertTrue($result['success']);
        $this->assertSame('completed', $this->offerStatus($offerId));
        $this->assertSame(150.0, $this->storageOf(2));
        $this->assertSame(10100.0, $this->bankOf(2));
        $this->assertSame(1, $this->txCount(FinancialTransactionService::TYPE_B2B_TRADE_REVENUE));
        $this->assertSame(51, $this->b2bScore(1));
        $this->assertSame(53, $this->b2bScore(2));
    }

    public function testCancelBuyOfferRefundsNinetyPercentAndLogsPenalty(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];

        $result = $this->service->cancelBuyOffer(1, $offerId, 'test');

        $this->assertTrue($result['success']);
        $this->assertSame('cancelled', $this->offerStatus($offerId));
        $this->assertSame(49000.0, $this->bankOf(1));
        $this->assertSame(9000.0, $result['refund_amount']);
        $this->assertSame(1000.0, $result['penalty_amount']);
        $this->assertSame(1, $this->txCount(FinancialTransactionService::TYPE_B2B_ESCROW_REFUND));
        $this->assertSame(1, $this->txCount(FinancialTransactionService::TYPE_B2B_CANCEL_PENALTY));
        $this->assertSame(47, $this->b2bScore(1));
    }

    public function testExpireOpenOffersRefundsFullEscrow(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->db->prepare("UPDATE b2b_contract_offers SET expires_at = ? WHERE id = ?")
            ->execute(['2000-01-01 00:00:00', $offerId]);

        $result = $this->service->expireOpenOffers(new DateTimeImmutable('2000-01-02 00:00:00'));

        $this->assertSame(1, $result['expired']);
        $this->assertSame(10000.0, $result['refunded']);
        $this->assertSame(50000.0, $this->bankOf(1));
        $this->assertSame('expired', $this->offerStatus($offerId));
        $this->assertSame(49, $this->b2bScore(1));
    }

    public function testExpireOpenOffersRespectsBatchLimit(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);
        for ($i = 0; $i < 3; $i++) {
            $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
            $this->db->prepare("UPDATE b2b_contract_offers SET expires_at = ? WHERE id = ?")
                ->execute(['2000-01-01 00:00:00', $offerId]);
        }

        $result = $this->service->expireOpenOffers(new DateTimeImmutable('2000-01-02 00:00:00'), 2);

        $this->assertSame(2, $result['expired']);
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM b2b_contract_offers WHERE status = 'open'")->fetchColumn());
        $this->assertSame(2, (int)$this->db->query("SELECT COUNT(*) FROM b2b_contract_offers WHERE status = 'expired'")->fetchColumn());
    }

    public function testFinalizeExpiredAcceptedOffersRespectsBatchLimit(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 1000.0);
        for ($i = 0; $i < 2; $i++) {
            $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
            $this->service->acceptOffer(2, $offerId, 30.0);
            $this->db->prepare("UPDATE b2b_contract_offers SET delivery_deadline_at = ? WHERE id = ?")
                ->execute(['2000-01-01 00:00:00', $offerId]);
        }

        $result = $this->service->finalizeExpiredAcceptedOffers(new DateTimeImmutable('2000-01-02 00:00:00'), 1);

        $this->assertSame(1, $result['finalized']);
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM b2b_contract_offers WHERE status = 'accepted'")->fetchColumn());
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM b2b_contract_offers WHERE status = 'partial_done'")->fetchColumn());
    }

    public function testTickModuleExpiresOffersAndRefundsEscrow(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->db->prepare("UPDATE b2b_contract_offers SET expires_at = ? WHERE id = ?")
            ->execute(['2000-01-01 00:00:00', $offerId]);

        $module = new B2BContractsModule();
        $ctx = new TickContext($this->db, new DateTimeImmutable('2000-01-02 00:00:00'), 'test');
        $module->run($ctx);

        $stats = $module->stats();
        $this->assertSame(1, $stats['b2b_contracts_expired']);
        $this->assertSame(10000.0, $stats['b2b_contracts_refunded']);
        $this->assertSame(0, $stats['b2b_contracts_finalized']);
        $this->assertSame(50000.0, $this->bankOf(1));
        $this->assertSame('expired', $this->offerStatus($offerId));
    }

    public function testTickModuleUsesConfiguredBatchLimit(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);
        for ($i = 0; $i < 3; $i++) {
            $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
            $this->db->prepare("UPDATE b2b_contract_offers SET expires_at = ? WHERE id = ?")
                ->execute(['2000-01-01 00:00:00', $offerId]);
        }

        $module = new B2BContractsModule();
        $ctx = new TickContext($this->db, new DateTimeImmutable('2000-01-02 00:00:00'), 'test');
        $ctx->setModuleLimit('b2b_contracts', 2);
        $module->run($ctx);

        $stats = $module->stats();
        $this->assertSame(2, $stats['b2b_contracts_expired']);
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM b2b_contract_offers WHERE status = 'open'")->fetchColumn());
    }

    // =========================================================
    // Testy dostaw czesciowych / Partial delivery tests
    // =========================================================

    public function testAcceptOfferWithPartialDeliverySetsStatusAccepted(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        // minFirstBbl = 100 * 25% = 25; deliver 30 > 25
        $result = $this->service->acceptOffer(2, $offerId, 30.0);

        $this->assertTrue($result['success']);
        $this->assertSame('accepted', $result['status']);
        $this->assertSame('accepted', $this->offerStatus($offerId));
        $this->assertSame(70.0, $result['remaining_bbl']);
    }

    public function testAcceptOfferRejectsBelowMinFirstDelivery(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        // minFirstBbl = 25; deliver 10 < 25 -> error
        $result = $this->service->acceptOffer(2, $offerId, 10.0);

        $this->assertFalse($result['success']);
        $this->assertSame('below_min_first_delivery', $result['status']);
        $this->assertGreaterThan(10.0, (float)$result['min_first_bbl']);
        $this->assertSame('open', $this->offerStatus($offerId));
    }

    public function testPartialDeliveryDisabledRequiresFullFirstDelivery(): void
    {
        $this->service->saveConfig(['partial_delivery_enabled' => 0]);
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];

        $partial = $this->service->acceptOffer(2, $offerId, 30.0);
        $full = $this->service->acceptOffer(2, $offerId, 100.0);

        $this->assertFalse($partial['success']);
        $this->assertSame('full_delivery_required', $partial['status']);
        $this->assertTrue($full['success']);
        $this->assertSame('completed', $full['status']);
    }

    public function testAcceptOfferRejectsWhenInsufficientOilForFirstDelivery(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 5.0); // 5 bbl < min 25 bbl
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];

        $result = $this->service->acceptOffer(2, $offerId, 30.0);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_oil', $result['status']);
        $this->assertSame('open', $this->offerStatus($offerId));
        $this->assertSame(5.0, $this->storageOf(2));
    }

    public function testAcceptOfferWithFullDeliveryCompletesOffer(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];

        $result = $this->service->acceptOffer(2, $offerId, 100.0); // full delivery

        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);
        $this->assertSame('completed', $this->offerStatus($offerId));
        $this->assertSame(0.0, $result['remaining_bbl']);
    }

    public function testAcceptOfferPaymentsProportionalToDelivered(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        // deliver 40 bbl @ 100/bbl = 4000 pln revenue
        $result = $this->service->acceptOffer(2, $offerId, 40.0);

        $this->assertTrue($result['success']);
        $this->assertSame(4000.0, $result['revenue']);
        $this->assertSame(4000.0, $this->bankOf(2));
    }

    public function testAcceptOfferDecreasesReleasedAmountAndRemainingBbl(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        // escrow = 10000; deliver 40 bbl -> released 4000, remaining 60 bbl
        $this->service->acceptOffer(2, $offerId, 40.0);

        $row = $this->db->query(
            "SELECT released_amount, remaining_bbl, remaining_escrow_amount
             FROM b2b_contract_offers WHERE id = {$offerId}"
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(4000.0, round((float)$row['released_amount'], 2));
        $this->assertSame(60.0, round((float)$row['remaining_bbl'], 2));
        $this->assertSame(6000.0, round((float)$row['remaining_escrow_amount'], 2));
    }

    public function testAcceptOfferSetsDeadlineOnOffer(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];

        $this->service->acceptOffer(2, $offerId, 30.0);

        $deadline = (string)$this->db->query(
            "SELECT delivery_deadline_at FROM b2b_contract_offers WHERE id = {$offerId}"
        )->fetchColumn();
        $this->assertNotEmpty($deadline);
        $this->assertNotSame('0000-00-00 00:00:00', $deadline);
        $this->assertGreaterThan(time(), strtotime($deadline));
    }

    public function testDeliverPartialAddsDeliveryRecordAndPays(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->service->acceptOffer(2, $offerId, 30.0); // first delivery: 3000 pln
        // Second delivery
        $result = $this->service->deliverPartial(2, $offerId, 20.0);

        $this->assertTrue($result['success']);
        $this->assertSame(20.0, $result['delivered_bbl']);
        $this->assertSame(2000.0, $result['revenue']);
        $this->assertSame(5000.0, $this->bankOf(2)); // 3000 + 2000
        $this->assertSame(2, $this->deliveryCount($offerId));
    }

    public function testDeliverPartialCapsExceedingBblToRemaining(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->service->acceptOffer(2, $offerId, 30.0); // remaining = 70

        // request 200 bbl but only 70 remain; capped to 70
        $result = $this->service->deliverPartial(2, $offerId, 200.0);

        $this->assertTrue($result['success']);
        $this->assertSame(70.0, $result['delivered_bbl']);
        $this->assertSame('completed', $this->offerStatus($offerId));
    }

    public function testSingleFollowUpDeliveryModeRequiresFinalDelivery(): void
    {
        $this->service->saveConfig(['allow_multiple_deliveries' => 0]);
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->assertTrue($this->service->acceptOffer(2, $offerId, 30.0)['success']);

        $tooSmall = $this->service->deliverPartial(2, $offerId, 20.0);
        $final = $this->service->deliverPartial(2, $offerId, 70.0);

        $this->assertFalse($tooSmall['success']);
        $this->assertSame('final_delivery_required', $tooSmall['status']);
        $this->assertTrue($final['success']);
        $this->assertSame('completed', $final['status']);
    }

    public function testDeliverPartialAfterDeadlineReturnsError(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->service->acceptOffer(2, $offerId, 30.0);
        $this->db->prepare("UPDATE b2b_contract_offers SET delivery_deadline_at = ? WHERE id = ?")
            ->execute(['2000-01-01 00:00:00', $offerId]);

        $result = $this->service->deliverPartial(2, $offerId, 20.0);

        $this->assertFalse($result['success']);
        $this->assertSame('deadline_passed', $result['status']);
        $this->assertSame('accepted', $this->offerStatus($offerId)); // unchanged
    }

    public function testDeliverPartialCompletesOfferWhenAllDelivered(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->service->acceptOffer(2, $offerId, 30.0); // remaining 70

        $result = $this->service->deliverPartial(2, $offerId, 70.0);

        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['status']);
        $this->assertSame('completed', $this->offerStatus($offerId));
        $this->assertSame(0.0, $result['remaining_bbl']);
    }

    public function testFinalPartialDeliveryReleasesResidualEscrow(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.01, 120)['offer_id'];

        $this->assertTrue($this->service->acceptOffer(2, $offerId, 25.34)['success']);
        $this->assertTrue($this->service->deliverPartial(2, $offerId, 0.33)['success']);
        $final = $this->service->deliverPartial(2, $offerId, 74.33);

        $this->assertTrue($final['success']);
        $this->assertSame('completed', $final['status']);
        $this->assertSame(10001.0, $this->bankOf(2));
        $this->assertSame(0.0, $this->offerFloat($offerId, 'remaining_escrow_amount'));
        $this->assertSame(10001.0, $this->offerFloat($offerId, 'released_amount'));
    }

    public function testFinalizeAfterPartialDeliverySetsPartialDoneStatus(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->service->acceptOffer(2, $offerId, 30.0); // 30 delivered, 70 left
        $this->db->prepare("UPDATE b2b_contract_offers SET delivery_deadline_at = ? WHERE id = ?")
            ->execute(['2000-01-01 00:00:00', $offerId]);

        $result = $this->service->finalizeAcceptedOffer($offerId, new DateTimeImmutable('2001-01-01'));

        $this->assertTrue($result['success']);
        $this->assertSame('partial_done', $result['status']);
        $this->assertSame('partial_done', $this->offerStatus($offerId));
        $this->assertSame(30.0, $result['delivered_bbl']);
        $this->assertSame(70.0, $result['missing_bbl']);
    }

    public function testFinalizeWithZeroDeliverySetsFailed(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        // Simulate: offer accepted but 0 delivered (manual state injection)
        $this->db->prepare(
            "UPDATE b2b_contract_offers
             SET status = 'accepted', seller_player_id = ?, delivered_bbl = 0,
                 remaining_bbl = total_bbl, delivery_deadline_at = ?, seller_penalty_pct = 10.0
             WHERE id = ?"
        )->execute([2, '2000-01-01 00:00:00', $offerId]);

        $result = $this->service->finalizeAcceptedOffer($offerId, new DateTimeImmutable('2001-01-01'));

        $this->assertTrue($result['success']);
        $this->assertSame('failed', $result['status']);
        $this->assertSame('failed', $this->offerStatus($offerId));
        $this->assertSame(0.0, $result['delivered_bbl']);
    }

    public function testFinalizeRefundsRemainingEscrowToBuyer(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0); // buyer bank 50000
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        // After create: buyer bank = 40000 (10000 escrow locked)
        $this->assertSame(40000.0, $this->bankOf(1));

        $this->service->acceptOffer(2, $offerId, 30.0); // seller gets 3000, escrow 7000 remaining
        $this->assertSame(40000.0, $this->bankOf(1)); // buyer bank unchanged during delivery

        $this->db->prepare("UPDATE b2b_contract_offers SET delivery_deadline_at = ? WHERE id = ?")
            ->execute(['2000-01-01 00:00:00', $offerId]);
        $this->service->finalizeAcceptedOffer($offerId, new DateTimeImmutable('2001-01-01'));

        // buyer gets back remaining 7000 escrow
        $this->assertSame(47000.0, $this->bankOf(1));
        $this->assertSame(1, $this->txCount(FinancialTransactionService::TYPE_B2B_ESCROW_REFUND));
    }

    public function testFinalizeChargesSellerPenaltyForMissingBbl(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 500.0); // seller has 500 in bank
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->service->acceptOffer(2, $offerId, 30.0); // seller.bank = 3000 after payment

        $this->db->prepare("UPDATE b2b_contract_offers SET delivery_deadline_at = ? WHERE id = ?")
            ->execute(['2000-01-01 00:00:00', $offerId]);
        $sellerBankBefore = $this->bankOf(2); // 500 + 3000 = 3500
        $result = $this->service->finalizeAcceptedOffer($offerId, new DateTimeImmutable('2001-01-01'));

        // missing = 70 bbl * 100 = 7000 value, penalty = 700 (10%)
        $this->assertSame(700.0, $result['penalty_amount']);
        $sellerDelta = round($this->bankOf(2) - $sellerBankBefore, 2);
        $this->assertSame(-700.0, $sellerDelta);
    }

    public function testListMyDeliveriesReturnsRecordsFromDeliveriesTable(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->service->acceptOffer(2, $offerId, 30.0);   // delivery 1
        $this->service->deliverPartial(2, $offerId, 20.0); // delivery 2

        $deliveries = $this->service->listMyDeliveries(2, 10, 0);
        $count = $this->service->countMyDeliveries(2);

        $this->assertCount(2, $deliveries);
        $this->assertSame(2, $count);
        $deliveredBbls = array_map(static fn($d) => round((float)$d['delivered_bbl'], 2), $deliveries);
        $this->assertContains(30.0, $deliveredBbls);
        $this->assertContains(20.0, $deliveredBbls);
    }

    public function testListAdminDeliveriesReturnsAllDeliveries(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->service->acceptOffer(2, $offerId, 30.0);

        $deliveries = $this->service->listAdminDeliveries([], 10, 0);
        $count = $this->service->countAdminDeliveries([]);

        $this->assertCount(1, $deliveries);
        $this->assertSame(1, $count);
        $this->assertSame((int)$offerId, (int)$deliveries[0]['offer_id']);
        $this->assertSame(30.0, round((float)$deliveries[0]['delivered_bbl'], 2));
    }

    public function testKeyEventsAreLoggedToContractLogsTable(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->service->acceptOffer(2, $offerId, 30.0);
        $this->service->deliverPartial(2, $offerId, 20.0);

        $eventKeys = $this->db->query(
            "SELECT event_key FROM b2b_contract_logs ORDER BY id"
        )->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('offer_accepted', $eventKeys);
        $this->assertContains('partial_delivery_made', $eventKeys);
        $this->assertContains('partial_payment_released', $eventKeys);
    }

    public function testListMyDeliveriesPaginationReturnsCorrectOffset(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 1000.0);
        // Offer for 200 bbl so we can do 3 deliveries
        $offerId = (int)$this->service->createBuyOffer(1, 200.0, 100.0, 120)['offer_id'];
        $this->service->acceptOffer(2, $offerId, 50.0);    // delivery 1
        $this->service->deliverPartial(2, $offerId, 80.0); // delivery 2
        $this->service->deliverPartial(2, $offerId, 70.0); // delivery 3

        $page1 = $this->service->listMyDeliveries(2, 2, 0); // first 2
        $page2 = $this->service->listMyDeliveries(2, 2, 2); // third one

        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);
        $this->assertSame(3, $this->service->countMyDeliveries(2));
    }

    public function testTickModuleFinalizesSetsCorrectStatsKeys(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->service->acceptOffer(2, $offerId, 30.0);
        $this->db->prepare("UPDATE b2b_contract_offers SET delivery_deadline_at = ? WHERE id = ?")
            ->execute(['2000-01-01 00:00:00', $offerId]);

        $module = new B2BContractsModule();
        $ctx = new TickContext($this->db, new DateTimeImmutable('2001-01-01 00:00:00'), 'test');
        $module->run($ctx);

        $stats = $module->stats();
        $this->assertSame(0, $stats['b2b_contracts_expired']);
        $this->assertSame(1, $stats['b2b_contracts_finalized']);
        $this->assertSame(1, $stats['b2b_contracts_partial_done']);
        $this->assertSame(0, $stats['b2b_contracts_failed']);
        $this->assertSame('partial_done', $this->offerStatus($offerId));
    }

    public function testTickModuleDoesNotFinalizeAcceptedOffersWhenAutoFinalizeDisabled(): void
    {
        $this->service->saveConfig(['auto_finalize_after_deadline' => 0]);
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->service->acceptOffer(2, $offerId, 30.0);
        $this->db->prepare("UPDATE b2b_contract_offers SET delivery_deadline_at = ? WHERE id = ?")
            ->execute(['2000-01-01 00:00:00', $offerId]);

        $module = new B2BContractsModule();
        $ctx = new TickContext($this->db, new DateTimeImmutable('2001-01-01 00:00:00'), 'test');
        $module->run($ctx);

        $this->assertSame(0, $module->stats()['b2b_contracts_finalized']);
        $this->assertSame('accepted', $this->offerStatus($offerId));
    }

    public function testSellerAbandonFinalizesWithoutBackdatingDeadline(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $this->seedPlayer(2, 0.0, 1000.0);
        $this->seedStorage(2, 500.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->service->acceptOffer(2, $offerId, 30.0);
        $deadlineBefore = (string)$this->db->query("SELECT delivery_deadline_at FROM b2b_contract_offers WHERE id = {$offerId}")->fetchColumn();

        $result = $this->service->sellerAbandonOffer(2, $offerId, 'test');
        $deadlineAfter = (string)$this->db->query("SELECT delivery_deadline_at FROM b2b_contract_offers WHERE id = {$offerId}")->fetchColumn();

        $this->assertTrue($result['success']);
        $this->assertSame('partial_done', $result['status']);
        $this->assertSame($deadlineBefore, $deadlineAfter);
        $this->assertSame('partial_done', $this->offerStatus($offerId));
    }

    public function testAdminFlagUnflagAndCancel(): void
    {
        $this->seedPlayer(1, 0.0, 50000.0);
        $offerId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];

        $this->assertTrue($this->service->adminFlagOffer(99, $offerId, 'review')['success']);
        $this->assertSame(1, (int)$this->db->query("SELECT is_flagged FROM b2b_contract_offers WHERE id = {$offerId}")->fetchColumn());

        $this->assertTrue($this->service->adminUnflagOffer(99, $offerId)['success']);
        $this->assertSame(0, (int)$this->db->query("SELECT is_flagged FROM b2b_contract_offers WHERE id = {$offerId}")->fetchColumn());

        $result = $this->service->adminCancelOffer(99, $offerId, 'admin review');
        $this->assertTrue($result['success']);
        $this->assertSame(50000.0, $this->bankOf(1));
        $this->assertSame('cancelled', $this->offerStatus($offerId));
    }

    public function testAdminOfferFiltersAndReputationListUsePagination(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);
        $this->seedPlayer(2, 0.0, 0.0);
        $this->seedStorage(2, 1000.0);
        $openId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $doneId = (int)$this->service->createBuyOffer(1, 100.0, 100.0, 120)['offer_id'];
        $this->assertTrue($this->service->acceptAndDeliver(2, $doneId)['success']);
        $this->assertTrue($this->service->adminFlagOffer(99, $openId, 'review')['success']);

        $flagged = $this->service->listAdminOffers(['flagged' => '1'], 10, 0);
        $completed = $this->service->listAdminOffers(['status' => 'completed'], 10, 0);
        $paged = $this->service->listAdminOffers([], 1, 1);
        $rep = $this->service->listReputationScores('', 10, 0);

        $this->assertCount(1, $flagged);
        $this->assertSame($openId, (int)$flagged[0]['id']);
        $this->assertCount(1, $completed);
        $this->assertSame($doneId, (int)$completed[0]['id']);
        $this->assertCount(1, $paged);
        $this->assertGreaterThanOrEqual(2, $this->service->countAdminOffers([]));
        $this->assertNotEmpty($rep);
    }

    private function createBaseSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE players (
                id INTEGER PRIMARY KEY,
                username TEXT NOT NULL,
                company_name TEXT NULL,
                cash REAL NOT NULL DEFAULT 0,
                bank_balance REAL NOT NULL DEFAULT 0,
                company_credibility INTEGER NOT NULL DEFAULT 50
            )'
        );
        $this->db->exec(
            'CREATE TABLE storage (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                capacity REAL NOT NULL DEFAULT 0,
                used REAL NOT NULL DEFAULT 0
            )'
        );
        $this->db->exec(
            'CREATE TABLE market_state (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                current_price REAL NOT NULL DEFAULT 100,
                oil_price REAL NOT NULL DEFAULT 100
            )'
        );
        $this->db->exec('INSERT INTO market_state (current_price, oil_price) VALUES (100, 100)');
    }

    private function seedPlayer(int $id, float $cash, float $bank): void
    {
        $this->db->prepare('INSERT INTO players (id, username, company_name, cash, bank_balance) VALUES (?, ?, ?, ?, ?)')
            ->execute([$id, 'player' . $id, 'Company ' . $id, $cash, $bank]);
    }

    private function seedStorage(int $playerId, float $used): void
    {
        $this->db->prepare('INSERT INTO storage (player_id, capacity, used) VALUES (?, ?, ?)')
            ->execute([$playerId, max($used, 1000.0), $used]);
    }

    private function cashOf(int $playerId): float
    {
        return round((float)$this->db->query("SELECT cash FROM players WHERE id = {$playerId}")->fetchColumn(), 2);
    }

    private function bankOf(int $playerId): float
    {
        return round((float)$this->db->query("SELECT bank_balance FROM players WHERE id = {$playerId}")->fetchColumn(), 2);
    }

    private function storageOf(int $playerId): float
    {
        return round((float)$this->db->query("SELECT COALESCE(SUM(used), 0) FROM storage WHERE player_id = {$playerId}")->fetchColumn(), 2);
    }

    private function offerStatus(int $offerId): string
    {
        return (string)$this->db->query("SELECT status FROM b2b_contract_offers WHERE id = {$offerId}")->fetchColumn();
    }

    private function txCount(string $type): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM bank_transactions WHERE transaction_type = ?');
        $stmt->execute([$type]);
        return (int)$stmt->fetchColumn();
    }

    private function b2bScore(int $playerId): int
    {
        $stmt = $this->db->prepare('SELECT score FROM b2b_reputation_scores WHERE player_id = ?');
        $stmt->execute([$playerId]);
        return (int)$stmt->fetchColumn();
    }

    private function deliveryCount(int $offerId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM b2b_contract_deliveries WHERE offer_id = ?');
        $stmt->execute([$offerId]);
        return (int)$stmt->fetchColumn();
    }

    private function offerFloat(int $offerId, string $column): float
    {
        $allowed = ['released_amount', 'remaining_escrow_amount'];
        $this->assertContains($column, $allowed);
        return round((float)$this->db->query("SELECT {$column} FROM b2b_contract_offers WHERE id = {$offerId}")->fetchColumn(), 2);
    }
}
