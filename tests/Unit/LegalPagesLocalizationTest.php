<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tests localization of legal pages (terms, privacy, cookies) and header brand strings.
 * Testy lokalizacji stron prawnych (regulamin, prywatnosc, cookies) oraz naglowka i marki.
 */
final class LegalPagesLocalizationTest extends TestCase
{
    private ?string $origLocale = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->origLocale = $_SESSION['locale'] ?? null;
        require_once dirname(__DIR__, 2) . '/src/init.php';
    }

    protected function tearDown(): void
    {
        if ($this->origLocale !== null) {
            $_SESSION['locale'] = $this->origLocale;
        } else {
            unset($_SESSION['locale']);
        }
        parent::tearDown();
    }

    public function testGetLocaleHelperReturnsValidLocale(): void
    {
        $_SESSION['locale'] = 'en';
        self::assertSame('en', getLocale());

        $_SESSION['locale'] = 'de';
        self::assertSame('de', getLocale());

        $_SESSION['locale'] = 'pl';
        self::assertSame('pl', getLocale());

        $_SESSION['locale'] = 'invalid_locale';
        self::assertSame('pl', getLocale());
    }

    public function testPageAndHeaderTranslationKeysExistInAllLocales(): void
    {
        $keys = [
            'page.last_updated',
            'page.back_to_game',
            'page.404_heading',
            'page.404_title',
            'page.404_not_found',
            'header.site_name',
            'header.site_tagline',
            'header.logout_fallback',
            'header.boardroom_link',
            'header.bankruptcy_strong',
            'header.bankruptcy_desc',
            'header.recovery_panel_link',
        ];

        foreach (['pl', 'en', 'de'] as $loc) {
            $_SESSION['locale'] = $loc;
            foreach ($keys as $k) {
                $val = tPlain($k);
                self::assertNotSame($k, $val, "Key {$k} is missing in {$loc}");
                self::assertNotEmpty($val, "Key {$k} is empty in {$loc}");
            }
        }
    }

    public function testPageLastUpdatedSubstitutesDate(): void
    {
        $_SESSION['locale'] = 'pl';
        self::assertStringContainsString('01.03.2026', t('page.last_updated', ['date' => '01.03.2026']));
        self::assertStringContainsString('Ostatnia aktualizacja', t('page.last_updated', ['date' => '01.03.2026']));

        $_SESSION['locale'] = 'en';
        self::assertStringContainsString('01.03.2026', t('page.last_updated', ['date' => '01.03.2026']));
        self::assertStringContainsString('Last updated', t('page.last_updated', ['date' => '01.03.2026']));

        $_SESSION['locale'] = 'de';
        self::assertStringContainsString('01.03.2026', t('page.last_updated', ['date' => '01.03.2026']));
        self::assertStringContainsString('Zuletzt aktualisiert', t('page.last_updated', ['date' => '01.03.2026']));
    }

    public function testLegalTemplatesExistAndContainOilEmpire(): void
    {
        $root = dirname(__DIR__, 2);
        $locales = ['pl', 'en', 'de'];
        $types   = ['regulamin', 'privacy', 'cookies'];

        foreach ($types as $type) {
            foreach ($locales as $loc) {
                $file = "{$root}/templates/views/public/pages/{$type}_{$loc}.php";
                self::assertFileExists($file);
                $content = file_get_contents($file);
                self::assertStringContainsString('OilEmpire', $content, "File {$file} does not mention OilEmpire");
                self::assertStringNotContainsString('OilCorp', $content, "File {$file} still mentions OilCorp");
            }
        }
    }
}
