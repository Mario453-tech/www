<?php
/**
 * public_html/index.php
 * Punkt wejcia zalogowany dashboard, niezalogowany login
 */
require_once __DIR__ . '/src/init.php';

if (!Auth::isLoggedIn()) {
    header('Location: /login', true, 302);
    exit;
}

require __DIR__ . '/public/index.php';
