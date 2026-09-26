<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GermanLocaleStageOneTest extends TestCase
{
    public function testProvidedModulesMatchPolishKeysAndPlaceholders(): void
    {
        $root = dirname(__DIR__, 2);
        $pattern = '/:[A-Za-z_][A-Za-z_0-9]*|\{[A-Za-z_][A-Za-z_0-9]*\}|%(?:[0-9]+\$)?[dsf]/';

        foreach (['auth', 'common', 'nav', 'notifications', 'profile'] as $module) {
            $polish = require $root . "/lang/pl/{$module}.php";
            $german = require $root . "/lang/de/{$module}.php";

            self::assertSame(array_keys($polish), array_keys($german), $module);

            foreach ($german as $key => $text) {
                preg_match_all($pattern, $polish[$key], $polishMatches);
                preg_match_all($pattern, $text, $germanMatches);
                self::assertSame($polishMatches[0], $germanMatches[0], $key);
            }
        }
    }

    public function testGermanModuleStringsUseEuroAndLoaderKeepsAllKeys(): void
    {
        $root = dirname(__DIR__, 2);
        $polish = require $root . '/lang/pl.php';
        $german = require $root . '/lang/de.php';

        self::assertSame(array_keys($polish), array_keys($german));
        self::assertSame('de-DE', $german['common.locale']);
        self::assertSame('de', $german['common.html_lang']);
        self::assertSame('EUR', $german['common.currency_code']);
        self::assertSame('€', $german['common.currency']);

        foreach (['auth', 'common', 'nav', 'notifications', 'profile'] as $module) {
            $strings = require $root . "/lang/de/{$module}.php";
            foreach ($strings as $key => $text) {
                self::assertDoesNotMatchRegularExpression(
                    '/\b(?:PLN|USD)\b|(?<!\p{L})zł(?!\p{L})/u',
                    $text,
                    $key
                );
            }
        }
    }
}