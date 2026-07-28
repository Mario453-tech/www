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
require_once __DIR__ . '/../src/B2BContractService.php';
require_once __DIR__ . '/../src/Employee/EmployeeRoleEffectService.php';

Auth::requireLogin();

$playerId = Auth::getUserId();
$db       = Database::getInstance()->getConnection();
$service  = new ContractService($db);
$b2bService = new B2BContractService($db);

$flash = $_SESSION['contracts_flash'] ?? [];
unset($_SESSION['contracts_flash']);

$error   = (string)($flash['error'] ?? '');
$success = (string)($flash['success'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectTab = 'system';
    if (!RateLimiter::check('action')) {
        $_SESSION['contracts_flash'] = ['error' => tPlain('common.rate_limit')];
    } elseif (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['contracts_flash'] = ['error' => tPlain('common.csrf_error')];
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
                $_SESSION['contracts_flash'] = ['success' => tPlain((string)($res['message_key'] ?? 'contracts.msg_signed'))];
            } else {
                $_SESSION['contracts_flash'] = ['error' => tPlain((string)($res['message_key'] ?? 'contracts.db_error'))];
            }
        } elseif ($action === 'cancel_contract') {
            $contractId = (int)($_POST['contract_id'] ?? 0);
            $res = $service->cancelContract($playerId, $contractId);
            if (!empty($res['success'])) {
                $_SESSION['contracts_flash'] = ['success' => tPlain((string)($res['message_key'] ?? 'contracts.msg_cancelled'))];
            } else {
                $_SESSION['contracts_flash'] = ['error' => tPlain((string)($res['message_key'] ?? 'contracts.db_error'))];
            }
        } elseif ($action === 'create_b2b_offer') {
            $redirectTab = 'b2b_my';
            $bbl = (float)str_replace(',', '.', (string)($_POST['bbl'] ?? '0'));
            $price = (float)str_replace(',', '.', (string)($_POST['price_per_bbl'] ?? '0'));
            $expiresMinutes = (int)($_POST['expires_minutes'] ?? 1440);
            $res = $b2bService->createBuyOffer($playerId, $bbl, $price, $expiresMinutes);
            if (!empty($res['success'])) {
                $_SESSION['contracts_flash'] = ['success' => tPlain((string)($res['message_key'] ?? 'contracts.b2b.created'))];
            } else {
                $_SESSION['contracts_flash'] = ['error' => tPlain((string)($res['message_key'] ?? 'contracts.b2b.db_error'))];
            }
        } elseif ($action === 'cancel_b2b_offer') {
            $redirectTab = 'b2b_my';
            $offerId = (int)($_POST['offer_id'] ?? 0);
            $res = $b2bService->cancelBuyOffer($playerId, $offerId, 'buyer_cancelled');
            if (!empty($res['success'])) {
                $_SESSION['contracts_flash'] = ['success' => tPlain((string)($res['message_key'] ?? 'contracts.b2b.cancelled'))];
            } else {
                $_SESSION['contracts_flash'] = ['error' => tPlain((string)($res['message_key'] ?? 'contracts.b2b.db_error'))];
            }
        } elseif ($action === 'accept_b2b_offer') {
            $redirectTab = 'b2b_market';
            $offerId = (int)($_POST['offer_id'] ?? 0);
            $firstBbl = (float)str_replace(',', '.', (string)($_POST['first_delivery_bbl'] ?? '0'));
            if ($firstBbl <= 0) {
                // No first_delivery_bbl supplied — fall back to full delivery (old flow)
                $res = $b2bService->acceptAndDeliver($playerId, $offerId);
            } else {
                $res = $b2bService->acceptOffer($playerId, $offerId, $firstBbl);
            }
            if (!empty($res['success'])) {
                $_SESSION['contracts_flash'] = ['success' => tPlain((string)($res['message_key'] ?? 'contracts.b2b.completed'))];
                $redirectTab = 'b2b_my';
            } else {
                $_SESSION['contracts_flash'] = ['error' => tPlain((string)($res['message_key'] ?? 'contracts.b2b.db_error'))];
            }
        } elseif ($action === 'deliver_b2b_partial') {
            $redirectTab = 'b2b_my';
            $offerId = (int)($_POST['offer_id'] ?? 0);
            $bbl = (float)str_replace(',', '.', (string)($_POST['deliver_bbl'] ?? '0'));
            $res = $b2bService->deliverPartial($playerId, $offerId, $bbl);
            if (!empty($res['success'])) {
                $_SESSION['contracts_flash'] = ['success' => tPlain((string)($res['message_key'] ?? 'contracts.b2b.partial_delivered'))];
            } else {
                $_SESSION['contracts_flash'] = ['error' => tPlain((string)($res['message_key'] ?? 'contracts.b2b.db_error'))];
            }
        } elseif ($action === 'seller_abandon_b2b') {
            $redirectTab = 'b2b_my';
            $offerId = (int)($_POST['offer_id'] ?? 0);
            $reason = trim((string)($_POST['reason'] ?? 'seller_abandoned'));
            $res = $b2bService->sellerAbandonOffer($playerId, $offerId, $reason);
            if (!empty($res['success'])) {
                $_SESSION['contracts_flash'] = ['success' => tPlain('contracts.b2b.seller_abandoned')];
            } else {
                $_SESSION['contracts_flash'] = ['error' => tPlain((string)($res['message_key'] ?? 'contracts.b2b.db_error'))];
            }
        } else {
            $_SESSION['contracts_flash'] = ['error' => tPlain('contracts.invalid_input')];
        }
    }
    header('Location: ' . (function_exists('url') ? url('contracts') : '/contracts') . '?tab=' . rawurlencode($redirectTab));
    exit;
}

// View data.

$moduleEnabled = $service->isModuleEnabled();
$b2bModuleEnabled = $b2bService->isModuleEnabled();
$contractsTab = (string)($_GET['tab'] ?? 'system');
$allowedTabs = ['system', 'b2b_market', 'b2b_my', 'b2b_history', 'b2b_logs'];
if (!in_array($contractsTab, $allowedTabs, true)) {
    $contractsTab = 'system';
}

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
$pageNum = static fn(string $key): int => max(1, (int)($_GET[$key] ?? 1));
$systemLimit = 5;
$systemShowAllLimit = 1000;
$deliveryHistoryPage = $pageNum('delivery_history_page');
$deliveryHistoryAll = (string)($_GET['delivery_history_all'] ?? '') === '1';
$contractLogsPage = $pageNum('contract_logs_page');
$contractLogsAll = (string)($_GET['contract_logs_all'] ?? '') === '1';

$active = $service->listActiveContracts($playerId);
$deliveriesCount = $service->countDeliveries($playerId);
$logsCount = $service->countLogs($playerId);
$deliveryHistoryPage = min($deliveryHistoryPage, max(1, (int)ceil($deliveriesCount / $systemLimit)));
$contractLogsPage = min($contractLogsPage, max(1, (int)ceil($logsCount / $systemLimit)));
$deliveries = $service->listDeliveries(
    $playerId,
    $deliveryHistoryAll ? max($systemLimit, min($deliveriesCount, $systemShowAllLimit)) : $systemLimit,
    $deliveryHistoryAll ? 0 : (($deliveryHistoryPage - 1) * $systemLimit)
);
$logs = $service->listLogs(
    $playerId,
    $contractLogsAll ? max($systemLimit, min($logsCount, $systemShowAllLimit)) : $systemLimit,
    $contractLogsAll ? 0 : (($contractLogsPage - 1) * $systemLimit)
);

$limit = 12;
$logsLimit = 30;
$b2bMarketPage = $pageNum('b2b_market_page');
$b2bMyPage = $pageNum('b2b_my_page');
$b2bHistoryPage = $pageNum('b2b_history_page');
$b2bLogsPage = $pageNum('b2b_logs_page');
$b2bConfig = $b2bService->getConfig();
$b2bReputationScore = $b2bService->getPlayerReputationScore($playerId);
$b2bCoordinatorEffects = [];
foreach ((new EmployeeRoleEffectService($db))->calculatePlayerEffects(
    $playerId,
    ['b2b_delivery_coordinator' => ['b2b']]
) as $roleEffect) {
    foreach ((array)($roleEffect['effects'] ?? []) as $effectKey => $effect) {
        $b2bCoordinatorEffects[(string)$effectKey] = (float)($effect['final_value'] ?? 0.0);
    }
}
$b2bCoordinatorContext = $b2bService->coordinatorContext($playerId, $b2bCoordinatorEffects);
$b2bMarketOffers = $b2bService->listOpenOffers($playerId, $limit, ($b2bMarketPage - 1) * $limit);
$b2bMarketCount = $b2bService->countOpenOffers($playerId);
$b2bMyBuyOffers = $b2bService->listMyBuyOffers($playerId, $limit, ($b2bMyPage - 1) * $limit);
$b2bMyBuyCount = $b2bService->countMyBuyOffers($playerId);
$b2bMySales = $b2bService->listMySales($playerId, $limit, ($b2bMyPage - 1) * $limit);
$b2bMySalesCount = $b2bService->countMySales($playerId);
$b2bHistory = $b2bService->listPlayerHistory($playerId, $limit, ($b2bHistoryPage - 1) * $limit);
$b2bHistoryCount = $b2bService->countPlayerHistory($playerId);
$b2bDeliveryPage = $pageNum('historia_b2b_strona');
$b2bDeliveries = $b2bService->listMyDeliveries($playerId, $limit, ($b2bDeliveryPage - 1) * $limit);
$b2bDeliveriesCount = $b2bService->countMyDeliveries($playerId);
$b2bLogs = $b2bService->listPlayerLogs($playerId, $logsLimit, ($b2bLogsPage - 1) * $logsLimit);
$b2bLogsCount = $b2bService->countPlayerLogs($playerId);

$viewData = compact(
    'moduleEnabled',
    'b2bModuleEnabled',
    'contractsTab',
    'available',
    'active',
    'deliveries',
    'deliveriesCount',
    'deliveryHistoryPage',
    'deliveryHistoryAll',
    'logs',
    'logsCount',
    'contractLogsPage',
    'contractLogsAll',
    'marketPrice',
    'error',
    'success',
    'b2bConfig',
    'b2bReputationScore',
    'b2bCoordinatorContext',
    'b2bMarketOffers',
    'b2bMarketCount',
    'b2bMarketPage',
    'b2bMyBuyOffers',
    'b2bMyBuyCount',
    'b2bMySales',
    'b2bMySalesCount',
    'b2bMyPage',
    'b2bHistory',
    'b2bHistoryCount',
    'b2bHistoryPage',
    'b2bDeliveries',
    'b2bDeliveriesCount',
    'b2bDeliveryPage',
    'b2bLogs',
    'b2bLogsCount',
    'b2bLogsPage'
);
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
