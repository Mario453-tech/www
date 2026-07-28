<?php

/** @var int $playerId */
/** @var PDO $db */
/** @var callable(mixed):bool $isLocalRegionLocked */
/** @var callable(?string):?int $hoursSince */
/** @var PipelineStaffingManagementService $pipelineStaffingMgmt */
/** @var ProtectionService|null $protSvc */
/** @var callable(array<string,mixed>):array<int,string> $protEffectLines */
/** @var array<int,array<string,mixed>> $wells */
/** @var array<string,mixed> $totals */
/** @var array<string,array<string,int|float>> $transportMix */
/** @var float $efficiency */
/** @var float $lossPct */
/** @var array<int,array<string,mixed>> $alerts */
/** @var array<int|string,mixed> $hubAvailByRegion */
/** @var array<int,array<string,mixed>> $hubCards */
/** @var array<int,array<string,mixed>> $hubStaffingViewByHub */
/** @var array<int,array<string,mixed>> $hubAlerts */
/** @var array<int,array<string,mixed>> $hubUnassigned */
/** @var array<int,array<string,mixed>> $hubIncidents */
/** @var array<int,array<string,mixed>> $playerHubRegions */
/** @var array<int,array<string,mixed>> $hubTypeOptions */
/** @var int $unassignedPage */
/** @var int $unassignedTotalPages */
/** @var int $unassignedTotal */
/** @var array<int,array<string,mixed>> $pipelines */
/** @var array<int,array<string,mixed>> $pipelineStaffingViewByPipeline */
/** @var array<string,mixed> $pipelineStaffingClientPayload */
/** @var array<string,mixed> $pipelineSummary */
/** @var array<string,mixed> $pipelineHse */
/** @var array<string,mixed> $logisticsInsights */
/** @var array<int,array<string,mixed>> $activeRoadTrips */
/** @var int $activeRoadTripsTotal */
/** @var int $activeRoadTripsPage */
/** @var int $activeRoadTripsTotalPages */
/** @var array<int,array<string,mixed>> $roadProtectionWells */
/** @var array<int,array<string,mixed>> $roadProtectionOptions */
/** @var array<int,array<string,mixed>> $hubProtectionTargets */
/** @var array<int,array<string,mixed>> $hubProtectionOptions */
/** @var array<int,array<string,mixed>> $pipelineProtectionTargets */
/** @var array<int,array<string,mixed>> $pipelineProtectionOptions */
/** @var float $storageBbl */
/** @var int $storagePct */
/** @var array<string,string> $staffingFlash */

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
            AND spec_code = 'pipeline_engineer'
            AND status IN ('active','busy')
            AND (fired_at IS NULL OR fired_at > NOW())"
    );
    $pipelineEngineerStmt->execute([$playerId]);
    $pipelineSummary['engineers'] = (int)($pipelineEngineerStmt->fetchColumn() ?? 0);

    foreach ($pipelineRows as $pipe) {
 // Hide pipelines in regions blocked by local work permits.
 // Ukrywa rurociagi w regionach zablokowanych przez brak pozwolen.
        if ($isLocalRegionLocked($pipe['region_id'] ?? 0)) {
            continue;
        }
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

if ($pipelines !== []) {
    try {
        $pipelineStaffingViewByPipeline = $pipelineStaffingMgmt->buildPipelineStaffingView($playerId, $pipelines);
        $pipelineStaffingClientPayload = $pipelineStaffingMgmt->buildClientPayload(
            $pipelineStaffingViewByPipeline
        );
    } catch (Throwable $e) {
        GameLog::error('logistics', 'Pipeline staffing data load failed', $e, ['player_id' => $playerId]);
        $pipelineStaffingViewByPipeline = [];
        $pipelineStaffingClientPayload = ['pipelines' => [], 'candidates' => []];
    }
}

// Load pipeline protection after pipelines because target_id references well_pipelines.id.
// Laduje ochrone po rurociagach, bo target_id wskazuje well_pipelines.id.
if ($protSvc !== null && $pipelines !== []) {
    try {
        foreach ($protSvc->getAvailableOptions($playerId, 'pipeline', 'pipeline_guard') as $opt) {
            $opt['effect_lines'] = $protEffectLines($opt['effects']);
            $pipelineProtectionOptions[] = $opt;
        }
        $pipelineIds = [];
        foreach ($pipelines as $pipe) {
            $pipeId = (int)($pipe['id'] ?? 0);
            if ($pipeId > 0 && ($pipe['status'] ?? '') !== 'building') {
                $pipelineIds[] = $pipeId;
            }
        }
        $activePipelineProtections = $protSvc->getActiveProtections(
            $playerId,
            'pipeline',
            $pipelineIds,
            'pipeline_guard'
        );


        foreach ($pipelines as $pipe) {
            $pipeId = (int)($pipe['id'] ?? 0);
            if ($pipeId <= 0 || ($pipe['status'] ?? '') === 'building') {
                continue;
            }
            $legLabel = ($pipe['leg'] ?? 'inbound') === 'outbound'
                ? tPlain('protection.pipeline_leg_outbound')
                : tPlain('protection.pipeline_leg_inbound');
            $activeProt = $activePipelineProtections[$pipeId] ?? null;
            $pipelineProtectionTargets[] = [
                'id'     => $pipeId,
                'name'   => tPlain('protection.pipeline_target_leg', ['id' => $pipeId, 'leg' => $legLabel]),
                'active' => $activeProt === null ? null : [
                    'name'    => (string)$activeProt['option_name'],
                    'ends_at' => (string)$activeProt['ends_at'],
                ],
            ];
        }
    } catch (Throwable $e) {
        GameLog::error('logistics', 'Pipeline protection load failed', $e, ['player' => $playerId]);
    }
}

// Load wells assigned to hubs without a pipeline.
// Laduje odwierty przypisane do hubow bez rurociagu.
$wellsWithoutPipeline = [];
try {
    $woPipelineStmt = $db->prepare("
        SELECT w.id, w.name AS well_name, w.status AS well_status,
               w.location_name, w.transport_type, w.region_id,
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

// Hide pipeline candidates until the local work permit is granted.
// Ukrywa kandydatow do czasu uzyskania pozwolenia na prace lokalne.
if (!empty($lockedRegionSet)) {
    $wellsWithoutPipeline = array_values(array_filter(
        $wellsWithoutPipeline,
        static fn(array $w): bool => !$isLocalRegionLocked($w['region_id'] ?? 0)
    ));
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
$marineBuffers      = [];
$marineMinLoadBbl   = 0.0;
$marineCfg        = TransportConfigService::getTypeConfig($db, 'tankowiec');
$marineMinLoadBbl = max(0.0, (float)($marineCfg['min_load_bbl'] ?? 0.0));
try {
    $marineSvc          = new MarineDeliveryService($db);
    $marineDeliveries   = $marineSvc->getActiveForPlayer($playerId);
    $marineBuffers      = $marineSvc->getBufferedForPlayer($playerId, $marineMinLoadBbl);
    $marineHistory      = $marineSvc->getHistoryForPlayer($playerId, 10);
    $marineInTransitBbl = $marineSvc->getInTransitBbl($playerId);
} catch (Throwable $e) {
    GameLog::error('logistics', 'MarineDeliveryService load failed', $e, ['player' => $playerId]);
}

if ($marineDeliveries === [] || $marineBuffers === [] || $marineHistory === [] || $marineInTransitBbl <= 0.0) {
    $marineFallback = MarineDeliveryService::loadPanelFallback($db, $playerId, $marineMinLoadBbl);
    if ($marineDeliveries === []) {
        $marineDeliveries = $marineFallback['deliveries'];
    }
    if ($marineBuffers === []) {
        $marineBuffers = $marineFallback['buffers'];
    }
    if ($marineHistory === []) {
        $marineHistory = $marineFallback['history'];
    }
    if ($marineInTransitBbl <= 0.0) {
        $marineInTransitBbl = $marineFallback['in_transit_bbl'];
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
    'hubStaffingViewByHub',
    'hubAlerts',
    'hubUnassigned',
    'hubIncidents',
    'playerHubRegions',
    'hubTypeOptions',
    'unassignedPage',
    'unassignedTotalPages',
    'unassignedTotal',
    'pipelines',
    'pipelineStaffingViewByPipeline',
    'pipelineStaffingClientPayload',
    'pipelineSummary',
    'pipelineHse',
    'logisticsInsights',
    'activeRoadTrips',
    'activeRoadTripsTotal',
    'activeRoadTripsPage',
    'activeRoadTripsTotalPages',
    'roadProtectionWells',
    'roadProtectionOptions',
    'hubProtectionTargets',
    'hubProtectionOptions',
    'pipelineProtectionTargets',
    'pipelineProtectionOptions',
    'marineDeliveries',
    'marineBuffers',
    'marineMinLoadBbl',
    'marineHistory',
    'marineInTransitBbl',
    'storageBbl',
    'storagePct',
    'staffingFlash'
);
$viewData = array_merge($viewData, GameShell::data($playerId));

return $viewData;
