<?php

trait LogisticsPageActionsTrait
{
    /**
     * Handle staffing mutations through PRG.
     * Obsluguje zmiany obsady przez PRG.
     */
    public function handlePost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        $action = (string)($_POST['action'] ?? '');
        $pipelineAction = str_contains($action, 'pipeline');
        $anchor = $pipelineAction ? 'logistics-pipelines-heading' : 'logistics-hubs-heading';

        if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
            $_SESSION['logistics_staffing_flash'] = ['error' => t('common.csrf_error')];
            $this->redirectToLogistics($anchor);
        }

        if (!in_array($action, [
            'assign_hub_staff',
            'release_hub_staff',
            'assign_pipeline_staff',
            'release_pipeline_staff',
        ], true)) {
            return;
        }

        try {
            $_SESSION['logistics_staffing_flash'] = $this->executeStaffingAction($action);
        } catch (Throwable $e) {
            GameLog::error('logistics', 'Staffing action failed', $e, [
                'player_id' => $this->playerId,
                'action' => $action,
            ]);
            $_SESSION['logistics_staffing_flash'] = [
                'error' => $this->staffingErrorMessage($e, $pipelineAction),
            ];
        }

        $this->redirectToLogistics($anchor);
    }

    /**
     * Execute one allowlisted staffing action.
     * Wykonuje jedna dozwolona akcje obsady.
     *
     * @return array{success?:string,error?:string}
     */
    private function executeStaffingAction(string $action): array
    {
        if ($action === 'assign_hub_staff') {
            $result = $this->hubStaffingMgmt->assignToHub(
                $this->playerId,
                (string)($_POST['source_type'] ?? ''),
                (int)($_POST['source_id'] ?? 0),
                (int)($_POST['hub_id'] ?? 0),
                (float)($_POST['allocation_pct'] ?? 0)
            );

            return [
                'success' => $result['was_update']
                    ? t('logistics.hub.staffing.ok_update')
                    : t('logistics.hub.staffing.ok_assign'),
            ];
        }

        if ($action === 'release_hub_staff') {
            $released = $this->hubStaffingMgmt->releaseFromHub(
                $this->playerId,
                (int)($_POST['assignment_id'] ?? 0)
            );

            return $released
                ? ['success' => t('logistics.hub.staffing.ok_release')]
                : ['error' => t('logistics.hub.staffing.err_release')];
        }

        if ($action === 'assign_pipeline_staff') {
            $result = $this->pipelineStaffingMgmt->assignToPipeline(
                $this->playerId,
                (string)($_POST['source_type'] ?? ''),
                (int)($_POST['source_id'] ?? 0),
                (int)($_POST['pipeline_id'] ?? 0),
                (float)($_POST['allocation_pct'] ?? 0)
            );

            return [
                'success' => $result['was_update']
                    ? t('logistics.pipeline.staffing.ok_update')
                    : t('logistics.pipeline.staffing.ok_assign'),
            ];
        }

        $released = $this->pipelineStaffingMgmt->releaseFromPipeline(
            $this->playerId,
            (int)($_POST['assignment_id'] ?? 0)
        );

        return $released
            ? ['success' => t('logistics.pipeline.staffing.ok_release')]
            : ['error' => t('logistics.pipeline.staffing.err_release')];
    }

    /**
     * Translate domain failures without exposing technical messages.
     * Tlumaczy bledy domenowe bez ujawniania komunikatow technicznych.
     */
    private function staffingErrorMessage(Throwable $e, bool $pipelineAction): string
    {
        return match ($e->getMessage()) {
            'Employee assignment is busy.' => t('logistics.hub.staffing.err_busy'),
            'Employee does not belong to this player.' => t('logistics.hub.staffing.err_employee_owner'),
            'Employee is not active.' => t('logistics.hub.staffing.err_employee_inactive'),
            'Employee relation status blocks assignment.' => t('logistics.hub.staffing.err_relation_blocked'),
            'Hub does not belong to this player.' => t('logistics.hub.staffing.err_hub_owner'),
            'Hub is not available for staffing.' => t('logistics.hub.staffing.err_hub_unavailable'),
            'Pipeline does not belong to this player.' => t('logistics.pipeline.staffing.err_pipeline_owner'),
            'Pipeline is not available for staffing.',
            'Assignment target is not available for staffing.' => t('logistics.pipeline.staffing.err_pipeline_unavailable'),
            'Employee specialization is not allowed for pipeline staffing.' => t('logistics.pipeline.staffing.err_role'),
            'Employee assignment allocation exceeds 100%.' => t('logistics.hub.staffing.err_allocation'),
            default => $pipelineAction
                ? t('logistics.pipeline.staffing.err_generic')
                : t('logistics.hub.staffing.err_generic'),
        };
    }

    /**
     * Redirect to the relevant logistics section.
     * Przekierowuje do odpowiedniej sekcji logistyki.
     */
    private function redirectToLogistics(string $anchor): never
    {
        $location = function_exists('url') ? url('logistics') : '/logistics';
        header('Location: ' . $location . '#' . $anchor);
        exit;
    }
}
