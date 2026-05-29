<?php

/**
 * HiringTrait - hiring candidates into board_members and technical_staff.
 * PL: HiringTrait - zatrudnianie kandydatow do board_members i technical_staff.
 */
trait HRHiringTrait
{
    // Public API for candidate hiring.
    // PL: Publiczne API dla zatrudniania kandydatow.

    /**
     * Hire a candidate and route them to the correct target structure.
     * PL: Zatrudnia kandydata i kieruje go do poprawnej struktury docelowej.
     *
     * @return array<string, mixed>
     */
    public function hireCandidate(int $candidateId, int $playerId, string $contractType = '1y'): array
    {
        GameLog::info('HRService', 'hireCandidate start', [
            'candidate_id'  => $candidateId,
            'player_id'     => $playerId,
            'contract_type' => $contractType,
        ]);

        try {
            $candidate = $this->getCandidateForHire($candidateId, $playerId);
            if (!$candidate) {
                return ['success' => false, 'message' => t('hr_hiring.err_candidate_unavailable')];
            }

            // Split flow: technical engineer versus department director.
            // PL: Rozdzielenie sciezek: inzynier techniczny versus dyrektor dzialu.
            $isTechEngineer = false;
            if (($candidate['role_code'] ?? '') === 'technical' && !empty($candidate['specialization_id'])) {
                $spStmt = $this->db->prepare("SELECT department FROM hr_specializations WHERE id = ? LIMIT 1");
                $spStmt->execute([(int)$candidate['specialization_id']]);
                $spDept = $spStmt->fetchColumn();

                if ($spDept === 'technical') {
                    $mgrExists = $this->isRoleOccupied((int)$candidate['role_id'], $playerId);
                    if ($mgrExists) {
                        $isTechEngineer = true;
                    }
                }
            }

            $isStaffCandidate = !empty($candidate['specialization_id']);

            GameLog::step('HRService', 'hireCandidate', 1, 'routing', [
                'is_tech_engineer' => $isTechEngineer,
                'role_code'        => $candidate['role_code'] ?? null,
                'spec_id'          => $candidate['specialization_id'] ?? null,
            ]);

            if ($isTechEngineer) {
                return $this->hireTechEngineerToStaff($candidate, $playerId);
            }

            if (!$isStaffCandidate && $this->isRoleOccupied((int)$candidate['role_id'], $playerId)) {
                return ['success' => false, 'message' => t('hr_hiring.err_role_already_filled')];
            }

            $this->db->beginTransaction();
            $result = $this->hireCandidateDefaultPath($candidate, $playerId, $contractType);
            $this->finalizeCandidateHiring(
                (int)$candidate['id'],
                (int)$candidate['role_id'],
                (int)($candidate['request_id'] ?? 0),
                $playerId,
                $isStaffCandidate
            );
            $this->db->commit();

            GameLog::info('HRService', 'hireCandidate success (board_member)', [
                'candidate_id' => $candidateId,
                'member_id'    => $result['member_id'],
            ]);

            return $result;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('HRService', 'hireCandidate failed', $e, [
                'candidate_id' => $candidateId,
                'player_id'    => $playerId,
            ]);
            return ['success' => false, 'message' => t('hr_hiring.err_hire_failed')];
        }
    }

    // Internal helpers for hire flow.
    // PL: Wewnetrzne helpery dla procesu zatrudnienia.

    /**
     * Hire a technical engineer directly into technical_staff.
     * PL: Zatrudnia inzyniera technicznego bezposrednio do technical_staff.
     *
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private function hireTechEngineerToStaff(array $candidate, int $playerId): array
    {
        $mgrStmt = $this->db->prepare("
            SELECT bm.id FROM board_members bm
            JOIN board_roles br ON bm.role_id = br.id
            WHERE br.code = 'technical' AND bm.status = 'active' AND bm.player_id = ? AND bm.member_type = 'director'
            LIMIT 1
        ");
        $mgrStmt->execute([$playerId]);
        $managerId = (int)($mgrStmt->fetchColumn() ?: 0);

        $specStmt = $this->db->prepare("SELECT code, name FROM hr_specializations WHERE id = ? LIMIT 1");
        $specStmt->execute([(int)$candidate['specialization_id']]);
        $spec = $specStmt->fetch();

        if (!$spec) {
            return ['success' => false, 'message' => t('hr_hiring.err_unknown_specialization')];
        }

        $skillLevel = max(1, min(10, (int)round((
            (int)$candidate['skill_analysis'] +
            (int)$candidate['skill_organization'] +
            (int)$candidate['skill_stress']
        ) / 3)));

        $salary = (int)$candidate['expected_salary'];
        $cashStmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
        $cashStmt->execute([$playerId]);
        $cash = (float)$cashStmt->fetchColumn();
        if ($cash < $salary) {
            return [
                'success' => false,
                'message' => t('hr_hiring.err_insufficient_funds', [
                    'required' => '$' . number_format($salary, 0, '.', ' '),
                    'available' => '$' . number_format($cash, 0, '.', ' '),
                ]),
            ];
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE players SET cash = cash - ? WHERE id = ?")->execute([$salary, $playerId]);

            $staffPerk = $this->rollStaffSpecialization($spec['code'], $skillLevel);

            $this->db->prepare("
                INSERT INTO technical_staff
                    (player_id, manager_id, first_name, last_name, spec_code, spec_name, skill_level, salary, specialization)
                VALUES (?,?,?,?,?,?,?,?,?)
            ")->execute([
                $playerId,
                $managerId,
                $candidate['first_name'],
                $candidate['last_name'],
                $spec['code'],
                $spec['name'],
                $skillLevel,
                $salary,
                $staffPerk,
            ]);

            $this->finalizeCandidateHiring(
                (int)$candidate['id'],
                (int)$candidate['role_id'],
                (int)($candidate['request_id'] ?? 0),
                $playerId,
                true
            );

            $this->db->commit();

            GameLog::info('HRService', 'hireTechEngineer success', [
                'player_id' => $playerId,
                'spec'      => $spec['code'],
                'salary'    => $salary,
            ]);

            return [
                'success'   => true,
                'member_id' => 0,
                'message'   => t('hr_hiring.msg_hired_engineer', [
                    'first' => $candidate['first_name'],
                    'last' => $candidate['last_name'],
                    'spec' => $spec['name'],
                    'cost' => '$' . number_format($salary, 0, '.', ' '),
                ]),
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('HRService', 'hireTechEngineer FAILED', $e, ['player_id' => $playerId]);
            return ['success' => false, 'message' => t('hr_hiring.err_hire_engineer_failed')];
        }
    }

    /**
     * Roll a staff specialization perk for a newly hired technical staff member.
     * PL: Losuje perk specjalizacji dla nowo zatrudnionego pracownika technicznego.
     */
    private function rollStaffSpecialization(string $specCode, int $skillLevel): ?string
    {
        $operatorSpecs   = ['drilling_engineer', 'petroleum_engineer', 'reservoir_engineer', 'rig_manager', 'production_engineer'];
        $technicianSpecs = ['maintenance_engineer', 'safety_engineer', 'pipeline_engineer', 'safety_officer'];

        if (in_array($specCode, $operatorSpecs, true)) {
            $role = 'operator';
        } elseif (in_array($specCode, $technicianSpecs, true)) {
            $role = 'technician';
        } else {
            return null;
        }

        $baseChance = 0.05 + max(0, $skillLevel - 5) * 0.01;
        if ((mt_rand(1, 1000) / 1000.0) > $baseChance) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("SELECT code, rarity FROM staff_specializations WHERE role = ?");
            $stmt->execute([$role]);
            $perks = $stmt->fetchAll();
            if (empty($perks)) {
                return null;
            }

            $weights = ['common' => 60, 'uncommon' => 30, 'rare' => 10];
            $pool = [];
            foreach ($perks as $perk) {
                $w = $weights[$perk['rarity']] ?? 20;
                for ($i = 0; $i < $w; $i++) {
                    $pool[] = $perk['code'];
                }
            }

            return $pool[array_rand($pool)];
        } catch (Throwable $e) {
            GameLog::error('HRService', 'rollStaffSpecialization FAILED', $e);
            return null;
        }
    }

    /**
     * Load a candidate that can still be hired.
     * PL: Laduje kandydata, ktorego nadal mozna zatrudnic.
     *
     * @return array<string, mixed>|null
     */
    private function getCandidateForHire(int $candidateId, int $playerId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, br.code AS role_code, br.name AS role_name
            FROM candidates c
            JOIN board_roles br ON br.id = c.role_id
            WHERE c.id = ?
              AND c.expires_at > NOW()
              AND (
                   c.player_id = ?
                   OR (c.player_id IS NULL AND c.request_id IN (
                       SELECT id FROM recruitment_requests WHERE player_id = ?
                   ))
              )
            LIMIT 1
        ");
        $stmt->execute([$candidateId, $playerId, $playerId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Check whether the director role for a department is already filled.
     * PL: Sprawdza, czy rola dyrektora dla dzialu jest juz obsadzona.
     */
    private function isRoleOccupied(int $roleId, int $playerId = 0): bool
    {
        if ($playerId <= 0) {
            $playerId = (int)($_SESSION['user_id'] ?? 0);
        }

        $occupied = $this->db->prepare("
            SELECT id FROM board_members
            WHERE role_id = ? AND status = 'active' AND player_id = ? AND member_type = 'director'
            LIMIT 1
        ");
        $occupied->execute([$roleId, $playerId]);
        return (bool)$occupied->fetch();
    }

    /**
     * Default hire flow for board members.
     * PL: Domyslny proces zatrudnienia dla board_members.
     *
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    private function hireCandidateDefaultPath(array $candidate, int $playerId, string $contractType): array
    {
        GameLog::step('HRService', 'hireCandidate', 1, 'default path', [
            'candidate_id' => $candidate['id'] ?? null,
            'role_code'    => $candidate['role_code'] ?? null,
        ]);

        $memberId = $this->insertBoardMember($candidate, $playerId);
        $this->createEmployeeContract($memberId, (float)$candidate['expected_salary'], $contractType);
        $this->createEmploymentHistory($memberId, t('hr_hiring.history_director_hire'));

        $roleName = $candidate['role_name'] ?? $this->getRoleName((int)$candidate['role_id']);
        return [
            'success'   => true,
            'member_id' => $memberId,
            'message'   => t('hr_hiring.msg_hired_member', [
                'first' => $candidate['first_name'],
                'last' => $candidate['last_name'],
                'role' => $roleName,
            ]),
        ];
    }

    /**
     * Insert a board member record.
     * PL: Wstawia rekord board member.
     *
     * @param array<string, mixed> $candidate
     */
    private function insertBoardMember(array $candidate, int $playerId = 0): int
    {
        if ($playerId <= 0) {
            $playerId = (int)($_SESSION['user_id'] ?? 0);
        }

        $ins = $this->db->prepare("
            INSERT INTO board_members (
                player_id, member_type,
                role_id, first_name, last_name, gender, birth_date, nationality,
                region_code, specialization_id, experience_years,
                skill_organization, skill_negotiation, skill_analysis,
                skill_stress, skill_ethics,
                trait_loyalty, trait_corruption_risk, trait_ambition,
                salary, hired_at, status
            ) VALUES (
                :player_id, 'director',
                :role_id, :first_name, :last_name, :gender, :birth_date, :nationality,
                :region_code, :specialization_id, :experience_years,
                :skill_organization, :skill_negotiation, :skill_analysis,
                :skill_stress, :skill_ethics,
                :trait_loyalty, :trait_corruption_risk, :trait_ambition,
                :salary, NOW(), 'active'
            )
        ");
        $ins->execute([
            ':player_id'             => $playerId,
            ':role_id'               => $candidate['role_id'],
            ':first_name'            => $candidate['first_name'],
            ':last_name'             => $candidate['last_name'],
            ':gender'                => $candidate['gender'] ?? 'M',
            ':birth_date'            => $candidate['birth_date'],
            ':nationality'           => $candidate['nationality'],
            ':region_code'           => $candidate['region_code'] ?? 'PL',
            ':specialization_id'     => $candidate['specialization_id'] ?? null,
            ':experience_years'      => $candidate['experience_years'],
            ':skill_organization'    => $candidate['skill_organization'],
            ':skill_negotiation'     => $candidate['skill_negotiation'],
            ':skill_analysis'        => $candidate['skill_analysis'],
            ':skill_stress'          => $candidate['skill_stress'],
            ':skill_ethics'          => $candidate['skill_ethics'],
            ':trait_loyalty'         => $candidate['trait_loyalty'],
            ':trait_corruption_risk' => $candidate['trait_corruption_risk'],
            ':trait_ambition'        => $candidate['trait_ambition'],
            ':salary'                => $candidate['expected_salary'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Create the employee contract for a newly hired board member.
     * PL: Tworzy kontrakt pracownika dla nowo zatrudnionego board membera.
     */
    private function createEmployeeContract(int $memberId, float $salary, string $contractType): void
    {
        $durations   = ['6m' => '+6 months', '1y' => '+1 year', '2y' => '+2 years'];
        $duration    = $durations[$contractType] ?? '+1 year';
        $contractEnd = date('Y-m-d', strtotime($duration));
        $this->db->prepare("
            INSERT INTO employee_contracts
                (member_id, contract_start, contract_end, salary, contract_type, status)
            VALUES (?, CURDATE(), ?, ?, ?, 'active')
        ")->execute([$memberId, $contractEnd, $salary, $contractType]);
    }

    /**
     * Append a history entry for employment actions.
     * PL: Dodaje wpis historii dla akcji zatrudnienia.
     */
    private function createEmploymentHistory(int $memberId, string $reason): void
    {
        $this->db->prepare("
            INSERT INTO employment_history (member_id, action, reason)
            VALUES (?, 'hired', ?)
        ")->execute([$memberId, $reason]);
    }

    /**
     * Remove candidate leftovers and close recruitment when needed.
     * PL: Usuwa pozostalosci kandydata i zamyka rekrutacje, gdy potrzeba.
     */
    private function finalizeCandidateHiring(int $candidateId, int $roleId, int $requestId, int $playerId, bool $isTechStaff = false): void
    {
        if ($requestId > 0) {
            $this->db->prepare("
                UPDATE recruitment_requests
                SET status = 'completed'
                WHERE id = ? AND player_id = ?
            ")->execute([$requestId, $playerId]);

            $this->db->prepare("
                DELETE FROM candidates
                WHERE request_id = ?
                  AND (player_id = ? OR player_id IS NULL)
            ")->execute([$requestId, $playerId]);
            return;
        }

        $this->db->prepare("
            DELETE FROM candidates
            WHERE id = ?
              AND role_id = ?
              AND (player_id = ? OR player_id IS NULL)
        ")->execute([$candidateId, $roleId, $playerId]);
    }
}
