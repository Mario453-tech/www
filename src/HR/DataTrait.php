<?php

require_once __DIR__ . '/EmployeeActionReceiptService.php';

/**
 * DataTrait gettery danych HR, zarzdzanie kandydatami i kontraktami.
 * Data trait HR data getters, candidate and contract management.
 */
trait HRDataTrait
{
 // GETTERY / Getters

 /** @return list<array<string, mixed>> */
    public function getRegions(): array
    {
        $stmt = $this->db->query("SELECT * FROM hr_regions ORDER BY name");
        return $stmt->fetchAll();
    }

 /** @return list<array<string, mixed>> */
    public function getSpecializations(): array
    {
        $stmt = $this->db->query("SELECT * FROM hr_specializations ORDER BY department, rarity DESC");
        return $stmt->fetchAll();
    }

 /** @return list<array<string, mixed>> */
    public function getCandidatesForRole(int $roleId): array
    {
        $playerId = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $this->db->prepare("
            SELECT c.*, hs.name as spec_name, hs.rarity,
                   hr.name as region_name
            FROM candidates c
            LEFT JOIN hr_specializations hs ON c.specialization_id = hs.id
            LEFT JOIN hr_regions hr ON c.region_code = hr.code
            WHERE c.role_id = ?
              AND c.expires_at > NOW()
              AND (
                   c.player_id = ?
                   OR (c.player_id IS NULL AND c.request_id IN (
                       SELECT id FROM recruitment_requests WHERE player_id = ?
                   ))
              )
            ORDER BY (c.skill_organization + c.skill_negotiation + c.skill_analysis +
                      c.skill_stress + c.skill_ethics) DESC
        ");
        $stmt->execute([$roleId, $playerId, $playerId]);
        return $stmt->fetchAll();
    }

 /** @return list<array<string, mixed>> */
    public function getActiveEmployees(int $playerId = 0): array
    {
        if ($playerId <= 0) $playerId = (int)($_SESSION['user_id'] ?? 0);
        if ($playerId <= 0) return [];

        $stmt = $this->db->prepare("
            SELECT bm.id, 'board_member' AS source,
                   bm.first_name, bm.last_name, bm.gender, bm.birth_date, bm.nationality,
                   bm.experience_years, bm.salary, bm.hired_at, bm.status,
                   bm.skill_organization, bm.skill_negotiation, bm.skill_analysis,
                   bm.skill_stress, bm.skill_ethics,
                   bm.trait_loyalty, bm.trait_corruption_risk, bm.trait_ambition,
                   bm.role_id, br.name as role_name, br.code as role_code,
                   hs.name as spec_name,
                   TIMESTAMPDIFF(YEAR, bm.birth_date, CURDATE()) as age,
                   DATEDIFF(CURDATE(), bm.hired_at) as days_employed,
                   ec.contract_end, ec.contract_type,
                   DATEDIFF(ec.contract_end, CURDATE()) as contract_days_left,
                   es.morale AS morale, CASE WHEN es.relation_status = 'on_strike' THEN 1 ELSE 0 END AS is_striking
            FROM board_members bm
            JOIN board_roles br ON bm.role_id = br.id
            LEFT JOIN hr_specializations hs ON bm.specialization_id = hs.id
            LEFT JOIN employee_contracts ec ON ec.member_id = bm.id AND ec.status = 'active' AND ec.contract_end >= CURDATE()
            LEFT JOIN employee_state es ON es.player_id = bm.player_id AND es.source_type = 'board_member' AND es.source_id = bm.id
            WHERE bm.status = 'active'
              AND bm.player_id = ?
              AND bm.member_type = 'staff'
            UNION ALL
            SELECT ts.id, 'technical_staff' AS source,
                   ts.first_name, ts.last_name, 'N' AS gender, NULL AS birth_date, '' AS nationality,
                   ts.experience_years, ts.salary, ts.hired_at, ts.status,
                   ts.skill_level AS skill_organization, ts.skill_level AS skill_negotiation,
                   ts.skill_level AS skill_analysis, ts.skill_level AS skill_stress,
                   ts.skill_level AS skill_ethics,
                   ts.trait_loyalty, ts.trait_corruption_risk, ts.trait_ambition,
                   br.id AS role_id, br.name AS role_name, br.code AS role_code,
                   ts.spec_name,
                   NULL AS age,
                   DATEDIFF(CURDATE(), ts.hired_at) AS days_employed,
                   NULL AS contract_end, NULL AS contract_type, NULL AS contract_days_left,
                   es.morale AS morale,
                   CASE WHEN es.relation_status = 'on_strike' THEN 1 ELSE 0 END AS is_striking
            FROM technical_staff ts
            JOIN board_roles br ON br.code = 'technical'
            LEFT JOIN employee_state es ON es.player_id = ts.player_id AND es.source_type = 'technical_staff' AND es.source_id = ts.id
            WHERE ts.status IN ('active','busy','on_leave')
              AND ts.player_id = ?
            ORDER BY hired_at ASC
        ");
        $stmt->execute([$playerId, $playerId]);
        return $stmt->fetchAll();
    }

 /** @return list<array<string, mixed>> */
    public function getActiveDirectors(int $playerId = 0): array
    {
        if ($playerId <= 0) $playerId = (int)($_SESSION['user_id'] ?? 0);
        if ($playerId <= 0) return [];

        $stmt = $this->db->prepare("
            SELECT bm.*, br.name as role_name, br.code as role_code,
                   TIMESTAMPDIFF(YEAR, bm.birth_date, CURDATE()) as age,
                   DATEDIFF(CURDATE(), bm.hired_at) as days_employed
            FROM board_members bm
            JOIN board_roles br ON bm.role_id = br.id
            WHERE bm.status = 'active'
              AND bm.player_id = ?
              AND bm.member_type = 'director'
            ORDER BY br.sort_order ASC, bm.hired_at ASC
        ");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }

    public function getActiveContracts(int $playerId = 0): array
    {
        if ($playerId <= 0) $playerId = (int)($_SESSION['user_id'] ?? 0);
        if ($playerId <= 0) return [];

        $stmt = $this->db->prepare("
            SELECT ec.*, bm.first_name, bm.last_name, br.name as role_name,
                   DATEDIFF(ec.contract_end, CURDATE()) as days_left
            FROM employee_contracts ec
            JOIN board_members bm ON ec.member_id = bm.id
            JOIN board_roles br ON bm.role_id = br.id
            WHERE ec.status = 'active'
              AND ec.contract_end >= CURDATE()
              AND bm.player_id = ?
              AND bm.member_type = 'staff'
            ORDER BY ec.contract_end ASC
        ");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }

    public function getActiveRecruitments(int $playerId = 0): array
    {
        if ($playerId <= 0) $playerId = (int)($_SESSION['user_id'] ?? 0);

        $stmt = $this->db->prepare("
            SELECT rr.*, br.name as role_name,
                   hs.name as spec_name,
                   TIMESTAMPDIFF(SECOND, NOW(), rr.ready_at) as seconds_remaining
            FROM recruitment_requests rr
            JOIN board_roles br ON rr.role_id = br.id
            LEFT JOIN hr_specializations hs ON hs.code = rr.spec_code
            WHERE rr.player_id = ?
              AND (
                   rr.status = 'pending'
                   OR (
                       rr.status = 'ready'
                       AND EXISTS (
                           SELECT 1
                           FROM candidates c
                           WHERE c.request_id = rr.id
                             AND c.expires_at > NOW()
                       )
                   )
              )
            ORDER BY rr.ready_at ASC
        ");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }

    public function getAllCandidates(int $playerId = 0): array
    {
        if ($playerId <= 0) $playerId = (int)($_SESSION['user_id'] ?? 0);
        if ($playerId <= 0) return [];

        $stmt = $this->db->prepare("
            SELECT c.*,
                   br.name  AS role_name,
                   hs.name  AS spec_name,
                   hs.rarity,
                   hr.name  AS region_name,
                   TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE())   AS age,
                   TIMESTAMPDIFF(HOUR, NOW(), c.expires_at)       AS hours_remaining
            FROM candidates c
            JOIN board_roles br             ON c.role_id        = br.id
            LEFT JOIN hr_specializations hs ON c.specialization_id = hs.id
            LEFT JOIN hr_regions hr         ON c.region_code    = hr.code
            WHERE c.expires_at > NOW()
              AND (
                   c.player_id = :pid
                   OR (c.player_id IS NULL AND c.request_id IN (
                       SELECT id FROM recruitment_requests WHERE player_id = :pid2
                   ))
              )
            ORDER BY (c.skill_organization + c.skill_negotiation +
                      c.skill_analysis     + c.skill_stress      + c.skill_ethics) DESC
        ");
        $stmt->execute([':pid' => $playerId, ':pid2' => $playerId]);
        return $stmt->fetchAll();
    }

    public function getHrCandidates(int $playerId = 0): array
    {
        if ($playerId <= 0) $playerId = (int)($_SESSION['user_id'] ?? 0);
        if ($playerId <= 0) return [];

        $stmt = $this->db->prepare("
            SELECT c.*,
                   br.name  AS role_name,
                   br.code  AS role_code,
                   hs.name  AS spec_name,
                   hs.rarity,
                   hr.name  AS region_name,
                   rr.initiated_by,
                   rr.spec_code,
                   TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE()) AS age,
                   TIMESTAMPDIFF(HOUR, NOW(), c.expires_at)     AS hours_remaining,
                   cr.score            AS technical_score,
                   cr.recommendation   AS tech_recommendation,
                   cr.comment          AS tech_comment,
                   cr.created_at       AS review_date
            FROM candidates c
            JOIN board_roles br             ON c.role_id = br.id
            LEFT JOIN hr_specializations hs ON c.specialization_id = hs.id
            LEFT JOIN hr_regions hr         ON c.region_code = hr.code
            LEFT JOIN recruitment_requests rr ON rr.id = c.request_id
            LEFT JOIN candidate_reviews cr  ON cr.candidate_id = c.id
                                           AND cr.player_id = :pid3
            WHERE c.expires_at > NOW()
              AND (
                   c.player_id = :pid
                   OR (c.player_id IS NULL AND c.request_id IN (
                       SELECT id FROM recruitment_requests WHERE player_id = :pid2
                   ))
              )
              AND (
                   rr.id IS NULL
                   OR rr.initiated_by <> 'director'
                   OR COALESCE(rr.spec_code, '') <> ''
              )
            ORDER BY
                CASE
                    WHEN cr.recommendation = 'hire' THEN 0
                    WHEN cr.recommendation = 'reject' THEN 1
                    ELSE 2
                END,
                (c.skill_organization + c.skill_negotiation +
                 c.skill_analysis + c.skill_stress + c.skill_ethics) DESC
        ");
        $stmt->execute([
            ':pid' => $playerId,
            ':pid2' => $playerId,
            ':pid3' => $playerId,
        ]);
        return $stmt->fetchAll();
    }

    public function getHistory(int $playerId = 0, int $limit = 100): array
    {
        if ($playerId <= 0) $playerId = (int)($_SESSION['user_id'] ?? 0);
        if ($playerId <= 0) return [];

        $stmt = $this->db->prepare("
            SELECT eh.*,
                   bm.first_name, bm.last_name,
                   br.name AS role_name
            FROM employment_history eh
            LEFT JOIN board_members bm ON eh.member_id = bm.id
            LEFT JOIN board_roles   br ON bm.role_id   = br.id
            WHERE bm.player_id = :player_id
            ORDER BY eh.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':player_id', $playerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTechnicalCandidatesWithReviews(int $playerId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*,
                   br.name  AS role_name,
                   hs.name  AS spec_name,
                   hr.name  AS region_name,
                   TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE())  AS age,
                   TIMESTAMPDIFF(HOUR, NOW(), c.expires_at)      AS hours_remaining,
                   cr.score AS technical_score,
                   cr.recommendation AS tech_recommendation,
                   cr.comment        AS tech_comment,
                   cr.created_at     AS review_date
            FROM candidates c
            JOIN board_roles br             ON c.role_id        = br.id
            LEFT JOIN hr_specializations hs ON c.specialization_id = hs.id
            LEFT JOIN hr_regions hr         ON c.region_code    = hr.code
            LEFT JOIN candidate_reviews cr  ON cr.candidate_id  = c.id
                                           AND cr.player_id     = ?
            WHERE br.code = 'technical'
              AND c.expires_at > NOW()
              AND (
                   c.player_id = ?
                   OR (c.player_id IS NULL AND c.request_id IN (
                       SELECT id FROM recruitment_requests WHERE player_id = ?
                   ))
              )
            ORDER BY c.expires_at ASC
        ");
        $stmt->execute([$playerId, $playerId, $playerId]);
        return $stmt->fetchAll();
    }

 // OPERACJE NA KANDYDATACH I KONTRAKTACH / Candidate and contract operations

    public function rejectCandidate(int $candidateId, int $playerId = 0): array
    {
        if ($playerId <= 0) $playerId = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $this->db->prepare("
            SELECT id, first_name, last_name
            FROM candidates
            WHERE id = ?
              AND expires_at > NOW()
              AND (
                   player_id = ?
                   OR (player_id IS NULL AND request_id IN (
                       SELECT id FROM recruitment_requests WHERE player_id = ?
                   ))
              )
        ");
        $stmt->execute([$candidateId, $playerId, $playerId]);
        $c = $stmt->fetch();
        if (!$c) return ['success' => false, 'message' => t('hr.err_candidate_not_found')];
        $this->db->prepare("
            DELETE FROM candidates
            WHERE id = ?
              AND expires_at > NOW()
              AND (
                   player_id = ?
                   OR (player_id IS NULL AND request_id IN (
                       SELECT id FROM recruitment_requests WHERE player_id = ?
                   ))
              )
        ")->execute([$candidateId, $playerId, $playerId]);
        return ['success' => true, 'message' => t('hr.msg_candidate_rejected', ['name' => "{$c['first_name']} {$c['last_name']}"])];
    }

    public function saveCandidate(int $candidateId, int $playerId = 0): array
    {
        if ($playerId <= 0) $playerId = (int)($_SESSION['user_id'] ?? 0);
        $stmt = $this->db->prepare("
            SELECT id, first_name, last_name, expires_at
            FROM candidates
            WHERE id = ?
              AND expires_at > NOW()
              AND (
                   player_id = ?
                   OR (player_id IS NULL AND request_id IN (
                       SELECT id FROM recruitment_requests WHERE player_id = ?
                   ))
              )
        ");
        $stmt->execute([$candidateId, $playerId, $playerId]);
        $c = $stmt->fetch();
        if (!$c) return ['success' => false, 'message' => t('hr.err_candidate_not_found')];
        $newExpiry = date('Y-m-d H:i:s', strtotime($c['expires_at']) + 48 * 3600);
        $update = $this->db->prepare("
            UPDATE candidates
            SET expires_at = ?
            WHERE id = ?
              AND expires_at > NOW()
              AND (
                   player_id = ?
                   OR (player_id IS NULL AND request_id IN (
                       SELECT id FROM recruitment_requests WHERE player_id = ?
                   ))
              )
        ");
        $update->execute([$newExpiry, $candidateId, $playerId, $playerId]);
        if ($update->rowCount() !== 1) {
            return ['success' => false, 'message' => t('hr.err_candidate_not_found')];
        }
        return ['success' => true, 'message' => t('hr.msg_candidate_saved', ['name' => "{$c['first_name']} {$c['last_name']}", 'date' => date('d.m.Y H:i', strtotime($newExpiry))])];
    }

    public function renewContract(
        int $memberId,
        string $contractType = '1y',
        int $playerId = 0,
        string $idempotencyToken = ''
    ): array
    {
        if ($playerId <= 0) $playerId = (int)($_SESSION['user_id'] ?? 0);
        $contractType = in_array($contractType, ['6m', '1y', '2y'], true) ? $contractType : '1y';
        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $receipts = new EmployeeActionReceiptService($this->db);
            $receipt = $receipts->claim($playerId, 'renew_contract', $idempotencyToken, [
                'member_id'=>$memberId,
                'contract_type'=>$contractType,
            ]);
            if ($receipt['replayed']) {
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return $receipt['response'];
            }
            $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $stmt = $this->db->prepare("
                SELECT ec.*, bm.first_name, bm.last_name
                FROM employee_contracts ec
                JOIN board_members bm ON ec.member_id = bm.id
                WHERE ec.member_id = ? AND ec.status = 'active'
                  AND bm.player_id = ? AND bm.status='active'
                ORDER BY ec.contract_end DESC LIMIT 1{$suffix}
            ");
            $stmt->execute([$memberId, $playerId]);
            $contract = $stmt->fetch();
            if (!$contract) {
                $result = ['success' => false, 'message' => t('hr.err_no_active_contract')];
            } else {
                $add = ['6m' => '+6 months', '1y' => '+1 year', '2y' => '+2 years'][$contractType];
                $baseDate = max(strtotime((string)$contract['contract_end']), strtotime(date('Y-m-d')));
                $newEnd = date('Y-m-d', strtotime(date('Y-m-d', $baseDate) . ' ' . $add));
                $update = $this->db->prepare(
                    "UPDATE employee_contracts
                        SET contract_end=?, contract_type=?
                      WHERE id=? AND member_id=? AND status='active'
                        AND EXISTS (
                            SELECT 1 FROM board_members bm
                             WHERE bm.id=employee_contracts.member_id
                               AND bm.player_id=? AND bm.status='active'
                        )"
                );
                $update->execute([$newEnd, $contractType, (int)$contract['id'], $memberId, $playerId]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException('Employee contract update did not affect exactly one row.');
                }
                $result = ['success' => true, 'message' => t('hr.msg_contract_renewed', [
                    'name' => "{$contract['first_name']} {$contract['last_name']}",
                    'date' => date('d.m.Y', strtotime($newEnd)),
                ])];
            }
            $receipts->complete((int)$receipt['id'], $playerId, $result);
            if ($ownTransaction) {
                $this->db->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

 // HELPER 

    private function getRoleName(int $roleId): string
    {
        $stmt = $this->db->prepare("SELECT name FROM board_roles WHERE id = ?");
        $stmt->execute([$roleId]);
        $row = $stmt->fetch();
        return $row ? $row['name'] : tPlain('hr.role_fallback_name', ['id' => $roleId]);
    }
}
