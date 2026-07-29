<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/TickModule.php';
require_once dirname(__DIR__) . '/TickContext.php';
require_once dirname(__DIR__) . '/BankSection.php';

final class BankModule implements TickModule
{
    private int $loanDecisions = 0;
    private int $negotiationsResolved = 0;
    private int $hrRecruitmentsProcessed = 0;
    private int $hrRecruitmentCleanupProcessed = 0;
    private int $headhunterSearchesProcessed = 0;
    private int $hrErrors = 0;
    private int $bankruptcyProcessed = 0;
    private int $bankruptcyRecovered = 0;

    public function key(): string { return 'bank'; }
    public function order(): int { return 30; }
    public function failurePolicy(): TickFailurePolicy { return TickFailurePolicy::STOP; }

    public function run(TickContext $ctx): void
    {
        $section = new BankSection(
            $ctx->db,
            $ctx->bankNegAvailable,
            $ctx->bankruptcyAvailable,
            $ctx->moduleLimit('bank', 200)
        );
        $section->run();
        $this->loanDecisions = $section->loanDecisions;
        $this->negotiationsResolved = $section->negotiationsResolved;
        $this->hrRecruitmentsProcessed = $section->hrRecruitmentsProcessed;
        $this->hrRecruitmentCleanupProcessed = $section->hrRecruitmentCleanupProcessed;
        $this->headhunterSearchesProcessed = $section->headhunterSearchesProcessed;
        $this->hrErrors = $section->hrErrors;
        $this->bankruptcyProcessed = $section->bankruptcyProcessed;
        $this->bankruptcyRecovered = $section->bankruptcyRecovered;
    }

    /** @return array<string,int> */
    public function stats(): array
    {
        return [
            'interest_processed' => 0,
            'installments_processed' => 0,
            'negotiations_resolved' => $this->negotiationsResolved,
            'loan_decisions' => $this->loanDecisions,
            'hr_recruitments_processed' => $this->hrRecruitmentsProcessed,
            'hr_recruitment_cleanup_processed' => $this->hrRecruitmentCleanupProcessed,
            'headhunter_searches_processed' => $this->headhunterSearchesProcessed,
            'hr_errors' => $this->hrErrors,
            'bankruptcy_processed' => $this->bankruptcyProcessed,
            'bankruptcy_recovered' => $this->bankruptcyRecovered,
        ];
    }
}
