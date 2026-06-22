<?php
require_once __DIR__ . '/../AbstractTrainingSkill.php';

/**
 * Umiejetnosc czlonka zarzadu, przechowywana w kolumnie tabeli board_members.
 * Board member skill, stored in a column of the board_members table.
 *
 * Sparametryzowana kodem kolumny (skill_organization, skill_negotiation...).
 * Parametrized by column code.
 */
class BoardColumnSkill extends AbstractTrainingSkill
{
    /** Dozwolone kolumny - twarda biala lista, kod nigdy nie pochodzi od uzytkownika. */
    private const ALLOWED = [
        'skill_organization', 'skill_negotiation', 'skill_analysis',
        'skill_stress', 'skill_ethics',
    ];

    public function __construct(private readonly string $code)
    {
        if (!in_array($this->code, self::ALLOWED, true)) {
            throw new InvalidArgumentException('Unknown board skill column: ' . $this->code);
        }
    }

    public function getCode(): string       { return $this->code; }
    public function getDepartment(): string { return 'board'; }

    public function getCurrentLevel(PDO $db, int $playerId, int $staffId): int
    {
        // Nazwa kolumny z bialej listy (walidacja w konstruktorze) - bezpieczna interpolacja.
        $stmt = $db->prepare(
            "SELECT `{$this->code}` FROM board_members WHERE id = ? AND player_id = ? LIMIT 1"
        );
        $stmt->execute([$staffId, $playerId]);
        $val = $stmt->fetchColumn();
        return $val === false ? 0 : (int)$val;
    }

    public function applyIncrement(PDO $db, int $playerId, int $staffId): int
    {
        $stmt = $db->prepare(
            "UPDATE board_members
                SET `{$this->code}` = LEAST(`{$this->code}` + 1, 10)
              WHERE id = ? AND player_id = ?"
        );
        $stmt->execute([$staffId, $playerId]);

        return $this->getCurrentLevel($db, $playerId, $staffId);
    }
}
