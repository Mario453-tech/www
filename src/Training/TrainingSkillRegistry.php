<?php
require_once __DIR__ . '/TrainingSkillInterface.php';
require_once __DIR__ . '/Skills/TechnicalSubSkill.php';
require_once __DIR__ . '/Skills/BoardColumnSkill.php';

/**
 * Rejestr umiejetnosci szkoleniowych.
 * Registry of trainable skills.
 *
 * Zeby dodac nowa umiejetnosc:
 * 1. (opcjonalnie) stworz wlasna klase implementujaca TrainingSkillInterface
 *    jesli umiejetnosc ma nietypowa logike; w przeciwnym razie uzyj
 *    TechnicalSubSkill lub BoardColumnSkill.
 * 2. Dodaj jedna linie w metodzie build() ponizej.
 * To jedyne miejsce, gdzie wymienione sa wszystkie umiejetnosci.
 */
class TrainingSkillRegistry
{
    /** @var array<string,TrainingSkillInterface> klucz: "department:skill_code" */
    private array $skills = [];

    public function register(TrainingSkillInterface $skill): void
    {
        $this->skills[$skill->getDepartment() . ':' . $skill->getCode()] = $skill;
    }

    /** Pobiera umiejetnosc po dziale i kodzie. Zwraca null gdy nieznana. */
    public function get(string $department, string $code): ?TrainingSkillInterface
    {
        return $this->skills[$department . ':' . $code] ?? null;
    }

    /** @return array<string,TrainingSkillInterface> */
    public function all(): array
    {
        return $this->skills;
    }

    /**
     * Fabryka - buduje rejestr ze wszystkimi umiejetnosciami.
     * Factory - builds the registry with all skills.
     */
    public static function build(): self
    {
        $registry = new self();

        // Dzial techniczny / Technical department
        $registry->register(new TechnicalSubSkill('skill_drilling'));
        $registry->register(new TechnicalSubSkill('skill_maintenance'));
        $registry->register(new TechnicalSubSkill('skill_safety'));
        $registry->register(new TechnicalSubSkill('skill_analysis'));

        // Zarzad / Board
        $registry->register(new BoardColumnSkill('skill_organization'));
        $registry->register(new BoardColumnSkill('skill_negotiation'));
        $registry->register(new BoardColumnSkill('skill_analysis'));
        $registry->register(new BoardColumnSkill('skill_stress'));
        $registry->register(new BoardColumnSkill('skill_ethics'));

        return $registry;
    }
}
