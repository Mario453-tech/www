<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';

/**
 * Contract tests for the profile redesign (brief: BRIEF-AI-PROFIL.md).
 * Testy kontraktowe redesignu profilu gracza (brief: BRIEF-AI-PROFIL.md).
 *
 * These tests assert source-level contracts: the controller keeps its
 * security behaviour, the view uses real data and server actions, the
 * three locales stay in sync and the CSS/JS follow the brief.
 * Te testy sprawdzaja kontrakty zrodlowe: kontroler zachowuje bezpieczenstwo,
 * widok uzywa prawdziwych danych i akcji serwera, trzy jezyki sa zsynchronizowane,
 * a CSS/JS zgodne z briefem.
 */
final class ProfileRedesignTest extends BaseTestCase
{
    private const ROOT = __DIR__ . '/../..';

    private function source(string $rel): string
    {
        $path = self::ROOT . '/' . $rel;
        self::assertFileExists($path, 'missing file: ' . $rel);
        $content = (string) file_get_contents($path);

        return $content;
    }

    public function testControllerKeepsAuthCsrfPasswordHashAndPrg(): void
    {
        $controller = $this->source('public/profile.php');

        foreach ([
            'Auth::requireLogin()',
            'Auth::getUserId()',
            'CSRF::validateToken(',
            'password_verify(',
            'password_hash(',
            'WHERE id=?',
            "\$_SESSION['profile_flash']",
            "header('Location: '",
        ] as $expected) {
            self::assertStringContainsString($expected, $controller);
        }
    }

    public function testControllerRestrictsAvatarUploadToBriefFormatsAndLimit(): void
    {
        $controller = $this->source('public/profile.php');

        self::assertStringContainsString("'image/jpeg'", $controller);
        self::assertStringContainsString("'image/png'", $controller);
        self::assertStringContainsString("'image/webp'", $controller);
        self::assertStringContainsString('2 * 1024 * 1024', $controller);
        // Brief dopuszcza JPG/PNG/WebP - GIF nie jest juz akceptowany.
        // The brief allows JPG/PNG/WebP - GIF is no longer accepted.
        self::assertStringNotContainsString("'image/gif'", $controller);
    }

    public function testProfilePostRejectsNonScalarFieldsWithoutCrashingOrChangingCompany(): void
    {
        $controller = $this->source('public/profile.php');
        foreach (['current_password', 'new_password', 'confirm_password', 'company_name'] as $field) {
            self::assertStringContainsString("is_string(\$_POST['{$field}'] ?? null)", $controller);
        }
        self::assertStringContainsString("is_string(\$csrfValue) ? \$csrfValue : ''", $controller);
    }

    public function testAvatarUploadChecksRealImageAndOnlyRemovesOldAfterDbUpdate(): void
    {
        $controller = $this->source('public/profile.php');
        self::assertStringContainsString('getimagesizefromstring(', $controller);
        self::assertStringContainsString('random_bytes(', $controller);
        self::assertStringContainsString('is_uploaded_file(', $controller);
        $dbUpdate = strpos($controller, 'UPDATE players SET avatar_path=? WHERE id=?');
        $oldDelete = strpos($controller, '@unlink($oldPath)');
        self::assertNotFalse($dbUpdate);
        self::assertNotFalse($oldDelete);
        self::assertGreaterThan($dbUpdate, $oldDelete);
    }

    public function testAjaxAvatarReadIsBoundedBeforeValidation(): void
    {
        $controller = $this->source('public/profile.php');
        self::assertStringContainsString("file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024 + 1)", $controller);
    }

    public function testViewUsesRealDataAndRealServerActions(): void
    {
        $view = $this->source('templates/views/profile/main.php');

        foreach ([
            '$playerData',
            '$sellStats',
            '$bmStats',
            '$credibilityLevel',
            'company_name',
            'current_password',
            'new_password',
            'confirm_password',
            'upload_avatar',
            'update_profile',
            'change_password',
            '$csrfToken',
        ] as $expected) {
            self::assertStringContainsString($expected, $view, 'missing in view: ' . $expected);
        }
    }

    public function testViewHasNoInlineScriptsHandlersOrEmoji(): void
    {
        $view = $this->source('templates/views/profile/main.php');

        self::assertStringNotContainsString('<script', $view);
        self::assertStringNotContainsString('onclick=', $view);
        self::assertStringNotContainsString('oninput=', $view);
        self::assertStringNotContainsString('onchange=', $view);
        self::assertStringNotContainsString('onsubmit=', $view);
        self::assertStringNotContainsString('&#128', $view);
    }

    public function testViewUsesTheProfileIconSetFromThePackage(): void
    {
        $view = $this->source('templates/views/profile/main.php');

        foreach ([
            'camera', 'stats', 'calendar', 'sign-in', 'well', 'oil', 'safety',
            'black-market', 'transactions', 'revenue', 'penalty', 'detected',
            'trust', 'company', 'lock', 'eye', 'eye-off',
        ] as $icon) {
            self::assertStringContainsString(
                'assets/icons/profile/' . $icon . '.svg',
                $view,
                'view must reference icon: ' . $icon
            );
        }
    }

    public function testProfileIconPackageIsCopiedWithoutModification(): void
    {
        $expected = [
            'black-market', 'calendar', 'camera', 'company', 'detected', 'eye',
            'eye-off', 'lock', 'oil', 'penalty', 'revenue', 'safety', 'sign-in',
            'stats', 'transactions', 'trust', 'well',
        ];

        foreach ($expected as $icon) {
            self::assertFileExists(
                self::ROOT . '/assets/icons/profile/' . $icon . '.svg',
                'icon missing: ' . $icon
            );
        }

        // Kiedy pakiet zrodlowy jest dostepny, ikony musza byc bajt-w-bajt identyczne.
        // When the source package is available, icons must be byte-identical.
        $sourceDir = 'C:/Users/Marioo/Documents/Codex/OilEmpire-strona-glowna/icons/profile';
        if (is_dir($sourceDir)) {
            foreach ($expected as $icon) {
                self::assertFileEquals(
                    $sourceDir . '/' . $icon . '.svg',
                    self::ROOT . '/assets/icons/profile/' . $icon . '.svg',
                    'icon differs from package: ' . $icon
                );
            }
        }
    }

    public function testLocaleKeysAreCompleteInThreeLanguages(): void
    {
        $pl = require self::ROOT . '/lang/pl/profile.php';
        self::assertIsArray($pl);

        foreach (['en', 'de'] as $locale) {
            $messages = require self::ROOT . '/lang/' . $locale . '/profile.php';
            self::assertSame(array_keys($pl), array_keys($messages), 'key parity for ' . $locale);
            foreach ([
                'profile.heading',
                'profile.stats_title',
                'profile.name_preview',
                'profile.security_title',
                'profile.toggle_show',
                'profile.toggle_hide',
                'profile.avatar_invalid',
                'profile.change_photo',
                'profile.market_title',
                'profile.credibility_title',
                'profile.credibility_level_high',
            ] as $key) {
                self::assertArrayHasKey($key, $messages, 'missing ' . $key . ' in ' . $locale);
                self::assertNotSame('', $messages[$key], 'empty ' . $key . ' in ' . $locale);
            }
        }
    }

    public function testCssProvidesRequestedGridCardsAndMobileOrder(): void
    {
        $css = $this->source('assets/css/profile.css');

        self::assertStringContainsString('grid-template-columns:', $css);
        self::assertStringContainsString('max-width: 690px', $css);
        foreach ([
            'identity', 'company', 'account-stats', 'market-stats', 'reputation', 'security',
        ] as $card) {
            self::assertStringContainsString('.profile-' . $card, $css, 'missing card class: ' . $card);
        }

        // Karta profilu ma docelowo 191 px (z avatarem 120 px), a bezpieczenstwo
        // nie moze wymuszac tej wysokosci - wysokosc wynika z zawartosci.
        // The profile card targets 191 px (with a 120 px avatar); the security
        // card must never force that height - its height comes from content.
        self::assertStringContainsString('min-height: 191px', $css);
        self::assertStringContainsString('120px', $css);
        self::assertStringNotContainsString(
            'min-height: 191px',
            substr($css, (int) strpos($css, '.profile-security'))
        );

        // Kolejnosc mobilna z briefu: profil, firma, statystyki, czarny rynek,
        // wiarygodnosc, bezpieczenstwo.
        // Mobile order from the brief: profile, company, stats, black market,
        // credibility, security.
        foreach (['order: 1', 'order: 2', 'order: 3', 'order: 4', 'order: 5', 'order: 6'] as $order) {
            self::assertStringContainsString($order, $css);
        }
        self::assertStringContainsString('display: contents', $css);
    }

    public function testJsHasClientValidationEyeToggleAndServerWiring(): void
    {
        $js = $this->source('assets/js/profile.js');

        foreach ([
            '2 * 1024 * 1024',
            "'image/jpeg'",
            "'image/png'",
            "'image/webp'",
            'data-password-toggle',
            'X-CSRF-Token',
            'URL.createObjectURL',
        ] as $expected) {
            self::assertStringContainsString($expected, $js, 'missing in profile.js: ' . $expected);
        }

        // Zadnych natywnych okien dialogowych (AGENTS.md).
        // No native dialogs (AGENTS.md).
        self::assertStringNotContainsString('alert(', $js);
        self::assertStringNotContainsString('confirm(', $js);
        self::assertStringNotContainsString('prompt(', $js);

        // Teksty uzytkownika ida przez konfiguracje z widoku (data attributes),
        // nie przez twarde lancuchy w JS.
        // User-facing texts come from the view config (data attributes),
        // not from hardcoded JS strings.
        self::assertStringNotContainsString('Podgląd', $js);
        self::assertStringNotContainsString('Hasła', $js);
        self::assertStringNotContainsString('Zmień', $js);
    }
}
