<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminPlayersLayoutTest extends TestCase
{
    public function testHeaderAndRowsShareNineColumnLayout(): void
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/assets/css/admin_players.css');
        self::assertMatchesRegularExpression('/\.players-grid \.list-header,\s*\.players-grid \.list-row\s*\{[^}]*grid-template-columns:\s*44px 48px minmax\(0, 1fr\) 110px 110px 90px 60px 150px 90px;/s', $css);
    }
}
