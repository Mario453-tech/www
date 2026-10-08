<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HomeDashboardContractTest extends TestCase
{
    public function testDashboardUsesStatisticsAndUnifiedActivityCenter(): void
    {
        $root = dirname(__DIR__, 2);
        $home = (string)file_get_contents($root . '/templates/views/index/main.php');
        $activity = (string)file_get_contents($root . '/templates/components/activity_center.php');
        $stats = (string)file_get_contents($root . '/templates/components/company_statistics.php');
        self::assertStringContainsString('company_statistics.php', $home);
        self::assertStringContainsString('activity_center.php', $home);
        self::assertStringContainsString("'/chat.php'", $activity);
        self::assertStringContainsString('role="tablist"', $activity);
        self::assertStringContainsString('role="tablist"', $stats);
        self::assertStringNotContainsString('onclick=', $activity . $stats);
        self::assertStringNotContainsString('<style', $activity . $stats);
    }

    public function testDashboardTranslationKeysMatch(): void
    {
        $root = dirname(__DIR__, 2);
        $pl = require $root . '/lang/pl/home.php';
        $en = require $root . '/lang/en/home.php';
        $plKeys = array_filter(array_keys($pl), static fn(string $key): bool => str_starts_with($key, 'home_dashboard.'));
        $enKeys = array_filter(array_keys($en), static fn(string $key): bool => str_starts_with($key, 'home_dashboard.'));
        sort($plKeys);
        sort($enKeys);
        self::assertSame($plKeys, $enKeys);
    }
}
