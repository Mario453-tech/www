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
            $res = $b2bService->acceptAndDeliver($playerId, $offerId);
            if (!empty($res['success'])) {
                $_SESSION['contracts_flash'] = ['success' => tPlain((string)($res['message_key'] ?? 'contracts.b2b.completed'))];
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

// == DANE DLA WIDOKU / VIEW DATA ==

$moduleEnabled = $service->isModuleEnabled();
$b2bModuleEnabled = $b2bService->isModuleEnabled();
$contractsTab = (string)($_GET['tab'] ?? 'system');
$allowedTabs = ['system', 'b2b_market', 'b2b_my', 'b2b_history', 'b2b_logs'];
if (!in_array($contractsTab, $allowedTabs, true)) {
    $contractsTab = 'system';
}

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

$pageNum = static fn(string $key): int => max(1, (int)($_GET[$key] ?? 1));
$limit = 12;
$logsLimit = 30;
$b2bMarketPage = $pageNum('b2b_market_page');
$b2bMyPage = $pageNum('b2b_my_page');
$b2bHistoryPage = $pageNum('b2b_history_page');
$b2bLogsPage = $pageNum('b2b_logs_page');
$b2bConfig = $b2bService->getConfig();
$b2bMarketOffers = $b2bService->listOpenOffers($playerId, $limit, ($b2bMarketPage - 1) * $limit);
$b2bMarketCount = $b2bService->countOpenOffers($playerId);
$b2bMyBuyOffers = $b2bService->listMyBuyOffers($playerId, $limit, ($b2bMyPage - 1) * $limit);
$b2bMyBuyCount = $b2bService->countMyBuyOffers($playerId);
$b2bMySales = $b2bService->listMySales($playerId, $limit, ($b2bMyPage - 1) * $limit);
$b2bMySalesCount = $b2bService->countMySales($playerId);
$b2bHistory = $b2bService->listPlayerHistory($playerId, $limit, ($b2bHistoryPage - 1) * $limit);
$b2bHistoryCount = $b2bService->countPlayerHistory($playerId);
$b2bLogs = $b2bService->listPlayerLogs($playerId, $logsLimit, ($b2bLogsPage - 1) * $logsLimit);
$b2bLogsCount = $b2bService->countPlayerLogs($playerId);

$viewData = compact(
    'moduleEnabled',
    'b2bModuleEnabled',
    'contractsTab',
    'available',
    'active',
    'deliveries',
    'logs',
    'marketPrice',
    'error',
    'success',
    'b2bConfig',
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
