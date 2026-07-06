-- ============================================================
-- Kontrakty dlugookresowe — pozycja nawigacji (akcje gracza)
-- Long-term contracts — player action nav item.
-- Dodaje wpis "Kontrakty" -> /contracts do tabeli nav_items,
-- dzieki czemu kafelek pojawia sie w sekcji AKCJE na pulpicie gracza.
-- Adds "Kontrakty" -> /contracts to nav_items so it shows in
-- the ACTIONS section of the player dashboard.
--
-- Uruchom RAZ w phpMyAdmin na bazie produkcyjnej.
-- Run ONCE in phpMyAdmin on the production database.
--
-- Skrypt jest idempotentny — ponowne uruchomienie nie utworzy duplikatu.
-- The script is idempotent — re-running it will not create a duplicate.
-- ============================================================

INSERT INTO `nav_items` (`label`, `url_key`, `icon`, `sort_order`, `active`, `css_class`, `location`)
SELECT 'Kontrakty', 'contracts', '', 55, 1, 'btn-secondary', 'actions' FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM `nav_items` WHERE `url_key` = 'contracts' AND `location` = 'actions'
);

-- Po wykonaniu: w sekcji AKCJE pojawi sie przycisk "Kontrakty" kierujacy
-- do /contracts (router obsluguje go przez ROUTES['contracts'] oraz .htaccess).
-- After running: an action button "Kontrakty" linking to /contracts appears
-- in the player's actions section.

-- ============================================================
-- COFNIECIE / ROLLBACK (gdyby trzeba bylo usunac pozycje)
-- ============================================================
-- DELETE FROM `nav_items` WHERE `url_key` = 'contracts' AND `location` = 'actions';
