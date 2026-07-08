<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/FinancialTransactionService.php';
require_once dirname(__DIR__, 2) . '/src/WalletConfig.php';
require_once dirname(__DIR__, 2) . '/src/init.php';

/**
 * Testy Etapu 2 kontraktow: typy FTS, routing pul, klucze lang.
 * Stage 2 contract tests: FTS types, pool routing, lang keys.
 */
final class ContractFinancesTest extends SqliteIntegrationTestCase
{
    private FinancialTransactionService $fts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->fts = new FinancialTransactionService($this->db);
    }

    // ================================================================== typy FTS / FTS types

    public function testContractTypesExistAsConstants(): void
    {
        $this->assertSame('contract_sale',    FinancialTransactionService::TYPE_CONTRACT_SALE);
        $this->assertSame('contract_penalty', FinancialTransactionService::TYPE_CONTRACT_PENALTY);
        $this->assertSame('contract_bonus',   FinancialTransactionService::TYPE_CONTRACT_BONUS);
        $this->assertSame('b2b_escrow_lock', FinancialTransactionService::TYPE_B2B_ESCROW_LOCK);
        $this->assertSame('b2b_escrow_refund', FinancialTransactionService::TYPE_B2B_ESCROW_REFUND);
        $this->assertSame('b2b_cancel_penalty', FinancialTransactionService::TYPE_B2B_CANCEL_PENALTY);
        $this->assertSame('b2b_trade_revenue', FinancialTransactionService::TYPE_B2B_TRADE_REVENUE);
    }

    public function testContractTypesAreInAllowedTypes(): void
    {
        $this->assertContains(
            FinancialTransactionService::TYPE_CONTRACT_SALE,
            FinancialTransactionService::ALLOWED_TYPES,
            'contract_sale nie ma w ALLOWED_TYPES'
        );
        $this->assertContains(
            FinancialTransactionService::TYPE_CONTRACT_PENALTY,
            FinancialTransactionService::ALLOWED_TYPES,
            'contract_penalty nie ma w ALLOWED_TYPES'
        );
        $this->assertContains(
            FinancialTransactionService::TYPE_CONTRACT_BONUS,
            FinancialTransactionService::ALLOWED_TYPES,
            'contract_bonus nie ma w ALLOWED_TYPES'
        );
        $this->assertContains(FinancialTransactionService::TYPE_B2B_ESCROW_LOCK, FinancialTransactionService::ALLOWED_TYPES);
        $this->assertContains(FinancialTransactionService::TYPE_B2B_ESCROW_REFUND, FinancialTransactionService::ALLOWED_TYPES);
        $this->assertContains(FinancialTransactionService::TYPE_B2B_CANCEL_PENALTY, FinancialTransactionService::ALLOWED_TYPES);
        $this->assertContains(FinancialTransactionService::TYPE_B2B_TRADE_REVENUE, FinancialTransactionService::ALLOWED_TYPES);
    }

    // ================================================================== routing pul / pool routing

    public function testContractSaleRoutesToBank(): void
    {
        $this->assertSame(
            WalletConfig::POOL_BANK,
            WalletConfig::TYPE_TO_POOL[FinancialTransactionService::TYPE_CONTRACT_SALE] ?? null,
            'contract_sale powinien trafiac na POOL_BANK'
        );
    }

    public function testContractBonusRoutesToBank(): void
    {
        $this->assertSame(
            WalletConfig::POOL_BANK,
            WalletConfig::TYPE_TO_POOL[FinancialTransactionService::TYPE_CONTRACT_BONUS] ?? null,
            'contract_bonus powinien trafiac na POOL_BANK'
        );
    }

    public function testContractPenaltyRoutesToBank(): void
    {
        $this->assertSame(
            WalletConfig::POOL_BANK,
            WalletConfig::TYPE_TO_POOL[FinancialTransactionService::TYPE_CONTRACT_PENALTY] ?? null,
            'contract_penalty powinien byc zmapowany na POOL_BANK'
        );
    }

    public function testB2BTypesRouteToBank(): void
    {
        $types = [
            FinancialTransactionService::TYPE_B2B_ESCROW_LOCK,
            FinancialTransactionService::TYPE_B2B_ESCROW_REFUND,
            FinancialTransactionService::TYPE_B2B_CANCEL_PENALTY,
            FinancialTransactionService::TYPE_B2B_TRADE_REVENUE,
        ];

        foreach ($types as $type) {
            $this->assertSame(WalletConfig::POOL_BANK, WalletConfig::TYPE_TO_POOL[$type] ?? null, $type);
        }
    }

    // ================================================================== credit contract_sale -> bank_balance

    public function testContractSaleCreditGoesToBankBalance(): void
    {
        $this->seedPlayer(1, 500.00, 0.00);

        $result = $this->fts->credit(
            1,
            1200.00,
            FinancialTransactionService::TYPE_CONTRACT_SALE,
            'Dostawa ropy w ramach kontraktu #7'
        );

        $this->assertTrue($result['success'], 'credit contract_sale powinien zwrocic success=true');
        $this->assertSame(500.00,  $this->cashOf(1),  'Gotowka nie zmienia sie przy contract_sale');
        $this->assertSame(1200.00, $this->bankOf(1),   'Przychod z kontraktu trafia na konto bankowe');
    }

    // ================================================================== credit contract_bonus -> bank_balance

    public function testContractBonusCreditGoesToBankBalance(): void
    {
        $this->seedPlayer(1, 0.00, 300.00);

        $this->fts->credit(1, 450.00, FinancialTransactionService::TYPE_CONTRACT_BONUS, 'Bonus za kontrakt #3');

        $this->assertSame(0.00,   $this->cashOf(1));
        $this->assertSame(750.00, $this->bankOf(1), 'Bonus kontraktowy trafia na konto bankowe');
    }

    // ================================================================== debitCombined contract_penalty

    public function testContractPenaltyDebitCombinedTakesBankFirst(): void
    {
        // debitCombined bierze najpierw z bank_balance, reszte z cash.
        // debitCombined drains bank_balance first, then cash for remainder.
        $this->seedPlayer(1, 200.00, 1000.00);

        $result = $this->fts->debitCombined(
            1,
            200.00,
            FinancialTransactionService::TYPE_CONTRACT_PENALTY,
            'Kara za niedostarczenie ropy w kontrakcie #5'
        );

        $this->assertTrue($result['success']);
        $this->assertSame(200.00, $this->cashOf(1),  'Gotowka nienaruszona - kara pobrana z konta bankowego');
        $this->assertSame(800.00, $this->bankOf(1),  'Kara 200 zdjeta z konta bankowego');
    }

    public function testContractPenaltyDebitCombinedDrainsCashWhenBankInsufficient(): void
    {
        // Gdy bank pokrywa calosc: gotowka nieruszona.
        // Gdy bank nie pokrywa: reszta schodzi z gotowki.
        // When bank covers all: cash untouched. When bank short: remainder from cash.
        $this->seedPlayer(1, 100.00, 50.00);

        $result = $this->fts->debitCombined(
            1,
            120.00,
            FinancialTransactionService::TYPE_CONTRACT_PENALTY,
            'Kara za niedostarczenie ropy w kontrakcie #9'
        );

        $this->assertTrue($result['success']);
        $this->assertSame(30.00, $this->cashOf(1),  'Pozostala gotowka po uzupelnieniu kary z gotowki');
        $this->assertSame(0.00,  $this->bankOf(1),  'Konto wyczerpane w calosci');
    }

    // ================================================================== klucze lang / lang keys

    public function testPolishLangKeysExist(): void
    {
        $this->assertNotEmpty(t('bank.account.type.contract_sale'));
        $this->assertNotEmpty(t('bank.account.type.contract_penalty'));
        $this->assertNotEmpty(t('bank.account.type.contract_bonus'));
        $this->assertNotEmpty(t('bank.tx_contract_sale'));
        $this->assertNotEmpty(t('bank.tx_contract_penalty'));
        $this->assertNotEmpty(t('bank.tx_contract_bonus'));
    }

    public function testLangKeysAreNotRaw(): void
    {
        // Klucz nieistniejacy zwraca surowy string z '.' w nazwie.
        // Non-existent key returns the raw string with '.' in it.
        $this->assertStringNotContainsString(
            'bank.account.type.contract_sale',
            t('bank.account.type.contract_sale'),
            'Klucz nie powinien zwracac samego siebie (brak tlumaczenia)'
        );
    }

    // ================================================================== helpers

    private function seedPlayer(int $id, float $cash, float $bank = 0.0): void
    {
        $this->db->prepare('INSERT INTO players (id, cash, bank_balance) VALUES (?, ?, ?)')
            ->execute([$id, $cash, $bank]);
    }

    private function cashOf(int $id): float
    {
        return (float)$this->db->query("SELECT cash FROM players WHERE id = {$id}")->fetchColumn();
    }

    private function bankOf(int $id): float
    {
        return (float)$this->db->query("SELECT bank_balance FROM players WHERE id = {$id}")->fetchColumn();
    }

    private function createSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS players (
                id INTEGER PRIMARY KEY,
                cash REAL NOT NULL DEFAULT 0,
                bank_balance REAL NOT NULL DEFAULT 0
            )'
        );
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS bank_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                from_player_id INTEGER NULL,
                to_player_id INTEGER NULL,
                amount REAL NOT NULL,
                transaction_type TEXT NOT NULL,
                description TEXT NULL,
                reference_type TEXT NULL,
                reference_id INTEGER NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }
}
