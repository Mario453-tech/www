<?php
declare(strict_types=1);

/**
 * ContractReputationService — wskaznik reputacji kontraktowej (0-100).
 * ContractReputationService — contract reputation indicator (0-100).
 *
 * Wynik domyslny: 50 (brak historii). Zakres: 0-100.
 * Default score: 50 (no history). Range: 0-100.
 *
 * Reguly zdarzen (z termsow podpisanego kontraktu):
 * Event rules (from signed contract terms snapshot):
 *   reputation_gain_on_delivery          — pelna dostawa
 *   reputation_gain_on_perfect_contract  — kontrakt bez braków
 *   reputation_loss_on_partial_delivery  — czesciowa dostawa (ujemny lub 0)
 *   reputation_loss_on_missed_delivery   — brak dostawy (ujemny lub 0)
 *   reputation_loss_on_contract_failed   — kontrakt nieudany (ujemny lub 0)
 *   reputation_loss_on_cancel            — anulowanie kontraktu (ujemny lub 0)
 */
class ContractReputationService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getScore(int $playerId): int
    {
        try {
            $stmt = $this->db->prepare("SELECT score FROM contract_reputation WHERE player_id = ? LIMIT 1");
            $stmt->execute([$playerId]);
            $val = $stmt->fetchColumn();
            return $val === false ? 50 : max(0, min(100, (int)$val));
        } catch (Throwable) {
            return 50;
        }
    }

    /** @return array{score:int,total_contracts:int,completed_contracts:int,failed_contracts:int,cancelled_contracts:int,missed_deliveries:int,perfect_contracts:int} */
    public function getStats(int $playerId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM contract_reputation WHERE player_id = ? LIMIT 1");
            $stmt->execute([$playerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $row = false;
        }
        return [
            'score'               => $row !== false ? max(0, min(100, (int)$row['score'])) : 50,
            'total_contracts'     => $row !== false ? (int)$row['total_contracts'] : 0,
            'completed_contracts' => $row !== false ? (int)$row['completed_contracts'] : 0,
            'failed_contracts'    => $row !== false ? (int)$row['failed_contracts'] : 0,
            'cancelled_contracts' => $row !== false ? (int)$row['cancelled_contracts'] : 0,
            'missed_deliveries'   => $row !== false ? (int)$row['missed_deliveries'] : 0,
            'perfect_contracts'   => $row !== false ? (int)$row['perfect_contracts'] : 0,
        ];
    }

    public function ensureRow(int $playerId): void
    {
        try {
            if ($this->driver() === 'sqlite') {
                $this->db->prepare(
                    "INSERT OR IGNORE INTO contract_reputation (player_id, score) VALUES (?, 50)"
                )->execute([$playerId]);
            } else {
                $this->db->prepare(
                    "INSERT IGNORE INTO contract_reputation (player_id, score) VALUES (?, 50)"
                )->execute([$playerId]);
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractReputationService', 'ensureRow FAILED', $e, ['player_id' => $playerId]);
            }
        }
    }

    /**
     * Zmienia wynik reputacji o $delta i loguje zmiane.
     * Changes the reputation score by $delta and logs the change.
     *
     * @param array<string,mixed> $meta
     */
    public function changeScore(int $playerId, int $delta, string $reason, ?int $contractId = null, array $meta = []): void
    {
        if ($delta === 0) {
            return;
        }
        try {
            $this->ensureRow($playerId);
            $now = $this->nowString();
            $this->db->prepare(
                "UPDATE contract_reputation
                 SET score = LEAST(100, GREATEST(0, score + ?)), updated_at = ?
                 WHERE player_id = ?"
            )->execute([$delta, $now, $playerId]);
            $scoreAfter = $this->getScore($playerId);
            $this->db->prepare(
                "INSERT INTO contract_reputation_log
                    (player_id, contract_id, delta, score_after, reason, message, meta_json, created_at)
                 VALUES (?, ?, ?, ?, ?, '', ?, ?)"
            )->execute([
                $playerId,
                $contractId,
                $delta,
                $scoreAfter,
                $reason,
                $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $now,
            ]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractReputationService', 'changeScore FAILED', $e, [
                    'player_id'   => $playerId,
                    'contract_id' => $contractId,
                    'delta'       => $delta,
                    'reason'      => $reason,
                ]);
            }
        }
    }

    /**
     * Rejestruje podpisanie nowego kontraktu (inkrementuje total_contracts).
     * Registers a new contract signing (increments total_contracts).
     */
    public function onContractSigned(int $playerId): void
    {
        try {
            $this->ensureRow($playerId);
            $this->db->prepare(
                "UPDATE contract_reputation SET total_contracts = total_contracts + 1, updated_at = ? WHERE player_id = ?"
            )->execute([$this->nowString(), $playerId]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractReputationService', 'onContractSigned FAILED', $e, ['player_id' => $playerId]);
            }
        }
    }

    /**
     * Zdarzenie pelnej dostawy (missedBbl = 0).
     * Full delivery event (missedBbl = 0).
     *
     * @param array<string,array{type:string,value:float,text:?string}> $terms
     */
    public function onDeliverySuccess(int $playerId, int $contractId, array $terms): void
    {
        $delta = (int)($terms['reputation_gain_on_delivery']['value'] ?? 0);
        if ($delta !== 0) {
            $this->changeScore($playerId, $delta, 'delivery_success', $contractId);
        }
    }

    /**
     * Zdarzenie czesciowej dostawy (deliveredBbl > 0, missedBbl > 0).
     * Partial delivery event.
     *
     * @param array<string,array{type:string,value:float,text:?string}> $terms
     */
    public function onDeliveryPartial(int $playerId, int $contractId, array $terms): void
    {
        $this->ensureRow($playerId);
        $delta = (int)($terms['reputation_loss_on_partial_delivery']['value'] ?? 0);
        if ($delta !== 0) {
            $this->changeScore($playerId, $delta, 'delivery_partial', $contractId);
        }
        try {
            $this->db->prepare(
                "UPDATE contract_reputation SET missed_deliveries = missed_deliveries + 1, updated_at = ? WHERE player_id = ?"
            )->execute([$this->nowString(), $playerId]);
        } catch (Throwable) {
        }
    }

    /**
     * Zdarzenie brakujacej dostawy (deliveredBbl = 0, missedBbl > 0).
     * Missed delivery event.
     *
     * @param array<string,array{type:string,value:float,text:?string}> $terms
     */
    public function onDeliveryMiss(int $playerId, int $contractId, array $terms): void
    {
        $this->ensureRow($playerId);
        $delta = (int)($terms['reputation_loss_on_missed_delivery']['value'] ?? 0);
        if ($delta !== 0) {
            $this->changeScore($playerId, $delta, 'delivery_missed', $contractId);
        }
        try {
            $this->db->prepare(
                "UPDATE contract_reputation SET missed_deliveries = missed_deliveries + 1, updated_at = ? WHERE player_id = ?"
            )->execute([$this->nowString(), $playerId]);
        } catch (Throwable) {
        }
    }

    /**
     * Zdarzenie zakonczenia kontraktu (status = completed).
     * Contract completed event.
     *
     * @param array<string,array{type:string,value:float,text:?string}> $terms
     */
    public function onContractCompleted(int $playerId, int $contractId, bool $perfect, array $terms): void
    {
        $this->ensureRow($playerId);
        if ($perfect) {
            $delta = (int)($terms['reputation_gain_on_perfect_contract']['value'] ?? 0);
            if ($delta !== 0) {
                $this->changeScore($playerId, $delta, 'contract_perfect', $contractId);
            }
        }
        try {
            $now = $this->nowString();
            if ($perfect) {
                $this->db->prepare(
                    "UPDATE contract_reputation
                     SET completed_contracts = completed_contracts + 1, perfect_contracts = perfect_contracts + 1, updated_at = ?
                     WHERE player_id = ?"
                )->execute([$now, $playerId]);
            } else {
                $this->db->prepare(
                    "UPDATE contract_reputation SET completed_contracts = completed_contracts + 1, updated_at = ? WHERE player_id = ?"
                )->execute([$now, $playerId]);
            }
        } catch (Throwable) {
        }
    }

    /**
     * Zdarzenie nieudanego kontraktu (status = failed).
     * Contract failed event.
     *
     * @param array<string,array{type:string,value:float,text:?string}> $terms
     */
    public function onContractFailed(int $playerId, int $contractId, array $terms): void
    {
        $this->ensureRow($playerId);
        $delta = (int)($terms['reputation_loss_on_contract_failed']['value'] ?? 0);
        if ($delta !== 0) {
            $this->changeScore($playerId, $delta, 'contract_failed', $contractId);
        }
        try {
            $this->db->prepare(
                "UPDATE contract_reputation SET failed_contracts = failed_contracts + 1, updated_at = ? WHERE player_id = ?"
            )->execute([$this->nowString(), $playerId]);
        } catch (Throwable) {
        }
    }

    /**
     * Zdarzenie anulowania kontraktu przez gracza.
     * Contract cancelled by player event.
     *
     * @param array<string,array{type:string,value:float,text:?string}> $terms
     */
    public function onContractCancelled(int $playerId, int $contractId, array $terms): void
    {
        $this->ensureRow($playerId);
        $delta = (int)($terms['reputation_loss_on_cancel']['value'] ?? 0);
        if ($delta !== 0) {
            $this->changeScore($playerId, $delta, 'contract_cancelled', $contractId);
        }
        try {
            $this->db->prepare(
                "UPDATE contract_reputation SET cancelled_contracts = cancelled_contracts + 1, updated_at = ? WHERE player_id = ?"
            )->execute([$this->nowString(), $playerId]);
        } catch (Throwable) {
        }
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
            $row = $this->db->query("SELECT NOW() AS n")->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? (string)$row['n'] : date('Y-m-d H:i:s');
        } catch (Throwable) {
            return date('Y-m-d H:i:s');
        }
    }
}
