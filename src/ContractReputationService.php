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
        $delta = $this->termInt($contractId, 'reputation_gain_on_delivery', 1);
        $this->changeScore($playerId, $delta, 'delivery_success', $contractId, $delivery);
    }

    /** @param array<string,mixed> $delivery */
    public function onDeliveryMiss(int $playerId, int $contractId, array $delivery): void
    {
        $status = (string)($delivery['status'] ?? 'missed');
        $term = $status === 'partial' ? 'reputation_loss_on_partial_delivery' : 'reputation_loss_on_missed_delivery';
        $delta = $this->termInt($contractId, $term, $status === 'partial' ? -1 : -2);

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
        $delta = $this->termInt($contractId, 'reputation_gain_on_full_completion', 1);
        if ($perfect) {
            $delta += $this->termInt($contractId, 'reputation_gain_on_perfect_contract', 0);
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
        $delta = $this->termInt($contractId, 'reputation_loss_on_contract_failed', -3);
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
        $delta = $this->termInt($contractId, 'reputation_loss_on_cancel', -1);
        $this->withTransaction(function () use ($playerId, $contractId, $delta): void {
            $this->incrementCounters($playerId, [
                'total_contracts' => 1,
                'cancelled_contracts' => 1,
            ]);
            $this->changeScore($playerId, $delta, 'contract_cancelled', $contractId);
        });
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

    private function termInt(int $contractId, string $termKey, int $default): int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT terms_json, contract_option_id
                   FROM player_contracts
                  WHERE id = ?
                  LIMIT 1"
            );
            $stmt->execute([$contractId]);
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
