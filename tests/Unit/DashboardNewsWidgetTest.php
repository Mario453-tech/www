<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DashboardNewsWidgetTest extends TestCase
{
    public function testDashboardLoadsNewsThroughDedicatedAsset(): void
    {
        $root = dirname(__DIR__, 2);
        $index = (string) file_get_contents($root . '/public/index.php');
        $view = (string) file_get_contents($root . '/templates/components/news_panel.php');
        $script = (string) file_get_contents($root . '/assets/js/dashboard_news.js');
        $api = (string) file_get_contents($root . '/src/AdminNewsApi.php');

        self::assertStringContainsString('/assets/js/dashboard_news.js', $index);
        self::assertStringContainsString('data-empty=', $view);
        self::assertStringContainsString('data-error=', $view);
        self::assertStringContainsString("fetch('/api/internal/AdminNewsApi.php'", $script);
        self::assertStringContainsString('SHOW COLUMNS FROM admin_news', $api);
        self::assertStringNotContainsString('addColumnIfMissing', $api);
    }
}
