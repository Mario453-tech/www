<?php

// Legacy strike API used by HR actions and the compatibility tick section.
// PL: Starsze API strajkow uzywane przez akcje HR i zgodnosciowa sekcje ticka.
class StrikeService
{
    /**
     * Rozpoczyna strajk pracownika (Start strike)
     */
    public static function startStrike(int $staffId, string $reason): void
    {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            INSERT INTO staff_strikes (technical_staff_id, start_time, reason) 
            SELECT ?, NOW(), ?
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM staff_strikes 
                WHERE technical_staff_id = ? AND end_time IS NULL
            )
        ");
        $stmt->execute([$staffId, $reason, $staffId]);
    }

    /**
     * Zwraca true jesli pracownik aktualnie strajkuje (end_time IS NULL)
     */
    public static function isStriking(int $staffId): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT 1 FROM staff_strikes WHERE technical_staff_id = ? AND end_time IS NULL LIMIT 1");
        $stmt->execute([$staffId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Rozwiazuje strajk (ustawia end_time na NOW)
     */
    public static function resolveStrike(int $staffId): void
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE staff_strikes SET end_time = NOW() WHERE technical_staff_id = ? AND end_time IS NULL");
        $stmt->execute([$staffId]);
    }

    /**
     * Pobiera aktywne strajki dla wszystkich pracownikow gracza
     * Zwraca tablice: [technical_staff_id => [reason, start_time]]
     *
     * @return array<int,array{reason:mixed,start_time:mixed}>
     */
    public static function getActiveStrikes(int $playerId): array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT s.technical_staff_id, s.reason, s.start_time
            FROM staff_strikes s
            JOIN technical_staff ts ON ts.id = s.technical_staff_id
            WHERE ts.player_id = ? AND s.end_time IS NULL
        ");
        $stmt->execute([$playerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
        $strikes = [];
        foreach ($rows as $row) {
            $strikes[(int)$row['technical_staff_id']] = [
                'reason' => $row['reason'],
                'start_time' => $row['start_time']
            ];
        }
        
        return $strikes;
    }
}
