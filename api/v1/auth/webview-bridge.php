<?php
declare(strict_types=1);

/**
 * POST /api/v1/auth/webview-bridge
 *
 * Creates a short-lived one-time URL that turns a mobile Bearer token into
 * the regular web session used by the browser game.
 */
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/src/MobileWebBridge.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError(405, 'Method Not Allowed - use POST');
}

$player = apiRequireAuth();
$mailCfg = is_file(dirname(__DIR__, 3) . '/config/mail.php')
    ? require dirname(__DIR__, 3) . '/config/mail.php'
    : [];
$trustedBaseUrl = (string)($mailCfg['base_url'] ?? 'https://oilempire.pl');
$bridgeUrl = MobileWebBridge::createForPlayer((int)$player['id'], $trustedBaseUrl);

apiJson([
    'bridge_url' => $bridgeUrl,
    'expires_in_seconds' => MobileWebBridge::ttlSeconds(),
]);
