<?php
require_once __DIR__ . '/../src/init.php';

Auth::requireLogin();

$playerId = Auth::getUserId();
$db       = Database::getInstance()->getConnection();

$srcDir = __DIR__ . '/../src';
require_once $srcDir . '/LogisticsService.php';
require_once $srcDir . '/HubService.php';
require_once $srcDir . '/HubIncidentService.php';
require_once $srcDir . '/HubViewService.php';
require_once $srcDir . '/HubEconomyService.php';
require_once $srcDir . '/RoadTransportService.php';
require_once $srcDir . '/TechnicalTeamService.php';
require_once $srcDir . '/WellPipelineService.php';

$logisticsSvc = new LogisticsService($playerId);
$hubSvc       = new HubService($db);
$econSvc      = new HubEconomyService($hubSvc);
$viewSvc      = new HubViewService($db, $hubSvc, $econSvc);

$summary = $logisticsSvc->getCurrentSummary();
$wells   = $summary['wells'] ?? [];
$totals  = $summary['totals'] ?? ['transported' => 0, 'loss' => 0, 'cost' => 0];

$wellSummaryById = [];
foreach ($wells as $wellSummary) {
    $wellSummaryById[(int)($wellSummary['id'] ?? 0)] = $wellSummary;
}

$transportMix = [
    'nieustawiony' => ['count' => 0, 'transported' => 0, 'loss' => 0, 'cost' => 0],
    'rurociag' => ['count' => 0, 'transported' => 0, 'loss' => 0, 'cost' => 0],
    'ciezarowki' => ['count' => 0, 'transported' => 0, 'loss' => 0, 'cost' => 0],
    'tankowiec' => ['count' => 0, 'transported' => 0, 'loss' => 0, 'cost' => 0],
];
foreach ($wells as $wellRow) {
    $transportType = $wellRow['transport'] ?? 'nieustawiony';
    if (!isset($transportMix[$transportType])) {
        $transportMix[$transportType] = ['count' => 0, 'transported' => 0, 'loss' => 0, 'cost' => 0];
    }
    $transportMix[$transportType]['count']++;
    $transportMix[$transportType]['transported'] += $wellRow['transported'] ?? 0;
    $transportMix[$transportType]['loss'] += $wellRow['loss'] ?? 0;
    $transportMix[$transportType]['cost'] += $wellRow['cost'] ?? 0;
}

$totalTransported = (float)$totals['transported'];
$totalLoss        = (float)$totals['loss'];
$efficiency       = ($totalTransported + $totalLoss) > 0
    ? round($totalTransported / ($totalTransported + $totalLoss) * 100, 1)
    : 100.0;
$lossPct          = ($totalTransported + $totalLoss) > 0
    ? round($totalLoss / ($totalTransported + $totalLoss) * 100, 1)
    : 0.0;

$alerts = [];
if ($totalLoss > 0) {
    $alerts[] = [
        'type' => 'warn',
        'text' => t('logistics.alert_loss', ['bbl' => number_format($totalLoss, 1, ',', ' ')]),
    ];
}

$hubCards          = [];
$hubAlerts         = [];
$hubUnassigned     = [];
$hubIncidents      = [];
$playerHubRegions  = [];
$hubTypeOptions    = [];
$hubAvailByRegion  = [];
$unassignedPage    = 1;
$unassignedTotal   = 0;
$unassignedTotalPages = 1;

$pipelines = [];
$pipelineSummary = [
    'total' => 0,
    'critical' => 0,
    'needs_service' => 0,
    'maintenance_overdue' => 0,
    'avg_condition' => 0.0,
    'avg_cost' => 0.0,
    'engineers' => 0,
];
$pipelineHse = [
    'state' => 'none',
    'pipelines' => 0,
    'supervised_units' => 0,
    'failure_pct' => 0,
    'cat_pct' => 0,
    'catastrophe_pct' => 0,
    'label' => '',
];
$logisticsInsights = [
    'unassigned_count' => 0,
    'loss_wells' => [],
    'cost_wells' => [],
    'hub_hotspots' => [],
    'recommendations' => [],
];
$activeRoadTrips = [];

$hoursSince = static function (?string $dateTime): ?int {
    if (!$dateTime) {
        return null;
    }

    $timestamp = strtotime($dateTime);
    if ($timestamp === false) {
        return null;
    }

    return max(0, (int)floor((time() - $timestamp) / 3600));
};

try {
 // Private ownership model: "Moje huby" = owned + rented; browser = market hubs to buy/rent.
    $hubCards         = $viewSvc->getMyHubCards($playerId);
    $hubAlerts        = $viewSvc->getAlerts($playerId);
    $hubAvailByRegion = $viewSvc->getMarketHubsByRegion($playerId);
    $hubUnassignedAll = $hubSvc->getUnassignedWells($playerId);
 // Use HubIncidentService to get only hub_incident_* events (all, regardless of is_read).
 // HubService::getUnreadEvents() returns all event types filtered by is_read=0 - wrong for incidents panel.
    $hubIncidentSvc   = new HubIncidentService($db, $hubSvc);
    $hubIncidents     = $hubIncidentSvc->getPlayerRecentIncidents($playerId, 20);

    $perPage = 5;
    $unassignedPage = max(1, (int)($_GET['unassigned_page'] ?? 1));
    $unassignedTotal = count($hubUnassignedAll);
    $unassignedTotalPages = (int)ceil($unassignedTotal / $perPage);
    $unassignedPage = min($unassignedPage, max(1, $unassignedTotalPages));
    $unassignedOffset = ($unassignedPage - 1) * $perPage;
    $hubUnassigned = array_slice($hubUnassignedAll, $unassignedOffset, $perPage);

 // Regions where the player operates (for the "buy new hub" modal).
    $regionIds = $hubSvc->getPlayerRegionIds($playerId);
    if (!empty($regionIds)) {
        $ph    = implode(',', array_fill(0, count($regionIds), '?'));
        $rStmt = $db->prepare("SELECT id, name FROM world_regions WHERE id IN ($ph) ORDER BY name");
        $rStmt->execute($regionIds);
        $playerHubRegions = $rStmt->fetchAll(PDO::FETCH_ASSOC);
    }

 // Hub type options with base build cost (for the "buy new hub" modal).
    foreach (['small', 'medium', 'large'] as $htype) {
        $defs = $hubSvc->getHubTypeDefaults($htype, 1);
        $hubTypeOptions[] = [
            'key'        => $htype,
            'build_cost' => (float)$defs['build_cost'],
            'slot_limit' => (int)$defs['slot_limit'],
            'nominal'    => (float)$defs['nominal_bph'],
        ];
    }
} catch (Throwable $e) {
    GameLog::error('logistics', 'Hub data load failed', $e, ['player' => $playerId]);
}

try {
    $roadTransportSvc = new RoadTransportService($db);
    $activeRoadTrips  = $roadTransportSvc->getActiveTripsForPlayer($playerId);
} catch (Throwable $e) {
    GameLog::error('logistics', 'Road transport data load failed', $e, ['player' => $playerId]);
}

try {
    $pipelineSvc = new WellPipelineService($db);
    $pipelineRows = [];

    foreach ($pipelineSvc->getPlayerPipelines($playerId) as $pipe) {
        $pipelineRows[(int)($pipe['id'] ?? 0)] = $pipe;
    }

    foreach ($pipelineSvc->getBuildingForPlayer($playerId) as $buildingPipe) {
        $pipelineId = (int)($buildingPipe['id'] ?? 0);
        $pipelineRows[$pipelineId] = array_merge($pipelineRows[$pipelineId] ?? [], $buildingPipe);
    }

    $pipelineEngineerStmt = $db->prepare(
        "SELECT COUNT(*)
           FROM technical_staff
          WHERE player_id = ?
            AND specialization = 'pipeline_engineer'
            AND status IN ('active','busy')
            AND (fired_at IS NULL OR fired_at > NOW())"
    );
    $pipelineEngineerStmt->execute([$playerId]);
    $pipelineSummary['engineers'] = (int)($pipelineEngineerStmt->fetchColumn() ?? 0);

    foreach ($pipelineRows as $pipe) {
        $status = (string)($pipe['status'] ?? 'active');
        $wellId = (int)($pipe['well_id'] ?? $pipe['source_well_id'] ?? 0);
        $wellSummary = $wellSummaryById[$wellId] ?? null;
        $usesPipeline = $status !== 'building'
            && (($pipe['transport_type'] ?? '') === 'rurociag' || (($wellSummary['transport'] ?? 'nieustawiony') === 'rurociag'));
        $flowBblH = $usesPipeline ? (float)($wellSummary['transported'] ?? 0.0) : 0.0;
        $capacityBblH = (float)($pipe['capacity_bbl_h'] ?? $pipe['real_capacity_bph'] ?? 0.0);
        $lossPctCurrent = (float)($pipe['transport_loss'] ?? $pipe['transport_loss_pct'] ?? 0.0);
        $lossBblH = $usesPipeline ? (float)($wellSummary['loss'] ?? 0.0) : 0.0;
        $utilizationPct = $capacityBblH > 0.0 ? round(($flowBblH / $capacityBblH) * 100, 1) : 0.0;
        $maintenanceHours = $hoursSince($pipe['last_maintenance_at'] ?? $pipe['last_inspected_at'] ?? null);
        $maintenanceOverdue = $status !== 'building' && $maintenanceHours !== null && $maintenanceHours >= 72;
        $conditionPct = (float)($pipe['condition_pct'] ?? 100.0);
        $needsService = $status !== 'building' && ($conditionPct < 70.0 || $lossPctCurrent >= 5.0 || $maintenanceOverdue);
        $isCritical = in_array($status, ['critical', 'damaged'], true) || $conditionPct < 40.0;
        $isDegraded = !$isCritical && ($status === 'degraded' || $conditionPct < 70.0);
        $totalCostEst = $usesPipeline
            ? round((float)($pipe['opex_per_tick'] ?? 0.0) + ($flowBblH * (float)($pipe['opex_per_bbl'] ?? 0.0)), 2)
            : 0.0;

        $pipe['flow_bbl_h'] = round($flowBblH, 2);
        $pipe['capacity_bbl_h'] = round($capacityBblH, 2);
        $pipe['loss_bbl_h'] = round($lossBblH, 2);
        $pipe['utilization_pct'] = round($utilizationPct, 1);
        $pipe['maintenance_hours'] = $maintenanceHours;
        $pipe['maintenance_overdue'] = $maintenanceOverdue;
        $pipe['needs_service'] = $needsService;
        $pipe['is_critical'] = $isCritical;
        $pipe['is_degraded'] = $isDegraded;
        $pipe['risk_factor'] = round((float)($pipe['incident_risk_mult'] ?? $pipe['incident_risk_factor'] ?? 1.0), 2);
        $pipe['total_cost_est'] = $totalCostEst;
        $pipelines[] = $pipe;
    }

    usort($pipelines, static function (array $left, array $right): int {
        $leftPriority = ($left['status'] ?? '') === 'building' ? 0 : 1;
        $rightPriority = ($right['status'] ?? '') === 'building' ? 0 : 1;
        if ($leftPriority !== $rightPriority) {
            return $leftPriority <=> $rightPriority;
        }

        return ((float)($left['condition_pct'] ?? 100.0) <=> (float)($right['condition_pct'] ?? 100.0));
    });

    $activePipelineCount = 0;
    $conditionSum = 0.0;
    $costSum = 0.0;
    foreach ($pipelines as $pipe) {
        $pipelineSummary['total']++;
        if (!empty($pipe['is_critical'])) {
            $pipelineSummary['critical']++;
        }
        if (!empty($pipe['needs_service'])) {
            $pipelineSummary['needs_service']++;
        }
        if (!empty($pipe['maintenance_overdue'])) {
            $pipelineSummary['maintenance_overdue']++;
        }

        if (($pipe['status'] ?? '') !== 'building') {
            $activePipelineCount++;
            $conditionSum += (float)($pipe['condition_pct'] ?? 100.0);
            $costSum += (float)($pipe['total_cost_est'] ?? 0.0);
        }
    }

    if ($activePipelineCount > 0) {
        $pipelineSummary['avg_condition'] = round($conditionSum / $activePipelineCount, 1);
        $pipelineSummary['avg_cost'] = round($costSum / $activePipelineCount, 2);
    }

    $technicalSvc = new TechnicalTeamService($playerId);
    $hseBonus = $technicalSvc->getHSEBonus();

    $pipelineHse['pipelines'] = $pipelineSummary['total'];
    $pipelineHse['supervised_units'] = (int)($hseBonus['supervised_units'] ?? 0);
    $pipelineHse['failure_pct'] = (int)round((1.0 - (float)($hseBonus['failure_reduction'] ?? 1.0)) * 100);
    $pipelineHse['catastrophe_pct'] = (int)round((1.0 - (float)($hseBonus['catastrophe_mult'] ?? 1.0)) * 100);
    $pipelineHse['cat_pct'] = $pipelineHse['catastrophe_pct'];
    $pipelineHse['label'] = (string)($hseBonus['label'] ?? '');

    $hasOfficer = !empty($hseBonus['has_officer']);
    $hasEngineer = !empty($hseBonus['has_engineer']);
    $officerCoverage = (int)($hseBonus['officer_coverage'] ?? 0);
    $engineerCoverage = (int)($hseBonus['engineer_coverage'] ?? 0);

    if ($pipelineSummary['total'] <= 0 || (!$hasOfficer && !$hasEngineer)) {
        $pipelineHse['state'] = 'none';
    } elseif ($hasOfficer && $hasEngineer && $officerCoverage >= 100 && $engineerCoverage >= 100) {
        $pipelineHse['state'] = 'full';
    } else {
        $pipelineHse['state'] = 'partial';
    }
} catch (Throwable $e) {
    GameLog::error('logistics', 'Pipeline data load failed', $e, ['player' => $playerId]);
}

// Wells with active hub assignment but no pipeline yet (candidates for pipeline purchase)
$wellsWithoutPipeline = [];
try {
    $woPipelineStmt = $db->prepare("
        SELECT w.id, w.name AS well_name, w.status AS well_status,
               w.location_name, w.transport_type,
               h.id AS hub_id, h.name AS hub_name
          FROM wells w
          JOIN logistics_hub_assignments a ON a.well_id = w.id AND a.status = 'active'
          JOIN logistics_hubs h ON h.id = a.hub_id AND h.status NOT IN ('disabled','building')
          LEFT JOIN well_pipelines p ON p.well_id = w.id
         WHERE w.player_id = ?
           AND p.id IS NULL
           AND w.status NOT IN ('sold','seized','blowout','broken')
         ORDER BY w.id
    ");
    $woPipelineStmt->execute([$playerId]);
    $wellsWithoutPipeline = $woPipelineStmt->fetchAll();
} catch (Throwable $e) {
    GameLog::error('logistics', 'Wells without pipeline query failed', $e, ['player' => $playerId]);
}

$lossWells = array_values(array_filter(
    $wells,
    static fn(array $row): bool => (float)($row['loss'] ?? 0.0) > 0.0
));
usort(
    $lossWells,
    static fn(array $left, array $right): int => ((float)($right['loss'] ?? 0.0) <=> (float)($left['loss'] ?? 0.0))
);

$costWells = $wells;
usort(
    $costWells,
    static fn(array $left, array $right): int => ((float)($right['cost'] ?? 0.0) <=> (float)($left['cost'] ?? 0.0))
);

$hubHotspots = [];
foreach ($hubCards as $card) {
    $hub = $card['hub'] ?? [];
    $lastStats = $card['last_stats'] ?? [];
    $loadPct = (float)($lastStats['load_pct'] ?? 0.0);
    $conditionPct = (float)($hub['condition_pct'] ?? 100.0);
    $lostBbl = (float)($lastStats['lost_volume_bbl'] ?? 0.0);
    $score = 0.0;

    if ($loadPct >= 90.0) {
        $score += 3.0;
    } elseif ($loadPct >= 75.0) {
        $score += 1.5;
    }

    if ($conditionPct < 50.0) {
        $score += 3.0;
    } elseif ($conditionPct < 70.0) {
        $score += 1.5;
    }

    if ($lostBbl > 0.0) {
        $score += min(3.0, $lostBbl / 10.0);
    }

    if ($score <= 0.0) {
        continue;
    }

    $hubHotspots[] = [
        'name' => (string)($hub['name'] ?? ('Hub #' . (int)($hub['id'] ?? 0))),
        'load_pct' => round($loadPct, 1),
        'condition_pct' => round($conditionPct, 1),
        'lost_bbl' => round($lostBbl, 1),
        '_score' => $score,
    ];
}

usort($hubHotspots, static function (array $left, array $right): int {
    return ((float)($right['_score'] ?? 0.0) <=> (float)($left['_score'] ?? 0.0));
});
$hubHotspots = array_slice(array_map(static function (array $row): array {
    unset($row['_score']);
    return $row;
}, $hubHotspots), 0, 5);

$logisticsInsights['unassigned_count'] = $unassignedTotal;
$logisticsInsights['loss_wells'] = array_slice($lossWells, 0, 5);
$logisticsInsights['cost_wells'] = array_slice($costWells, 0, 5);
$logisticsInsights['hub_hotspots'] = $hubHotspots;

if ($unassignedTotal > 0) {
    $logisticsInsights['recommendations'][] = [
        'tone' => 'warn',
        'title' => t('logistics.insight_reco_unassigned_title'),
        'text' => t('logistics.insight_reco_unassigned_text', ['count' => $unassignedTotal]),
        'cta_href' => '#logistics-available-hubs-heading',
        'cta_label' => t('logistics.insight_reco_cta_unassigned'),
    ];
}

if (!empty($hubHotspots)) {
    $logisticsInsights['recommendations'][] = [
        'tone' => 'danger',
        'title' => t('logistics.insight_reco_hubs_title'),
        'text' => t('logistics.insight_reco_hubs_text'),
        'cta_href' => '#logistics-hubs-heading',
        'cta_label' => t('logistics.insight_reco_cta_hubs'),
    ];
}

if ($lossPct >= 8.0) {
    $logisticsInsights['recommendations'][] = [
        'tone' => 'info',
        'title' => t('logistics.insight_reco_optimizer_title'),
        'text' => t('logistics.insight_reco_optimizer_text', [
            'pct' => number_format($lossPct, 1, ',', ' '),
        ]),
        'cta_href' => '#logistics-overview-heading',
        'cta_label' => t('logistics.insight_reco_cta_optimizer'),
    ];
}

if ($logisticsInsights['recommendations'] === []) {
    $logisticsInsights['recommendations'][] = [
        'tone' => 'ok',
        'title' => t('logistics.insight_reco_ok_title'),
        'text' => t('logistics.insight_reco_ok_text'),
        'cta_href' => '#logistics-overview-heading',
        'cta_label' => t('logistics.insight_reco_cta_transport'),
    ];
}

$marineDeliveries   = [];
$marineHistory      = [];
$marineInTransitBbl = 0.0;
if (class_exists('MarineDeliveryService')) {
    try {
        $marineSvc          = new MarineDeliveryService($db);
        $marineDeliveries   = $marineSvc->getActiveForPlayer($playerId);
        $marineHistory      = $marineSvc->getHistoryForPlayer($playerId, 10);
        $marineInTransitBbl = $marineSvc->getInTransitBbl($playerId);
    } catch (Throwable $e) {
        GameLog::error('logistics', 'MarineDeliveryService load failed', $e, ['player' => $playerId]);
    }
}

$viewData = compact(
    'wells',
    'totals',
    'transportMix',
    'efficiency',
    'lossPct',
    'alerts',
    'hubAvailByRegion',
    'hubCards',
    'hubAlerts',
    'hubUnassigned',
    'hubIncidents',
    'playerHubRegions',
    'hubTypeOptions',
    'unassignedPage',
    'unassignedTotalPages',
    'unassignedTotal',
    'pipelines',
    'pipelineSummary',
    'pipelineHse',
    'logisticsInsights',
    'activeRoadTrips',
    'marineDeliveries',
    'marineHistory',
    'marineInTransitBbl'
);
$viewData = array_merge($viewData, GameShell::data($playerId));

$pageTitle      = t('logistics.page_title') . ' - OilCorp';
$gameShellTitle = t('logistics.page_title');
$gameShellView  = __DIR__ . '/../templates/views/logistics/main.php';
$extraCss       = ['/assets/css/logistics.css'];
$extraJs        = ['/assets/js/logistics_hubs.js'];

require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';
