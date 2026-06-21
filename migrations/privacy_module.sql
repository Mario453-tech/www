-- ============================================================
-- MIGRACJA: privacy_module.sql
-- Cel: moduł RODO / cookies — tabele rdzenia i etapu 1.
-- Data: 2026-06-21
-- MySQL 8.0 compatible.
-- UWAGA: uruchom jednorazowo. Jesli tabela juz istnieje,
--        MySQL zglosi blad 1050 (Table already exists) - mozna zignorować.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Ustawienia modułu prywatności
--    (klucz-wartość, np. czy baner jest włączony, wersja)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `privacy_settings` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key`   VARCHAR(100)  NOT NULL,
    `setting_value` TEXT          NOT NULL DEFAULT '',
    `value_type`    ENUM('string','boolean','integer','json') NOT NULL DEFAULT 'string',
    `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Dziennik działań admina w module prywatności
--    (kto co zmienił, kiedy, jakie były stare i nowe dane)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `privacy_audit_logs` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `admin_id`      INT UNSIGNED NULL,
    `action`        VARCHAR(100) NOT NULL,
    `entity_type`   VARCHAR(100) NOT NULL,
    `entity_id`     INT UNSIGNED NULL,
    `old_data_json` JSON NULL,
    `new_data_json` JSON NULL,
    `ip_address`    VARCHAR(45)  NULL,
    `user_agent`    VARCHAR(512) NULL,
    `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_admin`   (`admin_id`),
    INDEX `idx_entity`  (`entity_type`, `entity_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. Rejestr podmodułów (które są włączone)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `privacy_features` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `feature_key` VARCHAR(100) NOT NULL,
    `is_enabled`  TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_feature_key` (`feature_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. Definicje cookies
--    (lista wszystkich cookies używanych w grze)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cookie_definitions` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(200) NOT NULL,
    `category`    ENUM('necessary','preferences','analytics','marketing') NOT NULL,
    `provider`    VARCHAR(200) NOT NULL DEFAULT '',
    `purpose`     TEXT         NOT NULL DEFAULT '',
    `duration`    VARCHAR(100) NOT NULL DEFAULT '',
    `type`        ENUM('cookie','local_storage','session_storage','indexeddb') NOT NULL DEFAULT 'cookie',
    `is_required` TINYINT(1)   NOT NULL DEFAULT 0,
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `cookie_key`  VARCHAR(200) NOT NULL DEFAULT '',
    `notes`       TEXT         NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                           ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_category` (`category`),
    INDEX `idx_active`   (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. Zgody użytkowników i gości
--    (kto co zaakceptował i kiedy)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cookie_consents` (
    `id`                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `player_id`                INT UNSIGNED NULL,
    `anonymous_token`          VARCHAR(64)  NOT NULL DEFAULT '',
    `consent_version`          VARCHAR(20)  NOT NULL,
    `banner_version`           VARCHAR(20)  NOT NULL,
    `accepted_categories_json` JSON         NOT NULL,
    `rejected_categories_json` JSON         NOT NULL,
    `source`                   ENUM('banner','settings','api') NOT NULL DEFAULT 'banner',
    `ip_address`               VARCHAR(45)  NULL,
    `user_agent`               VARCHAR(512) NULL,
    `created_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    `withdrawn_at`             DATETIME NULL,
    INDEX `idx_player`         (`player_id`),
    INDEX `idx_token`          (`anonymous_token`),
    INDEX `idx_version`        (`consent_version`),
    INDEX `idx_created`        (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. Wersje polityk prywatności i cookies
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `privacy_policy_versions` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `policy_type`  ENUM('cookies','privacy') NOT NULL,
    `version`      VARCHAR(20)  NOT NULL,
    `title`        VARCHAR(300) NOT NULL,
    `content`      LONGTEXT     NOT NULL,
    `is_active`    TINYINT(1)   NOT NULL DEFAULT 0,
    `published_at` DATETIME     NULL,
    `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_type_version` (`policy_type`, `version`),
    INDEX `idx_active` (`policy_type`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DANE STARTOWE
-- ============================================================

-- Domyślne ustawienia banera i modułu
INSERT IGNORE INTO `privacy_settings` (`setting_key`, `setting_value`, `value_type`) VALUES
('privacy.banner.enabled',                        '1',                                  'boolean'),
('privacy.banner.version',                        '1.0',                                'string'),
('privacy.banner.force_reconsent',                '0',                                  'boolean'),
('privacy.banner.heading',                        'Twoja prywatność ma znaczenie',      'string'),
('privacy.banner.description',                    'Używamy cookies, aby gra działała poprawnie i była dla Ciebie wygodna. Wybierz które cookies akceptujesz.', 'string'),
('privacy.banner.btn_accept_all',                 'Akceptuję wszystkie',                'string'),
('privacy.banner.btn_necessary_only',             'Tylko niezbędne',                    'string'),
('privacy.banner.btn_settings',                   'Ustawienia',                         'string'),
('privacy.banner.show_decline_button',            '1',                                  'boolean'),
('privacy.banner.policy_url',                     '/cookies-policy.php',                'string'),
('privacy.banner.privacy_url',                    '/privacy-policy.php',                'string'),
('privacy.cookies.policy_version',                '1.0',                                'string'),
('privacy.cookies.reconsent_after_policy_change', '1',                                  'boolean'),
('privacy.audit.enabled',                         '1',                                  'boolean');

-- Podmoduły aktywne domyślnie
INSERT IGNORE INTO `privacy_features` (`feature_key`, `is_enabled`, `sort_order`) VALUES
('cookies',         1, 10),
('consents',        1, 20),
('policy',          1, 30),
('banner_settings', 1, 40);

-- Domyślne definicje cookies dla OilCorp
INSERT IGNORE INTO `cookie_definitions`
    (`name`, `category`, `provider`, `purpose`, `duration`, `type`, `is_required`, `cookie_key`)
VALUES
('Sesja logowania',         'necessary',   'OilCorp', 'Utrzymuje sesję zalogowanego gracza. Wymagane do działania gry.',              'Do zamknięcia przeglądarki', 'cookie', 1, 'PHPSESSID'),
('Pamiętaj mnie',           'necessary',   'OilCorp', 'Automatyczne logowanie przy kolejnej wizycie.',                                '30 dni',                    'cookie', 1, 'remember_token'),
('Zaufane urządzenie (2FA)','necessary',   'OilCorp', 'Zapamiętuje urządzenie dla weryfikacji dwuetapowej konta.',                   '30 dni',                    'cookie', 1, 'trusted_device'),
('Token bezpieczeństwa',    'necessary',   'OilCorp', 'Ochrona formularzy przed atakami CSRF.',                                       'Sesja',                     'cookie', 1, 'csrf_token'),
('Język interfejsu',        'preferences', 'OilCorp', 'Zapamiętuje wybrany język gry (polski / angielski).',                         '1 rok',                     'cookie', 0, 'lang'),
('Dane analityczne',        'analytics',   'OilCorp', 'Przyszłe narzędzia analityczne — obecnie brak aktywnych wpisów.',             'Nie dotyczy',               'cookie', 0, '');

-- Domyślna wersja polityki cookies (treść zastępcza — admin powinien ją uzupełnić)
INSERT IGNORE INTO `privacy_policy_versions`
    (`policy_type`, `version`, `title`, `content`, `is_active`, `published_at`)
VALUES
('cookies', '1.0', 'Polityka cookies OilCorp',
'<h2>Polityka cookies</h2>
<p>Serwis OilCorp używa plików cookies i podobnych technologii w celu zapewnienia prawidłowego działania gry oraz bezpieczeństwa konta użytkownika.</p>
<h3>Czym są cookies?</h3>
<p>Cookies to małe pliki tekstowe zapisywane na Twoim urządzeniu przez przeglądarkę internetową.</p>
<h3>Jakich cookies używamy?</h3>
<p>Szczegółowa lista cookies dostępna jest w ustawieniach prywatności.</p>
<h3>Jak zarządzać cookies?</h3>
<p>Możesz zmienić swoje ustawienia w dowolnym momencie klikając link Ustawienia prywatności w stopce strony.</p>',
1, NOW()),
('privacy', '1.0', 'Polityka prywatności OilCorp',
'<h2>Polityka prywatności</h2>
<p>Niniejsza polityka prywatności opisuje zasady przetwarzania danych osobowych w serwisie OilCorp.</p>
<h3>Administrator danych</h3>
<p>Administratorem danych osobowych jest operator serwisu OilCorp.</p>
<h3>Podstawa prawna przetwarzania</h3>
<p>Dane przetwarzamy na podstawie Twojej zgody oraz w celu wykonania umowy (świadczenia usługi gry).</p>
<h3>Twoje prawa</h3>
<p>Masz prawo do dostępu do swoich danych, ich sprostowania, usunięcia oraz przenoszenia.</p>',
1, NOW());
