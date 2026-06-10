<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/FinancialTransactionService.php';

/**
 * Brief, sekcja "NOWY FUNDAMENT - API FINANSOWE" oraz "Formularz przelewu":
 * testy credit/debit/transfer/logTransaction.
 *
 * Brief, "NEW FOUNDATION - FINANCIAL API" and "Transfer form":
 * tests for credit/debit/transfer/logTransaction.
 */
final class FinancialTransactionServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private FinancialTransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->service = new FinancialTransactionService($this->db);
    }

    // ================================================================== credit()

    public function testCreditAddsFundsAndLogsTransaction(): void
    {
        $this->seedPlayer(1, 1000.00);

        $r = $this->service->credit(1, 250.00, FinancialTransactionService::TYPE_LOAN, 'Wyplata kredytu');

        $this->assertTrue($r['success']);
        $this->assertNotNull($r['transaction_id']);
        $this->assertNull($r['error']);
        // loan -> POOL_BANK (WalletConfig): srodki trafiaja na konto, gotowka bez zmian.
        // loan -> POOL_BANK (WalletConfig): funds go to the bank account, cash unchanged.
        $this->assertSame(1000.00, $this->cashOf(1), 'Gotowka bez zmian - kredyt idzie na konto.');
        $this->assertSame(250.00, $this->bankOf(1));

        $tx = $this->lastTransaction();
        $this->assertNull($tx['from_player_id'], 'credit = from NULL (wplyw z systemu)');
        $this->assertSame(1, (int)$tx['to_player_id']);
        $this->assertSame(250.00, (float)$tx['amount']);
        $this->assertSame('loan', $tx['transaction_type']);
        $this->assertSame('Wyplata kredytu', $tx['description']);
    }

    public function testCreditRejectsZeroAmount(): void
    {
        $this->seedPlayer(1, 1000);
        $r = $this->service->credit(1, 0.0, FinancialTransactionService::TYPE_LOAN);
        $this->assertFalse($r['success']);
        $this->assertSame('invalid_amount', $r['error']);
        $this->assertSame(1000.00, $this->cashOf(1));
        $this->assertCount(0, $this->allTransactions());
    }

    public function testCreditRejectsNegativeAmount(): void
    {
        $this->seedPlayer(1, 1000);
        $r = $this->service->credit(1, -50.0, FinancialTransactionService::TYPE_LOAN);
        $this->assertFalse($r['success']);
        $this->assertSame('invalid_amount', $r['error']);
    }

    public function testCreditRejectsInvalidType(): void
    {
        $this->seedPlayer(1, 1000);
        $r = $this->service->credit(1, 100.0, 'made_up_type');
        $this->assertFalse($r['success']);
        $this->assertSame('invalid_type', $r['error']);
    }

    public function testCreditRejectsUnknownPlayer(): void
    {
        $r = $this->service->credit(999, 100.0, FinancialTransactionService::TYPE_LOAN);
        $this->assertFalse($r['success']);
        $this->assertSame('recipient_not_found', $r['error']);
        $this->assertCount(0, $this->allTransactions());
    }

    // ================================================================== debit()

    public function testDebitRemovesFundsAndLogs(): void
    {
        $this->seedPlayer(1, 1000.00);

        $r = $this->service->debit(1, 300.00, FinancialTransactionService::TYPE_TAX, 'Podatek 2026');

        $this->assertTrue($r['success']);
        $this->assertSame(700.00, $this->cashOf(1));
        $tx = $this->lastTransaction();
        $this->assertSame(1, (int)$tx['from_player_id']);
        $this->assertNull($tx['to_player_id'], 'debit = to NULL (wyplyw na zewnatrz)');
        $this->assertSame('tax', $tx['transaction_type']);
    }

    public function testDebitFailsOnInsufficientFunds(): void
    {
        $this->seedPlayer(1, 100.00);
        $r = $this->service->debit(1, 150.00, FinancialTransactionService::TYPE_TAX);

        $this->assertFalse($r['success']);
        $this->assertSame('insufficient_funds', $r['error']);
        $this->assertSame(100.00, $this->cashOf(1), 'Saldo nie zmienia sie po nieudanym debit.');
        $this->assertCount(0, $this->allTransactions(), 'Nie powstaje wpis przy nieudanym debit.');
    }

    public function testDebitFailsForUnknownPlayer(): void
    {
        $r = $this->service->debit(999, 50.0, FinancialTransactionService::TYPE_TAX);
        $this->assertFalse($r['success']);
        $this->assertSame('sender_not_found', $r['error']);
    }

    public function testDebitAllowsFullBalance(): void
    {
        $this->seedPlayer(1, 100.00);
        $r = $this->service->debit(1, 100.00, FinancialTransactionService::TYPE_TAX);
        $this->assertTrue($r['success']);
        $this->assertSame(0.00, $this->cashOf(1));
    }

    // ================================================================== transfer()

    public function testTransferMovesFundsAtomicallyAndLogs(): void
    {
        // player_transfer -> POOL_BANK (WalletConfig): przelew P2P rusza konto bankowe.
        // player_transfer -> POOL_BANK (WalletConfig): P2P transfer moves the bank account.
        $this->seedPlayer(1, 0.00, 1000.00);
        $this->seedPlayer(2, 0.00, 500.00);

        $r = $this->service->transfer(1, 2, 200.00, 'Za wynajem rurociagu');

        $this->assertTrue($r['success']);
        $this->assertSame(800.00, $this->bankOf(1));
        $this->assertSame(700.00, $this->bankOf(2));

        $tx = $this->lastTransaction();
        $this->assertSame(1, (int)$tx['from_player_id']);
        $this->assertSame(2, (int)$tx['to_player_id']);
        $this->assertSame('player_transfer', $tx['transaction_type']);
        $this->assertSame('Za wynajem rurociagu', $tx['description']);
    }

    public function testTransferToSelfBlocked(): void
    {
        $this->seedPlayer(1, 1000.00);
        $r = $this->service->transfer(1, 1, 100.00);
        $this->assertFalse($r['success']);
        $this->assertSame('self_transfer', $r['error']);
        $this->assertSame(1000.00, $this->cashOf(1));
    }

    public function testTransferInsufficientFunds(): void
    {
        $this->seedPlayer(1, 50.00);
        $this->seedPlayer(2, 500.00);
        $r = $this->service->transfer(1, 2, 100.00);
        $this->assertFalse($r['success']);
        $this->assertSame('insufficient_funds', $r['error']);
        $this->assertSame(50.00, $this->cashOf(1));
        $this->assertSame(500.00, $this->cashOf(2));
        $this->assertCount(0, $this->allTransactions());
    }

    public function testTransferUnknownRecipient(): void
    {
        // player_transfer -> POOL_BANK: nadawca musi miec srodki na koncie, by przejsc
        // walidacje salda i dojsc do sprawdzenia odbiorcy.
        // player_transfer -> POOL_BANK: sender needs bank funds to pass the balance check
        // and reach the recipient validation.
        $this->seedPlayer(1, 0.00, 1000.00);
        $r = $this->service->transfer(1, 999, 100.00);
        $this->assertFalse($r['success']);
        $this->assertSame('recipient_not_found', $r['error']);
        $this->assertSame(1000.00, $this->bankOf(1), 'Rollback - saldo nadawcy bez zmian.');
    }

    public function testTransferRejectsZeroAmount(): void
    {
        $this->seedPlayer(1, 1000.00);
        $this->seedPlayer(2, 500.00);
        $r = $this->service->transfer(1, 2, 0.0);
        $this->assertFalse($r['success']);
        $this->assertSame('invalid_amount', $r['error']);
    }

    public function testTransferRoundsToTwoDecimals(): void
    {
        // player_transfer -> POOL_BANK: zaokraglenie sprawdzamy na puli bankowej.
        // player_transfer -> POOL_BANK: rounding is checked on the bank pool.
        $this->seedPlayer(1, 0.00, 1000.00);
        $this->seedPlayer(2, 0.00, 0.00);
        $r = $this->service->transfer(1, 2, 100.4567);
        $this->assertTrue($r['success']);
        $this->assertSame(100.46, $r['amount']);
        $this->assertSame(899.54, $this->bankOf(1));
        $this->assertSame(100.46, $this->bankOf(2));
    }

    // ================================================================== logTransaction()

    public function testLogTransactionWritesEntryWithoutChangingBalance(): void
    {
        $this->seedPlayer(1, 1000.00);
        $this->seedPlayer(2, 500.00);

        $id = $this->service->logTransaction(1, 2, 75.00, FinancialTransactionService::TYPE_MARKET_SALE, 'audit only', 'market_offer', 42);

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
        $this->assertSame(1000.00, $this->cashOf(1), 'Saldo nie zmienia sie - to tylko log.');
        $this->assertSame(500.00, $this->cashOf(2));

        $tx = $this->lastTransaction();
        $this->assertSame('market_sale', $tx['transaction_type']);
        $this->assertSame('audit only', $tx['description']);
        $this->assertSame('market_offer', $tx['reference_type']);
        $this->assertSame(42, (int)$tx['reference_id']);
    }

    public function testLogTransactionReturnsNullOnInvalidType(): void
    {
        $this->assertNull($this->service->logTransaction(1, 2, 50.00, 'not_in_list'));
    }

    public function testLogTransactionReturnsNullOnZeroAmount(): void
    {
        $this->assertNull($this->service->logTransaction(1, 2, 0.0, FinancialTransactionService::TYPE_TAX));
    }

    // ============================================================== Tick audit

    public function testTickAuditTypesAreAccepted(): void
    {
        $this->seedPlayer(1, 1000.00);

        foreach (FinancialTransactionService::TICK_AUDIT_TYPES as $type) {
            $id = $this->service->logTransaction(1, null, 12.34, $type, 'tick audit', 'tick', null);
            $this->assertIsInt($id, "Typ {$type} musi byc dozwolony / type {$type} must be allowed");
        }

        $this->assertSame(1000.00, $this->cashOf(1), 'Audit nie zmienia salda.');
        $this->assertCount(count(FinancialTransactionService::TICK_AUDIT_TYPES), $this->allTransactions());
    }

    public function testPurgeTickAuditDeletesOnlyOldTickEntries(): void
    {
        $this->seedPlayer(1, 1000.00);

        // Stary wpis tickowy (40 dni) - do usuniecia / Old tick entry (40 days) - purged.
        $this->insertTransactionAt(1, FinancialTransactionService::TYPE_TICK_OPEX, "-40 days");
        // Swiezy wpis tickowy (1 dzien) - zostaje / Fresh tick entry (1 day) - kept.
        $this->insertTransactionAt(1, FinancialTransactionService::TYPE_TICK_OPEX, "-1 days");
        // Stary przelew (40 dni) - zostaje na zawsze / Old transfer (40 days) - kept forever.
        $this->insertTransactionAt(1, FinancialTransactionService::TYPE_PLAYER_TRANSFER, "-40 days");

        $deleted = $this->service->purgeTickAudit(30);

        $this->assertSame(1, $deleted);
        $remaining = array_column($this->allTransactions(), 'transaction_type');
        sort($remaining);
        $this->assertSame(['player_transfer', 'tick_opex'], $remaining);
    }

    // ============================================================== Helpers

    private function insertTransactionAt(int $playerId, string $type, string $ageModifier): void
    {
        $this->db->prepare(
            "INSERT INTO bank_transactions
                (from_player_id, to_player_id, amount, transaction_type, created_at)
             VALUES (?, NULL, 10.0, ?, datetime('now', ?))"
        )->execute([$playerId, $type, $ageModifier]);
    }


    private function seedPlayer(int $id, float $cash, float $bank = 0.0): void
    {
        $this->db->prepare("INSERT INTO players (id, cash, bank_balance) VALUES (?, ?, ?)")
                 ->execute([$id, $cash, $bank]);
    }

    private function cashOf(int $id): float
    {
        $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
        $stmt->execute([$id]);
        return (float)$stmt->fetchColumn();
    }

    private function bankOf(int $id): float
    {
        $stmt = $this->db->prepare("SELECT bank_balance FROM players WHERE id = ?");
        $stmt->execute([$id]);
        return (float)$stmt->fetchColumn();
    }

    /** @return array<string,mixed> */
    private function lastTransaction(): array
    {
        $row = $this->db->query("SELECT * FROM bank_transactions ORDER BY id DESC LIMIT 1")->fetch();
        return $row ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    private function allTransactions(): array
    {
        return $this->db->query("SELECT * FROM bank_transactions ORDER BY id ASC")->fetchAll();
    }

    private function createSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE players (
                id INTEGER PRIMARY KEY,
                cash REAL NOT NULL DEFAULT 0,
                bank_balance REAL NOT NULL DEFAULT 0
            )'
        );
        $this->db->exec(
            'CREATE TABLE bank_transactions (
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
