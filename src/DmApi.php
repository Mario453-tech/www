<?php
// Private messages API endpoint.
// PL: Endpoint API prywatnych wiadomosci.
ob_start();
require_once __DIR__ . '/init.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

try {
    Auth::requireLogin();
} catch (Throwable $e) {
    echo json_encode(['error' => t('common.err_not_logged_in')]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    echo json_encode(['error' => t('common.err_db')]);
    exit;
}

// Delegate to ChatApi with DM-compatible query params.
// PL: Deleguj do ChatApi z parametrami zgodnymi z DM.
$_GET['with'] = $_GET['with'] ?? null;
require_once __DIR__ . '/ChatApi.php';
