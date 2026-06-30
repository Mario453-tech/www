<?php
declare(strict_types=1);

/**
 * GET /api/v1/technical/data.php
 *
 * Returns technical department overview: director, manager bonus, engineer staff,
 * well personnel assignments, and recruitment candidates.
 * Zwraca dane dzialu technicznego: kierownik, bonus, pracownicy, personel odwiertow,
 * kandydaci rekrutacyjni.
 */
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once $_API_ROOT . '/src/i18n.php';
require_once $_API_ROOT . '/src/TaskConfigService.php';
require_once $_API_ROOT . '/src/TechnicalTeamService.php';
require_once $_API_ROOT . '/src/WellStaffService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError(405, 'Method Not Allowed — use GET');
}

$player   = apiRequireAuth();
$playerId = (int)$player['id'];
$db       = Database::getInstance()->getConnection();

$svc        = new TechnicalTeamService($playerId);
$manager    = $svc->getManager();
$mBonus     = $svc->getManagerBonus($manager);
$staff      = $svc->getStaff();
$candidates = $svc->getTechnicalCandidates();
$wellStaff  = (new WellStaffService($playerId))->getWellsStaffStatus();

// Company name / Nazwa firmy
$cnStmt = $db->prepare("SELECT company_name FROM players WHERE id = ? LIMIT 1");
$cnStmt->execute([$playerId]);
$companyName = (string)($cnStmt->fetchColumn() ?: '');

// Director (board_member role=technical) / Kierownik techniczny
$director = null;
if ($manager) {
    $fn       = (string)($manager['first_name'] ?? '');
    $ln       = (string)($manager['last_name']  ?? '');
    $initials = mb_strtoupper(mb_substr($fn, 0, 1) . mb_substr($ln, 0, 1));
    $director = [
        'id'                 => (int)$manager['id'],
        'first_name'         => $fn,
        'last_name'          => $ln,
        'initials'           => $initials,
        'skill_organization' => (int)($manager['skill_organization'] ?? 0),
        'experience_years'   => (int)($manager['experience_years'] ?? 0),
        'days_employed'      => (int)($manager['days_employed'] ?? 0),
        'salary'             => round((float)($manager['salary'] ?? 0), 2),
    ];
}

// Manager bonus expressed as percentage reductions / Bonus kierownika w procentach redukcji
$managerBonus = [
    'skill'    => (int)($mBonus['skill'] ?? 0),
    'time_pct' => round((1.0 - (float)($mBonus['time_mult'] ?? 1.0)) * 100, 1),
    'cost_pct' => round((1.0 - (float)($mBonus['cost_mult'] ?? 1.0)) * 100, 1),
];

// Engineer list / Lista inzynierow
$specDefs  = TechnicalTeamService::SPECS;
$engineers = [];
foreach ($staff as $e) {
    $specCode = (string)($e['spec_code'] ?? '');
    $specIcon = $specDefs[$specCode]['icon'] ?? strtoupper(substr($specCode, 0, 3));
    $engineers[] = [
        'id'               => (int)$e['id'],
        'first_name'       => (string)($e['first_name'] ?? ''),
        'last_name'        => (string)($e['last_name']  ?? ''),
        'spec_code'        => $specCode,
        'spec_icon'        => $specIcon,
        'spec_name'        => (string)($e['spec_name']  ?? $specCode),
        'skill_level'      => (int)($e['skill_level']  ?? 1),
        'experience_years' => (int)($e['experience_years'] ?? 0),
        'salary'           => round((float)($e['salary'] ?? 0), 2),
        'status'           => (string)($e['status'] ?? 'active'),
        'active_task_type'  => isset($e['active_task_type']) && $e['active_task_type'] !== null
            ? (string)$e['active_task_type'] : null,
        'active_task_label' => isset($e['active_task_type']) && $e['active_task_type'] !== null
            ? (string)((TechnicalTeamService::getTaskDefinition((string)$e['active_task_type']) ?? [])['label'] ?? $e['active_task_type']) : null,
        'active_task_end'   => isset($e['active_task_end']) && $e['active_task_end'] !== null
            ? (string)$e['active_task_end'] : null,
    ];
}

// Well personnel / Personel odwiertow
$wellPersonnel = [];
foreach ($wellStaff as $wp) {
    $wellPersonnel[] = [
        'well_id'        => (int)($wp['well_id'] ?? 0),
        'well_name'      => (string)($wp['well_name'] ?? ''),
        'well_status'    => (string)($wp['status'] ?? ''),
        'has_operator'   => (bool)($wp['has_operator']   ?? false),
        'has_technician' => (bool)($wp['has_technician'] ?? false),
        'operator'       => $wp['operator']   ?? null,
        'technician'     => $wp['technician'] ?? null,
    ];
}

// Candidates / Kandydaci
$cands      = [];
$unreviewed = 0;
foreach ($candidates as $c) {
    $hasReview = !empty($c['review_id']);
    if (!$hasReview) {
        $unreviewed++;
    }
    $cands[] = [
        'id'               => (int)$c['id'],
        'first_name'       => (string)($c['first_name'] ?? ''),
        'last_name'        => (string)($c['last_name']  ?? ''),
        'spec_code'        => (string)($c['spec_code']  ?? ''),
        'spec_name'        => (string)($c['spec_name']  ?? ''),
        'skill_level'      => (int)($c['skill_level']  ?? 1),
        'experience_years' => (int)($c['experience_years'] ?? 0),
        'salary'           => round((float)($c['salary'] ?? 0), 2),
        'hours_remaining'  => (int)($c['hours_remaining'] ?? 0),
        'review_id'        => $hasReview ? (int)$c['review_id'] : null,
    ];
}

apiJson([
    'company_name'          => $companyName,
    'director'              => $director,
    'manager_bonus'         => $managerBonus,
    'staff_count'           => count($engineers),
    'engineers'             => $engineers,
    'well_personnel'        => $wellPersonnel,
    'well_personnel_count'  => count($wellPersonnel),
    'candidates'            => $cands,
    'unreviewed_candidates' => $unreviewed,
]);
