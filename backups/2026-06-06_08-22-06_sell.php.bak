<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('public/sell.php') : microtime(true);
try {


require_once __DIR__ . '/../src/init.php';

Auth::requireLogin();

if (!RateLimiter::check('action')) {
    die(tPlain('common.rate_limit'));
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    die(tPlain('auth.err_csrf'));
}

$player = new Player(Auth::getUserId());
$storage = new Storage(Auth::getUserId());
$market = new Market();

$currentPrice = $market->getCurrentPrice();
$earnings = $storage->sellAll($currentPrice);

if ($earnings > 0) {
    $player->updateCash($earnings);
}

header('Location: /');
exit();

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('public/sell.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo tPlain('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('public/sell.php', $_codexGuardStart);
    }
}
