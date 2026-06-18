<?php

declare(strict_types=1);

/**
 * wallet_transfer.php - AJAX endpoint dla transferu miedzy pulami portfela.
 * wallet_transfer.php - AJAX endpoint for wallet pool transfers.
 *
 * POST /wallet-transfer
 *   action:      'cash_to_bank' | 'bank_to_cash'
 *   amount:      kwota (string, moze zawierac spacje i przecinek dziesietny)
 *   csrf_token:  token CSRF
 *
 * Response JSON:
 *   {success, message, new_cash, new_bank, fee}
 */

require_once __DIR__ . '/../src/init.php';

header('Content-Type: application/json; charset=utf-8');

// Tylko POST / POST only.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

// Wymaga zalogowania / Requires login.
Auth::requireLogin();

// Walidacja CSRF / CSRF validation.
$csrfToken = (string)($_POST['csrf_token'] ?? '');
if (!CSRF::validateToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => tPlain('wallet.err_csrf')]);
    exit;
}

$playerId = Auth::getUserId();
$action   = (string)($_POST['action'] ?? '');

// Normalizacja kwoty: usun spacje, zamien przecinek na kropke.
// Normalize amount: remove spaces, replace comma with dot.
$rawAmount = (string)($_POST['amount'] ?? '');
$rawAmount = str_replace([' ', "\xc2\xa0"], '', $rawAmount); // usun spacje i nbsp
$rawAmount = str_replace(',', '.', $rawAmount);
$amount    = (float)$rawAmount;

if (!in_array($action, ['cash_to_bank', 'bank_to_cash'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => tPlain('wallet.err_invalid_action')]);
    exit;
}

try {
    $svc = new CashTransferService($playerId);
    $res = ($action === 'cash_to_bank')
        ? $svc->cashToBank($amount)
        : $svc->bankToCash($amount);
} catch (Throwable $e) {
    GameLog::error('wallet_transfer', 'CashTransferService FAILED', $e, [
        'player' => $playerId,
        'action' => $action,
        'amount' => $amount,
    ]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => tPlain('wallet.err_db')]);
    exit;
}

echo json_encode($res, JSON_UNESCAPED_UNICODE);
exit;
