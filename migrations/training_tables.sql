-- ============================================================
-- Migracja: tabele systemu szkolen pracownikow
-- Migration: employee training system tables
-- Uruchom na az.pl przez phpMyAdmin -> zakladka SQL
-- Run on az.pl via phpMyAdmin -> SQL tab
-- ============================================================

-- 1. Katalog programow szkolen (konfiguracja przez admina)
CREATE TABLE IF NOT EXISTS `training_programs` (
  `id`             int(10) unsigned     NOT NULL AUTO_INCREMENT,
  `code`           varchar(60)          NOT NULL,
  `department`     enum('technical','board') NOT NULL,
  `target_skill`   varchar(40)          NOT NULL,
  `name_pl`        varchar(120)         NOT NULL,
  `name_en`        varchar(120)         NOT NULL,
  `duration_hours` smallint(5) unsigned NOT NULL DEFAULT 24,
  `cost`           int(10) unsigned     NOT NULL DEFAULT 0,
  `base_pass_rate` tinyint(3) unsigned  NOT NULL DEFAULT 70
      COMMENT '0-100 percent chance to pass exam',
  `enabled`        tinyint(1)           NOT NULL DEFAULT 1,
  `created_at`     datetime             NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_code`    (`code`),
  KEY        `idx_dept`   (`department`),
  KEY        `idx_skill`  (`target_skill`),
  KEY        `idx_enabled`(`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Historia i aktywne szkolenia graczy
CREATE TABLE IF NOT EXISTS `staff_trainings` (
  `id`            int(10) unsigned     NOT NULL AUTO_INCREMENT,
  `player_id`     int(10) unsigned     NOT NULL,
  `staff_type`    enum('technical','board') NOT NULL,
  `staff_id`      int(10) unsigned     NOT NULL,
  `program_id`    int(10) unsigned     NOT NULL,
  `status`        enum('in_progress','passed','failed','cancelled')
                      NOT NULL DEFAULT 'in_progress',
  `started_at`    datetime             NOT NULL DEFAULT current_timestamp(),
  `finishes_at`   datetime             NOT NULL,
  `exam_score`    tinyint(3) unsigned  DEFAULT NULL
      COMMENT 'Wynik egzaminu 1-100',
  `exam_pass_min` tinyint(3) unsigned  DEFAULT NULL
      COMMENT 'Prog zaliczenia (100 - pass_chance)',
  `retry_count`   tinyint(3) unsigned  NOT NULL DEFAULT 0,
  `cooldown_until` datetime            DEFAULT NULL
      COMMENT 'Blokada ponownej proby po oblaniu',
  `skill_before`  tinyint(3) unsigned  DEFAULT NULL,
  `skill_after`   tinyint(3) unsigned  DEFAULT NULL,
  `cost_paid`     int(10) unsigned     NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_st_player`   (`player_id`),
  KEY `idx_st_staff`    (`staff_type`, `staff_id`),
  KEY `idx_st_status`   (`status`),
  KEY `idx_st_finishes` (`finishes_at`),
  KEY `fk_st_program`   (`program_id`),
  CONSTRAINT `fk_st_program`
      FOREIGN KEY (`program_id`) REFERENCES `training_programs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Dane startowe: 14 programow szkolen (technical + board)
INSERT IGNORE INTO `training_programs`
    (code, department, target_skill, name_pl, name_en, duration_hours, cost, base_pass_rate, enabled)
VALUES
    ('tech_drilling_basic',      'technical', 'skill_drilling',     'Kurs wiercenia poziom I',              'Drilling Course Level I',          48, 15000, 85, 1),
    ('tech_drilling_advanced',   'technical', 'skill_drilling',     'Kurs wiercenia poziom II',             'Drilling Course Level II',         72, 30000, 65, 1),
    ('tech_maintenance_basic',   'technical', 'skill_maintenance',  'Utrzymanie ruchu poziom I',            'Maintenance Level I',              36, 12000, 80, 1),
    ('tech_maintenance_advanced','technical', 'skill_maintenance',  'Utrzymanie ruchu poziom II',           'Maintenance Level II',             60, 24000, 62, 1),
    ('tech_safety_basic',        'technical', 'skill_safety',       'Szkolenie BHP poziom I',               'Safety Training Level I',          24,  8000, 90, 1),
    ('tech_safety_advanced',     'technical', 'skill_safety',       'Szkolenie BHP poziom II',              'Safety Training Level II',         48, 18000, 70, 1),
    ('tech_analysis_basic',      'technical', 'skill_analysis',     'Analiza geologiczna poziom I',         'Geological Analysis Level I',      60, 20000, 70, 1),
    ('tech_analysis_advanced',   'technical', 'skill_analysis',     'Analiza geologiczna poziom II',        'Geological Analysis Level II',     80, 38000, 55, 1),
    ('board_negotiation_basic',  'board',     'skill_negotiation',  'Negocjacje poziom I',                  'Negotiation Course Level I',       48, 25000, 80, 1),
    ('board_negotiation_advanced','board',    'skill_negotiation',  'Negocjacje poziom II',                 'Negotiation Course Level II',      72, 45000, 60, 1),
    ('board_ethics_basic',       'board',     'skill_ethics',       'Etyka biznesu poziom I',               'Business Ethics Level I',          36, 18000, 85, 1),
    ('board_stress_basic',       'board',     'skill_stress',       'Zarzadzanie stresem poziom I',         'Stress Management Level I',        24, 15000, 88, 1),
    ('board_organization_basic', 'board',     'skill_organization', 'Zarzadzanie i organizacja poziom I',   'Organization & Management Level I',48, 22000, 78, 1),
    ('board_analysis_basic',     'board',     'skill_analysis',     'Analiza biznesowa poziom I',           'Business Analysis Level I',        60, 28000, 72, 1);
