<?php

class Player
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
                GameLog::error('Player', '__construct failed', $e, ['player_id' => $playerId]);
            }
            throw $e;
        }
    }

 /** @return array<string, mixed>|false|null */
    public function getData(): array|false|null
    {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, COALESCE(s.capacity, 0) AS capacity, COALESCE(s.used, 0) AS used
                FROM players p
                LEFT JOIN storage s ON p.id = s.player_id
                WHERE p.id = :id
            ");
            $stmt->execute([':id' => $this->playerId]);
            $row = $stmt->fetch();
            if (class_exists('GameLog', false)) {
                GameLog::dbResult('Player', 'getData', $row ? 1 : 0, $row ? 'found' : 'not_found');
            }
            return $row;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Player', 'getData failed', $e, ['player_id' => $this->playerId]);
            }
            return null;
        }
    }

    // $type - opcjonalny typ transakcji do audit trail (etap 8).
    // $type - optional transaction type for the audit trail (stage 8).
    public function updateCash(float $amount, string $type = '', ?string $description = null): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE players
                SET cash = cash + :amount
                WHERE id = :id
            ");
            $ok = $stmt->execute([':amount' => $amount, ':id' => $this->playerId]);
            if (class_exists('GameLog', false)) {
                GameLog::dbResult('Player', 'updateCash', $stmt->rowCount(), $ok ? 'OK' : 'FAIL');
            }
            // Audit trail - loguj transakcje jesli podano typ (etap 8).
            // Audit trail - log transaction when type is provided (stage 8).
            if ($ok && $type !== '' && class_exists('FinancialTransactionService', false)) {
                try {
                    $abs = abs($amount);
                    $from = $amount < 0 ? $this->playerId : null;
                    $to   = $amount >= 0 ? $this->playerId : null;
                    (new FinancialTransactionService($this->db))->logTransaction($from, $to, $abs, $type, $description);
                } catch (Throwable $le) {
                    if (class_exists('GameLog', false)) {
                        GameLog::warn('Player', 'updateCash audit trail failed', ['error' => $le->getMessage()]);
                    }
                }
            }
            return $ok;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Player', 'updateCash failed', $e, [
                    'player_id' => $this->playerId,
                    'amount'    => $amount,
                ]);
            }
            return false;
        }
    }

    public function isBankrupt(): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT status, bankruptcy_at,
                       COALESCE(recovery_mode, 0) AS recovery_mode,
                       COALESCE(bankruptcy_status, 'none') AS bankruptcy_status
                FROM players
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $this->playerId]);
            $row = $stmt->fetch();

            if (!$row) {
                return false;
            }

            $status = (string)($row['status'] ?? 'active');
            $bankruptcyStatus = (string)($row['bankruptcy_status'] ?? 'none');

 // Considered bankrupt when:
 // - players.status = 'bankrupt'
 // - OR recovery_mode = 1 (restructuring in progress)
 // - OR bankruptcy_status is an active process (restructuring/liquidation)
 // We do NOT block on bankruptcy_at - it is a historical timestamp
 // that persists after the player exits bankruptcy (recovered).
            return $status === 'bankrupt'
                || (int)($row['recovery_mode'] ?? 0) === 1
                || ($bankruptcyStatus !== 'none' && $bankruptcyStatus !== 'recovered');
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Player', 'isBankrupt failed', $e, ['player_id' => $this->playerId]);
            }
            return false;
        }
    }

    public function getBankruptcyBlockMessage(): string
    {
        return t('player.bankruptcy_block_msg');
    }

    public function canAfford(float $cost): bool
    {
        try {
            if ($this->isBankrupt()) {
                if (class_exists('GameLog', false)) {
                    GameLog::warn('Player', 'Blocked spending during bankruptcy', [
                        'player_id' => $this->playerId,
                        'cost' => $cost,
                    ]);
                }
                return false;
            }

            $data = $this->getData();
            return isset($data['cash']) && $data['cash'] >= $cost;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Player', 'canAfford failed', $e, [
                    'player_id' => $this->playerId,
                    'cost' => $cost,
                ]);
            }
            return false;
        }
    }

    public function getCash(): float
    {
        try {
            $data = $this->getData();
            return (float)($data['cash'] ?? 0);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Player', 'getCash failed', $e, ['player_id' => $this->playerId]);
            }
            return 0.0;
        }
    }
}
