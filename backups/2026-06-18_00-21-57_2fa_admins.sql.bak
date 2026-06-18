-- ============================================================
-- 2FA (TOTP / Google Authenticator) dla panelu admina
-- Uruchom RAZ w phpMyAdmin na bazie produkcyjnej.
-- Run ONCE in phpMyAdmin on the production database.
-- ============================================================

-- 1) Dodaj kolumny przechowujace sekret i status 2FA.
--    Add columns holding the TOTP secret and 2FA status.
ALTER TABLE `admins`
  ADD COLUMN `totp_secret`  VARCHAR(64) NULL DEFAULT NULL AFTER `password_hash`,
  ADD COLUMN `totp_enabled` TINYINT(1)  NOT NULL DEFAULT 0  AFTER `totp_secret`;

-- Po wykonaniu powyzszego:
-- Przy najblizszym logowaniu kazdy admin (totp_enabled=0) zostanie poproszony
-- o skonfigurowanie Google Authenticator. 2FA jest OBOWIAZKOWE.
-- After running the above, every admin will be forced to set up Google
-- Authenticator on next login. 2FA is MANDATORY.

-- ============================================================
-- RATUNEK / RECOVERY (gdy admin zgubi telefon)
-- Wykonaj dla konkretnego konta, by zresetowac 2FA. Przy nastepnym
-- logowaniu admin skonfiguruje aplikacje od nowa.
-- Run for a specific account to reset 2FA; the admin will re-enroll
-- on the next login.
-- ============================================================
-- UPDATE `admins` SET `totp_enabled` = 0, `totp_secret` = NULL WHERE `username` = 'TWOJ_LOGIN';
