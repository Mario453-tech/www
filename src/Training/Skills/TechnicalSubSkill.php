<?php
require_once __DIR__ . '/../AbstractTrainingSkill.php';

/**
 * Sub-umiejetnosc pracownika technicznego, przechowywana w technical_staff_skills.
 * Technical staff sub-skill, stored in the technical_staff_skills table.
 *
 * Sparametryzowana kodem - jedna klasa obsluguje wszystkie sub-skille techniczne
 * (wiercenie, utrzymanie, BHP, analiza). Umiejetnosc o wlasnej logice = osobna podklasa.
 * Parametrized by code - one class serves all technical sub-skills.
 */
class TechnicalSubSkill extends AbstractTrainingSkill
{
    public function __construct(private readonly string $code) {}

    public function getCode(): string       { return $this->code; }
    public function getDepartment(): string { return 'technical'; }

    public function getCurrentLevel(PDO $db, int $playerId, int $staffId): int
    {
        // COALESCE: brak wiersza sub-skilla = poziom 1 (domyslny). Brak pracownika = NULL.
        $stmt = $db->prepare(
            "SELECT COALESCE(tss.skill_level, 1)
               FROM technical_staff ts
               LEFT JOIN technical_staff_skills tss
                      ON tss.staff_id = ts.id AND tss.skill_code = ?
              WHERE ts.id = ? AND ts.player_id = ?
              LIMIT 1"
        );
        $stmt->execute([$this->code, $staffId, $playerId]);
        $val = $stmt->fetchColumn();
        return $val === false ? 0 : (int)$val;
    }

    public function applyIncrement(PDO $db, int $playerId, int $staffId): int
    {
        // INSERT...SELECT z filtrem player_id - tworzy wiersz tylko dla wlasnego pracownika.
        // Pierwsze szkolenie: poziom 1 -> 2. Kolejne: +1 z limitem 10.
        // Kolumna skill_level kwalifikowana nazwa tabeli docelowej - zrodlo SELECT
        // (technical_staff) tez ma kolumne skill_level, wiec bez tego jest dwuznaczna.
        $stmt = $db->prepare(
            "INSERT INTO technical_staff_skills (staff_id, skill_code, skill_level, updated_at)
                  SELECT ts.id, ?, 2, NOW()
                    FROM technical_staff ts
                   WHERE ts.id = ? AND ts.player_id = ?
             ON DUPLICATE KEY UPDATE
                  skill_level = LEAST(technical_staff_skills.skill_level + 1, 10),
                  updated_at = NOW()"
        );
        $stmt->execute([$this->code, $staffId, $playerId]);

        return $this->getCurrentLevel($db, $playerId, $staffId);
    }
}
