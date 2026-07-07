<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/FinancialTransactionService.php';
require_once dirname(__DIR__, 2) . '/src/WalletConfig.php';
require_once dirname(__DIR__, 2) . '/src/init.php';

/**
 * Izolacja SAVEPOINT w FTS: gdy zapis pieniedzy zawiedzie W POLOWIE wewnatrz transakcji
 * wolajacego (wywolanie zagniezdzone), zmiany FTS musza sie wycofac bez zabijania
 * transakcji wolajacego.
 *
 * FTS SAVEPOINT isolation: when a money write fails mid-way inside a caller's transaction
 * (nested call), the FTS changes must roll back without killing the caller's transaction.
 */
final class FinancialTransactionSavepointTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private FinancialTransactionService $fts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        // Trigger wymusza blad INSERT-a do bank_transactions dla sentinela 'FORCE_FAIL',
        // symulujac blad DB juz PO zmianie salda (RAISE(ABORT) nie zabija transakcji).
        $this->db->exec(
            "CREATE TRIGGER force_fail_bank_tx BEFORE INSERT ON bank_transactions
             WHEN NEW.description = 'FORCE_FAIL'
             BEGIN SELECT RAISE(ABORT, 'forced insert failure'); END"
        );
        $this->fts = new FinancialTransactionService($this->db);
    }

    public function testNestedFailureRollsBackToSavepointAndKeepsOuterTransactionAlive(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $this->seedPlayer(2, 0.0, 0.0);

        $this->db->beginTransaction();
        // Praca wolajacego przed wywolaniem FTS.
        $this->db->prepare("UPDATE players SET cash = cash + 100 WHERE id = 2")->execute();

        // Zagniezdzone credit, ktore zawiedzie na logu (po dodaniu 500 do bank_balance).
        $res = $this->fts->credit(1, 500.0, FinancialTransactionService::TYPE_CONTRACT_SALE, 'FORCE_FAIL');

        $this->assertFalse($res['success']);
        $this->assertSame('db_error', $res['error']);
        // Zmiana FTS wycofana do SAVEPOINT — saldo gracza 1 bez zmian.
        $this->assertSame(0.0, $this->bankOf(1), 'FTS balance change must be rolled back to savepoint');
        // Transakcja wolajacego zyje dalej.
        $this->assertTrue($this->db->inTransaction(), 'caller transaction must stay alive');

        // Wolajacy moze dalej pisac i zatwierdzic.
        $this->db->prepare("UPDATE players SET cash = cash + 7 WHERE id = 2")->execute();
        $this->db->commit();

        $this->assertSame(107.0, $this->cashOf(2), 'caller writes must survive');
        $this->assertSame(0.0, $this->bankOf(1), 'no partial FTS money movement may persist');
        // Zaden wpis logu dla nieudanej operacji.
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM bank_transactions")->fetchColumn());
    }

    public function testNestedSuccessAfterEarlierNestedFailurePersists(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);

        $this->db->beginTransaction();

        // Pierwsze wywolanie zawodzi (rollback do wlasnego savepointu).
        $bad = $this->fts->credit(1, 500.0, FinancialTransactionService::TYPE_CONTRACT_SALE, 'FORCE_FAIL');
        $this->assertFalse($bad['success']);

        // Drugie wywolanie (osobny savepoint) przechodzi i musi zostac po commit.
        $good = $this->fts->credit(1, 250.0, FinancialTransactionService::TYPE_CONTRACT_SALE, 'ok');
        $this->assertTrue($good['success'], (string)$good['error']);

        $this->db->commit();

        $this->assertSame(250.0, $this->bankOf(1));
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM bank_transactions")->fetchColumn());
    }

    public function testStandaloneFailureRollsBackOwnTransaction(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);

        // Bez zewnetrznej transakcji — FTS otwiera wlasna i musi ja wycofac przy bledzie.
        $res = $this->fts->credit(1, 500.0, FinancialTransactionService::TYPE_CONTRACT_SALE, 'FORCE_FAIL');

        $this->assertFalse($res['success']);
        $this->assertFalse($this->db->inTransaction(), 'own transaction must be rolled back and closed');
        $this->assertSame(0.0, $this->bankOf(1));
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM bank_transactions")->fetchColumn());
    }

    public function testNestedDebitCombinedFailureRollsBackToSavepoint(): void
    {
        // bank 300 + cash 400; debitCombined pobiera 500 (bank-first), potem log zawodzi.
        $this->seedPlayer(1, 400.0, 300.0);

        $this->db->beginTransaction();
        $this->db->prepare("UPDATE players SET cash = cash + 1 WHERE id = 1")->execute();

        $res = $this->fts->debitCombined(1, 500.0, FinancialTransactionService::TYPE_CONTRACT_PENALTY, 'FORCE_FAIL');

        $this->assertFalse($res['success']);
        // Oba UPDATE-y (bank -300, cash -200) wycofane do savepointu; zostaje tylko +1 wolajacego.
        $this->assertTrue($this->db->inTransaction());
        $this->db->commit();

        $this->assertSame(300.0, $this->bankOf(1), 'bank must be restored');
        $this->assertSame(401.0, $this->cashOf(1), 'only caller +1 survives');
        $this->assertSame(0, (int)$this->db->query("SELECT COUNT(*) FROM bank_transactions")->fetchColumn());
    }

    // ================================================================== helpers

    private function seedPlayer(int $id, float $cash, float $bank): void
    {
        $this->db->prepare('INSERT INTO players (id, cash, bank_balance) VALUES (?, ?, ?)')
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
