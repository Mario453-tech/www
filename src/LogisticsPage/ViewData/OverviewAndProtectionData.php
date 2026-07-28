<?php

/** @var int $playerId */
/** @var PDO $db */
/** @var LogisticsService $logisticsSvc */
/** @var HubService $hubSvc */
/** @var HubViewService $viewSvc */
/** @var HubStaffingManagementService $hubStaffingMgmt */
/** @var string $srcDir */

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
$storagePct       = 0;
$storageBbl       = 0.0;

try {
    $storageRow = (new Storage($playerId))->getData();
    if (is_array($storageRow)) {
        $storageBbl = (float)($storageRow['used'] ?? 0.0);
        $storageCapacity = (float)($storageRow['capacity'] ?? 0.0);
        $storagePct = $storageCapacity > 0.0 ? (int)round(($storageBbl / $storageCapacity) * 100.0) : 0;
    }
} catch (Throwable $e) {
    GameLog::error('logistics', 'Storage snapshot load failed', $e, ['player_id' => $playerId]);
}

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
$hubStaffingViewByHub = [];
$playerHubRegions  = [];
$hubTypeOptions    = [];
$hubAvailByRegion  = [];
$unassignedPage    = 1;
$unassignedTotal   = 0;
$unassignedTotalPages = 1;

$pipelines = [];
$pipelineStaffingClientPayload = ['pipelines' => [], 'candidates' => []];
$pipelineSummary = [
    'total' => 0,
    'critical' => 0,
    'needs_service' => 0,
    'maintenance_overdue' => 0,
    'avg_condition' => 0.0,
    'avg_cost' => 0.0,
    'engineers' => 0,
];
$pipelineStaffingViewByPipeline = [];
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
$activeRoadTripsTotal = 0;
$activeRoadTripsPage = 1;
$activeRoadTripsTotalPages = 1;

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
 // Private ownership: "My hubs" includes owned and rented; browser lists market hubs.
 // Wlasnosc prywatna: "Moje huby" obejmuja wlasne i wynajete; przegladarka pokazuje rynek.
    $hubCards         = $viewSvc->getMyHubCards($playerId);
    $hubAlerts        = $viewSvc->getAlerts($playerId);
    $hubAvailByRegion = $viewSvc->getMarketHubsByRegion($playerId);
    $hubUnassignedAll = $hubSvc->getUnassignedWells($playerId);
 // Load all hub_incident_* events regardless of read state.
 // Laduje wszystkie zdarzenia hub_incident_* niezaleznie od stanu odczytu.
    $hubIncidentSvc   = new HubIncidentService($db, $hubSvc);
    $hubIncidents     = $hubIncidentSvc->getPlayerRecentIncidents($playerId, 20);

    $perPage = 5;
    $unassignedPage = max(1, (int)($_GET['unassigned_page'] ?? 1));
    $unassignedTotal = count($hubUnassignedAll);
    $unassignedTotalPages = (int)ceil($unassignedTotal / $perPage);
    $unassignedPage = min($unassignedPage, max(1, $unassignedTotalPages));
    $unassignedOffset = ($unassignedPage - 1) * $perPage;
    $hubUnassigned = array_slice($hubUnassignedAll, $unassignedOffset, $perPage);

 // Load operating regions for the new hub modal.
 // Laduje regiony dzialalnosci do modala nowego huba.
    $regionIds = $hubSvc->getPlayerRegionIds($playerId);
    if (!empty($regionIds)) {
        $ph    = implode(',', array_fill(0, count($regionIds), '?'));
        $rStmt = $db->prepare("SELECT id, name FROM world_regions WHERE id IN ($ph) ORDER BY name");
        $rStmt->execute($regionIds);
        $playerHubRegions = $rStmt->fetchAll(PDO::FETCH_ASSOC);
    }

 // Load hub types with base build costs.
 // Laduje typy hubow z bazowymi kosztami budowy.
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

if ($hubCards !== []) {
    try {
        $hubStaffingViewByHub = $hubStaffingMgmt->buildHubStaffingView($playerId, $hubCards);
    } catch (Throwable $e) {
        GameLog::error('logistics', 'Hub staffing view build failed', $e, ['player' => $playerId]);
        $hubStaffingViewByHub = [];
    }
}

try {
    $roadTransportSvc = new RoadTransportService($db);
    $activeRoadTripsAll = $roadTransportSvc->getActiveTripsForPlayer($playerId);
    $activeRoadTripsPerPage = 10;
    $activeRoadTripsTotal = count($activeRoadTripsAll);
    $activeRoadTripsTotalPages = (int)ceil($activeRoadTripsTotal / $activeRoadTripsPerPage);
    $activeRoadTripsPage = max(1, (int)($_GET['road_page'] ?? 1));
    $activeRoadTripsPage = min($activeRoadTripsPage, max(1, $activeRoadTripsTotalPages));
    $activeRoadTripsOffset = ($activeRoadTripsPage - 1) * $activeRoadTripsPerPage;
    $activeRoadTrips = array_slice($activeRoadTripsAll, $activeRoadTripsOffset, $activeRoadTripsPerPage);
} catch (Throwable $e) {
    GameLog::error('logistics', 'Road transport data load failed', $e, ['player' => $playerId]);
}

// Load universal protection data for roads, hubs and pipelines.
// Laduje dane uniwersalnej ochrony dla drog, hubow i rurociagow.
$roadProtectionWells = [];
$roadProtectionOptions = [];
$hubProtectionTargets = [];
$hubProtectionOptions = [];
$pipelineProtectionTargets = [];
$pipelineProtectionOptions = [];
$protSvc = null;

$protEffectLines = static function (array $effects): array {
    $lines = [];
    foreach ($effects as $key => $eff) {
        if (($eff['type'] ?? '') !== 'mult' || ($eff['value'] ?? 1.0) >= 1.0) {
            continue;
        }
        $strength = $eff['value'] <= 0.60 ? 'strong' : ($eff['value'] <= 0.85 ? 'medium' : 'light');
        $lines[] = tPlain('protection.effect.' . $strength, ['what' => tPlain('protection.risk.' . $key)]);
    }
    if ($lines !== []) {
        $lines[] = tPlain('protection.effect.disclaimer');
    }
    return $lines;
};

try {
    require_once $srcDir . '/ProtectionService.php';
    $protSvc = new ProtectionService($db);

    // Road transport.
    // Transport drogowy.
    $roadWellIds = [];
    foreach ($wells as $wellRow) {
        if (($wellRow['transport'] ?? '') === 'ciezarowki') {
            $roadWellIds[] = (int)$wellRow['id'];
        }
    }
    if ($roadWellIds !== []) {
        foreach ($protSvc->getAvailableOptions($playerId, 'road_transport', 'road_transport_guard') as $opt) {
            $opt['effect_lines'] = $protEffectLines($opt['effects']);
            $roadProtectionOptions[] = $opt;
        }

        $placeholders = implode(',', array_fill(0, count($roadWellIds), '?'));
        $nameStmt = $db->prepare(
            "SELECT id, COALESCE(NULLIF(name,''), location_name) AS well_name
               FROM wells
              WHERE player_id = ?
                AND id IN ({$placeholders})"
        );
        $nameStmt->execute(array_merge([$playerId], $roadWellIds));
        $wellNames = $nameStmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $activeRoadProtections = $protSvc->getActiveProtections(
            $playerId,
            'road_transport',
            $roadWellIds,
            'road_transport_guard'
        );

        foreach ($roadWellIds as $roadWellId) {
            $activeProt = $activeRoadProtections[$roadWellId] ?? null;
            $roadProtectionWells[] = [
                'id'     => $roadWellId,
                'name'   => (string)($wellNames[$roadWellId] ?? ''),
                'active' => $activeProt === null ? null : [
                    'name'    => (string)$activeProt['option_name'],
                    'ends_at' => (string)$activeProt['ends_at'],
                ],
            ];
        }
    }

    // Logistics hubs.
    // Huby logistyczne.
    if ($hubCards !== []) {
        foreach ($protSvc->getAvailableOptions($playerId, 'hub', 'hub_guard') as $opt) {
            $opt['effect_lines'] = $protEffectLines($opt['effects']);
            $hubProtectionOptions[] = $opt;
        }
        $hubIds = [];
        foreach ($hubCards as $card) {
            if (($card['ownership'] ?? '') !== 'owned') {
                continue;
            }
            $hubId = (int)(($card['hub'] ?? [])['id'] ?? 0);
            if ($hubId > 0) {
                $hubIds[] = $hubId;
            }
        }
        $activeHubProtections = $protSvc->getActiveProtections($playerId, 'hub', $hubIds, 'hub_guard');

        foreach ($hubCards as $card) {
            if (($card['ownership'] ?? '') !== 'owned') {
                continue;
            }
            $hub = $card['hub'] ?? [];
            $hubId = (int)($hub['id'] ?? 0);
            if ($hubId <= 0) {
                continue;
            }
            $activeProt = $activeHubProtections[$hubId] ?? null;
            $hubProtectionTargets[] = [
                'id'     => $hubId,
                'name'   => (string)($hub['name'] ?? ('Hub #' . $hubId)),
                'active' => $activeProt === null ? null : [
                    'name'    => (string)$activeProt['option_name'],
                    'ends_at' => (string)$activeProt['ends_at'],
                ],
            ];
        }
    }
} catch (Throwable $e) {
    GameLog::error('logistics', 'Protection data load failed', $e, ['player' => $playerId]);
}
