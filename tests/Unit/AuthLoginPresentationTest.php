<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AuthLoginPresentationTest extends TestCase
{
    public function testLoginBrandAndExampleEmailMatchInBothLanguages(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['pl', 'en'] as $locale) {
            $translations = require $root . '/lang/' . $locale . '/auth.php';

            self::assertSame('OilEmpire', $translations['auth.login_heading']);
            self::assertStringContainsString('OilEmpire', $translations['auth.login_title']);
            self::assertStringEndsWith('@oilempire.pl', $translations['auth.placeholder_email']);
        }
    }

    public function testLoginBackgroundHasNoOldBrandAsset(): void
    {
        $root = dirname(__DIR__, 2);
        $css = (string)file_get_contents($root . '/assets/css/auth.css');

        self::assertStringContainsString('/assets/images/oilempire_bg.png', $css);
        self::assertStringNotContainsString('/assets/images/oilcorp_bg.png', $css);
        self::assertFileExists($root . '/assets/images/oilempire_bg.png');
    }
}
