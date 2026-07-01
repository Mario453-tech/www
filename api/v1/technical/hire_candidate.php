<?php
declare(strict_types=1);

/**
 * POST /api/v1/technical/hire_candidate.php
 *
 * Hires a recruitment candidate as a technical staff member.
 * Zatrudnia kandydata jako pracownika technicznego.
 *
 * Body: {"candidate_id": 1}
 * 200: {success:true, message}
 * 422: {success:false, message}
 */
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once $_API_ROOT . '/src/i18n.php';
require_once $_API_ROOT . '/src/HRService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError(405, 'Method Not Allowed — use POST');
}

$player      = apiRequireAuth();
$playerId    = (int)$player['id'];

$body        = apiBody();
$candidateId = isset($body['candidate_id']) ? (int)$body['candidate_id'] : 0;

if ($candidateId <= 0) {
    apiError(400, 'Missing or invalid candidate_id');
}

$svc    = new HRService();
$result = $svc->hireCandidate($candidateId, $playerId);

if ($result['success']) {
    apiJson(['success' => true, 'message' => (string)($result['message'] ?? '')]);
} else {
    apiJson(['success' => false, 'message' => (string)($result['message'] ?? '')], 422);
}
