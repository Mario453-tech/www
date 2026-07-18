-- Run this migration once in phpMyAdmin after creating a database backup.
-- Uruchom te migracje raz w phpMyAdmin po wykonaniu kopii bazy danych.
-- It removes the legacy board-staff hub operator path and keeps one technical path.
-- Usuwa stara sciezke operatora z zarzadu i pozostawia jedna sciezke techniczna.

START TRANSACTION;

CREATE TEMPORARY TABLE tmp_legacy_hub_operators (
    id INT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=MEMORY;

INSERT INTO tmp_legacy_hub_operators (id)
SELECT bm.id
FROM board_members bm
JOIN hr_specializations hs ON hs.id = bm.specialization_id
WHERE bm.member_type = 'staff'
  AND hs.code = 'hub_operator';

DELETE ea
FROM employee_assignments ea
JOIN tmp_legacy_hub_operators legacy
  ON ea.source_type = 'board_member'
 AND ea.source_id = legacy.id;

DELETE es
FROM employee_state es
JOIN tmp_legacy_hub_operators legacy
  ON es.source_type = 'board_member'
 AND es.source_id = legacy.id;

-- Keep a technical mirror, but detach it from the board record that will be removed.
-- Zachowaj techniczna kopie, ale odepnij ja od usuwanego rekordu zarzadu.
UPDATE technical_staff ts
JOIN employee_source_links esl ON esl.technical_staff_id = ts.id
JOIN tmp_legacy_hub_operators legacy ON legacy.id = esl.board_member_id
SET ts.manager_id = 0;

DELETE esl
FROM employee_source_links esl
JOIN tmp_legacy_hub_operators legacy ON legacy.id = esl.board_member_id;

DELETE ec
FROM employee_contracts ec
JOIN tmp_legacy_hub_operators legacy ON legacy.id = ec.member_id;

DELETE eh
FROM employment_history eh
JOIN tmp_legacy_hub_operators legacy ON legacy.id = eh.member_id;

DELETE he
FROM hr_events he
JOIN tmp_legacy_hub_operators legacy ON legacy.id = he.member_id;

DELETE cert
FROM employee_certificates cert
JOIN tmp_legacy_hub_operators legacy ON legacy.id = cert.member_id
WHERE cert.staff_type = 'board';

DELETE cr
FROM candidate_reviews cr
JOIN tmp_legacy_hub_operators legacy ON legacy.id = cr.reviewer_member_id;

DELETE bm
FROM board_members bm
JOIN tmp_legacy_hub_operators legacy ON legacy.id = bm.id;

-- Remove the obsolete perk variant. The new model uses technical_staff.spec_code.
-- Usun stary wariant perku. Nowy model uzywa technical_staff.spec_code.
UPDATE technical_staff
SET specialization = NULL
WHERE specialization = 'hub_operator';

DELETE FROM staff_specializations
WHERE code = 'hub_operator';

-- Create or convert the recruitable technical position.
-- Utworz albo przeksztalc stanowisko rekrutowane w dziale technicznym.
INSERT INTO hr_specializations
    (code, name, department, rarity, base_salary_min, base_salary_max, min_age, max_age, description)
VALUES
    ('hub_operator', 'Operator huba', 'technical', 'common', 8200.00, 11500.00, 25, 58,
     'Obsługuje przepływ, bufor i wydajność huba.')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    department = 'technical',
    base_salary_min = VALUES(base_salary_min),
    base_salary_max = VALUES(base_salary_max),
    description = VALUES(description);

DROP TEMPORARY TABLE tmp_legacy_hub_operators;

COMMIT;

-- Verification queries.
-- Zapytania kontrolne.
SELECT id, code, name, department, rarity, base_salary_min, base_salary_max
FROM hr_specializations
WHERE code = 'hub_operator';

SELECT COUNT(*) AS technical_hub_operators
FROM technical_staff
WHERE spec_code = 'hub_operator';

SELECT COUNT(*) AS legacy_board_hub_operators
FROM board_members bm
JOIN hr_specializations hs ON hs.id = bm.specialization_id
WHERE bm.member_type = 'staff'
  AND hs.code = 'hub_operator';
