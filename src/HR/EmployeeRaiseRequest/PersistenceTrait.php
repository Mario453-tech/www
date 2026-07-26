<?php
declare(strict_types=1);

trait EmployeeRaiseRequestPersistenceTrait
{
    private function updateSalary(EmployeeRef $ref, float $salary): void
    {
        $table = $ref->sourceType === EmployeeRef::SOURCE_TECHNICAL_STAFF
            ? 'technical_staff'
            : 'board_members';
        $statusSql = $ref->sourceType === EmployeeRef::SOURCE_TECHNICAL_STAFF
            ? "status IN ('active','busy')"
            : "status='active'";
        $stmt = $this->db->prepare(
            "UPDATE {$table} SET salary=?
              WHERE id=? AND player_id=? AND {$statusSql}"
        );
        $stmt->execute([$salary, $ref->sourceId, $ref->playerId]);
        if ($stmt->rowCount() !== 1) {
            $check = $this->db->prepare(
                "SELECT salary FROM {$table} WHERE id=? AND player_id=? AND {$statusSql}"
            );
            $check->execute([$ref->sourceId, $ref->playerId]);
            $current = $check->fetchColumn();
            if ($current === false || abs((float)$current - $salary) > 0.009) {
                throw new RuntimeException('Employee salary update did not affect exactly one row.');
            }
        }
    }

    private function updateLoyaltyModifier(EmployeeRef $ref, float $gain): void
    {
        $stmt = $this->db->prepare(
            'UPDATE employee_state
                SET loyalty_modifier=CASE
                        WHEN loyalty_modifier < :gain_compare THEN :gain_value
                        ELSE loyalty_modifier
                    END,
                    updated_at=CURRENT_TIMESTAMP
              WHERE player_id=:player_id AND source_type=:source_type AND source_id=:source_id'
        );
        $stmt->execute([
            'gain_compare' => min(10.0, max(0.0, $gain)),
            'gain_value' => min(10.0, max(0.0, $gain)),
            'player_id' => $ref->playerId,
            'source_type' => $ref->sourceType,
            'source_id' => $ref->sourceId,
        ]);
    }

    private function updateState(
        EmployeeRef $ref,
        float $moraleDelta,
        string $relation,
        float $supportDelta,
        float $leaveRiskDelta = 0.0
    ): float
    {
        $stmt = $this->db->prepare(
            'UPDATE employee_state
                SET morale=CASE
                        WHEN morale+:morale_delta < 0 THEN 0
                        WHEN morale+:morale_delta > 100 THEN 100
                        ELSE morale+:morale_delta END,
                    strike_support=CASE
                        WHEN strike_support+:support_delta < 0 THEN 0
                        WHEN strike_support+:support_delta > 100 THEN 100
                        ELSE strike_support+:support_delta END,
                    leave_risk=CASE
                        WHEN leave_risk+:leave_risk_delta < 0 THEN 0
                        WHEN leave_risk+:leave_risk_delta > 100 THEN 100
                        ELSE leave_risk+:leave_risk_delta END,
                    relation_status=:relation,
                    salary_satisfaction=CASE
                        WHEN expected_salary > 0 THEN
                            CASE WHEN (:salary / expected_salary) * 100 > 120 THEN 120
                                 ELSE (:salary / expected_salary) * 100 END
                        ELSE salary_satisfaction END,
                    last_raise_at=CASE WHEN :is_raise=1 THEN CURRENT_TIMESTAMP ELSE last_raise_at END,
                    version=version+1,
                    updated_at=CURRENT_TIMESTAMP
              WHERE player_id=:player_id AND source_type=:source_type AND source_id=:source_id'
        );
        $salary = $this->currentSalary($ref);
        $stmt->execute([
            'morale_delta' => $moraleDelta,
            'support_delta' => $supportDelta,
            'leave_risk_delta' => $leaveRiskDelta,
            'relation' => $relation,
            'salary' => $salary,
            'is_raise' => $relation === 'normal' ? 1 : 0,
            'player_id' => $ref->playerId,
            'source_type' => $ref->sourceType,
            'source_id' => $ref->sourceId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Employee state update did not affect exactly one row.');
        }

        $check = $this->db->prepare(
            'SELECT morale FROM employee_state WHERE player_id=? AND source_type=? AND source_id=?'
        );
        $check->execute([$ref->playerId, $ref->sourceType, $ref->sourceId]);
        return (float)$check->fetchColumn();
    }

    private function resolveRequest(
        int $requestId,
        int $playerId,
        string $status,
        ?string $deadline,
        bool $resolved,
        bool $incrementPostponed = false,
        ?float $negotiatedSalary = null
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE employee_raise_requests
                SET status=?, deadline_at=?, resolved_at=CASE WHEN ?=1 THEN CURRENT_TIMESTAMP ELSE NULL END,
                    postponed_count=postponed_count + ?,
                    negotiated_salary=?,
                    updated_at=CURRENT_TIMESTAMP
              WHERE id=? AND player_id=? AND status IN (\'open\',\'postponed\')'
        );
        $stmt->execute([$status, $deadline, $resolved ? 1 : 0, $incrementPostponed ? 1 : 0, $negotiatedSalary, $requestId, $playerId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Raise request update did not affect exactly one row.');
        }
    }
}
