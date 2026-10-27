<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/init.php';

Auth::requireLogin();

$playerId = Auth::getUserId();
BoardAccess::require($playerId, 'legal');

$db = Database::getInstance()->getConnection();
$sabotage = new SabotageService($db);

// Suggestions are scoped to the authenticated player and only eligible active targets.
// Podpowiedzi dotycza zalogowanego gracza i tylko dostepnych aktywnych celow.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['suggest'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $query = is_string($_GET['suggest']) ? trim($_GET['suggest']) : '';
    $rows = $sabotage->isModuleEnabled() && $query !== ''
        ? $sabotage->searchPlayerTargets($playerId, $query) : [];
    echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}

$flash = $_SESSION['sabotage_flash'] ?? [];
unset($_SESSION['sabotage_flash']);

$error = (string)($flash['error'] ?? '');
$success = (string)($flash['success'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!RateLimiter::check('action')) {
        $error = t('common.ratelimit');
    } elseif (!CSRF::validateToken(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
        $error = t('common.csrf_error');
    } elseif (!is_string($_POST['operation_token'] ?? null)
        || !isset($_SESSION['sabotage_operation_token'])
        || !hash_equals($_SESSION['sabotage_operation_token'], $_POST['operation_token'])) {
        $error = tPlain('sabotage.err_duplicate');
    } else {
        // Consume before side effects; a repeated submit cannot repeat this operation.
        // Zuzyj token przed skutkami, aby ponowne wyslanie nie dublowalo akcji.
        unset($_SESSION['sabotage_operation_token']);
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        if ($action === 'execute_sabotage') {
            $targetPlayerId = filter_var($_POST['target_player_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
            $optionId = filter_var($_POST['option_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
            $result = $sabotage->executePlayerSabotage($playerId, $targetPlayerId, $optionId);
            // Show success only when sabotage actually worked; a missed roll is shown as an error.
            if ($result['success'] && $result['status'] !== 'failed') {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        } else {
            $error = tPlain('sabotage.err_option_unavailable');
        }
    }

    if ($error !== '') {
        $_SESSION['sabotage_flash'] = ['error' => $error];
    } elseif ($success !== '') {
        $_SESSION['sabotage_flash'] = ['success' => $success];
    }

    header('Location: ' . (function_exists('url') ? url('sabotage') : '/sabotage'));
    exit;
}

$playerStmt = $db->prepare(
    "SELECT username, company_name, cash, black_market_score
       FROM players
      WHERE id = ?
      LIMIT 1"
);
$playerStmt->execute([$playerId]);
$playerData = $playerStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'username' => '',
    'company_name' => '',
    'cash' => 0,
    'black_market_score' => 0,
];

$moduleEnabled = $sabotage->isModuleEnabled();
$options = $sabotage->getAvailableOptions(SabotageService::TARGET_PLAYER_COMPANY, SabotageService::CONTEXT_PLAYER_COMPANY);
$bmThreshold = $sabotage->getRequiredBlackMarketScore();
$targetStatus = is_string($_GET['status'] ?? null) ? $_GET['status'] : 'all';
if (!in_array($targetStatus, ['all', 'active', 'inactive'], true)) {
    http_response_code(400);
    exit('Invalid target filter');
}
$targetQuery = is_string($_GET['q'] ?? null) ? substr(trim($_GET['q']), 0, 80) : '';
foreach (['tpage', 'hpage'] as $pageParam) {
    $raw = $_GET[$pageParam] ?? '1';
    if (!is_scalar($raw) || !ctype_digit((string)$raw) || (int)$raw < 1 || (int)$raw > 100000) {
        http_response_code(400);
        exit('Invalid page');
    }
}
$perPage = 12;
$targetTotal = $sabotage->countPlayerTargets($playerId, $targetStatus, $targetQuery);
$targetPages = max(1, (int)ceil($targetTotal / $perPage));
$targetPage = min((int)($_GET['tpage'] ?? 1), $targetPages);
$targets = $sabotage->getPlayerTargets($playerId, $perPage, ($targetPage - 1) * $perPage, $targetStatus, $targetQuery);
$historyTotal = $sabotage->countRecentPlayerAttempts($playerId);
$historyPages = max(1, (int)ceil($historyTotal / 5));
$historyPage = min((int)($_GET['hpage'] ?? 1), $historyPages);
$attempts = $sabotage->getRecentPlayerAttempts($playerId, $historyPage);
$cooldownMap = $sabotage->getPlayerCooldownMap(
    $playerId,
    array_map(static fn(array $row): int => (int)$row['id'], $targets),
    array_map(static fn(array $row): int => (int)$row['id'], $options)
);
$costQuotes = [];
foreach ($targets as $target) {
    foreach ($options as $option) {
        $costQuotes[(int)$target['id']][(int)$option['id']] =
            $sabotage->estimatePlayerCost($option, (float)$target['cash']);
    }
}
$_SESSION['sabotage_operation_token'] ??= bin2hex(random_bytes(16));
$operationToken = $_SESSION['sabotage_operation_token'];
$selectedTarget = is_scalar($_GET['selected'] ?? null) ? (int)$_GET['selected'] : 0;

$viewData = array_merge(GameShell::data($playerId), [
    'error' => $error,
    'success' => $success,
    'moduleEnabled' => $moduleEnabled,
    'options' => $options,
    'targets'      => $targets,
    'targetPage'   => $targetPage,
    'targetPages'  => $targetPages,
    'targetTotal'  => $targetTotal,
    'targetQuery'  => $targetQuery,
    'targetStatus' => $targetStatus,
    'selectedTarget' => $selectedTarget,
    'attempts' => $attempts,
    'historyPage' => $historyPage,
    'historyPages' => $historyPages,
    'historyTotal' => $historyTotal,
    'cooldownMap' => $cooldownMap,
    'costQuotes' => $costQuotes,
    'bmThreshold' => $bmThreshold,
    'operationToken' => $operationToken,
    'playerData' => $playerData,
]);

$pageTitle = t('sabotage.page_title');
$gameShellTitle = t('sabotage.page_title');
$gameShellView = __DIR__ . '/../templates/views/sabotage/main.php';
$extraCss = ['/assets/css/sabotage.css'];

require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';
