<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/LegalService.php';
require_once __DIR__ . '/../src/CompanyCredibilityService.php';

Auth::requireLogin();

$playerId = Auth::getUserId();
BoardAccess::require($playerId, 'legal');
$db       = Database::getInstance()->getConnection();
$legal    = new LegalService($db);

$error   = '';
$success = '';

// == OBSŁUGA FORMULARZA ==

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!RateLimiter::check('action')) {
        $error = t('common.ratelimit');
    } elseif (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = t('common.csrf_error');
    } else {
        $action   = (string)($_POST['action'] ?? '');
        $regionId = (int)($_POST['region_id'] ?? 0);

        if ($action === 'submit_application' && $regionId > 0) {
            $res = $legal->submitApplication($playerId, $regionId);
            if ($res['success']) {
                $success = $res['message'];
            } else {
                $error = $res['message'];
            }
        }
    }
}

// == DANE DLA WIDOKU ==

$configs = $legal->getAllRegionConfigs();

// Zbieramy statusy wszystkich regionów i klasyfikujemy je
$permitsByRegion = [];
foreach ($configs as $cfg) {
    $rid = (int)$cfg['region_id'];
    $permitsByRegion[$rid] = $legal->getPermitStatus($playerId, $rid);
}

// Pobranie salda gracza (potrzebne też do klasyfikacji blokady kapitałowej §7.3)
// Player cash (also needed to classify capital lock §7.3)
$cash = (float)($db->query("SELECT cash FROM players WHERE id = " . (int)$playerId)->fetchColumn() ?? 0);
$legalLevel = $legal->getLegalLevelForPlayer($playerId);

// Pogrupowane regiony
$active        = []; // granted / transitional
$inProgress    = []; // pending / delayed / no_decision
$available     = []; // none lub refused (po cooldown), firma spełnia wymóg kapitału
$locked        = []; // refused (w cooldown)
$capitalLocked = []; // brief §7.3: region wysokiego ryzyka — brak wymaganego kapitału

$levelLocked = []; // P2: wymagany wyzszy poziom dzialu prawnego / Required higher legal department level

$now = new DateTime();
foreach ($configs as $cfg) {
    if (!(int)$cfg['enabled']) {
        continue;
    }
    $rid    = (int)$cfg['region_id'];
    $permit = $permitsByRegion[$rid];
    $status = $permit['status'];

    if ($permit['has_active']) {
        $active[] = ['config' => $cfg, 'permit' => $permit];
    } elseif (in_array($status, ['pending', 'delayed', 'no_decision'], true)) {
        $inProgress[] = ['config' => $cfg, 'permit' => $permit];
    } elseif ($status === 'refused' && !empty($permit['application']['refusal_cooldown_until'])
              && new DateTime((string)$permit['application']['refusal_cooldown_until']) > $now) {
        $locked[] = [
            'config'         => $cfg,
            'permit'         => $permit,
            'cooldown_until' => new DateTime((string)$permit['application']['refusal_cooldown_until']),
        ];
    } else {
        // Brak aktywnego zezwolenia ani cooldownu — region dostępny LUB
        // zablokowany kapitałowo (brief §7.3: nie pozwalamy złożyć wniosku).
        // No active permit nor cooldown — region available OR capital-locked
        // (brief §7.3: applying is blocked until the company meets the capital).
        $reqLevel = (int)($cfg['required_legal_level'] ?? 0);
        $reqCapital = (float)$cfg['required_capital'];
        if ($reqLevel > 0 && $legalLevel < $reqLevel) {
            $levelLocked[] = [
                'config' => $cfg,
                'permit' => $permit,
                'required_legal_level' => $reqLevel,
            ];
        } elseif ($reqCapital > 0.0 && $cash < $reqCapital) {
            $capitalLocked[] = ['config' => $cfg, 'permit' => $permit, 'required_capital' => $reqCapital];
        } else {
            $available[] = ['config' => $cfg, 'permit' => $permit];
        }
    }
}

// Wiarygodnosc firmy / Company credibility (karta w dziale prawnym)
$credibilityScore = CompanyCredibilityService::DEFAULT_SCORE;
$credibilityLevel = 'shaky';
try {
    $credService      = new CompanyCredibilityService();
    $credibilityScore = $credService->getScore($playerId);
    $credibilityLevel = $credService->getLevel($credibilityScore);
} catch (Throwable $e) {
    GameLog::error('legal.php', 'CompanyCredibilityService failed', $e, ['player_id' => $playerId]);
}

$viewData = compact(
    'active', 'inProgress', 'available', 'locked', 'capitalLocked', 'levelLocked',
    'cash', 'legalLevel', 'error', 'success',
    'credibilityScore', 'credibilityLevel'
);
$viewData = array_merge($viewData, GameShell::data($playerId));

$extraCss = ['/assets/css/legal.css', '/assets/css/credibility.css'];
$extraJs  = ['/assets/js/legal.js'];
require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
$gameShellTitle = t('legal.page_title');
$gameShellView  = __DIR__ . '/../templates/views/legal/main.php';
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';
