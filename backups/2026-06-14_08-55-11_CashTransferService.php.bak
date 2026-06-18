<?php

declare(strict_types=1);

/**
 * CashTransferService - logika biznesowa transferu gracza miedzy pulami portfela.
 * CashTransferService - business logic for player-initiated wallet pool transfers.
 *
 * Odpowiedzialnosci / Responsibilities:
 *  - walidacja kwoty (min/max z WalletConfig),
 *  - obliczenie prowizji,
 *  - atomowe pobranie (kwota + prowizja) z puli zrodlowej,
 *  - zasilenie puli docelowej kwota (prowizja idzie do systemu),
 *  - zapis w bank_transactions (audit trail).
 *
 * Limity i stawki: WalletConfig::TRANSFER_*.
 * Limits and rates: WalletConfig::TRANSFER_*.
 *
 * Publiczne metody / Public methods:
 *  - cashToBank(amount): array  - gotowka -> konto, z prowizja
 *  - bankToCash(amount): array  - konto -> gotowka, z prowizja
 *  - calcFee(amount): float     - podgladanie prowizji bez wykonywania transferu
 *
 * Zwracany array / Return array:
 *  {success: bool, message: string, new_cash: ?float, new_bank: ?float, fee: ?float}
 */
class CashTransferService
{
    public const TYPE_POOL_TRANSFER = 'pool_transfer';

    private int $playerId;
    private PDO $db;
    private WalletService $wallet;
    private FinancialTransactionService $fts;

    public function __construct(int $playerId, ?PDO $db = null)
    {
        $this->playerId = $playerId;
        $this->db       = $db ?? Database::getInstance()->getConnection();
        $this->wallet   = new WalletService($this->db);
        $this->fts      = new FinancialTransactionService($this->db);
    }

    // ============================================================ public

    /**
     * Transfer gotowki na konto bankowe.
     * Transfer cash to bank account.
     *
     * @return array{success:bool,message:string,new_cash:?float,new_bank:?float,fee:?float}
     */
    public function cashToBank(float $amount): array
    {
        return $this->doTransfer($amount, WalletConfig::POOL_CASH, WalletConfig::POOL_BANK);
    }

    /**
     * Wyplata z konta bankowego na gotowke.
     * Withdrawal from bank account to cash.
     *
     * @return array{success:bool,message:string,new_cash:?float,new_bank:?float,fee:?float}
     */
    public function bankToCash(float $amount): array
    {
        return $this->doTransfer($amount, WalletConfig::POOL_BANK, WalletConfig::POOL_CASH);
    }

    /**
     * Oblicza prowizje (bez wykonywania transferu) - uzywane przez UI do podgladu.
     * Computes fee (without doing the transfer) - used by UI for preview.
     */
    public function calcFee(float $amount): float
    {
        return WalletConfig::calcFee($amount);
    }

    // ============================================================ private

    /**
     * Glowna logika transferu: walidacja -> atomowy DB -> log.
     * Core transfer logic: validation -> atomic DB -> log.
     *
     * @return array{success:bool,message:string,new_cash:?float,new_bank:?float,fee:?float}
     */
    private function doTransfer(float $amount, string $fromPool, string $toPool): array
    {
        $amount = round($amount, 2);

        // 1) Walidacja kwoty / Amount validation.
        $err = $this->validateAmount($amount);
        if ($err !== null) {
            return $this->fail($err);
        }

        $fee   = $this->calcFee($amount);
        $total = round($amount + $fee, 2);  // lacznie do odjecia z puli zrodlowej / total to subtract from source pool

        // 2) Sprawdz saldo / Check balance.
        $balances = $this->wallet->getBalances($this->playerId);
        if ($balances === null) {
            return $this->fail(tPlain('wallet.err_player_not_found'));
        }
        $srcBalance = $balances[$fromPool] ?? 0.0;
        if ($srcBalance + 1e-9 < $total) {
            return $this->fail(tPlain('wallet.err_insufficient', [
                'need' => number_format($total, 2, ',', ' '),
                'have' => number_format($srcBalance, 2, ',', ' '),
            ]));
        }

        // 3) Atomowy DB: odejmij (kwota+prowizja) ze zrodla, dodaj kwote do celu.
        //    Prowizja = roznica miedzy total a amount = idzie do systemu.
        // 3) Atomic DB: subtract (amount+fee) from source, add amount to target.
        //    Fee = difference between total and amount = goes to system.
        $srcCol = ($fromPool === WalletConfig::POOL_CASH) ? 'cash' : 'bank_balance';
        $dstCol = ($toPool   === WalletConfig::POOL_CASH) ? 'cash' : 'bank_balance';

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

            // Blokuj wiersz, sprawdz saldo raz jeszcze (FOR UPDATE).
            // Lock row, check balance one more time (FOR UPDATE).
            $lockStmt = $this->db->prepare(
                "SELECT {$srcCol} FROM players WHERE id = ? FOR UPDATE"
            );
            $lockStmt->execute([$this->playerId]);
            $rawLocked = $lockStmt->fetchColumn();
            if ($rawLocked === false) {
                // Gracz zostal usuniety miedzy wczesnym sprawdzeniem a blokada.
                // Player was deleted between early check and lock.
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->fail(tPlain('wallet.err_player_not_found'));
            }
            $lockedBalance = (float)$rawLocked;
            if ($lockedBalance + 1e-9 < $total) {
                if ($ownTx) {
                    $this->db->rollBack();
                }
                return $this->fail(tPlain('wallet.err_insufficient', [
                    'need' => number_format($total, 2, ',', ' '),
                    'have' => number_format($lockedBalance, 2, ',', ' '),
                ]));
            }

            // Jeden UPDATE: -total ze zrodla, +amount do celu.
            // Single UPDATE: -total from source, +amount to target.
            $this->db->prepare(
                "UPDATE players
                    SET {$srcCol} = {$srcCol} - :total,
                        {$dstCol} = {$dstCol} + :amount
                  WHERE id = :id"
            )->execute([
                ':total'  => $total,
                ':amount' => $amount,
                ':id'     => $this->playerId,
            ]);

            // 4) Audit trail WEWNATRZ transakcji - cofnie przesuniecie salda gdy zapis nie powiedzie sie.
            //    logTransaction() polyka wlasny wyjatek i zwraca null przy bledzie, wiec
            //    sprawdzamy wynik i sami rzucamy wyjatek - inaczej commit ponizej przeszedlby
            //    mimo braku wpisu audytu (pieniadze ruszone, brak sladu).
            // 4) Audit trail INSIDE transaction - rolls back balance move if log write fails.
            //    logTransaction() swallows its own exception and returns null on failure, so we
            //    check the result and throw ourselves - otherwise commit below would proceed
            //    despite a missing audit row (money moved, no trace).
            $dirLabel = ($fromPool === WalletConfig::POOL_CASH)
                ? tPlain('wallet.tx_cash_to_bank', ['amount' => number_format($amount, 2, ',', ' ')])
                : tPlain('wallet.tx_bank_to_cash', ['amount' => number_format($amount, 2, ',', ' ')]);
            $logId = $this->fts->logTransaction(
                $this->playerId,
                null,
                $amount,
                self::TYPE_POOL_TRANSFER,
                $dirLabel,
                'wallet_transfer',
                null
            );
            if ($logId === null) {
                // Zapis audytu nie powiodl sie - wymus rollback przesuniecia salda.
                // Audit write failed - force rollback of the balance move.
                throw new RuntimeException('wallet pool transfer: audit log write failed');
            }

            if ($ownTx) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            try {
                if ($ownTx && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            } catch (Throwable) {}
            GameLog::error('CashTransferService', 'doTransfer FAILED', $e, [
                'player' => $this->playerId,
                'from'   => $fromPool,
                'to'     => $toPool,
                'amount' => $amount,
                'fee'    => $fee,
            ]);
            return $this->fail(tPlain('wallet.err_db'));
        }

        $newBalances = $this->wallet->getBalances($this->playerId);
        GameLog::info('CashTransferService', 'transfer OK', [
            'player' => $this->playerId,
            'from'   => $fromPool,
            'to'     => $toPool,
            'amount' => $amount,
            'fee'    => $fee,
        ]);

        return [
            'success'  => true,
            'message'  => tPlain('wallet.ok_transfer', [
                'amount' => number_format($amount, 2, ',', ' '),
                'fee'    => number_format($fee, 2, ',', ' '),
            ]),
            'new_cash' => $newBalances[WalletConfig::POOL_CASH] ?? null,
            'new_bank' => $newBalances[WalletConfig::POOL_BANK] ?? null,
            'fee'      => $fee,
        ];
    }

    /**
     * Waliduje kwote transferu. Zwraca komunikat bledu lub null jesli OK.
     * Validates transfer amount. Returns error message or null if OK.
     */
    private function validateAmount(float $amount): ?string
    {
        if ($amount < WalletConfig::TRANSFER_MIN_AMOUNT) {
            return tPlain('wallet.err_min', [
                'min' => number_format(WalletConfig::TRANSFER_MIN_AMOUNT, 0, ',', ' '),
            ]);
        }
        if ($amount > WalletConfig::TRANSFER_MAX_AMOUNT) {
            return tPlain('wallet.err_max', [
                'max' => number_format(WalletConfig::TRANSFER_MAX_AMOUNT, 0, ',', ' '),
            ]);
        }
        return null;
    }

    /** @return array{success:bool,message:string,new_cash:null,new_bank:null,fee:null} */
    private function fail(string $message): array
    {
        return [
            'success'  => false,
            'message'  => $message,
            'new_cash' => null,
            'new_bank' => null,
            'fee'      => null,
        ];
    }
}
