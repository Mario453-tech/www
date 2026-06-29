<?php

/**
 * One-time training schema bootstrap — creates tables + seeds programs.
 * PL: Jednorazowy bootstrap schematu szkolen — tworzy tabele i seeduje programy.
 *
 * Called from init.php after all services are loaded.
 * PL: Wywolywane z init.php po zaladowaniu wszystkich serwisow.
 */

if (!function_exists('ensureTrainingSchema')) {
    function ensureTrainingSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            $db = Database::getInstance()->getConnection();

            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                return;
            }

            // Katalog programow szkolen (konfiguracja przez admina).
            // Training program catalog (configured by admin).
            $db->exec("CREATE TABLE IF NOT EXISTS `training_programs` (
                `id`             INT(10) UNSIGNED     NOT NULL AUTO_INCREMENT,
                `code`           VARCHAR(60)          NOT NULL,
                `department`     ENUM('technical','board') NOT NULL,
                `target_skill`   VARCHAR(40)          NOT NULL,
                `name_pl`        VARCHAR(120)         NOT NULL,
                `name_en`        VARCHAR(120)         NOT NULL,
                `duration_hours` SMALLINT(5) UNSIGNED NOT NULL DEFAULT 24,
                `cost`           INT(10) UNSIGNED     NOT NULL DEFAULT 0,
                `base_pass_rate` TINYINT(3) UNSIGNED  NOT NULL DEFAULT 70
                    COMMENT '0-100 percent chance to pass exam',
                `enabled`        TINYINT(1)           NOT NULL DEFAULT 1,
                `created_at`     DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_code`     (`code`),
                KEY        `idx_dept`    (`department`),
                KEY        `idx_skill`   (`target_skill`),
                KEY        `idx_enabled` (`enabled`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Kolumna active_guard — atomowa blokada podwojnego rozpatrzenia egzaminu.
            // active_guard column — atomic lock preventing double exam processing.
            Database::addColumnIfMissing('staff_trainings', 'active_guard',
                "TINYINT(1) NULL DEFAULT NULL COMMENT 'NULL = zamkniety, 1 = w toku / NULL = closed, 1 = in progress'"
            );

            // Historia i aktywne szkolenia graczy.
            // Player training history and active trainings.
            $db->exec("CREATE TABLE IF NOT EXISTS `staff_trainings` (
                `id`             INT(10) UNSIGNED     NOT NULL AUTO_INCREMENT,
                `player_id`      INT(10) UNSIGNED     NOT NULL,
                `staff_type`     ENUM('technical','board') NOT NULL,
                `staff_id`       INT(10) UNSIGNED     NOT NULL,
                `program_id`     INT(10) UNSIGNED     NOT NULL,
                `status`         ENUM('in_progress','passed','failed','cancelled')
                                     NOT NULL DEFAULT 'in_progress',
                `started_at`     DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `finishes_at`    DATETIME             NOT NULL,
                `exam_score`     TINYINT(3) UNSIGNED  DEFAULT NULL
                                     COMMENT 'Wynik egzaminu 1-100',
                `exam_pass_min`  TINYINT(3) UNSIGNED  DEFAULT NULL
                                     COMMENT 'Prog zaliczenia (100 - pass_chance)',
                `retry_count`    TINYINT(3) UNSIGNED  NOT NULL DEFAULT 0,
                `cooldown_until` DATETIME             DEFAULT NULL
                                     COMMENT 'Blokada ponownej proby po oblaniu',
                `skill_before`   TINYINT(3) UNSIGNED  DEFAULT NULL,
                `skill_after`    TINYINT(3) UNSIGNED  DEFAULT NULL,
                `cost_paid`      INT(10) UNSIGNED     NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_st_player`   (`player_id`),
                KEY `idx_st_staff`    (`staff_type`, `staff_id`),
                KEY `idx_st_status`   (`status`),
                KEY `idx_st_finishes` (`finishes_at`),
                KEY `fk_st_program`   (`program_id`),
                CONSTRAINT `fk_st_program`
                    FOREIGN KEY (`program_id`) REFERENCES `training_programs` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Seed: 14 domyslnych programow szkolen (INSERT IGNORE — bezpieczne przy powtorium).
            // Seed: 14 default training programs (INSERT IGNORE — safe on re-run).
            $db->exec("INSERT IGNORE INTO `training_programs`
                (code, department, target_skill, name_pl, name_en, duration_hours, cost, base_pass_rate, enabled)
            VALUES
                ('tech_drilling_basic',       'technical', 'skill_drilling',     'Kurs wiercenia poziom I',             'Drilling Course Level I',           48, 15000, 85, 1),
                ('tech_drilling_advanced',    'technical', 'skill_drilling',     'Kurs wiercenia poziom II',            'Drilling Course Level II',          72, 30000, 65, 1),
                ('tech_maintenance_basic',    'technical', 'skill_maintenance',  'Utrzymanie ruchu poziom I',           'Maintenance Level I',               36, 12000, 80, 1),
                ('tech_maintenance_advanced', 'technical', 'skill_maintenance',  'Utrzymanie ruchu poziom II',          'Maintenance Level II',              60, 24000, 62, 1),
                ('tech_safety_basic',         'technical', 'skill_safety',       'Szkolenie BHP poziom I',              'Safety Training Level I',           24,  8000, 90, 1),
                ('tech_safety_advanced',      'technical', 'skill_safety',       'Szkolenie BHP poziom II',             'Safety Training Level II',          48, 18000, 70, 1),
                ('tech_analysis_basic',       'technical', 'skill_analysis',     'Analiza geologiczna poziom I',        'Geological Analysis Level I',       60, 20000, 70, 1),
                ('tech_analysis_advanced',    'technical', 'skill_analysis',     'Analiza geologiczna poziom II',       'Geological Analysis Level II',      80, 38000, 55, 1),
                ('board_negotiation_basic',   'board',     'skill_negotiation',  'Negocjacje poziom I',                 'Negotiation Course Level I',        48, 25000, 80, 1),
                ('board_negotiation_advanced','board',     'skill_negotiation',  'Negocjacje poziom II',                'Negotiation Course Level II',       72, 45000, 60, 1),
                ('board_ethics_basic',        'board',     'skill_ethics',       'Etyka biznesu poziom I',              'Business Ethics Level I',           36, 18000, 85, 1),
                ('board_stress_basic',        'board',     'skill_stress',       'Zarzadzanie stresem poziom I',        'Stress Management Level I',         24, 15000, 88, 1),
                ('board_organization_basic',  'board',     'skill_organization', 'Zarzadzanie i organizacja poziom I',  'Organization & Management Level I', 48, 22000, 78, 1),
                ('board_analysis_basic',      'board',     'skill_analysis',     'Analiza biznesowa poziom I',          'Business Analysis Level I',         60, 28000, 72, 1)
            ");

        } catch (Throwable $e) {
            GameLog::warn('init', 'Training schema bootstrap skipped', ['error' => $e->getMessage()]);
        }
    }
}
