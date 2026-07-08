<?php

require_once __DIR__ . '/../src/init.php';

GameLog::info('public/market.php', 'entry');
Auth::requireLogin();

require_once __DIR__ . '/../src/B2BContractService.php';

$player      = new Player(Auth::getUserId());
$storage     = new Storage(Auth::getUserId());
$market      = new Market();
$marketTick  = new MarketTick();
$marketOffer = new MarketOffer();
$b2bService  = new B2BContractService();

$playerData = $player->getData();
$storageData = $storage->getData();
$marketData = $market->getState();

$error = '';
$success = '';

if ($_POST && isset($_POST['action'])) {
    if (!RateLimiter::check('action')) {
        $error = t('market.error_ratelimit');
    } elseif (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = t('market.error_csrf');
    } else {
        $action = $_POST['action'];
        
        if ($action === 'sell_instant') {
            $amount = (int)$_POST['amount'];

            if ($amount <= 0 || $amount > $storageData['used']) {
                $error = t('market.error_amount');
            } else {
                $earnings = $amount * $marketData['current_price'];

                $db = Database::getInstance()->getConnection();
                $fts = new FinancialTransactionService();
                $db->beginTransaction();

                try {
                    $res = $fts->credit(Auth::getUserId(), $earnings, FinancialTransactionService::TYPE_MARKET_SALE, 'Sprzedaz ropy na rynku');
                    if (!$res['success']) {
                        throw new RuntimeException($res['error'] ?? t('common.app_error'));
                    }

                    $db->prepare("
                        UPDATE storage
                        SET used = used - :amount
                        WHERE player_id = :player_id
                    ")->execute([
                        ':amount'    => $amount,
                        ':player_id' => Auth::getUserId(),
                    ]);

                    $db->commit();
                    $success = sprintf(t('market.success_sold'), $amount, number_format($earnings));

                    $storageData['used']  -= $amount;
                    $playerData['cash']   += $earnings;

 // Credit score recovery after legal sale
                    try {
                        (new BlackMarketService())->applyLegalRecovery(Auth::getUserId());
                    } catch (\Throwable $e) {}

                } catch (Exception $e) {
                    $db->rollBack();
                    $error = tPlain('market.error_sell', ['msg' => $e->getMessage()]);
                }
            }

        } elseif ($action === 'create_offer') {
            $amount     = (int)$_POST['amount'];
            $limitPrice = (int)$_POST['limit_price'];

            $result = $marketOffer->createOffer(Auth::getUserId(), $amount, $limitPrice);
            if ($result['success']) {
                $success = $result['message'];
                $storageData = $storage->getData();
            } else {
                $error = $result['message'];
            }

        } elseif ($action === 'edit_offer') {
            $offerId       = (int)$_POST['offer_id'];
            $newLimitPrice = (int)$_POST['new_limit_price'];

            $result = $marketOffer->updateOffer($offerId, Auth::getUserId(), $newLimitPrice);
            if ($result['success']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
            }

        } elseif ($action === 'cancel_offer') {
            $offerId = (int)$_POST['offer_id'];

            $result = $marketOffer->cancelOffer($offerId, Auth::getUserId());
            if ($result['success']) {
                $success = $result['message'];
                $storageData = $storage->getData();
            } else {
                $error = $result['message'];
            }

        } elseif ($action === 'accept_b2b_offer') {
 // Realizacja zlecenia skupu wystawionego przez innego gracza — dostawa ropy z magazynu za gotowke.
 // Fulfill a buy-offer posted by another player — deliver oil from storage for cash.
            $offerId = (int)($_POST['offer_id'] ?? 0);
            $result = $b2bService->acceptAndDeliver(Auth::getUserId(), $offerId);
            if (!empty($result['success'])) {
                $success = tPlain((string)($result['message_key'] ?? 'contracts.b2b.completed'));
                $storageData = $storage->getData();
            } else {
                $error = tPlain((string)($result['message_key'] ?? 'contracts.b2b.db_error'));
            }
        }
    }
}

$myOffers = $marketOffer->getPlayerOffers(Auth::getUserId());
$offers   = $myOffers; // alias for backwards compatibility

$priceHistory = $marketTick->getPriceHistory(6);

// Sale history with pagination / Historia sprzedazy z paginacja
$historyPerPage = 5;
$historyPage    = max(1, (int)($_GET['hpage'] ?? 1));
$historyData    = $marketOffer->getSaleHistory(Auth::getUserId(), $historyPage, $historyPerPage);
$historyRows    = $historyData['rows'];
$historyTotal   = $historyData['total'];
$historyPages   = (int)ceil($historyTotal / $historyPerPage);

$marketTitlePlain = html_entity_decode(strip_tags(tPlain('market.page_title')), ENT_QUOTES, 'UTF-8');
$pageTitle = $marketTitlePlain;

// == ZLECENIA GRACZY (B2B) — otwarte oferty skupu / Player buy-offers (B2B) ==
$b2bModuleEnabled = $b2bService->isModuleEnabled();
$b2bPerPage       = 12;
$b2bPage          = max(1, (int)($_GET['b2bpage'] ?? 1));
$b2bOffers        = [];
$b2bOffersCount   = 0;
$b2bReputation    = 50;
if ($b2bModuleEnabled) {
    $b2bOffers      = $b2bService->listOpenOffers(Auth::getUserId(), $b2bPerPage, ($b2bPage - 1) * $b2bPerPage);
    $b2bOffersCount = $b2bService->countOpenOffers(Auth::getUserId());
    $b2bReputation  = $b2bService->getPlayerReputationScore(Auth::getUserId());
}
$b2bPages = max(1, (int)ceil($b2bOffersCount / $b2bPerPage));

$allowedTabs = $b2bModuleEnabled ? ['market', 'black_market', 'b2b'] : ['market', 'black_market'];
$activeTab = $_GET['tab'] ?? 'market';
// Po realizacji zlecenia B2B (POST na te sama strone) pozostan na zakladce graczy.
// After fulfilling a B2B offer (self POST) stay on the player-offers tab.
if (($_POST['action'] ?? '') === 'accept_b2b_offer' && $b2bModuleEnabled) {
    $activeTab = 'b2b';
}
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'market';
}

$viewData = compact(
    'error', 'success', 'activeTab',
    'marketData', 'storageData', 'playerData',
    'offers', 'myOffers', 'priceHistory',
    'historyRows', 'historyTotal', 'historyPage', 'historyPages', 'historyPerPage',
    'b2bModuleEnabled', 'b2bOffers', 'b2bOffersCount', 'b2bPage', 'b2bPages', 'b2bReputation'
);
$viewData = array_merge(GameShell::data(Auth::getUserId()), $viewData);
$extraCss = [
    '/assets/css/market.css',
    '/assets/css/black_market.css',
    '/assets/css/contracts.css', // karty zlecen B2B / B2B offer cards
];
$gameShellTitle = $marketTitlePlain;
$gameShellView = __DIR__ . '/../templates/views/market/main.php';

require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';
