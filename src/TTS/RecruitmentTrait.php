<?php
/**
 * TTS/RecruitmentTrait.php
 * Candidate review, recruitment, and formatting helpers.
 * Ocena kandydatow, rekrutacja i helpery formatowania.
 */
trait TTSRecruitmentTrait
{
    private const TECH_RECRUITMENT_MAX_PER_SPEC = 2;
    private const TECH_RECRUITMENT_MAX_TOTAL = 6;

 // Helpers / Helpery

    public static function formatTime(int $seconds): string
    {
        if ($seconds <= 0) {
            return t('technical.recruitment_msg.ready_now');
        }
        $h = floor($seconds / 3600);
        $m = floor(($seconds % 3600) / 60);
        if ($h > 0) {
            return "{$h}h {$m}m";
        }
        return "{$m}m";
    }

    public static function fmt(float $n): string
    {
        return '$' . number_format($n, 0, '.', ' ');
    }

 // Candidate review / Ocena kandydatow

    public function getTechnicalCandidates(): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*,
                   hs.name  AS spec_name,
                   hs.code  AS spec_code,
                   hr.name  AS region_name,
                   TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE())  AS age,
                   TIMESTAMPDIFF(HOUR, NOW(), c.expires_at)      AS hours_remaining,
                   cr.id               AS review_id,
                   cr.score            AS technical_score,
                   cr.recommendation   AS review_recommendation,
                   cr.comment          AS review_comment
            FROM candidates c
            JOIN board_roles br             ON c.role_id = br.id
            LEFT JOIN hr_specializations hs ON c.specialization_id = hs.id
            LEFT JOIN hr_regions hr         ON c.region_code = hr.code
            LEFT JOIN candidate_reviews cr  ON cr.candidate_id = c.id
                                           AND cr.player_id = ?
            WHERE br.code = 'technical'
              AND c.expires_at > NOW()
              AND (c.request_id IS NULL OR c.request_id IN (
                  SELECT id FROM recruitment_requests WHERE player_id = ?
              ))
            ORDER BY c.expires_at ASC
        ");
        $stmt->execute([$this->playerId, $this->playerId]);
        return $stmt->fetchAll();
    }

    public function reviewCandidate(int $candidateId, int $score, string $recommendation, string $comment): array
    {
        if ($score < 1 || $score > 10) {
            return ['success' => false, 'message' => t('technical.recruitment_msg.score_range')];
        }
        if (!in_array($recommendation, ['hire', 'reject'], true)) {
            return ['success' => false, 'message' => t('technical.recruitment_msg.invalid_recommendation')];
        }

        $manager = $this->getManager();
        $reviewerMemberId = $manager ? (int)$manager['id'] : 0;

        $cStmt = $this->db->prepare("SELECT id FROM candidates WHERE id = ? AND expires_at > NOW()");
        $cStmt->execute([$candidateId]);
        if (!$cStmt->fetch()) {
            return ['success' => false, 'message' => t('technical.recruitment_msg.candidate_missing')];
        }

        $this->db->prepare("
            INSERT INTO candidate_reviews
                (candidate_id, reviewer_member_id, player_id, score, recommendation, comment)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                score          = VALUES(score),
                recommendation = VALUES(recommendation),
                comment        = VALUES(comment),
                created_at     = CURRENT_TIMESTAMP
        ")->execute([$candidateId, $reviewerMemberId, $this->playerId, $score, $recommendation, $comment]);

        $recLabel = $recommendation === 'hire'
            ? t('technical.recruitment_msg.recommend_hire')
            : t('technical.recruitment_msg.recommend_reject');

        return ['success' => true, 'message' => t('technical.recruitment_msg.review_saved', [
            'label' => $recLabel,
            'score' => $score,
        ])];
    }

 /**
 * Marks recruitment as completed.
 * Oznacza rekrutacje jako zakonczona.
 */
    public function completeRecruitment(int $requestId): void
    {
        $this->db->prepare("
            UPDATE recruitment_requests SET status = 'completed'
            WHERE id = ? AND player_id = ?
        ")->execute([$requestId, $this->playerId]);
    }

    public function cancelRecruitment(int $requestId): array
    {
        $stmt = $this->db->prepare("
            UPDATE recruitment_requests
            SET status = 'cancelled'
            WHERE id = ?
              AND player_id = ?
              AND status = 'pending'
        ");
        $stmt->execute([$requestId, $this->playerId]);

        if ($stmt->rowCount() < 1) {
            return ['success' => false, 'message' => t('technical.err_cancel_recruitment_state')];
        }

        return ['success' => true, 'message' => t('technical.msg_recruitment_cancelled')];
    }

    public function getActiveRecruitment(): ?array
    {
        $stmt = $this->db->prepare("
            SELECT rr.*, br.name as role_name,
                   TIMESTAMPDIFF(SECOND, NOW(), rr.ready_at) as seconds_remaining
            FROM recruitment_requests rr
            JOIN board_roles br ON rr.role_id = br.id
            WHERE rr.player_id = ?
              AND br.code = 'technical'
              AND rr.status IN ('pending','ready')
            ORDER BY rr.requested_at DESC
            LIMIT 1
        ");
        $stmt->execute([$this->playerId]);
        return $stmt->fetch() ?: null;
    }

    public function getActiveRecruitments(): array
    {
        $stmt = $this->db->prepare("
            SELECT rr.*, br.name as role_name,
                   TIMESTAMPDIFF(SECOND, NOW(), rr.ready_at) as seconds_remaining
            FROM recruitment_requests rr
            JOIN board_roles br ON rr.role_id = br.id
            WHERE rr.player_id = ?
              AND br.code = 'technical'
              AND rr.status IN ('pending','ready')
            ORDER BY rr.requested_at DESC
        ");
        $stmt->execute([$this->playerId]);
        return $stmt->fetchAll();
    }

    public function getOutstandingRecruitmentCount(string $specCode): int
    {
        if (!self::getSpecDefinition($specCode)) {
            return 0;
        }

        $pendingStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM recruitment_requests rr
            JOIN board_roles br ON br.id = rr.role_id
            WHERE rr.player_id = ?
              AND rr.initiated_by = 'technical'
              AND rr.spec_code = ?
              AND br.code = 'technical'
              AND rr.status IN ('pending', 'processing')
        ");
        $pendingStmt->execute([$this->playerId, $specCode]);
        $pending = (int)$pendingStmt->fetchColumn();

        $candidateStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM candidates c
            JOIN recruitment_requests rr ON rr.id = c.request_id
            JOIN board_roles br ON br.id = c.role_id
            LEFT JOIN hr_specializations hs ON hs.id = c.specialization_id
            WHERE rr.player_id = ?
              AND rr.initiated_by = 'technical'
              AND rr.spec_code = ?
              AND br.code = 'technical'
              AND c.expires_at > NOW()
              AND (hs.code = ? OR rr.spec_code = ?)
        ");
        $candidateStmt->execute([$this->playerId, $specCode, $specCode, $specCode]);
        $candidates = (int)$candidateStmt->fetchColumn();

        return $pending + $candidates;
    }

    public function getOutstandingRecruitmentTotalCount(): int
    {
        $pendingStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM recruitment_requests rr
            JOIN board_roles br ON br.id = rr.role_id
            WHERE rr.player_id = ?
              AND rr.initiated_by = 'technical'
              AND br.code = 'technical'
              AND rr.status IN ('pending', 'processing')
        ");
        $pendingStmt->execute([$this->playerId]);
        $pending = (int)$pendingStmt->fetchColumn();

        $candidateStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM candidates c
            JOIN recruitment_requests rr ON rr.id = c.request_id
            JOIN board_roles br ON br.id = c.role_id
            WHERE rr.player_id = ?
              AND rr.initiated_by = 'technical'
              AND br.code = 'technical'
              AND c.expires_at > NOW()
        ");
        $candidateStmt->execute([$this->playerId]);
        $candidates = (int)$candidateStmt->fetchColumn();

        return $pending + $candidates;
    }

    public function getRecruitmentCapacityForSpec(string $specCode, int $maxPerSpec = self::TECH_RECRUITMENT_MAX_PER_SPEC, int $maxTotal = self::TECH_RECRUITMENT_MAX_TOTAL): int
    {
        $specOutstanding = $this->getOutstandingRecruitmentCount($specCode);
        $totalOutstanding = $this->getOutstandingRecruitmentTotalCount();
        $remainingForSpec = max(0, $maxPerSpec - $specOutstanding);
        $remainingTotal = max(0, $maxTotal - $totalOutstanding);
        return min($remainingForSpec, $remainingTotal);
    }

    public function getRecruitmentCapacitySummary(string $specCode): array
    {
        $specOutstanding = $this->getOutstandingRecruitmentCount($specCode);
        $totalOutstanding = $this->getOutstandingRecruitmentTotalCount();

        return [
            'per_spec_used'      => $specOutstanding,
            'per_spec_limit'     => self::TECH_RECRUITMENT_MAX_PER_SPEC,
            'department_used'    => $totalOutstanding,
            'department_limit'   => self::TECH_RECRUITMENT_MAX_TOTAL,
            'remaining_capacity' => min(
                max(0, self::TECH_RECRUITMENT_MAX_PER_SPEC - $specOutstanding),
                max(0, self::TECH_RECRUITMENT_MAX_TOTAL - $totalOutstanding)
            ),
        ];
    }

    public function requestRecruitment(string $specCode, string $regionCode = 'PL', string $recruitmentType = 'local'): array
    {
        $spec = self::getSpecDefinition($specCode);
        if (!$spec) {
            return ['success' => false, 'message' => t('technical.recruitment_msg.unknown_spec')];
        }

        $summary = $this->getRecruitmentCapacitySummary($specCode);
        $remaining = (int)$summary['remaining_capacity'];
        if ($remaining <= 0) {
            return [
                'success' => false,
                'message' => t('technical.rec_limit_reached', [
                    'spec'             => $spec['name'],
                    'per_spec_used'    => $summary['per_spec_used'],
                    'per_spec_limit'   => $summary['per_spec_limit'],
                    'department_used'  => $summary['department_used'],
                    'department_limit' => $summary['department_limit'],
                ]),
            ];
        }

        $roleStmt = $this->db->prepare("SELECT id FROM board_roles WHERE code = 'technical' LIMIT 1");
        $roleStmt->execute();
        $role = $roleStmt->fetch();
        if (!$role) {
            return ['success' => false, 'message' => t('technical.recruitment_msg.missing_role')];
        }

        $ranges = ['local' => [360, 720], 'international' => [1440, 2880]];
        $range = $ranges[$recruitmentType] ?? $ranges['local'];
        $duration = rand($range[0], $range[1]);
        $readyAt = date('Y-m-d H:i:s', time() + $duration);

        $this->db->prepare("
            INSERT INTO recruitment_requests
                (role_id, region_code, player_id, initiated_by, recruitment_type, spec_code, ready_at, status)
            VALUES (?, ?, ?, 'technical', ?, ?, ?, 'pending')
        ")->execute([$role['id'], $regionCode, $this->playerId, $recruitmentType, $specCode, $readyAt]);

        $mins = ceil($duration / 60);
        return [
            'success'  => true,
            'ready_at' => $readyAt,
            'message'  => t('technical.recruitment_msg.requested', [
                'spec' => $spec['name'],
                'mins' => $mins,
            ]),
        ];
    }
}
