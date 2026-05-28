-- ============================================================
-- Etap 6: Logistyka v2 — build timery, kursy drogowe,
--         przepiecie z timerem, hub_id w dostawach morskich,
--         rozszerzone incydenty
--
-- Uruchamiac jednorazowo. Przed uruchomieniem sprawdz
-- ze baza jest po migracji etap3..etap5.
--
-- Kolejnosc blokow jest wazna — nie przestawiac.
-- ============================================================

-- ============================================================
-- BLOK 1: well_pipelines — build timer + nowe statusy
--
-- Dodaje statusy: planned, building, leak, suspended
-- Dodaje kolumny: build_started_at, build_finish_at
-- ============================================================

-- Jesli tabela bazowa jeszcze nie istnieje, utworz ja najpierw.
CREATE TABLE IF NOT EXISTS well_pipelines (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    well_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    pipeline_type VARCHAR(32) NOT NULL DEFAULT 'standard',
    status ENUM(
        'planned',
        'building',
        'active',
        'degraded',
        'critical',
        'damaged',
        'leak',
        'suspended',
        'disabled'
    ) NOT NULL DEFAULT 'active',
    condition_pct DECIMAL(6,2) NOT NULL DEFAULT 100.00,
    transport_loss DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    nominal_capacity_bph DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    real_capacity_bph DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    degradation_rate_per_hour DECIMAL(8,4) NOT NULL DEFAULT 0.0500,
    incident_risk_mult DECIMAL(8,4) NOT NULL DEFAULT 1.0000,
    opex_per_tick DECIMAL(12,2) NOT NULL DEFAULT 140.00,
    opex_per_bbl DECIMAL(12,4) NOT NULL DEFAULT 0.2500,
    build_cost DECIMAL(12,2) NOT NULL DEFAULT 18000.00,
    build_started_at DATETIME DEFAULT NULL,
    build_finish_at DATETIME DEFAULT NULL,
    last_inspected_at DATETIME NULL DEFAULT NULL,
    last_maintenance_at DATETIME NULL DEFAULT NULL,
    damaged_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_well_pipeline_well (well_id),
    KEY idx_well_pipeline_player (player_id),
    KEY idx_well_pipeline_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rozszerz enum statusow (nowe wartosci na poczatku listy)
ALTER TABLE well_pipelines
    MODIFY COLUMN status ENUM(
        'planned',
        'building',
        'active',
        'degraded',
        'critical',
        'damaged',
        'leak',
        'suspended',
        'disabled'
    ) NOT NULL DEFAULT 'active';

-- Dodaj timer budowy — bezpiecznie przez information_schema
SET @q = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'well_pipelines'
       AND COLUMN_NAME  = 'build_started_at') = 0,
    'ALTER TABLE well_pipelines
        ADD COLUMN build_started_at DATETIME DEFAULT NULL AFTER build_cost,
        ADD COLUMN build_finish_at  DATETIME DEFAULT NULL AFTER build_started_at',
    'SELECT ''well_pipelines: build timer columns already exist'' AS info'
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- Indeks do szybkiego wyciagania rurociągow w budowie
SET @q = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'well_pipelines'
       AND INDEX_NAME   = 'idx_pipeline_build_finish') = 0,
    'ALTER TABLE well_pipelines
        ADD INDEX idx_pipeline_build_finish (status, build_finish_at)',
    'SELECT ''index already exists'' AS info'
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- ============================================================
-- BLOK 2: well_road_trips — kursy drogowe jako byty w czasie
--
-- Odpowiada za: odwiert -> hub transport drogowy
-- Kazdy kurs ma departure_at, eta_at, status, incydent
-- ============================================================

CREATE TABLE IF NOT EXISTS well_road_trips (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    player_id     INT              NOT NULL,
    well_id       INT              NOT NULL,
    hub_id        BIGINT UNSIGNED  DEFAULT NULL COMMENT 'hub docelowy (FK logistics_hubs.id)',
    volume_bbl    DECIMAL(12,4)    NOT NULL DEFAULT 0.0000,
    truck_type    ENUM('standard','heavy','armored') NOT NULL DEFAULT 'standard',
    status        ENUM('in_transit','delayed','lost','delivered') NOT NULL DEFAULT 'in_transit',
    departure_at  DATETIME         NOT NULL,
    eta_at        DATETIME         NOT NULL,
    arrived_at    DATETIME         DEFAULT NULL,
    incident_type ENUM('theft','raid','accident','sabotage','route_block') DEFAULT NULL,
    cost          DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_road_trips_player  (player_id),
    KEY idx_road_trips_well    (well_id),
    KEY idx_road_trips_hub     (hub_id),
    KEY idx_road_trips_status  (status),
    KEY idx_road_trips_eta     (eta_at),
    KEY idx_road_trips_active  (status, eta_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Road transport trips: well -> hub (time-based)';

-- ============================================================
-- BLOK 3: logistics_hub_assignments — timer przepiecia
--
-- Status 'relinking' juz istnieje, ale brakuje kolumn
-- relink_started_at i relink_finish_at (gracz widzi czas)
-- ============================================================

SET @q = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'logistics_hub_assignments'
       AND COLUMN_NAME  = 'relink_started_at') = 0,
    'ALTER TABLE logistics_hub_assignments
        ADD COLUMN relink_started_at DATETIME DEFAULT NULL AFTER cooldown_until,
        ADD COLUMN relink_finish_at  DATETIME DEFAULT NULL AFTER relink_started_at',
    'SELECT ''logistics_hub_assignments: relink timer columns already exist'' AS info'
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- Indeks do wyciagania aktywnych przepieczen
SET @q = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'logistics_hub_assignments'
       AND INDEX_NAME   = 'idx_assign_relink_finish') = 0,
    'ALTER TABLE logistics_hub_assignments
        ADD INDEX idx_assign_relink_finish (status, relink_finish_at)',
    'SELECT ''index already exists'' AS info'
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- ============================================================
-- BLOK 4: marine_deliveries — dodaj hub_id
--
-- Aktualnie dostawa morska ma tylko port_id.
-- hub_id pozwala graczowi widziec do jakiego huba plynie ropa
-- i laczy dostawe z logistyka hubowa.
-- ============================================================

SET @q = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'marine_deliveries'
       AND COLUMN_NAME  = 'hub_id') = 0,
    'ALTER TABLE marine_deliveries
        ADD COLUMN hub_id BIGINT UNSIGNED DEFAULT NULL
            COMMENT ''hub docelowy (FK logistics_hubs.id)''
            AFTER port_id,
        ADD INDEX idx_marine_hub (hub_id)',
    'SELECT ''marine_deliveries.hub_id already exists'' AS info'
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- ============================================================
-- BLOK 5: well_offshore_incident_logs — nowe typy incydentow
--
-- Dodaje: cataclysm (kataklizm), sabotage (sabotaz)
-- ============================================================

ALTER TABLE well_offshore_incident_logs
    MODIFY COLUMN incident_type ENUM(
        'storm',
        'breakdown',
        'delay',
        'piracy',
        'cataclysm',
        'sabotage'
    ) NOT NULL;

-- ============================================================
-- BLOK 6: pipelines (hub -> magazyn) — build timer + statusy
--
-- Stary model: jeden rurociag per gracza (Rurociag glowny).
-- Dodajemy stany budowy i timer aby odpowiadal README pkt 9.
-- ============================================================

ALTER TABLE pipelines
    MODIFY COLUMN status ENUM(
        'planned',
        'building',
        'active',
        'damaged',
        'leak',
        'paused'
    ) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active';

SET @q = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'pipelines'
       AND COLUMN_NAME  = 'build_started_at') = 0,
    'ALTER TABLE pipelines
        ADD COLUMN build_started_at DATETIME DEFAULT NULL AFTER built_at,
        ADD COLUMN build_finish_at  DATETIME DEFAULT NULL AFTER build_started_at',
    'SELECT ''pipelines: build timer columns already exist'' AS info'
);
PREPARE _stmt FROM @q; EXECUTE _stmt; DEALLOCATE PREPARE _stmt;

-- ============================================================
-- BLOK 7: hub_road_trips — kursy ciezarowek hub -> magazyn
--
-- Analog well_road_trips ale dla odcinka hub -> storage.
-- README pkt 9.2: ten etap tez trwa i ma incydenty.
-- ============================================================

CREATE TABLE IF NOT EXISTS hub_road_trips (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    player_id     INT              NOT NULL,
    hub_id        BIGINT UNSIGNED  NOT NULL COMMENT 'FK logistics_hubs.id',
    volume_bbl    DECIMAL(12,4)    NOT NULL DEFAULT 0.0000,
    truck_type    ENUM('standard','heavy','armored') NOT NULL DEFAULT 'standard',
    status        ENUM('in_transit','delayed','lost','delivered') NOT NULL DEFAULT 'in_transit',
    departure_at  DATETIME         NOT NULL,
    eta_at        DATETIME         NOT NULL,
    arrived_at    DATETIME         DEFAULT NULL,
    incident_type ENUM('theft','raid','accident','sabotage','route_block') DEFAULT NULL,
    cost          DECIMAL(10,2)    NOT NULL DEFAULT 0.00,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hub_trips_player  (player_id),
    KEY idx_hub_trips_hub     (hub_id),
    KEY idx_hub_trips_status  (status),
    KEY idx_hub_trips_eta     (eta_at),
    KEY idx_hub_trips_active  (status, eta_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Road transport trips: hub -> storage (time-based)';

-- ============================================================
-- KONIEC MIGRACJI
-- Sprawdz w phpMyAdmin ze ponizsze tabele/kolumny istnieja:
--   well_pipelines.build_started_at
--   well_pipelines.build_finish_at
--   well_pipelines.status IN ('planned','building','leak','suspended')
--   well_road_trips (nowa tabela)
--   logistics_hub_assignments.relink_started_at
--   logistics_hub_assignments.relink_finish_at
--   marine_deliveries.hub_id
--   well_offshore_incident_logs.incident_type IN ('cataclysm','sabotage')
--   pipelines.build_started_at
--   pipelines.build_finish_at
--   pipelines.status IN ('planned','building','leak','paused')
--   hub_road_trips (nowa tabela)
-- ============================================================
