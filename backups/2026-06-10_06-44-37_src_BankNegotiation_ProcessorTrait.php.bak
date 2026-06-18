<?php

/**
 * Resolves bank decisions, player acceptance, and plan verification.
 * Rozwiazuje decyzje banku, akceptacje gracza i weryfikacje planow.
 */
trait BankNegotiationProcessorTrait
{
 /**
 * Processes all pending negotiations whose decision time has passed.
 * Przetwarza wszystkie oczekujace negocjacje po czasie decyzji.
 */
    public function processPendingNegotiations(): int
    {
        GameLog::step('BankNeg', 'processPending', 1, 'start');

        try {
            $pending = $this->db->query("
                SELECT bn.*,
                       l.remaining_amount, l.principal_amount,
                       l.interest_rate, l.installment_amount, l.status AS loan_status,
                       p.credit_score
                FROM bank_negotiations bn
                JOIN loans   l ON l.id  = bn.loan_id
                JOIN players p ON p.id  = bn.player_id
                WHERE bn.status='pending'
                  AND bn.decision_due_at <= NOW()
            ")->fetchAll();

            $processed = 0;
            foreach ($pending as $neg) {
                try {
                    $ctx = $this->buildContext((int)$neg['player_id'], $neg);
                    $this->resolveNegotiation($neg, $ctx);
                    $processed++;
                } catch (Throwable $e) {
                    GameLog::error('BankNeg', 'resolveNegotiation FAILED', $e, ['id' => $neg['id']]);
                }
            }

            return $processed;
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'processPendingNegotiations FAILED', $e);
            return 0;
        }
    }

 /**
 * Resolves a single negotiation by approval chance roll.
 * Rozwiazuje pojedyncza negocjacje na podstawie losowania szansy.
 *
 * @param array<string, mixed> $neg
 * @param array<string, mixed> $ctx
 */
    private function resolveNegotiation(array $neg, array $ctx): void
    {
        $chance = (int)($neg['approval_chance'] ?? 75);
        $roll = rand(1, 100);
        $approved = ($roll <= $chance);

        GameLog::info('BankNeg', 'resolveNegotiation', [
            'id' => $neg['id'],
            'chance' => $chance,
            'roll' => $roll,
            'approved' => $approved,
        ]);

        if ($approved) {
            $expires = date('Y-m-d H:i:s', strtotime('+' . self::APPROVAL_VALID_HOURS . ' hours'));
            $msg = $this->buildApprovalMessage($neg, $ctx);
            $this->db->prepare("
                UPDATE bank_negotiations SET
                    status                    = 'approved',
                    approved_deferral_days    = requested_deferral_days,
                    approved_extension_months = requested_extension_months,
                    bank_decision             = :msg,
                    decision_at               = NOW(),
                    expires_at                = :exp
                WHERE id=:id
            ")->execute([':msg' => $msg, ':exp' => $expires, ':id' => $neg['id']]);

            $this->adjustTrustScore((int)$neg['player_id'], 'negocjacja_sukces');
        } else {
            $rejection = $this->buildRejectionMessage($neg, $ctx);
            $this->db->prepare("
                UPDATE bank_negotiations SET
                    status           = 'rejected',
                    bank_decision    = :msg,
                    rejection_reason = :reason,
                    decision_at      = NOW()
                WHERE id=:id
            ")->execute([
                ':msg' => $rejection['public'],
                ':reason' => $rejection['internal'],
                ':id' => $neg['id'],
            ]);

 // Restore bailiff when a suspended negotiation path is rejected.
 // Przywroc komornika, gdy odrzucono zawieszona sciezke negocjacji.
            $this->db->prepare("
                UPDATE bailiff_proceedings SET status='active'
                WHERE loan_id=:lid AND status='suspended'
            ")->execute([':lid' => $neg['loan_id']]);

            $this->adjustTrustScore((int)$neg['player_id'], 'negocjacja_odrzucona');
        }

        $this->notifyDirector(
            (int)$neg['player_id'],
            $approved ? 'bank_negotiation_approved' : 'bank_negotiation_rejected',
            ['type' => match ($neg['type']) {
                'deferral' => t('bank_neg.type_deferral'),
                'restructure' => t('bank_neg.type_restructure'),
                'recovery' => t('bank_neg.type_recovery'),
                default => t('bank_neg.type_negotiation'),
            }]
        );
    }

 /**
 * Applies an approved negotiation chosen by the player.
 * Zastosowuje zatwierdzona negocjacje wybrana przez gracza.
 */
    public function applyNegotiation(int $negotiationId, int $playerId): array
    {
        GameLog::step('BankNeg', 'applyNegotiation', 1, 'start', [
            'id' => $negotiationId,
            'player' => $playerId,
        ]);

        try {
            $stmt = $this->db->prepare("
                SELECT * FROM bank_negotiations
                WHERE id=:id AND player_id=:pid AND status='approved'
                  AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stmt->execute([':id' => $negotiationId, ':pid' => $playerId]);
            $neg = $stmt->fetch();

            if (!$neg) {
                return ['success' => false, 'message' => t('bank_neg.err_neg_not_found')];
            }

            $loan = $this->getLoan((int)$neg['loan_id'], $playerId);
            if (!$loan) {
                return ['success' => false, 'message' => t('bank_neg.err_loan_not_found')];
            }

            if ((float)$neg['additional_fee'] > 0) {
                $pRow = $this->db->prepare("SELECT cash FROM players WHERE id=:id");
                $pRow->execute([':id' => $playerId]);
                $p = $pRow->fetch();
                if (!$p || (float)$p['cash'] < (float)$neg['additional_fee']) {
                    return [
                        'success' => false,
                        'message' => t('bank_neg.err_no_funds_fee', ['fee' => number_format((float)$neg['additional_fee'])]),
                    ];
                }
            }

            $this->db->beginTransaction();

            if ((float)$neg['additional_fee'] > 0) {
                $this->db->prepare("UPDATE players SET cash=cash-:fee WHERE id=:id")
                    ->execute([':fee' => $neg['additional_fee'], ':id' => $playerId]);
            }

            if ($neg['type'] === 'deferral') {
                $newInstallmentAt = date(
                    'Y-m-d H:i:s',
                    strtotime('+' . (int)$neg['approved_deferral_days'] . ' days', strtotime($loan['next_installment_at'] ?? 'now'))
                );

                $this->db->prepare("
                    UPDATE loans SET
                        next_installment_at = DATE_ADD(next_installment_at, INTERVAL :days DAY),
                        interest_rate       = :rate,
                        late_since          = NULL,
                        status              = 'active'
                    WHERE id=:id
                ")->execute([
                    ':days' => $neg['approved_deferral_days'],
                    ':rate' => $neg['new_interest_rate'],
                    ':id' => $neg['loan_id'],
                ]);

 // Suspend bailiff until the shifted installment date.
 // Zawies komornika do przesunietej daty raty.
                $this->db->prepare("
                    UPDATE bailiff_proceedings
                    SET status         = 'suspended',
                        suspended_at   = NOW(),
                        suspended_until = :until,
                        suspend_reason = 'deferral'
                    WHERE loan_id = :lid
                      AND status IN ('active', 'pending')
                ")->execute([
                    ':until' => $newInstallmentAt,
                    ':lid' => $neg['loan_id'],
                ]);

                GameLog::info('BankNeg', 'deferral - bailiff suspended', [
                    'loan_id' => $neg['loan_id'],
                    'player_id' => $playerId,
                    'suspended_until' => $newInstallmentAt,
                    'days' => $neg['approved_deferral_days'],
                ]);
            } elseif ($neg['type'] === 'restructure') {
                $months = (int)$neg['approved_extension_months'];
                $remaining = (float)$loan['remaining_amount'];
                $installment = (float)$loan['installment_amount'];
                $currentRats = ($installment > 0) ? (int)ceil($remaining / $installment) : 20;
                $newRats = $currentRats + ($months * 2);
                $newInst = ($newRats > 0) ? round($remaining / $newRats, 2) : $installment;

                $this->db->prepare("
                    UPDATE loans SET installment_amount=:inst, late_since=NULL, status='active'
                    WHERE id=:id
                ")->execute([':inst' => $newInst, ':id' => $neg['loan_id']]);
            } elseif ($neg['type'] === 'recovery') {
 // Recovery plan suspends bailiff until full repayment or violation.
 // Plan naprawczy zawiesza komornika do pelnej splaty lub naruszenia.
                $this->db->prepare("
                    UPDATE bailiff_proceedings
                    SET status='suspended_recovery', negotiation_used=1,
                        suspended_at=NOW(), suspend_reason='plan_naprawczy'
                    WHERE loan_id=:lid AND status IN ('active','suspended')
                ")->execute([':lid' => $neg['loan_id']]);

                $this->db->prepare("
                    UPDATE loans SET late_since=NULL, status='active' WHERE id=:id
                ")->execute([':id' => $neg['loan_id']]);

                $this->adjustTrustScore($playerId, 'plan_naprawczy_ok');
            }

            $this->db->prepare("
                UPDATE bank_negotiations SET status='completed', decision_at=NOW() WHERE id=:id
            ")->execute([':id' => $negotiationId]);

            $this->db->commit();

            $fee = number_format((float)$neg['additional_fee']);
            $confirmMsg = match ($neg['type']) {
                'deferral' => t('bank_neg.msg_deferral_applied', ['fee' => $fee]),
                'restructure' => t('bank_neg.msg_restructure_applied', ['fee' => $fee]),
                'recovery' => t('bank_neg.msg_recovery_applied', ['fee' => $fee]),
                default => t('bank_neg.msg_applied'),
            };

            return ['success' => true, 'message' => $confirmMsg];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('BankNeg', 'applyNegotiation FAILED', $e, ['id' => $negotiationId]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }

 /**
 * Reactivates bailiff when recovery plan is violated.
 * Reaktywuje komornika przy naruszeniu planu naprawczego.
 */
    public function checkRecoveryPlanViolations(): int
    {
        $violations = 0;
        try {
            $rows = $this->db->query("
                SELECT l.id AS loan_id, l.player_id, l.next_installment_at
                FROM loans l
                WHERE l.status = 'late'
                  AND EXISTS (
                    SELECT 1 FROM bailiff_proceedings bp
                    WHERE bp.loan_id = l.id AND bp.status = 'suspended_recovery'
                  )
            ")->fetchAll();

            foreach ($rows as $row) {
                try {
                    $this->db->prepare("
                        UPDATE bailiff_proceedings
                        SET status='active', suspend_reason=NULL
                        WHERE loan_id=:lid AND status='suspended_recovery'
                    ")->execute([':lid' => $row['loan_id']]);

                    $this->adjustTrustScore((int)$row['player_id'], 'plan_naruszony');

                    $this->notifyDirector((int)$row['player_id'], 'recovery_plan_violated', [
                        'loan_id' => $row['loan_id'],
                    ]);

                    // Wiarygodnosc firmy: zlamany plan naprawczy / Company credibility: recovery plan broken
                    try {
                        (new CompanyCredibilityService($this->db))
                            ->applyEvent((int)$row['player_id'], 'recovery_plan_broken');
                    } catch (Throwable $ce) {
                        GameLog::error('BankNeg', 'credibility hook (recovery) FAILED', $ce, ['player_id' => $row['player_id']]);
                    }

                    $violations++;
                } catch (Throwable $e) {
                    GameLog::error('BankNeg', 'checkRecoveryViolation FAILED', $e, ['loan' => $row['loan_id']]);
                }
            }
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'checkRecoveryPlanViolations FAILED', $e);
        }
        return $violations;
    }

 /**
 * Checks suspended deferrals and reactivates bailiff when needed.
 * Sprawdza zawieszone odroczenia i reaktywuje komornika gdy trzeba.
 */
    public function checkDeferralSuspensions(): int
    {
        $reactivated = 0;
        try {
            $rows = $this->db->query("
                SELECT bp.id AS bp_id, bp.loan_id, bp.player_id, bp.suspended_until,
                       l.status AS loan_status, l.late_since, l.next_installment_at
                FROM bailiff_proceedings bp
                JOIN loans l ON l.id = bp.loan_id
                WHERE bp.status = 'suspended'
                  AND bp.suspend_reason = 'deferral'
                  AND bp.suspended_until IS NOT NULL
                  AND bp.suspended_until <= NOW()
            ")->fetchAll();

            foreach ($rows as $row) {
                try {
                    if ($row['loan_status'] === 'late' || $row['late_since'] !== null) {
                        $this->db->prepare("
                            UPDATE bailiff_proceedings
                            SET status = 'active',
                                suspended_until = NULL,
                                suspend_reason  = NULL,
                                next_action_at  = NOW()
                            WHERE id = :id
                        ")->execute([':id' => $row['bp_id']]);

                        $this->adjustTrustScore((int)$row['player_id'], 'plan_naruszony');

                        $this->notifyDirector((int)$row['player_id'], 'deferral_expired_late', [
                            'loan_id' => $row['loan_id'],
                        ]);

                        GameLog::info('BankNeg', 'deferral expired - installment overdue, bailiff reactivated', [
                            'bp_id' => $row['bp_id'],
                            'loan_id' => $row['loan_id'],
                            'player_id' => $row['player_id'],
                        ]);

                        $reactivated++;
                    } else {
 // Installment was paid, clear the suspension timer.
 // Rata zostala splacona, wyczysc licznik zawieszenia.
                        $this->db->prepare("
                            UPDATE bailiff_proceedings
                            SET suspended_until = NULL
                            WHERE id = :id
                        ")->execute([':id' => $row['bp_id']]);

                        GameLog::info('BankNeg', 'deferral expired - installment paid, bailiff remains suspended', [
                            'bp_id' => $row['bp_id'],
                            'loan_id' => $row['loan_id'],
                        ]);
                    }
                } catch (Throwable $e) {
                    GameLog::error('BankNeg', 'checkDeferralSuspensions row FAILED', $e, ['bp_id' => $row['bp_id']]);
                }
            }
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'checkDeferralSuspensions FAILED', $e);
        }
        return $reactivated;
    }

 /**
 * Dismisses recovery plan suspensions after full repayment.
 * Zamyka zawieszenia planu naprawczego po pelnej splacie.
 */
    public function checkRecoveryPlanCompleted(): int
    {
        $completed = 0;
        try {
            $rows = $this->db->query("
                SELECT l.id AS loan_id, l.player_id
                FROM loans l
                WHERE l.remaining_amount <= 0
                  AND EXISTS (
                    SELECT 1 FROM bailiff_proceedings bp
                    WHERE bp.loan_id = l.id AND bp.status = 'suspended_recovery'
                  )
            ")->fetchAll();

            foreach ($rows as $row) {
                try {
                    $this->db->prepare("
                        UPDATE bailiff_proceedings
                        SET status='dismissed', dismissed_at=NOW()
                        WHERE loan_id=:lid AND status='suspended_recovery'
                    ")->execute([':lid' => $row['loan_id']]);

                    $this->adjustTrustScore((int)$row['player_id'], 'negocjacja_dotrzymana');

                    $this->notifyDirector((int)$row['player_id'], 'recovery_plan_success', [
                        'loan_id' => $row['loan_id'],
                    ]);

                    $completed++;
                } catch (Throwable $e) {
                    GameLog::error('BankNeg', 'checkRecoveryCompleted FAILED', $e);
                }
            }
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'checkRecoveryPlanCompleted FAILED', $e);
        }
        return $completed;
    }

 /**
 * Checks if a player is currently allowed to negotiate a loan.
 * Sprawdza, czy gracz moze teraz negocjowac kredyt.
 */
    public function canNegotiate(int $playerId, int $loanId): array
    {
        try {
            $loan = $this->getLoan($loanId, $playerId);
            if (!$loan) {
                return ['can_negotiate' => false, 'reason' => t('bank_neg.err_loan_not_found')];
            }
            if (!in_array($loan['status'], ['active', 'late'], true)) {
                return ['can_negotiate' => false, 'reason' => t('bank_neg.err_negotiate_status')];
            }

 // Negotiations are available only after bailiff reaches wells.
 // Negocjacje sa dostepne dopiero po zajeciu odwiertow przez komornika.
            $bStmt = $this->db->prepare("
                SELECT id, negotiation_used FROM bailiff_proceedings
                WHERE loan_id = :lid AND status = 'active' LIMIT 1
            ");
            $bStmt->execute([':lid' => $loanId]);
            $bailiff = $bStmt->fetch();

            if (!$bailiff) {
                if ($loan['status'] === 'late') {
                    return [
                        'can_negotiate' => false,
                        'reason' => t('bank_neg.err_no_bailiff_late'),
                        'bailiff_pending' => true,
                    ];
                }
                return ['can_negotiate' => false, 'reason' => t('bank_neg.err_no_bailiff')];
            }

            if ($bailiff['negotiation_used']) {
                return ['can_negotiate' => false, 'reason' => t('bank_neg.err_negotiation_used')];
            }

            if ($this->hasPendingOrApproved($loanId)) {
                $neg = $this->getActivePendingOrApproved($loanId);
                if ($neg && $neg['status'] === 'pending') {
                    $due = date('H:i d.m.Y', strtotime($neg['decision_due_at']));
                    return [
                        'can_negotiate' => false,
                        'reason' => t('bank_neg.err_pending_decision', ['due' => $due]),
                        'pending' => $neg,
                    ];
                }
                return ['can_negotiate' => false, 'reason' => t('bank_neg.err_approved_waiting'), 'approved' => $neg];
            }

            return [
                'can_negotiate' => true,
                'reason' => null,
                'last_chance' => true,
            ];
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'canNegotiate FAILED', $e);
            return ['can_negotiate' => false, 'reason' => t('common.app_error')];
        }
    }

 /**
 * Returns active approved negotiations for one loan.
 * Zwraca aktywne zatwierdzone negocjacje dla jednego kredytu.
 */
    public function getActiveNegotiationsForLoan(int $playerId, int $loanId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM bank_negotiations
                WHERE player_id=:pid AND loan_id=:lid AND status='approved'
                  AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY requested_at DESC
            ");
            $stmt->execute([':pid' => $playerId, ':lid' => $loanId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'getActiveNegotiationsForLoan FAILED', $e);
            return [];
        }
    }

 /**
 * Returns the first active pending or approved negotiation for a loan.
 * Zwraca pierwsza aktywna negocjacje pending lub approved dla kredytu.
 */
    public function getActivePendingOrApproved(int $loanId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM bank_negotiations
                WHERE loan_id=:lid AND status IN ('pending','approved')
                  AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1
            ");
            $stmt->execute([':lid' => $loanId]);
            return $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'getActivePendingOrApproved FAILED', $e, ['loan_id' => $loanId]);
            return null;
        }
    }

 /**
 * Returns negotiation history for a player.
 * Zwraca historie negocjacji dla gracza.
 */
    public function getNegotiationHistory(int $playerId, int $limit = 20): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT bn.*, l.remaining_amount FROM bank_negotiations bn
                JOIN loans l ON l.id=bn.loan_id
                WHERE bn.player_id=:pid ORDER BY bn.requested_at DESC LIMIT :lim
            ");
            $stmt->bindValue(':pid', $playerId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'getNegotiationHistory FAILED', $e);
            return [];
        }
    }

 /**
 * Loads one loan for the player.
 * Laduje pojedynczy kredyt gracza.
 */
    private function getLoan(int $loanId, int $playerId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM loans WHERE id=:id AND player_id=:pid");
            $stmt->execute([':id' => $loanId, ':pid' => $playerId]);
            return $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'getLoan FAILED', $e);
            return null;
        }
    }

 /**
 * Checks for an active pending or approved negotiation.
 * Sprawdza, czy istnieje aktywna negocjacja pending lub approved.
 */
    private function hasPendingOrApproved(int $loanId): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM bank_negotiations
                WHERE loan_id=:lid AND status IN ('pending','approved')
                  AND (expires_at IS NULL OR expires_at > NOW()) LIMIT 1
            ");
            $stmt->execute([':lid' => $loanId]);
            return (bool)$stmt->fetch();
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'hasActivePendingOrApproved FAILED', $e, ['loan_id' => $loanId]);
            return false;
        }
    }

 /**
 * Sends a director notification for negotiation events.
 * Wysyla powiadomienie dyrektora dla zdarzen negocjacyjnych.
 */
    private function notifyDirector(int $playerId, string $tplKey, array $params = []): void
    {
        try {
            (new DirectorNotificationService())->create($playerId, $tplKey, $params, 72);
        } catch (Throwable $e) {
            GameLog::error('BankNeg', 'notifyDirector FAILED', $e);
        }
    }
}
