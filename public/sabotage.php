<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/init.php';

Auth::requireLogin();

$playerId = Auth::getUserId();
BoardAccess::require($playerId, 'legal');

$db = Database::getInstance()->getConnection();
$sabotage = new SabotageService($db);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!RateLimiter::check('action')) {
        $error = t('common.ratelimit');
    } elseif (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = t('common.csrf_error');
    } else {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'execute_sabotage') {
            $targetPlayerId = (int)($_POST['target_player_id'] ?? 0);
            $optionId = (int)($_POST['option_id'] ?? 0);
            $result = $sabotage->executePlayerSabotage($playerId, $targetPlayerId, $optionId);
            // Zielony box tylko gdy sabotaz faktycznie zadzialal; nieudany rzut ('failed')
            // pokazujemy jako blad, mimo ze operacja zostala przetworzona poprawnie.
            // Green box only when the sabotage actually worked; a missed roll ('failed') is
            // shown as an error even though the operation itself was processed correctly.
            if ($result['success'] && $result['status'] !== 'failed') {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }
        }
    }
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
$perPage      = 12;
$targetPage   = max(1, (int)($_GET['tpage'] ?? 1));
$targetOffset = ($targetPage - 1) * $perPage;
$targetTotal  = $sabotage->countPlayerTargets($playerId);
$targetPages  = (int)ceil($targetTotal / $perPage);
$targets      = $sabotage->getPlayerTargets($playerId, $perPage, $targetOffset);
$attempts = $sabotage->listAttemptsForPlayer($playerId, 20);
$cooldownMap = $sabotage->getPlayerCooldownMap(
    $playerId,
    array_map(static fn(array $row): int => (int)$row['id'], $targets),
    array_map(static fn(array $row): int => (int)$row['id'], $options)
);

$viewData = array_merge(GameShell::data($playerId), [
    'error' => $error,
    'success' => $success,
    'moduleEnabled' => $moduleEnabled,
    'options' => $options,
    'targets'      => $targets,
    'targetPage'   => $targetPage,
    'targetPages'  => $targetPages,
    'targetTotal'  => $targetTotal,
    'attempts' => $attempts,
    'cooldownMap' => $cooldownMap,
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
