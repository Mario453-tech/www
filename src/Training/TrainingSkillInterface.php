<?php
/**
 * Kontrakt dla pojedynczej umiejetnosci szkoleniowej.
 * Contract for a single trainable skill.
 *
 * Kazda umiejetnosc (wiercenie, BHP, negocjacje...) implementuje ten interfejs.
 * Zeby dodac nowa umiejetnosc - stworz klase implementujaca ten interfejs
 * i zarejestruj ja w TrainingSkillRegistry::build().
 * Each skill implements this interface; add a new one by registering it in build().
 */
interface TrainingSkillInterface
{
    /** Kod umiejetnosci, np. 'skill_drilling'. */
    public function getCode(): string;

    /** Dzial, do ktorego nalezy umiejetnosc: 'technical' lub 'board'. */
    public function getDepartment(): string;

    /** Maksymalny poziom umiejetnosci (gorny limit szkolenia). */
    public function getMaxLevel(): int;

    /**
     * Aktualny poziom umiejetnosci danego pracownika.
     * Current skill level for a given staff member.
     */
    public function getCurrentLevel(PDO $db, int $playerId, int $staffId): int;

    /**
     * Podnosi umiejetnosc o 1 (z gornym limitem). Zwraca nowy poziom.
     * Increments the skill by 1 (capped). Returns the new level.
     * Musi filtrowac po player_id (izolacja gracza).
     */
    public function applyIncrement(PDO $db, int $playerId, int $staffId): int;

    /**
     * Dodatkowy modyfikator szansy zdania zalezny od umiejetnosci/poziomu (w %).
     * Extra pass-chance modifier specific to this skill/level (percent).
     * Domyslnie 0 - trudniej zdac na wyzszych poziomach mozna tu zaszyc.
     *
     * @param array<string,mixed> $staffData
     */
    public function passRateModifier(array $staffData, int $currentLevel): int;
}
