<?php
/**
 * RecruitmentAPI - handles recruitment processes.
 *
 * Endpoints:
 * - startRecruitment: Starts a recruitment process (with a wait timer)
 * - checkRecruitmentStatus: Checks recruitment status
 * - getCandidates: Returns the list of candidates
 * - hireCandidate: Hires a selected candidate
 * - fireEmployee: Dismisses an employee
 * - getBoardMembers: Returns the list of board members
 */

class RecruitmentAPI {
    private $db;
    private $generator;
    private $hrService;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->generator = new CandidateGenerator($this->db);
        $this->hrService = new HRService();
    }
    
 /**
 * Starts a recruitment process for the given role.
 *
 * @param int $roleId Role ID
 * @param int $waitMinutes Wait time in minutes (default 60)
 * @return array Operation status
 */
    public function startRecruitment($roleId, $waitMinutes = 60) {
        try {
 // Check that the role exists and is active
            $stmt = $this->db->prepare("
                SELECT * FROM board_roles 
                WHERE id = ?
            ");
            $stmt->execute([$roleId]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$role) {
                return ['success' => false, 'error' => t('recruitment.err_role_not_found')];
            }
            
 // Check that no active recruitment process exists for this role
            $stmt = $this->db->prepare("
                SELECT * FROM recruitment_requests 
                WHERE role_id = ? AND status IN ('pending', 'ready')
            ");
            $stmt->execute([$roleId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                return ['success' => false, 'error' => t('recruitment.err_already_running')];
            }
            
 // Check that the position is not already filled
            $stmt = $this->db->prepare("
                SELECT * FROM board_members 
                WHERE role_id = ? AND status = 'active'
            ");
            $stmt->execute([$roleId]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($member) {
                return ['success' => false, 'error' => t('recruitment.err_position_occupied')];
            }
            
 // Create a new recruitment request
            $readyAt = date('Y-m-d H:i:s', strtotime('+' . $waitMinutes . ' minutes'));
            
            $stmt = $this->db->prepare("
                INSERT INTO recruitment_requests (role_id, ready_at, status)
                VALUES (?, ?, 'pending')
            ");
            $stmt->execute([$roleId, $readyAt]);
            $requestId = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'request_id' => $requestId,
                'role' => $role,
                'ready_at' => $readyAt,
                'wait_minutes' => $waitMinutes
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
 /**
 * Checks recruitment status and generates candidates if the wait has elapsed.
 *
 * @param int $requestId Recruitment request ID
 * @return array Recruitment status
 */
    public function checkRecruitmentStatus($requestId) {
        try {
            $stmt = $this->db->prepare("
                SELECT r.*, br.name as role_name, br.code as role_code
                FROM recruitment_requests r
                JOIN board_roles br ON r.role_id = br.id
                WHERE r.id = ?
            ");
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$request) {
                return ['success' => false, 'error' => t('recruitment.err_request_not_found')];
            }
            
 // If status is 'pending' and wait has elapsed - generate candidates
            if ($request['status'] === 'pending' && strtotime($request['ready_at']) <= time()) {
                $this->generator->generateCandidates($request['role_id']);
                
 // Update status to 'ready'
                $stmt = $this->db->prepare("
                    UPDATE recruitment_requests 
                    SET status = 'ready' 
                    WHERE id = ?
                ");
                $stmt->execute([$requestId]);
                
                $request['status'] = 'ready';
            }
            
            return [
                'success' => true,
                'request' => $request,
                'is_ready' => $request['status'] === 'ready',
                'time_remaining' => max(0, strtotime($request['ready_at']) - time())
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
 /**
 * Returns the list of candidates for a given role.
 *
 * @param int $roleId Role ID
 * @return array Candidate list
 */
    public function getCandidates($roleId) {
        try {
 // Remove expired candidates first
            $this->generator->cleanupExpiredCandidates();
            
            $stmt = $this->db->prepare("
                SELECT c.*, 
                       TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE()) as age,
                       TIMESTAMPDIFF(HOUR, NOW(), c.expires_at) as hours_remaining
                FROM candidates c
                WHERE c.role_id = ? AND c.expires_at > NOW()
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$roleId]);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'candidates' => $candidates,
                'count' => count($candidates)
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
 /**
 * Hires the selected candidate.
 *
 * @param int $candidateId Candidate ID
 * @return array Operation status
 */
    public function hireCandidate($candidateId, $playerId, $contractType = '1y') {
        try {
            GameLog::info('RecruitmentAPI', 'hireCandidate delegated to HRService', [
                'candidate_id' => (int)$candidateId,
                'player_id' => (int)$playerId,
                'contract_type' => (string)$contractType,
            ]);

            $result = $this->hrService->hireCandidate((int)$candidateId, (int)$playerId, (string)$contractType);

            if (($result['success'] ?? false) === true) {
                return [
                    'success' => true,
                    'member_id' => $result['member_id'] ?? null,
                    'message' => $result['message'] ?? t('recruitment.msg_hired'),
                ];
            }

            return [
                'success' => false,
                'error' => $result['message'] ?? t('recruitment.err_hire_failed'),
            ];
        } catch (Throwable $e) {
            GameLog::error('RecruitmentAPI', 'hireCandidate failed', $e, [
                'candidate_id' => (int)$candidateId,
                'player_id' => (int)$playerId,
                'contract_type' => (string)$contractType,
            ]);
            return ['success' => false, 'error' => t('recruitment.err_hire_exception')];
        }
    }
    
 /**
 * Dismisses an employee.
 *
 * @param int $memberId Board member ID
 * @param string|null $reason Reason for dismissal (null = default lang string)
 * @return array Operation status
 */
    public function fireEmployee($memberId, $reason = null) {
        $reason = $reason ?? t('recruitment.default_fire_reason');
        try {
            $this->db->beginTransaction();
            
 // Check that the employee exists
            $stmt = $this->db->prepare("
                SELECT * FROM board_members WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$memberId]);
            $member = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$member) {
                $this->db->rollBack();
                return ['success' => false, 'error' => t('recruitment.err_employee_not_found')];
            }
            
 // Update employee status
            $stmt = $this->db->prepare("
                UPDATE board_members 
                SET status = 'fired', fired_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$memberId]);
            
 // Add history record
            $stmt = $this->db->prepare("
                INSERT INTO employment_history (member_id, action, reason)
                VALUES (?, 'fired', ?)
            ");
            $stmt->execute([$memberId, $reason]);
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => t('recruitment.msg_fired'),
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

 /**
 * Returns the list of active board members.
 *
 * @return array Board member list
 */
    public function getBoardMembers() {
        try {
            $stmt = $this->db->prepare("
                SELECT m.*, r.name as role_name, r.code as role_code
                FROM board_members m
                JOIN board_roles r ON m.role_id = r.id
                WHERE m.status = 'active'
            ");
            $stmt->execute();
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'members' => $members,
                'count' => count($members),
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

function respondJson(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

// API request handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!Auth::isLoggedIn()) {
        GameLog::warn('RecruitmentAPI', 'Unauthorized access attempt', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        ]);
        respondJson(['success' => false, 'error' => t('recruitment.err_unauthorized')], 401);
    }

    if (!CSRF::validateToken($_POST['_token'] ?? '')) {
        GameLog::warn('RecruitmentAPI', 'Invalid CSRF token', [
            'player_id' => Auth::getUserId(),
            'action' => $_POST['action'] ?? '',
        ]);
        respondJson(['success' => false, 'error' => t('recruitment.err_csrf')], 419);
    }

    $api = new RecruitmentAPI();
    $action = $_POST['action'] ?? '';
    $playerId = Auth::getUserId();

    GameLog::info('RecruitmentAPI', 'Incoming action', [
        'action' => $action,
        'player_id' => $playerId,
    ]);

    try {
        $response = [];

        switch ($action) {
            case 'start_recruitment':
                $roleId = (int)($_POST['role_id'] ?? 0);
                if ($roleId <= 0) { throw new InvalidArgumentException(t('recruitment.err_missing_role_id')); }
                $waitMinutes = (int)($_POST['wait_minutes'] ?? 60);
                $response = $api->startRecruitment($roleId, $waitMinutes);
                break;

            case 'check_status':
                $requestId = (int)($_POST['request_id'] ?? 0);
                if ($requestId <= 0) { throw new InvalidArgumentException(t('recruitment.err_missing_request_id')); }
                $response = $api->checkRecruitmentStatus($requestId);
                break;

            case 'get_candidates':
                $roleId = (int)($_POST['role_id'] ?? 0);
                if ($roleId <= 0) { throw new InvalidArgumentException(t('recruitment.err_missing_role_id')); }
                $response = $api->getCandidates($roleId);
                break;

            case 'hire_candidate':
                $candidateId = (int)($_POST['candidate_id'] ?? 0);
                if ($candidateId <= 0) { throw new InvalidArgumentException(t('recruitment.err_missing_candidate_id')); }
                $contractType = $_POST['contract_type'] ?? '1y';
                $response = $api->hireCandidate($candidateId, $playerId, $contractType);
                break;

            case 'fire_employee':
                $memberId = (int)($_POST['member_id'] ?? 0);
                if ($memberId <= 0) { throw new InvalidArgumentException(t('recruitment.err_missing_member_id')); }
                $reason = $_POST['reason'] ?? null;
                $response = $api->fireEmployee($memberId, $reason);
                break;

            case 'get_board_members':
                $response = $api->getBoardMembers();
                break;

            default:
                GameLog::warn('RecruitmentAPI', 'Unknown action', [
                    'player_id' => $playerId, 'action' => $action,
                ]);
                respondJson(['success' => false, 'error' => t('recruitment.err_unknown_action')], 400);
        }

        respondJson($response, 200);
    } catch (InvalidArgumentException $e) {
        GameLog::warn('RecruitmentAPI', 'Validation error', [
            'player_id' => $playerId, 'action' => $action, 'error' => $e->getMessage(),
        ]);
        respondJson(['success' => false, 'error' => $e->getMessage()], 422);
    } catch (Throwable $e) {
        GameLog::error('RecruitmentAPI', 'Unhandled API error', $e, [
            'player_id' => $playerId, 'action' => $action,
        ]);
        respondJson(['success' => false, 'error' => t('recruitment.err_internal')], 500);
    }
}

respondJson(['success' => false, 'error' => 'Method Not Allowed'], 405);
