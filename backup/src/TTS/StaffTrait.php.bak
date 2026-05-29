<?php
/**
 * TTS/StaffTrait.php
 * Technical staff - fetching, bonus calculation, hiring, firing.
 * Zespol techniczny - pobieranie, bonusy, zatrudnianie, zwalnianie.
 */
trait TTSStaffTrait
{
    // Staff section / Sekcja pracownikow

    public function getStaff(): array
    {
        $stmt = $this->db->prepare("
            SELECT ts.*,
                   ss.name  AS specialization_name,
                   ss.rarity AS spec_rarity,
                   ss.prod_bonus, ss.wear_reduction, ss.incident_reduction,
                   ss.spiral_reduction, ss.catastrophe_reduction,
                   tt.id AS active_task_id,
                   tt.task_type AS active_task_type,
                   tt.title AS active_task_title,
                   tt.end_time AS active_task_end,
                   tt.status AS active_task_status,
                   (SELECT COUNT(*) FROM technical_task_queue q WHERE q.staff_id = ts.id) AS queued_tasks
            FROM technical_staff ts
            LEFT JOIN staff_specializations ss ON ss.code = ts.specialization
            LEFT JOIN technical_tasks tt ON tt.staff_id = ts.id AND tt.status = 'in_progress'
            WHERE ts.player_id = ? AND ts.status != 'fired'
            ORDER BY FIELD(ts.spec_code,
                'drilling_engineer','reservoir_engineer','production_engineer',
                'maintenance_engineer','pipeline_engineer','safety_engineer')
        ");
        $stmt->execute([$this->playerId]);
        return $stmt->fetchAll();
    }

    public function getStaffMember(int $staffId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM technical_staff WHERE id = ? AND player_id = ?
        ");
        $stmt->execute([$staffId, $this->playerId]);
        return $stmt->fetch() ?: null;
    }

    // Staff task bonus / Bonus pracownika do zadania
    public function getStaffBonus(array $staff): array
    {
        $skill = (int)$staff['skill_level'];
        $timeMult = $skill >= 7
            ? max(0.5, 1.0 - (($skill - 5) * 0.025))
            : ($skill <= 4 ? 1.0 + ((5 - $skill) * 0.025) : 1.0);
        $errorRisk = $skill <= 3 ? ($skill * 2) : 0;

        return [
            'skill'      => $skill,
            'time_mult'  => $timeMult,
            'cost_mult'  => max(0.7, 1.0 - ($skill * 0.015)),
            'error_risk' => $errorRisk,
            'label'      => t('technical.staff_msg.skill_bonus', [
                'skill' => $skill,
                'speed' => round((1 - $timeMult) * 100),
            ]),
        ];
    }

    // Hire engineer / Zatrudnij inzyniera
    public function hireEngineer(
        string $specCode,
        string $firstName,
        string $lastName,
        int $skillLevel,
        int $salary,
        int $managerId
    ): array {
        $spec = self::getSpecDefinition($specCode);
        if (!$spec) {
            return ['success' => false, 'message' => t('technical.staff_msg.unknown_spec')];
        }

        $cashStmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
        $cashStmt->execute([$this->playerId]);
        if ($cashStmt->fetchColumn() < $salary) {
            return ['success' => false, 'message' => t('technical.staff_msg.no_funds', [
                'salary' => number_format($salary, 0, '.', ' '),
            ])];
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE players SET cash = cash - ? WHERE id = ?")->execute([$salary, $this->playerId]);
            $this->db->prepare("
                INSERT INTO technical_staff
                    (player_id, manager_id, first_name, last_name, spec_code, spec_name, skill_level, salary)
                VALUES (?,?,?,?,?,?,?,?)
            ")->execute([
                $this->playerId,
                $managerId,
                $firstName,
                $lastName,
                $specCode,
                $spec['name'],
                max(1, min(10, $skillLevel)),
                $salary,
            ]);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            GameLog::error('TTS', 'hireEngineer FAILED', $e);
            return ['success' => false, 'message' => t('technical.staff_msg.hire_failed', [
                'error' => $e->getMessage(),
            ])];
        }

        return ['success' => true, 'message' => t('technical.staff_msg.hired', [
            'spec' => $spec['name'],
            'first' => $firstName,
            'last' => $lastName,
            'salary' => number_format($salary, 0, '.', ' '),
        ])];
    }

    // Fire engineer / Zwolnij inzyniera
    public function fireEngineer(int $staffId): array
    {
        $staff = $this->getStaffMember($staffId);
        if (!$staff) {
            return ['success' => false, 'message' => t('technical.staff_msg.staff_missing')];
        }

        $taskStmt = $this->db->prepare("SELECT id FROM technical_tasks WHERE staff_id = ? AND status = 'in_progress' LIMIT 1");
        $taskStmt->execute([$staffId]);
        if ($taskStmt->fetch()) {
            return ['success' => false, 'message' => t('technical.staff_msg.staff_busy')];
        }

        $this->db->prepare("UPDATE technical_staff SET status = 'fired', fired_at = NOW() WHERE id = ?")->execute([$staffId]);

        return ['success' => true, 'message' => t('technical.staff_msg.fired', [
            'first' => $staff['first_name'],
            'last' => $staff['last_name'],
        ])];
    }
}
