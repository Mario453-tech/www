<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/LegalService.php';

Auth::requireLogin();

$playerId = Auth::getUserId();
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

        // P2a: wniosek o zezwolenie na hub / P2a: hub permit application
        if ($action === 'submit_hub_application' && $regionId > 0) {
            $res = $legal->submitHubApplication($playerId, $regionId);
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

// Saldo gracza musi być znane przed klasyfikacją (blokada kapitałowa §7.3).
// Player cash must be fetched before classification (capital lock §7.3).
$cash = (float)($db->query("SELECT cash FROM players WHERE id = " . (int)$playerId)->fetchColumn() ?? 0);

// Zbieramy statusy wszystkich regionów i klasyfikujemy je
$permitsByRegion = [];
foreach ($configs as $cfg) {
    $rid = (int)$cfg['region_id'];
    $permitsByRegion[$rid] = $legal->getPermitStatus($playerId, $rid);
}

// Pogrupowane regiony / Grouped regions
$active        = []; // granted / transitional
$inProgress    = []; // pending / delayed / no_decision
$available     = []; // none lub refused (po cooldown) — bez blokady kapitałowej
$locked        = []; // refused (w cooldown)
$capitalLocked = []; // brak zezwolenia + required_capital > cash (§7.3)

$now = new DateTime();
foreach ($configs as $cfg) {
    if (!(int)$cfg['enabled']) {
        continue;
    }
    $rid    = (int)$cfg['region_id'];
    $permit = $permitsByRegion[$rid];
    $status = $permit['status'];
    $requiredCapital = (float)$cfg['required_capital'];

    if ($permit['has_active']) {
        $active[] = ['config' => $cfg, 'permit' => $permit];
    } elseif (in_array($status, ['pending', 'delayed', 'no_decision'], true)) {
        $inProgress[] = ['config' => $cfg, 'permit' => $permit];
    } elseif ($status === 'refused' && !empty($permit['application']['refusal_cooldown_until'])) {
        $cooldown = new DateTime((string)$permit['application']['refusal_cooldown_until']);
        if ($cooldown > $now) {
            $locked[] = ['config' => $cfg, 'permit' => $permit, 'cooldown_until' => $cooldown];
        } elseif ($requiredCapital > 0 && $cash < $requiredCapital) {
            // Cooldown minął, ale brak kapitału — zablokowany kapitałowo.
            // Cooldown expired but insufficient capital — capital locked.
            $capitalLocked[] = ['config' => $cfg, 'permit' => $permit];
        } else {
            $available[] = ['config' => $cfg, 'permit' => $permit];
        }
    } elseif ($requiredCapital > 0 && $cash < $requiredCapital) {
        // Region bez wniosku, ale firma nie spełnia progu kapitałowego (§7.3).
        // No application, but player doesn't meet capital threshold.
        $capitalLocked[] = ['config' => $cfg, 'permit' => $permit];
    } else {
        $available[] = ['config' => $cfg, 'permit' => $permit];
    }
}

// P2a: zezwolenia na huby — tylko regiony z hub_permit_enabled=1.
// P2a: hub permits — only regions with hub_permit_enabled=1.
$hubEnabledConfigs = array_values(array_filter($configs, static fn($c) => (int)($c['hub_permit_enabled'] ?? 0) === 1));
$hubRegionIds      = array_column($hubEnabledConfigs, 'region_id');

$hubActive     = []; // granted
$hubInProgress = []; // pending / delayed / no_decision
$hubAvailable  = []; // none lub refused (cooldown minął)
$hubLocked     = []; // refused (w cooldown)

if (!empty($hubRegionIds)) {
    $hubPermitsByRegion = $legal->getHubPermitBatch($playerId, $hubRegionIds);

    foreach ($hubEnabledConfigs as $cfg) {
        $rid    = (int)$cfg['region_id'];
        $permit = $hubPermitsByRegion[$rid] ?? ['status' => 'none', 'has_active' => false, 'application' => null];
        $status = $permit['status'];

        if ($permit['has_active']) {
            $hubActive[] = ['config' => $cfg, 'permit' => $permit];
        } elseif (in_array($status, ['pending', 'delayed', 'no_decision'], true)) {
            $hubInProgress[] = ['config' => $cfg, 'permit' => $permit];
        } elseif ($status === 'refused' && !empty($permit['application']['refusal_cooldown_until'])) {
            $cooldown = new DateTime((string)$permit['application']['refusal_cooldown_until']);
            if ($cooldown > $now) {
                $hubLocked[] = ['config' => $cfg, 'permit' => $permit, 'cooldown_until' => $cooldown];
            } else {
                $hubAvailable[] = ['config' => $cfg, 'permit' => $permit];
            }
        } else {
            $hubAvailable[] = ['config' => $cfg, 'permit' => $permit];
        }
    }
}

$hasHubSection = !empty($hubEnabledConfigs);

$viewData = compact(
    'active', 'inProgress', 'available', 'locked', 'capitalLocked',
    'hubActive', 'hubInProgress', 'hubAvailable', 'hubLocked', 'hasHubSection',
    'cash', 'error', 'success'
);
$viewData = array_merge($viewData, GameShell::data($playerId));

$extraCss = ['/assets/css/legal.css'];
require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
$gameShellTitle = t('legal.page_title');
$gameShellView  = __DIR__ . '/../templates/views/legal/main.php';
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';
