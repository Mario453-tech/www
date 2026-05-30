<?php
declare(strict_types=1);

function adminFinanceFormatPln(float $value, bool $sign = false): string
{
    $formatted = number_format(abs($value), 0, ',', ' ') . ' PLN';
    if ($sign) {
        return ($value >= 0 ? '+' : '&minus;') . $formatted;
    }

    return $value < 0 ? '&minus;' . $formatted : $formatted;
}

function adminFinanceBuildViewData(PDO $db, FinanceService $finSvc, FinancePolicyService $policySvc, int $hours, array $config, array $mults): array
{
    $global    = $finSvc->getGlobalStats($hours);
    $perPlayer = $finSvc->getPerPlayerStats($hours);
    $admAlerts = $finSvc->getAdminAlerts($hours);
    $oilPrice  = (float)($db->query("SELECT current_price FROM market_state WHERE id = 1")->fetchColumn() ?? 70);

    $historyStmt = [];
    try {
        $historyStmt = $db->prepare(
            "
            SELECT
                DATE_FORMAT(tick_at, '%d.%m %H:%i') AS label,
                SUM(revenue)                        AS revenue,
                SUM(net_profit)                     AS net_profit,
                SUM(loss_value)                     AS loss_value,
                AVG(oil_price)                      AS oil_price
            FROM finance_logs
            WHERE tick_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            GROUP BY DATE_FORMAT(tick_at, '%Y-%m-%d %H:%i')
            ORDER BY tick_at ASC
            "
        );
        $historyStmt->execute([$hours]);
        $globalHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $globalHistory = [];
        GameLog::error('admin/finance.php', 'load history FAILED', $e);
    }

    $gRev          = (float)($global['total_revenue'] ?? 0);
    $gNet          = (float)($global['total_net'] ?? 0);
    $gLoss         = (float)($global['total_loss'] ?? 0);
    $gBbl          = (float)($global['total_bbl'] ?? 0);
    $gPlayers      = (int)($global['player_count'] ?? 0);
    $gHubUsage     = (float)($global['total_hub_usage_cost'] ?? 0);
    $gHubLoss      = (float)($global['total_hub_loss_value'] ?? 0);
    $gFallbackLoss = (float)($global['total_fallback_loss_value'] ?? 0);
    $gHubIncLoss   = (float)($global['total_hub_incident_loss_value'] ?? 0);
    $lossPct       = $gRev > 0 ? round($gLoss / $gRev * 100, 1) : 0;

    $globalStatCards = [
        ['title' => t('admin.finance.stat_revenue'), 'val' => adminFinanceFormatPln($gRev), 'cls' => 'fin-green'],
        ['title' => t('admin.finance.stat_net'), 'val' => adminFinanceFormatPln($gNet, true), 'cls' => $gNet >= 0 ? 'fin-green' : 'fin-red'],
        ['title' => t('admin.finance.stat_loss'), 'val' => adminFinanceFormatPln($gLoss) . " ({$lossPct}%)", 'cls' => 'fin-orange'],
        ['title' => t('admin.finance.stat_hub_cost'), 'val' => adminFinanceFormatPln($gHubUsage), 'cls' => 'fin-red'],
        ['title' => t('admin.finance.stat_hub_loss'), 'val' => adminFinanceFormatPln($gHubLoss), 'cls' => 'fin-orange'],
        ['title' => t('admin.finance.stat_prod'), 'val' => number_format($gBbl, 1, ',', ' ') . ' bbl', 'cls' => ''],
        ['title' => t('admin.finance.stat_players'), 'val' => (string)$gPlayers, 'cls' => ''],
        ['title' => t('admin.finance.stat_oil_price'), 'val' => number_format($oilPrice, 2, ',', ' ') . ' PLN/bbl', 'cls' => 'fin-green'],
    ];

    $hubStatCards = [
        ['title' => t('admin.finance.stat_hub_cost'), 'val' => adminFinanceFormatPln($gHubUsage), 'cls' => 'fin-red'],
        ['title' => t('admin.finance.stat_hub_loss'), 'val' => adminFinanceFormatPln($gHubLoss), 'cls' => 'fin-orange'],
        ['title' => t('admin.finance.stat_fallback_loss'), 'val' => adminFinanceFormatPln($gFallbackLoss), 'cls' => 'fin-orange'],
        ['title' => t('admin.finance.stat_hub_incident_loss'), 'val' => adminFinanceFormatPln($gHubIncLoss), 'cls' => 'fin-red'],
    ];

    $hubMonitor = [];
    try {
        $stmtHub = $db->prepare("
            SELECT
                h.id, h.name, h.hub_type, h.status, h.level,
                h.slot_limit, h.condition_pct, h.efficiency_pct, h.opex_per_tick,
                h.player_id AS owner_id, COALESCE(NULLIF(p.company_name,''), p.username) AS owner_company,
                COALESCE(asgn.active_wells, 0)       AS active_wells,
                COALESCE(stats.avg_load_pct, 0.0)    AS avg_load_pct,
                COALESCE(stats.max_load_pct, 0.0)    AS max_load_pct,
                COALESCE(stats.total_lost_bbl, 0.0)  AS total_lost_bbl,
                COALESCE(stats.incident_count, 0)    AS incident_count,
                COALESCE(stats.overload_count, 0)    AS overload_count,
                COALESCE(stats.tick_count, 0)        AS tick_count
            FROM logistics_hubs h
            LEFT JOIN players p ON p.id = h.player_id
            LEFT JOIN (
                SELECT hub_id, COUNT(*) AS active_wells
                FROM logistics_hub_assignments
                WHERE status = 'active'
                GROUP BY hub_id
            ) asgn ON asgn.hub_id = h.id
            LEFT JOIN (
                SELECT hub_id,
                       AVG(load_pct)        AS avg_load_pct,
                       MAX(load_pct)        AS max_load_pct,
                       SUM(lost_volume_bbl) AS total_lost_bbl,
                       SUM(incident_flag)   AS incident_count,
                       SUM(overload_flag)   AS overload_count,
                       COUNT(*)             AS tick_count
                FROM logistics_hub_tick_stats
                WHERE tick_time >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                GROUP BY hub_id
            ) stats ON stats.hub_id = h.id
            WHERE h.status != 'disabled'
            ORDER BY avg_load_pct DESC, total_lost_bbl DESC, h.name ASC
        ");
        $stmtHub->execute([$hours]);
        $hubMonitor = $stmtHub->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        GameLog::error('admin/finance.php', 'hubMonitor FAILED', $e);
    }

    $configFields = adminFinanceConfigFields($config);

    $policyDistribution = [];
    try {
        $distributionKeys = [
            'technical_budget' => t('admin.finance.policy_technical'),
            'logistics_budget' => t('admin.finance.policy_logistics'),
            'hr_budget' => t('admin.finance.policy_hr'),
            'safety_budget' => t('admin.finance.policy_safety'),
            'reserve_policy' => t('admin.finance.policy_reserve'),
        ];

        foreach ($distributionKeys as $column => $label) {
            $stmt = $db->query("SELECT {$column} AS level_key, COUNT(*) AS cnt FROM player_finance_settings GROUP BY {$column}");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $map = ['low' => 0, 'standard' => 0, 'high' => 0];
            foreach ($rows as $row) {
                $levelKey = (string)($row['level_key'] ?? 'standard');
                if (isset($map[$levelKey])) {
                    $map[$levelKey] = (int)$row['cnt'];
                }
            }
            $policyDistribution[] = [
                'label' => $label,
                'low' => $map['low'],
                'standard' => $map['standard'],
                'high' => $map['high'],
            ];
        }
    } catch (Throwable $e) {
        GameLog::error('admin/finance.php', 'load policyDistribution FAILED', $e);
    }

    $savingsModes = ['off' => 0, 'moderate' => 0, 'aggressive' => 0];
    try {
        $rows = $db->query("SELECT COALESCE(savings_plan_mode,'off') AS mode, COUNT(*) AS cnt FROM player_finance_settings GROUP BY savings_plan_mode")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $mode = (string)($row['mode'] ?? 'off');
            if (isset($savingsModes[$mode])) {
                $savingsModes[$mode] = (int)$row['cnt'];
            }
        }
    } catch (Throwable $e) {
        GameLog::error('admin/finance.php', 'savingsModes FAILED', $e);
    }

    $playerPolicies = [];
    $nowTs = time();
    try {
        $rows = $db->query("
            SELECT p.id AS player_id, COALESCE(NULLIF(p.company_name,''), p.username) AS company, p.cash,
                   COALESCE(pfs.savings_plan_mode, 'off')        AS savings_plan_mode,
                   COALESCE(pfs.reserve_policy, 'standard')      AS reserve_policy,
                   pfs.savings_plan_changed_at,
                   COALESCE(pfs.technical_budget, 'standard')    AS technical_budget,
                   COALESCE(pfs.logistics_budget, 'standard')    AS logistics_budget,
                   COALESCE(pfs.hr_budget, 'standard')           AS hr_budget,
                   COALESCE(pfs.safety_budget, 'standard')       AS safety_budget
            FROM players p
            LEFT JOIN player_finance_settings pfs ON pfs.player_id = p.id
            WHERE p.status != 'bankrupt'
            ORDER BY pfs.savings_plan_mode DESC, COALESCE(NULLIF(p.company_name,''), p.username) ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $changedAt = (string)($row['savings_plan_changed_at'] ?? '');
            $cooldownActive = false;
            $cooldownLeft = 0;
            if ($changedAt !== '') {
                $changedAtTs = strtotime($changedAt);
                if ($changedAtTs !== false) {
                    $until = $changedAtTs + ((int)$config['savings_plan_cooldown_hours'] * 3600);
                    if ($until > $nowTs) {
                        $cooldownActive = true;
                        $cooldownLeft = $until - $nowTs;
                    }
                }
            }
            $row['cooldown_active'] = $cooldownActive;
            $row['cooldown_left'] = $cooldownLeft;
            $playerPolicies[] = $row;
        }
    } catch (Throwable $e) {
        GameLog::error('admin/finance.php', 'playerPolicies FAILED', $e);
    }

    $policyImpactPlayers = [];
    $policyImpactSummary = [
        'good' => 0,
        'caution' => 0,
        'warning' => 0,
        'negative_day' => 0,
    ];
    try {
        foreach ($playerPolicies as $policyRow) {
            $playerId = (int)($policyRow['player_id'] ?? 0);
            if ($playerId <= 0) {
                continue;
            }

            $settings = [
                'technical_budget' => (string)($policyRow['technical_budget'] ?? 'standard'),
                'logistics_budget' => (string)($policyRow['logistics_budget'] ?? 'standard'),
                'hr_budget' => (string)($policyRow['hr_budget'] ?? 'standard'),
                'safety_budget' => (string)($policyRow['safety_budget'] ?? 'standard'),
                'reserve_policy' => (string)($policyRow['reserve_policy'] ?? 'standard'),
                'savings_plan_mode' => (string)($policyRow['savings_plan_mode'] ?? 'off'),
                'savings_plan_changed_at' => (string)($policyRow['savings_plan_changed_at'] ?? ''),
            ];

            $last = $finSvc->getLastTick($playerId);
            $summary24 = $finSvc->getSummary($playerId, 24);
            $hourlyCost = (
                (float)($summary24['total_opex'] ?? 0.0) +
                (float)($summary24['total_salary'] ?? 0.0) +
                (float)($summary24['total_transport'] ?? 0.0) +
                (float)($summary24['total_hub_usage_cost'] ?? 0.0) +
                (float)($summary24['total_incident'] ?? 0.0)
            ) / 24.0;
            $cash = (float)($policyRow['cash'] ?? 0.0);

            $policySnapshot = $policySvc->getPolicySnapshot($playerId, $hourlyCost, $cash);
            $liquidity = $finSvc->getLiquidityOverview($playerId, $settings, $last, $summary24);
            $impact = $finSvc->getPolicyImpactOverview($playerId, $settings, $last, $summary24, $policySnapshot);
            $recommendation = $finSvc->getPolicyRecommendationOverview($settings, $liquidity, $summary24, $impact);

            $tick = (array)($impact['tick'] ?? []);
            $day = (array)($impact['day'] ?? []);
            $reserve = (array)($impact['reserve'] ?? []);
            $netDay = (float)($day['net'] ?? 0.0);
            $status = (string)($recommendation['status'] ?? 'good');

            if (isset($policyImpactSummary[$status])) {
                $policyImpactSummary[$status]++;
            }
            if ($netDay < 0.0) {
                $policyImpactSummary['negative_day']++;
            }

            $highlights = array_values(array_filter(array_map(
                static fn($value): string => trim((string)$value),
                (array)($recommendation['highlights'] ?? [])
            )));

            $policyImpactPlayers[] = [
                'player_id' => $playerId,
                'company' => (string)($policyRow['company'] ?? '?'),
                'cash' => $cash,
                'mode' => (string)($impact['mode'] ?? $settings['savings_plan_mode']),
                'reserve_level' => (string)($impact['reserve_level'] ?? $settings['reserve_policy']),
                'reserve_state' => (string)($reserve['state'] ?? 'good'),
                'tick_net' => (float)($tick['net'] ?? 0.0),
                'day_net' => $netDay,
                'transport_saved' => (float)($day['transport_saved'] ?? 0.0),
                'hub_saved' => (float)($day['hub_saved'] ?? 0.0),
                'extra_loss' => (float)($day['extra_loss'] ?? 0.0),
                'status' => $status,
                'title' => (string)($recommendation['title'] ?? ''),
                'summary' => (string)($recommendation['summary'] ?? ''),
                'highlights' => $highlights,
                'primary_action' => (array)($recommendation['primary_action'] ?? []),
                'secondary_action' => (array)($recommendation['secondary_action'] ?? []),
            ];
        }

        usort($policyImpactPlayers, static function (array $a, array $b): int {
            $rank = ['warning' => 0, 'caution' => 1, 'good' => 2];
            $aRank = $rank[$a['status'] ?? 'good'] ?? 9;
            $bRank = $rank[$b['status'] ?? 'good'] ?? 9;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }

            return ((float)$a['day_net']) <=> ((float)$b['day_net']);
        });
    } catch (Throwable $e) {
        GameLog::error('admin/finance.php', 'policyImpactPlayers FAILED', $e);
    }

    $historyPid = max(0, (int)($_GET['history_pid'] ?? 0));
    $decisionHistory = [];
    try {
        if ($historyPid > 0) {
            $stmtH = $db->prepare("
                SELECT pfd.id, pfd.player_id, pfd.decision_key,
                       pfd.old_value, pfd.new_value, pfd.source, pfd.created_at,
                       COALESCE(NULLIF(p.company_name,''), p.username) AS company
                FROM player_finance_decisions pfd
                JOIN players p ON p.id = pfd.player_id
                WHERE pfd.player_id = ?
                ORDER BY pfd.created_at DESC
                LIMIT 200
            ");
            $stmtH->execute([$historyPid]);
        } else {
            $stmtH = $db->query("
                SELECT pfd.id, pfd.player_id, pfd.decision_key,
                       pfd.old_value, pfd.new_value, pfd.source, pfd.created_at,
                       COALESCE(NULLIF(p.company_name,''), p.username) AS company
                FROM player_finance_decisions pfd
                JOIN players p ON p.id = pfd.player_id
                ORDER BY pfd.created_at DESC
                LIMIT 100
            ");
        }
        $decisionHistory = $stmtH->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        GameLog::error('admin/finance.php', 'decisionHistory FAILED', $e);
    }

    $playerList = [];
    try {
        $playerList = $db->query("SELECT id, COALESCE(NULLIF(company_name,''), username) AS company FROM players WHERE status != 'bankrupt' ORDER BY COALESCE(NULLIF(company_name,''), username) ASC")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        GameLog::error('admin/finance.php', 'playerList FAILED', $e);
    }

    $reserveStatus = [];
    $reserveCounts = ['critical' => 0, 'warning' => 0, 'caution' => 0];
    try {
        $rsRows = $db->query("
            SELECT p.id AS player_id, COALESCE(NULLIF(p.company_name,''), p.username) AS company, p.cash,
                   COALESCE(pfs.reserve_policy, 'standard') AS reserve_policy,
                   COALESCE(pfs.savings_plan_mode, 'off')   AS savings_plan_mode,
                   COALESCE(
                       (SELECT (SUM(fl.opex) + SUM(fl.salary_cost) + SUM(fl.transport_cost)
                               + SUM(fl.hub_usage_cost) + SUM(fl.incident_cost)) / 24.0
                        FROM finance_logs fl
                        WHERE fl.player_id = p.id
                          AND fl.tick_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                       ), 0
                   ) AS hourly_cost
            FROM players p
            LEFT JOIN player_finance_settings pfs ON pfs.player_id = p.id
            WHERE p.status != 'bankrupt'
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rsRows as $reserveRow) {
            $hourlyCost = (float)$reserveRow['hourly_cost'];
            $reserveHours = match ((string)$reserveRow['reserve_policy']) {
                'low' => 6.0,
                'high' => 24.0,
                default => 12.0,
            };
            $reserveTarget = $hourlyCost * $reserveHours;
            $cash = (float)$reserveRow['cash'];
            $coveragePct = $reserveTarget > 0 ? ($cash / $reserveTarget * 100) : 999.0;

            $state = 'good';
            if ($coveragePct < 50) {
                $state = 'critical';
            } elseif ($coveragePct < 100) {
                $state = 'warning';
            } elseif ($coveragePct < 125) {
                $state = 'caution';
            }

            if ($state !== 'good') {
                $reserveStatus[] = array_merge($reserveRow, [
                    'hourly_cost' => $hourlyCost,
                    'reserve_hours' => $reserveHours,
                    'reserve_target' => $reserveTarget,
                    'coverage_pct' => $coveragePct,
                    'state' => $state,
                ]);
                $reserveCounts[$state]++;
            }
        }
        usort($reserveStatus, fn($a, $b) => $a['coverage_pct'] <=> $b['coverage_pct']);
    } catch (Throwable $e) {
        GameLog::error('admin/finance.php', 'reserveStatus FAILED', $e);
    }

    return [
        'admAlerts' => $admAlerts,
        'globalStatCards' => $globalStatCards,
        'hubStatCards' => $hubStatCards,
        'perPlayer' => $perPlayer,
        'configFields' => $configFields,
        'policyDistribution' => $policyDistribution,
        'savingsModes' => $savingsModes,
        'playerPolicies' => $playerPolicies,
        'policyImpactPlayers' => $policyImpactPlayers,
        'policyImpactSummary' => $policyImpactSummary,
        'playerList' => $playerList,
        'reserveStatus' => $reserveStatus,
        'reserveCounts' => $reserveCounts,
        'global' => $global,
        'oilPrice' => $oilPrice,
        'globalHistory' => $globalHistory,
        'fmtAdminPln' => 'adminFinanceFormatPln',
        'mults' => $mults,
        'multDefaults' => adminFinanceMultiplierDefaults(),
        'hubMonitor' => $hubMonitor,
        'decisionHistory' => $decisionHistory,
        'historyPid' => $historyPid,
    ];
}
