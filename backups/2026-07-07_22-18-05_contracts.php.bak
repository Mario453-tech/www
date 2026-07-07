<?php
declare(strict_types=1);

/**
 * contracts.php - strona gracza: kontrakty dlugoterminowe (podpisz / anuluj).
 * contracts.php - player page: long-term contracts (sign / cancel).
 */

$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('public/contracts.php') : microtime(true);
try {

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/ContractService.php';

Auth::requireLogin();

$playerId = Auth::getUserId();
$db       = Database::getInstance()->getConnection();
$service  = new ContractService($db);

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!RateLimiter::check('action')) {
        $error = tPlain('common.rate_limit');
    } elseif (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = tPlain('common.csrf_error');
    } else {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'accept_contract') {
            $optionId = (int)($_POST['option_id'] ?? 0);
            $res = $service->acceptContract(
                $playerId,
                $optionId,
                ContractService::TARGET_STORAGE,
                null,
                ContractService::CONTEXT_STORAGE_DELIVERY
            );
            if (!empty($res['success'])) {
                $success = tPlain((string)($res['message_key'] ?? 'contracts.msg_signed'));
            } else {
                $error = tPlain((string)($res['message_key'] ?? 'contracts.db_error'));
            }
        } elseif ($action === 'cancel_contract') {
            $contractId = (int)($_POST['contract_id'] ?? 0);
            $res = $service->cancelContract($playerId, $contractId);
            if (!empty($res['success'])) {
                $success = tPlain((string)($res['message_key'] ?? 'contracts.msg_cancelled'));
            } else {
                $error = tPlain((string)($res['message_key'] ?? 'contracts.db_error'));
            }
        }
    }
}

// == DANE DLA WIDOKU / VIEW DATA ==

$moduleEnabled = $service->isModuleEnabled();

// Aktualna cena ropy do szacowania przychodu z dostawy.
// Current oil price for estimating delivery revenue.
$marketPrice = 0.0;
try {
    $priceRow = $db->query("SELECT current_price FROM market_state WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    $marketPrice = (float)($priceRow['current_price'] ?? 0.0);
} catch (Throwable $e) {
    GameLog::error('public/contracts.php', 'market price load FAILED', $e, ['player_id' => $playerId]);
}

$available  = $moduleEnabled
    ? $service->getAvailableOptions($playerId, ContractService::TARGET_STORAGE, ContractService::CONTEXT_STORAGE_DELIVERY, $marketPrice)
    : [];
$active     = $service->listActiveContracts($playerId);
$deliveries = $service->listDeliveries($playerId, 50);
$logs       = $service->listLogs($playerId, 50);

$viewData = compact('moduleEnabled', 'available', 'active', 'deliveries', 'logs', 'marketPrice', 'error', 'success');
$viewData = array_merge($viewData, GameShell::data($playerId));

$extraCss = ['/assets/css/contracts.css'];
$extraJs  = ['/assets/js/contracts.js'];
require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
$gameShellTitle = t('contracts.page_title');
$gameShellView  = __DIR__ . '/../templates/views/contracts/main.php';
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('public/contracts.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo tPlain('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('public/contracts.php', $_codexGuardStart);
    }
}
