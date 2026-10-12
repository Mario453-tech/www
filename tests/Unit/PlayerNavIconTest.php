<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/PlayerNavIcon.php';
require_once dirname(__DIR__, 2) . '/src/GameNavigation.php';
require_once dirname(__DIR__, 2) . '/src/PlayerNavigationSettings.php';

final class PlayerNavIconTest extends TestCase
{
    public function testBuiltInIconAndFallbackUseOnlyExistingFiles(): void
    {
        self::assertSame('mapa', PlayerNavIcon::resolve('mapa', 'market'));
        self::assertSame('rynek', PlayerNavIcon::resolve('../admin', 'market'));
        self::assertSame('dashboard', PlayerNavIcon::resolve('', 'unknown'));
        self::assertArrayHasKey('home', PlayerNavIcon::builtIns());
        self::assertArrayNotHasKey('missing', PlayerNavIcon::builtIns());
    }

    public function testPlayerMenuUsesConfiguredIconAndPreservesLink(): void
    {
        self::ensureUrlHelper();
        $_SESSION['locale'] = 'pl';
        $menu = GameNavigation::build([
            ['url_key' => 'market', 'label' => 'Rynek', 'lang_key' => '', 'icon_key' => 'mapa'],
        ], '/market');
        self::assertSame('mapa', $menu['business']['items'][0]['icon']);
        self::assertSame('/market', $menu['business']['items'][0]['href']);
    }

    public function testSpecialEntriesRespectSavedVisibilityAndIcon(): void
    {
        $rows = [
            ['url_key' => 'home', 'label' => 'Start', 'lang_key' => '', 'active' => 1, 'icon_key' => 'rynek'],
            ['url_key' => 'help', 'label' => 'Pomoc', 'lang_key' => '', 'active' => 0, 'icon_key' => 'pomoc'],
        ];
        self::assertSame('rynek', GameNavigation::special($rows, 'home')['icon']);
        self::assertSame('Start', GameNavigation::special($rows, 'home')['label']);
        self::assertNull(GameNavigation::special($rows, 'help'));
        self::assertSame('czat', GameNavigation::special($rows, 'chat')['icon']);
    }

    public function testDeletedSpecialItemDoesNotReappearAfterSeeding(): void
    {
        self::assertNull(GameNavigation::special([], 'chat', true));
        self::assertSame('czat', GameNavigation::special([], 'chat', false)['icon']);
    }

    public function testMainGroupsCanBeRenamedReorderedAndAssignedChildren(): void
    {
        self::ensureUrlHelper();
        $groups = [
            ['group_key' => 'operations', 'label' => 'Pole', 'lang_key' => '', 'icon_key' => 'mapa', 'sort_order' => 20, 'active' => 1],
            ['group_key' => 'business', 'label' => 'Handel', 'lang_key' => '', 'icon_key' => 'bank', 'sort_order' => 10, 'active' => 1],
            ['group_key' => 'custom-team', 'label' => 'Zespół', 'lang_key' => '', 'icon_key' => 'kadry', 'sort_order' => 30, 'active' => 1],
            ['group_key' => 'company', 'label' => 'Ukryta', 'lang_key' => '', 'icon_key' => '', 'sort_order' => 40, 'active' => 0],
        ];
        $items = [
            ['url_key' => 'market', 'label' => 'Rynek', 'lang_key' => '', 'group_key' => 'custom-team'],
            ['url_key' => 'map', 'label' => 'Mapa', 'lang_key' => '', 'group_key' => 'operations'],
            ['url_key' => 'hr', 'label' => 'Kadry', 'lang_key' => '', 'group_key' => 'company'],
        ];
        $result = GameNavigation::build($items, '/map', $groups);
        self::assertSame(['business', 'operations', 'custom-team'], array_keys($result));
        self::assertSame('Pole', $result['operations']['label']);
        self::assertSame('mapa', $result['operations']['icon']);
        self::assertSame('Zespół', $result['custom-team']['label']);
        self::assertSame('Rynek', $result['custom-team']['items'][0]['label']);
        self::assertFalse(isset($result['company']));
        self::assertSame([], $result['business']['items']);
    }

    public function testUploadsOnlySmallValidatedImagesAndNormalizesDimensions(): void
    {
        if (!extension_loaded('gd')) self::markTestSkipped('GD required');
        $source = tempnam(sys_get_temp_dir(), 'nav');
        $out = sys_get_temp_dir() . '/nav-test-' . bin2hex(random_bytes(6));
        mkdir($out);
        try {
            $image = imagecreatetruecolor(80, 40);
            imagepng($image, $source);
            imagedestroy($image);
            $name = PlayerNavIcon::storeImage($source, 'image/png', $out);
            self::assertMatchesRegularExpression('/^custom-[a-f0-9]{24}$/', $name);
            self::assertSame([40, 40], array_slice(getimagesize($out . '/' . $name . '.png'), 0, 2));
            file_put_contents($source, '<svg onload="alert(1)"/>');
            $this->expectException(InvalidArgumentException::class);
            PlayerNavIcon::storeImage($source, 'image/svg+xml', $out);
        } finally {
            foreach (glob($out . '/*') ?: [] as $file) unlink($file);
            rmdir($out);
            unlink($source);
        }
    }

    public function testOnlyRealPlayerRoutesAreAvailableForNewSubitems(): void
    {
        $routes = PlayerNavigationSettings::destinations();
        self::assertContains('market', $routes);
        self::assertContains('contracts', $routes);
        self::assertNotContains('logout', $routes);
        self::assertNotContains('missing-page', $routes);
        self::assertNotContains('sell', $routes);
        self::assertNotContains('upgrade-well', $routes);
        self::assertNotContains('wallet-transfer', $routes);
        self::assertNotContains('recovery', $routes);
        self::assertTrue(PlayerNavigationSettings::validDestination('/market'));
        self::assertFalse(PlayerNavigationSettings::validDestination('javascript:alert(1)'));
        self::assertFalse(PlayerNavigationSettings::validDestination('/src/GameNavigation.php'));
        self::assertFalse(PlayerNavigationSettings::validDestination('missing-page'));
    }

    public function testEditingTranslatedLabelMakesNewTextVisible(): void
    {
        self::assertSame('', PlayerNavigationSettings::editedLanguageKey('Operacje', 'nav.group.operations', 'Wydobycie', 'nav.group.operations'));
        self::assertSame('nav.group.operations', PlayerNavigationSettings::editedLanguageKey('Operacje', 'nav.group.operations', 'Operacje', 'nav.group.operations'));
        self::assertSame('nav.group.custom', PlayerNavigationSettings::editedLanguageKey('Operacje', 'nav.group.operations', 'Wydobycie', 'nav.group.custom'));
    }

    public function testCustomGroupAndSubitemLabelsHaveDistinctLocaleVariants(): void
    {
        self::ensureUrlHelper();
        $groups = [['group_key' => 'team', 'label' => 'Zespół', 'label_en' => 'Team', 'label_de' => 'Team DE',
            'lang_key' => '', 'icon_key' => 'kadry', 'sort_order' => 1, 'active' => 1]];
        $item = ['url_key' => 'market', 'label' => 'Rynek', 'label_en' => 'Market custom',
            'label_de' => 'Markt neu', 'lang_key' => '', 'group_key' => 'team'];
        $_SESSION['locale'] = 'en';
        self::assertSame('Team', GameNavigation::build([$item], '/market', $groups)['team']['label']);
        self::assertSame('Market custom', GameNavigation::build([$item], '/market', $groups)['team']['items'][0]['label']);
        $_SESSION['locale'] = 'de';
        self::assertSame('Team DE', GameNavigation::build([$item], '/market', $groups)['team']['label']);
        self::assertSame('Markt neu', GameNavigation::build([$item], '/market', $groups)['team']['items'][0]['label']);
        $_SESSION['locale'] = 'pl';
    }

    public function testExplicitLocaleNameOverridesExistingTranslationKey(): void
    {
        $_SESSION['locale'] = 'de';
        self::assertSame('Eigener Name', PlayerNavigationSettings::label([
            'label' => 'Nazwa', 'lang_key' => 'nav.market', 'label_de' => 'Eigener Name',
        ]));
        $_SESSION['locale'] = 'pl';
    }

    public function testInitialHeaderSeedIsNotBlockedByExistingActionOrFooter(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->exec('CREATE TABLE nav_items (location TEXT NOT NULL)');
        $db->exec("INSERT INTO nav_items (location) VALUES ('actions'), ('footer')");
        self::assertTrue(PlayerNavigationSettings::shouldSeedLocation($db, 'header', false));
        self::assertFalse(PlayerNavigationSettings::shouldSeedLocation($db, 'footer', false));
        self::assertFalse(PlayerNavigationSettings::shouldSeedLocation($db, 'header', true));
        $db->exec("INSERT INTO nav_items (location) VALUES ('header')");
        self::assertFalse(PlayerNavigationSettings::shouldSeedLocation($db, 'header', false));
    }

    public function testNewMenuEntriesRequireNamesInAllThreeLocales(): void
    {
        self::assertTrue(PlayerNavigationSettings::validNewLabels('Rynek', 'Market', 'Markt'));
        self::assertFalse(PlayerNavigationSettings::validNewLabels('Rynek', '', 'Markt'));
        self::assertFalse(PlayerNavigationSettings::validNewLabels('Rynek', 'Market', ''));
        self::assertFalse(PlayerNavigationSettings::validNewLabels('Rynek', 'Market', str_repeat('x', 65)));
    }

    public function testUnknownTranslationKeyFallsBackToSavedLabel(): void
    {
        $_SESSION['locale'] = 'pl';
        self::assertSame('Moja sekcja', PlayerNavigationSettings::label([
            'label' => 'Moja sekcja', 'lang_key' => 'nav.group.no_such_key_9459',
        ]));
    }

    public function testChangingExistingPolishNameRequiresEnglishAndGermanNames(): void
    {
        self::assertTrue(PlayerNavigationSettings::validEditedLabels('Operacje', 'Operacje', '', ''));
        self::assertFalse(PlayerNavigationSettings::validEditedLabels('Operacje', 'Wydobycie', '', 'Förderung'));
        self::assertTrue(PlayerNavigationSettings::validEditedLabels('Operacje', 'Wydobycie', 'Production', 'Förderung'));
    }

    private static function ensureUrlHelper(): void
    {
        if (!function_exists('url')) {
            require_once dirname(__DIR__, 2) . '/src/init.php';
        }
    }
}
