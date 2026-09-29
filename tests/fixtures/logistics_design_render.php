<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/src/i18n.php';
$_SESSION = ['locale' => $argv[1] ?? 'pl'];
class CSRF { public static function generateToken(): string { return 'fixture'; } public static function field(): string { return '<input type="hidden" name="csrf_token" value="fixture">'; } }
function url(string $path, array $params = []): string { return '/' . $path . ($params ? '?' . http_build_query($params) : ''); }

$staffingFlash = [];
$efficiency = 90.0;
$lossPct = 10.0;
$totals = ['transported' => 298.3, 'loss' => 33.3, 'cost' => 3156.72];
$wells = [
    ['id' => 54, 'well_name' => 'Rumaila, Irak', 'well_type' => 'onshore', 'status' => 'active', 'transport' => 'ciezarowki', 'capacity_pct' => 80.0, 'transported' => 133.1, 'loss' => 33.3, 'cost' => 2661.12],
    ['id' => 58, 'well_name' => 'Zatoka Perska', 'well_type' => 'offshore', 'status' => 'active', 'transport' => 'tankowiec', 'capacity_pct' => 110.0, 'transported' => 165.2, 'loss' => 0.0, 'cost' => 495.6],
];
$activeWellCount = 2;
$transportMix = [
    'ciezarowki' => ['count' => 1, 'transported' => 133.1, 'loss' => 33.3, 'cost' => 2661.12],
    'tankowiec' => ['count' => 1, 'transported' => 165.2, 'loss' => 0.0, 'cost' => 495.6],
    'rurociag' => ['count' => 0, 'transported' => 0.0, 'loss' => 0.0, 'cost' => 0.0],
    'nieustawiony' => ['count' => 0, 'transported' => 0.0, 'loss' => 0.0, 'cost' => 0.0],
];
$totalTransported = 298.3;
$totalLoss = 33.3;
$storageBbl = 2300.0;
$storagePct = 50;
$activeRoadTripsTotal = 1;
$activeRoadTrips = [['well_id' => 54, 'well_name' => 'Rumaila, Irak', 'volume_bbl' => 253.9, 'trips_count' => 1, 'truck_type' => 'standard', 'eta_at' => date('Y-m-d H:i:s', time() + 2340), 'seconds_remaining' => 2340]];
$activeRoadTripsPage = 1;
$activeRoadTripsTotalPages = 1;
$alerts = [['type' => 'warn', 'text' => t('logistics.alert_loss', ['bbl' => '33,3'])]];
$hubCards = [];
foreach ([1, 2] as $id) {
    $hubCards[] = [
        'hub' => ['id' => $id, 'name' => 'Hub testowy ' . $id, 'status' => 'active', 'region_id' => 1,
            'region_name' => 'Bliski Wschód', 'zone_key' => 'A1', 'hub_type' => 'small',
            'condition_pct' => $id === 1 ? 35.2 : 100.0, 'assigned_count' => 1,
            'slot_limit' => 3, 'level' => 1, 'work_mode' => 'standard'],
        'last_stats' => ['load_pct' => 65.0], 'wells' => [], 'ownership' => 'owned',
        'status_class' => 'badge-ok', 'max_level' => 3, 'can_upgrade' => false,
    ];
}
$hubAlerts = [];
$hubAvailByRegion = [['region_id' => 1, 'region_name' => 'Bliski Wschód', 'hubs' => [
    ['id' => 21, 'name' => 'Hub Alpha', 'slots_avail' => 2, 'slot_limit' => 4, 'hub_type' => 'small',
        'status' => 'active', 'acquisition_type' => 'used', 'condition_pct' => 80.0, 'buy_price' => 30000.0],
    ['id' => 22, 'name' => 'Hub Beta', 'slots_avail' => 3, 'slot_limit' => 4, 'hub_type' => 'medium',
        'status' => 'active', 'acquisition_type' => 'new', 'condition_pct' => 100.0, 'buy_price' => 60000.0],
]]];
$hubUnassigned = [];
$hubIncidents = [[
    'id' => 1, 'source' => 'hub', 'severity' => 'critical', 'message' => 'Awaria huba testowego.',
    'hub_name' => 'Hub testowy', 'meta_json' => '{}', 'created_at' => '2026-09-29 10:00:00',
]];
$hubIncidentsTotal = 1;
$hubIncidentsPage = 1;
$hubIncidentsTotalPages = 1;
$hubIncidentQuery = '';
$hubIncidentSeverity = '';
$hubStaffingViewByHub = [];
$playerHubRegions = [];
$hubTypeOptions = [];
$unassignedPage = 1;
$unassignedTotalPages = 1;
$unassignedTotal = 0;
$pipelines = [];
foreach ([1, 2] as $id) {
    $pipelines[] = ['id' => $id, 'name' => 'Rurociąg testowy ' . $id, 'well_name' => 'Odwiert #' . $id,
        'status' => 'active', 'pipeline_type' => 'standard', 'condition_pct' => 80.0,
        'capacity_bbl_h' => 200.0, 'flow_bbl_h' => 160.0];
}
$pipelineStaffingViewByPipeline = [];
$pipelineStaffingClientPayload = ['pipelines' => [], 'candidates' => []];
$pipelineSummary = ['total' => 2, 'critical' => 0, 'needs_service' => 0, 'avg_condition' => 80.0, 'avg_cost' => 0.0];
$pipelineHse = [];
$logisticsInsights = ['recommendations' => [['tone' => 'ok', 'title' => 'Transport działa', 'text' => 'Brak pilnych decyzji.', 'cta_href' => '#logistics-transport-section', 'cta_label' => 'Transport']]];
$roadProtectionWells = [];
$roadProtectionOptions = [];
$hubProtectionTargets = [];
$hubProtectionOptions = [];
$pipelineProtectionTargets = [];
$pipelineProtectionOptions = [];
$marineDeliveries = [];
$marineBuffers = [['well_id' => 58, 'well_name' => 'Zatoka Perska', 'marine_buffer_bbl' => 1826.2, 'min_load_bbl' => 4000.0]];
$marineHistory = [];
$marineMinLoadBbl = 4000.0;
$marineInTransitBbl = 0.0;
$wellsWithoutPipeline = [];

require dirname(__DIR__, 2) . '/templates/views/logistics/main.php';
