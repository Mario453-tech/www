<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/TickModule.php';
require_once dirname(__DIR__) . '/TickContext.php';
require_once dirname(__DIR__) . '/MarineDeliverySection.php';

final class MarinePurgeModule implements TickModule
{
    private int $terminal = 0;
    private int $stuck = 0;

    public function key(): string
    {
        return 'marine_purge';
    }

    public function order(): int
    {
        return 20;
    }

    public function failurePolicy(): TickFailurePolicy
    {
        return TickFailurePolicy::CONTINUE;
    }

    public function run(TickContext $ctx): void
    {
        $result = MarineDeliverySection::purgeStale($ctx->db);
        $this->terminal = (int)($result['terminal'] ?? 0);
        $this->stuck = (int)($result['stuck'] ?? 0);
    }

    /** @return array<string,int> */
    public function stats(): array
    {
        return ['terminal' => $this->terminal, 'stuck' => $this->stuck];
    }
}
