<?php

/**
 * Loads all bank view data for one player.
 * Laduje komplet danych widoku banku dla gracza.
 */
class BankDataLoader
{
    private int $playerId;
    private ?BankService $bankService;
    private ?BankNegotiationService $bankNeg;
    private mixed $db = null;
    private int $txPage;

    // Liczba wierszy historii na strone / Rows per page in transaction history.
    public const HISTORY_PER_PAGE = 25;

    public function __construct(int $playerId, ?BankService $bankService, ?BankNegotiationService $bankNeg, int $txPage = 1)
    {
        $this->playerId = $playerId;
        $this->bankService = $bankService;
        $this->bankNeg = $bankNeg;
        $this->txPage = max(1, $txPage);

        try {
            $this->db = Database::getInstance()->getConnection();
        } catch (Throwable $e) {
            $this->db = null;
        }
    }

 /**
 * Returns all data required by the bank page.
 * Zwraca wszystkie dane wymagane przez strone banku.
 */
    public function load(): array
    {
        $applicationStatus = $this->loadApplicationStatus();
        $activeLoans = $this->loadActiveLoans();
        $negData = [];
        $deferralOpts = [];

        if ($this->bankNeg && !empty($activeLoans)) {
            [$negData, $deferralOpts, $activeLoans] = $this->loadNegData($activeLoans);
        }

        GameLog::info('bank', 'render start', [
            'player' => $this->playerId,
            'loans' => count($activeLoans),
            'app_status' => $applicationStatus['status'] ?? 'unknown',
        ]);

        $appSt = $applicationStatus['status'] ?? 'none';
        $offer = $applicationStatus['offer'] ?? [];
        $offerReduced = !empty($offer)
            && (float)($offer['amount'] ?? 0) < (float)($offer['requested_amount'] ?? 0);

        [$canApply, $blockReasons, $blockHasActiveLoan, $isCrisis] = $this->resolveCanApply($appSt, $applicationStatus, $activeLoans);
        [$creditLimit] = $this->loadCreditLimit($canApply, $blockReasons);
        $hasEverHadLoan = $this->checkHasEverHadLoan();

        // Etap 4: dane konta bankowego (numer, saldo, historia operacji).
        // Stage 4: bank account data (number, balance, operation history).
        $accountData = $this->loadAccountData();

        return [
            'applicationStatus' => $applicationStatus,
            'activeLoans' => $activeLoans,
            'negData' => $negData,
            'deferralOpts' => $deferralOpts,
            'appSt' => $appSt,
            'offer' => $offer,
            'offerReduced' => $offerReduced,
            'canApply' => $canApply,
            'blockReasons' => $blockReasons,
            'blockHasActiveLoan' => $blockHasActiveLoan,
            'isCrisis' => $isCrisis,
            'creditLimit' => $creditLimit,
            'hasEverHadLoan' => $hasEverHadLoan,
            'accountNumber'       => $accountData['number'],
            'accountBalance'      => $accountData['balance'],       // bank_balance (saldo konta)
            'cashBalance'         => $accountData['cashBalance'],   // cash (gotowka)
            'accountHistory'      => $accountData['history'],
            'accountHistoryTotal' => $accountData['historyTotal'],
            'accountHistoryPage'  => $accountData['historyPage'],
        ];
    }

 /**
 * Etap 4: laduje numer rachunku, saldo i historie operacji ze stronicowaniem.
 * Stage 4: loads account number, balance and paginated transaction history.
 *
 * @return array{number:string,balance:float,cashBalance:float,history:array<int,array<string,mixed>>,historyTotal:int,historyPage:int}
 */
    private function loadAccountData(): array
    {
        $result = ['number' => '', 'balance' => 0.0, 'cashBalance' => 0.0, 'history' => [], 'historyTotal' => 0, 'historyPage' => 1];

        if (!$this->db) {
            return $result;
        }

        try {
            // ensureAccount: generuje numer jesli gracz go nie ma (lazy backfill).
            // ensureAccount: generates a number if the player has none (lazy backfill).
            if (class_exists('BankAccountService', false) || @class_exists('BankAccountService')) {
                $accSvc = new BankAccountService($this->db);
                $num = $accSvc->ensureAccount($this->playerId);
                $result['number'] = (string)($num ?? '');
            }

            // Uzyj WalletService dla obu pul: bank_balance = "saldo konta", cash = "gotowka".
            // Use WalletService for both pools: bank_balance = "account balance", cash = "cash".
            $walletSvc             = new WalletService($this->db);
            $pools                 = $walletSvc->getBalances($this->playerId);
            $result['balance']     = (float)($pools[WalletConfig::POOL_BANK] ?? 0.0);
            $result['cashBalance'] = (float)($pools[WalletConfig::POOL_CASH] ?? 0.0);

            // Laczna liczba rekordow dla stronicowania / Total row count for pagination.
            $cStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM bank_transactions
                  WHERE from_player_id = :pid OR to_player_id = :pid"
            );
            $cStmt->execute([':pid' => $this->playerId]);
            $total = (int)$cStmt->fetchColumn();
            $result['historyTotal'] = $total;

            // Oblicz aktualna strone i offset / Calculate current page and offset.
            $perPage = self::HISTORY_PER_PAGE;
            $maxPage = max(1, (int)ceil($total / $perPage));
            $page    = min($this->txPage, $maxPage);
            $result['historyPage'] = $page;
            $offset  = ($page - 1) * $perPage;

            // Historia z offsetem / History with offset.
            $hStmt = $this->db->prepare(
                "SELECT bt.*,
                        pf.bank_account_number AS from_account,
                        pf.username           AS from_username,
                        pt.bank_account_number AS to_account,
                        pt.username           AS to_username
                   FROM bank_transactions bt
                   LEFT JOIN players pf ON pf.id = bt.from_player_id
                   LEFT JOIN players pt ON pt.id = bt.to_player_id
                  WHERE bt.from_player_id = :pid OR bt.to_player_id = :pid
                  ORDER BY bt.id DESC
                  LIMIT :lim OFFSET :off"
            );
            $hStmt->bindValue(':pid', $this->playerId, \PDO::PARAM_INT);
            $hStmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
            $hStmt->bindValue(':off', $offset, \PDO::PARAM_INT);
            $hStmt->execute();
            $rows = $hStmt->fetchAll();

            foreach ($rows as &$row) {
                $row['is_inflow'] = ((int)($row['to_player_id'] ?? 0) === $this->playerId);
                $row['signed_amount'] = $row['is_inflow']
                    ? (float)$row['amount']
                    : -1 * (float)$row['amount'];
                $row['counterparty_label'] = $this->formatCounterparty($row);
                $row['created_at_fmt'] = !empty($row['created_at'])
                    ? date('d.m.Y H:i', strtotime((string)$row['created_at']))
                    : '';
            }
            unset($row);
            $result['history'] = $rows;
        } catch (Throwable $e) {
            GameLog::error('bank', 'loadAccountData FAILED', $e, ['player' => $this->playerId]);
        }

        return $result;
    }

 /**
 * Buduje etykiete drugiej strony transakcji: numer konta + nazwa firmy gracza,
 * albo "Bank / System" gdy from/to == NULL (np. wyplata kredytu, podatek).
 * Builds the counterparty label: account number + player name, or "Bank / System"
 * when from/to == NULL (e.g. loan disbursement, tax).
 *
 * @param array<string,mixed> $row
 */
    private function formatCounterparty(array $row): string
    {
        $isInflow = !empty($row['is_inflow']);
        if ($isInflow) {
            $acc = (string)($row['from_account'] ?? '');
            $name = (string)($row['from_username'] ?? '');
        } else {
            $acc = (string)($row['to_account'] ?? '');
            $name = (string)($row['to_username'] ?? '');
        }

        if ($acc === '' && $name === '') {
            return t('bank.account.counterparty_system');
        }

        $parts = [];
        if ($acc !== '')  { $parts[] = $acc; }
        if ($name !== '') { $parts[] = $name; }
        return implode(' - ', $parts);
    }

 /**
 * Checks whether the player had any historical loan/application outcome.
 * Sprawdza, czy gracz mial juz historyczny wynik kredytu lub wniosku.
 */
    private function checkHasEverHadLoan(): bool
    {
        if (!$this->db) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM loan_applications
                WHERE player_id = :pid AND status IN ('accepted','rejected','expired')
                LIMIT 1
            ");
            $stmt->execute([':pid' => $this->playerId]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

 /**
 * Loads application status if service is available.
 * Laduje status wniosku, jesli serwis jest dostepny.
 */
    private function loadApplicationStatus(): array
    {
        if (!$this->bankService) {
            return ['status' => 'none'];
        }

        try {
            $status = $this->bankService->getLoanApplicationStatus($this->playerId);
            GameLog::dbResult('bank', 'getLoanApplicationStatus', 1);
            return $status;
        } catch (Throwable $e) {
            GameLog::error('bank', 'getLoanApplicationStatus FAILED', $e, ['player' => $this->playerId]);
            return ['status' => 'none'];
        }
    }

 /**
 * Loads active loans if service is available.
 * Laduje aktywne kredyty, jesli serwis jest dostepny.
 */
    private function loadActiveLoans(): array
    {
        if (!$this->bankService) {
            return [];
        }

        try {
            $loans = $this->bankService->getActiveLoans($this->playerId);
            GameLog::dbResult('bank', 'getActiveLoans', count($loans));
            return $loans;
        } catch (Throwable $e) {
            GameLog::error('bank', 'getActiveLoans FAILED', $e, ['player' => $this->playerId]);
            return [];
        }
    }

 /**
 * Loads negotiation-related view data for all active loans.
 * Laduje dane negocjacyjne do widoku dla aktywnych kredytow.
 */
    private function loadNegData(array $activeLoans): array
    {
        $negData = [];
        $deferralOpts = [];

        foreach ($activeLoans as &$loan) {
            $loanId = (int)$loan['id'];
            $remaining = (float)($loan['remaining_amount'] ?? 0);

            try {
                $can = $this->bankNeg->canNegotiate($this->playerId, $loanId);
                $active = $this->bankNeg->getActivePendingOrApproved($loanId);
                $events = $this->loadNegEvents($active);

                if ($active) {
                    $active['decision_due_at_fmt'] = !empty($active['decision_due_at'])
                        ? date('d.m.Y H:i', strtotime($active['decision_due_at']))
                        : t('common.dash');
                    $active['expires_at_fmt'] = !empty($active['expires_at'])
                        ? date('d.m.Y H:i', strtotime($active['expires_at']))
                        : t('common.dash');
                }

                $hasBailiff = $this->checkBailiff($loanId);
                $restructDisplay = $this->loadRestructDisplay($loanId);

                $principal = (float)($loan['principal_amount'] ?? 0);
                $pct = 0;
                if ($principal > 0) {
                    $pct = max(0, min(100, (int)round(($principal - $remaining) / $principal * 100)));
                }

                $loan['next_installment_fmt'] = !empty($loan['next_installment_at'])
                    ? date('d.m.Y H:i', strtotime($loan['next_installment_at']))
                    : t('common.dash');

                $deferralOpts[$loanId] = [
                    30 => ['apr' => '+5% APR', 'fee' => round($remaining * 0.020)],
                    90 => ['apr' => '+10% APR', 'fee' => round($remaining * 0.040)],
                    180 => ['apr' => '+15% APR', 'fee' => round($remaining * 0.070)],
                ];

                $negData[$loanId] = [
                    'can' => $can,
                    'active' => $active,
                    'events' => $events,
                    'hasBailiff' => $hasBailiff,
                    'restructDisplay' => $restructDisplay,
                    'pct' => $pct,
                ];

                GameLog::step('bank', 'negData', $loanId, 'OK', [
                    'can_neg' => $can['can_negotiate'],
                    'active_neg' => $active ? $active['status'] : 'none',
                    'events' => count($events),
                ]);
            } catch (Throwable $e) {
                GameLog::error('bank', 'negData load FAILED', $e, ['loan_id' => $loanId]);
                $negData[$loanId] = [
                    'can' => ['can_negotiate' => false, 'reason' => ''],
                    'active' => null,
                    'events' => [],
                    'hasBailiff' => false,
                    'restructDisplay' => null,
                    'pct' => 0,
                ];
            }
        }
        unset($loan);

        return [$negData, $deferralOpts, $activeLoans];
    }

 /**
 * Loads recent negotiation events for one active negotiation.
 * Laduje ostatnie zdarzenia negocjacyjne dla aktywnej negocjacji.
 */
    private function loadNegEvents(?array $active): array
    {
        if (!$active || !$this->db) {
            return [];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT * FROM bank_negotiation_events
                WHERE negotiation_id = :nid
                ORDER BY created_at DESC LIMIT 10
            ");
            $stmt->execute([':nid' => $active['id']]);
            $events = $stmt->fetchAll();
            foreach ($events as &$ev) {
                $ev['created_at_fmt'] = !empty($ev['created_at'])
                    ? date('d.m H:i', strtotime($ev['created_at']))
                    : '';
            }
            unset($ev);
            GameLog::dbResult('bank', 'bank_negotiation_events', count($events));
            return $events;
        } catch (Throwable $e) {
            GameLog::error('bank', 'bank_negotiation_events FAILED', $e, ['neg_id' => $active['id']]);
            return [];
        }
    }

 /**
 * Checks whether a bailiff proceeding is active for the given loan.
 * Sprawdza, czy dla kredytu jest aktywny komornik.
 */
    private function checkBailiff(int $loanId): bool
    {
        if (!$this->db) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id FROM bailiff_proceedings
                WHERE loan_id = :lid AND status = 'active' LIMIT 1
            ");
            $stmt->execute([':lid' => $loanId]);
            return (bool)$stmt->fetch();
        } catch (Throwable $e) {
            GameLog::error('bank', 'bailiff check FAILED', $e, ['loan_id' => $loanId]);
            return false;
        }
    }

 /**
 * Loads restructure display helper data.
 * Laduje pomocnicze dane wyswietlania restrukturyzacji.
 */
    private function loadRestructDisplay(int $loanId): ?array
    {
        if (!$this->db) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT approved_extension_months, requested_at, decision_at
                FROM bank_negotiations
                WHERE loan_id = ?
                  AND type = 'restructure'
                  AND status IN ('completed','approved')
                ORDER BY decision_at DESC LIMIT 1
            ");
            $stmt->execute([$loanId]);
            $info = $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            GameLog::error('bank', 'restructureInfo FAILED', $e, ['loan_id' => $loanId]);
            return null;
        }

        if (!$info || empty($info['decision_at'])) {
            return null;
        }

        $months = (int)($info['approved_extension_months'] ?? 0);
        $appliedTs = strtotime($info['decision_at']);
        $endTs = $months > 0
            ? strtotime('+' . $months . ' months', $appliedTs)
            : strtotime('+30 days', $appliedTs);
        $now = time();

        return [
            'months' => $months,
            'totalDays' => (int)ceil(($endTs - $appliedTs) / 86400),
            'daysLeft' => max(0, (int)ceil(($endTs - $now) / 86400)),
            'isActive' => $endTs > $now,
            'expiresAt' => date('d.m.Y', $endTs),
        ];
    }

 /**
 * Resolves whether the player can apply for a new loan.
 * Wylicza, czy gracz moze zlozyc nowy wniosek kredytowy.
 */
    private function resolveCanApply(string $appSt, array $applicationStatus, array $activeLoans): array
    {
        $blockReasons = [];
        $blockHasActiveLoan = false;
        $isCrisis = false;

        if ($this->db) {
            try {
                $stmt = $this->db->prepare("SELECT financial_state FROM players WHERE id = ? LIMIT 1");
                $stmt->execute([$this->playerId]);
                $row = $stmt->fetch();
                if ($row && ($row['financial_state'] ?? 'normal') === 'crisis') {
                    $isCrisis = true;
                    $blockReasons[] = t('bank.block_crisis');
                }
            } catch (Throwable $e) {
            }
        }

        $canApplyStatuses = ['none', 'accepted', 'expired', 'defaulted', 'unknown', 'error'];
        if ($appSt === 'rejected' && ($applicationStatus['can_reapply'] ?? false)) {
            $canApplyStatuses[] = 'rejected';
        }
        $canApply = $this->bankService && in_array($appSt, $canApplyStatuses, true);

        foreach ($activeLoans as $loan) {
            if (in_array($loan['status'], ['active', 'late'], true)) {
                $blockReasons[] = t('bank.block_active_loan');
                $blockHasActiveLoan = true;
                break;
            }
        }

        if ($this->db) {
            try {
                $stmt = $this->db->prepare("
                    SELECT id FROM bailiff_proceedings
                    WHERE player_id=? AND status IN ('active','pending') LIMIT 1
                ");
                $stmt->execute([$this->playerId]);
                if ($stmt->fetch()) {
                    $blockReasons[] = t('bank.block_bailiff');
                }
            } catch (Throwable $e) {
            }

            try {
                $stmt = $this->db->prepare("
                    SELECT bn.type FROM bank_negotiations bn
                    JOIN loans l ON l.id = bn.loan_id
                    WHERE bn.player_id = ? AND bn.status IN ('pending','approved')
                      AND l.status IN ('active','late')
                    LIMIT 1
                ");
                $stmt->execute([$this->playerId]);
                $activeNeg = $stmt->fetch();
                if ($activeNeg) {
                    $blockReasons[] = match ($activeNeg['type']) {
                        'deferral' => t('bank.block_neg_deferral'),
                        'restructure' => t('bank.block_neg_restructure'),
                        'recovery' => t('bank.block_neg_recovery'),
                        default => t('bank.block_neg_other'),
                    };
                }
            } catch (Throwable $e) {
            }
        }

        if (!empty($blockReasons)) {
            $canApply = false;
        }

        return [$canApply, $blockReasons, $blockHasActiveLoan, $isCrisis];
    }

 /**
 * Loads credit limit and related blocking reasons.
 * Laduje limit kredytowy i powiazane powody blokady.
 */
    private function loadCreditLimit(bool $canApply, array &$blockReasons): array
    {
        $creditLimit = 0;
        $playerData = null;
        $wellsData = null;

        if (!$this->bankService || !$this->db) {
            return [$creditLimit];
        }

        try {
            $stmt = $this->db->prepare("SELECT * FROM players WHERE id = :pid LIMIT 1");
            $stmt->execute([':pid' => $this->playerId]);
            $playerData = $stmt->fetch() ?: null;

            $stmt = $this->db->prepare("
                SELECT COUNT(*) AS total,
                       SUM(CASE WHEN status NOT IN ('seized','paused_staff') THEN 1 ELSE 0 END) AS usable,
                       SUM(CASE WHEN well_type = 'offshore' THEN 1 ELSE 0 END) AS offshore_cnt,
                       SUM(CASE WHEN well_type = 'onshore' THEN 1 ELSE 0 END) AS onshore_cnt,
                       COALESCE(SUM(base_production_per_hour), 0) AS total_prod,
                       COALESCE(AVG(technical_condition), 100) AS avg_condition
                FROM wells
                WHERE player_id = :pid AND status NOT IN ('seized')
            ");
            $stmt->execute([':pid' => $this->playerId]);
            $wellsData = $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            GameLog::error('bank', 'creditLimit calc FAILED', $e, ['player' => $this->playerId]);
            return [$creditLimit];
        }

        if ((int)($wellsData['usable'] ?? 0) === 0) {
            $blockReasons[] = t('bank.err_no_wells');
            $canApply = false;
        }

        if ($playerData && !empty($playerData['created_at'])) {
            $accountAgeDays = (time() - strtotime((string)$playerData['created_at'])) / 86400;
            if ($accountAgeDays < 4) {
                $daysLeft = (int)ceil(4 - $accountAgeDays);
                $blockReasons[] = t('bank.block_account_too_young', ['days' => $daysLeft]);
                $canApply = false;
            }
        }

        if ($canApply && $playerData && $wellsData && (int)($wellsData['usable'] ?? 0) > 0) {
            try {
                $creditLimit = $this->bankService->calculateCreditLimit($this->playerId, $playerData, $wellsData);
            } catch (Throwable $e) {
                GameLog::error('bank', 'creditLimit calc FAILED', $e, ['player' => $this->playerId]);
            }
        }

        return [$creditLimit];
    }
}
