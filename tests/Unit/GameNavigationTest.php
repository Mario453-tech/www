<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/GameNavigation.php';

final class GameNavigationTest extends TestCase
{
    public function testGroupsOnlyItemsPassedAfterPermissionFiltering(): void
    {
        $_SESSION['locale'] = 'pl';
        $items = [
            ['url_key' => '/map', 'lang_key' => 'nav.map'],
            ['url_key' => '/market', 'lang_key' => 'nav.market'],
            ['url_key' => '/technical', 'lang_key' => 'nav.technical'],
            ['url_key' => '/hr', 'lang_key' => 'nav.hr'],
            ['url_key' => '/logout', 'lang_key' => 'nav.logout'],
        ];
        $groups = GameNavigation::build($items, '/technical');
        self::assertSame(['Mapa', 'Technika'], array_column($groups['operations']['items'], 'label'));
        self::assertTrue($groups['operations']['active']);
        self::assertSame(['Rynek'], array_column($groups['business']['items'], 'label'));
        self::assertSame(['Zarząd'], array_column($groups['company']['items'], 'label'));
        self::assertFalse($groups['company']['active']);
        self::assertCount(4, array_merge(...array_column($groups, 'items')));
    }
}
