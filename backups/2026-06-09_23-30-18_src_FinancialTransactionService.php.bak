<?php

declare(strict_types=1);

require_once __DIR__ . '/BankAccountService.php';

/**
 * FinancialTransactionService - centralne API ruchu srodkow (Etap 3).
 * FinancialTransactionService - central API for money movement (Stage 3).
 *
 * Brief, sekcja "NOWY FUNDAMENT - API FINANSOWE":
 * "Od tego momentu nie nalezy wykonywac money += / money -= bezposrednio
 *  w nowych modulach. Nalezy przygotowac wspolny serwis BankService lub
 *  FinancialTransactionService, ktory bedzie jedynym miejscem odpowiedzialnym
 *  za ruch srodkow."
 *
 * Brief, "NEW FOUNDATION - FINANCIAL API":
 * "From now on do not perform money +=/-= directly in new modules. Prepare
 *  a single service - BankService or FinancialTransactionService - as the
 *  only place responsible for money movement."
 *
 * Zakres tego pliku:
 *  - credit(): dodanie srodkow graczowi + log,
 *  - debit():  pobranie srodkow gracza (z walidacja salda) + log,
 *  - transfer(): atomowy przelew miedzy graczami + powiadomienia (etap 6),
 *  - logTransaction(): publiczna metoda dla istniejacych modulow (etap 8),
 *    pozwalajaca dopisac wpis bez zmiany salda (audit trail).
 *
 * This file scope:
 *  - credit(): add funds to a player + log,
 *  - debit():  withdraw funds (with balance validation) + log,
 *  - transfer(): atomic P2P transfer + notifications (stage 6),
 *  - logTransaction(): public method for existing modules (stage 8),
 *    lets legacy callers append an entry without touching the balance.
 *
 * Saldo zrodlem prawdy: players.cash (DECIMAL(20,2)) - bez drugiej tabeli kont.
 * Source-of-truth balance: players.cash (DECIMAL(20,2)) - no second accounts table.
 */
class FinancialTransactionService
{
    /**
     * Dozwolone typy operacji (brief, sekcja "Historia operacji").
     * Allowed transaction types (brief, "Transaction history").
     */
    public const TYPE_PLAYER_TRANSFER    = 'player_transfer';
    public const TYPE_LOAN               = 'loan';
    public const TYPE_LOAN_PAYMENT       = 'loan_payment';
    public const TYPE_MARKET_SALE        = 'market_sale';
    public const TYPE_TAX                = 'tax';
    public const TYPE_WELL_PURCHASE      = 'well_purchase';
    public const TYPE_WELL_UPGRADE       = 'well_upgrade';
    public const TYPE_WELL_MAINTENANCE   = 'well_maintenance';
    public const TYPE_HUB_PURCHASE       = 'hub_purchase';
    public const TYPE_PIPELINE_PURCHASE  = 'pipeline_purchase';
    public const TYPE_PIPELINE_REPAIR    = 'pipeline_repair';
    public const TYPE_PIPELINE_MAINTENANCE = 'pipeline_maintenance';
    public const TYPE_LEGAL_FEE          = 'legal_fee';
    public const TYPE_ADMIN_ADJUSTMENT   = 'admin_adjustment';
    public const TYPE_BAILIFF_SEIZURE    = 'bailiff_seizure';
    public const TYPE_HR_FEE             = 'hr_fee';
    public const TYPE_TTS_FEE            = 'tts_fee';
    public const TYPE_BANKRUPTCY_EVENT   = 'bankruptcy_event';
    public const TYPE_GEOLOGICAL_FEE     = 'geological_fee';
    public const TYPE_MAP_PURCHASE       = 'map_purchase';
    public const TYPE_STORAGE_UPGRADE    = 'storage_upgrade';

    /** Pelna lista dozwolonych typow / Full list of allowed types. */
    public const ALLOWED_TYPES = [
        self::TYPE_PLAYER_TRANSFER,
        self::TYPE_LOAN,
        self::TYPE_LOAN_PAYMENT,
        self::TYPE_MARKET_SALE,
        self::TYPE_TAX,
        self::TYPE_WELL_PURCHASE,
        self::TYPE_WELL_UPGRADE,
        self::TYPE_WELL_MAINTENANCE,
        self::TYPE_HUB_PURCHASE,
        self::TYPE_PIPELINE_PURCHASE,
        self::TYPE_PIPELINE_REPAIR,
        self::TYPE_PIPELINE_MAINTENANCE,
        self::TYPE_LEGAL_FEE,
        self::TYPE_ADMIN_ADJUSTMENT,
        self::TYPE_BAILIFF_SEIZURE,
        self::TYPE_HR_FEE,
        self::TYPE_TTS_FEE,
        self::TYPE_BANKRUPTCY_EVENT,
        self::TYPE_GEOLOGICAL_FEE,
        self::TYPE_MAP_PURCHASE,
        self::TYPE_STORAGE_UPGRADE,
    ];

    /**
     * Minimalna kwota - musi byc dodatnia, niezerowa.
     * Minimum amount - must be positive and non-zero.
     */
    public const MIN_AMOUNT = 0.01;

    private PDO $db;
    /** @var array<int,bool> Cache schematu per polaczenie / Schema cache per connection. */
    private static array $schemaReady = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->ensureTransactionSchema();
    }

    // ================================================================== Operacje
    // ================================================================== Operations

    /**
     * Dodaje srodki na konto gracza i loguje transakcje.
     * Adds funds to a player's account and logs the transaction.
     *
     * "From NULL" - wplyw z zewnatrz/systemu (np. wyplata kredytu, sprzedaz ropy
     * na rynku, podatek zwracany itp.).
     * "From NULL" - inflow from outside/system (loan disbursement, oil sale on
     * market, tax refund etc.).
     *
     * @return array{success:bool,transaction_id:?int,error:?string,amount:float}
     */
    public function credit(
        int $playerId,
        float $amount,
        string $type,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): array {
        return $this->moveFunds(null, $playerId, $amount, $type, $description, $referenceType, $referenceId);
    }

    /**
     * Pobiera srodki z konta gracza (walidacja salda) i loguje transakcje.
     * Withdraws funds from a player's account (validates balance) and logs.
     *
     * "To NULL" - wyplyw poza system (podatek, oplata prawna, koszt eksploatacji).
     * "To NULL" - outflow outside the system (tax, legal fee, operating cost).
     *
     * @return array{success:bool,transaction_id:?int,error:?string,amount:float}
     */
    public function debit(
        int $playerId,
        float $amount,
        string $type,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): array {
        return $this->moveFunds($playerId, null, $amount, $type, $description, $referenceType, $referenceId);
    }

    /**
     * Atomowy przelew miedzy dwoma graczami (brief, sekcja "Formularz przelewu").
     * Atomic P2P transfer (brief, "Transfer form").
     *
     * Walidacja:
     *  - kwota > 0,
     *  - nadawca != odbiorca,
     *  - obaj gracze istnieja,
     *  - nadawca ma wystarczajace srodki.
     *
     * Validation:
     *  - amount > 0,
     *  - sender != recipient,
     *  - both players exist,
     *  - sender has sufficient funds.
     *
     * @return array{success:bool,transaction_id:?int,error:?string,amount:float}
     */
    public function transfer(
        int $fromPlayerId,
        int $toPlayerId,
        float $amount,
        ?string $description = null
    ): array {
        if ($fromPlayerId === $toPlayerId) {
            return $this->fail('self_transfer', $amount);
        }
        return $this->moveFunds(
            $fromPlayerId,
            $toPlayerId,
            $amount,
            self::TYPE_PLAYER_TRANSFER,
            $description,
            null,
            null
        );
    }

    /**
     * Dopisuje wpis do bank_transactions BEZ ruszania salda. Dla legacy modulow
     * (etap 8) - tam gdzie pieniadze juz sie zmieniaja w istniejacym kodzie,
     * a my chcemy tylko dodac audit trail. Zwraca id wpisu lub NULL.
     *
     * Appends an entry to bank_transactions WITHOUT touching the balance. For
     * legacy modules (stage 8) - where money is already changed by existing code
     * and we only want to add an audit trail. Returns the entry id or NULL.
     */
    public function logTransaction(
        ?int $fromPlayerId,
        ?int $toPlayerId,
        float $amount,
        string $type,
        ?string $description = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): ?int {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            if (class_exists('GameLog', false)) {
                GameLog::warn('FinancialTransactionService', 'logTransaction: invalid type', ['type' => $type]);
            }
            return null;
        }
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO bank_transactions
                    (from_player_id, to_player_id, amount, transaction_type, description, reference_type, reference_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $fromPlayerId, $toPlayerId, $amount, $type, $description, $referenceType, $referenceId,
            ]);
            return (int)$this->db->lastInsertId();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('FinancialTransactionService', 'logTransaction FAILED', $e, [
                    'from' => $fromPlayerId, 'to' => $toPlayerId,
                    'amount' => $amount, 'type' => $type,
                ]);
            }
            return null;
        }
    }

    // ================================================================== Core
    // ================================================================== Core

    /**
     * Zapewnia schemat historii finansowej bez ryzyka niejawnego commita MySQL.
     * Ensures financial history schema without risking an implicit MySQL commit.
     */
    private function ensureTransactionSchema(): void
    {
        $connId = spl_object_id($this->db);
        if (isset(self::$schemaReady[$connId])) {
            return;
        }

        try {
            $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            $driver = 'mysql';
        }

        if ($driver === 'sqlite') {
            $this->ensureSqliteTransactionSchema();
            self::$schemaReady[$connId] = true;
            return;
        }

        try {
            if ($this->db->inTransaction()) {
                return;
            }
        } catch (Throwable) {
            // Kontynuuj poza transakcja / Continue outside an explicit transaction.
        }

        if (class_exists('BankAccountService')) {
            new BankAccountService($this->db);
        }
        self::$schemaReady[$connId] = true;
    }

    /**
     * Minimalny schemat SQLite dla testow integracyjnych.
     * Minimal SQLite schema for integration tests.
     */
    private function ensureSqliteTransactionSchema(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS bank_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                from_player_id INTEGER NULL,
                to_player_id INTEGER NULL,
                amount REAL NOT NULL,
                transaction_type TEXT NOT NULL,
                description TEXT NULL,
                reference_type TEXT NULL,
                reference_id INTEGER NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_bank_tx_from_created ON bank_transactions (from_player_id, created_at)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_bank_tx_to_created ON bank_transactions (to_player_id, created_at)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_bank_tx_type_created ON bank_transactions (transaction_type, created_at)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_bank_tx_ref ON bank_transactions (reference_type, reference_id)");
    }

    /**
     * Wspolny rdzen credit/debit/transfer. NULL po stronie from/to oznacza
     * operacje systemowa (wplyw spoza gry / wyplyw na zewnatrz).
     *
     * Common core for credit/debit/transfer. NULL on the from/to side means
     * a system operation (inflow from outside / outflow outside).
     *
     * @return array{success:bool,transaction_id:?int,error:?string,amount:float}
     */
    private function moveFunds(
        ?int $fromPlayerId,
        ?int $toPlayerId,
        float $amount,
        string $type,
        ?string $description,
        ?string $referenceType,
        ?int $referenceId
    ): array {
        $amount = round($amount, 2);

        // 1) Walidacja parametrow / Parameter validation.
        if ($amount < self::MIN_AMOUNT) {
            return $this->fail('invalid_amount', $amount);
        }
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return $this->fail('invalid_type', $amount);
        }
        if ($fromPlayerId === null && $toPlayerId === null) {
            return $this->fail('no_endpoint', $amount);
        }

        // 2) Decyzja o transakcji: jezeli wywolujacy juz jest w transakcji,
        //    dolaczamy do niej i NIE robimy wlasnego BEGIN/COMMIT (nested guard).
        // 2) Transaction decision: if the caller is already in a transaction,
        //    join it and do NOT issue our own BEGIN/COMMIT (nested guard).
        $ownTransaction = false;
        try {
            $ownTransaction = !$this->db->inTransaction();
        } catch (Throwable) {
            $ownTransaction = true;
        }

        try {
            if ($ownTransaction) {
                $this->db->beginTransaction();
            }

            // 3) Debit (jezeli source jest graczem): zablokuj wiersz, sprawdz saldo, odejmij.
            // 3) Debit (if source is a player): lock row, check balance, subtract.
            if ($fromPlayerId !== null) {
                $balance = $this->lockAndReadBalance($fromPlayerId);
                if ($balance === null) {
                    if ($ownTransaction) { $this->db->rollBack(); }
                    return $this->fail('sender_not_found', $amount);
                }
                if ($balance + 1e-9 < $amount) {
                    if ($ownTransaction) { $this->db->rollBack(); }
                    return $this->fail('insufficient_funds', $amount);
                }
                $this->db->prepare(
                    "UPDATE players SET cash = cash - :a WHERE id = :id"
                )->execute([':a' => $amount, ':id' => $fromPlayerId]);
            }

            // 4) Credit (jezeli destination jest graczem): doloz srodki.
            // 4) Credit (if destination is a player): add funds.
            if ($toPlayerId !== null) {
                // Sprawdz ze gracz istnieje (uniknij silent UPDATE 0 row).
                // Verify recipient exists (avoid silent UPDATE 0 row).
                if (!$this->playerExists($toPlayerId)) {
                    if ($ownTransaction) { $this->db->rollBack(); }
                    return $this->fail('recipient_not_found', $amount);
                }
                $this->db->prepare(
                    "UPDATE players SET cash = cash + :a WHERE id = :id"
                )->execute([':a' => $amount, ':id' => $toPlayerId]);
            }

            // 5) Log transakcji.
            // 5) Log the transaction.
            $stmt = $this->db->prepare(
                "INSERT INTO bank_transactions
                    (from_player_id, to_player_id, amount, transaction_type, description, reference_type, reference_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $fromPlayerId, $toPlayerId, $amount, $type, $description, $referenceType, $referenceId,
            ]);
            $transactionId = (int)$this->db->lastInsertId();

            if ($ownTransaction) {
                $this->db->commit();
            }

            if (class_exists('GameLog', false)) {
                GameLog::info('FinancialTransactionService', 'moveFunds OK', [
                    'tx_id' => $transactionId,
                    'from'  => $fromPlayerId,
                    'to'    => $toPlayerId,
                    'amount'=> $amount,
                    'type'  => $type,
                ]);
            }

            return [
                'success'        => true,
                'transaction_id' => $transactionId,
                'error'          => null,
                'amount'         => $amount,
            ];
        } catch (Throwable $e) {
            try {
                if ($ownTransaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (Throwable) {
                // ignore rollback failure
            }
            if (class_exists('GameLog', false)) {
                GameLog::error('FinancialTransactionService', 'moveFunds FAILED', $e, [
                    'from' => $fromPlayerId, 'to' => $toPlayerId,
                    'amount' => $amount, 'type' => $type,
                ]);
            }
            return $this->fail('db_error', $amount);
        }
    }

    /**
     * Czyta saldo z blokada wiersza (MySQL: FOR UPDATE; SQLite: zwykly SELECT,
     * bo SQLite serializuje zapisy na poziomie pliku).
     *
     * Reads balance with row lock (MySQL: FOR UPDATE; SQLite: plain SELECT,
     * because SQLite serializes writes at the file level).
     */
    private function lockAndReadBalance(int $playerId): ?float
    {
        $driver = 'mysql';
        try {
            $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            // pozostawiamy mysql / keep mysql
        }
        $forUpdate = ($driver === 'mysql') ? ' FOR UPDATE' : '';

        $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?{$forUpdate}");
        $stmt->execute([$playerId]);
        $val = $stmt->fetchColumn();
        if ($val === false || $val === null) {
            return null;
        }
        return (float)$val;
    }

    private function playerExists(int $playerId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM players WHERE id = ? LIMIT 1");
        $stmt->execute([$playerId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * @return array{success:bool,transaction_id:?int,error:?string,amount:float}
     */
    private function fail(string $errorKey, float $amount): array
    {
        return [
            'success'        => false,
            'transaction_id' => null,
            'error'          => $errorKey,
            'amount'         => round($amount, 2),
        ];
    }
}
