<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class WellConfigWebBootstrapTest extends TestCase
{
    public function testWebStartupDoesNotRunWellConfigAlter(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/init.php');
        self::assertIsString($source);
        $section = substr($source, strpos($source, '// WellConfig schema migration'));
        $section = substr($section, 0, strpos($section, '// ROUTING'));
        self::assertMatchesRegularExpression(
            '/if \(PHP_SAPI === \'cli\'\) \{[\s\S]*ALTER TABLE well_config/',
            $section
        );
    }
}
