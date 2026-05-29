<?php

require_once __DIR__ . '/../src/init.php';

GameLog::info('public/market.php', 'entry');
Auth::requireLogin();

$player      = new Player(Auth::getUserId());
$storage     = new Storage(Auth::getUserId());
$market      = new Market();
$marketTick  = new MarketTick();
$marketOffer = new MarketOffer();

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
                $db->beginTransaction();

                try {
                    $player->updateCash($earnings);

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
        }
    }
}

$myOffers = $marketOffer->getPlayerOffers(Auth::getUserId());
$offers   = $myOffers; // alias for backwards compatibility

$priceHistory = $marketTick->getPriceHistory(6);

$marketTitlePlain = html_entity_decode(strip_tags(tPlain('market.page_title')), ENT_QUOTES, 'UTF-8');
$pageTitle = $marketTitlePlain;

$activeTab = $_GET['tab'] ?? 'market';
if (!in_array($activeTab, ['market', 'black_market'])) {
    $activeTab = 'market';
}

$viewData = compact(
    'error', 'success', 'activeTab',
    'marketData', 'storageData', 'playerData',
    'offers', 'myOffers', 'priceHistory'
);
$viewData = array_merge(GameShell::data(Auth::getUserId()), $viewData);
$extraCss = [
    '/assets/css/market.css',
    '/assets/css/black_market.css',
];
$gameShellTitle = $marketTitlePlain;
$gameShellView = __DIR__ . '/../templates/views/market/main.php';

require_once __DIR__ . '/../templates/header.php';
extract($viewData, EXTR_SKIP);
require __DIR__ . '/../templates/components/game_shell.php';
require_once __DIR__ . '/../templates/footer.php';
