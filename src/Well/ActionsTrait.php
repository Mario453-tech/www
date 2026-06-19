<?php
trait WellActionsTrait
{
 /**
 * Zmiana tieru sprzetu lub upgrade poziomu.
 * Changes equipment tier or upgrades level.
 * Koszty: black_market=500k, standard=2mln, premium=8mln
 * Costs: black_market=500k, standard=2mln, premium=8mln
 * Upgrade lvl: 1=1mln, 2=2.5mln, 3=5mln
 */
 /** @return array<string, mixed> */
    public function upgradeEquipment(int $wellId, int $playerId, string $action, string $tier = 'standard'): array
    {
        $well = $this->getWell($wellId, $playerId);
        if (!$well) return ['success' => false, 'message' => t('well.err_not_found')];

        $currentTier  = $well['equipment_tier']          ?? 'standard';
        $currentLevel = (int)($well['equipment_upgrade_level'] ?? 0);

        if ($action === 'set_tier') {
            $tierCosts = ['black_market' => 500_000, 'standard' => 2_000_000, 'premium' => 8_000_000];
            if (!isset($tierCosts[$tier])) return ['success' => false, 'message' => t('well.err_invalid_tier')];
            if ($tier === $currentTier)    return ['success' => false, 'message' => t('well.err_tier_already_installed')];

            $cost        = $tierCosts[$tier];
            $player      = new Player($playerId);
            $swapMinutes = 60;
            $swapUntil   = date('Y-m-d H:i:s', time() + $swapMinutes * 60);
            $prevStatus  = $well['status'] ?? 'active';

            // Transakcja obejmuje odjecie cash i UPDATE wells — atomowo lub wcale.
            // Transaction wraps cash deduct and well UPDATE — all-or-nothing.
            $this->db->beginTransaction();
            try {
                if (!$player->updateCash(-$cost, \FinancialTransactionService::TYPE_WELL_UPGRADE, 'Zmiana tieru wyposazenia odwiertu')) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => t('well.err_insufficient_funds', ['cost' => $this->fmt($cost)])];
                }
                $stmtUpd = $this->db->prepare("
                    UPDATE wells
                    SET equipment_tier = ?,
                        equipment_upgrade_level = 0,
                        status = 'equipment_swap',
                        equipment_swap_until = ?,
                        equipment_swap_prev_status = ?
                    WHERE id = ? AND player_id = ?
                ");
                $stmtUpd->execute([$tier, $swapUntil, $prevStatus, $wellId, $playerId]);
                // Sprawdz czy wiersz zostal zaktualizowany — brak wierszy = odwiert zniknal po getWell().
                // Check if any row was updated — 0 rows means the well disappeared after getWell().
                if ($stmtUpd->rowCount() === 0) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => t('well.err_not_found')];
                }
                $this->logEvent($wellId, $playerId, 'upgrade', $cost,
                    "Equipment tier changed: {$currentTier}  {$tier} (swap until {$swapUntil})");
                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollBack();
                GameLog::error('WellService', 'upgradeEquipment set_tier FAILED', $e);
                return ['success' => false, 'message' => t('well.err_generic')];
            }

            GameLog::info('WellService', 'equipment_tier_changed', [
                'well_id' => $wellId, 'from' => $currentTier, 'to' => $tier,
                'cost' => $cost, 'swap_until' => $swapUntil,
            ]);
            return [
                'success'      => true,
                'message'      => t('well.msg_tier_changed', ['tier' => $tier, 'cost' => $this->fmt($cost)]),
                'swap_until'   => $swapUntil,
                'swap_minutes' => $swapMinutes,
            ];

        } elseif ($action === 'upgrade_level') {
            $player = new Player($playerId);

            // Transakcja obejmuje odjecie cash i UPDATE wells — atomowo lub wcale.
            // Transaction wraps cash deduct and well UPDATE — all-or-nothing.
            $this->db->beginTransaction();
            try {
                // SELECT z blokada wierszowa — zapobiega race condition przy rownolegych zadaniach.
                // Row-level lock prevents race condition under concurrent requests.
                $lockStmt = $this->db->prepare("SELECT id, equipment_upgrade_level, status FROM wells WHERE id = ? AND player_id = ? FOR UPDATE LIMIT 1");
                $lockStmt->execute([$wellId, $playerId]);
                $freshWell = $lockStmt->fetch();
                if (!$freshWell) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => t('well.err_not_found')];
                }

                // Odwiert w jednym ze stanow blokujacych upgrade nie moze byc ulepszany.
                // Well in a blocking status cannot be upgraded.
                $blockedStatuses = ['equipment_swap', 'seized', 'sold', 'blowout'];
                if (in_array($freshWell['status'], $blockedStatuses, true)) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => t('well.err_invalid_status')];
                }

                // Swiezy poziom z zablokowanego wiersza — nie z getWell() sprzed transakcji.
                // Fresh level from the locked row — not from getWell() called before the transaction.
                $currentLevel = max(0, (int)$freshWell['equipment_upgrade_level']);
                if ($currentLevel >= 3) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => t('well.err_max_upgrade')];
                }

                $upgradeCosts = [1 => 1_000_000, 2 => 2_500_000, 3 => 5_000_000];
                $nextLevel    = $currentLevel + 1;
                $cost         = $upgradeCosts[$nextLevel];

                if (!$player->updateCash(-$cost, \FinancialTransactionService::TYPE_WELL_UPGRADE, 'Ulepszenie poziomu wyposazenia odwiertu')) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => t('well.err_insufficient_funds', ['cost' => $this->fmt($cost)])];
                }

                // Atomowy INCREMENT z dodatkowym warunkiem na aktualny poziom — gwarancja idempotentnosci.
                // Atomic INCREMENT with current-level guard — guarantees idempotency.
                $stmtUpd = $this->db->prepare("
                    UPDATE wells
                    SET equipment_upgrade_level = equipment_upgrade_level + 1
                    WHERE id = ? AND player_id = ? AND equipment_upgrade_level = ?
                ");
                $stmtUpd->execute([$wellId, $playerId, $currentLevel]);
                // Sprawdz czy wiersz zostal zaktualizowany — 0 wierszy = race condition lub odwiert zniknal.
                // Check if any row was updated — 0 rows means race condition or well disappeared.
                if ($stmtUpd->rowCount() === 0) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => t('well.err_not_found')];
                }
                $this->logEvent($wellId, $playerId, 'upgrade', $cost,
                    "Equipment upgrade: lvl {$currentLevel}  {$nextLevel} ({$currentTier})");
                $this->db->commit();
            } catch (\Throwable $e) {
                $this->db->rollBack();
                GameLog::error('WellService', 'upgradeEquipment upgrade_level FAILED', $e);
                return ['success' => false, 'message' => t('well.err_generic')];
            }

            GameLog::info('WellService', 'equipment_upgraded', [
                'well_id' => $wellId, 'tier' => $currentTier, 'level' => $nextLevel, 'cost' => $cost,
            ]);
            return ['success' => true, 'message' => t('well.msg_upgrade_done', ['level' => $nextLevel, 'cost' => $this->fmt($cost)])];
        }

        return ['success' => false, 'message' => t('well.err_unknown_action')];
    }

 /**
 * Konserwacja odwiertu
 * Well maintenance.
 */
    public function performMaintenance(int $wellId, int $playerId): array
    {
        $player = new Player($playerId);

        $fts3 = new FinancialTransactionService();
        $this->db->beginTransaction();
        try {
            // SELECT z blokada wierszowa — zapobiega race condition przy rownolegych zadaniach.
            // Row-level lock prevents race condition under concurrent requests.
            $lockStmt = $this->db->prepare("SELECT * FROM wells WHERE id = ? AND player_id = ? FOR UPDATE LIMIT 1");
            $lockStmt->execute([$wellId, $playerId]);
            $freshWell = $lockStmt->fetch();
            if (!$freshWell) {
                $this->db->rollBack();
                return ['success' => false, 'message' => t('well.err_not_found')];
            }

            // Koszt liczony ze swiezych danych z zablokowanego wiersza — nie sprzed transakcji.
            // Cost computed from locked row — not from data fetched before the transaction.
            $cost = $this->getMaintenanceCost($freshWell);
            if ($cost <= 0) {
                $this->db->rollBack();
                return ['success' => false, 'message' => t('well.err_maintenance_not_needed')];
            }

            $condBefore  = (int)$freshWell['technical_condition'];
            $condAfter   = min(100, $condBefore + 30);
            $boostBefore = (float)($freshWell['post_disaster_risk_boost'] ?? 0.0);
            // Obliczamy wartosc po odjecie stacka — bez dodatkowego SELECT po commit.
            // Compute new value locally — no extra SELECT after commit needed.
            $boostAfter  = max(0.0, round($boostBefore - 0.10, 10));

            // Sprawdzenie salda atomowo wewnatrz transakcji — return false gdy za malo.
            // Atomic balance check inside transaction — returns false when insufficient.
            if (!$player->updateCash(-$cost, \FinancialTransactionService::TYPE_WELL_MAINTENANCE, 'Konserwacja odwiertu')) {
                $this->db->rollBack();
                return ['success' => false, 'message' => t('well.err_insufficient_funds', ['cost' => $this->fmt($cost)])];
            }
            $stmtUpd = $this->db->prepare("UPDATE wells SET technical_condition = ? WHERE id = ? AND player_id = ?");
            $stmtUpd->execute([$condAfter, $wellId, $playerId]);
            // Sprawdz czy wiersz zostal zaktualizowany — brak wierszy = odwiert zniknal po getWell().
            // Check if any row was updated — 0 rows means the well disappeared after getWell().
            if ($stmtUpd->rowCount() === 0) {
                $this->db->rollBack();
                return ['success' => false, 'message' => t('well.err_not_found')];
            }

            // Konserwacja redukuje spirale katastrof o 1 stack (-0.10) / Maintenance reduces the disaster spiral by 1 stack (-0.10)
            $this->db->prepare("
                UPDATE wells
                SET post_disaster_risk_boost = GREATEST(0, post_disaster_risk_boost - 0.10)
                WHERE id = ? AND player_id = ?
            ")->execute([$wellId, $playerId]);

            $this->logEvent($wellId, $playerId, 'maintenance', $cost,
                "Maintenance - condition: {$condBefore}%  {$condAfter}%", $condBefore, $condAfter);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            GameLog::error('WellService', 'performMaintenance FAILED', $e);
            return ['success' => false, 'message' => t('well.err_generic')];
        }

        return [
            'success' => true,
            'message' => t('well.msg_maintenance_done', ['before' => $condBefore, 'after' => $condAfter, 'cost' => $this->fmt($cost)])
                . ($boostAfter < $boostBefore ? ' ' . t('well.msg_risk_reduced') : ''),
            'cost'             => $cost,
            'condition_before' => $condBefore,
            'condition_after'  => $condAfter,
            'boost_after'      => $boostAfter,
        ];
    }

    private function logEvent(int $wellId, int $playerId, string $type, float $cost,
                               string $desc, ?int $condBefore = null, ?int $condAfter = null): void
    {
        $this->db->prepare("
            INSERT INTO well_events (well_id, player_id, event_type, cost, description,
                technical_condition_before, technical_condition_after)
            VALUES (?,?,?,?,?,?,?)
        ")->execute([$wellId, $playerId, $type, $cost, $desc, $condBefore, $condAfter]);
    }

    private function fmt(float $n): string
    {
        return '$' . number_format($n, 0, '.', ' ');
    }
}
