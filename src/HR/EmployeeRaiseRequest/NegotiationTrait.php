<?php
declare(strict_types=1);

trait EmployeeRaiseRequestNegotiationTrait
{
    /**
     * @param array<string,mixed> $employee
     * @param array<string,mixed> $state
     * @return array<string,float>
     */
    private function negotiationFormula(
        int $playerId,
        array $employee,
        array $state,
        float $currentSalary,
        float $requestedSalary,
        float $offeredSalary
    ): array {
        $range = max(0.01, $requestedSalary - $currentSalary);
        $offerQuality = max(0.0, min(100.0, (($offeredSalary - $currentSalary) / $range) * 100.0));
        $skills = is_array($employee['skills'] ?? null) ? $employee['skills'] : [];
        $traits = is_array($employee['traits'] ?? null) ? $employee['traits'] : [];
        $employeeNegotiation = (float)($skills['negotiation'] ?? $skills['role_skill'] ?? 5);
        $loyalty = min(
            10.0,
            (float)($traits['loyalty'] ?? 5) + max(0.0, (float)($state['loyalty_modifier'] ?? 0.0))
        );
        $ambition = (float)($traits['ambition'] ?? 5);
        $morale = max(0.0, min(100.0, (float)$state['morale']));
        $hrEffectiveness = $this->hrEffectiveness($playerId);
        $hasSalaryNegotiator = $this->hasSalaryNegotiator($playerId);
        $negotiatorBonus = $hasSalaryNegotiator
            ? $this->config->getFloat('raise_salary_negotiator_chance_bonus')
            : 0.0;
        $chance = 10.0
            + $offerQuality * 0.65
            + $hrEffectiveness * 0.15
            + $morale * 0.10
            + $loyalty
            - $ambition * 1.2
            - $employeeNegotiation
            + $negotiatorBonus;
        $roll = max(0.01, min(100.0, (float)($this->randomRoll)()));

        return [
            'current_salary' => $currentSalary,
            'requested_salary' => $requestedSalary,
            'offered_salary' => $offeredSalary,
            'offer_quality' => round($offerQuality, 4),
            'employee_negotiation' => $employeeNegotiation,
            'employee_loyalty' => $loyalty,
            'employee_ambition' => $ambition,
            'employee_morale' => $morale,
            'hr_effectiveness' => round($hrEffectiveness, 4),
            'salary_negotiator_active' => $hasSalaryNegotiator ? 1.0 : 0.0,
            'salary_negotiator_bonus' => $negotiatorBonus,
            'chance' => round(max(5.0, min(95.0, $chance)), 4),
            'random_roll' => round($roll, 4),
        ];
    }

    private function hrEffectiveness(int $playerId): float
    {
        $stmt = $this->db->prepare(
            "SELECT AVG(
                    ((COALESCE(bm.skill_negotiation,5) + COALESCE(bm.skill_organization,5)) * 5)
                    * (0.5 + COALESCE(es.morale,65) / 200)
                )
               FROM board_members bm
               JOIN board_roles br ON br.id=bm.role_id AND br.code='hr'
          LEFT JOIN employee_state es
                 ON es.player_id=bm.player_id
                AND es.source_type='board_member'
                AND es.source_id=bm.id
              WHERE bm.player_id=? AND bm.status='active'"
        );
        $stmt->execute([$playerId]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null
            ? max(0.0, min(100.0, (float)$value))
            : 0.0;
    }

    private function hasSalaryNegotiator(int $playerId): bool
    {
        $stmt = $this->db->prepare(
            "SELECT 1
               FROM board_members bm
               JOIN hr_specializations hs ON hs.id=bm.specialization_id
              WHERE bm.player_id=? AND bm.status='active' AND hs.code='salary_negotiator'
              LIMIT 1"
        );
        $stmt->execute([$playerId]);
        return $stmt->fetchColumn() !== false;
    }
}
