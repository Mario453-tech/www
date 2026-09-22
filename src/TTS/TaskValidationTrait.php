<?php

trait TTSTaskValidationTrait
{
    /**
     * @param array<string,mixed> $staff
     * @return array{success:false,message:string}|null
     */
    private function validateTaskTarget(array $staff, string $taskType, ?int $wellId, ?int $hubId, ?int $pipelineId): ?array
    {
        $taskDef = self::getTaskDefinition($taskType);
        if (!$taskDef) return ['success' => false, 'message' => t('technical.task_msg.task_unknown')];

        if (!in_array($staff['spec_code'], $taskDef['assignable'])) {
            $allowed = implode(', ', array_map(fn($s) => (self::getSpecDefinition($s, $this->db)['name'] ?? $s), $taskDef['assignable']));
            return ['success' => false, 'message' => t('technical.task_msg.task_wrong_specialist', [
                'allowed' => $allowed,
                'spec' => $staff['spec_name'],
            ])];
        }

        if ($taskDef['needs_well'] && !$wellId) {
            return ['success' => false, 'message' => t('technical.task_msg.task_requires_well')];
        }

        if (($taskDef['needs_hub'] ?? false) && !$hubId) {
            return ['success' => false, 'message' => t('technical.task_msg.task_requires_hub')];
        }

        if (($taskDef['needs_pipeline'] ?? false)) {
            if (!$pipelineId) {
                return ['success' => false, 'message' => t('technical.task_msg.task_requires_pipeline')];
            }
            $pipeStmt = $this->db->prepare("SELECT status FROM well_pipelines WHERE id = ? AND player_id = ? LIMIT 1" . $this->taskLockSuffix());
            $pipeStmt->execute([$pipelineId, $this->playerId]);
            $pipeRow = $pipeStmt->fetch();
            if (!$pipeRow) {
                return ['success' => false, 'message' => t('technical.task_msg.pipeline_not_owned')];
            }
            if ($pipeRow['status'] === 'building') {
                return ['success' => false, 'message' => t('technical.task_msg.pipeline_unavailable', ['status' => $pipeRow['status']])];
            }
        }

        if ($wellId && $taskDef['needs_well']) {
            $wellStmt = $this->db->prepare("SELECT status, paused_staff_reason FROM wells WHERE id = ? AND player_id = ? LIMIT 1" . $this->taskLockSuffix());
            $wellStmt->execute([$wellId, $this->playerId]);
            $wellRow = $wellStmt->fetch();
            if (!$wellRow) {
                return ['success' => false, 'message' => t('technical.task_msg.well_not_owned')];
            }
            if ($wellRow['status'] === 'paused_staff') {
                $missing = $wellRow['paused_staff_reason'] ?? 'brak personelu';
                return [
                    'success' => false,
                    'message' => t('technical.task_msg.well_paused_staff', ['missing' => $missing]),
                ];
            }
            // Reject unavailable wells; blowout control is the recovery exception.
            // Odrzuc niedostepne odwierty; opanowanie erupcji jest wyjatkiem naprawczym.
            $blocked = in_array($wellRow['status'], ['seized', 'sold', 'layer_switch', 'equipment_swap'], true)
                || ($wellRow['status'] === 'blowout' && $taskType !== 'blowout_control');
            if ($blocked) {
                return ['success' => false, 'message' => t('technical.task_msg.well_unavailable', ['status' => $wellRow['status']])];
            }
        }

        if ($hubId && ($taskDef['needs_hub'] ?? false)) {
            $hubStmt = $this->db->prepare("
                SELECT h.id
                FROM logistics_hubs h
                JOIN logistics_hub_assignments a ON a.hub_id = h.id AND a.status = 'active'
                JOIN wells w ON w.id = a.well_id
                WHERE h.id = ?
                  AND w.player_id = ?
                  AND w.status NOT IN ('sold','seized')
                  AND (h.player_id = ? OR h.tenant_player_id = ?)
                  AND h.status NOT IN ('building','planned','sold')
                LIMIT 1
            " . $this->taskLockSuffix());
            $hubStmt->execute([
                $hubId,
                $this->playerId,
                $this->playerId,
                $this->playerId,
            ]);
            if (!$hubStmt->fetch()) {
                return ['success' => false, 'message' => t('technical.task_msg.hub_not_used')];
            }
        }
        return null;
    }
}
