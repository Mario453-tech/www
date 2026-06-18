<?php
require_once __DIR__ . '/src/init.php';
Auth::requireLogin();
BoardAccess::require(Auth::getUserId(), 'hr');

$_pageStart = GameLog::pageStart('hr.php');
$playerId = Auth::getUserId();
GameLog::info('hr.php', 'Player logged in', ['player_id' => $playerId]);

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
    GameLog::step('hr.php', 'init', 1, 'HRService OK');
    GameLog::step('hr.php', 'init', 2, 'checkExpiringContracts');
    $hr->checkExpiringContracts();
    GameLog::step('hr.php', 'init', 3, 'headhunter processReady');
    $hh->processReady();
} catch (Throwable $e) {
    GameLog::error('hr.php', 'Error processing contracts or headhunter', $e);
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
    $history = $hr->getHistory(100);
    GameLog::dbResult('hr.php', 'getHistory', count($history));
} catch (Throwable $e) {
    GameLog::error('hr.php', 'getHistory failed', $e);
    $history = [];
}

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
];
$viewData = array_merge($viewData, GameShell::data($playerId));

$pageTitle = t('hr.page_title');
$extraCss = [
    'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@300;400;600&family=Montserrat:wght@300;400;600&display=swap',
    '/assets/css/recruitment.css',
    '/assets/css/hr.css',
];
$gameShellTitle = t('hr.page_title');
$gameShellView = __DIR__ . '/templates/views/hr/main.php';

require_once __DIR__ . '/templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/templates/components/game_shell.php';
require_once __DIR__ . '/templates/footer.php';
GameLog::pageEnd('hr.php', $_pageStart);
