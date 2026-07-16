<?php
declare(strict_types=1);

require_once __DIR__ . '/Contracts/ContractSchema.php';

/**
 * ContractReputationService - separate 0-100 contract reputation indicator.
 * ContractReputationService - osobny wskaznik reputacji kontraktowej 0-100.
 */
class ContractReputationService
{
    private const DEFAULT_SCORE = 50;
    private const MIN_SCORE = 0;
    private const MAX_SCORE = 100;

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        ContractSchema::ensure($this->db);
    }

    public function getScore(int $playerId): int
    {
        if ($playerId <= 0) {
            return self::DEFAULT_SCORE;
        }
        $this->ensureRow($playerId);
        $stmt = $this->db->prepare('SELECT score FROM contract_reputation WHERE player_id = ? LIMIT 1');
        $stmt->execute([$playerId]);
        return $this->clampScore((int)$stmt->fetchColumn());
    }

    /**
     * Return player reputation rows for admin review.
     * Zwraca reputacje graczy do podgladu admina.
     *
     * @return list<array<string,mixed>>
     */
    public function listScores(string $search = '', int $limit = 100): array
    {
        $limit = max(1, min(250, $limit));
        $params = [];
        $where = '';
        $search = trim($search);
        if ($search !== '') {
            $where = ' WHERE p.username LIKE ? OR p.company_name LIKE ?';
            $needle = '%' . $search . '%';
            $params = [$needle, $needle];
        }

        $stmt = $this->db->prepare(
            "SELECT p.id AS player_id, p.username, p.company_name,
                    COALESCE(cr.score, " . self::DEFAULT_SCORE . ") AS score,
                    COALESCE(cr.total_contracts, 0) AS total_contracts,
                    COALESCE(cr.completed_contracts, 0) AS completed_contracts,
                    COALESCE(cr.failed_contracts, 0) AS failed_contracts,
                    COALESCE(cr.cancelled_contracts, 0) AS cancelled_contracts,
                    COALESCE(cr.missed_deliveries, 0) AS missed_deliveries,
                    COALESCE(cr.perfect_contracts, 0) AS perfect_contracts,
                    cr.contract_blocked_until,
                    cr.updated_at
               FROM players p
               LEFT JOIN contract_reputation cr ON cr.player_id = p.id
               {$where}
              ORDER BY score ASC, p.id ASC
              LIMIT {$limit}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Return reputation change log for admin audit.
     * Zwraca historie zmian reputacji do audytu admina.
     *
     * @return list<array<string,mixed>>
     */
    public function recentLogs(?int $playerId = null, int $limit = 100): array
    {
        $limit = max(1, min(250, $limit));
        $where = '';
        $params = [];
        if ($playerId !== null && $playerId > 0) {
            $where = ' WHERE crl.player_id = ?';
            $params[] = $playerId;
        }

        $stmt = $this->db->prepare(
            "SELECT crl.*, p.username, p.company_name, pc.contract_name
               FROM contract_reputation_log crl
               LEFT JOIN players p ON p.id = crl.player_id
               LEFT JOIN player_contracts pc ON pc.id = crl.contract_id
               {$where}
              ORDER BY crl.id DESC
              LIMIT {$limit}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Apply manual admin adjustment through the same reputation ledger.
     * Wykonuje reczna korekte admina przez ten sam dziennik reputacji.
     *
     * @return array{score:int}
     */
    public function adminAdjustScore(int $playerId, int $delta, string $note): array
    {
        if ($playerId <= 0) {
            throw new InvalidArgumentException('Invalid player id.');
        }
        if (!$this->playerExists($playerId)) {
            throw new InvalidArgumentException('Player does not exist.');
        }
        if ($delta < -100 || $delta > 100 || $delta === 0) {
            throw new InvalidArgumentException('Invalid reputation delta.');
        }

        $note = trim($note);
        $safeNote = function_exists('mb_substr') ? mb_substr($note, 0, 255) : substr($note, 0, 255);

        $this->changeScore($playerId, $delta, 'admin_adjustment', null, [
            'note' => $safeNote,
        ]);

        return ['score' => $this->getScore($playerId)];
    }

    public function ensureRow(int $playerId): void
    {
        if ($playerId <= 0) {
            return;
        }
        if ($this->driver() === 'sqlite') {
            $this->db->prepare(
                "INSERT OR IGNORE INTO contract_reputation (player_id, score, updated_at) VALUES (?, ?, ?)"
            )->execute([$playerId, self::DEFAULT_SCORE, $this->nowString()]);
            return;
        }
        $this->db->prepare(
            "INSERT IGNORE INTO contract_reputation (player_id, score, updated_at) VALUES (?, ?, ?)"
        )->execute([$playerId, self::DEFAULT_SCORE, $this->nowString()]);
    }

    public function getBlockedUntil(int $playerId): ?string
    {
        if ($playerId <= 0) {
            return null;
        }
        $this->ensureRow($playerId);
        $stmt = $this->db->prepare('SELECT contract_blocked_until FROM contract_reputation WHERE player_id = ? LIMIT 1');
        $stmt->execute([$playerId]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null || $value === '' ? null : (string)$value;
    }

    public function getBlockedUntilForUpdate(int $playerId): ?string
    {
        if ($playerId <= 0) {
            return null;
        }
        $this->ensureRow($playerId);
        $forUpdate = $this->driver() === 'sqlite' ? '' : ' FOR UPDATE';
        $stmt = $this->db->prepare("SELECT contract_blocked_until FROM contract_reputation WHERE player_id = ? LIMIT 1{$forUpdate}");
        $stmt->execute([$playerId]);
        $value = $stmt->fetchColumn();
        return $value === false || $value === null || $value === '' ? null : (string)$value;
    }

    public function blockNewContracts(int $playerId, int $minutes): void
    {
        if ($playerId <= 0 || $minutes <= 0) {
            return;
        }
        $this->ensureRow($playerId);
        $now = $this->nowString();
        $blockedUntil = $this->datePlusMinutes($now, $minutes);
        $this->db->prepare(
            'UPDATE contract_reputation
                SET contract_blocked_until = CASE
                        WHEN contract_blocked_until IS NULL OR contract_blocked_until < ? THEN ?
                        ELSE contract_blocked_until
                    END,
                    updated_at = ?
              WHERE player_id = ?'
        )->execute([$blockedUntil, $blockedUntil, $now, $playerId]);
    }

    /** @param array<string,mixed> $meta */
    public function changeScore(int $playerId, int $delta, string $reason, ?int $contractId = null, array $meta = []): void
    {
        if ($playerId <= 0 || $reason === '') {
            return;
        }

        $ownTx = !$this->db->inTransaction();
        try {
            if ($ownTx) {
                $this->db->beginTransaction();
            }
            $this->ensureRow($playerId);

            $forUpdate = $this->driver() === 'sqlite' ? '' : ' FOR UPDATE';
            $stmt = $this->db->prepare("SELECT score FROM contract_reputation WHERE player_id = ? LIMIT 1{$forUpdate}");
            $stmt->execute([$playerId]);
            $oldScore = (int)$stmt->fetchColumn();
            $newScore = $this->clampScore($oldScore + $delta);
            $effectiveDelta = $newScore - $oldScore;
            $now = $this->nowString();

            $this->db->prepare(
                "UPDATE contract_reputation SET score = ?, updated_at = ? WHERE player_id = ?"
            )->execute([$newScore, $now, $playerId]);

            $this->db->prepare(
                "INSERT INTO contract_reputation_log
                    (player_id, contract_id, delta, score_after, reason, message, meta_json, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $playerId,
                $contractId,
                $effectiveDelta,
                $newScore,
                $reason,
                'contract.reputation.' . $reason,
                $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $now,
            ]);

            if ($ownTx) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractReputationService', 'changeScore FAILED', $e, [
                    'player_id' => $playerId,
                    'contract_id' => $contractId,
                    'reason' => $reason,
                ]);
            }
            throw $e;
        }
    }

    /** @param array<string,mixed> $delivery */
    public function onDeliverySuccess(int $playerId, int $contractId, array $delivery): void
    {
        $delta = $this->termInt($playerId, $contractId, 'reputation_gain_on_delivery', 1);
        $this->changeScore($playerId, $delta, 'delivery_success', $contractId, $delivery);
    }

    /** @param array<string,mixed> $delivery */
    public function onDeliveryMiss(int $playerId, int $contractId, array $delivery): void
    {
        $status = (string)($delivery['status'] ?? 'missed');
        $term = $status === 'partial' ? 'reputation_loss_on_partial_delivery' : 'reputation_loss_on_missed_delivery';
        $delta = $this->termInt($playerId, $contractId, $term, $status === 'partial' ? -1 : -2);

        $this->withTransaction(function () use ($playerId, $contractId, $delivery, $status, $delta): void {
            $this->ensureRow($playerId);
            $this->db->prepare(
                "UPDATE contract_reputation SET missed_deliveries = missed_deliveries + 1, updated_at = ? WHERE player_id = ?"
            )->execute([$this->nowString(), $playerId]);
            $this->changeScore($playerId, $delta, $status === 'partial' ? 'delivery_partial' : 'delivery_missed', $contractId, $delivery);
        });
    }

    public function onContractCompleted(int $playerId, int $contractId, bool $perfect): void
    {
        $delta = $this->termInt($playerId, $contractId, 'reputation_gain_on_full_completion', 1);
        if ($perfect) {
            $delta += $this->termInt($playerId, $contractId, 'reputation_gain_on_perfect_contract', 0);
        }
        $this->withTransaction(function () use ($playerId, $contractId, $perfect, $delta): void {
            $this->incrementCounters($playerId, [
                'total_contracts' => 1,
                'completed_contracts' => 1,
                'perfect_contracts' => $perfect ? 1 : 0,
            ]);
            $this->changeScore($playerId, $delta, $perfect ? 'contract_perfect' : 'contract_completed', $contractId);
        });
    }

    public function onContractFailed(int $playerId, int $contractId): void
    {
        $delta = $this->termInt($playerId, $contractId, 'reputation_loss_on_contract_failed', -3);
        $this->withTransaction(function () use ($playerId, $contractId, $delta): void {
            $this->incrementCounters($playerId, [
                'total_contracts' => 1,
                'failed_contracts' => 1,
            ]);
            $this->changeScore($playerId, $delta, 'contract_failed', $contractId);
        });
    }

    public function onContractCancelled(int $playerId, int $contractId): void
    {
        $delta = $this->termInt(
            $playerId,
            $contractId,
            'cancel_reputation_loss',
            $this->termInt($playerId, $contractId, 'reputation_loss_on_cancel', -1)
        );
        $this->withTransaction(function () use ($playerId, $contractId, $delta): void {
            $this->incrementCounters($playerId, [
                'total_contracts' => 1,
                'cancelled_contracts' => 1,
            ]);
            $this->changeScore($playerId, $delta, 'contract_cancelled', $contractId);
        });
    }

    private function datePlusMinutes(string $base, int $minutes): string
    {
        if ($this->driver() === 'sqlite') {
            return date('Y-m-d H:i:s', strtotime($base . ' +' . $minutes . ' minutes'));
        }
        $dt = new DateTimeImmutable($base);
        return $dt->modify('+' . $minutes . ' minutes')->format('Y-m-d H:i:s');
    }

    /** @param array<string,int> $counters */
    private function incrementCounters(int $playerId, array $counters): void
    {
        if ($playerId <= 0 || $counters === []) {
            return;
        }
        $this->ensureRow($playerId);
        $allowed = [
            'total_contracts',
            'completed_contracts',
            'failed_contracts',
            'cancelled_contracts',
            'missed_deliveries',
            'perfect_contracts',
        ];
        $sets = [];
        $params = [];
        foreach ($counters as $column => $increment) {
            if (!in_array($column, $allowed, true) || $increment === 0) {
                continue;
            }
            $sets[] = "{$column} = {$column} + ?";
            $params[] = $increment;
        }
        if ($sets === []) {
            return;
        }
        $sets[] = 'updated_at = ?';
        $params[] = $this->nowString();
        $params[] = $playerId;
        $this->db->prepare('UPDATE contract_reputation SET ' . implode(', ', $sets) . ' WHERE player_id = ?')
            ->execute($params);
    }

    private function termInt(int $playerId, int $contractId, string $termKey, int $default): int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT terms_json, contract_option_id
                   FROM player_contracts
                  WHERE id = ?
                    AND player_id = ?
                  LIMIT 1"
            );
            $stmt->execute([$contractId, $playerId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($contract)) {
                return $default;
            }

            $terms = json_decode((string)($contract['terms_json'] ?? ''), true);
            if (is_array($terms) && isset($terms[$termKey]) && is_array($terms[$termKey])) {
                return (int)round((float)($terms[$termKey]['value'] ?? $default));
            }

            return $this->currentTermInt((int)$contract['contract_option_id'], $termKey, $default);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractReputationService', 'termInt FAILED', $e, [
                    'contract_id' => $contractId,
                    'term_key' => $termKey,
                ]);
            }
            return $default;
        }
    }

    private function currentTermInt(int $optionId, string $termKey, int $default): int
    {
        if ($optionId <= 0) {
            return $default;
        }
        $stmt = $this->db->prepare(
            "SELECT term_value
               FROM contract_terms
              WHERE contract_option_id = ? AND term_key = ?
              LIMIT 1"
        );
        $stmt->execute([$optionId, $termKey]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (int)round((float)$value);
    }

    private function playerExists(int $playerId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM players WHERE id = ? LIMIT 1');
        $stmt->execute([$playerId]);
        return (bool)$stmt->fetchColumn();
    }

    private function withTransaction(callable $callback): void
    {
        $ownTx = !$this->db->inTransaction();
        try {
            if ($ownTx) {
                $this->db->beginTransaction();
            }
            $callback();
            if ($ownTx) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function clampScore(int $score): int
    {
        return max(self::MIN_SCORE, min(self::MAX_SCORE, $score));
    }

    private function driver(): string
    {
        return (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function nowString(): string
    {
        if ($this->driver() === 'sqlite') {
            return date('Y-m-d H:i:s');
        }
        try {
            $row = $this->db->query('SELECT NOW() AS n')->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? (string)$row['n'] : date('Y-m-d H:i:s');
        } catch (Throwable) {
            return date('Y-m-d H:i:s');
        }
    }
}
