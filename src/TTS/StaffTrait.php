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
        // Replace correlated subquery with LEFT JOIN on derived table to avoid N+1 per row.
        // Zastap skorelowane podzapytanie LEFT JOIN na tabeli pochodnej, by uniknac N+1 na wiersz.
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
                   COALESCE(ttq.cnt, 0) AS queued_tasks
            FROM technical_staff ts
            LEFT JOIN staff_specializations ss ON ss.code = ts.specialization
            LEFT JOIN technical_tasks tt ON tt.staff_id = ts.id AND tt.status = 'in_progress'
            LEFT JOIN (
                SELECT staff_id, COUNT(*) AS cnt
                FROM technical_task_queue
                GROUP BY staff_id
            ) ttq ON ttq.staff_id = ts.id
            WHERE ts.player_id = ? AND ts.status != 'fired'
            ORDER BY FIELD(ts.spec_code,
                'well_operator','roughneck','field_supervisor',
                'drilling_engineer','petroleum_engineer','reservoir_engineer','production_engineer','geologist',
                'well_technician','maintenance_engineer',
                'pipeline_engineer',
                'safety_officer','safety_engineer')
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
        // skill>=6 gets speed bonus (~2.5% each step above 5); skill<=4 gets penalty.
        // skill>=6 dostaje bonus predkosci (~2.5% na kazdy krok powyzej 5); skill<=4 ma kare.
        $timeMult = $skill >= 6
            ? max(0.5, 1.0 - (($skill - 5) * 0.025))
            : ($skill <= 4 ? 1.0 + ((5 - $skill) * 0.025) : 1.0);
        // Monotonically decreasing error risk: skill=1->8, 2->6, 3->4, 4->2, 5+->0.
        // Monotonicznie malejace ryzyko bledu: skill=1->8, 2->6, 3->4, 4->2, 5+->0.
        $errorRisk = $skill <= 4 ? max(0, (5 - $skill) * 2) : 0;

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

        // Wzorzec $ownTx — metoda moze byc wywolana samodzielnie lub wewnatrz transakcji wywolujacego (Rule 5).
        // $ownTx pattern — method may be called standalone or inside a caller's transaction (Rule 5).
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) $this->db->beginTransaction();
        try {
            // Atomic cash check + deduct to avoid TOCTOU race.
            // Atomowe sprawdzenie + odliczenie srodkow, by uniknac wyscigu TOCTOU.
            $cashUpd = $this->db->prepare("UPDATE players SET cash = cash - ? WHERE id = ? AND cash >= ?");
            $cashUpd->execute([$salary, $this->playerId, $salary]);
            if ($cashUpd->rowCount() === 0) {
                if ($ownTx) $this->db->rollBack();
                return ['success' => false, 'message' => t('technical.staff_msg.no_funds', ['amount' => $salary])];
            }
            try {
                if (class_exists('FinancialTransactionService', false)) {
                    (new FinancialTransactionService($this->db))->logTransaction(
                        $this->playerId, null, $salary,
                        FinancialTransactionService::TYPE_HR_FEE,
                        'Zatrudnienie pracownika TTS (pierwsza pensja)'
                    );
                }
            } catch (Throwable $le) { /* audit trail failure must not break the operation */ }
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
            if ($ownTx) $this->db->commit();
        } catch (Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) $this->db->rollBack();
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

        // Transakcja chroni przed race condition: sprawdzenie zajecia i zwolnienie sa atomowe.
        // Transaction prevents race condition: busy-check and firing are atomic.
        $this->db->beginTransaction();
        try {
            // Blokuj rekord pracownika, by wykluczyc wspolbiezne operacje na tym samym staffId.
            // Lock the staff row to exclude concurrent operations on the same staffId.
            $this->db->prepare("SELECT id FROM technical_staff WHERE id = ? AND player_id = ? LIMIT 1 FOR UPDATE")
                ->execute([$staffId, $this->playerId]);

            // Guard by player_id to prevent cross-player firing + busy-check before firing.
            // Sprawdzenie player_id (ochrona przed zwolnieniem pracownika innego gracza) + blokada przy zadaniu w toku.
            // Filtruj po player_id — zapobiega blokowaniu przez zadanie innego gracza (Rule 1).
            // Filter by player_id — prevents blocking by another player's task (Rule 1).
            $taskStmt = $this->db->prepare("SELECT id FROM technical_tasks WHERE staff_id = ? AND player_id = ? AND status = 'in_progress' LIMIT 1");
            $taskStmt->execute([$staffId, $this->playerId]);
            if ($taskStmt->fetch()) {
                $this->db->rollBack();
                return ['success' => false, 'message' => t('technical.staff_msg.staff_busy')];
            }

            $this->db->prepare("UPDATE technical_staff SET status = 'fired', fired_at = NOW() WHERE id = ? AND player_id = ?")->execute([$staffId, $this->playerId]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('TTS', 'fireEngineer FAILED', $e, ['staff_id' => $staffId]);
            return ['success' => false, 'message' => t('technical.staff_msg.staff_missing')];
        }

        return ['success' => true, 'message' => t('technical.staff_msg.fired', [
            'first' => $staff['first_name'],
            'last' => $staff['last_name'],
        ])];
    }
}
