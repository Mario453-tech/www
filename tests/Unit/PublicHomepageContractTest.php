<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PublicHomepageContractTest extends TestCase
{
    public function testAnonymousRootUsesDedicatedLandingBeforeLoginGuard(): void
    {
        $root = dirname(__DIR__, 2);
        $entrypoint = (string) file_get_contents($root . '/public/index.php');

        $landingBranch = strpos($entrypoint, 'if (!Auth::isLoggedIn() && !Auth::tryRememberMe())');
        $loginGuard = strpos($entrypoint, 'Auth::requireLogin();');

        self::assertNotFalse(
            $landingBranch,
            'The public homepage must be shown only after remember-me restoration has been attempted.'
        );
        self::assertNotFalse($loginGuard);
        self::assertLessThan($loginGuard, $landingBranch);
        self::assertStringContainsString(
            "require __DIR__ . '/../templates/views/public/home/main.php';",
            $entrypoint
        );
        self::assertFileExists($root . '/templates/views/public/home/main.php');
    }

    public function testLandingStructureAssetsAndTranslationsMatchTheBrief(): void
    {
        $root = dirname(__DIR__, 2);
        $template = (string) file_get_contents($root . '/templates/views/public/home/main.php');
        self::assertFileExists($root . '/lang/pl/home.php');
        self::assertFileExists($root . '/lang/en/home.php');
        $polish = require $root . '/lang/pl/home.php';
        $english = require $root . '/lang/en/home.php';

        foreach (['home-about', 'home-gameplay', 'home-growth'] as $id) {
            self::assertStringContainsString('id="' . $id . '"', $template);
        }

        self::assertSame(3, substr_count($template, 'role="tab"'));
        self::assertSame(3, substr_count($template, 'role="tabpanel"'));
        self::assertStringContainsString('id="home-screenshot-dialog"', $template);
        self::assertStringContainsString('aria-controls="home-primary-nav"', $template);
        self::assertStringContainsString("url('login')", $template);
        self::assertStringContainsString("url('register')", $template);
        self::assertStringContainsString("asset('/assets/css/public_home.css')", $template);
        self::assertStringContainsString("asset('/assets/js/public_home.js')", $template);

        self::assertFileExists($root . '/assets/css/public_home.css');
        self::assertFileExists($root . '/assets/js/public_home.js');
        self::assertSame('Zbuduj własne naftowe imperium.', $polish['home.hero_title']);
        self::assertSame('Build your own oil empire.', $english['home.hero_title']);
        self::assertArrayHasKey('home.preview_unavailable', $polish);
        self::assertArrayHasKey('home.preview_unavailable', $english);
    }

    public function testLandingKeepsContentAvailableWithoutJavaScript(): void
    {
        $root = dirname(__DIR__, 2);
        $template = (string) file_get_contents($root . '/templates/views/public/home/main.php');
        $javascript = (string) file_get_contents($root . '/assets/js/public_home.js');
        $css = (string) file_get_contents($root . '/assets/css/public_home.css');

        self::assertStringNotContainsString('data-home-panel="logistics" hidden', $template);
        self::assertStringNotContainsString('data-home-panel="management" hidden', $template);
        self::assertStringContainsString('activateTab(tabs[0]);', $javascript);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $css);
    }
}
