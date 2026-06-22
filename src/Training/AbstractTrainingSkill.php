<?php
/**
 * Klasa bazowa dla umiejetnosci szkoleniowych.
 * Base class for trainable skills.
 *
 * Dostarcza wspolne modyfikatory szansy zdania (ambicja, retry) oraz
 * domyslny brak modyfikatora specyficznego dla umiejetnosci.
 * Provides shared pass-chance modifiers (ambition, retry) and a default
 * zero skill-specific modifier.
 */
abstract class AbstractTrainingSkill implements TrainingSkillInterface
{
    public function getMaxLevel(): int
    {
        return 10;
    }

    public function passRateModifier(array $staffData, int $currentLevel): int
    {
        return 0;
    }

    /**
     * Modyfikator ambicji: trait_ambition 1-10, +2% za kazdy punkt powyzej 5,
     * -2% za kazdy ponizej. Pracownicy bez tej cechy (technicy) -> 0.
     * Ambition modifier: +/-2% per point from the 5 baseline; 0 when absent.
     */
    protected function ambitionModifier(array $staffData): int
    {
        if (!isset($staffData['trait_ambition'])) {
            return 0;
        }
        return ((int)$staffData['trait_ambition'] - 5) * 2;
    }

    /** Bonus za poprzednie podejscia: +10% za kazde oblanie, max +30%. */
    protected function retryModifier(int $retryCount): int
    {
        return min($retryCount * 10, 30);
    }
}
