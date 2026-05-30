-- CI schema for GitHub Actions MySQL integration tests.
-- Schemat CI dla MySQL integration testow na GitHub Actions.
--
-- Contains only tables NOT auto-created by service ensureSchema() methods.
-- Zawiera tylko tabele, ktore NIE sa auto-tworzone przez serwisy.
--
-- Auto-created by services (do NOT add here):
--   well_pipelines, well_pipeline_events, well_pipeline_tick_stats (WellPipelineService)
--   well_road_configs, well_road_trips, well_road_incident_logs (road transport service)
--   well_offshore_configs, well_offshore_incident_logs (offshore transport service)
--   ports, port_queue, marine_deliveries (marine transport service)
--   loans (BankService)
--   player_finance_settings, player_finance_decisions (FinancePolicyService)
--   bankruptcy_events (BankruptcyBootstrap)

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------
-- players
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `players` (
  `id`                      INT NOT NULL AUTO_INCREMENT,
  `username`                VARCHAR(50)  NOT NULL DEFAULT '',
  `email`                   VARCHAR(100) NOT NULL DEFAULT '',
  `password_hash`           VARCHAR(255) NOT NULL DEFAULT '',
  `cash`                    DECIMAL(14,2) NOT NULL DEFAULT '0.00',
  `status`                  ENUM('active','bankrupt','banned') NOT NULL DEFAULT 'active',
  `bankruptcy_status`       ENUM('none','restructuring','liquidation','recovered') NOT NULL DEFAULT 'none',
  `bankruptcy_at`           DATETIME NULL DEFAULT NULL,
  `financial_state`         VARCHAR(20) NOT NULL DEFAULT 'normal',
  `crisis_ticks`            INT NOT NULL DEFAULT '0',
  `credit_score`            INT NOT NULL DEFAULT '50',
  `safety_procedures_level` INT NOT NULL DEFAULT '0',
  `procedure_integrity`     INT NOT NULL DEFAULT '100',
  `procedures_last_decay_at` DATETIME NULL DEFAULT NULL,
  `recovery_mode`           TINYINT(1) NOT NULL DEFAULT '0',
  `last_crisis_tick_at`     DATETIME NULL DEFAULT NULL,
  `created_at`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_tick_at`            DATETIME NULL DEFAULT NULL,
  `last_login_at`           DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- wells
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wells` (
  `id`                         INT NOT NULL AUTO_INCREMENT,
  `player_id`                  INT NOT NULL,
  `name`                       VARCHAR(100) NOT NULL DEFAULT '',
  `status`                     VARCHAR(30)  NOT NULL DEFAULT 'active',
  `well_type`                  VARCHAR(20)  NOT NULL DEFAULT 'onshore',
  `transport_type`             VARCHAR(30)  NOT NULL DEFAULT 'rurociag',
  `transport_capacity_pct`     FLOAT        NOT NULL DEFAULT '100',
  `base_production_per_hour`   FLOAT        NOT NULL DEFAULT '0',
  `technical_condition`        FLOAT        NOT NULL DEFAULT '100',
  `risk_score`                 FLOAT        NOT NULL DEFAULT '0',
  `region_id`                  INT          NOT NULL DEFAULT '0',
  `zone_key`                   VARCHAR(10)  NOT NULL DEFAULT 'A1',
  `location_name`              VARCHAR(100) NOT NULL DEFAULT '',
  `hub_outbound_transport_type` ENUM('nieustawiony','rurociag','ciezarowki','tankowiec') NOT NULL DEFAULT 'nieustawiony',
  `created_at`                 DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sold_at`                    DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_player_status` (`player_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- wells_for_sale
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `wells_for_sale` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `player_id`  INT NOT NULL,
  `well_id`    INT NOT NULL,
  `price`      DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- logistics_hubs
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logistics_hubs` (
  `id`                   INT NOT NULL AUTO_INCREMENT,
  `player_id`            INT NOT NULL DEFAULT '0',
  `region_id`            INT NOT NULL DEFAULT '0',
  `zone_key`             VARCHAR(10)   NOT NULL DEFAULT 'A1',
  `name`                 VARCHAR(100)  NOT NULL DEFAULT '',
  `hub_type`             VARCHAR(20)   NOT NULL DEFAULT 'medium',
  `acquisition_type`     VARCHAR(20)   NOT NULL DEFAULT 'new',
  `status`               VARCHAR(20)   NOT NULL DEFAULT 'active',
  `work_mode`            VARCHAR(20)   NOT NULL DEFAULT 'standard',
  `slot_limit`           INT           NOT NULL DEFAULT '4',
  `condition_pct`        DECIMAL(6,2)  NOT NULL DEFAULT '100.00',
  `initial_condition_pct` DECIMAL(6,2) NOT NULL DEFAULT '100.00',
  `wear_level`           DECIMAL(8,4)  NOT NULL DEFAULT '0.0000',
  `efficiency_pct`       DECIMAL(6,2)  NOT NULL DEFAULT '100.00',
  `nominal_capacity_bph` DECIMAL(10,2) NOT NULL DEFAULT '200.00',
  `real_capacity_bph`    DECIMAL(10,2) NOT NULL DEFAULT '200.00',
  `buffer_capacity_bbl`  DECIMAL(10,2) NOT NULL DEFAULT '500.00',
  `buffer_current_bbl`   DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `opex_per_tick`        DECIMAL(10,2) NOT NULL DEFAULT '100.00',
  `lease_fee_per_tick`   DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `build_cost`           DECIMAL(12,2) NOT NULL DEFAULT '100000.00',
  `repair_cost_estimate` DECIMAL(12,2) NOT NULL DEFAULT '200000.00',
  `outbound_transport_type` VARCHAR(30) NULL DEFAULT NULL,
  `last_processed_at`    DATETIME NULL DEFAULT NULL,
  `created_at`           DATETIME NULL DEFAULT NULL,
  `updated_at`           DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_player_status` (`player_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- logistics_hub_assignments
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logistics_hub_assignments` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `hub_id`      INT NOT NULL,
  `well_id`     INT NOT NULL,
  `status`      VARCHAR(20) NOT NULL DEFAULT 'active',
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hub_id` (`hub_id`),
  KEY `well_id` (`well_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- logistics_hub_tick_stats
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logistics_hub_tick_stats` (
  `id`                  INT NOT NULL AUTO_INCREMENT,
  `hub_id`              INT NOT NULL,
  `tick_time`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `input_volume_bbl`    DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `processed_volume_bbl` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `buffered_volume_bbl` DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `lost_volume_bbl`     DECIMAL(12,4) NOT NULL DEFAULT '0.0000',
  `load_pct`            DECIMAL(6,2)  NOT NULL DEFAULT '0.00',
  `condition_before_pct` DECIMAL(6,2) NOT NULL DEFAULT '100.00',
  `condition_after_pct`  DECIMAL(6,2) NOT NULL DEFAULT '100.00',
  `wear_added`          DECIMAL(8,4)  NOT NULL DEFAULT '0.0000',
  `overload_flag`       TINYINT(1)    NOT NULL DEFAULT '0',
  `incident_flag`       TINYINT(1)    NOT NULL DEFAULT '0',
  `created_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `hub_id` (`hub_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- logistics_hub_events
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logistics_hub_events` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `player_id`  INT NOT NULL,
  `hub_id`     INT NOT NULL,
  `well_id`    INT NULL DEFAULT NULL,
  `event_type` VARCHAR(50)  NOT NULL DEFAULT '',
  `severity`   VARCHAR(20)  NOT NULL DEFAULT 'info',
  `title`      VARCHAR(200) NOT NULL DEFAULT '',
  `message`    TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`),
  KEY `hub_id` (`hub_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- pipelines (legacy)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pipelines` (
  `id`               INT NOT NULL AUTO_INCREMENT,
  `player_id`        INT NOT NULL,
  `name`             VARCHAR(100) NOT NULL DEFAULT '',
  `capacity_bbl_h`   FLOAT        NOT NULL DEFAULT '0',
  `condition_pct`    FLOAT        NOT NULL DEFAULT '100',
  `status`           VARCHAR(20)  NOT NULL DEFAULT 'active',
  `last_inspected_at` DATETIME NULL DEFAULT NULL,
  `transport_loss`   FLOAT        NOT NULL DEFAULT '0',
  `built_at`         DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- board_roles
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `board_roles` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `code`        VARCHAR(30)  NOT NULL DEFAULT '',
  `name`        VARCHAR(100) NOT NULL DEFAULT '',
  `slots_limit` INT          NOT NULL DEFAULT '1',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- board_members
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `board_members` (
  `id`                 INT NOT NULL AUTO_INCREMENT,
  `role_id`            INT NOT NULL,
  `player_id`          INT NULL DEFAULT NULL,
  `member_type`        ENUM('director','staff') NOT NULL DEFAULT 'director',
  `status`             VARCHAR(20) NOT NULL DEFAULT 'active',
  `skill_organization` INT NOT NULL DEFAULT '0',
  `skill_negotiation`  INT NOT NULL DEFAULT '0',
  `first_name`         VARCHAR(50) NOT NULL DEFAULT '',
  `last_name`          VARCHAR(50) NOT NULL DEFAULT '',
  `created_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- technical_staff
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `technical_staff` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `player_id`      INT NOT NULL,
  `manager_id`     INT NOT NULL,
  `first_name`     VARCHAR(50)  NOT NULL DEFAULT '',
  `last_name`      VARCHAR(50)  NOT NULL DEFAULT '',
  `spec_code`      VARCHAR(30)  NOT NULL DEFAULT '',
  `specialization` VARCHAR(30)  NOT NULL DEFAULT '',
  `spec_name`      VARCHAR(100) NOT NULL DEFAULT '',
  `skill_level`    INT          NOT NULL DEFAULT '1',
  `salary`         DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `status`         VARCHAR(20)  NOT NULL DEFAULT 'active',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- technical_notifications
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `technical_notifications` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `player_id`  INT NOT NULL,
  `type`       VARCHAR(50)  NOT NULL DEFAULT '',
  `message`    TEXT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT '0',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- technical_tasks
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `technical_tasks` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `player_id`   INT NOT NULL,
  `well_id`     INT NULL DEFAULT NULL,
  `hub_id`      INT NULL DEFAULT NULL,
  `task_type`   VARCHAR(50) NOT NULL DEFAULT 'well_maintenance',
  `status`      VARCHAR(20) NOT NULL DEFAULT 'pending',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- technical_task_queue
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `technical_task_queue` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `player_id`   INT NOT NULL,
  `well_id`     INT NULL DEFAULT NULL,
  `hub_id`      INT NULL DEFAULT NULL,
  `task_type`   VARCHAR(50) NOT NULL DEFAULT 'well_maintenance',
  `priority`    INT NOT NULL DEFAULT '0',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- finance_logs
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_logs` (
  `id`                      INT NOT NULL AUTO_INCREMENT,
  `player_id`               INT NOT NULL,
  `tick_at`                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revenue`                 DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `gross_revenue`           DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `opex`                    DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `salary_cost`             DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `transport_cost`          DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `hub_usage_cost`          DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `incident_cost`           DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `tax`                     DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `loss_bbl`                DECIMAL(14,4) NOT NULL DEFAULT '0.0000',
  `pre_storage_loss_bbl`    DECIMAL(14,4) NOT NULL DEFAULT '0.0000',
  `transport_loss_bbl`      DECIMAL(14,4) NOT NULL DEFAULT '0.0000',
  `transport_event_loss_bbl` DECIMAL(14,4) NOT NULL DEFAULT '0.0000',
  `hub_loss_bbl`            DECIMAL(14,4) NOT NULL DEFAULT '0.0000',
  `fallback_loss_bbl`       DECIMAL(14,4) NOT NULL DEFAULT '0.0000',
  `hub_incident_loss_bbl`   DECIMAL(14,4) NOT NULL DEFAULT '0.0000',
  `loss_value`              DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `hub_loss_value`          DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `fallback_loss_value`     DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `hub_incident_loss_value` DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `net_profit`              DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `cash_after`              DECIMAL(16,2) NOT NULL DEFAULT '0.00',
  `oil_price`               DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `produced_bbl`            DECIMAL(14,4) NOT NULL DEFAULT '0.0000',
  `delivered_bbl`           DECIMAL(14,4) NOT NULL DEFAULT '0.0000',
  `bbl_produced`            DECIMAL(14,4) NOT NULL DEFAULT '0.0000',
  `wells_active`            INT           NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_player_status` (`player_id`,`tick_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- well_config (config lookup used by HubService and others)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `well_config` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `category`    VARCHAR(50)  NOT NULL DEFAULT '',
  `key`         VARCHAR(100) NOT NULL DEFAULT '',
  `value`       TEXT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_key` (`category`,`key`(80))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------
-- market_state (used by BankService for oil price)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `market_state` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `current_price` DECIMAL(10,2) NOT NULL DEFAULT '70.00',
  `trend`         VARCHAR(20)   NOT NULL DEFAULT 'stable',
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `market_state` (`id`, `current_price`, `trend`) VALUES (1, 70.00, 'stable');

-- -----------------------------------------------------------------------
-- storage (used by BankService)
-- -----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `storage` (
  `id`        INT NOT NULL AUTO_INCREMENT,
  `player_id` INT NOT NULL,
  `used`      DECIMAL(14,4) NOT NULL DEFAULT '0.0000',
  `capacity`  DECIMAL(14,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`id`),
  KEY `player_id` (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
