<?php
declare(strict_types=1);

require_once __DIR__ . '/TickFailurePolicy.php';

/**
 * TickModule - wspolny kontrakt dla przyszlych sekcji ticka.
 * TickModule - shared contract for future tick sections.
 */
interface TickModule
{
    public function key(): string;

    public function order(): int;

    public function failurePolicy(): TickFailurePolicy;

    public function run(TickContext $ctx): void;

    /**
     * Zwroc statystyki modulu / Return module stats.
     *
     * @return array<string, mixed>
     */
    public function stats(): array;
}
