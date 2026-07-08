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

        $this->assertSame(['expired' => 1, 'refunded' => 10000.0], $module->stats());
        $this->assertSame(50000.0, $this->bankOf(1));
        $this->assertSame('expired', $this->offerStatus($offerId));
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
}
