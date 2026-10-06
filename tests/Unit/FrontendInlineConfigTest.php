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
        $wellGridJs = (string) file_get_contents($root . '/assets/js/well_grid.js');
        $gameJs = (string) file_get_contents($root . '/assets/js/game.js');

        self::assertStringNotContainsString('<script>', $wellGrid);
        self::assertStringNotContainsString('<script>', $footer);
        self::assertStringContainsString('id="wgConfig"', $wellGrid);
        self::assertStringContainsString('id="gameConfig"', $footer);
        self::assertStringContainsString("JSON.parse(node.dataset.lang || '{}')", $wellGridJs);
        self::assertStringContainsString("JSON.parse(_gameConfig.dataset.lang || '{}')", $gameJs);
    }
}
