-- ============================================================
-- Etap 5+6: Dostawy morskie w czasie + porty systemowe
-- Tables: ports, marine_deliveries, port_queue
-- Each block is idempotent (CREATE TABLE IF NOT EXISTS).
-- ============================================================

-- ============================================================
-- ports — systemowe punkty odbioru ropy z transportu morskiego.
-- ports — system-owned oil reception points for marine transport.
-- ============================================================
CREATE TABLE IF NOT EXISTS ports (
    id                      INT             NOT NULL AUTO_INCREMENT PRIMARY KEY,
    region_id               INT             NOT NULL,
    name                    VARCHAR(120)    NOT NULL,
    port_type               ENUM('small','medium','large') NOT NULL DEFAULT 'medium',
    throughput_per_tick     DECIMAL(12,2)   NOT NULL DEFAULT 500.00,   -- max bbl handled per tick
    queue_limit             INT             NOT NULL DEFAULT 20,        -- max deliveries in queue
    handling_cost_per_bbl   DECIMAL(8,4)    NOT NULL DEFAULT 0.50,     -- PLN per bbl
    base_transit_hours      DECIMAL(6,2)    NOT NULL DEFAULT 3.00,     -- base delivery time in hours
    overload_risk_pct       DECIMAL(5,2)    NOT NULL DEFAULT 15.00,    -- chance of delay when overloaded
    failure_risk_per_tick   DECIMAL(8,6)    NOT NULL DEFAULT 0.001000, -- chance of port failure per tick
    status                  ENUM('active','overloaded','damaged','closed') NOT NULL DEFAULT 'active',
    created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_ports_region (region_id),
    KEY idx_ports_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- marine_deliveries — rejsy tankowcow (ropa w drodze morskiej).
-- marine_deliveries — tanker voyages (oil in transit by sea).
-- ============================================================
CREATE TABLE IF NOT EXISTS marine_deliveries (
    id              BIGINT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    player_id       INT             NOT NULL,
    well_id         INT             NOT NULL,
    port_id         INT             NULL DEFAULT NULL,           -- assigned port (set at departure or on arrival)
    volume_bbl      DECIMAL(12,4)   NOT NULL DEFAULT 0.0000,    -- oil volume sent
    status          ENUM('departing','in_transit','waiting_for_port','processing','delivered','delayed','lost')
                                    NOT NULL DEFAULT 'departing',
    departure_at    DATETIME        NOT NULL,
    eta_at          DATETIME        NOT NULL,                   -- estimated time of arrival
    arrived_at      DATETIME        NULL DEFAULT NULL,          -- actual arrival at port
    delivered_at    DATETIME        NULL DEFAULT NULL,          -- storage credit time
    delay_ticks     SMALLINT        NOT NULL DEFAULT 0,
    incident_type   VARCHAR(64)     NULL DEFAULT NULL,          -- storm / piracy / breakdown / NULL
    handling_cost   DECIMAL(12,2)   NOT NULL DEFAULT 0.00,      -- port handling fee paid
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_marine_player   (player_id),
    KEY idx_marine_well     (well_id),
    KEY idx_marine_port     (port_id),
    KEY idx_marine_status   (status),
    KEY idx_marine_eta      (eta_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- port_queue — kolejka dostaw oczekujacych na obsluge w porcie.
-- port_queue — deliveries waiting to be processed by a port.
-- ============================================================
CREATE TABLE IF NOT EXISTS port_queue (
    id                      BIGINT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    port_id                 INT             NOT NULL,
    delivery_id             BIGINT          NOT NULL,
    player_id               INT             NOT NULL,
    volume_bbl              DECIMAL(12,4)   NOT NULL,
    queued_at               DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processing_started_at   DATETIME        NULL DEFAULT NULL,
    processed_at            DATETIME        NULL DEFAULT NULL,
    status                  ENUM('waiting','processing','done','abandoned') NOT NULL DEFAULT 'waiting',
    UNIQUE KEY uq_port_queue_delivery (delivery_id),
    KEY idx_port_queue_port     (port_id),
    KEY idx_port_queue_player   (player_id),
    KEY idx_port_queue_status   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
