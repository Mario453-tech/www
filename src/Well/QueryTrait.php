<?php
trait WellQueryTrait
{
    /** @return array<string, mixed>|null */
    public function getWell(int $wellId, int $playerId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT w.*,
                       GROUP_CONCAT(wu.upgrade_type) AS installed_upgrades
                FROM wells w
                LEFT JOIN well_upgrades wu ON wu.well_id = w.id
                WHERE w.id = ? AND w.player_id = ?
                GROUP BY w.id
            ");
            $stmt->execute([$wellId, $playerId]);
            return $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('WellService', 'getWell FAILED', $e, ['well_id' => $wellId, 'player_id' => $playerId]);
            }
            return null;
        }
    }

    /** @return list<array<string, mixed>> */
    public function getPlayerWells(int $playerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT w.*,
                       GROUP_CONCAT(wu.upgrade_type) AS installed_upgrades
                FROM wells w
                LEFT JOIN well_upgrades wu ON wu.well_id = w.id
                WHERE w.player_id = ? AND w.status != 'sold'
                GROUP BY w.id
                ORDER BY w.id
            ");
            $stmt->execute([$playerId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('WellService', 'getPlayerWells FAILED', $e, ['player_id' => $playerId]);
            }
            return [];
        }
    }

    /** @return list<array<string, mixed>> */
    public function getWellEvents(int $wellId, int $limit = 20): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM well_events
                WHERE well_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$wellId, $limit]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('WellService', 'getWellEvents FAILED', $e, ['well_id' => $wellId]);
            }
            return [];
        }
    }

    /** @return list<string> */
    private function getInstalledUpgrades(int $wellId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT upgrade_type FROM well_upgrades WHERE well_id = ?");
            $stmt->execute([$wellId]);
            return array_column($stmt->fetchAll(), 'upgrade_type');
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('WellService', 'getInstalledUpgrades FAILED', $e, ['well_id' => $wellId]);
            }
            return [];
        }
    }
}