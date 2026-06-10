<?php

/**
 * Applies bankruptcy recovery options such as sales, cuts and investor actions.
 * PL: Stosuje opcje ratunkowe bankructwa, jak sprzedaz, ciecia i inwestor.
 */
trait BankruptcyOptionsTrait
{
 /**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
    public function applyOption(string $option, array $payload = []): array
    {
        try {
            $state = $this->loadState();
            if (empty($state['is_bankrupt'])) {
                return ['success' => false, 'message' => t('bankruptcy.err_not_bankrupt')];
            }

            $result = match ($option) {
                'sell_asset'      => $this->applySellAsset($payload),
                'bank_takeover'   => $this->applyBankTakeover(),
                'emergency_loan'  => $this->applyEmergencyLoan(),
                'cost_cuts'       => $this->applyCostCuts(),
                'rescue_investor' => $this->applyRescueInvestor(),
                'new_start'       => $this->applyNewStart(),
                default           => ['success' => false, 'message' => t('bankruptcy.err_unknown_option')],
            };

            if (!empty($result['success'])) {
                $this->tickBankruptcyFlow();
                $this->applyLiquidationResetIfNeeded(false);
                $this->tryRecover();
            }

            return $result;
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', 'applyOption failed', $e, ['player_id' => $this->playerId, 'option' => $option]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }

 // Asset sale branch.
 // PL: Galaz sprzedazy aktywow.
 /**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
    private function applySellAsset(array $payload): array
    {
        $assetType = (string)($payload['asset_type'] ?? 'well');
        if ($assetType === 'storage') {
            return $this->applySellStorageAsset();
        }
        return $this->applySellWellAsset((int)($payload['well_id'] ?? 0));
    }

 /** @return array<string, mixed> */
    private function applySellWellAsset(int $wellId): array
    {
        if ($wellId <= 0) {
            return ['success' => false, 'message' => t('bankruptcy.err_select_well')];
        }

        $stmt = $this->db->prepare("SELECT id, level, base_production_per_hour, status FROM wells WHERE id=? AND player_id=? LIMIT 1");
        $stmt->execute([$wellId, $this->playerId]);
        $well = $stmt->fetch();
        if (!$well || (string)$well['status'] === 'seized') {
            return ['success' => false, 'message' => t('bankruptcy.err_well_seized')];
        }

        $baseValue = max(50000, (int)round(((int)$well['level'] * 28000) + ((float)$well['base_production_per_hour'] * 1700)));
        $payout = (int)round($baseValue * ($this->randBetween(60, 80) / 100));

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE wells SET status='seized', marine_buffer_bbl=0 WHERE id=? AND player_id=?")->execute([$wellId, $this->playerId]);
            $this->db->prepare("UPDATE players SET cash = cash + ? WHERE id=?")->execute([$payout, $this->playerId]);
            try {
                if (class_exists('FinancialTransactionService', false)) {
                    (new FinancialTransactionService($this->db))->logTransaction(
                        null, $this->playerId, (float)$payout,
                        FinancialTransactionService::TYPE_BANKRUPTCY_EVENT,
                        'Sprzedaz odwiertu #' . $wellId . ' w ramach restrukturyzacji'
                    );
                }
            } catch (Throwable $le) { /* audit trail failure must not break the operation */ }
            $this->logEvent('sell_asset', t('bankruptcy.log_sell_well', ['id' => $wellId, 'payout' => number_format($payout)]), ['asset_type' => 'well', 'well_id' => $wellId, 'payout' => $payout], 'high', 0, null);
            $this->addNotification(t('bankruptcy.notif_sell_well', ['payout' => number_format($payout)]));
            $this->db->commit();
            return ['success' => true, 'message' => t('bankruptcy.msg_sell_well', ['payout' => number_format($payout)])];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('BankruptcyService', 'applySellWellAsset FAILED', $e, ['player_id' => $this->playerId]);
            throw $e;
        }
    }

    private function applySellStorageAsset(): array
    {
        try {
            $chk = $this->db->prepare("SELECT COUNT(*) FROM bankruptcy_events WHERE player_id=? AND event_type='sell_asset_storage'");
            $chk->execute([$this->playerId]);
            if ((int)$chk->fetchColumn() > 0) {
                return ['success' => false, 'message' => t('bankruptcy.err_storage_already_sold')];
            }
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', 'applySellStorageAsset check FAILED', $e, ['player_id' => $this->playerId]);
        }

        $stmt = $this->db->prepare("SELECT capacity, used FROM storage WHERE player_id=? LIMIT 1");
        $stmt->execute([$this->playerId]);
        $storage = $stmt->fetch();
        if (!$storage) {
            return ['success' => false, 'message' => t('bankruptcy.err_no_storage')];
        }

        $capacity = (int)$storage['capacity'];
        $used = (int)$storage['used'];
        if ($capacity <= 1000) {
            return ['success' => false, 'message' => t('bankruptcy.err_storage_at_min')];
        }

        $sellCap = $capacity - 1000;
        if ($sellCap <= 0) {
            return ['success' => false, 'message' => t('bankruptcy.err_storage_below_min')];
        }

        $newCapacity = 1000;
        $newUsed = min($used, $newCapacity);
        $payout = (int)round(($sellCap * 85) * ($this->randBetween(60, 80) / 100));

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE storage SET capacity=?, used=? WHERE player_id=?")->execute([$newCapacity, $newUsed, $this->playerId]);
            $this->db->prepare("UPDATE players SET cash = cash + ? WHERE id=?")->execute([$payout, $this->playerId]);
            try {
                if (class_exists('FinancialTransactionService', false)) {
                    (new FinancialTransactionService($this->db))->logTransaction(
                        null, $this->playerId, (float)$payout,
                        FinancialTransactionService::TYPE_BANKRUPTCY_EVENT,
                        'Sprzedaz magazynu w ramach restrukturyzacji'
                    );
                }
            } catch (Throwable $le) { /* audit trail failure must not break the operation */ }
            $this->logEvent('sell_asset_storage', t('bankruptcy.log_sell_storage'), ['asset_type' => 'storage', 'capacity_sold' => $sellCap, 'payout' => $payout], 'medium', 0, null);
            $this->addNotification(t('bankruptcy.notif_sell_storage', ['payout' => number_format($payout)]));
            GameLog::info('BankruptcyService', 'applySellStorageAsset OK', ['player_id' => $this->playerId, 'capacity_sold' => $sellCap, 'payout' => $payout]);
            $this->db->commit();
            return ['success' => true, 'message' => t('bankruptcy.msg_sell_storage', ['payout' => number_format($payout)])];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('BankruptcyService', 'applySellStorageAsset FAILED', $e, ['player_id' => $this->playerId]);
            throw $e;
        }
    }

 // Bank takeover branch.
 // PL: Galaz przejecia przez bank.
    private function applyBankTakeover(): array
    {
        $wellStmt = $this->db->prepare("SELECT id, level, base_production_per_hour FROM wells WHERE player_id=? AND status!='seized' ORDER BY level DESC, base_production_per_hour DESC LIMIT 1");
        $wellStmt->execute([$this->playerId]);
        $well = $wellStmt->fetch();
        if (!$well) {
            return ['success' => false, 'message' => t('bankruptcy.err_no_assets_takeover')];
        }

        $loanStmt = $this->db->prepare("SELECT id FROM loans WHERE player_id=? AND status IN ('active','late') ORDER BY remaining_amount DESC LIMIT 1");
        $loanStmt->execute([$this->playerId]);
        $loanId = (int)$loanStmt->fetchColumn();
        if ($loanId <= 0) {
            return ['success' => false, 'message' => t('bankruptcy.err_no_active_loan')];
        }

        $debtReduction = (int)round(max(80000, ((int)$well['level'] * 32000) + ((float)$well['base_production_per_hour'] * 2100)) * 0.85);

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE wells SET status='seized', marine_buffer_bbl=0 WHERE id=? AND player_id=?")->execute([$well['id'], $this->playerId]);
 // Uwaga: MySQL ewaluuje SET left-to-right, wiec w CASE remaining_amount jest juz zaktualizowane.
 // Note: MySQL evaluates SET left-to-right, so in the CASE remaining_amount is already updated.
 // Sprawdzamy remaining_amount <= 0 (nowa wartosc po GREATEST), nie odejmujemy ponownie debtReduction.
 // We check remaining_amount <= 0 (the new value after GREATEST), not subtract debtReduction again.
            $this->db->prepare("UPDATE loans SET remaining_amount = GREATEST(0, remaining_amount - ?), status = CASE WHEN remaining_amount <= 0 THEN 'paid_off' ELSE status END WHERE id=?")
                ->execute([$debtReduction, $loanId]);
            $this->logEvent('bank_takeover', t('bankruptcy.log_bank_takeover'), ['well_id' => (int)$well['id'], 'loan_id' => $loanId, 'debt_reduction' => $debtReduction], 'high', 0, null);
            $this->addNotification(t('bankruptcy.notif_bank_takeover', ['amount' => number_format($debtReduction)]));
            $this->db->commit();
            return ['success' => true, 'message' => t('bankruptcy.msg_bank_takeover', ['amount' => number_format($debtReduction)])];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('BankruptcyService', 'applyBankTakeover FAILED', $e, ['player_id' => $this->playerId]);
            throw $e;
        }
    }

 // Emergency loan branch.
 // PL: Galaz pozyczki ratunkowej.
    private function applyEmergencyLoan(): array
    {
        $state = $this->loadState();
        if ((int)($state['player']['credit_score'] ?? 0) < 30) {
            return ['success' => false, 'message' => t('bankruptcy.err_loan_low_score')];
        }

        $checkStmt = $this->db->prepare("SELECT id FROM loans WHERE player_id=? AND status IN ('active','late') AND interest_rate >= 15 LIMIT 1");
        $checkStmt->execute([$this->playerId]);
        if ($checkStmt->fetch()) {
            return ['success' => false, 'message' => t('bankruptcy.err_loan_already_active')];
        }

        $wells = (int)($state['wells']['wells_non_seized'] ?? 0);
        $amount = 180000 + max(0, $wells * 40000);
        $rate = $this->randBetween(15, 25);
        $installment = (float)round(($amount * (1 + ($rate / 100) * 0.6)) / 16, 2);

        $this->db->beginTransaction();
        try {
            $this->db->prepare("INSERT INTO loans (player_id, application_id, principal_amount, remaining_amount, interest_rate, installment_amount, installment_frequency, next_installment_at, status, created_at) VALUES (?, NULL, ?, ?, ?, ?, 12, DATE_ADD(NOW(), INTERVAL 12 HOUR), 'active', NOW())")
                ->execute([$this->playerId, $amount, $amount, $rate, $installment]);
            $loanId = (int)$this->db->lastInsertId();
            $this->db->prepare("UPDATE players SET cash = cash + ?, credit_score = GREATEST(0, credit_score - 50), recovery_mode=1, bankruptcy_status='restructuring' WHERE id=?")
                ->execute([$amount, $this->playerId]);
            try {
                if (class_exists('FinancialTransactionService', false)) {
                    (new FinancialTransactionService($this->db))->logTransaction(
                        null, $this->playerId, (float)$amount,
                        FinancialTransactionService::TYPE_LOAN,
                        'Wyplata kredytu ratunkowego (restrukturyzacja)'
                    );
                }
            } catch (Throwable $le) { /* audit trail failure must not break the operation */ }
            $this->logEvent('emergency_loan', t('bankruptcy.log_emergency_loan'), ['loan_id' => $loanId, 'amount' => $amount, 'interest_rate' => $rate], 'high', 0, null);
            $this->addNotification(t('bankruptcy.notif_emergency_loan', ['amount' => number_format($amount), 'rate' => $rate]));
            $this->db->commit();
            return ['success' => true, 'message' => t('bankruptcy.msg_emergency_loan', ['amount' => number_format($amount)])];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('BankruptcyService', 'applyEmergencyLoan FAILED', $e, ['player_id' => $this->playerId]);
            throw $e;
        }
    }

 // Cost-cutting branch.
 // PL: Galaz ciec kosztow.
    private function applyCostCuts(): array
    {
        $this->db->beginTransaction();
        try {
            $wStmt = $this->db->prepare("UPDATE wells SET status='paused_cash' WHERE player_id=? AND status='active'");
            $wStmt->execute([$this->playerId]);
            $paused = (int)$wStmt->rowCount();

            $techStmt = $this->db->prepare("UPDATE technical_staff SET status='fired', fired_at=NOW() WHERE player_id=? AND status IN ('active','busy','on_leave')");
            $techStmt->execute([$this->playerId]);
            $firedTech = (int)$techStmt->rowCount();

            $boardStmt = $this->db->prepare("UPDATE board_members bm JOIN board_roles br ON br.id=bm.role_id SET bm.status='suspended' WHERE bm.status='active' AND br.code!='hr'");
            $boardStmt->execute();
            $suspendedBoard = (int)$boardStmt->rowCount();

            if ($suspendedBoard > 0) {
                $this->db->prepare("UPDATE employee_contracts ec JOIN board_members bm ON bm.id=ec.member_id JOIN board_roles br ON br.id=bm.role_id SET ec.status='terminated' WHERE bm.status='suspended' AND br.code!='hr' AND ec.status='active'")->execute();
            }

            $relief = min(200000, 40000 + ($paused * 12000) + ($firedTech * 8000) + ($suspendedBoard * 15000));

            $this->db->prepare("UPDATE players SET cash=cash+?, credit_score=GREATEST(0,credit_score-1), recovery_mode=1, bankruptcy_status='restructuring' WHERE id=?")->execute([$relief, $this->playerId]);
            try {
                if (class_exists('FinancialTransactionService', false)) {
                    (new FinancialTransactionService($this->db))->logTransaction(
                        null, $this->playerId, (float)$relief,
                        FinancialTransactionService::TYPE_BANKRUPTCY_EVENT,
                        'Ulga gotowkowa - ciecie kosztow (restrukturyzacja)'
                    );
                }
            } catch (Throwable $le) { /* audit trail failure must not break the operation */ }

            $this->logEvent('cost_cuts', t('bankruptcy.log_cost_cuts'), [
                'paused_wells' => $paused,
                'fired_tech' => $firedTech,
                'suspended_board' => $suspendedBoard,
                'cash_relief' => $relief,
            ], 'medium', 0, null);

            $message = t('bankruptcy.msg_cost_cuts', [
                'wells' => $paused,
                'tech' => $firedTech,
                'board' => $suspendedBoard,
                'relief' => number_format($relief),
            ]);
            $this->addNotification($message);
            GameLog::info('BankruptcyService', 'applyCostCuts OK', ['player_id' => $this->playerId, 'relief' => $relief]);
            $this->db->commit();
            return ['success' => true, 'message' => $message];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('BankruptcyService', 'applyCostCuts FAILED', $e, ['player_id' => $this->playerId]);
            throw $e;
        }
    }

 // Rescue investor branch.
 // PL: Galaz inwestora ratunkowego.
    private function applyRescueInvestor(): array
    {
        try {
            $chk = $this->db->prepare("SELECT COUNT(*) FROM bankruptcy_events WHERE player_id=? AND event_type='rescue_investor'");
            $chk->execute([$this->playerId]);
            if ((int)$chk->fetchColumn() > 0) {
                return ['success' => false, 'message' => t('bankruptcy.err_investor_already_used')];
            }
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', 'applyRescueInvestor check FAILED', $e, ['player_id' => $this->playerId]);
        }

        $state = $this->loadState();
        $debtActive = (float)($state['loans']['debt_active'] ?? 0);
        if ($debtActive <= 0) {
            return ['success' => false, 'message' => t('bankruptcy.err_investor_no_debt')];
        }

        $equity = $this->randBetween(35, 50);
        $debtCoverage = $this->randBetween(40, 60) / 100;
        $cashInjection = (int)round($debtActive * $debtCoverage);
        $operationalFee = (int)round($debtActive * 0.15);
        $totalCash = $cashInjection + $operationalFee;
        $directDebtRepay = (int)round($cashInjection * 0.6);
        $cashToPlayer = max((int)round($debtActive * 0.05), $totalCash - $directDebtRepay);

        $this->db->beginTransaction();
        try {
            if ($directDebtRepay > 0) {
                $lateLoans = $this->db->prepare("SELECT id, remaining_amount FROM loans WHERE player_id=? AND status='late' ORDER BY remaining_amount DESC");
                $lateLoans->execute([$this->playerId]);
                $remaining = $directDebtRepay;
                foreach ($lateLoans->fetchAll() as $loan) {
                    if ($remaining <= 0) {
                        break;
                    }
                    $repay = min($remaining, (float)$loan['remaining_amount']);
 // Ten sam wzorzec co bank_takeover: CASE sprawdza juz zaktualizowane remaining_amount <= 0.
 // Same pattern as bank_takeover: CASE checks the already-updated remaining_amount <= 0.
                    $this->db->prepare("UPDATE loans SET remaining_amount=GREATEST(0,remaining_amount-?), status=CASE WHEN remaining_amount<=0 THEN 'paid_off' ELSE 'active' END, late_since=NULL WHERE id=?")->execute([$repay, $loan['id']]);
                    $remaining -= $repay;
                }
            }

            $this->db->prepare("UPDATE players SET cash=cash+?, credit_score=GREATEST(0,credit_score-4), recovery_mode=1, bankruptcy_status='restructuring' WHERE id=?")->execute([$cashToPlayer, $this->playerId]);
            try {
                if (class_exists('FinancialTransactionService', false)) {
                    (new FinancialTransactionService($this->db))->logTransaction(
                        null, $this->playerId, (float)$cashToPlayer,
                        FinancialTransactionService::TYPE_BANKRUPTCY_EVENT,
                        'Inwestor ratunkowy - zastrzyk gotowki (restrukturyzacja)'
                    );
                }
            } catch (Throwable $le) { /* audit trail failure must not break the operation */ }

            $this->logEvent('rescue_investor', t('bankruptcy.log_rescue_investor'), [
                'cash_injection' => $totalCash,
                'direct_debt_repay' => $directDebtRepay,
                'cash_to_player' => $cashToPlayer,
                'equity_percent' => $equity,
            ], 'high', 0, null);

            $message = t('bankruptcy.msg_rescue_investor', [
                'debt' => number_format($directDebtRepay),
                'cash' => number_format($cashToPlayer),
                'equity' => $equity,
            ]);
            $this->addNotification($message);
            GameLog::info('BankruptcyService', 'applyRescueInvestor OK', ['player_id' => $this->playerId, 'direct_debt_repay' => $directDebtRepay, 'cash_to_player' => $cashToPlayer]);
            $this->db->commit();
            return ['success' => true, 'message' => $message];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('BankruptcyService', 'applyRescueInvestor FAILED', $e, ['player_id' => $this->playerId]);
            throw $e;
        }
    }

 // New-start branch.
 // PL: Galaz nowego startu.
    private function applyNewStart(): array
    {
        $ok = $this->applyLiquidationResetIfNeeded(true);
        return $ok
            ? ['success' => true, 'message' => t('bankruptcy.msg_new_start_ok')]
            : ['success' => false, 'message' => t('bankruptcy.err_new_start_failed')];
    }
}
