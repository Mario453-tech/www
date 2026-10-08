-- Run explicitly before deploying region-based well names.
-- Uruchom jawnie przed wdrozeniem nazw odwiertow wedlug regionow.
CREATE TABLE IF NOT EXISTS well_name_counters (
    player_id INT NOT NULL,
    region_id INT NOT NULL,
    last_number INT UNSIGNED NOT NULL,
    PRIMARY KEY (player_id, region_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
