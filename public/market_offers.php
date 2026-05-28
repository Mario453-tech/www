<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('public/market_offers.php') : microtime(true);
try {


require_once __DIR__ . '/../src/init.php';

Auth::requireLogin();

$player = new Player(Auth::getUserId());
$storage = new Storage(Auth::getUserId());
$market = new Market();
$marketOffer = new MarketOffer();

$playerData = $player->getData();
$storageData = $storage->getData();
$marketData = $market->getState();
$myOffers = $marketOffer->getPlayerOffers(Auth::getUserId());

$error = '';
$success = '';

if ($_POST && isset($_POST['action'])) {
    if (!RateLimiter::check('action')) {
        $error = tPlain('common.rate_limit');
    } elseif (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $error = tPlain('auth.err_csrf');
    } elseif ($_POST['action'] === 'create_offer') {
        $amount = (int)$_POST['amount'];
        $limitPrice = (int)$_POST['limit_price'];
        
        $result = $marketOffer->createOffer(Auth::getUserId(), $amount, $limitPrice);
        
        if ($result['success']) {
            $success = $result['message'];
            $storageData['used'] -= $amount;
            $myOffers = $marketOffer->getPlayerOffers(Auth::getUserId());
        } else {
            $error = $result['message'];
        }
    } elseif ($_POST['action'] === 'edit_offer') {
        $offerId = (int)$_POST['offer_id'];
        $newLimitPrice = (int)$_POST['new_limit_price'];
        
        $result = $marketOffer->updateOffer($offerId, Auth::getUserId(), $newLimitPrice);
        
        if ($result['success']) {
            $success = $result['message'];
            $myOffers = $marketOffer->getPlayerOffers(Auth::getUserId());
        } else {
            $error = $result['message'];
        }
    } elseif ($_POST['action'] === 'cancel_offer') {
        $offerId = (int)$_POST['offer_id'];
        
        $result = $marketOffer->cancelOffer($offerId, Auth::getUserId());
        
        if ($result['success']) {
            $success = $result['message'];
            $storageData['used'] += $result['returned_amount'] ?? 0;
            $myOffers = $marketOffer->getPlayerOffers(Auth::getUserId());
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = tPlain('market_offers.page_title');

$statusItems = GameShell::statusItems(Auth::getUserId());

$extraCss = [
    '/assets/css/home.css',
    '/assets/css/market.css',
];

require_once __DIR__ . '/../templates/header.php';
?>

<div class="fade-in">
    <?php 
    require __DIR__ . '/../templates/components/alert.php';
    require __DIR__ . '/../templates/components/urgent_offer_alert.php';
    require __DIR__ . '/../templates/components/status_grid.php';
    ?>
    
    <section class="card" aria-labelledby="offers-heading">
        <h2 id="offers-heading"><?= t('market_offers.heading_my_offers') ?></h2>
        <?php require __DIR__ . '/../templates/components/my_offers_table.php' ?>
    </section>
    
    <?php if ($storageData['used'] > 0): ?>
        <section class="card" aria-labelledby="new-offer-heading">
            <h2 id="new-offer-heading"><?= t('market_offers.heading_new_offer') ?></h2>
            
            <form method="post" class="form-grid form-sell"
                  data-action-type="create_offer">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="create_offer">
                
                <div class="form-group">
                    <label for="amount"><?= t('market_offers.label_amount') ?></label>
                    <input type="number" id="amount" name="amount" min="1" max="<?= htmlspecialchars($storageData['used']) ?>" 
                           value="<?= htmlspecialchars(min(10, $storageData['used'])) ?>" required>
                    <small><?= t('market_offers.label_available', ['val' => htmlspecialchars($storageData['used'])]) ?></small>
                </div>
                
                <div class="form-group">
                    <label for="limit_price"><?= t('market_offers.label_limit_price') ?></label>
                    <input type="number" id="limit_price" name="limit_price" min="50" max="500" 
                           value="<?= htmlspecialchars(max(50, $marketData['current_price'] + 10)) ?>" required>
                    <small><?= t('market_offers.label_current_price', ['price' => htmlspecialchars(number_format($marketData['current_price']))]) ?></small>
                </div>
                
                <button type="submit" class="btn btn-success">
                    <?= t('market_offers.btn_submit') ?>
                </button>
            </form>
            
            <div class="info-box info-box-blue">
                <strong><?= t('market_offers.info_title') ?></strong><br>
                • <?= t('market_offers.info_1') ?><br>
                • <?= t('market_offers.info_2') ?><br>
                • <?= t('market_offers.info_3') ?><br>
                • <?= t('market_offers.info_4') ?>
            </div>
        </section>
    <?php endif ?>
    
    <nav class="page-nav" aria-label="Nawigacja strony">
            <a href="<?= url('market') ?>" class="btn btn-primary"><?= t('market_offers.btn_back_market') ?></a>
    </nav>
</div>

<script>
window.MARKET_PRICE = <?= json_encode((float)($marketData['current_price'] ?? 0)) ?>;
window.MARKET_MSG   = <?= json_encode($success) ?>;
window.MARKET_ERR   = <?= json_encode($error) ?>;
window.MARKET_LANG  = <?= json_encode([
    'confirm_sell'      => t('market.confirm_sell_instant'),
    'confirm_sell_btn'  => t('market.confirm_sell_btn'),
    'confirm_offer'     => t('market.confirm_create_offer'),
    'confirm_offer_btn' => t('market.confirm_offer_btn'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="/assets/js/market.js"></script>
<?php require_once __DIR__ . '/../templates/footer.php' ?>

<?php
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('public/market_offers.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo tPlain('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('public/market_offers.php', $_codexGuardStart);
    }
}
