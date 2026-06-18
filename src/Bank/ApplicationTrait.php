<?php

/**
 * Handles loan applications, status checks and offer acceptance.
 * Obsluguje wnioski kredytowe, statusy i akceptacje ofert.
 */
trait BankApplicationTrait
{
 /**
 * Submits a loan application for the player.
 * Sklada wniosek kredytowy dla gracza.
 *
 * @return array<string, mixed>
 */
    public function submitLoanApplication(int $playerId, int $requestedAmount): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM players WHERE id = :id");
            $stmt->execute([':id' => $playerId]);
            $player = $stmt->fetch();

            if (!$player) {
                return ['success' => false, 'message' => t('bank.err_player_not_found')];
            }

 // Check minimum account age.
 // Sprawdz minimalny wiek konta.
            $accountAge = (time() - strtotime((string)$player['created_at'])) / 86400;
            if ($accountAge < 4) {
                $daysLeft = (int)ceil(4 - $accountAge);
                return ['success' => false, 'message' => t('bank.err_account_too_young', ['days' => $daysLeft])];
            }

 // Block applications during bankruptcy flow.
 // Blokuj wnioski podczas procesu bankructwa.
            $bkStatus = (string)($player['bankruptcy_status'] ?? 'none');
            $plStatus = (string)($player['status'] ?? 'active');
            if ($plStatus === 'bankrupt' || in_array($bkStatus, ['restructuring', 'liquidation'], true)) {
                return ['success' => false, 'message' => t('bank.err_bankruptcy')];
            }

 // Block applications during financial crisis.
 // Blokuj wnioski podczas kryzysu finansowego.
            if ((string)($player['financial_state'] ?? 'normal') === 'crisis') {
                return ['success' => false, 'message' => t('bank.err_crisis_mode')];
            }

 // Block when active bailiff proceedings exist.
 // Blokuj przy aktywnym postepowaniu komorniczym.
            $bailiffCheck = $this->db->prepare("
                SELECT bp.id
                FROM bailiff_proceedings bp
                JOIN loans l ON bp.loan_id = l.id
                WHERE bp.player_id = :pid
                  AND bp.status = 'active'
                LIMIT 1
            ");
            $bailiffCheck->execute([':pid' => $playerId]);
            if ($bailiffCheck->fetch()) {
                return ['success' => false, 'message' => t('bank.err_bailiff_active')];
            }

 // Prevent parallel applications and parallel active loans.
 // Blokuj rownolegle wnioski i rownolegle aktywne kredyty.
            $activeApp = $this->db->prepare("
                SELECT id FROM loan_applications
                WHERE player_id = :pid AND status IN ('pending', 'approved')
                LIMIT 1
            ");
            $activeApp->execute([':pid' => $playerId]);
            if ($activeApp->fetch()) {
                return ['success' => false, 'message' => t('bank.err_pending_application')];
            }

            $activeLoan = $this->db->prepare("
                SELECT id, remaining_amount, status
                FROM loans
                WHERE player_id = :pid AND status IN ('active', 'late')
                LIMIT 1
            ");
            $activeLoan->execute([':pid' => $playerId]);
            $existingLoan = $activeLoan->fetch();
            if ($existingLoan) {
                $remaining = number_format((float)$existingLoan['remaining_amount'], 0, '.', ' ');
                $loanStatus = $existingLoan['status'] === 'late'
                    ? t('bank.loan_status_late')
                    : t('bank.loan_status_active');
                return [
                    'success' => false,
                    'message' => t('bank.err_existing_loan', [
                        'status' => $loanStatus,
                        'remaining' => $remaining,
                    ]),
                ];
            }

 // Enforce cooldown after rejected applications.
 // Wymus cooldown po odrzuconym wniosku.
            $rejStmt = $this->db->prepare("
                SELECT decided_at FROM loan_applications
                WHERE player_id = :pid AND status = 'rejected'
                ORDER BY decided_at DESC LIMIT 1
            ");
            $rejStmt->execute([':pid' => $playerId]);
            $rejection = $rejStmt->fetch();
            if ($rejection && !empty($rejection['decided_at'])) {
                $next = strtotime((string)$rejection['decided_at']) + 48 * 3600;
                if (time() < $next) {
                    $hours = (int)ceil(($next - time()) / 3600);
                    return ['success' => false, 'message' => t('bank.err_rejection_cooldown', ['hours' => $hours])];
                }
            }

 // Require at least one usable well as collateral.
 // Wymagaj co najmniej jednego uzywalnego odwiertu jako zabezpieczenia.
            $wellsStmt = $this->db->prepare("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status NOT IN ('seized','paused_staff') THEN 1 ELSE 0 END) AS usable,
                    SUM(CASE WHEN well_type = 'offshore' THEN 1 ELSE 0 END) AS offshore_cnt,
                    SUM(CASE WHEN well_type = 'onshore'  THEN 1 ELSE 0 END) AS onshore_cnt,
                    COALESCE(SUM(base_production_per_hour), 0) AS total_prod,
                    COALESCE(AVG(technical_condition), 100) AS avg_condition
                FROM wells
                WHERE player_id = :pid AND status NOT IN ('seized')
            ");
            $wellsStmt->execute([':pid' => $playerId]);
            $wellsData = $wellsStmt->fetch();

            if ((int)$wellsData['usable'] === 0) {
                return ['success' => false, 'message' => t('bank.err_no_wells')];
            }

 // Calculate dynamic credit limit.
 // Oblicz dynamiczny limit kredytowy.
            $maxLoan = $this->calculateCreditLimit($playerId, $player, $wellsData);

            if ($requestedAmount <= 0) {
                return ['success' => false, 'message' => t('bank.err_amount_zero')];
            }
            if ($requestedAmount > $maxLoan) {
                return [
                    'success' => false,
                    'message' => t('bank.err_exceeds_limit', ['max' => number_format($maxLoan, 0, '.', ' ')]),
                    'max_loan' => $maxLoan,
                ];
            }

 // Store the pending application with a 1h decision window.
 // Zapisz oczekujacy wniosek z oknem decyzji 1h.
            $ins = $this->db->prepare("
                INSERT INTO loan_applications
                    (player_id, requested_amount, status, decision_at, created_at)
                VALUES
                    (:pid, :amount, 'pending', DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())
            ");
            $ins->execute([':pid' => $playerId, ':amount' => $requestedAmount]);
            $applicationId = (int)$this->db->lastInsertId();

            GameLog::info('BankService', 'Loan application submitted', [
                'player_id' => $playerId,
                'application_id' => $applicationId,
                'requested_amount' => $requestedAmount,
                'max_loan' => $maxLoan,
            ]);

            return [
                'success' => true,
                'message' => t('bank.msg_application_submitted'),
                'application_id' => $applicationId,
                'max_loan' => $maxLoan,
            ];
        } catch (Throwable $e) {
            GameLog::error('BankService', 'submitLoanApplication failed', [
                'player_id' => $playerId,
                'requested_amount' => $requestedAmount,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }

 /**
 * Returns the latest application status for a player.
 * Zwraca status najnowszego wniosku gracza.
 */
    public function getLoanApplicationStatus(int $playerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM loan_applications
                WHERE player_id = :pid
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([':pid' => $playerId]);
            $app = $stmt->fetch();

            if (!$app) {
                return ['status' => 'none', 'message' => t('bank.msg_no_application')];
            }

            switch ($app['status']) {
                case 'pending':
                    $left = max(0, strtotime((string)$app['decision_at']) - time());
                    $minutes = (int)ceil($left / 60);
                    return [
                        'status' => 'pending',
                        'message' => $minutes > 0
                            ? t('bank.msg_pending', ['min' => $minutes])
                            : t('bank.msg_pending_soon'),
                        'application' => $app,
                    ];

                case 'approved':
                    $apr = (float)$app['interest_rate'];
                    $principal = (float)$app['approved_amount'];

                    $installmentOptions = [];
                    foreach ([3, 5, 10, 20, 30, 40] as $n) {
                        $rate = self::calculateAnnuityInstallment($principal, $apr, $n, 12);
                        $installmentOptions[$n] = [
                            'n' => $n,
                            'installment' => $rate,
                            'total' => round($rate * $n),
                            'days' => round($n * 12 / 24, 1),
                        ];
                    }

                    $defaultN = 20;
                    $installment = self::calculateAnnuityInstallment($principal, $apr, $defaultN, 12);
                    $totalCost = round($installment * $defaultN);

 // Estimate daily delay interest based on APR / 365.
 // Oszacuj dzienne odsetki opoznienia jako APR / 365.
                    $dailyInterestCost = round($principal * ($apr / 100) / 365, 2);

                    return [
                        'status' => 'approved',
                        'message' => t('bank.msg_approved'),
                        'application' => $app,
                        'offer' => [
                            'amount' => $principal,
                            'requested_amount' => (float)$app['requested_amount'],
                            'interest_rate' => $apr,
                            'installment_amount' => $installment,
                            'estimated_total_cost' => $totalCost,
                            'daily_interest_cost' => $dailyInterestCost,
                            'reason' => $app['rejection_reason'],
                            'expires_at' => $app['expires_at'],
                            'installment_options' => $installmentOptions,
                        ],
                    ];

                case 'rejected':
                    $next = strtotime((string)$app['decided_at']) + 48 * 3600;
                    $canReapply = time() >= $next;
                    $hoursLeft = max(0, (int)ceil(($next - time()) / 3600));
                    return [
                        'status' => 'rejected',
                        'message' => t('bank.msg_rejected'),
                        'reason' => $app['rejection_reason'],
                        'application' => $app,
                        'can_reapply' => $canReapply,
                        'hours_until_reapply' => $hoursLeft,
                    ];

                case 'accepted':
                    return ['status' => 'accepted', 'message' => t('bank.msg_loan_active'), 'application' => $app];

                case 'expired':
                    return [
                        'status' => 'expired',
                        'message' => t('bank.msg_expired'),
                        'application' => $app,
                        'can_reapply' => true,
                    ];

                default:
                    return ['status' => 'unknown', 'message' => t('bank.msg_unknown_status')];
            }
        } catch (Throwable $e) {
            GameLog::error('BankService', 'getLoanApplicationStatus failed', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
            return ['status' => 'error', 'message' => t('common.app_error')];
        }
    }

 /**
 * Ensures required columns exist in loans table.
 * Zapewnia istnienie wymaganych kolumn w tabeli loans.
 */
    private function ensureLoanColumnsExist(): void
    {
        try {
 // Use a separate connection because ALTER TABLE cannot run inside a transaction.
 // Uzyj osobnego polaczenia, bo ALTER TABLE nie moze dzialac w transakcji.
            $db = $this->db->inTransaction()
                ? Database::getInstance()->getConnection()
                : $this->db;

 // Create the loans table if it does not exist yet.
 // Utworz tabele loans, jesli jeszcze nie istnieje.
            $tableExists = $db->query("SHOW TABLES LIKE 'loans'")->fetchColumn();
            if (!$tableExists) {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS loans (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        player_id INT NOT NULL,
                        application_id INT NOT NULL,
                        principal_amount DECIMAL(12,2) NOT NULL,
                        remaining_amount DECIMAL(12,2) NOT NULL,
                        interest_rate DECIMAL(5,2) NOT NULL,
                        installment_amount DECIMAL(12,2) NOT NULL,
                        installment_frequency INT DEFAULT 12,
                        installments_total INT DEFAULT 20,
                        installments_paid INT DEFAULT 0,
                        interest_model VARCHAR(20) DEFAULT 'annuity',
                        next_installment_at TIMESTAMP NOT NULL,
                        status ENUM('active','late','defaulted','paid_off') DEFAULT 'active',
                        late_since TIMESTAMP NULL,
                        last_interest_calc_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        total_interest_paid DECIMAL(12,2) DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        paid_off_at TIMESTAMP NULL,
                        resolved_at TIMESTAMP NULL,
                        FOREIGN KEY (player_id) REFERENCES players(id),
                        FOREIGN KEY (application_id) REFERENCES loan_applications(id)
                    )
                ");
                GameLog::info('BankService', 'Auto-migrated: created loans table');
                return;
            }

 // Add missing legacy columns one by one for older schemas.
 // Dodaj brakujace stare kolumny po jednej dla starszych schematow.
            $cols = $db->query("SHOW COLUMNS FROM loans LIKE 'installments_total'")->fetchColumn();
            if (!$cols) {
                $db->exec("ALTER TABLE loans ADD COLUMN installments_total INT DEFAULT 20");
                GameLog::info('BankService', 'Auto-migrated: added installments_total to loans');
            }

            $cols = $db->query("SHOW COLUMNS FROM loans LIKE 'installments_paid'")->fetchColumn();
            if (!$cols) {
                $db->exec("ALTER TABLE loans ADD COLUMN installments_paid INT DEFAULT 0");
                GameLog::info('BankService', 'Auto-migrated: added installments_paid to loans');
            }

            $cols = $db->query("SHOW COLUMNS FROM loans LIKE 'interest_model'")->fetchColumn();
            if (!$cols) {
                $db->exec("ALTER TABLE loans ADD COLUMN interest_model VARCHAR(20) DEFAULT 'annuity'");
                GameLog::info('BankService', 'Auto-migrated: added interest_model to loans');
            }

            $cols = $db->query("SHOW COLUMNS FROM loans LIKE 'resolved_at'")->fetchColumn();
            if (!$cols) {
                $db->exec("ALTER TABLE loans ADD COLUMN resolved_at TIMESTAMP NULL");
                GameLog::info('BankService', 'Auto-migrated: added resolved_at to loans');
            }
        } catch (Throwable $e) {
            GameLog::warn('BankService', 'Auto-migration failed', ['error' => $e->getMessage()]);
        }
    }

 /**
 * Accepts an approved loan offer and starts the loan.
 * Akceptuje zatwierdzona oferte i uruchamia kredyt.
 */
    public function acceptLoanOffer(int $applicationId, int $playerId, int $nInstallments = 20): array
    {
        $nInstallments = max(3, min(40, $nInstallments));

        try {
            // Zapewnij schemat historii bankowej przed transakcja / Ensure bank history schema before transaction.
            if (class_exists('BankAccountService')) {
                (new BankAccountService($this->db))->ensureSchema();
            }

 // Zapewnij zgodnosc schematu przed utworzeniem kredytu / Ensure schema compatibility before loan creation.
            $this->ensureLoanColumnsExist();

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                SELECT a.* FROM loan_applications a
                WHERE a.id = :id AND a.player_id = :pid AND a.status = 'approved'
                FOR UPDATE
            ");
            $stmt->execute([':id' => $applicationId, ':pid' => $playerId]);
            $app = $stmt->fetch();

            if (!$app) {
                throw new Exception(t('bank.err_app_not_found'));
            }

            if (!empty($app['expires_at']) && strtotime((string)$app['expires_at']) < time()) {
                $this->db->prepare("UPDATE loan_applications SET status = 'expired' WHERE id = :id")
                    ->execute([':id' => $applicationId]);
                throw new Exception(t('bank.err_offer_expired'));
            }

            $apr = (float)$app['interest_rate'];
            $principal = (float)$app['approved_amount'];
            $installment = self::calculateAnnuityInstallment($principal, $apr, $nInstallments, 12);
            $totalOwed = round($installment * $nInstallments, 2);

            $loanStmt = $this->db->prepare("
                INSERT INTO loans
                    (player_id, application_id, principal_amount, remaining_amount,
                     interest_rate, installment_amount, installment_frequency,
                     installments_total, installments_paid, interest_model,
                     next_installment_at, status, created_at)
                VALUES
                    (:pid, :app_id, :principal, :remaining,
                     :rate, :installment, 12,
                     :n_total, 0, 'annuity',
                     DATE_ADD(NOW(), INTERVAL 12 HOUR), 'active', NOW())
            ");
            $loanStmt->execute([
                ':pid' => $playerId,
                ':app_id' => $applicationId,
                ':principal' => $principal,
                ':remaining' => $totalOwed,
                ':rate' => $apr,
                ':installment' => $installment,
                ':n_total' => $nInstallments,
            ]);
            $loanId = (int)$this->db->lastInsertId();

            $creditResult = (new FinancialTransactionService($this->db))->credit(
                $playerId,
                $principal,
                FinancialTransactionService::TYPE_LOAN,
                t('bank.tx_loan_disbursement', ['id' => $loanId]),
                'loan',
                $loanId
            );
            if (empty($creditResult['success'])) {
                throw new RuntimeException(t('bank.err_financial_transaction'));
            }

            $this->db->prepare("UPDATE loan_applications SET status = 'accepted' WHERE id = :id")
                ->execute([':id' => $applicationId]);
            $this->db->commit();

            GameLog::info('BankService', 'Loan offer accepted (annuity)', [
                'player_id' => $playerId,
                'application_id' => $applicationId,
                'loan_id' => $loanId,
                'principal' => $principal,
                'n_installments' => $nInstallments,
                'installment' => $installment,
                'total_owed' => $totalOwed,
                'apr' => $apr,
            ]);

            return [
                'success' => true,
                'message' => t('bank.msg_loan_started', [
                    'n' => $nInstallments,
                    'installment' => number_format($installment, 0, '.', ' '),
                ]),
                'loan_id' => $loanId,
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('BankService', 'acceptLoanOffer failed', [
                'application_id' => $applicationId,
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

 /**
 * Rejects an approved loan offer on player request.
 * Odrzuca zatwierdzona oferte na prosbe gracza.
 */
    public function rejectLoanOffer(int $applicationId, int $playerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, status FROM loan_applications
                WHERE id = :id AND player_id = :pid LIMIT 1
            ");
            $stmt->execute([':id' => $applicationId, ':pid' => $playerId]);
            $app = $stmt->fetch();

            if (!$app) {
                return ['success' => false, 'message' => t('bank.err_offer_not_found')];
            }
            if ($app['status'] !== 'approved') {
                return ['success' => false, 'message' => t('bank.err_offer_not_approved')];
            }

            $upd = $this->db->prepare("
                UPDATE loan_applications
                SET status = 'rejected', decided_at = NOW(), rejection_reason = :reason
                WHERE id = :id AND player_id = :pid
            ");
            $upd->execute([
                ':reason' => t('bank.offer_rejected_by_player'),
                ':id' => $applicationId,
                ':pid' => $playerId,
            ]);

            GameLog::info('BankService', 'oferta odrzucona przez gracza', [
                'application_id' => $applicationId,
                'player_id' => $playerId,
            ]);

            return ['success' => true, 'message' => t('bank.msg_offer_rejected')];
        } catch (Throwable $e) {
            GameLog::error('BankService', 'rejectLoanOffer failed', [
                'application_id' => $applicationId,
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }
}
