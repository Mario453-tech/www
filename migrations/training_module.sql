-- ============================================================
-- MIGRACJA: training_module.sql
-- Cel: system szkolen pracownikow (dział techniczny + zarzad).
-- Data: 2026-06-22
-- MySQL 8.0 compatible.
-- UWAGA: uruchom jednorazowo.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Programy szkoleniowe (konfiguracja kursow)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `training_programs` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code`           VARCHAR(60)                      NOT NULL,
    `department`     ENUM('technical','board')        NOT NULL,
    `target_skill`   VARCHAR(40)                      NOT NULL,
    `name_pl`        VARCHAR(120)                     NOT NULL,
    `name_en`        VARCHAR(120)                     NOT NULL,
    `duration_hours` SMALLINT UNSIGNED                NOT NULL DEFAULT 24,
    `cost`           INT UNSIGNED                     NOT NULL DEFAULT 0,
    `base_pass_rate` TINYINT UNSIGNED                 NOT NULL DEFAULT 70
                     COMMENT '0-100 percent chance to pass exam',
    `enabled`        TINYINT(1)                       NOT NULL DEFAULT 1,
    `created_at`     DATETIME                         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_code` (`code`),
    INDEX `idx_dept`    (`department`),
    INDEX `idx_skill`   (`target_skill`),
    INDEX `idx_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Sub-skille pracownikow technicznych
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `technical_staff_skills` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `staff_id`    INT UNSIGNED NOT NULL,
    `skill_code`  VARCHAR(40)  NOT NULL,
    `skill_level` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                  ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_staff_skill` (`staff_id`, `skill_code`),
    INDEX `idx_staff` (`staff_id`),
    CONSTRAINT `fk_tss_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `technical_staff`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. Szkolenia pracownikow (historia + aktywne)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staff_trainings` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `player_id`      INT UNSIGNED                                          NOT NULL,
    `staff_type`     ENUM('technical','board')                             NOT NULL,
    `staff_id`       INT UNSIGNED                                          NOT NULL,
    `program_id`     INT UNSIGNED                                          NOT NULL,
    `status`         ENUM('in_progress','passed','failed','cancelled')     NOT NULL DEFAULT 'in_progress',
    `started_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `finishes_at`    DATETIME     NOT NULL,
    `exam_score`     TINYINT UNSIGNED NULL     COMMENT 'Wynik egzaminu 1-100',
    `exam_pass_min`  TINYINT UNSIGNED NULL     COMMENT 'Prog zaliczenia (100 - pass_chance)',
    `retry_count`    TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `cooldown_until` DATETIME     NULL         COMMENT 'Blokada ponownej proby po oblaniu',
    `skill_before`   TINYINT UNSIGNED NULL,
    `skill_after`    TINYINT UNSIGNED NULL,
    `cost_paid`      INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX `idx_st_player`   (`player_id`),
    INDEX `idx_st_staff`    (`staff_type`, `staff_id`),
    INDEX `idx_st_status`   (`status`),
    INDEX `idx_st_finishes` (`finishes_at`),
    CONSTRAINT `fk_st_program`
        FOREIGN KEY (`program_id`) REFERENCES `training_programs`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. Rozszerzenie employee_certificates o kolumny dla szkolen
-- ------------------------------------------------------------
ALTER TABLE `employee_certificates`
    ADD COLUMN IF NOT EXISTS `staff_type`  ENUM('technical','board') NULL
        COMMENT 'Typ pracownika (technical_staff lub board_members)'
        AFTER `member_id`,
    ADD COLUMN IF NOT EXISTS `training_id` INT UNSIGNED NULL
        COMMENT 'Referencja do staff_trainings'
        AFTER `staff_type`,
    ADD COLUMN IF NOT EXISTS `score`       TINYINT UNSIGNED NULL
        COMMENT 'Wynik egzaminu ktory przyznał certyfikat'
        AFTER `training_id`;

-- ------------------------------------------------------------
-- 5. Dane seed — programy szkoleniowe
-- ------------------------------------------------------------
INSERT IGNORE INTO `training_programs`
    (`code`, `department`, `target_skill`, `name_pl`, `name_en`, `duration_hours`, `cost`, `base_pass_rate`)
VALUES
    ('tech_drilling_basic',
     'technical', 'skill_drilling',
     'Kurs wiercenia poziom I', 'Drilling Course Level I',
     48, 15000, 85),

    ('tech_drilling_advanced',
     'technical', 'skill_drilling',
     'Kurs wiercenia poziom II', 'Drilling Course Level II',
     72, 30000, 65),

    ('tech_maintenance_basic',
     'technical', 'skill_maintenance',
     'Utrzymanie ruchu poziom I', 'Maintenance Level I',
     36, 12000, 80),

    ('tech_maintenance_advanced',
     'technical', 'skill_maintenance',
     'Utrzymanie ruchu poziom II', 'Maintenance Level II',
     60, 24000, 62),

    ('tech_safety_basic',
     'technical', 'skill_safety',
     'Szkolenie BHP poziom I', 'Safety Training Level I',
     24, 8000, 90),

    ('tech_safety_advanced',
     'technical', 'skill_safety',
     'Szkolenie BHP poziom II', 'Safety Training Level II',
     48, 18000, 70),

    ('tech_analysis_basic',
     'technical', 'skill_analysis',
     'Analiza geologiczna poziom I', 'Geological Analysis Level I',
     60, 20000, 70),

    ('tech_analysis_advanced',
     'technical', 'skill_analysis',
     'Analiza geologiczna poziom II', 'Geological Analysis Level II',
     80, 38000, 55),

    ('board_negotiation_basic',
     'board', 'skill_negotiation',
     'Negocjacje poziom I', 'Negotiation Course Level I',
     48, 25000, 80),

    ('board_negotiation_advanced',
     'board', 'skill_negotiation',
     'Negocjacje poziom II', 'Negotiation Course Level II',
     72, 45000, 60),

    ('board_ethics_basic',
     'board', 'skill_ethics',
     'Etyka biznesu poziom I', 'Business Ethics Level I',
     36, 18000, 85),

    ('board_stress_basic',
     'board', 'skill_stress',
     'Zarzadzanie stresem poziom I', 'Stress Management Level I',
     24, 15000, 88),

    ('board_organization_basic',
     'board', 'skill_organization',
     'Zarzadzanie i organizacja poziom I', 'Organization & Management Level I',
     48, 22000, 78),

    ('board_analysis_basic',
     'board', 'skill_analysis',
     'Analiza biznesowa poziom I', 'Business Analysis Level I',
     60, 28000, 72);
