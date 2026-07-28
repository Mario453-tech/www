<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Employee/EmployeeRef.php';

final class EmployeeRelationLifecycleService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function deactivate(EmployeeRef $ref, DateTimeInterface $now): void
    {
        $this->leaveOpenStrikes($ref, $now);
        $this->db->prepare(
            "UPDATE employee_state
                SET relation_status='inactive', workload=0, inactive_at=?,
                    version=version+1, updated_at=CURRENT_TIMESTAMP
              WHERE player_id=? AND source_type=? AND source_id=?"
        )->execute([$now->format('Y-m-d H:i:s'), $ref->playerId, $ref->sourceType, $ref->sourceId]);
    }

    public function leaveOpenStrikes(EmployeeRef $ref, DateTimeInterface $now): void
    {
        $stmt = $this->db->prepare(
            'SELECT strike_id FROM employee_strike_members
              WHERE player_id=? AND source_type=? AND source_id=? AND left_at IS NULL'
        );
        $stmt->execute([$ref->playerId, $ref->sourceType, $ref->sourceId]);
        $strikeIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if ($strikeIds === []) {
            return;
        }
        $this->db->prepare(
            'UPDATE employee_strike_members SET left_at=?
              WHERE player_id=? AND source_type=? AND source_id=? AND left_at IS NULL'
        )->execute([$now->format('Y-m-d H:i:s'), $ref->playerId, $ref->sourceType, $ref->sourceId]);

        $close = $this->db->prepare(
            "UPDATE employee_strikes
                SET status='resolved', open_key=NULL, resolved_at=?, updated_at=CURRENT_TIMESTAMP
              WHERE id=? AND player_id=? AND open_key IS NOT NULL
                AND NOT EXISTS (
                    SELECT 1 FROM employee_strike_members sm
                     WHERE sm.strike_id=employee_strikes.id
                       AND sm.player_id=employee_strikes.player_id
                       AND sm.left_at IS NULL
                )"
        );
        $closeNegotiation = $this->db->prepare(
            "UPDATE employee_strike_negotiations
                SET status='failed', updated_at=CURRENT_TIMESTAMP
              WHERE strike_id=? AND player_id=? AND status='open'"
        );
        foreach ($strikeIds as $strikeId) {
            $close->execute([$now->format('Y-m-d H:i:s'), $strikeId, $ref->playerId]);
            if ($close->rowCount() === 1) {
                $closeNegotiation->execute([$strikeId, $ref->playerId]);
            }
        }
    }
}
