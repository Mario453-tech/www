<?php
/**
 * AJAX endpoint for HR module actions.
 * PL: Endpoint AJAX dla akcji modulu HR.
 *
 * Path: /src/HRApi.php
 * PL: Sciezka: /src/HRApi.php
 */
ob_start(); // Buffer output to prevent accidental bytes before JSON. / PL: Buforuj output, aby uniknac bajtow przed JSON.
require_once __DIR__ . '/init.php';

// Clear any buffered output before sending JSON headers.
// PL: Wyczysc bufor przed wyslaniem naglowkow JSON.
ob_clean();
header('Content-Type: application/json; charset=utf-8');

function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if (!Auth::isLoggedIn()) {
    GameLog::warn('HRApi', 'Unauthorized access attempt', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    ]);
    respondJson(['success' => false, 'error' => t('common.not_logged_in')], 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['_token'] ?? '')) {
        GameLog::warn('HRApi', 'Invalid CSRF token', [
            'player_id' => Auth::getUserId(),
            'action' => $_REQUEST['action'] ?? '',
        ]);
        respondJson(['success' => false, 'error' => t('common.csrf_error')], 419);
    }
}

$hr = new HRService();
$hh = new HeadhunterService(Auth::getUserId());
$playerId = Auth::getUserId();
$action = $_REQUEST['action'] ?? '';
$db = Database::getInstance()->getConnection();

GameLog::info('HRApi', 'Incoming action', [
    'player_id' => $playerId,
    'action' => $action,
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
]);

try {
    switch ($action) {
        case 'get_panel_data':
 // processReadyRecruitments() is only triggered by tick.php.
 // PL: processReadyRecruitments() jest wywolywane tylko przez tick.php.
            echo json_encode([
                'success' => true,
                'employees' => $hr->getActiveEmployees(),
                'directors' => $hr->getActiveDirectors(),
                'recruitments' => $hr->getActiveRecruitments(),
                'contracts' => $hr->getActiveContracts(),
                'regions' => $hr->getRegions(),
                'events' => $hr->getUnreadEvents($playerId),
            ]);
            break;

        case 'get_candidates':
            $roleId = (int)($_REQUEST['role_id'] ?? 0);
            if (!$roleId) {
                throw new InvalidArgumentException(t('hr_api.err_missing_role_id'));
            }
            echo json_encode([
                'success' => true,
                'candidates' => $hr->getCandidatesForRole($roleId),
            ]);
            break;

        case 'start_recruitment':
            $roleId = (int)($_POST['role_id'] ?? 0);
            $regionCode = Validator::sanitize($_POST['region_code'] ?? 'PL');
            $specCode = !empty($_POST['spec_code']) ? Validator::sanitize($_POST['spec_code']) : null;
            $initiator = $_POST['initiated_by'] ?? 'director';
            if (!in_array($initiator, ['director', 'hr', 'technical'], true)) {
                $initiator = 'director';
            }
            $recruitmentType = in_array($_POST['recruitment_type'] ?? '', ['local', 'international'], true)
                ? $_POST['recruitment_type']
                : 'local';

            if (!$roleId) {
                throw new InvalidArgumentException(t('hr_api.err_missing_role_id'));
            }
            if ($initiator === 'hr' && !$specCode) {
                throw new InvalidArgumentException(t('hr.err_missing_specialization'));
            }

            $roleStmt = $db->prepare("SELECT code FROM board_roles WHERE id = ? LIMIT 1");
            $roleStmt->execute([$roleId]);
            $roleCode = (string)($roleStmt->fetchColumn() ?: '');
            if ($roleCode === '') {
                throw new InvalidArgumentException(t('recruitment.err_role_not_found'));
            }
            if ($initiator === 'hr' || $initiator === 'technical') {
                respondJson(['success' => false, 'error' => t('hr.recruitment_moved_to_dashboard')], 403);
            }
            if ($initiator === 'director') {
                $specCode = null;
            }

 // Max 2 parallel recruitments.
 // PL: Maksymalnie 2 rownolegle rekrutacje.
            if ($initiator === 'director') {
                $cntStmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM recruitment_requests
                    WHERE player_id = ?
                      AND initiated_by = 'director'
                      AND COALESCE(spec_code, '') = ''
                      AND status IN ('pending','ready')
                ");
                $cntStmt->execute([$playerId]);
            } else {
                $cntStmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM recruitment_requests
                    WHERE player_id = ?
                      AND initiated_by = ?
                      AND status IN ('pending','ready')
                ");
                $cntStmt->execute([$playerId, $initiator]);
            }
            $activeCount = (int)$cntStmt->fetchColumn();
            if ($activeCount >= 2) {
                respondJson(['success' => false, 'error' => t('hr.err_max_recruitments')], 422);
            }

 // Prevent duplicate recruitment for the same role.
 // PL: Nie pozwalaj na duplikat rekrutacji dla tej samej roli.
            if ($initiator === 'director') {
                $dupStmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM recruitment_requests
                    WHERE player_id = ?
                      AND role_id = ?
                      AND initiated_by = 'director'
                      AND COALESCE(spec_code, '') = ''
                      AND status IN ('pending','ready')
                ");
                $dupStmt->execute([$playerId, $roleId]);
            } else {
                $dupStmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM recruitment_requests
                    WHERE player_id = ?
                      AND role_id = ?
                      AND initiated_by = ?
                      AND status IN ('pending','ready')
                ");
                $dupStmt->execute([$playerId, $roleId, $initiator]);
            }
            if ((int)$dupStmt->fetchColumn() > 0) {
                respondJson(['success' => false, 'error' => t('hr.err_role_already_recruiting')], 422);
            }

            $result = $hr->startRecruitment($playerId, $roleId, $regionCode, $specCode, $initiator, $recruitmentType);
            $active = array_values(array_filter($hr->getActiveRecruitments($playerId), static fn($rec) => (int)$rec['id'] === (int)($result['request_id'] ?? 0)));
            if (!empty($active)) {
                $result['recruitment'] = $active[0];
            }
            echo json_encode($result);
            break;

        case 'hire_candidate':
            $candidateId = (int)($_POST['candidate_id'] ?? 0);
            $contractType = Validator::sanitize($_POST['contract_type'] ?? '1y');
            if (!$candidateId) {
                throw new InvalidArgumentException(t('hr_api.err_missing_candidate_id'));
            }
            echo json_encode($hr->hireCandidate($candidateId, $playerId, $contractType));
            break;

        case 'fire_employee':
            $memberId = (int)($_POST['member_id'] ?? 0);
            $reason = Validator::sanitize($_POST['reason'] ?? t('hr_api.default_director_reason'));
            if (!$memberId) {
                throw new InvalidArgumentException(t('hr_api.err_missing_member_id'));
            }
            echo json_encode($hr->fireEmployee($memberId, $reason, $playerId));
            break;

        case 'fire_technical_staff':
            $staffId = (int)($_POST['staff_id'] ?? 0);
            $reason = Validator::sanitize($_POST['reason'] ?? t('hr_api.default_director_reason'));
            if (!$staffId) {
                throw new InvalidArgumentException(t('hr_api.err_missing_staff_id'));
            }
            echo json_encode($hr->fireTechnicalStaff($staffId, $playerId, $reason));
            break;

        case 'mark_events_read':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respondJson(['success' => false, 'error' => 'Method Not Allowed'], 405);
            }
            $hr->markEventsRead($playerId);
            echo json_encode(['success' => true]);
            break;

        case 'get_regions':
            echo json_encode(['success' => true, 'regions' => $hr->getRegions()]);
            break;

        case 'get_specializations':
            echo json_encode(['success' => true, 'specializations' => $hr->getSpecializations()]);
            break;

        case 'get_all_candidates':
            echo json_encode(['success' => true, 'candidates' => $hr->getAllCandidates($playerId)]);
            break;

        case 'get_history':
            echo json_encode(['success' => true, 'history' => $hr->getHistory($playerId, 100)]);
            break;

        case 'reject_candidate':
            $candidateId = (int)($_POST['candidate_id'] ?? 0);
            if (!$candidateId) {
                throw new InvalidArgumentException(t('hr_api.err_missing_candidate_id'));
            }
            echo json_encode($hr->rejectCandidate($candidateId, $playerId));
            break;

        case 'save_candidate':
            $candidateId = (int)($_POST['candidate_id'] ?? 0);
            if (!$candidateId) {
                throw new InvalidArgumentException(t('hr_api.err_missing_candidate_id'));
            }
            echo json_encode($hr->saveCandidate($candidateId, $playerId));
            break;

        case 'renew_contract':
            $memberId = (int)($_POST['member_id'] ?? 0);
            $contractType = $_POST['contract_type'] ?? '1y';
            if (!$memberId) {
                throw new InvalidArgumentException(t('hr_api.err_missing_member_id'));
            }
            echo json_encode($hr->renewContract($memberId, $contractType, $playerId));
            break;

        case 'start_headhunter':
            $hh->processReady();
            $specId = (int)($_POST['specialization_id'] ?? 0);
            if ($specId <= 0) {
                throw new InvalidArgumentException(t('hr_api.err_missing_specialization_id'));
            }
            echo json_encode($hh->startSearch($specId));
            break;

        case 'open_strike_negotiation':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respondJson(['success' => false, 'error' => t('hr_api.err_post_required')], 405);
            }
            $strikeId = (int)($_POST['strike_id'] ?? 0);
            if ($strikeId <= 0) {
                throw new InvalidArgumentException(t('hr_api.err_missing_strike_id'));
            }
            require_once __DIR__ . '/HR/EmployeeNegotiationService.php';
            try {
                $result = (new EmployeeNegotiationService($db))->openForStrike($playerId, $strikeId);
                $result['message'] = t('hr.msg_strike_negotiation_opened');
                respondJson($result);
            } catch (Throwable $e) {
                GameLog::warn('HRApi', 'Strike negotiation could not be opened', [
                    'player_id' => $playerId,
                    'strike_id' => $strikeId,
                    'exception_class' => get_class($e),
                    'error' => $e->getMessage(),
                ]);
                respondJson(['success' => false, 'error' => t('hr.err_strike_negotiation_unavailable')], 422);
            }

        case 'submit_strike_offer':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respondJson(['success' => false, 'error' => t('hr_api.err_post_required')], 405);
            }
            $strikeId = (int)($_POST['strike_id'] ?? 0);
            $raiseRaw = $_POST['raise_pct'] ?? null;
            $bonusRaw = $_POST['bonus_per_member'] ?? null;
            $token = trim((string)($_POST['idempotency_token'] ?? ''));
            if ($strikeId <= 0) {
                throw new InvalidArgumentException(t('hr_api.err_missing_strike_id'));
            }
            if ($token === '') {
                throw new InvalidArgumentException(t('hr_api.err_missing_idempotency_token'));
            }
            if (!is_numeric($raiseRaw) || !is_numeric($bonusRaw)) {
                throw new InvalidArgumentException(t('hr_api.err_invalid_strike_offer'));
            }
            require_once __DIR__ . '/HR/EmployeeNegotiationService.php';
            try {
                $result = (new EmployeeNegotiationService($db))->submitOffer(
                    $playerId,
                    $strikeId,
                    (float)$raiseRaw,
                    (float)$bonusRaw,
                    $token
                );
                $result['message'] = t('hr.strike_offer_result.' . (string)$result['result']);
                unset($result['formula']);
                respondJson($result);
            } catch (Throwable $e) {
                GameLog::warn('HRApi', 'Strike negotiation offer failed', [
                    'player_id' => $playerId,
                    'strike_id' => $strikeId,
                    'exception_class' => get_class($e),
                    'error' => $e->getMessage(),
                ]);
                respondJson(['success' => false, 'error' => t('hr.err_strike_offer_rejected')], 422);
            }
        case 'accept_raise_request':
        case 'negotiate_raise_request':
        case 'reject_raise_request':
        case 'postpone_raise_request':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respondJson(['success' => false, 'error' => t('hr_api.err_post_required')], 405);
            }
            $requestId = (int)($_POST['request_id'] ?? 0);
            $token = trim((string)($_POST['idempotency_token'] ?? ''));
            if ($requestId <= 0) {
                throw new InvalidArgumentException(t('hr_api.err_missing_raise_request_id'));
            }
            if ($token === '') {
                throw new InvalidArgumentException(t('hr_api.err_missing_idempotency_token'));
            }
            try {
                $raiseService = new EmployeeRaiseRequestService($db);
                $result = match ($action) {
                    'accept_raise_request' => $raiseService->acceptFull($playerId, $requestId, $token),
                    'reject_raise_request' => $raiseService->reject($playerId, $requestId, $token),
                    'postpone_raise_request' => $raiseService->postpone($playerId, $requestId, $token),
                    'negotiate_raise_request' => is_numeric($_POST['offered_salary'] ?? null)
                        ? $raiseService->negotiate($playerId, $requestId, (float)$_POST['offered_salary'], $token)
                        : throw new InvalidArgumentException(t('hr_api.err_invalid_raise_offer')),
                };
                $result['success'] = true;
                $result['message'] = t('hr.raise_result.' . (string)$result['result']);
                unset($result['roll']);
                respondJson($result);
            } catch (Throwable $e) {
                GameLog::warn('HRApi', 'Raise request action failed', [
                    'player_id' => $playerId,
                    'request_id' => $requestId,
                    'action' => $action,
                    'exception_class' => get_class($e),
                    'error' => $e->getMessage(),
                ]);
                respondJson(['success' => false, 'error' => t('hr.err_raise_request_action')], 422);
            }
        case 'grant_bonus':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respondJson(['success' => false, 'error' => t('hr_api.err_post_required')], 405);
            }
            $staffId = (int)($_POST['staff_id'] ?? 0);
            if ($staffId <= 0) {
                throw new InvalidArgumentException(t('hr_api.err_missing_staff_id'));
            }
            require_once __DIR__ . '/HR/EmployeeBonusService.php';
            $bonus = (new EmployeeBonusService($db))->grantTechnicalBonus($playerId, $staffId);
            if (!$bonus['success']) {
                respondJson(['success' => false, 'error' => t('hr.err_no_funds_for_bonus')], 422);
            }
            respondJson(['success' => true, 'message' => t('hr.msg_bonus_granted')]);
        case 'get_headhunter_status':
            $hh = new HeadhunterService($playerId);
            $hh->processReady();
            echo json_encode([
                'success' => true,
                'active' => $hh->getActiveSearch(),
                'candidates' => $hh->getAvailableCandidates(),
            ]);
            break;

        case 'make_offer':
            $hh = new HeadhunterService($playerId);
            $candidateId = (int)($_POST['candidate_id'] ?? 0);
            $offeredSalary = (float)($_POST['offered_salary'] ?? 0);
            $signingBonus = (float)($_POST['signing_bonus'] ?? 0);
            if (!$candidateId || $offeredSalary <= 0) {
                throw new InvalidArgumentException(t('hr_api.err_missing_offer_data'));
            }
            echo json_encode($hh->makeOffer($candidateId, $offeredSalary, $signingBonus));
            break;

        case 'hire_headhunter_candidate':
            $hh = new HeadhunterService($playerId);
            $candidateId = (int)($_POST['candidate_id'] ?? 0);
            $offeredSalary = (float)($_POST['offered_salary'] ?? ($_POST['salary'] ?? 0));
            $signingBonus = (float)($_POST['signing_bonus'] ?? 0);
            if (!$candidateId || $offeredSalary <= 0) {
                throw new InvalidArgumentException(t('hr_api.err_missing_offer_data'));
            }
            echo json_encode($hh->makeOffer($candidateId, $offeredSalary, $signingBonus));
            break;

        case 'make_headhunter_offer':
            $hh->processReady();
            $candId = (int)($_POST['candidate_id'] ?? 0);
            $salary = (float)($_POST['salary'] ?? 0);
            $bonus = (float)($_POST['signing_bonus'] ?? 0);
            if (!$candId || !$salary) {
                throw new InvalidArgumentException(t('hr_api.err_missing_offer_data'));
            }
            echo json_encode($hh->makeOffer($candId, $salary, $bonus));
            break;

        default:
            GameLog::warn('HRApi', 'Unknown action', [
                'player_id' => $playerId,
                'action' => $action,
            ]);
            respondJson(['success' => false, 'error' => t('common.unknown_action', ['action' => $action])], 400);
    }
} catch (InvalidArgumentException $e) {
    GameLog::warn('HRApi', 'Validation error', [
        'player_id' => $playerId,
        'action' => $action,
        'error' => $e->getMessage(),
    ]);
    respondJson(['success' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    GameLog::error('HRApi', 'Unhandled API error', $e, [
        'player_id' => $playerId,
        'action' => $action,
    ]);
    respondJson(['success' => false, 'error' => t('common.app_error')], 500);
}
