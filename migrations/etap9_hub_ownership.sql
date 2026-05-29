-- Etap 9: Hub ownership columns — 2026-05-29
-- Adds private ownership support to logistics_hubs.
-- player_id = 0  : hub is on the marketplace (system hub, available to buy or rent)
-- player_id > 0  : hub is owned exclusively by that player
-- tenant_player_id > 0 : a player is currently renting a market hub (player_id = 0)
-- These columns are also added idempotently via HubService::ensureHubSchema() at runtime.

ALTER TABLE `logistics_hubs`
    ADD COLUMN IF NOT EXISTS `tenant_player_id`  BIGINT UNSIGNED NOT NULL DEFAULT 0
        COMMENT 'Player currently renting this market hub; 0 = no active tenant'
        AFTER `player_id`,
    ADD COLUMN IF NOT EXISTS `acquisition_price` DECIMAL(14,2) NOT NULL DEFAULT 0.00
        COMMENT 'Price paid by owner when acquiring this hub'
        AFTER `build_cost`,
    ADD COLUMN IF NOT EXISTS `acquired_at`       DATETIME NULL DEFAULT NULL
        COMMENT 'When the hub was acquired by its current owner/tenant'
        AFTER `acquisition_price`;
