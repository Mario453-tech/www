<?php

/**
 * EventsTrait - firing employees and HR event management.
 * PL: EventsTrait - zwalnianie pracownikow i obsluga zdarzen HR.
 */
trait HREventsTrait
{
 // Employee termination actions.
 // PL: Akcje zwiazane ze zwalnianiem pracownikow.

    public function fireEmployee(int $memberId, string $reason = '', int $playerId = 0): array
    {
        if ($playerId <= 0) {
            $playerId = (int)($_SESSION['user_id'] ?? 0);
        }

        $reason = $reason !== '' ? $reason : t('hr_events.default_director_reason');
        $stmt = $this->db->prepare("
            SELECT *
            FROM board_members
            WHERE id = ?
              AND player_id = ?
              AND status = 'active'
              AND member_type = 'staff'
        ");
        $stmt->execute([$memberId, $playerId]);
        $member = $stmt->fetch();

        if (!$member) {
            return ['success' => false, 'message' => t('hr_events.err_employee_missing')];
        }

        // Build the assignment service before the transaction because its bootstrap may create schema.
        // Zbuduj serwis przypisan przed transakcja, poniewaz bootstrap moze tworzyc schemat.
        $assignments = new EmployeeAssignmentService($this->db);
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE board_members SET status = 'fired', fired_at = NOW() WHERE id = ? AND player_id = ?")
                ->execute([$memberId, $playerId]);
            $this->db->prepare(
                "UPDATE employee_contracts ec
                 JOIN board_members bm ON bm.id = ec.member_id
                    SET ec.status = 'terminated'
                  WHERE ec.member_id = ?
                    AND bm.player_id = ?
                    AND ec.status = 'active'"
            )->execute([$memberId, $playerId]);
            $this->db->prepare("INSERT INTO employment_history (member_id, action, reason) VALUES (?, 'fired', ?)")
                ->execute([$memberId, $reason]);
            $assignments->releaseEmployeeAssignments(
                new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, $memberId, $playerId)
            );
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('HRService', 'fireEmployee FAILED', $e, [
                'player_id' => $playerId,
                'member_id' => $memberId,
            ]);
            return ['success' => false, 'message' => t('common.app_error')];
        }

        return [
            'success' => true,
            'message' => t('hr_events.msg_employee_fired', [
                'first' => $member['first_name'],
                'last' => $member['last_name'],
            ]),
        ];
    }

    public function fireTechnicalStaff(int $staffId, int $playerId, string $reason = ''): array
    {
        $reason = $reason !== '' ? $reason : t('hr_events.default_director_reason');
        $stmt = $this->db->prepare("SELECT * FROM technical_staff WHERE id = ? AND player_id = ? AND status IN ('active','busy','on_leave')");
        $stmt->execute([$staffId, $playerId]);
        $staff = $stmt->fetch();

        if (!$staff) {
            return ['success' => false, 'message' => t('hr_events.err_employee_missing')];
        }

        // Build the assignment service before the transaction because its bootstrap may create schema.
        // Zbuduj serwis przypisan przed transakcja, poniewaz bootstrap moze tworzyc schemat.
        $assignments = new EmployeeAssignmentService($this->db);
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE technical_staff SET status = 'fired', fired_at = NOW() WHERE id = ? AND player_id = ?")
                ->execute([$staffId, $playerId]);

            $this->db->prepare("
                UPDATE well_staff_assignments
                SET unassigned_at = NOW()
                WHERE staff_id = ?
                  AND player_id = ?
                  AND unassigned_at IS NULL
            ")->execute([$staffId, $playerId]);

            $this->db->prepare("
                UPDATE wells
                SET operator_id = CASE WHEN operator_id = ? THEN NULL ELSE operator_id END,
                    technician_id = CASE WHEN technician_id = ? THEN NULL ELSE technician_id END
                WHERE player_id = ?
                  AND (operator_id = ? OR technician_id = ?)
            ")->execute([$staffId, $staffId, $playerId, $staffId, $staffId]);
            $assignments->releaseEmployeeAssignments(
                new EmployeeRef(EmployeeRef::SOURCE_TECHNICAL_STAFF, $staffId, $playerId)
            );
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('HRService', 'fireTechnicalStaff FAILED', $e, [
                'player_id' => $playerId,
                'staff_id' => $staffId,
            ]);
            return ['success' => false, 'message' => t('common.app_error')];
        }

        return [
            'success' => true,
            'message' => t('hr_events.msg_employee_fired_plain', [
                'first' => $staff['first_name'],
                'last' => $staff['last_name'],
            ]),
        ];
    }

 // HR events and notifications.
 // PL: Zdarzenia i powiadomienia HR.

    public function createEvent(int $playerId, string $type, string $title, string $message, ?int $memberId): void
    {
        $this->db->prepare("
            INSERT INTO hr_events (player_id, type, title, message, member_id)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$playerId, $type, $title, $message, $memberId]);
    }

    public function getUnreadEvents(int $playerId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM hr_events
            WHERE player_id = ? AND is_read = 0
            ORDER BY created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$playerId]);
        return $stmt->fetchAll();
    }

    public function markEventsRead(int $playerId): void
    {
        $this->db->prepare("UPDATE hr_events SET is_read = 1 WHERE player_id = ?")
            ->execute([$playerId]);
    }

 /**
 * Check contracts that are about to expire and create HR events.
 * PL: Sprawdza kontrakty bliskie konca i tworzy zdarzenia HR.
 */
    public function checkExpiringContracts(?int $playerId = null): void
    {
        $playerFilter = $playerId !== null && $playerId > 0 ? " AND bm.player_id = ?" : "";
        $params = $playerFilter !== "" ? [$playerId] : [];

        $stmt = $this->db->prepare("
            SELECT ec.*, bm.player_id, bm.first_name, bm.last_name, br.name as role_name,
                   DATEDIFF(ec.contract_end, CURDATE()) as days_left
            FROM employee_contracts ec
            JOIN board_members bm ON ec.member_id = bm.id
            JOIN board_roles br ON bm.role_id = br.id
            WHERE ec.status = 'active'
              AND ec.contract_end BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
              {$playerFilter}
        ");
        $stmt->execute($params);
        $expiring = $stmt->fetchAll();

        foreach ($expiring as $contract) {
            $exists = $this->db->prepare("
                SELECT id FROM hr_events
                WHERE member_id = ? AND player_id = ? AND type = 'contract_expiring'
                  AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $eventPlayerId = (int)$contract['player_id'];
            $exists->execute([$contract['member_id'], $eventPlayerId]);
            if ($exists->fetch()) {
                continue;
            }

            $this->createEvent(
                $eventPlayerId,
                'contract_expiring',
                t('hr_events.contract_expiring_title', [
                    'first' => $contract['first_name'],
                    'last' => $contract['last_name'],
                ]),
                t('hr_events.contract_expiring_message', [
                    'days'  => $contract['days_left'],
                    'first' => $contract['first_name'],
                    'last'  => $contract['last_name'],
                    'role'  => $contract['role_name'],
                ]),
                $contract['member_id']
            );
        }
    }
}
