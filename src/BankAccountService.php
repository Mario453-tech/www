<?php

declare(strict_types=1);

/**
 * BankAccountService — fundament systemu bankowego (Etap 1: schemat).
 * BankAccountService — banking system foundation (Stage 1: schema).
 *
 * Zakres etapu 1 (zgodnie z briefem § "Założenia architektoniczne"):
 *  - dodanie kolumny players.bank_account_number (VARCHAR(32) UNIQUE NULL),
 *  - utworzenie tabeli historii bank_transactions.
 *
 * Stage 1 scope (per brief § "Architectural assumptions"):
 *  - add players.bank_account_number column (VARCHAR(32) UNIQUE NULL),
 *  - create the bank_transactions history table.
 *
 * NIE wdrażamy tu (zgodnie z briefem):
 *  - drugiej tabeli kont (player_bank_accounts) — saldo `players.cash` pozostaje źródłem prawdy,
 *  - migracji środków,
 *  - logiki przelewów (etap 3 / FinancialTransactionService).
 *
 * NOT in scope here (per brief):
 *  - a second accounts table (player_bank_accounts) — `players.cash` remains source of truth,
 *  - any money migration,
 *  - transfer logic (stage 3 / FinancialTransactionService).
 */
class BankAccountService
{
    private PDO $db;

    /** @var array<int,bool> cache zapewnionego schematu per polaczenie / schema-ensured cache per connection */
    private static array $schemaEnsured = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->ensureSchema();
    }

    /**
     * Zwraca sterownik PDO (mysql/sqlite). Na blad zaklada mysql (produkcja).
     * Returns the PDO driver name (mysql/sqlite). On error assumes mysql (production).
     */
    private function driver(): string
    {
        try {
            return (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            return 'mysql';
        }
    }

    /**
     * Tworzy kolumne i tabele (idempotentnie). DDL MySQL — na SQLite (testy) jest no-op,
     * bo testy buduja wlasny schemat.
     * Creates the column and table (idempotent). MySQL DDL — no-op on SQLite (tests
     * build their own schema).
     */
    public function ensureSchema(): void
    {
        $connId = spl_object_id($this->db);
        if (isset(self::$schemaEnsured[$connId])) {
            return;
        }

        if ($this->driver() !== 'mysql') {
            self::$schemaEnsured[$connId] = true;
            return;
        }

        // DDL (ALTER/CREATE) robi w MySQL niejawny commit — nie wolno go odpalac
        // wewnatrz transakcji. Odraczamy do pierwszej konstrukcji poza transakcja.
        // DDL (ALTER/CREATE) triggers an implicit commit in MySQL — never run inside
        // a transaction. Defer until first construction outside a transaction.
        try {
            if ($this->db->inTransaction()) {
                return;
            }
        } catch (Throwable) {
            // Brak wsparcia inTransaction — kontynuuj / inTransaction unsupported — continue
        }

        self::$schemaEnsured[$connId] = true;

        try {
            // Nowe pole numeru rachunku w graczach (brief § "Nowe pole w tabeli graczy").
            // New account number field on players (brief § "New player field").
            // UNIQUE dodajemy osobno (ALTER ADD COLUMN UNIQUE bywa kapryśny przy istniejących NULLach).
            // UNIQUE added separately (ALTER ADD COLUMN UNIQUE can be flaky with existing NULLs).
            Database::addColumnIfMissing(
                'players',
                'bank_account_number',
                'VARCHAR(32) NULL DEFAULT NULL'
            );
            $this->ensureUniqueIndex('players', 'uq_players_bank_account_number', 'bank_account_number');

            // Historia operacji finansowych (brief § "Historia operacji").
            // Financial transaction history (brief § "Transaction history").
            //
            // from_player_id / to_player_id moga byc NULL:
            //  - NULL from = wplyw z zewnatrz/systemu (np. wyplata kredytu, rejestracyjne 10M),
            //  - NULL to   = wyplyw poza system (np. podatek, oplata prawna).
            // Ten model pozwala uniknac sztucznych "kont systemowych".
            //
            // from_player_id / to_player_id may be NULL:
            //  - NULL from = inflow from outside/system (e.g. loan disbursement, register-bonus 10M),
            //  - NULL to   = outflow outside the system (e.g. tax, legal fee).
            // This avoids synthetic "system accounts".
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS bank_transactions (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    from_player_id INT UNSIGNED NULL DEFAULT NULL,
                    to_player_id INT UNSIGNED NULL DEFAULT NULL,
                    amount DECIMAL(20,2) NOT NULL,
                    transaction_type VARCHAR(32) NOT NULL,
                    description VARCHAR(255) NULL DEFAULT NULL,
                    reference_type VARCHAR(32) NULL DEFAULT NULL,
                    reference_id BIGINT UNSIGNED NULL DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_from_created (from_player_id, created_at),
                    KEY idx_to_created (to_player_id, created_at),
                    KEY idx_type_created (transaction_type, created_at),
                    KEY idx_ref (reference_type, reference_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('BankAccountService', 'ensureSchema FAILED', $e);
            }
        }
    }

    /**
     * Dodaje UNIQUE indeks idempotentnie — sprawdza INFORMATION_SCHEMA.STATISTICS.
     * Adds a UNIQUE index idempotently — checks INFORMATION_SCHEMA.STATISTICS.
     */
    private function ensureUniqueIndex(string $table, string $indexName, string $column): void
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND INDEX_NAME = ?"
            );
            $stmt->execute([$table, $indexName]);
            if ((int)$stmt->fetchColumn() === 0) {
                $this->db->exec(
                    "ALTER TABLE `{$table}` ADD UNIQUE KEY `{$indexName}` (`{$column}`)"
                );
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('BankAccountService', 'ensureUniqueIndex FAILED', $e, [
                    'table' => $table, 'index' => $indexName, 'column' => $column,
                ]);
            }
        }
    }
}
