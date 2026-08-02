<?php
require_once __DIR__ . '/../src/init.php';
Auth::requireLogin();
BoardAccess::require(Auth::getUserId(), 'hr');

$_pageStart = GameLog::pageStart('hr.php');
$playerId = Auth::getUserId();
GameLog::info('hr.php', 'Player logged in', ['player_id' => $playerId]);
$allowedTabs = ['employees', 'recruitment', 'raises', 'morale', 'conflicts', 'training', 'history'];
$requestedTab = strtolower(trim((string)($_GET['tab'] ?? 'employees')));
$activeHrTab = in_array($requestedTab, $allowedTabs, true) ? $requestedTab : 'employees';
$focusedRecord = trim((string)($_GET['record'] ?? ''));
if ($focusedRecord !== ''
    && preg_match('/^(?:employee:(?:board_member|technical_staff):[0-9]+|[a-z_]+:[0-9]+)$/', $focusedRecord) !== 1) {
    $focusedRecord = '';
}
$eventPage = max(1, (int)($_GET['event_page'] ?? 1));
$historyPage = max(1, (int)($_GET['history_page'] ?? 1));
$historyPerPage = 20;

try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    GameLog::error('hr.php', 'DB connection failed', $e);
    die(t('common.app_error'));
}

try {
    $hr = new HRService();
    GameLog::info('hr.php', 'HRService initialized OK');
} catch (Throwable $e) {
    GameLog::error('hr.php', 'Failed to create HRService', $e);
    die(t('hr.err_init_hr'));
}

try {
    $hh = new HeadhunterService($playerId);
    GameLog::info('hr.php', 'HeadhunterService initialized OK');
} catch (Throwable $e) {
    GameLog::error('hr.php', 'Failed to create HeadhunterService', $e);
    die(t('hr.err_init_hh'));
}

try {
    require_once __DIR__ . '/../src/HR/EmployeeStrikeService.php';
    require_once __DIR__ . '/../src/Employee/EmployeeSystemConfigService.php';
    $employeeStrikeService = new EmployeeStrikeService($db);
    $employeeConfig = new EmployeeSystemConfigService($db);
    $activeStrikes = $employeeStrikeService->activeForPlayer($playerId);
    $strikeNegotiationLimits = [
        'raise_min' => $employeeConfig->getFloat('negotiation_raise_min'),
        'raise_max' => $employeeConfig->getFloat('negotiation_raise_max'),
        'bonus_max' => $employeeConfig->getFloat('negotiation_bonus_max'),
        'enabled' => $employeeConfig->getBool('feature_negotiations'),
    ];
} catch (Throwable $e) {
    GameLog::error('hr.php', 'Failed to load employee strike dashboard', $e, ['player_id' => $playerId]);
    $activeStrikes = [];
    $strikeNegotiationLimits = [
        'raise_min' => 0.0,
        'raise_max' => 30.0,
        'bonus_max' => 100000.0,
        'enabled' => false,
    ];
}
try {
    $raiseRequestService = new EmployeeRaiseRequestService($db);
    $raiseConfig = new EmployeeSystemConfigService($db);
    $raiseRequests = $raiseRequestService->listForPlayer($playerId);
    $raiseDecisionLimits = [
        'salary_step' => 100.0,
        'max_postponements' => $raiseConfig->getInt('raise_max_postponements'),
    ];
} catch (Throwable $e) {
    GameLog::error('hr.php', 'Failed to load employee raise requests', $e, ['player_id' => $playerId]);
    $raiseRequests = [];
    $raiseDecisionLimits = ['salary_step' => 100.0, 'max_postponements' => 0];
}
try {
    $employeeDashboard = (new EmployeeDashboardQueryService($db))->forPlayer($playerId, $eventPage);
} catch (Throwable $e) {
    GameLog::error('hr.php', 'Failed to load canonical employee dashboard', $e, ['player_id' => $playerId]);
    $employeeDashboard = [
        'employees' => [],
        'morale' => [
            'employee_count' => 0,
            'average_morale' => 0.0,
            'average_leave_risk' => 0.0,
            'average_strike_support' => 0.0,
        ],
        'trainings' => [],
        'events' => [],
        'event_pagination' => [
            'page' => 1,
            'pages' => 1,
            'total' => 0,
            'per_page' => 20,
            'unread_count' => 0,
        ],
    ];
}

$_t = microtime(true);

try {
    GameLog::step('hr.php', 'data', 1, 'getActiveEmployees');
    $employees = $hr->getActiveEmployees();
    GameLog::dbResult('hr.php', 'getActiveEmployees', count($employees));
} catch (Throwable $e) {
    GameLog::error('hr.php', 'getActiveEmployees failed', $e);
    $employees = [];
}

try {
    GameLog::step('hr.php', 'data', 2, 'getActiveDirectors');
    $directors = $hr->getActiveDirectors($playerId);
    GameLog::dbResult('hr.php', 'getActiveDirectors', count($directors));
} catch (Throwable $e) {
    GameLog::error('hr.php', 'getActiveDirectors failed', $e);
    $directors = [];
}

try {
    GameLog::step('hr.php', 'data', 3, 'getActiveContracts');
    $contracts = $hr->getActiveContracts();
    GameLog::dbResult('hr.php', 'getActiveContracts', count($contracts));
} catch (Throwable $e) {
    GameLog::error('hr.php', 'getActiveContracts failed', $e);
    $contracts = [];
}

try {
    GameLog::step('hr.php', 'data', 4, 'getRegions');
    $regions = $hr->getRegions();
    GameLog::dbResult('hr.php', 'getRegions', count($regions));
} catch (Throwable $e) {
    GameLog::error('hr.php', 'getRegions failed', $e);
    $regions = [];
}

try {
    GameLog::step('hr.php', 'data', 5, 'getSpecializations');
    $specializations = $hr->getSpecializations();
    GameLog::dbResult('hr.php', 'getSpecializations', count($specializations));
} catch (Throwable $e) {
    GameLog::error('hr.php', 'getSpecializations failed', $e);
    $specializations = [];
}

try {
    GameLog::step('hr.php', 'data', 6, 'getHistory');
    $historyCount = $db->prepare(
        'SELECT COUNT(*)
           FROM employment_history eh
           JOIN board_members bm ON bm.id=eh.member_id
          WHERE bm.player_id=?'
    );
    $historyCount->execute([$playerId]);
    $historyTotal = (int)$historyCount->fetchColumn();
    $historyPages = max(1, (int)ceil($historyTotal / $historyPerPage));
    $historyPage = min($historyPage, $historyPages);
    $historyOffset = ($historyPage - 1) * $historyPerPage;
    $historyQuery = $db->prepare(
        'SELECT eh.*, bm.first_name, bm.last_name, br.name AS role_name
           FROM employment_history eh
           JOIN board_members bm ON bm.id=eh.member_id
      LEFT JOIN board_roles br ON br.id=bm.role_id
          WHERE bm.player_id=?
          ORDER BY eh.created_at DESC
          LIMIT ? OFFSET ?'
    );
    $historyQuery->bindValue(1, $playerId, PDO::PARAM_INT);
    $historyQuery->bindValue(2, $historyPerPage, PDO::PARAM_INT);
    $historyQuery->bindValue(3, $historyOffset, PDO::PARAM_INT);
    $historyQuery->execute();
    $history = $historyQuery->fetchAll(PDO::FETCH_ASSOC);
    GameLog::dbResult('hr.php', 'getHistory', count($history));
} catch (Throwable $e) {
    GameLog::error('hr.php', 'getHistory failed', $e);
    $history = [];
    $historyTotal = 0;
    $historyPages = 1;
    $historyPage = 1;
}
$historyPagination = [
    'page' => $historyPage,
    'pages' => $historyPages,
    'total' => $historyTotal,
    'per_page' => $historyPerPage,
];

try {
    GameLog::step('hr.php', 'data', 7, 'getHrCandidates');
    $staffCandidates = $hr->getHrCandidates($playerId);
    GameLog::dbResult('hr.php', 'getHrCandidates', count($staffCandidates));
} catch (Throwable $e) {
    GameLog::error('hr.php', 'getHrCandidates failed', $e);
    $staffCandidates = [];
}

try {
    GameLog::step('hr.php', 'data', 8, 'headhunter getActiveSearch');
    $hhActiveSearch = $hh->getActiveSearch();
    GameLog::info('hr.php', 'getActiveSearch', ['found' => $hhActiveSearch ? 'yes' : 'no']);
} catch (Throwable $e) {
    GameLog::error('hr.php', 'getActiveSearch failed', $e);
    $hhActiveSearch = null;
}

try {
    GameLog::step('hr.php', 'data', 9, 'headhunter getAvailableCandidates');
    $hhCandidates = $hh->getAvailableCandidates();
    GameLog::dbResult('hr.php', 'getAvailableCandidates', count($hhCandidates));
} catch (Throwable $e) {
    GameLog::error('hr.php', 'getAvailableCandidates failed', $e);
    $hhCandidates = [];
}

try {
    GameLog::step('hr.php', 'data', 10, 'headhunter getRecentSearches');
    $hhRecentSearches = $hh->getRecentSearches(5);
    GameLog::dbResult('hr.php', 'getRecentSearches', count($hhRecentSearches));
} catch (Throwable $e) {
    GameLog::error('hr.php', 'getRecentSearches failed', $e);
    $hhRecentSearches = [];
}

GameLog::perf('hr.php', 'Data load (10 queries)', $_t);

$expiring = array_filter($contracts, static fn($contract) => ($contract['days_left'] ?? 999) <= 14);
$csrfToken = CSRF::generateToken();

GameLog::info('hr.php', 'Data ready, rendering HTML', [
    'employees' => count($employees),
    'directors' => count($directors),
    'contracts' => count($contracts),
    'staff_candidates' => count($staffCandidates),
    'expiring' => count($expiring),
    'specializations' => count($specializations),
    'headhunter_candidates' => count($hhCandidates),
]);

$viewData = [
    'employees' => $employees,
    'directors' => $directors,
    'contracts' => $contracts,
    'regions' => $regions,
    'specializations' => $specializations,
    'history' => $history,
    'staffCandidates' => $staffCandidates,
    'hhActiveSearch' => $hhActiveSearch,
    'hhCandidates' => $hhCandidates,
    'hhRecentSearches' => $hhRecentSearches,
    'expiring' => $expiring,
    'csrfToken' => $csrfToken,
    'activeStrikes' => $activeStrikes,
    'strikeNegotiationLimits' => $strikeNegotiationLimits,
    'raiseRequests' => $raiseRequests,
    'raiseDecisionLimits' => $raiseDecisionLimits,
    'employeeDashboard' => $employeeDashboard,
    'historyPagination' => $historyPagination,
    'activeHrTab' => $activeHrTab,
    'focusedRecord' => $focusedRecord,
];
$viewData = array_merge($viewData, GameShell::data($playerId));

$pageTitle = t('hr.page_title');
$extraCss = [
    'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;600&family=Montserrat:wght@300;400;600&display=swap',
    '/assets/css/recruitment.css',
    '/assets/css/hr.css',
    '/assets/css/hr_employees.css',
    '/assets/css/hr_morale.css',
    '/assets/css/hr_strikes.css',
    '/assets/css/hr_raises.css',
];
$gameShellTitle = t('hr.page_title');
$gameShellView = __DIR__ . '/../templates/views/hr/main.php';

require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';
GameLog::pageEnd('hr.php', $_pageStart);
