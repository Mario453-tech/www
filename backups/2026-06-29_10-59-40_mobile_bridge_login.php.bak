<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/init.php';
require_once __DIR__ . '/../src/MobileWebBridge.php';

$token = trim((string)($_GET['token'] ?? ''));
$player = MobileWebBridge::consume($token);
if (!$player) {
    header('Location: ' . url('login') . '?mobile_bridge=invalid');
    exit;
}

if (!Auth::loginByPlayerId((int)$player['id'])) {
    header('Location: ' . url('login') . '?mobile_bridge=failed');
    exit;
}

header('Location: ' . url('home'));
exit;
