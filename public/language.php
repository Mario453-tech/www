<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/init.php';

$allowedLocales = ['pl', 'en'];
$locale = (string)($_POST['locale'] ?? '');
$redirect = (string)($_POST['redirect'] ?? '/');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    $locale = '';
}

if (in_array($locale, $allowedLocales, true)) {
    $_SESSION['locale'] = $locale;
    setcookie('locale', $locale, [
        'expires' => time() + 31536000,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

if ($redirect === '' || $redirect[0] !== '/' || str_starts_with($redirect, '//')) {
    $redirect = '/';
}

header('Location: ' . $redirect, true, 303);
exit;
