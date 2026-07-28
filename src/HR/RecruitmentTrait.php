<?php
require_once __DIR__ . '/../FinancePolicyService.php';

/**
 * RecruitmentTrait - zlecanie i przetwarzanie rekrutacji.
 * Recruitment trait - starting and processing recruitment requests.
 */
trait HRRecruitmentTrait
{
 /**
 * Zleca rekrutacje na dane stanowisko.
 * Starts a recruitment request for the given role.
 *
 * @return array<string, mixed>
 */
    public function startRecruitment(
        int $playerId,
        int $roleId,
        string $regionCode = 'PL',
        ?string $specCode = null,
        string $initiatedBy = 'director',
        string $recruitmentType = 'local'
    ): array {
        $range = self::$recruitDuration[$recruitmentType] ?? self::$recruitDuration['local'];
        $durationMult = 1.0;

        if (class_exists('FinancePolicyService')) {
            try {
                $finPolicySvc = new FinancePolicyService($this->db);
                $hrMods = $finPolicySvc->getHRModifiers($playerId);
                $durationMult = (float)($hrMods['duration_mult'] ?? 1.0);
            } catch (Throwable $e) {
                GameLog::error('HRService', 'startRecruitment finance policy FAILED', $e, ['player_id' => $playerId]);
            }
        }

        $strikeEffects = $this->strikeEffects->forPlayer($playerId);
        $durationMult *= (float)($strikeEffects['hr']['recruitment_time_mult'] ?? 1.0);

        $isBankrupt = false;
        try {
            $bStmt = $this->db->prepare("
                SELECT COALESCE(recovery_mode, 0) AS recovery_mode,
                       status
                FROM players WHERE id = ? LIMIT 1
            ");
            $bStmt->execute([$playerId]);
            $bRow = $bStmt->fetch();
            $isBankrupt = $bRow && (
                (string)$bRow['status'] === 'bankrupt'
                || (int)$bRow['recovery_mode'] === 1
            );
        } catch (Throwable $e) {
            GameLog::error('HRService', 'startRecruitment bankruptcy check FAILED', $e, ['player_id' => $playerId]);
        }

        if ($isBankrupt) {
            $range = [min(360, $range[0] + 60), min(360, $range[1] + 60)];
            GameLog::info('HRService', 'startRecruitment - bankrupt, extended duration', [
                'player_id' => $playerId,
                'range' => $range,
            ]);
        }

        $duration = (int)max(60, round(rand($range[0], $range[1]) * $durationMult));
        $readyAt = date('Y-m-d H:i:s', time() + $duration);

        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare("SELECT id FROM players WHERE id = ? LIMIT 1 FOR UPDATE");
            $lock->execute([$playerId]);

            if ($initiatedBy === 'director') {
                $limitStmt = $this->db->prepare("
                    SELECT COUNT(*)
                    FROM recruitment_requests
                    WHERE player_id = ?
                      AND initiated_by = 'director'
                      AND COALESCE(spec_code, '') = ''
                      AND status IN ('pending','ready')
                ");
                $limitStmt->execute([$playerId]);
                if ((int)$limitStmt->fetchColumn() >= 2) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => tPlain('hr.err_max_recruitments')];
                }

                $dupStmt = $this->db->prepare("
                    SELECT COUNT(*)
                    FROM recruitment_requests
                    WHERE player_id = ?
                      AND role_id = ?
                      AND initiated_by = 'director'
                      AND COALESCE(spec_code, '') = ''
                      AND status IN ('pending','ready')
                ");
                $dupStmt->execute([$playerId, $roleId]);
            } elseif ($initiatedBy === 'technical') {
                $techTotalStmt = $this->db->prepare("
                    SELECT COUNT(*)
                    FROM recruitment_requests rr
                    JOIN board_roles br ON br.id = rr.role_id
                    WHERE rr.player_id = ?
                      AND rr.initiated_by = 'technical'
                      AND br.code = 'technical'
                      AND rr.status IN ('pending','ready')
                ");
                $techTotalStmt->execute([$playerId]);

                $techSpecStmt = $this->db->prepare("
                    SELECT COUNT(*)
                    FROM recruitment_requests rr
                    JOIN board_roles br ON br.id = rr.role_id
                    WHERE rr.player_id = ?
                      AND rr.initiated_by = 'technical'
                      AND rr.spec_code = ?
                      AND br.code = 'technical'
                      AND rr.status IN ('pending','ready')
                ");
                $techSpecStmt->execute([$playerId, $specCode]);

                if ((int)$techTotalStmt->fetchColumn() >= 6 || (int)$techSpecStmt->fetchColumn() >= 2) {
                    $this->db->rollBack();
                    return ['success' => false, 'message' => tPlain('hr.err_max_recruitments')];
                }

                $dupStmt = null;
            } else {
                $dupStmt = $this->db->prepare("
                    SELECT COUNT(*)
                    FROM recruitment_requests
                    WHERE player_id = ?
                      AND role_id = ?
                      AND initiated_by = ?
                      AND COALESCE(spec_code, '') = COALESCE(?, '')
                      AND status IN ('pending','ready')
                ");
                $dupStmt->execute([$playerId, $roleId, $initiatedBy, $specCode]);
            }

            if ($dupStmt !== null && (int)$dupStmt->fetchColumn() > 0) {
                $this->db->rollBack();
                return ['success' => false, 'message' => tPlain('hr.err_role_already_recruiting')];
            }

            $stmt = $this->db->prepare("
                INSERT INTO recruitment_requests
                    (role_id, region_code, player_id, initiated_by, recruitment_type, spec_code, ready_at, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$roleId, $regionCode, $playerId, $initiatedBy, $recruitmentType, $specCode, $readyAt]);
            $requestId = (int)$this->db->lastInsertId();
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('HRService', 'startRecruitment insert FAILED', $e, [
                'player_id' => $playerId,
                'role_id' => $roleId,
                'initiated_by' => $initiatedBy,
                'spec_code' => $specCode,
            ]);
            return ['success' => false, 'message' => tPlain('common.db_error')];
        }

        $typeLabel = tPlain($recruitmentType === 'international' ? 'recruitment.type_international' : 'recruitment.type_local');
        $mins = (int)ceil($duration / 60);

        GameLog::info('HRService', 'startRecruitment OK', [
            'player_id' => $playerId,
            'role_id' => $roleId,
            'request_id' => $requestId,
            'type' => $recruitmentType,
            'duration_s' => $duration,
            'strike_time_multiplier' => (float)($strikeEffects['hr']['recruitment_time_mult'] ?? 1.0),
            'is_bankrupt' => $isBankrupt,
            'initiated_by' => $initiatedBy,
            'spec_code' => $specCode,
        ]);

        return [
            'success' => true,
            'request_id' => $requestId,
            'ready_at' => $readyAt,
            'duration' => $duration,
            'message' => tPlain('recruitment.msg_started', ['type' => $typeLabel, 'mins' => $mins]),
        ];
    }

 /**
 * Sprawdza gotowe rekrutacje i generuje kandydatow.
 * Checks completed recruitments and generates candidates.
 * Call from cron or when loading board or HR views.
 * Wywolywac z crona lub przy zaladowaniu widokow zarzadu albo HR.
 */
    public function processReadyRecruitments(?int $playerId = null): int
    {
        $playerFilter = $playerId !== null && $playerId > 0 ? " AND player_id = ?" : "";
        $params = $playerFilter !== "" ? [$playerId] : [];

        $stmt = $this->db->prepare("
            SELECT * FROM recruitment_requests
            WHERE status = 'pending'
              AND ready_at <= NOW()
              {$playerFilter}
            ORDER BY ready_at ASC
        ");
        $stmt->execute($params);
        $ready = $stmt->fetchAll();
        $processed = 0;

        foreach ($ready as $req) {
            try {
                $this->db->beginTransaction();

                $claim = $this->db->prepare("
                    UPDATE recruitment_requests
                    SET status = 'ready'
                    WHERE id = ?
                      AND (player_id = ? OR (player_id IS NULL AND ? IS NULL))
                      AND status = 'pending'
                      AND ready_at <= NOW()
                      {$playerFilter}
                ");
                $requestPlayerId = $req['player_id'] !== null ? (int)$req['player_id'] : null;
                $claimParams = [(int)$req['id'], $requestPlayerId, $requestPlayerId];
                if ($playerFilter !== "") {
                    $claimParams[] = $playerId;
                }
                $claim->execute($claimParams);
                if ($claim->rowCount() !== 1) {
                    $this->db->rollBack();
                    continue;
                }

                $bankruptPenalty = 1.0;

                if (!empty($req['player_id'])) {
                    try {
                        $bStmt = $this->db->prepare("
                            SELECT COALESCE(recovery_mode, 0) AS recovery_mode, status
                            FROM players WHERE id = ? LIMIT 1
                        ");
                        $bStmt->execute([$req['player_id']]);
                        $bRow = $bStmt->fetch();
                        if ($bRow && (
                            (string)$bRow['status'] === 'bankrupt'
                            || (int)$bRow['recovery_mode'] === 1
                        )) {
                            $bankruptPenalty = 0.7;
                            GameLog::info('HRService', 'processReadyRecruitments - bankrupt penalty', [
                                'player_id' => $req['player_id'],
                                'request_id' => $req['id'],
                                'penalty' => $bankruptPenalty,
                            ]);
                        }
                    } catch (Throwable $e) {
                        GameLog::error('HRService', 'processReadyRecruitments bankruptcy check FAILED', $e, [
                            'player_id' => $req['player_id'],
                            'request_id' => $req['id'],
                        ]);
                    }
                }

                $generated = $this->generator->generateForRequest(
                    (int)$req['role_id'],
                    (int)$req['id'],
                    (string)($req['region_code'] ?: 'PL'),
                    $req['spec_code'] ?? null,
                    (string)($req['recruitment_type'] ?? 'local'),
                    $bankruptPenalty,
                    (string)($req['initiated_by'] ?? 'director')
                );

                $generatedCount = is_array($generated) ? count($generated) : 0;

                if ($generatedCount > 0) {
                    if (!empty($req['player_id'])) {
                        $role = $this->getRoleName((int)$req['role_id']);
                        $this->createEvent(
                            (int)$req['player_id'],
                            'new_candidates',
                            tPlain('recruitment.event_new_candidates_title', ['role' => $role]),
                            tPlain('recruitment.event_new_candidates_msg', ['role' => $role]),
                            null
                        );
                    }
                } else {
                    $cancelStmt = $this->db->prepare(
                        "UPDATE recruitment_requests
                            SET status = 'cancelled'
                          WHERE id = ?
                            AND (player_id = ? OR (player_id IS NULL AND ? IS NULL))
                            AND status = 'ready'"
                    );
                    $cancelStmt->execute([(int)$req['id'], $requestPlayerId, $requestPlayerId]);
                    if ($cancelStmt->rowCount() !== 1) {
                        GameLog::warn('HRService', 'processReadyRecruitments - cancel rowCount mismatch', [
                            'request_id' => (int)$req['id'],
                        ]);
                    }

                    GameLog::info('HRService', 'processReadyRecruitments - no candidates, request closed', [
                        'request_id' => (int)$req['id'],
                        'role_id' => (int)$req['role_id'],
                        'player_id' => (int)($req['player_id'] ?? 0),
                        'initiated_by' => (string)($req['initiated_by'] ?? 'director'),
                    ]);
                }

                $this->db->commit();
                $processed++;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                GameLog::error('HRService', 'processReadyRecruitments request FAILED', $e, [
                    'request_id' => (int)($req['id'] ?? 0),
                    'player_id' => (int)($req['player_id'] ?? 0),
                ]);
            }
        }

        return $processed;
    }
}
