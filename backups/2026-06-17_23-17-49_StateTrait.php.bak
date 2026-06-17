<?php

/**
 * Reads bankruptcy state and recovery options.
 * PL: Odczytuje stan bankructwa i opcje ratunkowe.
 */
trait BankruptcyStateTrait
{
 /** @return array<string, mixed> */
    public function getState(): array
    {
        try {
            $this->ensureRecoveryMode();
            $state = $this->loadState();
            if (empty($state['exists'])) {
                return ['exists' => false, 'is_bankrupt' => false, 'events' => [], 'critical_open' => 0];
            }
            if (!empty($state['is_bankrupt'])) {
                $this->tickBankruptcyFlow();
                $state = $this->loadState();
            }
            $state['events'] = $this->getEvents(15);
            $state['critical_open'] = $this->countOpenCriticalEvents();
            return $state;
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', 'getState failed', $e, ['player_id' => $this->playerId]);
            return ['exists' => false, 'is_bankrupt' => false, 'events' => [], 'critical_open' => 0];
        }
    }

    public function ensureRecoveryMode(): void
    {
        try {
            $stmt = $this->db->prepare("SELECT status, COALESCE(recovery_mode,0) AS recovery_mode, COALESCE(bankruptcy_status,'none') AS bankruptcy_status FROM players WHERE id = ? LIMIT 1");
            $stmt->execute([$this->playerId]);
            $row = $stmt->fetch();
            if (!$row) {
                return;
            }

            $isBankrupt = ((string)$row['status'] === 'bankrupt')
                || (int)$row['recovery_mode'] === 1
                || !in_array((string)$row['bankruptcy_status'], ['none', 'recovered'], true);

            if ($isBankrupt && (int)$row['recovery_mode'] !== 1) {
                $this->db->prepare("UPDATE players SET recovery_mode = 1, bankruptcy_status = IF(bankruptcy_status='none','restructuring',bankruptcy_status) WHERE id = ?")
                    ->execute([$this->playerId]);
            }
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', 'ensureRecoveryMode failed', $e, ['player_id' => $this->playerId]);
        }
    }

 /** @return array<string, array<string, mixed>> */
    public function getRecoveryOptions(): array
    {
        try {
            $state = $this->getState();
            if (empty($state['is_bankrupt'])) {
                return [];
            }

            $wellStmt = $this->db->prepare("SELECT id, level, base_production_per_hour, status FROM wells WHERE player_id = ? AND status != 'seized' ORDER BY level DESC, base_production_per_hour DESC");
            $wellStmt->execute([$this->playerId]);
            $wells = $wellStmt->fetchAll() ?: [];

            $stStmt = $this->db->prepare("SELECT capacity FROM storage WHERE player_id = ? LIMIT 1");
            $stStmt->execute([$this->playerId]);
            $storageCap = (int)$stStmt->fetchColumn();

            $usedStmt = $this->db->prepare("
                SELECT event_type, COUNT(*) as cnt
                FROM bankruptcy_events
                WHERE player_id = ?
                  AND event_type IN ('rescue_investor', 'sell_asset_storage')
                GROUP BY event_type
            ");
            $usedStmt->execute([$this->playerId]);
            $usedEvents = [];
            foreach ($usedStmt->fetchAll() as $row) {
                $usedEvents[$row['event_type']] = (int)$row['cnt'];
            }

            $investorUsed = ($usedEvents['rescue_investor'] ?? 0) > 0;
            $storageSoldAlready = ($usedEvents['sell_asset_storage'] ?? 0) > 0;
            $debtActive = (float)($state['loans']['debt_active'] ?? 0);

            return [
                'sell_asset' => [
                    'enabled' => !empty($wells) || ($storageCap > 1000 && !$storageSoldAlready),
                    'sellable_wells' => $wells,
                    'can_sell_storage' => ($storageCap > 1000 && !$storageSoldAlready),
                    'storage_sold' => $storageSoldAlready,
                ],
                'bank_takeover' => ['enabled' => !empty($wells) && $debtActive > 0],
                'emergency_loan' => ['enabled' => (int)($state['player']['credit_score'] ?? 0) >= 30, 'apr_range' => '15-25%'],
                'cost_cuts' => ['enabled' => (int)($state['wells']['wells_active'] ?? 0) > 0],
                'rescue_investor' => [
                    'enabled' => !$investorUsed && $debtActive > 0,
                    'already_used' => $investorUsed,
                    'debt_active' => $debtActive,
                    'est_injection' => $investorUsed ? 0 : (int)round($debtActive * 0.50 * 0.40 + $debtActive * 0.15),
                ],
                'new_start' => ['enabled' => true],
            ];
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', 'getRecoveryOptions failed', $e, ['player_id' => $this->playerId]);
            return [];
        }
    }

    public function tryRecover(): bool
    {
        try {
            $state = $this->loadState();
            if (empty($state['is_bankrupt'])) {
                return true;
            }

            $cash = (float)($state['player']['cash'] ?? 0);
            $lateDebt = (float)($state['loans']['debt_late'] ?? 0);
            $activeWells = (int)($state['wells']['wells_active'] ?? 0);
            $openCritical = $this->countOpenCriticalEvents();

            if ($cash >= 120000 && $lateDebt <= 0 && $activeWells >= 1 && $openCritical === 0) {
                $this->db->prepare("
                    UPDATE players
                    SET status            = 'active',
                        bankruptcy_at     = NULL,
                        recovery_mode     = 0,
                        bankruptcy_status = 'recovered',
                        credit_score      = GREATEST(80, LEAST(150, credit_score + 30))
                    WHERE id = ?
                ")->execute([$this->playerId]);

                $this->db->prepare("DELETE FROM wells WHERE player_id=? AND status='seized'")->execute([$this->playerId]);
                $this->db->prepare("UPDATE bailiff_proceedings SET status='completed' WHERE player_id=? AND status='active'")->execute([$this->playerId]);

                $this->logEvent('recovered', t('bankruptcy.log_recovered'), ['cash' => $cash, 'active_wells' => $activeWells], 'high', 0, null);
                $this->addNotification(t('bankruptcy.notif_recovered'));
                return true;
            }

            return false;
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', 'tryRecover failed', $e, ['player_id' => $this->playerId]);
            return false;
        }
    }

    private function loadState(): array
    {
        $pStmt = $this->db->prepare("SELECT id, username, cash, status, bankruptcy_at, COALESCE(recovery_mode,0) AS recovery_mode, COALESCE(bankruptcy_status,'none') AS bankruptcy_status, COALESCE(credit_score,600) AS credit_score FROM players WHERE id = ? LIMIT 1");
        $pStmt->execute([$this->playerId]);
        $player = $pStmt->fetch();
        if (!$player) {
            return ['exists' => false, 'is_bankrupt' => false];
        }

        $lStmt = $this->db->prepare("SELECT COALESCE(SUM(CASE WHEN status IN ('active','late') THEN remaining_amount ELSE 0 END),0) AS debt_active, COALESCE(SUM(CASE WHEN status='late' THEN remaining_amount ELSE 0 END),0) AS debt_late, COUNT(CASE WHEN status IN ('active','late') THEN 1 END) AS loans_count FROM loans WHERE player_id=?");
        $lStmt->execute([$this->playerId]);
        $loans = $lStmt->fetch() ?: ['debt_active' => 0, 'debt_late' => 0, 'loans_count' => 0];

        $wStmt = $this->db->prepare("SELECT COUNT(*) AS wells_total, COUNT(CASE WHEN status!='seized' THEN 1 END) AS wells_non_seized, COUNT(CASE WHEN status='active' THEN 1 END) AS wells_active FROM wells WHERE player_id=?");
        $wStmt->execute([$this->playerId]);
        $wells = $wStmt->fetch() ?: ['wells_total' => 0, 'wells_non_seized' => 0, 'wells_active' => 0];

        $sStmt = $this->db->prepare("SELECT capacity, used FROM storage WHERE player_id=? LIMIT 1");
        $sStmt->execute([$this->playerId]);
        $storage = $sStmt->fetch() ?: ['capacity' => 0, 'used' => 0];

        $isBankrupt = ((string)$player['status'] === 'bankrupt')
            || (int)$player['recovery_mode'] === 1
            || !in_array((string)$player['bankruptcy_status'], ['none', 'recovered'], true);

        return [
            'exists' => true,
            'player' => $player,
            'loans' => $loans,
            'wells' => $wells,
            'storage' => $storage,
            'is_bankrupt' => $isBankrupt,
        ];
    }

    private function getEvents(int $limit): array
    {
        try {
            $limit = max(1, min(50, $limit));
            $stmt = $this->db->prepare("SELECT id, event_type, message, severity, is_critical, due_at, resolved_at, resolution_note, created_at FROM bankruptcy_events WHERE player_id=? ORDER BY id DESC LIMIT {$limit}");
            $stmt->execute([$this->playerId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', 'getEvents failed', $e, ['player_id' => $this->playerId]);
            return [];
        }
    }

    private function countOpenCriticalEvents(): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM bankruptcy_events WHERE player_id=? AND is_critical=1 AND resolved_at IS NULL");
            $stmt->execute([$this->playerId]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            GameLog::error('BankruptcyService', 'countOpenCriticalEvents failed', $e, ['player_id' => $this->playerId]);
            return 0;
        }
    }
}
