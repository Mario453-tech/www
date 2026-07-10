<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/TickModule.php';
require_once dirname(__DIR__) . '/TickContext.php';
require_once dirname(__DIR__) . '/LegalSection.php';

final class LegalModule implements TickModule
{
    private int $decided = 0;
    private int $notified = 0;
    private int $hubDecided = 0;
    private int $hubNotified = 0;

    public function key(): string
    {
        return 'legal';
    }

    public function order(): int
    {
        return 70;
    }

    public function failurePolicy(): TickFailurePolicy
    {
        return TickFailurePolicy::CONTINUE;
    }

    public function run(TickContext $ctx): void
    {
        $section = new LegalSection($ctx->db, $ctx->mutableNow());
        $section->run();
        $this->decided = $section->decided;
        $this->notified = $section->notified;
        $this->hubDecided = $section->hubDecided;
        $this->hubNotified = $section->hubNotified;
    }

    /** @return array<string,int> */
    public function stats(): array
    {
        return [
            'decided' => $this->decided,
            'notified' => $this->notified,
            'hub_decided' => $this->hubDecided,
            'hub_notified' => $this->hubNotified,
        ];
    }
}
