<?php
// Wgraj do /admin/redirect_diag.php i otwórz BEZ logowania
// NIE ma requireLogin() - celowo
require_once __DIR__ . '/init.php';
GameLog::info('admin/redirect_diag.php', 'entry');

session_start();

echo "<pre style='font-family:monospace;background:#111;color:#0f0;padding:20px'>";
echo "=== DIAGNOSTYKA PRZEKIEROWAÑ ===\n\n";

echo "PHP_SELF: " . $_SERVER['PHP_SELF'] . "\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'brak') . "\n";
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'brak') . "\n";
echo "SERVER_PORT: " . ($_SERVER['SERVER_PORT'] ?? 'brak') . "\n\n";

echo "=== SESJA ===\n";
echo "session_id: " . session_id() . "\n";
echo "admin_logged_in: " . var_export($_SESSION['admin_logged_in'] ?? null, true) . "\n";
echo "admin_user: " . ($_SESSION['admin_user'] ?? 'brak') . "\n";
echo "admin_ip (sesja): " . ($_SESSION['admin_ip'] ?? 'brak') . "\n";
echo "admin_last_active: " . ($_SESSION['admin_last_active'] ?? 'brak') . "\n";
echo "REMOTE_ADDR (teraz): " . ($_SERVER['REMOTE_ADDR'] ?? 'brak') . "\n\n";

echo "=== .HTACCESS / HTTPS REDIRECT ===\n";
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || ($_SERVER['SERVER_PORT'] ?? '') == 443
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
echo "Wykryty HTTPS: " . ($isHttps ? 'TAK' : 'NIE') . "\n";
echo "HTTP_X_FORWARDED_PROTO: " . ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'brak') . "\n";
echo "HTTP_X_FORWARDED_SSL: " . ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? 'brak') . "\n\n";

echo "=== PLIKI .HTACCESS ===\n";
$dirs = [
    '/home/vh15188/public_html/.htaccess',
    '/home/vh15188/public_html/admin/.htaccess',
    '/home/vh15188/public_html/public/.htaccess',
];
foreach ($dirs as $f) {
    if (file_exists($f)) {
        echo "ZNALEZIONO: $f\n";
        echo file_get_contents($f) . "\n---\n";
    } else {
        echo "BRAK: $f\n";
    }
}

echo "\n=== INIT.PHP TEST ===\n";
try {
    require_once __DIR__ . '/init.php';
    echo "init.php: OK\n";
    echo "AdminAuth::isLoggedIn(): " . var_export(AdminAuth::isLoggedIn(), true) . "\n";
} catch (Throwable $e) {
    echo "init.php B£¥D: " . $e->getMessage() . "\n";
}

echo "</pre>";
