<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/PlayerMenuEditor.php';

final class PlayerMenuEditorTest extends TestCase
{
    public function testLegacyPostsCannotBypassVersionedPlayerMenuSave(): void
    {
        $controller = (string)file_get_contents(dirname(__DIR__, 2) . '/admin/template_editor.php');
        self::assertStringContainsString("in_array(\$action, ['save_nav_group', 'add_nav_group']", $controller);
        self::assertStringContainsString("in_array(\$action, ['save_nav', 'delete_nav']", $controller);
        self::assertStringContainsString("in_array(\$_POST['new_location'] ?? 'header', ['footer', 'actions'], true)", $controller);
    }

    public function testExternalTargetsRequireSafeHttpsOrHttpHost(): void
    {
        self::assertSame('https://example.org/path', PlayerMenuEditor::externalUrl('https://example.org/path'));
        foreach (['javascript:alert(1)', 'data:text/html,x', '//example.org', 'https://user:pass@example.org', 'https://example.org/#bad', 'http://localhost/test', 'http://127.0.0.1/', 'https://example.org/\r\nHost: bad'] as $bad) {
            try { PlayerMenuEditor::externalUrl($bad); self::fail('Accepted: ' . $bad); }
            catch (InvalidArgumentException $e) { self::assertSame('destination', $e->getMessage()); }
        }
    }

    public function testSnapshotVersionIsStableAndChangesWithItem(): void
    {
        $groups = [['group_key' => 'business', 'label' => 'Biznes']];
        $items = [['id' => 1, 'label' => 'Rynek']];
        $version = PlayerMenuEditor::version($groups, $items);
        self::assertSame($version, PlayerMenuEditor::version($groups, $items));
        self::assertNotSame($version, PlayerMenuEditor::version($groups, [['id' => 1, 'label' => 'Handel']]));
    }

    public function testExternalDestinationIsBuiltAsLinkRatherThanRoute(): void
    {
        self::assertSame('https://example.org/path', PlayerMenuEditor::href('https://example.org/path'));
        self::assertSame('/market', PlayerMenuEditor::href('market'));
        $this->expectException(InvalidArgumentException::class);
        PlayerMenuEditor::href('javascript:alert(1)');
    }

    public function testExternalItemKeepsNewTabPreference(): void
    {
        $db = self::database();
        [$groups, $items] = PlayerMenuEditor::rows($db);
        $input = ['version' => PlayerMenuEditor::version($groups, $items), 'groups' => $groups, 'items' => $items];
        $input['items'][0]['url_key'] = 'https://example.org/about';
        $input['items'][0]['open_new_tab'] = 1;
        $saved = PlayerMenuEditor::save($db, $input);
        self::assertSame('https://example.org/about', $saved['items'][0]['url_key']);
        self::assertSame(1, (int)$saved['items'][0]['open_new_tab']);
    }

    public function testPlayerMenuUsesExternalHrefAndNewTabFlag(): void
    {
        require_once dirname(__DIR__, 2) . '/src/GameNavigation.php';
        if (!function_exists('getLocale')) require_once dirname(__DIR__, 2) . '/src/i18n.php';
        $groups = [['id' => 1, 'group_key' => 'business', 'label' => 'Biznes', 'label_en' => 'Business', 'label_de' => 'Geschäft', 'lang_key' => '', 'icon_key' => '', 'sort_order' => 10, 'active' => 1]];
        $items = [['id' => 4, 'label' => 'Strona', 'label_en' => 'Site', 'label_de' => 'Seite', 'lang_key' => '', 'icon_key' => '', 'url_key' => 'https://example.org', 'group_key' => 'business', 'sort_order' => 20, 'active' => 1, 'open_new_tab' => 1]];
        $built = GameNavigation::build($items, '/market', $groups);
        self::assertSame('https://example.org', $built['business']['items'][0]['href']);
        self::assertTrue($built['business']['items'][0]['new_tab']);
    }

    public function testLegacyInternalPathIsRenderedWithoutRemovingExistingMenuEntry(): void
    {
        self::assertSame('/legacy-report', PlayerMenuEditor::href('/legacy-report'));
        self::assertSame('/legacy-report', PlayerMenuEditor::href('legacy-report'));
        foreach (['//evil.example', '/javascript:alert(1)', '/bad?next=x'] as $unsafe) {
            try { PlayerMenuEditor::href($unsafe); self::fail('Unsafe path accepted'); }
            catch (InvalidArgumentException $e) { self::assertSame('destination', $e->getMessage()); }
        }
    }

    public function testNewGroupAndChildCanBeSavedTogether(): void
    {
        $db = self::database();
        [$groups, $items] = PlayerMenuEditor::rows($db);
        $input = ['version' => PlayerMenuEditor::version($groups, $items), 'groups' => $groups, 'items' => $items,
            'new_groups' => [['group_key' => 'draft-team', 'label' => 'Zespół', 'label_en' => 'Team', 'label_de' => 'Team', 'lang_key' => '', 'icon_key' => 'kadry', 'sort_order' => 30, 'active' => 1]],
            'new_items' => [['label' => 'Mapa', 'label_en' => 'Map', 'label_de' => 'Karte', 'lang_key' => '', 'icon_key' => 'mapa', 'url_key' => 'map', 'group_key' => 'draft-team', 'sort_order' => 10, 'active' => 1, 'css_class' => '']]];
        $saved = PlayerMenuEditor::save($db, $input);
        self::assertCount(2, $saved['groups']);
        self::assertCount(2, $saved['items']);
        $newGroup = array_values(array_filter($saved['groups'], static fn(array $row): bool => $row['label'] === 'Zespół'))[0];
        $newItem = array_values(array_filter($saved['items'], static fn(array $row): bool => $row['url_key'] === 'map'))[0];
        self::assertSame($newGroup['group_key'], $newItem['group_key']);
        self::assertSame('map', $newItem['url_key']);
    }

    private static function database(): PDO
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("CREATE TABLE player_nav_groups (id INTEGER PRIMARY KEY, group_key TEXT, label TEXT, label_en TEXT, label_de TEXT, lang_key TEXT, icon_key TEXT, sort_order INTEGER, active INTEGER)");
        $db->exec("CREATE TABLE nav_items (id INTEGER PRIMARY KEY, label TEXT, label_en TEXT, label_de TEXT, lang_key TEXT, icon_key TEXT, url_key TEXT, group_key TEXT, sort_order INTEGER, active INTEGER, css_class TEXT, location TEXT, open_new_tab INTEGER NOT NULL DEFAULT 0)");
        $db->exec("INSERT INTO player_nav_groups VALUES (1,'business','Biznes','Business','Geschäft','','',10,1)");
        $db->exec("INSERT INTO nav_items (id,label,label_en,label_de,lang_key,icon_key,url_key,group_key,sort_order,active,css_class,location) VALUES (4,'Rynek','Market','Markt','','','market','business',20,1,'','header')");
        return $db;
    }

    public function testExistingUngroupedEntryGetsItsLegacyDefaultGroup(): void
    {
        $db = self::database();
        $db->exec("UPDATE nav_items SET group_key='' WHERE id=4");
        [$groups, $items] = PlayerMenuEditor::rows($db);
        $saved = PlayerMenuEditor::save($db, ['version' => PlayerMenuEditor::version($groups, $items), 'groups' => $groups, 'items' => $items]);
        self::assertSame('business', $saved['items'][0]['group_key']);
    }

    public function testLegacyEntryKeepsOriginalGroupWhenDestinationChanges(): void
    {
        $db = self::database();
        $db->exec("UPDATE nav_items SET group_key='' WHERE id=4");
        [$groups, $items] = PlayerMenuEditor::rows($db);
        $version = PlayerMenuEditor::version($groups, $items);
        $items[0]['url_key'] = 'https://example.org/map';
        $saved = PlayerMenuEditor::save($db, ['version' => $version, 'groups' => $groups, 'items' => $items]);
        self::assertSame('business', $saved['items'][0]['group_key']);
    }

    public function testUploadedImageIsRejectedWhenInvalidAndDoesNotChangeRows(): void
    {
        $db = self::database();
        [$groups, $items] = PlayerMenuEditor::rows($db);
        $input = ['version' => PlayerMenuEditor::version($groups, $items), 'groups' => $groups, 'items' => $items];
        $input['items'][0]['icon_upload'] = 'data:image/png;base64,' . base64_encode('<svg onload="alert(1)"/>');
        try { PlayerMenuEditor::save($db, $input); self::fail('invalid image accepted'); }
        catch (InvalidArgumentException $e) { self::assertSame('image', $e->getMessage()); }
        self::assertSame('', $db->query('SELECT icon_key FROM nav_items')->fetchColumn());
    }

    public function testGroupCannotBeDeletedWithChildren(): void
    {
        $db = self::database();
        [$groups, $items] = PlayerMenuEditor::rows($db);
        $input = ['version' => PlayerMenuEditor::version($groups, $items), 'groups' => [], 'items' => $items,
            'deleted_groups' => ['business']];
        try { PlayerMenuEditor::save($db, $input); self::fail('orphan accepted'); }
        catch (InvalidArgumentException $e) { self::assertSame('group', $e->getMessage()); }
        self::assertSame(1, (int)$db->query('SELECT COUNT(*) FROM player_nav_groups')->fetchColumn());
    }

    public function testDeletingChildAndItsGroupIsAtomic(): void
    {
        $db = self::database();
        [$groups, $items] = PlayerMenuEditor::rows($db);
        $input = ['version' => PlayerMenuEditor::version($groups, $items), 'groups' => [], 'items' => [],
            'deleted_groups' => ['business'], 'deleted_items' => [4]];
        $saved = PlayerMenuEditor::save($db, $input);
        self::assertSame([], $saved['groups']);
        self::assertSame([], $saved['items']);
    }

    public function testBatchSaveIsAtomicAndRejectsStaleVersion(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("CREATE TABLE player_nav_groups (id INTEGER PRIMARY KEY, group_key TEXT, label TEXT, label_en TEXT, label_de TEXT, lang_key TEXT, icon_key TEXT, sort_order INTEGER, active INTEGER)");
        $db->exec("CREATE TABLE nav_items (id INTEGER PRIMARY KEY, label TEXT, label_en TEXT, label_de TEXT, lang_key TEXT, icon_key TEXT, url_key TEXT, group_key TEXT, sort_order INTEGER, active INTEGER, css_class TEXT, location TEXT, open_new_tab INTEGER NOT NULL DEFAULT 0)");
        $db->exec("INSERT INTO player_nav_groups VALUES (1,'business','Biznes','Business','Geschäft','','',10,1)");
        $db->exec("INSERT INTO nav_items (id,label,label_en,label_de,lang_key,icon_key,url_key,group_key,sort_order,active,css_class,location) VALUES (4,'Rynek','Market','Markt','','','market','business',20,1,'','header')");
        [$groups, $items] = PlayerMenuEditor::rows($db);
        $input = ['version' => PlayerMenuEditor::version($groups, $items), 'groups' => $groups, 'items' => $items];
        $input['groups'][0]['label'] = 'Handel';
        $input['items'][0]['label'] = 'Nowy rynek';
        $input['items'][0]['label_en'] = 'New market';
        $input['items'][0]['label_de'] = 'Neuer Markt';
        $input['items'][0]['url_key'] = 'javascript:bad';
        try { PlayerMenuEditor::save($db, $input); self::fail('unsafe URL accepted'); }
        catch (InvalidArgumentException $e) { self::assertSame('destination', $e->getMessage()); }
        self::assertSame('Biznes', $db->query('SELECT label FROM player_nav_groups')->fetchColumn());
        $input['items'][0]['url_key'] = 'market';
        $saved = PlayerMenuEditor::save($db, $input);
        self::assertSame('Handel', $saved['groups'][0]['label']);
        self::assertSame('Nowy rynek', $saved['items'][0]['label']);
        try { PlayerMenuEditor::save($db, $input); self::fail('stale snapshot accepted'); }
        catch (RuntimeException $e) { self::assertSame('conflict', $e->getMessage()); }
    }
}
