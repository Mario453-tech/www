<?php

class Storage
{
    private PDO $db;
    private int $playerId;

    public function __construct(int $playerId)
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            $this->playerId = $playerId;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Storage', '__construct failed', $e, ['player_id' => $playerId]);
            }
            throw $e;
        }
    }

    /** @return array<string, mixed> */
    public function getData(): array
    {
        $default = ['player_id' => $this->playerId, 'capacity' => 0, 'used' => 0];
        try {
            $stmt = $this->db->prepare("SELECT * FROM storage WHERE player_id = :player_id");
            $stmt->execute([':player_id' => $this->playerId]);
            $row = $stmt->fetch();
            if (!$row) {
                // No storage record found — auto-create it
                try {
                    $this->db->prepare("INSERT IGNORE INTO storage (player_id, capacity, used, updated_at) VALUES (?, 1200, 0, NOW())")
                        ->execute([$this->playerId]);
                    GameLog::info('Storage', 'Auto-created storage record', ['player_id' => $this->playerId]);
                } catch (Throwable $insertE) {
                    GameLog::error('Storage', 'Auto-create storage FAILED', $insertE, ['player_id' => $this->playerId]);
                }
                return array_merge($default, ['capacity' => 1200]);
            }
            return $row;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Storage', 'getData failed', $e, ['player_id' => $this->playerId]);
            }
            return $default;
        }
    }

    public function addOil(float $amount): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE storage
                SET used = LEAST(used + :amount, capacity)
                WHERE player_id = :player_id
            ");
            $ok = $stmt->execute([
                ':amount'    => $amount,
                ':player_id' => $this->playerId,
            ]);
            if (class_exists('GameLog', false)) {
                GameLog::dbResult('Storage', 'addOil', $stmt->rowCount(), $ok ? 'OK' : 'FAIL');
            }
            return $ok;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Storage', 'addOil failed', $e, [
                    'player_id' => $this->playerId,
                    'amount'    => $amount,
                ]);
            }
            return false;
        }
    }

    public function sellAll(float $price): float|int
    {
        try {
            $storage = $this->getData();

            if (!$storage || $storage['used'] == 0) {
                GameLog::info('Storage', 'sellAll — no oil to sell', ['player_id' => $this->playerId]);
                return 0;
            }

            $oilAmount = $storage['used'];
            $earnings  = $oilAmount * $price;

            $stmt = $this->db->prepare("
                UPDATE storage
                SET used = 0, updated_at = NOW()
                WHERE player_id = :player_id
            ");
            $ok = $stmt->execute([':player_id' => $this->playerId]);
            if (class_exists('GameLog', false)) {
                GameLog::dbResult('Storage', 'sellAll', $stmt->rowCount(), $ok ? 'OK' : 'FAIL');
            }
            return $earnings;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Storage', 'sellAll failed', $e, [
                    'player_id' => $this->playerId,
                    'price'     => $price,
                ]);
            }
            return 0;
        }
    }

    public function upgrade(): bool
    {
        try {
            $player = new Player($this->playerId);
            if ($player->isBankrupt()) {
                GameLog::warn('Storage', 'Blocked storage upgrade during bankruptcy', [
                    'player_id' => $this->playerId,
                ]);
                return false;
            }

            $stmt = $this->db->prepare("
                UPDATE storage
                SET capacity = capacity + 100
                WHERE player_id = :player_id
            ");
            return $stmt->execute([':player_id' => $this->playerId]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Storage', 'upgrade failed', $e, ['player_id' => $this->playerId]);
            }
            return false;
        }
    }

    public function getUpgradeCost(): int|false
    {
        try {
            $storage = $this->getData();
            if (!$storage) {
                return false;
            }
            return $storage['capacity'] * 50;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Storage', 'getUpgradeCost failed', $e, ['player_id' => $this->playerId]);
            }
            return false;
        }
    }
}