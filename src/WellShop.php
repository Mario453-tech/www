<?php

class WellShop
{
    private PDO $db;

    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            GameLog::info('WellShop', 'Service initialized');
        } catch (Throwable $e) {
            GameLog::error('WellShop', 'Initialization failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getAvailableWells(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT * FROM wells_for_sale
                WHERE available = TRUE
                ORDER BY base_cost ASC
            ");
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('WellShop', 'Failed to fetch available wells', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getWell(int $wellId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM wells_for_sale
                WHERE id = :id AND available = TRUE
            ");
            $stmt->execute([':id' => $wellId]);
            return $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            GameLog::error('WellShop', 'Failed to fetch well', [
                'well_id' => $wellId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function buyWell(int $playerId, int $wellId): array
    {
        try {
            $player = new Player($playerId);
            if ($player->isBankrupt()) {
                return [
                    'success' => false,
                    'message' => t('well_shop.err_bankrupt'),
                ];
            }

            $well = $this->getWell($wellId);
            if (!$well) {
                return ['success' => false, 'message' => t('well_shop.err_not_available')];
            }

            if (!$player->canAfford((float)$well['base_cost'])) {
                return ['success' => false, 'message' => t('well_shop.err_no_funds')];
            }

            $wellCount = $this->db->prepare("SELECT COUNT(*) as count FROM wells WHERE player_id = :player_id");
            $wellCount->execute([':player_id' => $playerId]);
            $count = (int)($wellCount->fetch()['count'] ?? 0);

            if ($count >= 5) {
                return ['success' => false, 'message' => t('well_shop.err_max_wells')];
            }

            $this->db->beginTransaction();
            try {
                $player->updateCash(-(float)$well['base_cost']);
                $transportProfile = TransportConfigService::getTypeConfig($this->db, 'nieustawiony');

                $insertWell = $this->db->prepare("
                    INSERT INTO wells (
                        player_id, level, status, base_production_per_hour, upkeep_cost_per_hour,
                        transport_type, transport_capacity_pct, transport_opex_pct,
                        last_production_at, created_at
                    )
                    VALUES (
                        :player_id, 1, 'active', :production, :upkeep,
                        :transport_type, :transport_capacity_pct, :transport_opex_pct,
                        NOW(), NOW()
                    )
                ");

                $insertWell->execute([
                    ':player_id' => $playerId,
                    ':production' => $well['base_production'],
                    ':upkeep' => $well['upkeep_cost'],
                    ':transport_type' => 'nieustawiony',
                    ':transport_capacity_pct' => (float)($transportProfile['capacity'] ?? 0.0),
                    ':transport_opex_pct' => (float)($transportProfile['opex'] ?? 0.0),
                ]);

                $this->db->commit();

                GameLog::info('WellShop', 'Well purchased', [
                    'player_id' => $playerId,
                    'well_id' => $wellId,
                    'cost' => (float)$well['base_cost'],
                ]);

                return [
                    'success' => true,
                    'message' => t('well_shop.msg_bought', ['location' => $well['location_name']]),
                    'well_data' => $well,
                ];
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                GameLog::error('WellShop', 'Well purchase transaction failed', [
                    'player_id' => $playerId,
                    'well_id' => $wellId,
                    'error' => $e->getMessage(),
                ]);
                return ['success' => false, 'message' => t('common.app_error')];
            }
        } catch (Throwable $e) {
            GameLog::error('WellShop', 'Unexpected buyWell failure', [
                'player_id' => $playerId,
                'well_id' => $wellId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => t('common.app_error')];
        }
    }

    public function getPlayerWellCount(int $playerId): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM wells WHERE player_id = :player_id");
            $stmt->execute([':player_id' => $playerId]);
            return (int)($stmt->fetch()['count'] ?? 0);
        } catch (Throwable $e) {
            GameLog::error('WellShop', 'Failed to count player wells', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
            return 0;
        }
    }
}
