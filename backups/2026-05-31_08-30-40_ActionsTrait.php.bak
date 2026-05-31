<?php

trait TechnicalPageActionsTrait
{
    public function handlePost(): array
    {
        $msg = '';
        $msgType = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return [$msg, $msgType];
        }

        GameLog::info('technical.php', 'POST request', [
            'action' => $_POST['action'] ?? '?',
            'keys'   => array_keys($_POST),
        ]);

        if (!CSRF::validateToken($_POST['_token'] ?? '')) {
            GameLog::warn('technical.php', 'CSRF validation FAILED');
            return [t('common.csrf_error'), 'error'];
        }

        $action = $_POST['action'] ?? '';
        GameLog::step('technical.php', 'POST', 1, "action: {$action}");

        switch ($action) {
            case 'assign_task':
                return $this->handleAssignTask();
            case 'fire_engineer':
                return $this->handleFireEngineer();
            case 'review_candidate':
                return $this->handleReviewCandidate();
            case 'request_recruitment':
                return $this->handleRequestRecruitment();
            case 'cancel_recruitment':
                return $this->handleCancelRecruitment();
            case 'cancel_task':
                return $this->handleCancelTask();
            case 'cancel_queue_item':
                return $this->handleCancelQueueItem();
            case 'upgrade_procedures':
                return $this->handleUpgradeProcedures();
            case 'repair_procedure_integrity':
                return $this->handleRepairProcedureIntegrity();
            case 'dismiss_notification':
                $this->handleDismissNotification();
                exit;
            default:
                GameLog::warn('technical.php', "Unknown POST action: {$action}");
                return [$msg, $msgType];
        }
    }

    private function handleAssignTask(): array
    {
        try {
            $result = $this->svc->assignTask(
                (int)($_POST['staff_id'] ?? 0),
                $_POST['task_type'] ?? '',
                ($_POST['well_id'] ?? '') !== '' ? (int)$_POST['well_id'] : null,
                ($_POST['module_type'] ?? '') !== '' ? $_POST['module_type'] : null,
                ($_POST['hub_id'] ?? '') !== '' ? (int)$_POST['hub_id'] : null
            );
            GameLog::info('technical.php', 'assign_task result', $result);
            return [$result['message'], $result['success'] ? 'success' : 'error'];
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'assign_task EXCEPTION', $e);
            return [t('technical.err_assign_task'), 'error'];
        }
    }

    private function handleFireEngineer(): array
    {
        try {
            $result = $this->svc->fireEngineer((int)($_POST['staff_id'] ?? 0));
            GameLog::info('technical.php', 'fire_engineer result', $result);
            return [$result['message'], $result['success'] ? 'success' : 'error'];
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'fire_engineer EXCEPTION', $e);
            return [t('technical.err_fire_engineer'), 'error'];
        }
    }

    private function handleReviewCandidate(): array
    {
        try {
            $result = $this->svc->reviewCandidate(
                (int)($_POST['candidate_id'] ?? 0),
                (int)($_POST['technical_score'] ?? 0),
                $_POST['recommendation'] ?? 'reject',
                Validator::sanitize($_POST['comment'] ?? '')
            );
            GameLog::info('technical.php', 'review_candidate result', $result);
            return [$result['message'], $result['success'] ? 'success' : 'error'];
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'review_candidate EXCEPTION', $e);
            return [t('technical.err_review'), 'error'];
        }
    }

    private function handleRequestRecruitment(): array
    {
        try {
            $requestedCount = max(1, min(3, (int)($_POST['count'] ?? 1)));
            $specCode = $_POST['spec_code'] ?? '';
            $regionCode = $_POST['region_code'] ?? 'PL';
            $recruitmentType = $_POST['recruitment_type'] ?? 'local';
            $specName = TechnicalTeamService::getSpecDefinition($specCode)['name'] ?? $specCode;
            $capacitySummary = $this->svc->getRecruitmentCapacitySummary($specCode);
            $remainingSlots = (int)$capacitySummary['remaining_capacity'];

            if ($remainingSlots <= 0) {
                GameLog::warn('technical.php', 'request_recruitment limit reached', [
                    'spec_code' => $specCode,
                    'requested' => $requestedCount,
                    'summary'   => $capacitySummary,
                ]);
                return [
                    t('technical.rec_limit_reached', array_merge($capacitySummary, ['spec' => $specName])),
                    'error',
                ];
            }

            $allowedCount = min($requestedCount, $remainingSlots);
            $messages = [];
            $allOk = true;
            $successfulCount = 0;

            for ($index = 0; $index < $allowedCount; $index++) {
                $result = $this->svc->requestRecruitment($specCode, $regionCode, $recruitmentType);
                if (!$result['success']) {
                    $allOk = false;
                    $messages[] = $result['message'];
                    break;
                }
                $successfulCount++;
                $messages[] = $result['message'];
            }

            GameLog::info('technical.php', 'request_recruitment result', [
                'count'      => $successfulCount,
                'requested'  => $requestedCount,
                'ok'         => $allOk,
                'remaining'  => $remainingSlots,
                'spec_code'  => $specCode,
            ]);

            if ($requestedCount === 1 && $successfulCount === 1) {
                return [$messages[0], 'success'];
            }

            if ($successfulCount > 0 && $successfulCount < $requestedCount) {
                return [t('technical.recruitment_batch_partial', [
                    'count'     => $successfulCount,
                    'requested' => $requestedCount,
                    'spec'      => $specName,
                ]), 'success'];
            }

            if ($allOk) {
                return [t('technical.recruitment_batch_ok', ['count' => $successfulCount]), 'success'];
            }

            return [t('technical.recruitment_batch_err', [
                'count'  => $successfulCount,
                'detail' => implode('; ', $messages),
            ]), $successfulCount > 0 ? 'success' : 'error'];
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'request_recruitment EXCEPTION', $e);
            return [t('technical.err_recruitment'), 'error'];
        }
    }

    private function handleCancelRecruitment(): array
    {
        try {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $result = $this->svc->cancelRecruitment($requestId);
            GameLog::info('technical.php', 'cancel_recruitment result', [
                'request_id' => $requestId,
                'ok'         => $result['success'],
            ]);
            return [$result['message'], $result['success'] ? 'success' : 'error'];
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'cancel_recruitment EXCEPTION', $e);
            return [t('technical.err_cancel_recruitment'), 'error'];
        }
    }

    private function handleCancelTask(): array
    {
        try {
            $taskId = (int)($_POST['task_id'] ?? 0);
            $result = $this->svc->cancelTask($taskId);
            GameLog::info('technical.php', 'cancel_task', ['task_id' => $taskId, 'ok' => $result['success']]);
            return [$result['message'], $result['success'] ? 'success' : 'error'];
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'cancel_task EXCEPTION', $e);
            return [t('technical.err_cancel_task'), 'error'];
        }
    }

    private function handleCancelQueueItem(): array
    {
        try {
            $queueId = (int)($_POST['queue_id'] ?? 0);
            $result = $this->svc->cancelQueueItem($queueId);
            GameLog::info('technical.php', 'cancel_queue_item', ['queue_id' => $queueId, 'ok' => $result['success']]);
            return [$result['message'], $result['success'] ? 'success' : 'error'];
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'cancel_queue_item EXCEPTION', $e);
            return [t('technical.err_cancel_queue'), 'error'];
        }
    }

    private function handleUpgradeProcedures(): array
    {
        try {
            $result = $this->svc->upgradeProcedures();
            GameLog::info('technical.php', 'upgrade_procedures result', $result);
            return [$result['message'], $result['success'] ? 'success' : 'error'];
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'upgrade_procedures EXCEPTION', $e);
            return [t('technical.err_upgrade_proc'), 'error'];
        }
    }

    private function handleRepairProcedureIntegrity(): array
    {
        try {
            $result = $this->svc->repairProcedureIntegrity();
            GameLog::info('technical.php', 'repair_procedure_integrity result', $result);
            return [$result['message'], $result['success'] ? 'success' : 'error'];
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'repair_procedure_integrity EXCEPTION', $e);
            return [t('technical.err_repair_proc'), 'error'];
        }
    }

    private function handleDismissNotification(): void
    {
        try {
            $this->svc->markRead((int)($_POST['notif_id'] ?? 0));
            GameLog::info('technical.php', 'dismiss_notification OK', ['notif_id' => $_POST['notif_id'] ?? 0]);
        } catch (Throwable $e) {
            GameLog::error('technical.php', 'dismiss_notification EXCEPTION', $e);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    }
}
