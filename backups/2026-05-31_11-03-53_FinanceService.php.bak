<?php
declare(strict_types=1);

class FinanceService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        try {
            Database::addColumnIfMissing('finance_logs', 'produced_bbl', 'DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER oil_price');
            Database::addColumnIfMissing('finance_logs', 'delivered_bbl', 'DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER produced_bbl');
            Database::addColumnIfMissing('finance_logs', 'pre_storage_loss_bbl', 'DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER loss_bbl');
            Database::addColumnIfMissing('finance_logs', 'transport_loss_bbl', 'DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER pre_storage_loss_bbl');
            Database::addColumnIfMissing('finance_logs', 'transport_event_loss_bbl', 'DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER transport_loss_bbl');
            Database::addColumnIfMissing('finance_logs', 'hub_usage_cost', 'DECIMAL(16,2) NOT NULL DEFAULT 0.00 AFTER transport_cost');
            Database::addColumnIfMissing('finance_logs', 'hub_loss_bbl', 'DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER loss_bbl');
            Database::addColumnIfMissing('finance_logs', 'hub_loss_value', 'DECIMAL(16,2) NOT NULL DEFAULT 0.00 AFTER loss_value');
            Database::addColumnIfMissing('finance_logs', 'fallback_loss_bbl', 'DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER hub_loss_bbl');
            Database::addColumnIfMissing('finance_logs', 'fallback_loss_value', 'DECIMAL(16,2) NOT NULL DEFAULT 0.00 AFTER hub_loss_value');
            Database::addColumnIfMissing('finance_logs', 'hub_incident_loss_bbl', 'DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER fallback_loss_bbl');
            Database::addColumnIfMissing('finance_logs', 'hub_incident_loss_value', 'DECIMAL(16,2) NOT NULL DEFAULT 0.00 AFTER fallback_loss_value');
            $this->db->exec("UPDATE finance_logs SET delivered_bbl = bbl_produced WHERE delivered_bbl = 0 AND bbl_produced > 0");
            $this->db->exec("UPDATE finance_logs SET produced_bbl = delivered_bbl WHERE produced_bbl = 0 AND delivered_bbl > 0");
        } catch (Throwable $e) {
            GameLog::error('FinanceService', 'ensureSchema FAILED', $e);
        }
    }

    public function saveTick(
        int $playerId,
        string $tickAt,
        float $revenue,
        float $grossRevenue,
        float $opex,
        float $salaryCost,
        float $transportCost,
        float $incidentCost,
        float $tax,
        float $lossBbl,
        float $lossValue,
        float $cashAfter,
        float $oilPrice,
        float $bblDeliveredLegacy,
        int $wellsActive,
        float $hubUsageCost = 0.0,
        float $hubLossBbl = 0.0,
        float $hubLossValue = 0.0,
        float $fallbackLossBbl = 0.0,
        float $fallbackLossValue = 0.0,
        float $hubIncidentLossBbl = 0.0,
        float $hubIncidentLossValue = 0.0,
        float $producedBbl = 0.0,
        float $deliveredBbl = 0.0,
        float $preStorageLossBbl = 0.0,
        float $transportLossBbl = 0.0,
        float $transportEventLossBbl = 0.0
    ): void {
        $netProfit = $revenue - ($opex + $salaryCost + $transportCost + $incidentCost + $tax);
        $deliveredBbl = $deliveredBbl > 0 ? $deliveredBbl : $bblDeliveredLegacy;
        $producedBbl = $producedBbl > 0 ? $producedBbl : $deliveredBbl;

        try {
            $this->db->prepare(
                "
                INSERT INTO finance_logs
                    (player_id, tick_at, revenue, gross_revenue, opex, salary_cost,
                     transport_cost, hub_usage_cost, incident_cost, tax, loss_bbl, pre_storage_loss_bbl,
                     transport_loss_bbl, transport_event_loss_bbl, hub_loss_bbl,
                     fallback_loss_bbl, hub_incident_loss_bbl, loss_value, hub_loss_value,
                     fallback_loss_value, hub_incident_loss_value, net_profit, cash_after,
                     oil_price, produced_bbl, delivered_bbl, bbl_produced, wells_active)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                "
            )->execute([
                $playerId,
                $tickAt,
                round($revenue, 2),
                round($grossRevenue, 2),
                round($opex, 2),
                round($salaryCost, 2),
                round($transportCost, 2),
                round($hubUsageCost, 2),
                round($incidentCost, 2),
                round($tax, 2),
                round($lossBbl, 4),
                round($preStorageLossBbl, 4),
                round($transportLossBbl, 4),
                round($transportEventLossBbl, 4),
                round($hubLossBbl, 4),
                round($fallbackLossBbl, 4),
                round($hubIncidentLossBbl, 4),
                round($lossValue, 2),
                round($hubLossValue, 2),
                round($fallbackLossValue, 2),
                round($hubIncidentLossValue, 2),
                round($netProfit, 2),
                round($cashAfter, 2),
                round($oilPrice, 2),
                round($producedBbl, 4),
                round($deliveredBbl, 4),
                round($deliveredBbl, 4),
                $wellsActive,
            ]);
        } catch (Throwable $e) {
            GameLog::error('FinanceService', 'saveTick FAILED', $e, ['player_id' => $playerId]);
        }
    }

 /**
 * @return array<string, mixed>|null
 */
    public function getLastTick(int $playerId): ?array
    {
        $stmt = $this->db->prepare(
            "
            SELECT *
            FROM finance_logs
            WHERE player_id = ?
            ORDER BY tick_at DESC
            LIMIT 1
            "
        );
        $stmt->execute([$playerId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

 /**
 * @return array<string, float>
 */
    public function getSummary(int $playerId, int $hours = 24): array
    {
        $stmt = $this->db->prepare(
            "
            SELECT
                SUM(revenue)                 AS total_revenue,
                SUM(gross_revenue)           AS total_gross,
                SUM(opex)                    AS total_opex,
                SUM(salary_cost)             AS total_salary,
                SUM(transport_cost)          AS total_transport,
                SUM(hub_usage_cost)          AS total_hub_usage_cost,
                SUM(incident_cost)           AS total_incident,
                SUM(tax)                     AS total_tax,
                SUM(loss_bbl)                AS total_loss_bbl,
                SUM(pre_storage_loss_bbl)    AS total_pre_storage_loss_bbl,
                SUM(transport_loss_bbl)      AS total_transport_loss_bbl,
                SUM(transport_event_loss_bbl) AS total_transport_event_loss_bbl,
                SUM(loss_value)              AS total_loss_value,
                SUM(hub_loss_bbl)            AS total_hub_loss_bbl,
                SUM(hub_loss_value)          AS total_hub_loss_value,
                SUM(fallback_loss_bbl)       AS total_fallback_loss_bbl,
                SUM(fallback_loss_value)     AS total_fallback_loss_value,
                SUM(hub_incident_loss_bbl)   AS total_hub_incident_loss_bbl,
                SUM(hub_incident_loss_value) AS total_hub_incident_loss_value,
                SUM(net_profit)              AS total_net,
                SUM(CASE WHEN delivered_bbl > 0 THEN delivered_bbl ELSE bbl_produced END) AS total_bbl,
                SUM(CASE WHEN produced_bbl > 0 THEN produced_bbl ELSE bbl_produced END) AS total_produced_bbl,
                AVG(oil_price)               AS avg_price,
                COUNT(*)                     AS tick_count
            FROM finance_logs
            WHERE player_id = ?
              AND tick_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            "
        );
        $stmt->execute([$playerId, $hours]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return array_map(
            static fn($value): float => (float)($value ?? 0),
            $row
        );
    }

 /**
 * @return list<array<string, mixed>>
 */
    public function getHistory(int $playerId, int $hours = 24): array
    {
        $stmt = $this->db->prepare(
            "
            SELECT
                tick_at,
                revenue,
                net_profit,
                opex + salary_cost + transport_cost + hub_usage_cost + incident_cost + tax AS total_cost,
                loss_value,
                oil_price,
                bbl_produced,
                produced_bbl,
                delivered_bbl,
                pre_storage_loss_bbl,
                transport_loss_bbl,
                transport_event_loss_bbl
            FROM finance_logs
            WHERE player_id = ?
              AND tick_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY tick_at ASC
            "
        );
        $stmt->execute([$playerId, $hours]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

 /**
 * @return list<array<string, mixed>>
 */
    public function getPerWellStats(int $playerId): array
    {
        try {
            $stmt = $this->db->prepare(
                "
                SELECT
                    w.id,
                    w.name,
                    w.well_name,
                    w.status,
                    w.base_production_per_hour,
                    w.upkeep_cost_per_hour,
                    w.transport_type,
                    w.transport_opex_pct,
                    w.technical_condition,
                    w.wear_level,
                    w.regional_tax_rate,
                    w.well_type,
                    w.location_name
                FROM wells w
                WHERE w.player_id = ?
                  AND w.status NOT IN ('seized', 'blowout')
                ORDER BY w.status ASC, w.id ASC
                "
            );
            $stmt->execute([$playerId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            GameLog::error('FinanceService', 'getPerWellStats FAILED', $e, ['player_id' => $playerId]);
            return [];
        }
    }

 /**
 * @param array<string, mixed> $last
 * @param array<string, float> $summary24h
 * @return list<array{level:string, icon:string, msg:string}>
 */
    public function getAlerts(int $playerId, array $last, array $summary24h): array
    {
        $alerts = [];

        if (!empty($last) && (float)($last['net_profit'] ?? 0) < 0) {
            $alerts[] = [
                'level' => 'danger',
                'icon'  => '&#128308;',
                'msg'   => t('finance.alert_net_loss', ['amount' => $this->fmt((float)$last['net_profit'])]),
            ];
        }

        $totalRevenue         = (float)($summary24h['total_revenue'] ?? 0);
        $totalLoss            = (float)($summary24h['total_loss_value'] ?? 0);
        $totalHubLoss         = (float)($summary24h['total_hub_loss_value'] ?? 0);
        $totalFallbackLoss    = (float)($summary24h['total_fallback_loss_value'] ?? 0);
        $totalHubIncidentLoss = (float)($summary24h['total_hub_incident_loss_value'] ?? 0);
        $totalHubUsageCost    = (float)($summary24h['total_hub_usage_cost'] ?? 0);

        if ($totalRevenue > 0 && ($totalLoss / $totalRevenue) > 0.15) {
            $alerts[] = [
                'level' => 'warning',
                'icon'  => '&#9888;',
                'msg'   => t('finance.alert_transport_loss', [
                    'pct'    => round($totalLoss / $totalRevenue * 100, 1),
                    'amount' => $this->fmt($totalLoss),
                ]),
            ];
        }

        if ($totalRevenue > 0 && ($totalHubLoss / $totalRevenue) > 0.08) {
            $alerts[] = [
                'level' => 'warning',
                'icon'  => '&#128230;',
                'msg'   => t('finance.alert_hub_loss', [
                    'pct'    => round($totalHubLoss / $totalRevenue * 100, 1),
                    'amount' => $this->fmt($totalHubLoss),
                ]),
            ];
        }

        if ($totalFallbackLoss > 0) {
            $alerts[] = [
                'level' => 'info',
                'icon'  => '&#128668;',
                'msg'   => t('finance.alert_fallback_loss', [
                    'amount' => $this->fmt($totalFallbackLoss),
                    'bbl'    => number_format((float)($summary24h['total_fallback_loss_bbl'] ?? 0), 1, ',', ' '),
                ]),
            ];
        }

        if ($totalRevenue > 0 && ($totalHubUsageCost / $totalRevenue) > 0.10) {
            $alerts[] = [
                'level' => 'info',
                'icon'  => '&#128176;',
                'msg'   => t('finance.alert_hub_cost', [
                    'pct'    => round($totalHubUsageCost / $totalRevenue * 100, 1),
                    'amount' => $this->fmt($totalHubUsageCost),
                ]),
            ];
        }

        if ($totalHubIncidentLoss > 0) {
            $alerts[] = [
                'level' => 'warning',
                'icon'  => '&#128167;',
                'msg'   => t('finance.alert_hub_incident_loss', [
                    'amount' => $this->fmt($totalHubIncidentLoss),
                    'bbl'    => number_format((float)($summary24h['total_hub_incident_loss_bbl'] ?? 0), 1, ',', ' '),
                ]),
            ];
        }

        if (!empty($last) && (float)($last['bbl_produced'] ?? 0) === 0.0 && (int)($last['wells_active'] ?? 0) > 0) {
            $alerts[] = [
                'level' => 'warning',
                'icon'  => '&#9888;',
                'msg'   => t('finance.alert_no_production'),
            ];
        }

        $totalTax = (float)($summary24h['total_tax'] ?? 0);
        if ($totalRevenue > 0 && ($totalTax / $totalRevenue) > 0.20) {
            $alerts[] = [
                'level' => 'info',
                'icon'  => '&#127963;',
                'msg'   => t('finance.alert_high_tax', ['pct' => round($totalTax / $totalRevenue * 100, 1)]),
            ];
        }

        return $alerts;
    }

 /**
 * @return array<string, mixed>
 */
    public function getGlobalStats(int $hours = 24): array
    {
        $stmt = $this->db->prepare(
            "
            SELECT
                COUNT(DISTINCT player_id)                             AS player_count,
                SUM(revenue)                                          AS total_revenue,
                SUM(opex + salary_cost + transport_cost + hub_usage_cost + incident_cost + tax) AS total_cost,
                SUM(net_profit)                                       AS total_net,
                AVG(net_profit)                                       AS avg_net,
                SUM(loss_value)                                       AS total_loss,
                SUM(hub_usage_cost)                                   AS total_hub_usage_cost,
                SUM(hub_loss_value)                                   AS total_hub_loss_value,
                SUM(fallback_loss_value)                              AS total_fallback_loss_value,
                SUM(hub_incident_loss_value)                          AS total_hub_incident_loss_value,
                AVG(loss_value)                                       AS avg_loss,
                AVG(oil_price)                                        AS avg_price,
                SUM(bbl_produced)                                     AS total_bbl
            FROM finance_logs
            WHERE tick_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            "
        );
        $stmt->execute([$hours]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

 /**
 * @return list<array<string, mixed>>
 */
    public function getPerPlayerStats(int $hours = 24): array
    {
        $stmt = $this->db->prepare(
            "
            SELECT
                fl.player_id,
                COALESCE(NULLIF(p.company_name, ''), p.username) AS company,
                p.cash,
                SUM(fl.revenue)                                  AS total_revenue,
                SUM(fl.net_profit)                               AS total_net,
                SUM(fl.loss_value)                               AS total_loss,
                SUM(fl.hub_usage_cost)                           AS total_hub_usage_cost,
                SUM(fl.hub_loss_value)                           AS total_hub_loss_value,
                SUM(fl.fallback_loss_value)                      AS total_fallback_loss_value,
                SUM(fl.hub_incident_loss_value)                  AS total_hub_incident_loss_value,
                AVG(fl.net_profit)                               AS avg_net_per_tick,
                MAX(fl.wells_active)                             AS wells_active,
                COUNT(*)                                         AS tick_count
            FROM finance_logs fl
            JOIN players p ON p.id = fl.player_id
            WHERE fl.tick_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            GROUP BY fl.player_id
            ORDER BY total_net DESC
            "
        );
        $stmt->execute([$hours]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

 /**
 * @return list<array{level:string, icon:string, msg:string}>
 */
    public function getAdminAlerts(int $hours = 24): array
    {
        $alerts = [];

        $stmt = $this->db->prepare(
            "
            SELECT COUNT(*) FROM (
                SELECT player_id
                FROM finance_logs
                WHERE tick_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY player_id
                HAVING SUM(net_profit) < 0
            ) x
            "
        );
        $stmt->execute([$hours]);
        $lossPlayers = (int)($stmt->fetchColumn() ?: 0);

        $global = $this->getGlobalStats($hours);
        $rev    = (float)($global['total_revenue'] ?? 0);
        $loss   = (float)($global['total_loss'] ?? 0);
        $hub    = (float)($global['total_hub_loss_value'] ?? 0);
        $fall   = (float)($global['total_fallback_loss_value'] ?? 0);

        $thresh = $this->getAlertThresholds();

        if ($lossPlayers >= $thresh['alert_loss_player_min']) {
            $alerts[] = [
                'level' => 'warning',
                'icon'  => '&#128184;',
                'msg'   => t('admin.finance.alert_loss_players', ['count' => $lossPlayers, 'hours' => $hours]),
            ];
        }

        if ($rev > 0 && ($loss / $rev) > ($thresh['alert_loss_pct'] / 100.0)) {
            $alerts[] = [
                'level' => 'critical',
                'icon'  => '&#128308;',
                'msg'   => t('admin.finance.alert_global_loss', ['pct' => round($loss / $rev * 100, 1)]),
            ];
        }

        if ($rev > 0 && ($hub / $rev) > ($thresh['alert_hub_loss_pct'] / 100.0)) {
            $alerts[] = [
                'level' => 'warning',
                'icon'  => '&#128230;',
                'msg'   => t('admin.finance.alert_hub_loss', ['pct' => round($hub / $rev * 100, 1)]),
            ];
        }

        if ($fall >= $thresh['alert_fallback_min_pln']) {
            $alerts[] = [
                'level' => 'info',
                'icon'  => '&#128668;',
                'msg'   => t('admin.finance.alert_fallback_loss', ['amount' => $this->fmt($fall)]),
            ];
        }

        return $alerts;
    }

 /**
 * Odczytuje progi alertw z well_config. Fallback do bezpiecznych wartoci domylnych.
 * Reads alert thresholds from well_config. Falls back to safe defaults.
 * @return array{alert_loss_pct: float, alert_hub_loss_pct: float, alert_fallback_min_pln: float, alert_loss_player_min: int}
 */
    private function getAlertThresholds(): array
    {
        $defaults = [
            'alert_loss_pct'         => 10.0,  // % of revenue -> global loss alert
            'alert_hub_loss_pct'     => 5.0,   // % of revenue -> hub loss alert
            'alert_fallback_min_pln' => 1.0,   // min PLN loss fallback -> alert
            'alert_loss_player_min'  => 1,     // min players with negative net -> alert
        ];
        try {
            $stmt = $this->db->query(
                "SELECT `key`, value FROM well_config
                 WHERE `key` IN ('alert_loss_pct','alert_hub_loss_pct','alert_fallback_min_pln','alert_loss_player_min')"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];
            foreach ($defaults as $k => &$v) {
                if (isset($rows[$k]) && is_numeric($rows[$k])) {
                    $v = $k === 'alert_loss_player_min'
                        ? max(1, (int)$rows[$k])
                        : max(0.0, (float)$rows[$k]);
                }
            }
            unset($v);
        } catch (Throwable $e) {
 // well_config not available using default values
        }
        return $defaults;
    }

 /**
 * @param array<string, string> $settings
 * @param array<string, mixed>|null $last
 * @param array<string, float> $summary24h
 * @return array<string, float|string>
 */
    public function getLiquidityOverview(int $playerId, array $settings, ?array $last, array $summary24h): array
    {
        $cash = 0.0;
        try {
            $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ? LIMIT 1");
            $stmt->execute([$playerId]);
            $cash = (float)($stmt->fetchColumn() ?: 0.0);
        } catch (Throwable $e) {
            GameLog::error('FinanceService', 'getLiquidityOverview cash FAILED', $e, ['player_id' => $playerId]);
        }

        $tickCount       = max(1.0, (float)($summary24h['tick_count'] ?? 1.0));
        $hoursWindow     = 24.0;
        $hourlyNet       = (float)($summary24h['total_net'] ?? 0.0) / $hoursWindow;
        $hourlyCost      = (
            (float)($summary24h['total_opex'] ?? 0.0) +
            (float)($summary24h['total_salary'] ?? 0.0) +
            (float)($summary24h['total_transport'] ?? 0.0) +
            (float)($summary24h['total_hub_usage_cost'] ?? 0.0) +
            (float)($summary24h['total_incident'] ?? 0.0) +
            (float)($summary24h['total_tax'] ?? 0.0)
        ) / $hoursWindow;
        $tickNet         = (float)($summary24h['total_net'] ?? 0.0) / $tickCount;
        $nextTick        = $tickNet;
        $nextHour        = $hourlyNet;
        $nextSixHours    = $hourlyNet * 6.0;
        $nextDay         = $hourlyNet * 24.0;
        $reserveHours    = 12.0;
        if (class_exists('FinancePolicyService')) {
            $policySvc = new FinancePolicyService($this->db);
            $reserveHours = $policySvc->getReserveTargetHours($playerId);
        } else {
            $reserveHours = ($settings['reserve_policy'] ?? 'standard') === 'high'
                ? 24.0
                : (($settings['reserve_policy'] ?? 'standard') === 'low' ? 6.0 : 12.0);
        }

        $reserveTargetValue = max(0.0, $hourlyCost * $reserveHours);
        $coverageHours      = $hourlyCost > 0 ? ($cash / $hourlyCost) : 999.0;
        $reserveState       = 'good';
        if ($coverageHours < ($reserveHours * 0.5)) {
            $reserveState = 'critical';
        } elseif ($coverageHours < $reserveHours) {
            $reserveState = 'warning';
        } elseif ($coverageHours < ($reserveHours * 1.25)) {
            $reserveState = 'caution';
        }

        $level = 'good';
        if (($cash + $nextDay) < 0 || $coverageHours < 6.0) {
            $level = 'critical';
        } elseif ($cash < $reserveTargetValue || $coverageHours < 12.0) {
            $level = 'warning';
        } elseif ($cash < ($reserveTargetValue * 1.25) || $coverageHours < 18.0) {
            $level = 'caution';
        }

        return [
            'cash' => $cash,
            'hourly_cost' => $hourlyCost,
            'hourly_net' => $hourlyNet,
            'next_tick' => $nextTick,
            'next_hour' => $nextHour,
            'next_six_hours' => $nextSixHours,
            'next_day' => $nextDay,
            'reserve_target_hours' => $reserveHours,
            'reserve_target_value' => $reserveTargetValue,
            'reserve_gap' => max(0.0, $reserveTargetValue - $cash),
            'coverage_hours' => $coverageHours,
            'reserve_state' => $reserveState,
            'is_below_target' => $cash < $reserveTargetValue,
            'level' => $level,
            'last_tick_net' => (float)($last['net_profit'] ?? 0.0),
        ];
    }

 /**
 * @param array<string, string> $settings
 * @param array<string, float|string> $liquidity
 * @param array<string, float> $summary24h
 * @return list<array{level:string,icon:string,msg:string}>
 */
    public function getStage3Alerts(array $settings, array $liquidity, array $summary24h): array
    {
        $alerts = [];

        if (!empty($liquidity['is_below_target'])) {
            $alerts[] = [
                'level' => ($liquidity['reserve_state'] ?? 'warning') === 'critical' ? 'danger' : 'warning',
                'icon' => '&#128176;',
                'msg' => t('finance.alert_reserve_below_target', [
                    'target' => $this->fmt((float)($liquidity['reserve_target_value'] ?? 0.0)),
                    'hours' => number_format((float)($liquidity['reserve_target_hours'] ?? 0.0), 0, ',', ' '),
                ]),
            ];
        }

        $mode = (string)($settings['savings_plan_mode'] ?? 'off');
        if ($mode !== 'off') {
            $alerts[] = [
                'level' => $mode === 'aggressive' ? 'warning' : 'info',
                'icon' => '&#9888;',
                'msg' => t('finance.alert_savings_plan_active', [
                    'mode' => t('finance.savings_mode_' . $mode),
                ]),
            ];
        }

        $revenue = (float)($summary24h['total_revenue'] ?? 0.0);
        $hubUsage = (float)($summary24h['total_hub_usage_cost'] ?? 0.0);
        if ($revenue > 0.0 && ($hubUsage / $revenue) > 0.12) {
            $alerts[] = [
                'level' => 'warning',
                'icon' => '&#128230;',
                'msg' => t('finance.alert_high_hub_usage_share', [
                    'pct' => number_format(($hubUsage / $revenue) * 100.0, 1, ',', ' '),
                ]),
            ];
        }

        return $alerts;
    }

 /**
 * @param array<string, string> $settings
 * @param array<string, mixed>|null $last
 * @param array<string, float> $summary24h
 * @param list<array<string, mixed>> $perWell
 * @param array<string, mixed>|null $liquidity
 * @return list<array<string, string|float|int>>
 */
    public function getRiskOverview(array $settings, ?array $last, array $summary24h, array $perWell, ?array $liquidity = null): array
    {
        $revenue          = (float)($summary24h['total_revenue'] ?? 0.0);
        $totalLoss        = (float)($summary24h['total_loss_value'] ?? 0.0);
        $hubLoss          = (float)($summary24h['total_hub_loss_value'] ?? 0.0);
        $fallbackLoss     = (float)($summary24h['total_fallback_loss_value'] ?? 0.0);
        $incidentHubLoss  = (float)($summary24h['total_hub_incident_loss_value'] ?? 0.0);
        $incidentCost     = (float)($summary24h['total_incident'] ?? 0.0);
        $totalNet         = (float)($summary24h['total_net'] ?? 0.0);
        $costBase         = (
            (float)($summary24h['total_opex'] ?? 0.0) +
            (float)($summary24h['total_salary'] ?? 0.0) +
            (float)($summary24h['total_transport'] ?? 0.0) +
            (float)($summary24h['total_hub_usage_cost'] ?? 0.0) +
            (float)($summary24h['total_tax'] ?? 0.0)
        );

        $lossPct          = $revenue > 0 ? ($totalLoss / $revenue) * 100.0 : 0.0;
        $hubPct           = $revenue > 0 ? (($hubLoss + $fallbackLoss + $incidentHubLoss) / $revenue) * 100.0 : 0.0;
        $costPct          = $revenue > 0 ? ($costBase / $revenue) * 100.0 : 0.0;
        $incidentPct      = $revenue > 0 ? ($incidentCost / $revenue) * 100.0 : 0.0;

        $negativeWells = 0;
        foreach ($perWell as $well) {
            $prod    = (float)($well['base_production_per_hour'] ?? 0.0);
            $price   = (float)($last['oil_price'] ?? 0.0);
            $grossH  = $prod * $price;
            $opexH   = (float)($well['upkeep_cost_per_hour'] ?? 0.0);
            $trPct   = (float)($well['transport_opex_pct'] ?? 0.0);
            $taxRate = (float)($well['regional_tax_rate'] ?? 0.0);
            $netH    = $grossH - $opexH - ($grossH * ($trPct / 100.0)) - ($grossH * $taxRate);
            if ($netH < 0) {
                $negativeWells++;
            }
        }

        $liquidityLevel = (string)($liquidity['level'] ?? '');
        $liquidityRiskLevel = match ($liquidityLevel) {
            'critical' => 'critical',
            'warning' => 'high',
            'caution' => 'medium',
            default => ($totalNet < 0 ? 'high' : 'low'),
        };

        $policyLevel = 'low';
        if (($settings['savings_plan_mode'] ?? 'off') === 'aggressive' || ($settings['reserve_policy'] ?? 'standard') === 'low') {
            $policyLevel = 'high';
        } elseif (($settings['savings_plan_mode'] ?? 'off') === 'moderate') {
            $policyLevel = 'medium';
        }

        return [
            $this->buildRiskItem(
                'liquidity',
                t('finance.risk_liquidity'),
                $liquidityRiskLevel,
                $liquidityRiskLevel === 'high' || $liquidityRiskLevel === 'critical'
                    ? t('finance.risk_liquidity_desc_bad')
                    : t('finance.risk_liquidity_desc_ok'),
                t('finance.risk_liquidity_hint'),
                'liquidity',
                t('finance.risk_action_liquidity')
            ),
            $this->buildRiskItem(
                'logistics',
                t('finance.risk_logistics'),
                $hubPct >= 10.0 ? 'high' : ($hubPct >= 4.0 ? 'medium' : 'low'),
                t('finance.risk_logistics_desc', ['pct' => number_format($hubPct, 1, ',', ' ')]),
                t('finance.risk_logistics_hint'),
                null,
                t('finance.risk_action_logistics')
            ),
            $this->buildRiskItem(
                'costs',
                t('finance.risk_costs'),
                $costPct >= 75.0 ? 'high' : ($costPct >= 55.0 ? 'medium' : 'low'),
                t('finance.risk_costs_desc', ['pct' => number_format($costPct, 1, ',', ' ')]),
                t('finance.risk_costs_hint'),
                'overview',
                t('finance.risk_action_overview')
            ),
            $this->buildRiskItem(
                'operations',
                t('finance.risk_operations'),
                $negativeWells >= 2 || $incidentPct >= 6.0 ? 'high' : (($negativeWells >= 1 || $incidentPct >= 3.0) ? 'medium' : 'low'),
                t('finance.risk_operations_desc', ['count' => (string)$negativeWells]),
                t('finance.risk_operations_hint'),
                'budgets',
                t('finance.risk_action_budgets')
            ),
            $this->buildRiskItem(
                'policy',
                t('finance.risk_policy'),
                $policyLevel,
                t('finance.risk_policy_desc', [
                    'policy' => t('finance.reserve_' . ($settings['reserve_policy'] ?? 'standard')),
                    'mode' => t('finance.savings_mode_' . ($settings['savings_plan_mode'] ?? 'off')),
                ]),
                t('finance.risk_policy_hint'),
                'policy',
                t('finance.risk_action_policy')
            ),
        ];
    }

 /**
 * Zbiorczy snapshot aktywnego wpywu polityki finansowej.
 * Aggregated snapshot of the active financial policy impact.
 *
 * @param array<string, string> $settings
 * @param array<string, mixed>|null $last
 * @param array<string, mixed> $summary24h
 * @param array<string, mixed> $policySnapshot
 * @return array<string, mixed>
 */
    public function getPolicyImpactOverview(int $playerId, array $settings, ?array $last, array $summary24h, array $policySnapshot = []): array
    {
        $defaults = [
            'mode' => (string)($settings['savings_plan_mode'] ?? 'off'),
            'reserve' => (string)($settings['reserve_policy'] ?? 'standard'),
            'effects' => [],
            'tick' => [
                'transport_saved' => 0.0,
                'hub_saved' => 0.0,
                'extra_loss' => 0.0,
                'net' => 0.0,
                'has_direct_effect' => false,
            ],
            'day' => [
                'transport_saved' => 0.0,
                'hub_saved' => 0.0,
                'extra_loss' => 0.0,
                'net' => 0.0,
                'has_direct_effect' => false,
            ],
            'reserve' => [
                'target_hours' => (float)($policySnapshot['reserve_target_hours'] ?? 12.0),
                'coverage_hours' => (float)($policySnapshot['coverage_hours'] ?? 0.0),
                'state' => (string)($policySnapshot['reserve_state'] ?? 'good'),
            ],
        ];

        if (!class_exists('FinancePolicyService')) {
            return $defaults;
        }

        try {
            $policySvc = new FinancePolicyService($this->db);
            $techMods = $policySvc->getTechnicalModifiers($playerId);
            $logMods = $policySvc->getLogisticsModifiers($playerId);
            $hrMods = $policySvc->getHRModifiers($playerId);
            $safetyMods = $policySvc->getSafetyModifiers($playerId);

            $effects = [
                $this->buildPolicyEffect(
                    'transport_cost',
                    t('finance.policy_effect_transport_cost'),
                    (float)($logMods['transport_cost_mult'] ?? 1.0),
                    false
                ),
                $this->buildPolicyEffect(
                    'hub_cost',
                    t('finance.policy_effect_hub_cost'),
                    (float)($logMods['hub_cost_mult'] ?? 1.0),
                    false
                ),
                $this->buildPolicyEffect(
                    'loss',
                    t('finance.policy_effect_loss'),
                    (float)($logMods['loss_mult'] ?? 1.0),
                    false
                ),
                $this->buildPolicyEffect(
                    'wear',
                    t('finance.policy_effect_wear'),
                    (float)($techMods['wear_mult'] ?? 1.0),
                    false
                ),
                $this->buildPolicyEffect(
                    'degradation',
                    t('finance.policy_effect_degradation'),
                    (float)($techMods['degradation_mult'] ?? 1.0),
                    false
                ),
                $this->buildPolicyEffect(
                    'hr_duration',
                    t('finance.policy_effect_hr_duration'),
                    (float)($hrMods['duration_mult'] ?? 1.0),
                    false
                ),
                $this->buildPolicyEffect(
                    'hr_quality',
                    t('finance.policy_effect_hr_quality'),
                    (float)($hrMods['quality_mult'] ?? 1.0),
                    true
                ),
                $this->buildPolicyEffect(
                    'safety_incident',
                    t('finance.policy_effect_safety_incident'),
                    (float)($safetyMods['incident_mult'] ?? 1.0),
                    false
                ),
                $this->buildPolicyEffect(
                    'safety_disaster',
                    t('finance.policy_effect_safety_disaster'),
                    (float)($safetyMods['disaster_mult'] ?? 1.0),
                    false
                ),
            ];

            $tickTransportSaved = $this->estimateCostDelta(
                (float)($last['transport_cost'] ?? 0.0),
                (float)($logMods['transport_cost_mult'] ?? 1.0)
            );
            $tickHubSaved = $this->estimateCostDelta(
                (float)($last['hub_usage_cost'] ?? 0.0),
                (float)($logMods['hub_cost_mult'] ?? 1.0)
            );
            $tickLossCurrent = (float)($last['loss_value'] ?? 0.0)
                + (float)($last['hub_loss_value'] ?? 0.0)
                + (float)($last['fallback_loss_value'] ?? 0.0)
                + (float)($last['hub_incident_loss_value'] ?? 0.0);
            $tickExtraLoss = $this->estimateExtraLossDelta(
                $tickLossCurrent,
                (float)($logMods['loss_mult'] ?? 1.0)
            );

            $dayTransportSaved = $this->estimateCostDelta(
                (float)($summary24h['total_transport'] ?? 0.0),
                (float)($logMods['transport_cost_mult'] ?? 1.0)
            );
            $dayHubSaved = $this->estimateCostDelta(
                (float)($summary24h['total_hub_usage_cost'] ?? 0.0),
                (float)($logMods['hub_cost_mult'] ?? 1.0)
            );
            $dayLossCurrent = (float)($summary24h['total_loss_value'] ?? 0.0)
                + (float)($summary24h['total_hub_loss_value'] ?? 0.0)
                + (float)($summary24h['total_fallback_loss_value'] ?? 0.0)
                + (float)($summary24h['total_hub_incident_loss_value'] ?? 0.0);
            $dayExtraLoss = $this->estimateExtraLossDelta(
                $dayLossCurrent,
                (float)($logMods['loss_mult'] ?? 1.0)
            );

            return [
                'mode' => (string)($settings['savings_plan_mode'] ?? 'off'),
                'reserve_level' => (string)($settings['reserve_policy'] ?? 'standard'),
                'effects' => $effects,
                'tick' => [
                    'transport_saved' => $tickTransportSaved,
                    'hub_saved' => $tickHubSaved,
                    'extra_loss' => $tickExtraLoss,
                    'net' => $tickTransportSaved + $tickHubSaved - $tickExtraLoss,
                    'has_direct_effect' => ($tickTransportSaved + $tickHubSaved + $tickExtraLoss) > 0.009,
                ],
                'day' => [
                    'transport_saved' => $dayTransportSaved,
                    'hub_saved' => $dayHubSaved,
                    'extra_loss' => $dayExtraLoss,
                    'net' => $dayTransportSaved + $dayHubSaved - $dayExtraLoss,
                    'has_direct_effect' => ($dayTransportSaved + $dayHubSaved + $dayExtraLoss) > 0.009,
                ],
                'reserve' => [
                    'target_hours' => (float)($policySnapshot['reserve_target_hours'] ?? 12.0),
                    'coverage_hours' => (float)($policySnapshot['coverage_hours'] ?? 0.0),
                    'state' => (string)($policySnapshot['reserve_state'] ?? 'good'),
                ],
            ];
        } catch (Throwable $e) {
            GameLog::error('FinanceService', 'getPolicyImpactOverview FAILED', $e, ['player_id' => $playerId]);
            return $defaults;
        }
    }

 /**
 * Rekomendacje dla aktywnej polityki finansowej.
 *
 * @param array<string, string> $settings
 * @param array<string, mixed> $liquidity
 * @param array<string, mixed> $summary24h
 * @param array<string, mixed> $policyImpact
 * @return array<string, mixed>
 */
    public function getPolicyRecommendationOverview(array $settings, array $liquidity, array $summary24h, array $policyImpact): array
    {
        $mode = (string)($settings['savings_plan_mode'] ?? 'off');
        $reserveState = (string)($policyImpact['reserve']['state'] ?? ($liquidity['level'] ?? 'good'));
        $day = (array)($policyImpact['day'] ?? []);
        $transportSaved = (float)($day['transport_saved'] ?? 0.0);
        $hubSaved = (float)($day['hub_saved'] ?? 0.0);
        $extraLoss = (float)($day['extra_loss'] ?? 0.0);
        $netDay = (float)($day['net'] ?? 0.0);
        $savingsTotal = $transportSaved + $hubSaved;
        $lossPct = 0.0;
        $revenue24 = (float)($summary24h['total_revenue'] ?? 0.0);
        $loss24 = (float)($summary24h['total_loss_value'] ?? 0.0)
            + (float)($summary24h['total_hub_loss_value'] ?? 0.0)
            + (float)($summary24h['total_fallback_loss_value'] ?? 0.0)
            + (float)($summary24h['total_hub_incident_loss_value'] ?? 0.0);
        if ($revenue24 > 0.0) {
            $lossPct = ($loss24 / $revenue24) * 100.0;
        }

        $status = 'good';
        $title = t('finance.policy_reco_title_good');
        $summary = t('finance.policy_reco_summary_good');
        $primaryAction = [
            'href' => '?tab=overview&hours=24',
            'label' => t('finance.policy_reco_action_overview'),
        ];
        $secondaryAction = [];
        $highlights = [];

        if ($transportSaved > 0.0 || $hubSaved > 0.0) {
            $bestLabel = $hubSaved >= $transportSaved
                ? t('finance.policy_effect_hub_cost')
                : t('finance.policy_effect_transport_cost');
            $bestValue = max($hubSaved, $transportSaved);
            if ($bestValue > 0.0) {
                $highlights[] = t('finance.policy_reco_highlight_best_saving', [
                    'label' => $bestLabel,
                    'amount' => $this->fmt($bestValue),
                ]);
            }
        }

        if ($extraLoss > 0.0) {
            $highlights[] = t('finance.policy_reco_highlight_extra_loss', [
                'amount' => $this->fmt($extraLoss),
            ]);
        }

        if ($reserveState === 'critical' || $reserveState === 'warning') {
            $highlights[] = t('finance.policy_reco_highlight_reserve_low');
        }

        if ($mode === 'aggressive' && $netDay < 0.0) {
            $status = 'warning';
            $title = t('finance.policy_reco_title_aggressive_bad');
            $summary = t('finance.policy_reco_summary_aggressive_bad');
            $primaryAction = [
                'href' => '/logistics',
                'label' => t('finance.policy_reco_action_logistics'),
            ];
            $secondaryAction = [
                'href' => '?tab=budgets&hours=24',
                'label' => t('finance.policy_reco_action_budgets'),
            ];
        } elseif ($mode !== 'off' && $extraLoss > $savingsTotal && $extraLoss > 0.0) {
            $status = 'warning';
            $title = t('finance.policy_reco_title_losses');
            $summary = t('finance.policy_reco_summary_losses');
            $primaryAction = [
                'href' => '/logistics',
                'label' => t('finance.policy_reco_action_logistics'),
            ];
            $secondaryAction = [
                'href' => '?tab=policy&hours=24#fin-savings-plan',
                'label' => t('finance.policy_reco_action_policy'),
            ];
        } elseif ($mode === 'off' && ($reserveState === 'critical' || $reserveState === 'warning')) {
            $status = 'caution';
            $title = t('finance.policy_reco_title_buffer');
            $summary = t('finance.policy_reco_summary_buffer');
            $primaryAction = [
                'href' => '?tab=liquidity&hours=24',
                'label' => t('finance.policy_reco_action_liquidity'),
            ];
            $secondaryAction = [
                'href' => '?tab=policy&hours=24#fin-savings-plan',
                'label' => t('finance.policy_reco_action_policy'),
            ];
        } elseif ($mode === 'off' && $lossPct >= 8.0) {
            $status = 'caution';
            $title = t('finance.policy_reco_title_logistics');
            $summary = t('finance.policy_reco_summary_logistics');
            $primaryAction = [
                'href' => '/logistics',
                'label' => t('finance.policy_reco_action_logistics'),
            ];
            $secondaryAction = [
                'href' => '?tab=policy&hours=24#fin-savings-plan',
                'label' => t('finance.policy_reco_action_policy'),
            ];
        } elseif ($mode !== 'off' && $netDay >= 0.0 && ($reserveState === 'critical' || $reserveState === 'warning')) {
            $status = 'caution';
            $title = t('finance.policy_reco_title_reserve');
            $summary = t('finance.policy_reco_summary_reserve');
            $primaryAction = [
                'href' => '?tab=liquidity&hours=24',
                'label' => t('finance.policy_reco_action_liquidity'),
            ];
            $secondaryAction = [
                'href' => '?tab=policy&hours=24#fin-savings-plan',
                'label' => t('finance.policy_reco_action_policy'),
            ];
        } elseif ($mode !== 'off' && $netDay >= 0.0) {
            $title = t('finance.policy_reco_title_profit');
            $summary = t('finance.policy_reco_summary_profit');
            $primaryAction = [
                'href' => '/logistics',
                'label' => t('finance.policy_reco_action_logistics'),
            ];
            $secondaryAction = [
                'href' => '?tab=history&hours=24',
                'label' => t('finance.policy_reco_action_history'),
            ];
        }

        if ($mode === 'off' && empty($highlights)) {
            $highlights[] = t('finance.policy_reco_highlight_off_normal');
        }

        return [
            'status' => $status,
            'title' => $title,
            'summary' => $summary,
            'highlights' => $highlights,
            'primary_action' => $primaryAction,
            'secondary_action' => $secondaryAction,
        ];
    }

 /**
 * @return array<string, string|float|int>
 */
    private function buildRiskItem(string $key, string $label, string $level, string $desc, string $hint, ?string $actionTab = null, ?string $actionLabel = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'level' => $level,
            'level_label' => t('finance.risk_level_' . $level),
            'desc' => $desc,
            'hint' => $hint,
            'action_tab' => $actionTab ?? '',
            'action_label' => $actionLabel ?? '',
        ];
    }

 /**
 * @return array<string, string|float|bool>
 */
    private function buildPolicyEffect(string $key, string $label, float $multiplier, bool $higherIsGood): array
    {
        $deltaPct = round(($multiplier - 1.0) * 100.0, 1);
        $state = 'neutral';
        if (abs($deltaPct) >= 0.05) {
            $improves = $higherIsGood ? ($deltaPct > 0) : ($deltaPct < 0);
            $state = $improves ? 'good' : 'bad';
        }

        return [
            'key' => $key,
            'label' => $label,
            'multiplier' => $multiplier,
            'delta_pct' => $deltaPct,
            'state' => $state,
            'higher_is_good' => $higherIsGood,
        ];
    }

    private function estimateCostDelta(float $currentValue, float $multiplier): float
    {
        if ($currentValue <= 0.0 || $multiplier <= 0.0 || abs($multiplier - 1.0) < 0.0001) {
            return 0.0;
        }
        $baseline = $currentValue / $multiplier;
        return round(max(0.0, $baseline - $currentValue), 2);
    }

    private function estimateExtraLossDelta(float $currentValue, float $multiplier): float
    {
        if ($currentValue <= 0.0 || $multiplier <= 0.0 || abs($multiplier - 1.0) < 0.0001) {
            return 0.0;
        }
        $baseline = $currentValue / $multiplier;
        return round(max(0.0, $currentValue - $baseline), 2);
    }

    private function fmt(float $value): string
    {
        return number_format($value, 0, ',', ' ');
    }
}
