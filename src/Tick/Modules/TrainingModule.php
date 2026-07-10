<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/TickModule.php';
require_once dirname(__DIR__) . '/TickContext.php';
require_once dirname(__DIR__) . '/TrainingSection.php';

final class TrainingModule implements TickModule
{
    private int $examined = 0;

    public function key(): string
    {
        return 'training';
    }

    public function order(): int
    {
        return 80;
    }

    public function failurePolicy(): TickFailurePolicy
    {
        return TickFailurePolicy::CONTINUE;
    }

    public function run(TickContext $ctx): void
    {
        $section = new TrainingSection($ctx->db);
        $section->run();
        $this->examined = $section->examined;
    }

    /** @return array<string,int> */
    public function stats(): array
    {
        return ['examined' => $this->examined];
    }
}
