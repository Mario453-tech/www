<?php
declare(strict_types=1);

final class TickModuleCatalog
{
    /** @var array<string,array{interval:int,limit:int,critical:bool}> */
    private const SETTINGS = [
        'market' => ['interval' => 1, 'limit' => 1, 'critical' => true],
        'marine_purge' => ['interval' => 1, 'limit' => 200, 'critical' => false],
        'bank' => ['interval' => 1, 'limit' => 500, 'critical' => true],
        'players' => ['interval' => 1, 'limit' => 500, 'critical' => true],
        'black_market' => ['interval' => 1, 'limit' => 500, 'critical' => false],
        'credibility' => ['interval' => 6, 'limit' => 500, 'critical' => false],
        'legal' => ['interval' => 6, 'limit' => 200, 'critical' => false],
        'training' => ['interval' => 1, 'limit' => 500, 'critical' => false],
        'contracts' => ['interval' => 1, 'limit' => 200, 'critical' => false],
        'b2b_contracts' => ['interval' => 2, 'limit' => 200, 'critical' => false],
        'cleanup' => ['interval' => 12, 'limit' => 500, 'critical' => false],
        'daily_stats' => ['interval' => 288, 'limit' => 500, 'critical' => false],
    ];

    public static function recommendedInterval(string $key): int
    {
        return self::SETTINGS[$key]['interval'] ?? 1;
    }

    public static function recommendedLimit(string $key): int
    {
        return self::SETTINGS[$key]['limit'] ?? 200;
    }

    public static function isCritical(string $key): bool
    {
        return self::SETTINGS[$key]['critical'] ?? false;
    }
}
