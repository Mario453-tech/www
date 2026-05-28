<?php
require_once __DIR__ . '/src/init.php';
Auth::requireLogin();
BoardAccess::require(Auth::getUserId(), 'logistics');

$_pageStart = GameLog::pageStart('logistics.php');
$playerId = Auth::getUserId();

try {
    $db  = Database::getInstance()->getConnection();
    $svc = new LogisticsService($playerId);
    $summary = $svc->getCurrentSummary();
    $cooldown = $svc->getRemainingCooldown();

    $totals = $summary['totals'] ?? ['transported' => 0, 'loss' => 0, 'cost' => 0];
    $wells = $summary['wells'] ?? [];
    $totalOutput = (float)$totals['transported'] + (float)$totals['loss'];
    $efficiency = $totalOutput > 0 ? round(((float)$totals['transported'] / $totalOutput) * 100, 1) : 0.0;
    $lossPct = $totalOutput > 0 ? round(((float)$totals['loss'] / $totalOutput) * 100, 1) : 0.0;

    $transportMix = [];
    foreach ($wells as $well) {
        $type = $well['transport'] ?? 'unknown';
        if (!isset($transportMix[$type])) {
            $transportMix[$type] = ['count' => 0, 'transported' => 0.0, 'loss' => 0.0, 'cost' => 0.0];
        }
        $transportMix[$type]['count']++;
        $transportMix[$type]['transported'] += (float)($well['transported'] ?? 0);
        $transportMix[$type]['loss'] += (float)($well['loss'] ?? 0);
        $transportMix[$type]['cost'] += (float)($well['cost'] ?? 0);
    }

    $alerts = [];
    if (empty($wells) || $totalOutput == 0) {
        $alerts[] = ['type' => 'danger', 'text' => t('logistics.no_wells')];
    } else {
        if ($lossPct >= 15) {
            $alerts[] = ['type' => 'danger', 'text' => t('logistics.alert_loss_critical', ['pct' => $lossPct])];
        } elseif ($lossPct >= 8) {
            $alerts[] = ['type' => 'warn', 'text' => t('logistics.alert_loss_warn', ['pct' => $lossPct])];
        }
        if ($efficiency < 85) {
            $alerts[] = ['type' => 'warn', 'text' => t('logistics.alert_efficiency_low', ['pct' => $efficiency])];
        }
        if (empty($alerts)) {
            $alerts[] = ['type' => 'ok', 'text' => t('logistics.alert_all_good')];
        }
    }
// Dane modulu hubow | Hub module data
// Huby sa infrastruktura systemowa - gracz przypisuje odwierty do istniejacych hubow | Hubs are system infrastructure - player assigns wells to existing hubs
    $hubCards         = [];   // huby z przypisanymi odwiertami gracza
    $hubAlerts        = [];   // alerty logistyczne
    $hubRegions       = [];   // podsumowanie per region
    $hubUnassigned    = [];   // odwierty bez huba
    $hubAvailByRegion = [];   // wszystkie dostepne huby systemowe w regionach gracza
    $hubIncidents     = [];   // ostatnie incydenty logistyczne

    if (class_exists('HubService') && class_exists('HubEconomyService') && class_exists('HubViewService')) {
        try {
            $hubSvc  = new HubService($db);
            $econSvc = new HubEconomyService($hubSvc);
            $hubView = new HubViewService($db, $hubSvc, $econSvc);

            $hubCards         = $hubView->getHubCards($playerId);
            $hubAlerts        = $hubView->getAlerts($playerId);
            $hubRegions       = $hubView->getRegionSummary($playerId);
            $hubUnassigned    = $hubSvc->getUnassignedWells($playerId);
            $hubAvailByRegion = $hubView->getAvailableHubsByRegion($playerId);
        } catch (Throwable $e) {
            GameLog::error('logistics.php', 'hub data load FAILED', $e, ['player_id' => $playerId]);
        }
    }

    if (class_exists('HubIncidentService')) {
        try {
            $hubIncSvc    = new HubIncidentService($db);
            $hubIncidents = $hubIncSvc->getPlayerRecentIncidents($playerId, 15);
        } catch (Throwable $e) {
            GameLog::error('logistics.php', 'hub incident data load FAILED', $e, ['player_id' => $playerId]);
        }
    }

    $pipelines = [];
    $pipelineSummary = [
        'total' => 0,
        'critical' => 0,
        'degraded' => 0,
        'needs_service' => 0,
        'maintenance_overdue' => 0,
        'avg_condition' => 0.0,
        'avg_loss' => 0.0,
        'avg_cost' => 0.0,
        'engineers' => 0,
    ];
    $pipelineHse = [
        'active' => false,
        'state' => 'none',
        'label' => '',
        'failure_pct' => 0,
        'catastrophe_pct' => 0,
        'pipelines' => 0,
        'supervised_units' => 0,
    ];
    $marineDeliveries = [];
    $marineHistory = [];
    $marineInTransitBbl = 0.0;

    if (class_exists('WellPipelineService')) {
        try {
            $pipelineSvc = new WellPipelineService($db);

            $wellSummaryById = [];
            foreach ($wells as $well) {
                $wellSummaryById[(int)($well['id'] ?? 0)] = $well;
            }

            $pipelines = $pipelineSvc->getPlayerPipelines($playerId);
            $conditionSum = 0.0;
            $lossSum = 0.0;
            $costSum = 0.0;
            $nowTs = time();

            foreach ($pipelines as &$pipeline) {
                $wellId = (int)($pipeline['well_id'] ?? $pipeline['source_well_id'] ?? 0);
                $wellSummary = $wellSummaryById[$wellId] ?? [];
                $flowBbl = (float)($wellSummary['transported'] ?? 0) + (float)($wellSummary['loss'] ?? 0);
                $capacityBbl = (float)($pipeline['capacity_bbl_h'] ?? $pipeline['real_capacity_bph'] ?? 0.0);
                $transportLossPct = (float)($pipeline['transport_loss'] ?? 0.0);
                $conditionPct = (float)($pipeline['condition_pct'] ?? 100.0);
                $status = (string)($pipeline['status'] ?? 'active');

                // Add build timer data for building pipelines / Dodaj dane timera dla rurociagow w budowie
                $buildFinishAt = $pipeline['build_finish_at'] ?? null;
                $pipeline['seconds_remaining'] = ($status === 'building' && $buildFinishAt !== null)
                    ? max(0, strtotime($buildFinishAt) - $nowTs)
                    : null;

                $utilizationPct = $capacityBbl > 0 ? min(999.0, round(($flowBbl / $capacityBbl) * 100, 1)) : 0.0;
                $lossBbl = round($flowBbl * ($transportLossPct / 100), 1);
                $tickCost = round((float)($pipeline['opex_per_tick'] ?? 0.0), 2);
                $flowCost = round($flowBbl * (float)($pipeline['opex_per_bbl'] ?? 0.0), 2);
                $totalCost = round($tickCost + $flowCost, 2);
                $maintenanceHours = null;
                if (!empty($pipeline['last_maintenance_at'])) {
                    $maintenanceTs = strtotime((string)$pipeline['last_maintenance_at']);
                    if ($maintenanceTs !== false) {
                        $maintenanceHours = max(0, (int)floor(($nowTs - $maintenanceTs) / 3600));
                    }
                }

                $isCritical = in_array($status, ['critical', 'damaged'], true) || $conditionPct < 40.0;
                $isDegraded = !$isCritical && ($status === 'degraded' || $conditionPct < 70.0 || $transportLossPct >= 6.0);
                $maintenanceOverdue = $maintenanceHours === null || $maintenanceHours >= 72;
                $needsService = $maintenanceOverdue || $isCritical || $isDegraded;

                $pipeline['flow_bbl_h'] = round($flowBbl, 1);
                $pipeline['loss_bbl_h'] = $lossBbl;
                $pipeline['utilization_pct'] = $utilizationPct;
                $pipeline['tick_cost_est'] = $tickCost;
                $pipeline['flow_cost_est'] = $flowCost;
                $pipeline['total_cost_est'] = $totalCost;
                $pipeline['risk_factor'] = round((float)($pipeline['incident_risk_mult'] ?? 1.0), 2);
                $pipeline['maintenance_hours'] = $maintenanceHours;
                $pipeline['maintenance_overdue'] = $maintenanceOverdue;
                $pipeline['needs_service'] = $needsService;
                $pipeline['is_critical'] = $isCritical;
                $pipeline['is_degraded'] = $isDegraded;

                $pipelineSummary['total']++;
                $conditionSum += $conditionPct;
                $lossSum += $transportLossPct;
                $costSum += $totalCost;
                if ($isCritical) {
                    $pipelineSummary['critical']++;
                }
                if ($isDegraded) {
                    $pipelineSummary['degraded']++;
                }
                if ($needsService) {
                    $pipelineSummary['needs_service']++;
                }
                if ($maintenanceOverdue) {
                    $pipelineSummary['maintenance_overdue']++;
                }
            }
            unset($pipeline);

            if ($pipelineSummary['total'] > 0) {
                $pipelineSummary['avg_condition'] = round($conditionSum / $pipelineSummary['total'], 1);
                $pipelineSummary['avg_loss'] = round($lossSum / $pipelineSummary['total'], 2);
                $pipelineSummary['avg_cost'] = round($costSum / $pipelineSummary['total'], 2);
            }
        } catch (Throwable $e) {
            GameLog::error('logistics.php', 'pipeline data load FAILED', $e, ['player_id' => $playerId]);
        }
    }

    if (class_exists('TechnicalTeamService')) {
        try {
            $techSvc = new TechnicalTeamService($playerId);
            $hseBonus = $techSvc->getHSEBonus();

            $pipelineEngineerStmt = $db->prepare("
                SELECT COUNT(*)
                FROM technical_staff
                WHERE player_id = ?
                  AND spec_code = 'pipeline_engineer'
                  AND status IN ('active','busy')
                  AND (fired_at IS NULL OR fired_at > NOW())
            ");
            $pipelineEngineerStmt->execute([$playerId]);
            $pipelineSummary['engineers'] = (int)$pipelineEngineerStmt->fetchColumn();

            $pipelineHse['active'] = ((int)($hseBonus['active_hse'] ?? 0) > 0);
            $pipelineHse['label'] = (string)($hseBonus['label'] ?? '');
            $pipelineHse['failure_pct'] = (int)round((1 - (float)($hseBonus['failure_reduction'] ?? 1.0)) * 100);
            $pipelineHse['catastrophe_pct'] = (int)round((1 - (float)($hseBonus['catastrophe_mult'] ?? 1.0)) * 100);
            $pipelineHse['pipelines'] = (int)($hseBonus['total_pipelines'] ?? $pipelineSummary['total']);
            $pipelineHse['supervised_units'] = (int)($hseBonus['supervised_units'] ?? 0);

            $officerCoverage = (int)($hseBonus['officer_coverage'] ?? 0);
            $engineerCoverage = (int)($hseBonus['engineer_coverage'] ?? 0);
            if (!$pipelineHse['active']) {
                $pipelineHse['state'] = 'none';
            } elseif ($officerCoverage < 100 || $engineerCoverage < 100) {
                $pipelineHse['state'] = 'partial';
            } else {
                $pipelineHse['state'] = 'full';
            }
        } catch (Throwable $e) {
            GameLog::error('logistics.php', 'pipeline support data FAILED', $e, ['player_id' => $playerId]);
        }
    }

    $lossWells = array_values(array_filter($wells, static fn(array $well): bool => (float)($well['loss'] ?? 0) > 0));
    usort($lossWells, static fn(array $a, array $b): int => ((float)($b['loss'] ?? 0)) <=> ((float)($a['loss'] ?? 0)));
    $lossWells = array_slice($lossWells, 0, 3);

    // Worst pipelines: top 3 non-building by condition_pct ASC, secondary transport_loss DESC
    $worstPipelines = array_values(array_filter($pipelines, static fn(array $p): bool =>
        !in_array((string)($p['status'] ?? ''), ['building', 'disabled'], true)
    ));
    usort($worstPipelines, static function (array $a, array $b): int {
        $condCmp = ((float)($a['condition_pct'] ?? 100)) <=> ((float)($b['condition_pct'] ?? 100));
        if ($condCmp !== 0) return $condCmp;
        return ((float)($b['transport_loss'] ?? 0)) <=> ((float)($a['transport_loss'] ?? 0));
    });
    $worstPipelines = array_slice($worstPipelines, 0, 3);

    $costWells = $wells;
    usort($costWells, static fn(array $a, array $b): int => ((float)($b['cost'] ?? 0)) <=> ((float)($a['cost'] ?? 0)));
    $costWells = array_slice($costWells, 0, 3);

    $hubHotspots = [];
    foreach ($hubCards as $card) {
        $hub = $card['hub'] ?? [];
        $lastStats = $card['last_stats'] ?? [];
        $condPct = (float)($hub['condition_pct'] ?? 100);
        $loadPct = (float)($lastStats['load_pct'] ?? 0);
        $lostBbl = (float)($lastStats['lost_volume_bbl'] ?? 0);

        $score = 0.0;
        if ($loadPct > 80) {
            $score += $loadPct;
        }
        if ($condPct < 60) {
            $score += (60 - $condPct) * 2;
        }
        if ($lostBbl > 0) {
            $score += min(50.0, $lostBbl);
        }

        if ($score <= 0) {
            continue;
        }

        $hubHotspots[] = [
            'hub_id' => (int)($hub['id'] ?? 0),
            'name' => (string)($hub['name'] ?? ('Hub #' . (int)($hub['id'] ?? 0))),
            'condition_pct' => $condPct,
            'load_pct' => $loadPct,
            'lost_bbl' => $lostBbl,
            'score' => $score,
        ];
    }
    usort($hubHotspots, static fn(array $a, array $b): int => ($b['score'] <=> $a['score']));
    $hubHotspots = array_slice($hubHotspots, 0, 3);

    $logisticsRecommendations = [];
    if (!empty($hubUnassigned)) {
        $logisticsRecommendations[] = [
            'tone' => 'warn',
            'title' => t('logistics.insight_reco_unassigned_title'),
            'text' => t('logistics.insight_reco_unassigned_text', ['count' => count($hubUnassigned)]),
            'cta_label' => t('logistics.insight_reco_cta_unassigned'),
            'cta_href' => '#logistics-unassigned-heading',
        ];
    }
    if (!empty($hubHotspots)) {
        $logisticsRecommendations[] = [
            'tone' => 'danger',
            'title' => t('logistics.insight_reco_hubs_title'),
            'text' => t('logistics.insight_reco_hubs_text'),
            'cta_label' => t('logistics.insight_reco_cta_hubs'),
            'cta_href' => '#logistics-hubs-heading',
        ];
    }
    if ($lossPct >= 8 || !empty($lossWells)) {
        $logisticsRecommendations[] = [
            'tone' => 'info',
            'title' => t('logistics.insight_reco_optimizer_title'),
            'text' => t('logistics.insight_reco_optimizer_text', ['pct' => number_format($lossPct, 1, ',', ' ')]),
            'cta_label' => t('logistics.insight_reco_cta_optimizer'),
            'cta_href' => '#btn-logistics-open',
        ];
    }
    if (empty($logisticsRecommendations)) {
        $logisticsRecommendations[] = [
            'tone' => 'ok',
            'title' => t('logistics.insight_reco_ok_title'),
            'text' => t('logistics.insight_reco_ok_text'),
            'cta_label' => t('logistics.insight_reco_cta_transport'),
            'cta_href' => '#logistics-transport-heading',
        ];
    }

    $logisticsInsights = [
        'loss_wells'      => $lossWells,
        'cost_wells'      => $costWells,
        'hub_hotspots'    => $hubHotspots,
        'worst_pipelines' => $worstPipelines,
        'unassigned_count' => count($hubUnassigned),
        'recommendations' => array_slice($logisticsRecommendations, 0, 3),
    ];

    // Aktywne kursy drogowe (P1.2) / Active road trips (P1.2)
    $activeRoadTrips = [];
    if (class_exists('RoadTransportService')) {
        try {
            $roadSvc = new RoadTransportService($db);
            $activeRoadTrips = $roadSvc->getActiveTripsForPlayer($playerId);
        } catch (Throwable $e) {
            GameLog::error('logistics.php', 'road trips data load FAILED', $e, ['player_id' => $playerId]);
        }
    }

    $viewData = array_merge(GameShell::data($playerId), [
        'summary'          => $summary,
        'totals'           => $totals,
        'wells'            => $wells,
        'transportMix'     => $transportMix,
        'cooldown'         => $cooldown,
        'efficiency'       => $efficiency,
        'lossPct'          => $lossPct,
        'alerts'           => $alerts,
        'csrfToken'        => CSRF::generateToken(),
        // Hub data (system-owned hubs)
        'hubCards'         => $hubCards,
        'hubAlerts'        => $hubAlerts,
        'hubRegions'       => $hubRegions,
        'hubUnassigned'    => $hubUnassigned,
        'unassignedPage'   => 1,
        'unassignedTotalPages' => 1,
        'unassignedTotal'  => count($hubUnassigned),
        'hubAvailByRegion' => $hubAvailByRegion,
        'hubIncidents'     => $hubIncidents,
        'logisticsInsights'=> $logisticsInsights,
        'pipelines'        => $pipelines,
        'pipelineSummary'  => $pipelineSummary,
        'pipelineHse'      => $pipelineHse,
        'marineDeliveries' => $marineDeliveries,
        'marineHistory'    => $marineHistory,
        'marineInTransitBbl' => $marineInTransitBbl,
        // Building pipelines are already in $pipelines - filter here for convenience
        'buildingPipelines' => array_values(array_filter($pipelines, static fn(array $p): bool => ($p['status'] ?? '') === 'building')),
        // P1.2: active road trips in transit / aktywne kursy drogowe w tranzycie
        'activeRoadTrips'  => $activeRoadTrips,
    ]);

    $pageTitle = t('logistics.page_title');
    $extraHead = '<meta name="csrf-token" content="' . htmlspecialchars($viewData['csrfToken'], ENT_QUOTES) . '">';
    $extraCss = ['/assets/css/logistics.css'];
    $extraJs = ['/assets/js/logistics.js', '/assets/js/logistics_hubs.js'];
    $gameShellTitle = t('logistics.page_title');
    $gameShellView = __DIR__ . '/templates/views/logistics/main.php';

    require_once __DIR__ . '/templates/header.php';
    extract($viewData, EXTR_SKIP);
    require __DIR__ . '/templates/components/game_shell.php';
    require_once __DIR__ . '/templates/footer.php';
} catch (Throwable $e) {
    GameLog::error('logistics.php', 'Unhandled exception', $e);
    http_response_code(500);
    echo t('common.app_error');
} finally {
    GameLog::pageEnd('logistics.php', $_pageStart);
}
