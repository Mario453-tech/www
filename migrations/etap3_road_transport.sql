-- ============================================================
-- Etap 3: Transport drogowy jako kursy
-- Tabele: well_road_configs, well_road_incident_logs
-- ============================================================

CREATE TABLE IF NOT EXISTS well_road_configs (
    id                  INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_id           INT           NOT NULL,
    well_id             INT           NOT NULL,
    truck_type          ENUM('standard','heavy','armored') NOT NULL DEFAULT 'standard',
    trip_capacity_bbl   DECIMAL(10,2) NOT NULL DEFAULT 25.00,
    cost_per_trip       DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    incident_risk_mult  DECIMAL(6,3)  NOT NULL DEFAULT 1.000,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_road_cfg_well  (well_id),
    KEY        idx_road_cfg_player (player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS well_road_incident_logs (
    id            INT                                                          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    well_id       INT                                                          NOT NULL,
    player_id     INT                                                          NOT NULL,
    incident_type ENUM('theft','raid','accident','sabotage','route_block')    NOT NULL,
    trips_total   SMALLINT UNSIGNED                                            NOT NULL DEFAULT 0,
    trips_lost    SMALLINT UNSIGNED                                            NOT NULL DEFAULT 0,
    vol_lost_bbl  DECIMAL(12,4)                                               NOT NULL DEFAULT 0.0000,
    created_at    DATETIME                                                     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_road_inc_well    (well_id),
    KEY idx_road_inc_player  (player_id),
    KEY idx_road_inc_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
