<?php
/**
 * upgrade_well.php - well upgrade AJAX endpoint
 * upgrade_well.php - endpoint AJAX do ulepszania odwiertu
 * Always returns JSON: {"success": bool, "message": string, "data": {...}}
 * Zawsze zwraca JSON: {"success": bool, "message": string, "data": {...}}
 */

require_once __DIR__ . '/../src/init.php';

header('Content-Type: application/json; charset=utf-8');

function jsonResponse(bool $success, string $message, array $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

GameLog::step('upgrade_well.php', 'ajax', 1, 'entry');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, t('common.invalid_method'));
}

if (!Auth::isLoggedIn()) {
    jsonResponse(false, t('common.not_logged_in'));
}

if (!RateLimiter::check('action')) {
    jsonResponse(false, t('common.rate_limit'));
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    GameLog::warn('upgrade_well.php', 'CSRF validation failed');
    jsonResponse(false, t('common.csrf_error'));
}

$playerId = Auth::getUserId();
$wellId   = (int)($_POST['well_id'] ?? 0);

GameLog::step('upgrade_well.php', 'ajax', 2, "player={$playerId} well={$wellId}");

try {
    $player = new Player($playerId);
    $well   = new Well($playerId);

    if ($wellId <= 0) {
        $wells = $well->getWells();
        if (empty($wells)) {
            jsonResponse(false, t('upgrade_well.err_no_wells'));
        }
        $wellId = (int)$wells[0]['id'];
    }

    $wellData = $well->getWell($wellId);
    if (!$wellData) {
        jsonResponse(false, t('upgrade_well.err_not_found'));
    }

    $upgradeCost = $well->getUpgradeCost($wellId);
    if ($upgradeCost === false) {
        jsonResponse(false, t('upgrade_well.err_cost_calc'));
    }

    $cash = (float)$player->getCash();
    if (!$player->canAfford($upgradeCost)) {
        jsonResponse(false,
            t('upgrade_well.err_no_funds', [
                'required' => number_format($upgradeCost, 0, ',', ' '),
                'cash'     => number_format($cash, 0, ',', ' '),
            ]),
            ['required' => $upgradeCost, 'cash' => $cash]
        );
    }

    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();

    $player->updateCash(-$upgradeCost);
    $ok = $well->upgrade($wellId);

    if (!$ok) {
        $db->rollBack();
        jsonResponse(false, t('upgrade_well.err_failed'));
    }

    $db->commit();

    $wellAfter = $well->getWell($wellId);
    $newLevel  = (int)($wellAfter['level']                    ?? ($wellData['level'] + 1));
    $newProd   = (float)($wellAfter['base_production_per_hour'] ?? 0);
    $cashAfter = (float)$player->getCash();
    $nextCost  = $well->getUpgradeCost($wellId);

    GameLog::info('upgrade_well.php', 'Upgrade OK', [
        'player_id'  => $playerId,
        'well_id'    => $wellId,
        'new_level'  => $newLevel,
        'cost'       => $upgradeCost,
        'cash_after' => $cashAfter,
    ]);

    jsonResponse(true,
        t('upgrade_well.msg_success', [
            'well_id' => $wellId,
            'level'   => $newLevel,
            'prod'    => number_format($newProd, 1, ',', ' '),
        ]),
        [
            'well_id'    => $wellId,
            'new_level'  => $newLevel,
            'new_prod'   => $newProd,
            'cost_paid'  => $upgradeCost,
            'cash_after' => $cashAfter,
            'next_cost'  => $nextCost,
        ]
    );

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    GameLog::error('upgrade_well.php', 'Exception', $e, ['player_id' => $playerId ?? 0, 'well_id' => $wellId ?? 0]);
    jsonResponse(false, t('common.app_error'));
}
