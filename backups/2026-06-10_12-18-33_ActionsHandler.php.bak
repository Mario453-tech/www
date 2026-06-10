<?php

/**
 * Handles POST actions for the bank module.
 * Obsluguje akcje POST dla modulu banku.
 */
class BankActionsHandler
{
    private int $playerId;
    private ?BankService $bankService;
    private ?BankNegotiationService $bankNeg;

    public string $error = '';
    public string $success = '';

    public function __construct(int $playerId, ?BankService $bankService, ?BankNegotiationService $bankNeg)
    {
        $this->playerId = $playerId;
        $this->bankService = $bankService;
        $this->bankNeg = $bankNeg;
    }

 /**
 * Handles POST request and returns true if an action was executed.
 * Obsluguje zadanie POST i zwraca true, jesli wykonano akcje.
 */
    public function handle(): bool
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['action'])) {
            return false;
        }

        GameLog::step('bank', 'POST', 1, 'action=' . ($_POST['action'] ?? '?'));

        if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            $this->error = t('bank.action_err_csrf');
            GameLog::warn('bank', 'CSRF walidacja nieudana', ['player' => $this->playerId]);
            return true;
        }

        match ($_POST['action']) {
            'submit_application' => $this->submitApplication(),
            'accept_offer' => $this->acceptOffer(),
            'reject_offer' => $this->rejectOffer(),
            'repay_loan' => $this->repayLoan(),
            'neg_deferral' => $this->negDeferral(),
            'neg_restructure' => $this->negRestructure(),
            'neg_recovery' => $this->negRecovery(),
            'neg_apply' => $this->negApply(),
            'bank_transfer' => $this->bankTransfer(),
            default => GameLog::warn('bank', 'nieznana akcja POST', ['action' => $_POST['action'] ?? '', 'player' => $this->playerId]),
        };

        return true;
    }

 /**
 * Wykonuje przelew P2P z konta gracza na inne konto (brief, sekcja "Formularz przelewu").
 * Walidacja: numer konta istnieje, nie wlasny, kwota > 0, srodki wystarczajace.
 *
 * Performs a P2P transfer from the player's account to another account (brief, "Transfer form").
 * Validation: account exists, not self, amount > 0, sufficient funds.
 */
    private function bankTransfer(): void
    {
        if (!class_exists('FinancialTransactionService', false) && !@class_exists('FinancialTransactionService')) {
            $this->error = t('bank.action_err_service');
            return;
        }
        if (!class_exists('BankAccountService', false) && !@class_exists('BankAccountService')) {
            $this->error = t('bank.action_err_service');
            return;
        }

        try {
            $rawAccount = trim((string)($_POST['recipient_account'] ?? ''));
            // Sanityzacja kwoty - obsluga separatorow tysiecznych i polskiego przecinka.
            // Amount sanitization - handle thousands separators and Polish decimal comma.
            $rawAmount = preg_replace('/\s+/u', '', (string)($_POST['amount'] ?? '0'));
            if (strpos($rawAmount, '.') === false && strpos($rawAmount, ',') !== false) {
                $rawAmount = str_replace(',', '.', $rawAmount);
            }
            $amount = (float)$rawAmount;
            $description = trim((string)($_POST['description'] ?? ''));
            if (strlen($description) > 255) {
                $description = substr($description, 0, 255);
            }

            GameLog::step('bank', 'POST', 2, 'bank_transfer', [
                'recipient_account' => $rawAccount,
                'amount' => $amount,
            ]);

            if ($rawAccount === '') {
                $this->error = t('bank.account.err_account_empty');
                return;
            }
            if ($amount <= 0) {
                $this->error = t('bank.account.err_amount_invalid');
                return;
            }

            $accSvc = new BankAccountService();
            $recipientId = $accSvc->findPlayerIdByAccount($rawAccount);
            if ($recipientId === null) {
                $this->error = t('bank.account.err_account_not_found');
                return;
            }
            if ($recipientId === $this->playerId) {
                $this->error = t('bank.account.err_self_transfer');
                return;
            }

            $txSvc = new FinancialTransactionService();
            $result = $txSvc->transfer($this->playerId, $recipientId, $amount, $description !== '' ? $description : null);

            if ($result['success']) {
                $this->success = t('bank.account.msg_transfer_ok', [
                    'amount' => number_format($result['amount'], 2, ',', ' '),
                    'account' => $rawAccount,
                ]);
                GameLog::info('bank', 'transfer OK', [
                    'tx_id' => $result['transaction_id'],
                    'from' => $this->playerId,
                    'to' => $recipientId,
                    'amount' => $result['amount'],
                ]);

                // Etap 6: powiadomienia dla nadawcy i odbiorcy (brief, sekcja "Powiadomienia").
                // Pelnie guarded - blad powiadomien nie cofa juz wykonanego przelewu.
                // Stage 6: notifications for sender and recipient (brief, "Notifications").
                // Fully guarded - notification failure does not roll back the completed transfer.
                $this->notifyTransferParties(
                    $this->playerId,
                    $recipientId,
                    $rawAccount,
                    (float)$result['amount'],
                    $description
                );
            } else {
                $this->error = match ($result['error']) {
                    'insufficient_funds'   => t('bank.account.err_insufficient_funds'),
                    'invalid_amount'       => t('bank.account.err_amount_invalid'),
                    'self_transfer'        => t('bank.account.err_self_transfer'),
                    'sender_not_found',
                    'recipient_not_found'  => t('bank.account.err_account_not_found'),
                    default                => t('bank.account.err_generic'),
                };
            }
        } catch (Throwable $e) {
            $this->error = t('bank.account.err_generic');
            GameLog::error('bank', 'bank_transfer FAILED', $e, ['player' => $this->playerId]);
        }
    }

 /**
 * Submits a loan application for the player.
 * Wysyla wniosek kredytowy dla gracza.
 */
    private function submitApplication(): void
    {
        if (!$this->bankService) {
            $this->error = t('bank.action_err_service_retry');
            return;
        }

        try {
            $amount = (int)($_POST['amount'] ?? 0);
            GameLog::step('bank', 'POST', 2, 'submit_application', ['amount' => $amount]);
            $result = $this->bankService->submitLoanApplication($this->playerId, $amount);

            if ($result['success']) {
                $this->success = $result['message'];
                GameLog::info('bank', 'loan application submitted', ['player' => $this->playerId]);
            } else {
                $this->error = $result['message'];
                GameLog::info('bank', 'wniosek odrzucony', ['reason' => $result['message']]);
            }
        } catch (Throwable $e) {
            $this->error = t('bank.action_err_submit_application');
            GameLog::error('bank', 'submit_application FAILED', $e, ['player' => $this->playerId]);
        }
    }

 /**
 * Rejects a prepared bank offer.
 * Odrzuca przygotowana oferte banku.
 */
    private function rejectOffer(): void
    {
        if (!$this->bankService) {
            $this->error = t('bank.action_err_service');
            return;
        }

        try {
            $appId = (int)($_POST['application_id'] ?? 0);
            GameLog::step('bank', 'POST', 2, 'reject_offer', ['app_id' => $appId]);
            $result = $this->bankService->rejectLoanOffer($appId, $this->playerId);

            if ($result['success']) {
                $this->success = $result['message'];
                GameLog::info('bank', 'oferta odrzucona', ['app_id' => $appId]);
            } else {
                $this->error = $result['message'];
            }
        } catch (Throwable $e) {
            $this->error = t('bank.action_err_reject_offer');
            GameLog::error('bank', 'reject_offer FAILED', $e, ['player' => $this->playerId]);
        }
    }

 /**
 * Accepts an approved bank offer.
 * Akceptuje zatwierdzona oferte banku.
 */
    private function acceptOffer(): void
    {
        if (!$this->bankService) {
            $this->error = t('bank.action_err_service');
            return;
        }

        try {
            $appId = (int)($_POST['application_id'] ?? 0);
            $nInstallments = (int)($_POST['n_installments'] ?? 20);
            GameLog::step('bank', 'POST', 2, 'accept_offer', ['app_id' => $appId, 'n' => $nInstallments]);
            $result = $this->bankService->acceptLoanOffer($appId, $this->playerId, $nInstallments);

            if ($result['success']) {
                $this->success = $result['message'];
                GameLog::info('bank', 'oferta zaakceptowana', ['app_id' => $appId]);
            } else {
                $this->error = $result['message'];
            }
        } catch (Throwable $e) {
            $this->error = t('bank.action_err_accept_offer');
            GameLog::error('bank', 'accept_offer FAILED', $e, ['player' => $this->playerId]);
        }
    }

 /**
 * Repays a selected bank loan.
 * Splca wybrany kredyt bankowy.
 */
    private function repayLoan(): void
    {
        if (!$this->bankService) {
            $this->error = t('bank.action_err_service');
            return;
        }

        try {
            $loanId = (int)($_POST['loan_id'] ?? 0);
            $mode = in_array($_POST['repay_mode'] ?? '', ['single', 'multiple', 'full'], true)
                ? $_POST['repay_mode']
                : 'single';
            $count = (int)($_POST['repay_count'] ?? 1);
            GameLog::step('bank', 'POST', 2, 'repay_loan', ['loan' => $loanId, 'mode' => $mode]);
            $result = $this->bankService->repay($loanId, $this->playerId, $mode, $count);

            if ($result['success']) {
                $this->success = $result['message'];
                GameLog::info('bank', 'repayment completed', ['loan' => $loanId, 'mode' => $mode]);
            } else {
                $this->error = $result['message'];
            }
        } catch (Throwable $e) {
            $this->error = t('bank.action_err_repay_loan');
            GameLog::error('bank', 'repay_loan FAILED', $e, ['player' => $this->playerId]);
        }
    }

 /**
 * Sends a deferral negotiation request.
 * Wysyla wniosek negocjacyjny o odroczenie.
 */
    private function negDeferral(): void
    {
        if (!$this->bankNeg) {
            $this->error = t('bank.action_err_negotiation_unavailable');
            return;
        }

        try {
            $loanId = (int)($_POST['loan_id'] ?? 0);
            $days = (int)($_POST['days'] ?? 0);
            GameLog::step('bank', 'POST', 2, 'neg_deferral', ['loan' => $loanId, 'days' => $days]);
            $result = $this->bankNeg->requestDeferral($this->playerId, $loanId, $days);

            if ($result['success']) {
                $this->success = htmlspecialchars($result['message']);
                if (!empty($result['cfo_message'])) {
                    $this->success .= '<br><em class="neg-cfo-msg">' . htmlspecialchars($result['cfo_message']) . '</em>';
                }
                GameLog::info('bank', 'deferral submitted', ['loan' => $loanId, 'neg_id' => $result['negotiation_id'] ?? null]);
            } else {
                $this->error = $result['message'];
            }
        } catch (Throwable $e) {
            $this->error = t('bank.action_err_neg_deferral');
            GameLog::error('bank', 'neg_deferral FAILED', $e, ['player' => $this->playerId]);
        }
    }

 /**
 * Sends a restructure negotiation request.
 * Wysyla wniosek negocjacyjny o restrukturyzacje.
 */
    private function negRestructure(): void
    {
        if (!$this->bankNeg) {
            $this->error = t('bank.action_err_negotiation_unavailable');
            return;
        }

        try {
            $loanId = (int)($_POST['loan_id'] ?? 0);
            $months = (int)($_POST['months'] ?? 0);
            GameLog::step('bank', 'POST', 2, 'neg_restructure', ['loan' => $loanId, 'months' => $months]);
            $result = $this->bankNeg->requestRestructure($this->playerId, $loanId, $months);

            if ($result['success']) {
                $this->success = htmlspecialchars($result['message']);
                if (!empty($result['cfo_message'])) {
                    $this->success .= '<br><em class="neg-cfo-msg">' . htmlspecialchars($result['cfo_message']) . '</em>';
                }
                GameLog::info('bank', 'restructure submitted', ['loan' => $loanId, 'neg_id' => $result['negotiation_id'] ?? null]);
            } else {
                $this->error = $result['message'];
            }
        } catch (Throwable $e) {
            $this->error = t('bank.action_err_neg_restructure');
            GameLog::error('bank', 'neg_restructure FAILED', $e, ['player' => $this->playerId]);
        }
    }

 /**
 * Sends a recovery plan negotiation request.
 * Wysyla wniosek negocjacyjny o plan naprawczy.
 */
    private function negRecovery(): void
    {
        if (!$this->bankNeg) {
            $this->error = t('bank.action_err_negotiation_unavailable');
            return;
        }

        try {
            $loanId = (int)($_POST['loan_id'] ?? 0);
            GameLog::step('bank', 'POST', 2, 'neg_recovery', ['loan' => $loanId]);
            $result = $this->bankNeg->requestRecoveryPlan($this->playerId, $loanId);

            if ($result['success']) {
                $this->success = htmlspecialchars($result['message']);
                if (!empty($result['cfo_message'])) {
                    $this->success .= '<br><em class="neg-cfo-msg">' . htmlspecialchars($result['cfo_message']) . '</em>';
                }
                if (!empty($result['lawyer_message'])) {
                    $this->success .= '<br><em class="neg-lawyer-msg">' . htmlspecialchars($result['lawyer_message']) . '</em>';
                }
                GameLog::info('bank', 'recovery plan submitted', ['loan' => $loanId, 'neg_id' => $result['negotiation_id'] ?? null]);
            } else {
                $this->error = $result['message'];
            }
        } catch (Throwable $e) {
            $this->error = t('bank.action_err_neg_recovery');
            GameLog::error('bank', 'neg_recovery FAILED', $e, ['player' => $this->playerId]);
        }
    }

 /**
 * Applies an approved negotiation result.
 * Zastosowuje zatwierdzony wynik negocjacji.
 */
    private function negApply(): void
    {
        if (!$this->bankNeg) {
            $this->error = t('bank.action_err_negotiation_unavailable');
            return;
        }

        try {
            $negId = (int)($_POST['negotiation_id'] ?? 0);
            GameLog::step('bank', 'POST', 2, 'neg_apply', ['neg_id' => $negId]);
            $result = $this->bankNeg->applyNegotiation($negId, $this->playerId);

            if ($result['success']) {
                $this->success = $result['message'];
                GameLog::info('bank', 'negocjacja zastosowana', ['neg_id' => $negId]);
            } else {
                $this->error = $result['message'];
            }
        } catch (Throwable $e) {
            $this->error = t('bank.action_err_neg_apply');
            GameLog::error('bank', 'neg_apply FAILED', $e, ['player' => $this->playerId]);
        }
    }

 /**
 * Tworzy 2 powiadomienia po udanym przelewie: dla nadawcy i dla odbiorcy.
 * Brief, sekcja "Powiadomienia": kwota, data, opis.
 * Pelnie guarded - blad powiadomien nie cofa juz wykonanego przelewu.
 *
 * Creates 2 notifications after a successful transfer: sender and recipient.
 * Brief, "Notifications": amount, date, description.
 * Fully guarded - notification failure does not roll back the completed transfer.
 */
    private function notifyTransferParties(
        int $senderId,
        int $recipientId,
        string $recipientAccount,
        float $amount,
        string $description
    ): void {
        if (!class_exists('DirectorNotificationService', false) && !@class_exists('DirectorNotificationService')) {
            return;
        }
        if (!class_exists('BankAccountService', false) && !@class_exists('BankAccountService')) {
            return;
        }

        try {
            $accSvc = new BankAccountService();
            $senderAccount = (string)($accSvc->getAccount($senderId) ?? '-');

            // Nazwy firm dla czytelnego komunikatu (fallback: numer rachunku).
            // Company names for a readable message (fallback: account number).
            $db = Database::getInstance()->getConnection();
            $nameStmt = $db->prepare(
                "SELECT id, COALESCE(NULLIF(company_name, ''), username) AS display_name
                   FROM players WHERE id IN (?, ?)"
            );
            $nameStmt->execute([$senderId, $recipientId]);
            $names = [];
            foreach ($nameStmt->fetchAll() as $r) {
                $names[(int)$r['id']] = (string)$r['display_name'];
            }
            $senderName    = $names[$senderId]    ?? $senderAccount;
            $recipientName = $names[$recipientId] ?? $recipientAccount;

            $amountFmt = number_format($amount, 2, ',', ' ');
            $dateFmt   = date('d.m.Y H:i');
            $descFmt   = $description !== '' ? $description : '(brak opisu)';

            $notifSvc = new DirectorNotificationService();

            // Powiadomienie dla nadawcy.
            // Sender notification.
            try {
                $notifSvc->create($senderId, 'bank_transfer_sent', [
                    'amount'      => $amountFmt,
                    'recipient'   => $recipientAccount . ' (' . $recipientName . ')',
                    'date'        => $dateFmt,
                    'description' => $descFmt,
                ], 72);
            } catch (Throwable $e) {
                GameLog::error('bank', 'notify sender FAILED', $e, ['player' => $senderId]);
            }

            // Powiadomienie dla odbiorcy.
            // Recipient notification.
            try {
                $notifSvc->create($recipientId, 'bank_transfer_received', [
                    'amount'      => $amountFmt,
                    'sender'      => $senderName . ' (' . $senderAccount . ')',
                    'date'        => $dateFmt,
                    'description' => $descFmt,
                ], 72);
            } catch (Throwable $e) {
                GameLog::error('bank', 'notify recipient FAILED', $e, ['player' => $recipientId]);
            }
        } catch (Throwable $e) {
            GameLog::error('bank', 'notifyTransferParties FAILED', $e, [
                'sender' => $senderId, 'recipient' => $recipientId,
            ]);
        }
    }
}
