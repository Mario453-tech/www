<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/TickModule.php';
require_once dirname(__DIR__) . '/TickContext.php';
require_once dirname(__DIR__) . '/EmployeeMoraleSection.php';

final class EmployeesModule implements TickModule
{
    /** @var array<string,mixed> */
    private array $moduleStats = [];

    public function key(): string
    {
        return 'employees';
    }

    public function order(): int
    {
        return 35;
    }

    public function failurePolicy(): TickFailurePolicy
    {
        return TickFailurePolicy::CONTINUE;
    }

    public function run(TickContext $ctx): void
    {
        $section = new EmployeeMoraleSection(
            $ctx->db,
            $ctx->now,
            $ctx->runSequence,
            $ctx->moduleLimit('employees', 200)
        );
        $section->run();
        $this->moduleStats = [
            'cycle_id'=>$section->cycleId,
            'examined'=>$section->examined,
            'processed'=>$section->processed,
            'failed'=>$section->failed,
            'already_processed'=>$section->alreadyProcessed,
            'remaining'=>$section->remaining,
            'cycle_completed'=>$section->cycleCompleted,
            'morale_changed'=>$section->moraleChanged,
            'raise_requests'=>$section->raiseRequests,
            'threats_started'=>$section->threatsStarted,
            'strikes_started'=>$section->strikesStarted,
            'departures'=>$section->departures,
        ];
    }

    /** @return array<string,mixed> */
    public function stats(): array
    {
        return $this->moduleStats;
    }
}
