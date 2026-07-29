<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__) . '/Employee/EmployeeSystemConfigService.php';
require_once dirname(__DIR__) . '/FinancialTransactionService.php';
require_once __DIR__ . '/EmployeeDialogueTemplateService.php';
require_once __DIR__ . '/EmployeeStrikeService.php';
require_once __DIR__ . '/EmployeeNegotiationEffectivenessService.php';
require_once __DIR__ . '/EmployeeDeadlockRetry.php';

final class EmployeeNegotiationService
{
    private readonly EmployeeSystemConfigService $config;
    private readonly EmployeeDialogueTemplateService $dialogues;
    private readonly EmployeeStrikeService $strikes;

    public function __construct(private readonly PDO $db)
    {
        EmployeeSystemBootstrap::ensure($db);
        $this->config = new EmployeeSystemConfigService($db);
        $this->dialogues = new EmployeeDialogueTemplateService($db);
        $this->strikes = new EmployeeStrikeService($db);
    }

    /** @return array<string,mixed> */
    public function openForStrike(int $playerId, int $strikeId, ?DateTimeInterface $now = null): array
    {
        $now ??= new DateTimeImmutable('now');
        if (!$this->config->getBool('feature_negotiations')) {
            throw new RuntimeException('Employee strike negotiations are disabled.');
        }
        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $strike = $this->lockStrike($playerId, $strikeId);
            if (!in_array((string)$strike['status'], ['active', 'negotiating'], true)) {
                throw new RuntimeException('Only active strikes can be negotiated.');
            }
            if (!empty($strike['negotiation_cooldown_until'])
                && strtotime((string)$strike['negotiation_cooldown_until']) > $now->getTimestamp()) {
                throw new RuntimeException('Strike negotiation is on cooldown.');
            }
            $this->openNegotiationInsideTransaction($playerId, $strikeId, $now);
            $negotiation = $this->loadNegotiation($playerId, $strikeId, true);
            if ($ownTransaction) {
                $this->db->commit();
            }
            return $this->formatNegotiation($negotiation);
        } catch (Throwable $exception) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    public function submitOffer(
        int $playerId,
        int $strikeId,
        float $raisePct,
        float $bonusPerMember,
        string $idempotencyToken,
        ?DateTimeInterface $now = null,
        ?int $expectedRound = null
    ): array {
        return EmployeeDeadlockRetry::run(
            $this->db,
            fn(): array => $this->submitOfferOnce(
                $playerId,
                $strikeId,
                $raisePct,
                $bonusPerMember,
                $idempotencyToken,
                $now,
                $expectedRound
            )
        );
    }

    /** @return array<string,mixed> */
    private function submitOfferOnce(
        int $playerId,
        int $strikeId,
        float $raisePct,
        float $bonusPerMember,
        string $idempotencyToken,
        ?DateTimeInterface $now = null,
        ?int $expectedRound = null
    ): array {
        $now ??= new DateTimeImmutable('now');
        if (!$this->config->getBool('feature_negotiations')) {
            throw new RuntimeException('Employee strike negotiations are disabled.');
        }
        $raisePct = round($raisePct, 4);
        $bonusPerMember = round($bonusPerMember, 2);
        $minRaise = $this->config->getFloat('negotiation_raise_min');
        $maxRaise = $this->config->getFloat('negotiation_raise_max');
        $maxBonus = $this->config->getFloat('negotiation_bonus_max');
        if ($raisePct <= 0.0 && $bonusPerMember <= 0.0) {
            throw new InvalidArgumentException('Employee strike offer must contain a real raise or bonus.');
        }
        if ($raisePct < $minRaise || $raisePct > $maxRaise || $bonusPerMember < 0 || $bonusPerMember > $maxBonus) {
            throw new InvalidArgumentException('Employee strike offer is outside configured limits.');
        }
        if ($expectedRound !== null && $expectedRound <= 0) {
            throw new InvalidArgumentException('Expected negotiation round must be positive.');
        }
        $token = $this->normalizeToken($playerId, $idempotencyToken);
        $existing = $this->roundByToken($playerId, $strikeId, $token);
        if ($existing !== null) {
            $this->assertMatchingOffer($existing, $raisePct, $bonusPerMember);
            return $existing + ['idempotent' => true];
        }

        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->lockPlayer($playerId);
            $strike = $this->lockStrike($playerId, $strikeId);
            $existing = $this->roundByToken($playerId, $strikeId, $token);
            if ($existing !== null) {
                $this->assertMatchingOffer($existing, $raisePct, $bonusPerMember);
                if ($ownTransaction) {
                    $this->db->commit();
                }
                return $existing + ['idempotent' => true];
            }
            if (empty($strike['open_key'])) {
                throw new RuntimeException('Open strike does not exist for this player.');
            }
            if ((string)$strike['status'] === 'active') {
                if (!empty($strike['negotiation_cooldown_until']) && strtotime((string)$strike['negotiation_cooldown_until']) > $now->getTimestamp()) {
                    throw new RuntimeException('Cannot open negotiation during cooldown.');
                }
                $this->openNegotiationInsideTransaction($playerId, $strikeId, $now);
                $strike = $this->lockStrike($playerId, $strikeId);
            }
            if ((string)$strike['status'] !== 'negotiating') {
                throw new RuntimeException('Strike is not open for negotiation.');
            }
            $negotiation = $this->loadNegotiation($playerId, $strikeId, true);
            if ((string)$negotiation['status'] !== 'open') {
                throw new RuntimeException('Strike negotiation is not open.');
            }
            if (!empty($negotiation['round_deadline_at'])
                && strtotime((string)$negotiation['round_deadline_at']) < $now->getTimestamp()) {
                $this->strikes->expireNegotiation(
                    $playerId,
                    $strikeId,
                    (int)$negotiation['id'],
                    $now
                );
                if ($ownTransaction) {
                    $this->db->commit();
                }
                throw new RuntimeException('Negotiation round deadline has passed.');
            }
            $roundNo = (int)$negotiation['current_round'];
            if ($expectedRound !== null && $roundNo !== $expectedRound) {
                throw new RuntimeException('Negotiation round has already changed.');
            }
            $members = $this->members($playerId, $strikeId);
            if ($members === []) {
                throw new RuntimeException('Strike has no active members.');
            }
            $metrics = $this->metrics($members);
            $formula = $this->score($playerId, $raisePct, $bonusPerMember, $metrics, $roundNo, (int)$negotiation['max_rounds']);
            $accepted = $formula['score'] >= $formula['random_roll'];
            $isFinal = $roundNo >= (int)$negotiation['max_rounds'];
            $result = $accepted ? 'accepted' : ($isFinal ? 'final_failure' : 'rejected');
            $counterRaise = $accepted ? null : min($maxRaise, round($raisePct + max(2.0, (100.0 - $formula['score']) / 12.0), 2));
            $counterBonus = $accepted ? null : min($maxBonus, round($bonusPerMember + max(5000.0, $metrics['participant_count'] * 500.0), 2));
            $template = $this->dialogues->choose(
                $result === 'accepted' ? 'accepted' : ($result === 'final_failure' ? 'final_failure' : 'counteroffer'),
                (string)$strike['department_code'],
                $roundNo,
                $accepted ? 'conciliatory' : 'firm',
                $strikeId
            );
            $this->insertRound(
                (int)$negotiation['id'],
                $strikeId,
                $playerId,
                (int)$negotiation['attempt_no'],
                $roundNo,
                $token,
                $raisePct,
                $bonusPerMember,
                $counterRaise,
                $counterBonus,
                $formula,
                $template !== null ? (int)$template['id'] : null,
                $result
            );

            if ($accepted) {
                $this->settle($playerId, $strikeId, $members, $raisePct, $bonusPerMember, (float)$this->config->getFloat('settlement_morale_gain'));
                $this->db->prepare(
                    "UPDATE employee_strike_negotiations SET status='accepted', updated_at=CURRENT_TIMESTAMP
                      WHERE id=? AND player_id=? AND status='open'"
                )->execute([(int)$negotiation['id'], $playerId]);
            } else {
                $effects = $this->rejectedOfferEffects((float)$formula['offer_quality']);
                $this->db->prepare(
                    'UPDATE employee_strikes SET support_pct=CASE
                                WHEN support_pct+:support_delta < 0 THEN 0
                                WHEN support_pct+:support_delta > 100 THEN 100
                                ELSE support_pct+:support_delta END,
                            updated_at=CURRENT_TIMESTAMP
                      WHERE id=:id AND player_id=:player_id AND open_key IS NOT NULL'
                )->execute(['support_delta' => $effects['support_delta'], 'id' => $strikeId, 'player_id' => $playerId]);
                $this->applyMemberPressure($playerId, $strikeId, $effects['support_delta'], $effects['morale_delta']);
                if ($isFinal) {
                    $cooldown = date('Y-m-d H:i:s', $now->getTimestamp() + $this->config->getInt('negotiation_cooldown_hours') * 3600);
                    $this->db->prepare(
                        "UPDATE employee_strike_negotiations SET status='failed', updated_at=CURRENT_TIMESTAMP
                          WHERE id=? AND player_id=? AND status='open'"
                    )->execute([(int)$negotiation['id'], $playerId]);
                    $this->db->prepare(
                        "UPDATE employee_strikes SET status='active', negotiation_cooldown_until=?, updated_at=CURRENT_TIMESTAMP
                          WHERE id=? AND player_id=? AND open_key IS NOT NULL"
                    )->execute([$cooldown, $strikeId, $playerId]);
                } else {
                    $deadline = date('Y-m-d H:i:s', $now->getTimestamp() + $this->config->getInt('negotiation_round_hours') * 3600);
                    $this->db->prepare(
                        'UPDATE employee_strike_negotiations SET current_round=current_round+1,
                                round_deadline_at=?, updated_at=CURRENT_TIMESTAMP
                          WHERE id=? AND player_id=? AND status=\'open\''
                    )->execute([$deadline, (int)$negotiation['id'], $playerId]);
                }
            }

            $round = $this->roundByToken($playerId, $strikeId, $token) ?? [];
            if ($ownTransaction) {
                $this->db->commit();
            }
            return $round + ['idempotent' => false];
        } catch (Throwable $exception) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    /** @return array{support_delta:float,morale_delta:float} */
    private function rejectedOfferEffects(float $offerQuality): array
    {
        $gain = $this->config->getFloat('negotiation_reject_support_gain');
        if ($offerQuality < 10.0) {
            return ['support_delta' => 0.0, 'morale_delta' => -3.0];
        }
        if ($offerQuality < 25.0) {
            return ['support_delta' => round($gain * 0.25, 2), 'morale_delta' => -1.0];
        }
        return ['support_delta' => $gain, 'morale_delta' => 0.0];
    }

    private function applyMemberPressure(int $playerId, int $strikeId, float $supportDelta, float $moraleDelta): void
    {
        $stmt = $this->db->prepare(
            'UPDATE employee_state
                SET strike_support=CASE
                        WHEN strike_support+:support_delta < 0 THEN 0
                        WHEN strike_support+:support_delta > 100 THEN 100
                        ELSE strike_support+:support_delta END,
                    morale=CASE
                        WHEN morale+:morale_delta < 0 THEN 0
                        WHEN morale+:morale_delta > 100 THEN 100
                        ELSE morale+:morale_delta END,
                    dispute_ticks=dispute_ticks+1,
                    version=version+1,
                    updated_at=CURRENT_TIMESTAMP
              WHERE player_id=:player_id
                AND EXISTS (
                    SELECT 1 FROM employee_strike_members sm
                     WHERE sm.player_id=employee_state.player_id
                       AND sm.source_type=employee_state.source_type
                       AND sm.source_id=employee_state.source_id
                       AND sm.strike_id=:strike_id
                       AND sm.left_at IS NULL
                )'
        );
        $stmt->execute([
            'support_delta' => $supportDelta,
            'morale_delta' => $moraleDelta,
            'player_id' => $playerId,
            'strike_id' => $strikeId,
        ]);
    }

    /** @return array<string,mixed> */
    private function lockStrike(int $playerId, int $strikeId): array
    {
        $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT * FROM employee_strikes WHERE id=? AND player_id=? LIMIT 1{$suffix}"
        );
        $stmt->execute([$strikeId, $playerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Open strike does not exist for this player.');
        }
        return $row;
    }

    /** @return array<string,mixed> */
    private function loadNegotiation(int $playerId, int $strikeId, bool $lock): array
    {
        $suffix = $lock && $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT * FROM employee_strike_negotiations WHERE player_id=? AND strike_id=? LIMIT 1{$suffix}"
        );
        $stmt->execute([$playerId, $strikeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Strike negotiation does not exist.');
        }
        return $row;
    }

    private function openNegotiationInsideTransaction(int $playerId, int $strikeId, DateTimeInterface $now): void
    {
        $maxRounds = max(1, min(5, $this->config->getInt('negotiation_rounds')));
        $deadline = date('Y-m-d H:i:s', $now->getTimestamp() + $this->config->getInt('negotiation_round_hours') * 3600);
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? "INSERT INTO employee_strike_negotiations
                (strike_id, player_id, status, current_round, max_rounds, round_deadline_at)
               VALUES (?, ?, 'open', 1, ?, ?) ON CONFLICT(strike_id) DO NOTHING"
            : "INSERT IGNORE INTO employee_strike_negotiations
                (strike_id, player_id, status, current_round, max_rounds, round_deadline_at)
               VALUES (?, ?, 'open', 1, ?, ?)";
        $this->db->prepare($sql)->execute([$strikeId, $playerId, $maxRounds, $deadline]);
        $this->db->prepare(
            "UPDATE employee_strike_negotiations
                SET status='open', attempt_no=attempt_no+1, current_round=1,
                    max_rounds=?, round_deadline_at=?, updated_at=CURRENT_TIMESTAMP
              WHERE strike_id=? AND player_id=? AND status IN ('failed','expired')"
        )->execute([$maxRounds, $deadline, $strikeId, $playerId]);
        $this->db->prepare(
            "UPDATE employee_strikes SET status='negotiating', updated_at=CURRENT_TIMESTAMP
              WHERE id=? AND player_id=? AND status='active' AND open_key IS NOT NULL"
        )->execute([$strikeId, $playerId]);
    }

    /** @return list<array<string,mixed>> */
    private function members(int $playerId, int $strikeId): array
    {
        $stmt = $this->db->prepare(
            "SELECT sm.*, es.morale, es.strike_support, es.salary_satisfaction, es.workload
               FROM employee_strike_members sm
               JOIN employee_state es ON es.player_id=sm.player_id
                 AND es.source_type=sm.source_type AND es.source_id=sm.source_id
          LEFT JOIN technical_staff ts
                 ON sm.source_type='technical_staff' AND ts.id=sm.source_id AND ts.player_id=sm.player_id
          LEFT JOIN board_members bm
                 ON sm.source_type='board_member' AND bm.id=sm.source_id AND bm.player_id=sm.player_id
               WHERE sm.player_id=? AND sm.strike_id=? AND sm.left_at IS NULL
                 AND es.relation_status='on_strike'
                 AND ((sm.source_type='technical_staff' AND ts.status IN ('active','busy','on_leave'))
                   OR (sm.source_type='board_member' AND bm.status='active'))"
        );
        $stmt->execute([$playerId, $strikeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param list<array<string,mixed>> $members
     * @return array<string,float|int>
     */
    private function metrics(array $members): array
    {
        $count = max(1, count($members));
        return [
            'participant_count' => $count,
            'avg_morale' => array_sum(array_map(static fn(array $m): float => (float)$m['morale'], $members)) / $count,
            'avg_support' => array_sum(array_map(static fn(array $m): float => (float)$m['strike_support'], $members)) / $count,
        ];
    }

    /**
     * @param array<string,float|int> $metrics
     * @return array<string,float|int>
     */
    private function score(int $playerId, float $raisePct, float $bonusPerMember, array $metrics, int $roundNo, int $maxRounds): array
    {
        $maxRaise = max(0.01, $this->config->getFloat('negotiation_raise_max'));
        $maxBonus = max(1.0, $this->config->getFloat('negotiation_bonus_max'));
        $offerQuality = min(100.0, ($raisePct / $maxRaise) * 70.0 + ($bonusPerMember / $maxBonus) * 30.0);
        $roundPressure = $maxRounds > 1 ? (($roundNo - 1) / ($maxRounds - 1)) * 10.0 : 5.0;
        $hrEffectiveness = $this->hrEffectiveness($playerId);
        $score = $offerQuality * $this->config->getFloat('negotiation_offer_weight')
            + (100.0 - (float)$metrics['avg_support']) * $this->config->getFloat('negotiation_support_weight')
            + (float)$metrics['avg_morale'] * $this->config->getFloat('negotiation_morale_weight')
            + $hrEffectiveness * $this->config->getFloat('negotiation_hr_weight')
            + $roundPressure;
        return [
            'participant_count' => (int)$metrics['participant_count'],
            'offer_quality' => round($offerQuality, 4),
            'avg_support' => round((float)$metrics['avg_support'], 4),
            'avg_morale' => round((float)$metrics['avg_morale'], 4),
            'hr_effectiveness' => round($hrEffectiveness, 4),
            'round_pressure' => round($roundPressure, 4),
            'score' => round(max(0.0, min(100.0, $score)), 4),
            'random_roll' => random_int(0, 10000) / 100.0,
        ];
    }

    private function hrEffectiveness(int $playerId): float
    {
        return (new EmployeeNegotiationEffectivenessService($this->db))->calculate($playerId, false);
    }

    /** @param array<string,float|int> $formula */
    private function insertRound(
        int $negotiationId,
        int $strikeId,
        int $playerId,
        int $attemptNo,
        int $roundNo,
        string $token,
        float $raisePct,
        float $bonusPerMember,
        ?float $counterRaise,
        ?float $counterBonus,
        array $formula,
        ?int $templateId,
        string $result
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO employee_strike_negotiation_rounds
                (negotiation_id, strike_id, player_id, attempt_no, round_no, idempotency_token, raise_pct,
                  bonus_per_member, counter_raise_pct, counter_bonus_per_member, random_roll,
                  formula_json, dialogue_template_id, result)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $negotiationId, $strikeId, $playerId, $attemptNo, $roundNo, $token, $raisePct, $bonusPerMember,
            $counterRaise, $counterBonus, $formula['random_roll'], json_encode($formula, JSON_THROW_ON_ERROR),
            $templateId, $result,
        ]);
    }

    /** @param list<array<string,mixed>> $members */
    private function settle(int $playerId, int $strikeId, array $members, float $raisePct, float $bonusPerMember, float $moraleGain): void
    {
        $totalBonus = round($bonusPerMember * count($members), 2);
        if ($totalBonus > 0.0) {
            $payment = (new FinancialTransactionService($this->db))->debitCombined(
                $playerId,
                $totalBonus,
                FinancialTransactionService::TYPE_HR_STRIKE_SETTLEMENT,
                'HR strike settlement',
                'employee_strike',
                $strikeId
            );
            if (empty($payment['success'])) {
                throw new RuntimeException('Strike settlement payment failed: ' . (string)($payment['error'] ?? 'unknown'));
            }
        }
        foreach ($members as $member) {
            $this->raiseSalary($playerId, (string)$member['source_type'], (int)$member['source_id'], $raisePct);
        }
        $this->strikes->closeByAgreement($playerId, $strikeId, $moraleGain);
    }

    private function raiseSalary(int $playerId, string $sourceType, int $sourceId, float $raisePct): void
    {
        if ($raisePct <= 0.0) {
            return;
        }
        $table = $sourceType === 'technical_staff' ? 'technical_staff' : 'board_members';
        $statusSql = $sourceType === 'technical_staff'
            ? "status IN ('active','busy','on_leave')"
            : "status='active'";
        $stmt = $this->db->prepare(
            "UPDATE {$table} SET salary=ROUND(salary * ?, 2)
              WHERE id=? AND player_id=? AND {$statusSql}"
        );
        $stmt->execute([1.0 + $raisePct / 100.0, $sourceId, $playerId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Employee salary update did not affect exactly one row.');
        }
        if ($sourceType === 'board_member') {
            $contract = $this->db->prepare(
                "UPDATE employee_contracts
                    SET salary=ROUND(salary * ?, 2)
                  WHERE member_id=? AND status='active'
                    AND EXISTS (
                        SELECT 1 FROM board_members bm
                         WHERE bm.id=employee_contracts.member_id
                           AND bm.player_id=? AND bm.status='active'
                    )"
            );
            $contract->execute([1.0 + $raisePct / 100.0, $sourceId, $playerId]);
        }
    }

    /** @return array<string,mixed>|null */
    private function roundByToken(int $playerId, int $strikeId, string $token): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, n.max_rounds, n.round_deadline_at, s.department_code,
                    d.text_pl AS dialogue_text_pl, d.text_en AS dialogue_text_en
               FROM employee_strike_negotiation_rounds r
               JOIN employee_strike_negotiations n
                 ON n.id=r.negotiation_id AND n.player_id=r.player_id AND n.strike_id=r.strike_id
               JOIN employee_strikes s ON s.id=r.strike_id AND s.player_id=r.player_id
               LEFT JOIN employee_dialogue_templates d ON d.id=r.dialogue_template_id
              WHERE r.player_id=? AND r.strike_id=? AND r.idempotency_token=? LIMIT 1'
        );
        $stmt->execute([$playerId, $strikeId, $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->formatRound($row) : null;
    }

    private function normalizeToken(int $playerId, string $token): string
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 120) {
            throw new InvalidArgumentException('Invalid negotiation idempotency token.');
        }
        return 'p' . $playerId . ':' . hash('sha256', $token);
    }

    private function lockPlayer(int $playerId): void
    {
        $suffix = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare("SELECT id FROM players WHERE id=? LIMIT 1{$suffix}");
        $stmt->execute([$playerId]);
        if ((int)($stmt->fetchColumn() ?: 0) !== $playerId) {
            throw new RuntimeException('Player does not exist for HR negotiation.');
        }
    }

    /** @param array<string,mixed> $existing */
    private function assertMatchingOffer(array $existing, float $raisePct, float $bonusPerMember): void
    {
        if (abs((float)$existing['raise_pct'] - $raisePct) > 0.00009
            || abs((float)$existing['bonus_per_member'] - $bonusPerMember) > 0.009) {
            throw new RuntimeException('Negotiation token was reused with different offer data.');
        }
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function formatRound(array $row): array
    {
        $formula = json_decode((string)$row['formula_json'], true) ?: [];
        $values = [
            'department' => (string)($row['department_code'] ?? ''),
            'round' => (int)$row['round_no'],
            'max_rounds' => (int)($row['max_rounds'] ?? $row['round_no']),
            'morale' => round((float)($formula['avg_morale'] ?? 0), 1),
            'support_pct' => round((float)($formula['avg_support'] ?? 0), 1),
            'raise_pct' => (float)$row['raise_pct'],
            'bonus' => (float)$row['bonus_per_member'],
            'counter_raise_pct' => $row['counter_raise_pct'] !== null ? (float)$row['counter_raise_pct'] : 0,
            'counter_bonus' => $row['counter_bonus_per_member'] !== null ? (float)$row['counter_bonus_per_member'] : 0,
            'deadline' => (string)($row['round_deadline_at'] ?? ''),
            'participant_count' => (int)($formula['participant_count'] ?? 0),
        ];
        return [
            'success' => true,
            'round_id' => (int)$row['id'],
            'attempt_no' => (int)($row['attempt_no'] ?? 1),
            'round_no' => (int)$row['round_no'],
            'result' => (string)$row['result'],
            'raise_pct' => (float)$row['raise_pct'],
            'bonus_per_member' => (float)$row['bonus_per_member'],
            'counter_raise_pct' => $row['counter_raise_pct'] !== null ? (float)$row['counter_raise_pct'] : null,
            'counter_bonus_per_member' => $row['counter_bonus_per_member'] !== null ? (float)$row['counter_bonus_per_member'] : null,
            'formula' => $formula,
            'dialogue_template_id' => $row['dialogue_template_id'] !== null ? (int)$row['dialogue_template_id'] : null,
            'dialogue' => [
                'pl' => $this->renderRoundDialogue((string)($row['dialogue_text_pl'] ?? ''), $values),
                'en' => $this->renderRoundDialogue((string)($row['dialogue_text_en'] ?? ''), $values),
            ],
        ];
    }

    /** @param array<string,int|float|string> $values */
    private function renderRoundDialogue(string $text, array $values): string
    {
        foreach ($values as $key => $value) {
            $text = str_replace('{' . $key . '}', (string)$value, $text);
        }
        return $text;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function formatNegotiation(array $row): array
    {
        return [
            'success' => true,
            'negotiation_id' => (int)$row['id'],
            'strike_id' => (int)$row['strike_id'],
            'status' => (string)$row['status'],
            'attempt_no' => (int)($row['attempt_no'] ?? 1),
            'current_round' => (int)$row['current_round'],
            'max_rounds' => (int)$row['max_rounds'],
            'round_deadline_at' => $row['round_deadline_at'],
        ];
    }
}
