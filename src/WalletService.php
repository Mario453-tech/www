<?php

declare(strict_types=1);

/**
 * WalletService - surowe operacje DB na pulach gotowki gracza + migracja schematu.
 * WalletService - raw DB operations on player cash pools + schema migration.
 *
 * Metody publiczne / Public methods:
 *  - getBalances(playerId)                                 -> array{cash,bank_balance}|null
 *  - transferBetweenPools(id, fromPool, toPool, amount)    -> bool
 *  - initNewPlayer(id, startingCash)                       -> void
 *
 * Uwaga: ta klasa NIE loguje transakcji do bank_transactions.
 *        Logowanie jest zadaniem CashTransferService lub FTS.
 * Note: this class does NOT log to bank_transactions.
 *       Logging is the responsibility of CashTransferService or FTS.
 */
class WalletService
{
    private PDO $db;
    private static bool $schemaReady = false;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->ensureSchema();
    }

    // ============================================================ public API

    /**
     * Zwraca obie pule gracza: cash i bank_balance.
     * Returns both player pools: cash and bank_balance.
     *
     * @return array{cash:float,bank_balance:float}|null  null jesli gracz nie znaleziony
     */
    public function getBalances(int $playerId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT cash, bank_balance FROM players WHERE id = ? LIMIT 1"
            );
            $stmt->execute([$playerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            return [
                WalletConfig::POOL_CASH => (float)$row['cash'],
                WalletConfig::POOL_BANK => (float)($row['bank_balance'] ?? 0.0),
            ];
        } catch (Throwable $e) {
            GameLog::error('WalletService', 'getBalances FAILED', $e, ['player' => $playerId]);
            return null;
        }
    }

    /**
     * Atomowy transfer miedzy pulami gracza (bez prowizji - prowizja to zadanie CashTransferService).
     * Atomic transfer between player pools (no fee - fee is CashTransferService's job).
     *
     * Blokuje wiersz FOR UPDATE jesli nie jestesmy juz w transakcji.
     * Locks the row FOR UPDATE unless we are already inside a transaction.
     *
     * @return bool  false gdy niewystarczajace saldo lub blad DB
     */
    public function transferBetweenPools(
        int $playerId,
        string $fromPool,
        string $toPool,
        float $amount
    ): bool {
        $fromCol = $this->safeColumn($fromPool);
        $toCol   = $this->safeColumn($toPool);
        if ($fromCol === null || $toCol === null || $fromCol === $toCol) {
            GameLog::warn('WalletService', 'transferBetweenPools: invalid pool', [
                'from' => $fromPool, 'to' => $toPool,
            ]);
            return false;
        }
        $amount = round($amount, 2);
        if ($amount < 0.01) {
            return false;
        }

        $ownTx = false;
        try {
            $ownTx = !$this->db->inTransaction();
        } catch (Throwable) {
            $ownTx = true;
        }

        try {
            if ($ownTx) {
                $this->db->beginTransaction();
            }

            // Zablokuj wiersz i sprawdz saldo zrodlowe.
            // Lock the row and check the source balance.
            $stmt = $this->db->prepare(
                "SELECT {$fromCol} FROM players WHERE id = ? FOR UPDATE"
            );
            $stmt->execute([$playerId]);
            $srcBalance = $stmt->fetchColumn();
            if ($srcBalance === false || $srcBalance === null) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return false;
            }
            if ((float)$srcBalance + 1e-9 < $amount) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return false;
            }

            // Jeden UPDATE: odejmij ze zrodla, dodaj do celu.
            // Single UPDATE: subtract from source, add to target.
            $this->db->prepare(
                "UPDATE players
                    SET {$fromCol} = {$fromCol} - :amt,
                        {$toCol}   = {$toCol}   + :amt
                  WHERE id = :id"
            )->execute([':amt' => $amount, ':id' => $playerId]);

            if ($ownTx) {
                $this->db->commit();
            }
            return true;
        } catch (Throwable $e) {
            try {
                if ($ownTx && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (Throwable) {}
            GameLog::error('WalletService', 'transferBetweenPools FAILED', $e, [
                'player' => $playerId,
                'from'   => $fromPool,
                'to'     => $toPool,
                'amount' => $amount,
            ]);
            return false;
        }
    }

    /**
     * Inicjalizuje portfel nowego gracza - podział 50/50 zgodnie z WalletConfig.
     * Initialises a new player's wallet - 50/50 split per WalletConfig.
     */
    public function initNewPlayer(int $playerId, float $startingCash): void
    {
        $bankShare = round($startingCash * (1.0 - WalletConfig::NEW_PLAYER_CASH_RATIO), 2);
        $cashShare = round($startingCash - $bankShare, 2);
        try {
            $this->db->prepare(
                "UPDATE players
                    SET cash              = :cash,
                        bank_balance      = :bank,
                        wallet_initialized = 1
                  WHERE id = :id"
            )->execute([':cash' => $cashShare, ':bank' => $bankShare, ':id' => $playerId]);
            GameLog::info('WalletService', 'initNewPlayer OK', [
                'player'    => $playerId,
                'cash'      => $cashShare,
                'bank'      => $bankShare,
            ]);
        } catch (Throwable $e) {
            GameLog::error('WalletService', 'initNewPlayer FAILED', $e, ['player' => $playerId]);
        }
    }

    // ============================================================ private

    /**
     * Zapewnia kolumny bank_balance i wallet_initialized w tabeli players,
     * oraz jednorazowo migruje istniejacych graczy (podzial 50/50).
     *
     * Ensures bank_balance and wallet_initialized columns in the players table,
     * and one-time migrates existing players (50/50 split).
     *
     * Statyczna flaga gwarantuje uruchomienie raz per PHP-worker.
     * Static flag ensures one run per PHP worker process.
     */
    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        try {
            $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            $driver = 'mysql';
        }

        if ($driver === 'sqlite') {
            $this->ensureSchemaSqlite();
            self::$schemaReady = true;
            return;
        }

        // Dodaj kolumny jesli nie istnieja. Owijamy w try - jesli rzuci wyjatkiem,
        // NIE ustawiamy schemaReady, zeby kolejne zadanie moglo sprobowac ponownie.
        // Add columns if missing. Wrap in try - if it throws, do NOT set schemaReady
        // so the next request can retry.
        try {
            Database::addColumnIfMissing(
                'players',
                'bank_balance',
                'DECIMAL(20,2) NOT NULL DEFAULT 0.00 AFTER cash'
            );
            Database::addColumnIfMissing(
                'players',
                'wallet_initialized',
                'TINYINT(1) NOT NULL DEFAULT 0 AFTER bank_balance'
            );

            // Jednorazowa migracja: podziel cash 50/50 dla nieprzemigrowanych graczy.
            // One-time migration: split cash 50/50 for non-migrated players.
            try {
                $this->db->exec(
                    "UPDATE players
                        SET bank_balance      = ROUND(cash / 2, 2),
                            cash              = cash - ROUND(cash / 2, 2),
                            wallet_initialized = 1
                      WHERE wallet_initialized = 0"
                );
            } catch (Throwable $e) {
                GameLog::error('WalletService', 'schema migration FAILED', $e);
            }

            // Ustaw flage dopiero po sukcesie addColumnIfMissing (nie przed).
            // Set flag only after addColumnIfMissing succeeded (not before).
            self::$schemaReady = true;
        } catch (Throwable $e) {
            GameLog::error('WalletService', 'ensureSchema column add FAILED', $e);
            // Nie ustawiaj schemaReady - nastepne zadanie ponowi probe.
            // Do not set schemaReady - next request will retry.
        }
    }

    /**
     * Schemat SQLite dla testow integracyjnych.
     * SQLite schema for integration tests.
     */
    private function ensureSchemaSqlite(): void
    {
        try {
            $this->db->exec("ALTER TABLE players ADD COLUMN bank_balance REAL NOT NULL DEFAULT 0");
        } catch (Throwable) {}
        try {
            $this->db->exec("ALTER TABLE players ADD COLUMN wallet_initialized INTEGER NOT NULL DEFAULT 0");
        } catch (Throwable) {}
    }

    /**
     * Zwraca bezpieczna nazwe kolumny lub null (biala lista).
     * Returns a safe column name or null (whitelist).
     */
    private function safeColumn(string $pool): ?string
    {
        return match ($pool) {
            WalletConfig::POOL_CASH => 'cash',
            WalletConfig::POOL_BANK => 'bank_balance',
            default                 => null,
        };
    }
}
