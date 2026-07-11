CREATE TABLE IF NOT EXISTS `tick_module_config` (
  `module_key` varchar(64) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `interval_ticks` int unsigned NOT NULL DEFAULT 1,
  `max_items_per_run` int unsigned NOT NULL DEFAULT 200,
  `last_run_tick` bigint unsigned NOT NULL DEFAULT 0,
  `last_run_at` datetime DEFAULT NULL,
  `last_duration_ms` int unsigned DEFAULT NULL,
  `last_status` varchar(16) NOT NULL DEFAULT 'never',
  `last_error` varchar(500) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`module_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tick_module_run_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `module_key` varchar(64) NOT NULL,
  `tick_sequence` bigint unsigned NOT NULL DEFAULT 0,
  `source` varchar(32) NOT NULL,
  `status` varchar(16) NOT NULL,
  `duration_ms` int unsigned NOT NULL DEFAULT 0,
  `stats_json` json DEFAULT NULL,
  `error_message` varchar(500) DEFAULT NULL,
  `forced` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tick_module_logs_key_created` (`module_key`, `created_at`),
  KEY `idx_tick_module_logs_sequence` (`tick_sequence`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
