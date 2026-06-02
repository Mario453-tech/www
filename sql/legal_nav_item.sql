-- ============================================================
-- Dział prawny — pozycja nawigacji (akcje gracza)
-- Dodaje wpis "Dział prawny" -> /legal do tabeli nav_items,
-- dzięki czemu link pojawia się w sekcji AKCJE na pulpicie gracza.
--
-- Uruchom RAZ w phpMyAdmin na bazie produkcyjnej.
-- Run ONCE in phpMyAdmin on the production database.
--
-- Skrypt jest idempotentny — ponowne uruchomienie nie utworzy duplikatu.
-- The script is idempotent — re-running it will not create a duplicate.
-- ============================================================

INSERT INTO `nav_items` (`label`, `url_key`, `icon`, `sort_order`, `active`, `css_class`, `location`)
SELECT 'Dział prawny', 'legal', '', 50, 1, 'btn-secondary', 'actions'
WHERE NOT EXISTS (
    SELECT 1 FROM `nav_items` WHERE `url_key` = 'legal' AND `location` = 'actions'
);

-- Po wykonaniu: w sekcji AKCJE pojawi się przycisk "Dział prawny" kierujący
-- do /legal (router obsługuje go przez ROUTES['legal'] oraz .htaccess).
-- After running: an action button "Dział prawny" linking to /legal appears
-- in the player's actions section.

-- ============================================================
-- COFNIĘCIE / ROLLBACK (gdyby trzeba było usunąć pozycję)
-- ============================================================
-- DELETE FROM `nav_items` WHERE `url_key` = 'legal' AND `location` = 'actions';
