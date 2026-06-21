<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/init.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Tylko POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// CSRF
if (!CSRF::validateToken((string)($body['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => t('common.csrf_error')]);
    exit;
}

// Załaduj serwisy prywatności
require_once __DIR__ . '/../../src/Privacy/PrivacyFeatureRegistry.php';

$db             = Database::getInstance()->getConnection();
$privSettings   = new PrivacySettingsService($db);
$privConsent    = new PrivacyConsentService($db, $privSettings);

// Walidacja kategorii
$validCategories  = ['necessary', 'preferences', 'analytics', 'marketing'];
$rawAccepted      = $body['accepted_categories'] ?? [];
if (!is_array($rawAccepted)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => t('privacy.consent.invalid_categories')]);
    exit;
}

// Kategoria "necessary" jest zawsze wymagana
$accepted = ['necessary'];
foreach ($rawAccepted as $cat) {
    $cat = (string)$cat;
    if (in_array($cat, $validCategories, true) && !in_array($cat, $accepted, true)) {
        $accepted[] = $cat;
    }
}
$rejected = array_values(array_diff($validCategories, $accepted));

$source      = in_array((string)($body['source'] ?? ''), ['banner', 'settings', 'api'], true)
               ? (string)$body['source']
               : 'banner';
$playerId    = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$anonToken   = $playerId ? '' : PrivacyConsentService::getOrCreateAnonymousToken();
$ip          = $_SERVER['REMOTE_ADDR'] ?? '';
$ua          = $_SERVER['HTTP_USER_AGENT'] ?? '';

$id = $privConsent->saveConsent($playerId, $anonToken, $accepted, $rejected, $source, $ip, $ua);

echo json_encode([
    'success'    => $id > 0,
    'message'    => $id > 0 ? t('privacy.consent.saved') : 'Save failed',
    'consent_id' => $id,
]);
