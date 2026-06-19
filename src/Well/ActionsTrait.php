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
            // $ownTx — zabezpieczenie przed zagniezdzona transakcja (Rule 5).
            // $ownTx — guard against nested transaction (Rule 5).
            $ownTx = !$this->db->inTransaction();
            if ($ownTx) $this->db->beginTransaction();
            try {
                if (!$player->updateCash(-$cost, \FinancialTransactionService::TYPE_WELL_UPGRADE, 'Zmiana tieru wyposazenia odwiertu')) {
                    if ($ownTx) $this->db->rollBack();
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
                    if ($ownTx) $this->db->rollBack();
                    return ['success' => false, 'message' => t('well.err_not_found')];
                }
                $this->logEvent($wellId, $playerId, 'upgrade', $cost,
                    "Equipment tier changed: {$currentTier}  {$tier} (swap until {$swapUntil})");
                if ($ownTx) $this->db->commit();
            } catch (\Throwable $e) {
                if ($ownTx && $this->db->inTransaction()) $this->db->rollBack();
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
            if ($currentLevel >= 3) return ['success' => false, 'message' => t('well.err_max_upgrade')];

            $upgradeCosts = [1 => 1_000_000, 2 => 2_500_000, 3 => 5_000_000];
            $nextLevel    = $currentLevel + 1;
            $cost         = $upgradeCosts[$nextLevel];
            $player       = new Player($playerId);

            // Transakcja obejmuje odjecie cash i UPDATE wells — atomowo lub wcale.
            // Transaction wraps cash deduct and well UPDATE — all-or-nothing.
            // $ownTx — zabezpieczenie przed zagniezdzona transakcja (Rule 5).
            // $ownTx — guard against nested transaction (Rule 5).
            $ownTx = !$this->db->inTransaction();
            if ($ownTx) $this->db->beginTransaction();
            try {
                if (!$player->updateCash(-$cost, \FinancialTransactionService::TYPE_WELL_UPGRADE, 'Ulepszenie poziomu wyposazenia odwiertu')) {
                    if ($ownTx) $this->db->rollBack();
                    return ['success' => false, 'message' => t('well.err_insufficient_funds', ['cost' => $this->fmt($cost)])];
                }
                $stmtUpd = $this->db->prepare("UPDATE wells SET equipment_upgrade_level = ? WHERE id = ? AND player_id = ?");
                $stmtUpd->execute([$nextLevel, $wellId, $playerId]);
                // Sprawdz czy wiersz zostal zaktualizowany — brak wierszy = odwiert zniknal po getWell().
                // Check if any row was updated — 0 rows means the well disappeared after getWell().
                if ($stmtUpd->rowCount() === 0) {
                    if ($ownTx) $this->db->rollBack();
                    return ['success' => false, 'message' => t('well.err_not_found')];
                }
                $this->logEvent($wellId, $playerId, 'upgrade', $cost,
                    "Equipment upgrade: lvl {$currentLevel}  {$nextLevel} ({$currentTier})");
                if ($ownTx) $this->db->commit();
            } catch (\Throwable $e) {
                if ($ownTx && $this->db->inTransaction()) $this->db->rollBack();
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
        $well = $this->getWell($wellId, $playerId);
        if (!$well) return ['success' => false, 'message' => t('well.err_not_found')];

        $cost = $this->getMaintenanceCost($well);
        if ($cost <= 0) return ['success' => false, 'message' => t('well.err_maintenance_not_needed')];

        $player      = new Player($playerId);
        $condBefore  = (int)$well['technical_condition'];
        $condAfter   = min(100, $condBefore + 30);
        $boostBefore = (float)($well['post_disaster_risk_boost'] ?? 0.0);
        // Obliczamy wartosc po odjecie stacka — bez dodatkowego SELECT po commit.
        // Compute new value locally — no extra SELECT after commit needed.
        $boostAfter  = max(0.0, round($boostBefore - 0.10, 10));

        // $ownTx — zabezpieczenie przed zagniezdzona transakcja (Rule 5).
        // $ownTx — guard against nested transaction (Rule 5).
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) $this->db->beginTransaction();
        try {
            // Sprawdzenie salda atomowo wewnatrz transakcji — return false gdy za malo.
            // Atomic balance check inside transaction — returns false when insufficient.
            if (!$player->updateCash(-$cost, \FinancialTransactionService::TYPE_WELL_MAINTENANCE, 'Konserwacja odwiertu')) {
                if ($ownTx) $this->db->rollBack();
                return ['success' => false, 'message' => t('well.err_insufficient_funds', ['cost' => $this->fmt($cost)])];
            }
            $stmtUpd = $this->db->prepare("UPDATE wells SET technical_condition = ? WHERE id = ? AND player_id = ?");
            $stmtUpd->execute([$condAfter, $wellId, $playerId]);
            // Sprawdz czy wiersz zostal zaktualizowany — brak wierszy = odwiert zniknal po getWell().
            // Check if any row was updated — 0 rows means the well disappeared after getWell().
            if ($stmtUpd->rowCount() === 0) {
                if ($ownTx) $this->db->rollBack();
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
            if ($ownTx) $this->db->commit();
        } catch (\Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) $this->db->rollBack();
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
