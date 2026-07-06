<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/TickModule.php';
require_once dirname(__DIR__) . '/TickContext.php';
require_once dirname(__DIR__) . '/CredibilitySection.php';
require_once dirname(__DIR__, 2) . '/CompanyCredibilityService.php';

/**
 * CredibilityModule - adapter dla istniejacej sekcji wiarygodnosci.
 * CredibilityModule - adapter for the existing credibility section.
 */
final class CredibilityModule implements TickModule
{
    private int $playersChecked = 0;
    private int $cleanBonuses = 0;

    public function key(): string
    {
        return 'credibility';
    }

    public function order(): int
    {
        return 60;
    }

    public function run(TickContext $ctx): void
    {
        $section = new CredibilitySection($ctx->db, $ctx->now);
        $section->run();

        $this->playersChecked = $section->playersChecked;
        $this->cleanBonuses = $section->cleanBonuses;

        if ($this->cleanBonuses > 0 && class_exists('GameLog', false)) {
            GameLog::info('tick', "Wiarygodnosc firmy: przyznano {$this->cleanBonuses} bonusow za czysty okres");
        }
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        return [
            'players_checked' => $this->playersChecked,
            'clean_bonuses' => $this->cleanBonuses,
        ];
    }
}
