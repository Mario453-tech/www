<?php
trait WellSellTrait
{
 /**
 * Oblicz wycen sprzeday odwiertu (bez zapisu) do wywietlenia w modalu.
 * Calculate well sale value (without saving) for display in the modal.
 * Zwraca tablic z sell_value, breakdown i ewentualnym bdem.
 * Returns array with sell_value, breakdown and optional error.
 */
    public function calculateSellValue(int $wellId, int $playerId): array
    {
        $well = $this->getWell($wellId, $playerId);
        if (!$well) {
            return ['error' => t('well.err_not_found')];
        }

 // Blokady statusu / Status locks
        $blockedStatuses = ['seized', 'blowout', 'sold'];
        if (in_array($well['status'], $blockedStatuses, true)) {
            return ['error' => t('well.err_sell_blocked_status', ['status' => $well['status']])];
        }

 // Blokada: odwiert musi byc odpienty od huba i pracownikow przed sprzedaza.
 // Block: well must be detached from hub and staff before selling.
        $hubCheck = $this->db->prepare(
            "SELECT COUNT(*) FROM logistics_hub_assignments WHERE well_id = ? AND status = 'active'"
        );
        $hubCheck->execute([$wellId]);
        $staffCheck = $this->db->prepare(
            "SELECT COUNT(*) FROM well_staff_assignments WHERE well_id = ? AND player_id = ? AND unassigned_at IS NULL"
        );
        $staffCheck->execute([$wellId, $playerId]);
        if ((int)$hubCheck->fetchColumn() > 0 || (int)$staffCheck->fetchColumn() > 0) {
            return ['error' => t('well.err_sell_has_hub_or_staff')];
        }

 // Cooldown 2h od created_at / 2h cooldown from created_at
        $createdAt = strtotime($well['created_at'] ?? 'now');
        if ((time() - $createdAt) < 7200) {
            $remaining = 7200 - (time() - $createdAt);
            return ['error' => t('well.err_sell_cooldown', ['min' => ceil($remaining / 60)])];
        }

 // Cena ropy / Oil price
        $oilPrice = 0.0;
        try {
            $market   = new Market();
            $oilPrice = $market->getCurrentPrice();
        } catch (Throwable $e) {}
        if ($oilPrice <= 0) $oilPrice = 50.0; // fallback

 // Profit/h
        $prodPerH   = (float)($well['base_production_per_hour'] ?? 0);
        $upkeepPerH = $this->getOpexPerHour($well);
        $profitH    = $prodPerH * $oilPrice - $upkeepPerH;

        if ($profitH <= 0) {
            return ['error' => t('well.err_sell_unprofitable')];
        }

 // Konfiguracja (z well_config lub defaults) / Configuration (from well_config or defaults)
        $baseDaysMult = $this->cfg('sell_base_days_mult',  1.2);
        $wearDivisor  = $this->cfg('sell_wear_divisor',    120.0);
        $riskDivisor  = $this->cfg('sell_risk_divisor',    300.0);
        $minHours     = $this->cfg('sell_min_hours',       12.0);
        $maxHours     = $this->cfg('sell_max_hours',       36.0);
        $premiumMult  = $this->cfg('sell_eq_premium',      1.1);
        $standardMult = $this->cfg('sell_eq_standard',     1.0);
        $blackMkt     = $this->cfg('sell_eq_black_market', 0.8);

 // Baza / Base value
        $base = $profitH * 24 * $baseDaysMult;

 // Mnoniki / Multipliers
        $condMult = (float)($well['technical_condition'] ?? 100) / 100.0;

        $wearLevel = (float)($well['wear_level'] ?? 0);
        $wearMult  = 1.0 - ($wearLevel / max(1, $wearDivisor));
        $wearMult  = max(0.1, $wearMult);

        $riskScore = (float)($well['risk_score'] ?? 0);
        $riskMult  = 1.0 - ($riskScore / max(1, $riskDivisor));
        $riskMult  = max(0.1, $riskMult);

        $eqTier = $well['equipment_tier'] ?? 'standard';
        $eqMult = match($eqTier) {
            'premium'      => $premiumMult,
            'black_market' => $blackMkt,
            default        => $standardMult,
        };

        $depthM    = (float)($well['depth_m'] ?? 2000);
        $depthMult = $depthM > 3000 ? 1.1 : ($depthM < 2000 ? 0.95 : 1.0);

        $incidentBoost   = (float)($well['post_incident_risk_boost'] ?? 0);
        $incidentPenalty = $incidentBoost > 0 ? 0.8 : 1.0;

 // Finalna warto / Final value
        $sellValue = $base * $condMult * $wearMult * $riskMult * $eqMult * $depthMult * $incidentPenalty;

 // Clamp min/max
        $minValue  = $profitH * $minHours;
        $maxValue  = $profitH * $maxHours;
        $sellValue = max($minValue, min($maxValue, $sellValue));
        $sellValue = round($sellValue, 2);

 // Breakdown (procenty wzgldem bazy) / Breakdown (percentages relative to base)
        $breakdown = [
            'base'          => round($base, 2),
            'condition_pct' => round(($condMult    - 1) * 100, 1),
            'wear_pct'      => round(($wearMult    - 1) * 100, 1),
            'risk_pct'      => round(($riskMult    - 1) * 100, 1),
            'equipment_pct' => round(($eqMult      - 1) * 100, 1),
            'depth_pct'     => round(($depthMult   - 1) * 100, 1),
            'incident_pct'  => $incidentPenalty < 1 ? -20 : 0,
            'profit_h'      => round($profitH, 2),
            'oil_price'     => round($oilPrice, 2),
            'clamped'       => ($sellValue === round($minValue, 2) || $sellValue === round($maxValue, 2)),
        ];

        return [
            'sell_value' => $sellValue,
            'breakdown'  => $breakdown,
            'well'       => $well,
            'error'      => null,
        ];
    }

 /**
 * Wykonaj sprzeda odwiertu zapisuje do DB, aktualizuje balans gracza.
 * Execute well sale saves to DB, updates player balance.
 */
    public function sellWell(int $wellId, int $playerId): array
    {
        try {
            $calc = $this->calculateSellValue($wellId, $playerId);
            if (!empty($calc['error'])) {
                return ['success' => false, 'message' => $calc['error']];
            }

            $sellValue = $calc['sell_value'];
            $well      = $calc['well'];
            $breakdown = $calc['breakdown'];

            $this->db->beginTransaction();

 // 1. Status sold
            $this->db->prepare("UPDATE wells SET status='sold', sold_at=NOW(), operator_id=NULL, technician_id=NULL WHERE id=? AND player_id=?")
                     ->execute([$wellId, $playerId]);

 // Odpisz przypisanych pracownikw bez tego pozostaj "zajci" i nie mona ich przypisa do nowego odwiertu
 // Unassign assigned workers otherwise they remain "busy" and cannot be assigned to a new well
            $this->db->prepare("
                UPDATE well_staff_assignments SET unassigned_at = NOW()
                WHERE well_id = ? AND player_id = ? AND unassigned_at IS NULL
            ")->execute([$wellId, $playerId]);

 // 2. Dodaj kas graczowi / Add cash to player
            $this->db->prepare("UPDATE players SET cash = cash + ? WHERE id=?")
                     ->execute([$sellValue, $playerId]);

 // 3. Zapis do bankruptcy_events (historia transakcji) / Save to bankruptcy_events (transaction history)
            $payload = json_encode([
                'asset_type' => 'well',
                'well_id'    => $wellId,
                'payout'     => $sellValue,
                'reason'     => 'manual_sell',
                'breakdown'  => $breakdown,
            ], JSON_UNESCAPED_UNICODE);
            $this->db->prepare("
                INSERT INTO bankruptcy_events (player_id, event_type, message, severity, is_critical, payload_json, created_at)
                VALUES (?, 'well_sold', ?, 'low', 0, ?, NOW())
            ")->execute([
                $playerId,
                tPlain('well.sell_event_msg', ['id' => $wellId, 'amount' => number_format($sellValue, 0, '.', ' ')]),
                $payload,
            ]);

 // 4. Zapis do admin_logs / Save to admin_logs
            $this->db->prepare("
                INSERT INTO admin_logs (action, description, target_player_id, target_type, target_id, admin_user, admin_ip, created_at)
                VALUES ('well_sold', ?, ?, 'well', ?, 'system', '', NOW())
            ")->execute([
                tPlain('well.sell_admin_log', ['id' => $wellId, 'amount' => number_format($sellValue, 0, '.', ' '), 'profit' => $breakdown['profit_h']]),
                $playerId,
                $wellId,
            ]);

            $this->db->commit();

            GameLog::info('WellService', 'sellWell', [
                'well_id'    => $wellId,
                'player_id'  => $playerId,
                'sell_value' => $sellValue,
                'profit_h'   => $breakdown['profit_h'],
            ]);

            return [
                'success'    => true,
                'sell_value' => $sellValue,
                'breakdown'  => $breakdown,
                'message'    => t('well.msg_sold', ['value' => number_format($sellValue, 0, '.', ' ')]),
            ];

        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            GameLog::error('WellService', 'sellWell FAILED', $e, ['well_id' => $wellId, 'player_id' => $playerId]);
            return ['success' => false, 'message' => t('well.err_generic')];
        }
    }
}
