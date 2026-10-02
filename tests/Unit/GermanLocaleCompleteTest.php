<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GermanLocaleCompleteTest extends TestCase
{
    private const PLACEHOLDER_PATTERN = '/:[A-Za-z_][A-Za-z_0-9]*|\{[A-Za-z_][A-Za-z_0-9]*\}|%(?:[0-9]+\$)?[dsf]/';

    public function testAllGermanModulesMatchPolishKeysAndPlaceholders(): void
    {
        $root = dirname(__DIR__, 2);
        $deDir = $root . '/lang/de';
        $files = glob($deDir . '/*.php') ?: [];

        self::assertNotEmpty($files, 'German language directory should not be empty');

        foreach ($files as $file) {
            $module = basename($file, '.php');
            if ($module === 'admin') {
                continue;
            }

            $plPath = $root . "/lang/pl/{$module}.php";
            self::assertFileExists($plPath, "Polish module counterpart must exist for {$module}");

            $polish = require $plPath;
            $german = require $file;

            self::assertSame(array_keys($polish), array_keys($german), "Keys mismatch in module: {$module}");

            foreach ($german as $key => $text) {
                if (!is_string($text) || !is_string($polish[$key])) {
                    continue;
                }

                preg_match_all(self::PLACEHOLDER_PATTERN, $polish[$key], $polishMatches);
                preg_match_all(self::PLACEHOLDER_PATTERN, $text, $germanMatches);
                self::assertSame($polishMatches[0], $germanMatches[0], "Placeholder mismatch in key: {$key}");
            }
        }
    }

    public function testGermanGlobalLoaderMatchesPolishAndUsesEuro(): void
    {
        $root = dirname(__DIR__, 2);
        $polish = require $root . '/lang/pl.php';
        $german = require $root . '/lang/de.php';

        self::assertSame(array_keys($polish), array_keys($german), 'Global loader keys mismatch');
        self::assertSame('de-DE', $german['common.locale']);
        self::assertSame('de', $german['common.html_lang']);
        self::assertSame('EUR', $german['common.currency_code']);
        self::assertSame('€', $german['common.currency']);

        $deDir = $root . '/lang/de';
        $files = glob($deDir . '/*.php') ?: [];

        foreach ($files as $file) {
            $module = basename($file, '.php');
            if ($module === 'admin') {
                continue;
            }

            $strings = require $file;
            if (!is_array($strings)) {
                continue;
            }

            foreach ($strings as $key => $text) {
                if (!is_string($text)) {
                    continue;
                }
                self::assertDoesNotMatchRegularExpression(
                    '/\b(?:PLN|USD)\b|(?<!\p{L})zł(?!\p{L})/u',
                    $text,
                    "Currency leak in key: {$key}"
                );
            }
        }
    }
}
