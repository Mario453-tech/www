<?php

declare(strict_types=1);

/**
 * BankAccountService — fundament systemu bankowego (Etap 1: schemat).
 * BankAccountService — banking system foundation (Stage 1: schema).
 *
 * Zakres etapu 1 (zgodnie z briefem, sekcja "Zalozenia architektoniczne"):
 *  - dodanie kolumny players.bank_account_number (VARCHAR(32) UNIQUE NULL),
 *  - utworzenie tabeli historii bank_transactions.
 *
 * Stage 1 scope (per brief, "Architectural assumptions"):
 *  - add players.bank_account_number column (VARCHAR(32) UNIQUE NULL),
 *  - create the bank_transactions history table.
 *
 * NIE wdrazamy tu (zgodnie z briefem):
 *  - drugiej tabeli kont (player_bank_accounts) - saldo players.cash pozostaje zrodlem prawdy,
 *  - migracji srodkow,
 *  - logiki przelewow (etap 3 / FinancialTransactionService).
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
            // UNIQUE dodajemy osobno (ALTER ADD COLUMN UNIQUE bywa kaprysny przy istniejacych NULLach).
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

    // ============================================================== Numery rachunkow
    // ============================================================== Account numbers

    /**
     * Prefiks numerow rachunkow Oil Empire / Oil Empire account number prefix.
     * Format: OC-{6-cyfrowy numer}-{rok}, np. OC-000001-2026
     * Format: OC-{6-digit sequence}-{year}, e.g. OC-000001-2026
     */
    public const ACCOUNT_PREFIX = 'OC';

    /** Maksymalna liczba prob przy kolizji UNIQUE / Max retries on UNIQUE collision. */
    private const MAX_GENERATION_RETRIES = 8;

    /**
     * Zwraca numer rachunku gracza lub NULL, gdy jeszcze nieprzypisany.
     * Returns the player's account number or NULL when not yet assigned.
     */
    public function getAccount(int $playerId): ?string
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT bank_account_number FROM players WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$playerId]);
            $val = $stmt->fetchColumn();
            if ($val === false || $val === null || $val === '') {
                return null;
            }
            return (string)$val;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('BankAccountService', 'getAccount FAILED', $e, ['player_id' => $playerId]);
            }
            return null;
        }
    }

    /**
     * Zapewnia, ze gracz ma numer rachunku. Jesli ma — zwraca istniejacy;
     * w przeciwnym razie generuje nowy, zapisuje atomowo i zwraca.
     * Numer jest NIEZMIENNY (brief § "Nowe pole w tabeli graczy") — funkcja nigdy
     * nie zastapi istniejacego numeru.
     *
     * Ensures the player has an account number. If they have one — return it;
     * otherwise generate a new one, save atomically and return it.
     * The number is IMMUTABLE (brief § "New player field") — this function never
     * replaces an existing number.
     *
     * @return string|null numer rachunku lub NULL przy bledzie / account number or NULL on error
     */
    public function ensureAccount(int $playerId): ?string
    {
        $existing = $this->getAccount($playerId);
        if ($existing !== null) {
            return $existing;
        }

        // Retry przy kolizji UNIQUE (rzadka, gdy dwa watki rejestracji wybiora ten sam numer).
        // Retry on UNIQUE collision (rare; two concurrent registrations may pick the same number).
        for ($attempt = 0; $attempt < self::MAX_GENERATION_RETRIES; $attempt++) {
            $candidate = $this->generateNumber();
            try {
                $stmt = $this->db->prepare(
                    "UPDATE players
                        SET bank_account_number = ?
                      WHERE id = ?
                        AND (bank_account_number IS NULL OR bank_account_number = '')"
                );
                $stmt->execute([$candidate, $playerId]);

                if ($stmt->rowCount() === 1) {
                    if (class_exists('GameLog', false)) {
                        GameLog::info('BankAccountService', 'account assigned', [
                            'player_id' => $playerId,
                            'number'    => $candidate,
                        ]);
                    }
                    return $candidate;
                }

                // rowCount=0: gracz juz ma numer (rownolegly thread) lub gracz nie istnieje.
                // rowCount=0: player already has a number (parallel thread) or player does not exist.
                $now = $this->getAccount($playerId);
                if ($now !== null) {
                    return $now;
                }
                return null; // gracz nie istnieje / player not found
            } catch (PDOException $e) {
                // 23000 = SQLSTATE Integrity constraint violation (UNIQUE collision).
                if ($e->getCode() === '23000' || str_contains((string)$e->getMessage(), 'Duplicate')) {
                    if (class_exists('GameLog', false)) {
                        GameLog::warn('BankAccountService', 'UNIQUE collision, retrying', [
                            'player_id' => $playerId, 'candidate' => $candidate, 'attempt' => $attempt + 1,
                        ]);
                    }
                    continue;
                }
                if (class_exists('GameLog', false)) {
                    GameLog::error('BankAccountService', 'ensureAccount FAILED', $e, [
                        'player_id' => $playerId,
                    ]);
                }
                return null;
            }
        }

        if (class_exists('GameLog', false)) {
            GameLog::error('BankAccountService', 'ensureAccount: max retries exceeded', null, [
                'player_id' => $playerId,
            ]);
        }
        return null;
    }

    /**
     * Zwraca id gracza po numerze rachunku albo NULL.
     * Returns the player id by account number, or NULL.
     */
    public function findPlayerIdByAccount(string $number): ?int
    {
        $number = trim($number);
        if ($number === '') {
            return null;
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT id FROM players WHERE bank_account_number = ? LIMIT 1"
            );
            $stmt->execute([$number]);
            $id = $stmt->fetchColumn();
            return ($id === false || $id === null) ? null : (int)$id;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('BankAccountService', 'findPlayerIdByAccount FAILED', $e, ['number' => $number]);
            }
            return null;
        }
    }

    /**
     * Migracja istniejacych graczy: dla kazdego bez numeru rachunku — wygeneruj i zapisz.
     * Idempotentna; bezpieczna do uruchomienia wielokrotnie. Zwraca podsumowanie.
     *
     * Backfill for existing players: for each player without a number — generate and save.
     * Idempotent; safe to run multiple times. Returns a summary.
     *
     * @return array{generated:int,skipped:int,errors:int,total:int}
     */
    public function migrateExisting(): array
    {
        $result = ['generated' => 0, 'skipped' => 0, 'errors' => 0, 'total' => 0];

        try {
            $stmt = $this->db->query(
                "SELECT id, bank_account_number
                   FROM players
                  ORDER BY id ASC"
            );
            $rows = $stmt ? $stmt->fetchAll() : [];
            $result['total'] = count($rows);

            foreach ($rows as $row) {
                $existing = (string)($row['bank_account_number'] ?? '');
                if ($existing !== '') {
                    $result['skipped']++;
                    continue;
                }
                $assigned = $this->ensureAccount((int)$row['id']);
                if ($assigned !== null) {
                    $result['generated']++;
                } else {
                    $result['errors']++;
                }
            }

            if (class_exists('GameLog', false)) {
                GameLog::info('BankAccountService', 'migrateExisting', $result);
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('BankAccountService', 'migrateExisting FAILED', $e);
            }
        }

        return $result;
    }

    /**
     * Generuje kolejny numer rachunku dla biezacego roku.
     * Format: OC-{6-cyfrowa sekwencja}-{rok}. Sekwencja jest per-rok.
     *
     * Generates the next account number for the current year.
     * Format: OC-{6-digit sequence}-{year}. Sequence resets per year.
     *
     * Wykorzystuje MAX(bank_account_number) z LIKE — dla 6-cyfrowej, zero-padded
     * sekwencji porzadek leksykalny jest zgodny z numerycznym.
     * Uses MAX(bank_account_number) with LIKE — for a 6-digit zero-padded sequence,
     * lexicographic order matches numeric order.
     */
    private function generateNumber(): string
    {
        $year = date('Y');
        $like = sprintf('%s-%%-%s', self::ACCOUNT_PREFIX, $year);

        $next = 1;
        try {
            $stmt = $this->db->prepare(
                "SELECT MAX(bank_account_number) FROM players WHERE bank_account_number LIKE ?"
            );
            $stmt->execute([$like]);
            $max = $stmt->fetchColumn();
            if (is_string($max) && $max !== '') {
                // Wyciagniecie segmentu sekwencji: OC-000042-2026 -> 42
                // Extract the sequence segment: OC-000042-2026 -> 42
                $parts = explode('-', $max);
                if (count($parts) === 3) {
                    $next = max(1, (int)$parts[1] + 1);
                }
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('BankAccountService', 'generateNumber: MAX query FAILED', $e);
            }
            // Fallback: zaczynamy od 1 — UNIQUE constraint + retry zalatwia kolizje.
            // Fallback: start from 1 — UNIQUE constraint + retry handles collisions.
        }

        return sprintf('%s-%06d-%s', self::ACCOUNT_PREFIX, $next, $year);
    }
}
