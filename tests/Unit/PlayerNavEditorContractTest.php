<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PlayerNavEditorContractTest extends TestCase
{
    public function testAdminFormAndPlayerHeaderShareValidatedIconAndSpecialMenu(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string)file_get_contents($root . '/admin/template_editor.php');
        $form = (string)file_get_contents($root . '/templates/views/admin/template_editor/main.php');
        $header = (string)file_get_contents($root . '/templates/header.php');
        $nav = (string)file_get_contents($root . '/templates/components/game_navigation.php');
        self::assertStringContainsString("'icon_key'", $controller);
        self::assertStringContainsString('PlayerNavIcon::valid(', $controller);
        self::assertStringContainsString('PlayerNavIcon::storeImage(', $controller);
        self::assertStringContainsString("require __DIR__ . '/player_menu.php'", $form);
        $editor = (string)file_get_contents($root . '/templates/views/admin/template_editor/player_menu.php');
        self::assertStringContainsString('id="pm-icon"', $editor);
        self::assertStringContainsString('id="pm-file"', $editor);
        self::assertStringContainsString('id="pm-preview-content"', $editor);
        self::assertStringContainsString('GameNavigation::special(', $header);
        self::assertStringContainsString('PlayerNavigationSettings::label($__logoutItem)', $header);
        self::assertStringContainsString('PlayerNavIcon::url(', $nav);
    }

    public function testIconSelectionHasActualSizeLivePreviewInBothEditors(): void
    {
        $root = dirname(__DIR__, 2);
        $form = (string)file_get_contents($root . '/templates/views/admin/template_editor/player_menu.php');
        $css = (string)file_get_contents($root . '/assets/css/player_menu_editor.css');
        $controller = (string)file_get_contents($root . '/admin/template_editor.php');
        self::assertStringContainsString('id="pm-icon-preview"', $form);
        self::assertStringContainsString('id="pm-result-icon"', $form);
        self::assertStringContainsString('id="pm-identity-icon"', $form);
        self::assertStringContainsString('config.icons[row.icon_key]', (string)file_get_contents($root . '/assets/js/player_menu_editor.js'));
        self::assertStringContainsString('width: 22px; height: 22px', $css);
        self::assertStringContainsString("'/assets/js/player_menu_editor.js'", $controller);
    }

    public function testGermanNavigationEditorControlsAreTranslatedWithoutPolishFallback(): void
    {
        $root = dirname(__DIR__, 2);
        $de = require $root . '/lang/de/admin/template_editor.php';
        $pl = require $root . '/lang/pl/admin/template_editor.php';
        $en = require $root . '/lang/en/admin/template_editor.php';
        foreach (['heading', 'desc', 'page_title', 'tab_nav', 'tab_footer', 'tab_actions', 'tab_config',
            'badge_hidden', 'hint_url_key', 'msg_nav_added', 'msg_nav_deleted', 'err_invalid_data',
            'err_label_url_required', 'preview_btn', 'btn_delete', 'placeholder_label_nav',
            'tab_boardroom', 'label_label', 'label_lang_key', 'label_order', 'label_url_key',
            'label_visible', 'btn_add_nav', 'btn_save', 'no_item_selected', 'sidebar_nav',
            'edit_heading', 'label_css_class', 'confirm_delete', 'msg_nav_saved'] as $key) {
            $name = 'admin.template_editor.' . $key;
            self::assertArrayHasKey($name, $de);
            self::assertNotSame($pl[$name], $de[$name]);
            if (isset($en[$name])) self::assertNotSame($en[$name], $de[$name]);
        }
    }
}
