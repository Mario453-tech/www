<?php

final class LegacyMoraleService
{
    /**
     * Zmiana morale z logowaniem (Change morale with logging)
     * Morale zostaje obciete do zakresu [0, 100]
     */
    public static function modifyMorale(int $staffId, int $amount, string $reason): void
    {
        if ($amount === 0) {
            return;
        }

        $db = Database::getInstance()->getConnection();

        $db->beginTransaction();
        try {
            // Pobierz obecne wartosci
            $stmt = $db->prepare("SELECT current_morale FROM technical_staff WHERE id = ? FOR UPDATE");
            $stmt->execute([$staffId]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                $db->rollBack();
                return;
            }

            $currentMorale = (int)$staff['current_morale'];
            $newMorale = $currentMorale + $amount;
            
            // Morale jest ograniczone do [0, 100]
            if ($newMorale < 0) {
                $newMorale = 0;
            } elseif ($newMorale > 100) {
                $newMorale = 100;
            }

            $realAmount = $newMorale - $currentMorale;
            
            if ($realAmount !== 0) {
                $updStmt = $db->prepare("UPDATE technical_staff SET current_morale = ? WHERE id = ?");
                $updStmt->execute([$newMorale, $staffId]);

                $logStmt = $db->prepare("INSERT INTO staff_morale_logs (technical_staff_id, change_amount, reason) VALUES (?, ?, ?)");
                $logStmt->execute([$staffId, $realAmount, $reason]);
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            if (class_exists('GameLog', false)) {
                GameLog::error('HR', 'Failed to modify morale', $e);
            }
            throw $e;
        }
    }

    /**
     * Zwraca historie zmian morale pracownika
     */
    public static function getMoraleHistory(int $staffId, int $limit = 20): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT change_amount, reason, created_at 
            FROM staff_morale_logs 
            WHERE technical_staff_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->bindValue(1, $staffId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
