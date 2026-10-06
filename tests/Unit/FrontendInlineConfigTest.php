<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FrontendInlineConfigTest extends TestCase
{
    public function testDashboardConfigurationIsNotRenderedInsideInlineScripts(): void
    {
        $root = dirname(__DIR__, 2);
        $wellGrid = (string) file_get_contents($root . '/templates/components/well_grid.php');
        $footer = (string) file_get_contents($root . '/templates/footer.php');
        $header = (string) file_get_contents($root . '/templates/header.php');
        $notifications = (string) file_get_contents($root . '/templates/components/director_notifications.php');
        $wellGridJs = (string) file_get_contents($root . '/assets/js/well_grid.js');
        $gameJs = (string) file_get_contents($root . '/assets/js/game.js');
        $modalJs = (string) file_get_contents($root . '/assets/js/modal.js');

        self::assertStringNotContainsString('<script>', $wellGrid);
        self::assertStringNotContainsString('<script>', $footer);
        self::assertStringNotContainsString('<script>', $header);
        self::assertStringNotContainsString('<script>', $notifications);
        self::assertStringNotContainsString('onclick=', $notifications);
        self::assertStringContainsString('id="wgConfig"', $wellGrid);
        self::assertStringContainsString('id="gameConfig"', $footer);
        self::assertStringContainsString('id="appConfig"', $header);
        self::assertStringContainsString("JSON.parse(node.dataset.lang || '{}')", $wellGridJs);
        self::assertStringContainsString("JSON.parse(_gameConfig.dataset.lang || '{}')", $gameJs);
        self::assertStringContainsString("JSON.parse(appConfig.dataset.modalLang || '{}')", $modalJs);
    }
}
