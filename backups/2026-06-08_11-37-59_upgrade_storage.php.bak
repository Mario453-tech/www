<?php

require_once __DIR__ . '/../src/init.php';

GameLog::info('public/upgrade_storage.php', 'entry');
Auth::requireLogin();

$playerId = Auth::getUserId();

// Wykryj czy to zadanie AJAX (fetch)
$isAjax = (
    ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
);

function ajaxOrRedirect(bool $isAjax, bool $success, string $msg, string $redirectUrl): void {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $msg]);
        exit();
    }
    header('Location: ' . $redirectUrl);
    exit();
}

if (!RateLimiter::check('action')) {
    GameLog::warn('upgrade_storage', 'Rate limit hit', ['player_id' => $playerId]);
    ajaxOrRedirect($isAjax, false, t('upgrade_storage.err_rate_limit'), 'index.php?error=rate_limit');
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    GameLog::warn('upgrade_storage', 'CSRF fail', ['player_id' => $playerId]);
    ajaxOrRedirect($isAjax, false, t('upgrade_storage.err_csrf'), 'index.php?error=csrf');
}

$wellId  = (int)($_POST['well_id'] ?? 0);
$player  = new Player($playerId);
$storage = new Storage($playerId);

$upgradeCost = $storage->getUpgradeCost();

if (!$upgradeCost) {
    GameLog::error('upgrade_storage', 'Storage not found', null, ['player_id' => $playerId]);
    ajaxOrRedirect($isAjax, false, t('upgrade_storage.err_no_storage'), 'index.php?error=no_storage');
}

if (!$player->canAfford($upgradeCost)) {
    GameLog::warn('upgrade_storage', 'Insufficient funds', [
        'player_id' => $playerId, 'cost' => $upgradeCost, 'cash' => $player->getCash(),
    ]);
    ajaxOrRedirect($isAjax, false, t('upgrade_storage.err_no_funds'), 'index.php?error=no_funds');
}

$db = Database::getInstance()->getConnection();
$db->beginTransaction();

try {
    $player->updateCash(-$upgradeCost);
    $storage->upgrade();
    $db->commit();

    GameLog::info('upgrade_storage', 'Storage upgrade OK', [
        'player_id' => $playerId, 'well_id' => $wellId, 'upgrade_cost' => $upgradeCost,
    ]);

    ajaxOrRedirect($isAjax, true, t('upgrade_storage.success'), 'index.php?success=storage_upgraded');

} catch (Throwable $e) {
    $db->rollBack();
    GameLog::error('upgrade_storage', 'Storage upgrade FAILED', $e, [
        'player_id' => $playerId, 'well_id' => $wellId,
    ]);
    ajaxOrRedirect($isAjax, false, t('upgrade_storage.err_failed'), 'index.php?error=upgrade_failed');
}