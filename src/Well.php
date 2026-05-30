<?php

class Well
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
                GameLog::error('Well', '__construct failed', $e, ['player_id' => $playerId]);
            }
            throw $e;
        }
    }

 /** @return array<int, array<string, mixed>> */
    public function getWells(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT w.*,
                       wl.oil_richness,
                       wl.description          AS location_description,
                       wr.name                 AS region_name,
                       wr.code                 AS region_code,
                       wr.color_hex            AS region_color,
                       wr.political_risk       AS region_political_risk,
                       wr.production_bonus     AS region_production_bonus,
                       wr.tax_rate             AS region_tax_rate,
                       wr.opex_mult            AS region_opex_mult,
                       wr.stability_bonus      AS region_stability_bonus
                FROM wells w
                LEFT JOIN world_locations wl ON wl.id = w.location_id
                LEFT JOIN world_regions   wr ON wr.id = w.region_id
                WHERE w.player_id = :player_id AND w.status != 'sold'
                ORDER BY w.id
            ");
            $stmt->execute([':player_id' => $this->playerId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Well', 'getWells failed', $e, ['player_id' => $this->playerId]);
            }
            return [];
        }
    }

 /** @return array<string, mixed>|false|null */
    public function getWell(int $wellId): array|false|null
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM wells
                WHERE id = :id AND player_id = :player_id
            ");
            $stmt->execute([':id' => $wellId, ':player_id' => $this->playerId]);
            return $stmt->fetch();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Well', 'getWell failed', $e, [
                    'player_id' => $this->playerId,
                    'well_id' => $wellId,
                ]);
            }
            return null;
        }
    }

    public function upgrade(int $wellId): bool
    {
        try {
            $player = new Player($this->playerId);
            if ($player->isBankrupt()) {
                GameLog::warn('Well', 'Blocked well upgrade during bankruptcy', [
                    'player_id' => $this->playerId,
                    'well_id' => $wellId,
                ]);
                return false;
            }

            $stmt = $this->db->prepare("
                UPDATE wells
                SET level = level + 1,
                    base_production_per_hour = base_production_per_hour + 20,
                    upkeep_cost_per_hour = upkeep_cost_per_hour + 5
                WHERE id = :id AND player_id = :player_id
            ");
            return $stmt->execute([':id' => $wellId, ':player_id' => $this->playerId]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Well', 'upgrade failed', $e, [
                    'player_id' => $this->playerId,
                    'well_id' => $wellId,
                ]);
            }
            return false;
        }
    }

    public function getUpgradeCost(int $wellId): int|false
    {
        try {
            $well = $this->getWell($wellId);
            if (!$well) {
                return false;
            }
            return $well['level'] * 10000;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('Well', 'getUpgradeCost failed', $e, [
                    'player_id' => $this->playerId,
                    'well_id' => $wellId,
                ]);
            }
            return false;
        }
    }
}
