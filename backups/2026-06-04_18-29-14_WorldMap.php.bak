<?php

/**
 * WorldMap - world map service.
 * Handles regions, locations and well purchases via the map.
 */
class WorldMap
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        try {
            $this->db = $db ?? Database::getInstance()->getConnection();
            GameLog::info('WorldMap', 'Service initialized');
        } catch (Throwable $e) {
            GameLog::error('WorldMap', 'Initialization failed', $e);
            throw $e;
        }
    }

    /**
     * Dział prawny P1 — bramka zakupu odwiertów w regionie.
     * Zwraca null, jeśli gracz ma aktywne zezwolenie na wiercenie w regionie
     * (granted/transitional). W przeciwnym razie zwraca gotowy wynik błędu do
     * przekazania graczowi (zakup zablokowany).
     *
     * @return array<string,mixed>|null
     */
    public function regionPurchaseBlock(int $playerId, int $regionId): ?array
    {
        try {
            $legal = new LegalService($this->db);
            if ($legal->hasActivePermit($playerId, $regionId)) {
                return null;
            }
            $region     = $this->getRegion($regionId);
            $regionName = $region['name'] ?? ('#' . $regionId);
            return [
                'success'   => false,
                'message'   => t('legal.err_no_drilling_permit', ['region' => $regionName]),
                'no_permit' => true,
                'region_id' => $regionId,
            ];
        } catch (Throwable $e) {
            GameLog::error('WorldMap', 'regionPurchaseBlock FAILED', $e, [
                'player_id' => $playerId,
                'region_id' => $regionId,
            ]);
            // Fail-closed: przy błędzie bramki blokujemy zakup (zasada nadrzędna P1).
            return [
                'success'   => false,
                'message'   => t('legal.err_no_drilling_permit', ['region' => '#' . $regionId]),
                'no_permit' => true,
                'region_id' => $regionId,
            ];
        }
    }

 // Regions

    public function getRegions(): array
    {
        try {
            return $this->db->query("SELECT * FROM world_regions ORDER BY id")->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('WorldMap', 'getRegions FAILED', $e);
            return [];
        }
    }

    public function getRegion(int $regionId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM world_regions WHERE id = ? LIMIT 1");
            $stmt->execute([$regionId]);
            return $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            GameLog::error('WorldMap', 'getRegion FAILED', $e, ['region_id' => $regionId]);
            return null;
        }
    }

 // Locations

    public function getLocations(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT wl.*, wr.name AS region_name, wr.code AS region_code,
                       wr.tax_rate, wr.political_risk, wr.production_bonus,
                       wr.entry_cost, wr.color_hex, wr.opex_mult,
                       COALESCE(wl.entry_cost_override, wr.entry_cost) AS effective_entry_cost,
                       COALESCE(wl.tax_rate_override,   wr.tax_rate)   AS effective_tax_rate,
                       COALESCE(wl.tier, 'medium')                     AS tier
                FROM world_locations wl
                JOIN world_regions wr ON wr.id = wl.region_id
                WHERE wl.available = 1
                ORDER BY wl.region_id, wl.name
            ");
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('WorldMap', 'getLocations FAILED', $e);
            return [];
        }
    }

    public function getLocation(int $locationId): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT wl.*, wr.name AS region_name, wr.code AS region_code,
                       wr.tax_rate, wr.political_risk, wr.production_bonus,
                       wr.entry_cost, wr.color_hex, wr.opex_mult,
                       COALESCE(wl.entry_cost_override, wr.entry_cost) AS effective_entry_cost,
                       COALESCE(wl.tax_rate_override,   wr.tax_rate)   AS effective_tax_rate,
                       COALESCE(wl.tier, 'medium')                     AS tier
                FROM world_locations wl
                JOIN world_regions wr ON wr.id = wl.region_id
                WHERE wl.id = ? AND wl.available = 1
                LIMIT 1
            ");
            $stmt->execute([$locationId]);
            return $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            GameLog::error('WorldMap', 'getLocation FAILED', $e, ['location_id' => $locationId]);
            return null;
        }
    }

 /**
 * Returns locations occupied by the player (location_id => well data).
 */
    public function getPlayerOccupiedLocations(int $playerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT location_id, id AS well_id, status,
                       base_production_per_hour, technical_condition, level
                FROM wells
                WHERE player_id = ? AND location_id IS NOT NULL
                  AND status != 'sold'
            ");
            $stmt->execute([$playerId]);
            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                $out[(int)$row['location_id']] = [
                    'well_id'    => (int)$row['well_id'],
                    'status'     => $row['status'],
                    'production' => (float)$row['base_production_per_hour'],
                    'condition'  => (float)$row['technical_condition'],
                    'level'      => (int)$row['level'],
                ];
            }
            return $out;
        } catch (Throwable $e) {
            GameLog::error('WorldMap', 'getPlayerOccupiedLocations FAILED', $e, ['player_id' => $playerId]);
            return [];
        }
    }

 // Well purchase

 /**
 * Purchases a well at the chosen map location.
 * Replaces WellShop::buyWell() - players now buy through the map.
 */
    public function buyWellAtLocation(int $playerId, int $locationId): array
    {
        try {
 // Player validation
            $player = new Player($playerId);
            if ($player->isBankrupt()) {
                return ['success' => false, 'message' => t('world_map.err_bankrupt')];
            }

 // Fetch location with region data
            $loc = $this->getLocation($locationId);
            if (!$loc) {
                return ['success' => false, 'message' => t('world_map.err_location_unavailable')];
            }

 // Check if this location is already occupied by anyone
            $occStmt = $this->db->prepare("
                SELECT id FROM wells WHERE location_id = ? AND status NOT IN ('seized','sold') LIMIT 1
            ");
            $occStmt->execute([$locationId]);
            if ($occStmt->fetch()) {
                return ['success' => false, 'message' => t('world_map.err_location_occupied')];
            }

 // Check if the player already has a well at this location
            $ownStmt = $this->db->prepare("
                SELECT id FROM wells WHERE player_id = ? AND location_id = ? LIMIT 1
            ");
            $ownStmt->execute([$playerId, $locationId]);
            if ($ownStmt->fetch()) {
                return ['success' => false, 'message' => t('world_map.err_already_own')];
            }

 // Well limit
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM wells WHERE player_id = ?");
            $countStmt->execute([$playerId]);
            if ((int)$countStmt->fetchColumn() >= 10) {
                return ['success' => false, 'message' => t('world_map.err_well_limit')];
            }

 // Dział prawny P1: bez aktywnego zezwolenia na wiercenie w regionie zakup jest zablokowany.
            $permitBlock = $this->regionPurchaseBlock($playerId, (int)$loc['region_id']);
            if ($permitBlock !== null) {
                return $permitBlock;
            }

 // Cost = effective_entry_cost (override or region) x oil_richness
            $entryBase = (float)$loc['effective_entry_cost'];
            $richness  = (float)$loc['oil_richness'];
            $totalCost = (int)round($entryBase * max(1.0, $richness * 0.8));

            if (!$player->canAfford($totalCost)) {
                return [
                    'success' => false,
                    'message' => t('world_map.err_insufficient_funds', ['cost' => number_format($totalCost, 0, '.', ' ')]),
                    'cost'    => $totalCost,
                ];
            }

 // Calculate well parameters from region and location data
            $prodBonus   = (float)$loc['production_bonus'];
            $taxRate     = (float)$loc['effective_tax_rate']; // override or region default
            $opexMult    = (float)($loc['opex_mult'] ?? 1.0);

 // Base production: onshore 54 bbl/h, offshore 59 bbl/h (x richness x prodBonus)
 // Target: 4 wells -> ~300 bbl/h -> fills 1800 bbl in ~6h
            $baseProduction = $loc['well_type'] === 'offshore'
                ? round(59 * (1 + $prodBonus) * $richness, 2)
                : round(54 * (1 + $prodBonus) * $richness, 2);

 // Base OPEX x regional cost multiplier (logistics, infrastructure)
            $baseUpkeepRaw = $loc['well_type'] === 'offshore' ? 4000.0 : 1458.33;
            $baseUpkeep    = round($baseUpkeepRaw * $opexMult, 2);
            $reservoir     = (int)round(300000 * $richness);
            $transportType = $loc['well_type'] === 'offshore' ? 'tankowiec' : 'nieustawiony';
            $transportProfile = TransportConfigService::getTypeConfig($this->db, $transportType);

            $this->db->beginTransaction();
            try {
                $player->updateCash(-$totalCost);

 // Check if a well was previously sold at this location
 // If so, the reservoir is already partially depleted
                $prevStmt = $this->db->prepare("
                    SELECT reservoir_remaining, reservoir_max
                    FROM wells
                    WHERE location_id = ? AND status = 'sold'
                    ORDER BY sold_at DESC
                    LIMIT 1
                ");
                $prevStmt->execute([$locationId]);
                $prevWell = $prevStmt->fetch();

                if ($prevWell) {
 // Inherit depleted reservoir - use what was left by the previous owner
                    $reservoirRemaining = max(0, (float)$prevWell['reservoir_remaining']);
                    $reservoirMax       = (float)$prevWell['reservoir_max'];
                } else {
 // Fresh reservoir - full resources
                    $reservoirRemaining = $reservoir;
                    $reservoirMax       = $reservoir;
                }

                $this->db->prepare("
                    INSERT INTO wells
                        (player_id, level, status, base_production_per_hour, upkeep_cost_per_hour,
                         well_type, location_id, region_id, regional_tax_rate, region_opex_mult,
                         transport_type, transport_capacity_pct, transport_opex_pct,
                         reservoir_remaining, reservoir_max, pressure,
                         well_name, location_name, depth_m,
                         last_production_at, created_at)
                    VALUES
                        (?, 1, 'paused_staff', ?, ?,
                         ?, ?, ?, ?, ?,
                         ?, ?, ?,
                         ?, ?, 1.00,
                         ?, ?, ?,
                         NOW(), NOW())
                ")->execute([
                    $playerId,
                    $baseProduction, $baseUpkeep,
                    $loc['well_type'], $locationId, $loc['region_id'], $taxRate, $opexMult,
                    $transportType,
                    (float)($transportProfile['capacity'] ?? 0.0),
                    (float)($transportProfile['opex'] ?? 0.0),
                    $reservoirRemaining, $reservoirMax,
                    $loc['name'],
                    $loc['name'],
                    $loc['well_type'] === 'offshore' ? 3500 : 2200,
                ]);

                $wellId = (int)$this->db->lastInsertId();
                $this->db->commit();

                GameLog::info('WorldMap', 'Well purchased via map', [
                    'player_id'   => $playerId,
                    'well_id'     => $wellId,
                    'location_id' => $locationId,
                    'location'    => $loc['name'],
                    'region'      => $loc['region_name'],
                    'cost'        => $totalCost,
                    'production'  => $baseProduction,
                    'tax_rate'    => $taxRate,
                ]);

                $reservoirPct = $reservoirMax > 0
                    ? round(($reservoirRemaining / $reservoirMax) * 100, 0)
                    : 100;
                $reservoirNote = $prevWell && $reservoirPct < 100
                    ? ' ' . t('world_map.msg_reservoir_partial', ['pct' => $reservoirPct])
                    : '';

                return [
                    'success'    => true,
                    'message'    => t('world_map.msg_purchased', ['name' => $loc['name'], 'region' => $loc['region_name']]) . $reservoirNote,
                    'well_id'    => $wellId,
                    'location'   => $loc,
                    'cost'       => $totalCost,
                    'production' => $baseProduction,
                    'reservoir_pct' => $reservoirPct,
                ];

            } catch (Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                GameLog::error('WorldMap', 'buyWellAtLocation TX FAILED', $e, [
                    'player_id'   => $playerId,
                    'location_id' => $locationId,
                ]);
                return ['success' => false, 'message' => t('world_map.err_tx_failed', ['msg' => $e->getMessage()])];
            }

        } catch (Throwable $e) {
            GameLog::error('WorldMap', 'buyWellAtLocation FAILED', $e, [
                'player_id'   => $playerId,
                'location_id' => $locationId,
            ]);
            return ['success' => false, 'message' => t('world_map.err_system')];
        }
    }

 // JSON API

 /**
 * Returns map data for the frontend (regions + locations + player occupancy).
 */
    public function getMapData(int $playerId): array
    {
        try {
            $regions   = $this->getRegions();
            $locations = $this->getLocations();
            $occupied  = $this->getPlayerOccupiedLocations($playerId);

 // Dział prawny P1: status zezwolenia gracza per region (granted/transitional).
 // Pozwala mapie pokazać wymóg zezwolenia ZANIM gracz kliknie zakup.
            $permitByRegion = [];
            try {
                $legal = new LegalService($this->db);
                foreach ($regions as $reg) {
                    $rid = (int)$reg['id'];
                    $permitByRegion[$rid] = $legal->hasActivePermit($playerId, $rid);
                }
            } catch (Throwable $e) {
                GameLog::error('WorldMap', 'getMapData permit status FAILED', $e, ['player_id' => $playerId]);
 // Fail-closed: przy błędzie traktuj wszystkie regiony jako bez zezwolenia.
                foreach ($regions as $reg) {
                    $permitByRegion[(int)$reg['id']] = false;
                }
            }
            foreach ($regions as &$reg) {
                $reg['has_permit'] = $permitByRegion[(int)$reg['id']] ?? false;
            }
            unset($reg);

 // Well count per region
            $wellsPerRegion = [];
            $stmt = $this->db->prepare("
                SELECT region_id, COUNT(*) AS cnt
                FROM wells
                WHERE player_id = ? AND region_id IS NOT NULL AND status NOT IN ('seized','sold')
                GROUP BY region_id
            ");
            $stmt->execute([$playerId]);
            foreach ($stmt->fetchAll() as $row) {
                $wellsPerRegion[(int)$row['region_id']] = (int)$row['cnt'];
            }

 // Attach occupancy flags to each location
            foreach ($locations as &$loc) {
                $locId = (int)$loc['id'];
                $loc['occupied_by_me']       = isset($occupied[$locId]);
                $loc['occupied_by_anyone']   = $this->isLocationOccupied($locId);
                $loc['my_well_id']           = $occupied[$locId]['well_id']    ?? null;
                $loc['my_well_status']       = $occupied[$locId]['status']     ?? null;
                $loc['my_well_production']   = $occupied[$locId]['production'] ?? null;
                $loc['my_well_condition']    = $occupied[$locId]['condition']  ?? null;
                $loc['my_well_level']        = $occupied[$locId]['level']      ?? null;
                $loc['wells_in_region']      = $wellsPerRegion[(int)$loc['region_id']] ?? 0;
            }
            unset($loc);

            return [
                'regions'   => $regions,
                'locations' => $locations,
                'occupied'  => $occupied,
            ];
        } catch (Throwable $e) {
            GameLog::error('WorldMap', 'getMapData FAILED', $e, ['player_id' => $playerId]);
            return ['regions' => [], 'locations' => [], 'occupied' => []];
        }
    }

    private function isLocationOccupied(int $locationId): bool
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM wells WHERE location_id = ? AND status NOT IN ('seized','sold') LIMIT 1
            ");
            $stmt->execute([$locationId]);
            return (bool)$stmt->fetch();
        } catch (Throwable $e) {
            GameLog::error('WorldMap', 'isLocationOccupied FAILED', $e, ['location_id' => $locationId]);
            return false;
        }
    }
}
