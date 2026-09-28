<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/src/i18n.php';
require_once dirname(__DIR__, 2) . '/src/AdminHub/ConfigFieldTrait.php';
$_SESSION = ['locale' => $argv[2] ?? 'pl'];
function asset(string $path): string { return $path; }
function url(string $path, array $params = []): string { return '/' . $path . ($params === [] ? '' : '?' . http_build_query($params)); }
class GameLog { public static function info(string $source, string $message): void {} }
class Security { public static function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); } }
class AdminAuth { public static function getAdminUsername(): string { return 'tester'; } }
class CSRF { public static function field(): string { return '<input type="hidden" name="csrf_token" value="test">'; } }

$surface = $argv[1] ?? 'overview';
if ($surface === 'incidents') {
    $incTotal = 4;
    $incPage = 1;
    $incTotalPages = 1;
    $incidents = [];
    foreach (range(1, 4) as $id) {
        $incidents[] = [
            'level' => $id === 2 ? 'medium' : 'minor', 'cause_type' => $id === 3 ? 'operator' : 'system',
            'message' => $id === 2 ? 'Awaria pompy ograniczyła produkcję odwiertu.' : ($id === 3 ? 'Operator źle skalibrował zawór ciśnieniowy.' : 'Czujnik temperatury zgłosił odczyt poza normą.'),
            'well_id' => 54, 'well_name' => 'Rumaila, Irak', 'prod_drop' => $id === 2 ? 39 : 0,
            'cost' => $id === 2 ? 170500 : 0, 'deg_damage' => $id === 2 ? 6 : 0,
            'auto_repair' => $id !== 2, 'repaired_at' => $id === 2 ? null : '2026-09-28 16:00:00',
            'created_at' => "2026-09-28 1{$id}:30:00",
        ];
    }
    $hubIncTotal = 206;
    $hubIncidents = [];
    foreach (range(1, 4) as $id) {
        $hubIncidents[] = [
            'severity' => $id === 1 ? 'critical' : 'medium',
            'message' => 'Hub Mały Rosja / Syberia T20 — awaria urządzeń przeładunkowych.',
            'hub_name' => 'Hub Mały Rosja / Syberia T20', 'hub_id' => 20,
            'meta_json' => '{"extra_loss_bbl":0,"condition_dmg":2}',
            'created_at' => "2026-09-26 2{$id}:30:00",
        ];
    }
    require dirname(__DIR__, 2) . '/templates/views/technical/tabs/incidents.php';
    exit;
}

if ($surface === 'logistics') {
    $hubIncidentsPage = 1;
    $hubIncidentsTotal = 206;
    $hubIncidentsTotalPages = 11;
    $_GET = ['tab' => 'logistics'];
    $messages = [
        'Hub Mały Rosja / Syberia T20 - przeciążenie krytyczne przy 200%: uszkodzenie pompy głównej.',
        'Wykryto wyciek ropy w instalacji huba Hub Mały Rosja / Syberia T20.',
        'Przerwa pracy urządzeń przeładunkowych w Hub Mały Rosja / Syberia T20.',
        'Korek na wejściu kolektora Hub Mały Rosja / Syberia T20.',
    ];
    $hubIncidents = [];
    foreach ($messages as $index => $message) {
        $hubIncidents[] = [
            'severity' => $index === 0 ? 'critical' : 'medium',
            'message' => $message,
            'hub_name' => 'Hub Mały Rosja / Syberia T20',
            'hub_id' => 20,
            'meta_json' => '{"extra_loss_bbl":12.4,"condition_dmg":2}',
            'created_at' => '2026-09-26 23:30:00',
        ];
    }
    require dirname(__DIR__, 2) . '/templates/views/logistics/sections/hub_incidents_section.php';
    exit;
}

if (!in_array($surface, ['overview', 'list', 'config'], true)) { throw new InvalidArgumentException('Unknown surface'); }
$activeView = $surface;
$msg = '';
$msgErr = false;
$csrf = 'test';
$db = null;
$filterStatus = '';
$filterRegion = 0;
$filterCond = '';
$viewHubId = 0;
$viewHub = null;
$viewWells = [];
$viewLastStats = null;
$page = 1;
$totalPages = 85;
$totalHubs = 422;
$activeCount = 419;
$pausedCount = 0;
$allRegions = [['id' => 1, 'name' => 'Bliski Wschód']];
$allHubs = [];
foreach (range(1, 5) as $id) {
    $allHubs[] = [
        'id' => 40 + $id, 'name' => 'Hub Duży Bliski Wschód ' . chr(64 + $id) . $id,
        'hub_type' => 'large', 'acquisition_type' => $id < 3 ? 'rental' : 'used',
        'condition_pct' => $id === 3 ? 35.2 : 97.8 - $id * 7,
        'lease_fee_per_tick' => $id < 3 ? 380 : 0, 'assigned_count' => max(0, 4 - $id),
        'slot_limit' => 6, 'work_mode' => $id === 3 ? 'eco' : 'standard',
        'status' => 'active', 'region_name' => 'Bliski Wschód', 'region_id' => 1,
    ];
}
$hubsPageByRegion = ['Bliski Wschód' => $allHubs];
$typeMap = ['small' => 'Mały', 'medium' => 'Średni', 'large' => 'Duży (kontynentalny)'];
$statusMap = ['active' => 'Aktywny', 'paused' => 'Wstrzymany'];
$statusBadge = ['active' => 'green', 'paused' => 'yellow'];
$modeMap = ['eco' => 'Eco – mniejsze straty, wolniej', 'standard' => 'Standard – normalny ruch', 'max' => 'Max'];
$staffingConfig = ['enabled' => false, 'small' => 1, 'medium' => 2, 'large' => 3];
$staffingFilters = ['player_id' => 0, 'hub_id' => 0, 'employee' => '', 'status' => '', 'page' => 1];
$staffingDiagnostics = [
    'summary' => ['controlled_hubs' => 12, 'fully_staffed' => 0, 'understaffed' => 0, 'unstaffed' => 12, 'average_coverage' => 0],
    'page' => 1, 'total_pages' => 1, 'assignments' => [],
    'coverage_rows' => [
        ['hub_name' => 'Hub Duży Bliski Wschód B2', 'hub_id' => 42, 'region_name' => 'Bliski Wschód', 'coverage_pct' => 0, 'assigned_count' => 0, 'required_count' => 3, 'average_skill' => 0, 'average_morale' => 0],
        ['hub_name' => 'Hub Duży Bliski Wschód C3', 'hub_id' => 43, 'region_name' => 'Bliski Wschód', 'coverage_pct' => 0, 'assigned_count' => 0, 'required_count' => 3, 'average_skill' => 0, 'average_morale' => 0],
    ],
];
$hubConfigMap = [];
$cfgGet = static fn(string $group, string $key, string $default = '0'): string => $hubConfigMap[$group][$key] ?? $default;
$hub_admin = new class {
    use AdminHubConfigFieldTrait;
    public function getTickVerificationStats(mixed $db): array {
        return ['unassigned_wells' => 18, 'unassigned_production' => 0, 'hub_assignments' => 12, 'total_opex_charged' => 4200, 'fallback_losses_bbl' => 0, 'fallback_losses_value' => 0];
    }
};
$_SERVER['PHP_SELF'] = '/admin/logistics_hubs.php';
$pageTitle = t('admin.logistics.title');
$adminExtraCss = ['/assets/css/admin_logistics.css', '/assets/css/admin_hubs.css', '/assets/css/admin_staffing.css', '/assets/css/admin_hubs_view.css'];
$extraJs = ['/assets/js/admin_logistics_hubs.js'];
require dirname(__DIR__, 2) . '/admin/partials/header.php';
require dirname(__DIR__, 2) . '/templates/views/admin/logistics/main.php';
require dirname(__DIR__, 2) . '/admin/partials/footer.php';
