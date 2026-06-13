<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/init.php';

Auth::requireLogin();
BoardAccess::require(Auth::getUserId(), 'finance');

$db       = Database::getInstance()->getConnection();
$playerId = Auth::getUserId();

require_once __DIR__ . '/../src/FinanceService.php';
require_once __DIR__ . '/../src/FinancePolicyService.php';
$finSvc = new FinanceService();
$policySvc = new FinancePolicyService();

$hours = (int)($_GET['hours'] ?? 24);
if (!in_array($hours, [24, 168], true)) {
    $hours = 24;
}

$tab = (string)($_GET['tab'] ?? 'overview');
if (!in_array($tab, ['overview', 'budgets', 'liquidity', 'risk', 'policy', 'history'], true)) {
    $tab = 'overview';
}

$msg = '';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        die(t('common.csrf_error'));
    }

    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save_finance_settings') {
        $policySvc->saveSettings($playerId, [
            'technical_budget' => (string)($_POST['technical_budget'] ?? 'standard'),
            'logistics_budget' => (string)($_POST['logistics_budget'] ?? 'standard'),
            'hr_budget' => (string)($_POST['hr_budget'] ?? 'standard'),
            'safety_budget' => (string)($_POST['safety_budget'] ?? 'standard'),
        ]);
        $msg = t('finance.msg_settings_saved');
        $tab = 'budgets';
    } elseif ($action === 'save_finance_policy') {
        $result = $policySvc->savePolicySettings($playerId, [
            'savings_plan_mode' => (string)($_POST['savings_plan_mode'] ?? 'off'),
            'reserve_policy' => (string)($_POST['reserve_policy'] ?? 'standard'),
        ]);
        if (!empty($result['ok'])) {
            $msg = t('finance.msg_policy_saved');
        } elseif (($result['error'] ?? '') === 'cooldown') {
            $err = t('finance.err_savings_cooldown');
        } else {
            $err = t('finance.err_policy_save');
        }
        $tab = 'policy';
    }
}

$last      = $finSvc->getLastTick($playerId);
$summary   = $finSvc->getSummary($playerId, $hours);
$summary24 = $finSvc->getSummary($playerId, 24);
$history   = $finSvc->getHistory($playerId, $hours);
$perWell   = $finSvc->getPerWellStats($playerId);
$settings  = $policySvc->getSettings($playerId);
$alerts    = $finSvc->getAlerts($playerId, $last ?? [], $summary24);
$liquidity = $finSvc->getLiquidityOverview($playerId, $settings, $last, $summary24);
$oilPrice = (float)($db->query("SELECT current_price FROM market_state WHERE id = 1")->fetchColumn() ?? 70);
$cash     = (float)($db->query("SELECT cash FROM players WHERE id = " . (int)$playerId)->fetchColumn() ?? 0);
$policySnapshot = $policySvc->getPolicySnapshot($playerId, (float)($liquidity['hourly_cost'] ?? 0.0), $cash);
$policyImpact = $finSvc->getPolicyImpactOverview($playerId, $settings, $last, $summary24, $policySnapshot);
$policyRecommendation = $finSvc->getPolicyRecommendationOverview($settings, $liquidity, $summary24, $policyImpact);
$riskOverview = $finSvc->getRiskOverview($settings, $last, $summary24, $perWell, $liquidity);
$alerts = array_merge($alerts, $finSvc->getStage3Alerts($settings, $liquidity, $summary24));
$decisionHistory = $policySvc->getDecisionHistory($playerId, 20);
$financeLocale = $_SESSION['locale'] ?? $_COOKIE['locale'] ?? 'pl';

$budgetLevelOptions = [
    'low' => t('finance.level_low'),
    'standard' => t('finance.level_standard'),
    'high' => t('finance.level_high'),
];
$reserveOptions = [
    'low' => t('finance.reserve_low'),
    'standard' => t('finance.reserve_standard'),
    'high' => t('finance.reserve_high'),
];
$savingsModeOptions = [
    'off' => t('finance.savings_mode_off'),
    'moderate' => t('finance.savings_mode_moderate'),
    'aggressive' => t('finance.savings_mode_aggressive'),
];

function fmtPLN(float $value, bool $sign = false): string
{
    global $financeLocale;
    $currency = $financeLocale === 'en' ? 'USD' : 'PLN';
    $formatted = number_format(abs($value), 0, ',', ' ') . ' ' . $currency;
    if ($sign) {
        return ($value >= 0 ? '+' : '&minus;') . $formatted;
    }

    return $value < 0 ? '&minus;' . $formatted : $formatted;
}

function fmtPct(float $value): string
{
    return number_format(abs($value), 1, ',', ' ') . '%';
}

function pct(float $part, float $base): string
{
    return $base > 0 ? number_format(abs($part / $base * 100), 1, ',', ' ') . '%' : '&mdash;';
}

$pageTitle = t('finance.page_title');
$extraCss  = ['/assets/css/finance.css'];
$viewData  = compact(
    'alerts',
    'hours',
    'tab',
    'msg',
    'err',
    'last',
    'summary',
    'summary24',
    'history',
    'perWell',
    'oilPrice',
    'cash',
    'settings',
    'budgetLevelOptions',
    'reserveOptions',
    'savingsModeOptions',
    'liquidity',
    'policySnapshot',
    'policyImpact',
    'policyRecommendation',
    'riskOverview',
    'decisionHistory'
);
$viewData  = array_merge($viewData, GameShell::data($playerId));

$gameShellTitle = t('finance.page_title');
$gameShellView  = __DIR__ . '/../templates/views/public/finance/main.php';

require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
$actions     = $viewData['actions'] ?? [];
$statusItems = $viewData['statusItems'] ?? [];
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';
