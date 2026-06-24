<?php
declare(strict_types=1);

/**
 * POST /api/v1/auth/logout
 *
 * Uniewa znia token uzywany w tym zadaniu.
 * Revokes the token used in this request.
 * Authorization: Bearer <token>
 */
require_once dirname(__DIR__) . '/_bootstrap.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'], true)) {
    apiError(405, 'Method Not Allowed — use POST lub DELETE');
}

apiRequireAuth(); // weryfikuje token / verifies token

$token = ApiAuth::getRawToken();
ApiAuth::revokeToken($token);

apiJson(['success' => true, 'message' => 'Wylogowano pomyslnie']);
