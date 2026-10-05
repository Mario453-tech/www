<?php
declare(strict_types=1);

// Unit tests for unified chat redesign and code review bugfixes.
// Testy jednostkowe zunifikowanego czatu gracza oraz poprawek z code review.

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/ChatBootstrap.php';

final class ChatRedesignTest extends TestCase
{
    public function testEnsureChatSchemaFunctionExists(): void
    {
        $this->assertTrue(function_exists('ensureChatSchema'));
    }

    public function testLocalizationKeysExistInAllLanguages(): void
    {
        $root = dirname(__DIR__, 2);
        $pl = require $root . '/lang/pl/chat.php';
        $en = require $root . '/lang/en/chat.php';
        $de = require $root . '/lang/de/chat.php';

        $requiredKeys = [
            'chat.page_title',
            'chat.kicker',
            'chat.title',
            'chat.subtitle',
            'chat.rooms',
            'chat.private',
            'chat.active_players',
            'chat.online_count',
            'chat.admin_box_title',
            'chat.info_title',
            'chat.placeholder_room',
            'chat.placeholder_direct',
            'chat.send',
            'chat.date_today',
            'chat.admin_badge',
            'chat.attachment_photo',
            'chat.yesterday_relative',
            'chat.days_ago',
            'chat.connection_error',
            'chat.send_connection_error'
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $pl, "Missing key $key in PL");
            $this->assertArrayHasKey($key, $en, "Missing key $key in EN");
            $this->assertArrayHasKey($key, $de, "Missing key $key in DE");
        }
    }

    public function testViewContainsNoTablesAndNoInlineOnclick(): void
    {
        $root = dirname(__DIR__, 2);
        $viewContent = file_get_contents($root . '/templates/views/chat/main.php');
        $this->assertIsString($viewContent);

        // Verification of AGENTS.md rule: Zero <table> for layout.
        // Weryfikacja reguly AGENTS.md: zero znacznikow <table> do layoutu.
        $this->assertDoesNotMatchRegularExpression('/<table\b/i', $viewContent, 'Chat view should not contain <table> tags.');

        // Verification of AGENTS.md rule: Zero inline JS logic in PHP.
        // Weryfikacja reguly AGENTS.md: zero logiki inline JS w PHP.
        $this->assertDoesNotMatchRegularExpression('/\bonclick\s*=/i', $viewContent, 'Chat view should not contain inline onclick handlers.');

        // Verification of 3-column elements.
        // Weryfikacja elementow 3 kolumn.
        $this->assertStringContainsString('id="chatColLeft"', $viewContent);
        $this->assertStringContainsString('id="chatColCenter"', $viewContent);
        $this->assertStringContainsString('id="chatColRight"', $viewContent);
        $this->assertStringContainsString('id="chatMessagesArea"', $viewContent);
        $this->assertStringContainsString('id="chatMsgInput"', $viewContent);
        $this->assertStringContainsString('id="chatSendBtn"', $viewContent);

        // Verification of CSRF token configuration for JS.
        // Weryfikacja konfiguracji tokenu CSRF dla JS.
        $this->assertStringContainsString("'csrfToken' => CSRF::generateToken()", $viewContent);
    }

    public function testChatServiceDirectThreadsSubqueryHasPidProjection(): void
    {
        $root = dirname(__DIR__, 2);
        $serviceCode = file_get_contents($root . '/src/ChatService.php');
        $this->assertIsString($serviceCode);

        // Verify that subquery projects m2.pid to prevent MySQL "Unknown column 'last_msg.pid' in 'on clause'".
        // Weryfikacja, ze podzapytanie mapuje m2.pid, aby uniknac bledu MySQL o nieznanej kolumnie.
        $this->assertStringContainsString('SELECT m1.*, m2.pid', $serviceCode);
    }

    public function testAdminChatViewUsesCssClassesForRooms(): void
    {
        $root = dirname(__DIR__, 2);
        $adminView = file_get_contents($root . '/templates/views/admin/chat/main.php');
        $this->assertIsString($adminView);

        $this->assertStringContainsString('class="panel admin-room-card"', $adminView);
        $this->assertStringContainsString('class="admin-audit-entry"', $adminView);
        $this->assertStringNotContainsString('style="display:grid;grid-template-columns:repeat', $adminView);
    }

    public function testRouteConfigured(): void
    {
        $root = dirname(__DIR__, 2);
        $initContent = file_get_contents($root . '/src/init.php');
        $this->assertMatchesRegularExpression("/'chat'\s*=>\s*'\/chat'/", $initContent);
    }

    public function testHtaccessRewriteRuleConfigured(): void
    {
        $root = dirname(__DIR__, 2);
        $htaccessContent = file_get_contents($root . '/.htaccess');
        $this->assertMatchesRegularExpression('/RewriteRule\s+\^chat\$\s+\/public\/chat\.php\s+\[L,PT\]/', $htaccessContent);
    }

    // Verify presence of translation keys across PL, EN and DE files.
    // Weryfikacja obecnosci kluczy tlumaczen w plikach PL, EN i DE.
    public function testChatLanguageFilesContainAllKeys(): void
    {
        $root = dirname(__DIR__, 2);

        $plChat = require $root . '/lang/pl/chat.php';
        $enChat = require $root . '/lang/en/chat.php';
        $deChat = require $root . '/lang/de/chat.php';

        $expectedChatKeys = [
            'chat.loading_messages',
            'chat.load_error',
            'chat.unread_badge_title',
            'chat.default_player_name',
            'chat.online_count',
            'chat.kicker',
            'chat.title',
            'chat.rooms',
            'chat.private',
            'chat.active_players',
            'chat.input_aria',
            'chat.placeholder_widget',
        ];

        foreach ($expectedChatKeys as $k) {
            $this->assertArrayHasKey($k, $plChat, "PL chat missing {$k}");
            $this->assertArrayHasKey($k, $enChat, "EN chat missing {$k}");
            $this->assertArrayHasKey($k, $deChat, "DE chat missing {$k}");
        }

        $plAdmin = require $root . '/lang/pl/admin/chat.php';
        $enAdmin = require $root . '/lang/en/admin/chat.php';
        $deAdmin = require $root . '/lang/de/admin/chat.php';

        $expectedAdminKeys = [
            'admin.chat.badge_expired',
            'admin.chat.badge_expired_title',
            'admin.chat.badge_admin',
            'admin.chat.badge_admin_title',
            'admin.chat.badge_pinned_title',
            'admin.chat.pagination_aria',
            'admin.chat.room_messages_label',
            'admin.chat.room_sort_order_label',
            'admin.chat.room_archive_confirm',
            'admin.chat.room_archive_title',
            'admin.chat.room_archive_btn',
            'admin.chat.room_slug_label',
            'admin.chat.room_slug_ph',
            'admin.chat.room_type_label',
            'admin.chat.room_type_language',
            'admin.chat.room_type_custom',
            'admin.chat.room_type_system',
            'admin.chat.room_locale_label',
            'admin.chat.room_locale_ph',
            'admin.chat.room_name_pl_label',
            'admin.chat.room_name_en_label',
            'admin.chat.room_name_de_label',
            'admin.chat.room_desc_pl_label',
            'admin.chat.room_desc_en_label',
            'admin.chat.room_desc_de_label',
            'admin.chat.room_sort_label',
            'admin.chat.room_status_label',
            'admin.chat.room_status_active',
            'admin.chat.room_status_read_only',
            'admin.chat.room_save_btn',
            'admin.chat.audit_on',
            'admin.chat.interval_15m',
            'admin.chat.interval_30m',
            'admin.chat.interval_60m',
            'admin.chat.interval_90m',
            'admin.chat.interval_120m',
        ];

        foreach ($expectedAdminKeys as $k) {
            $this->assertArrayHasKey($k, $plAdmin, "PL admin chat missing {$k}");
            $this->assertArrayHasKey($k, $enAdmin, "EN admin chat missing {$k}");
            $this->assertArrayHasKey($k, $deAdmin, "DE admin chat missing {$k}");
        }
    }

    // Verify that views do not contain hardcoded Polish diacritic text outside comments.
    // Weryfikacja, ze widoki nie zawieraja hardkodowanych tekstow poza komentarzami.
    public function testChatViewsHaveNoHardcodedPolishDiacritics(): void
    {
        $root = dirname(__DIR__, 2);
        $views = [
            $root . '/templates/views/chat/main.php',
            $root . '/templates/views/admin/chat/main.php',
            $root . '/templates/components/chat.php',
        ];

        foreach ($views as $viewPath) {
            $lines = file($viewPath);
            foreach ($lines as $idx => $line) {
                $stripped = preg_replace('#//.*$#', '', $line);
                $stripped = preg_replace('#/\*.*?\*/#', '', $stripped);
                $stripped = preg_replace('#<!--.*?-->#', '', $stripped);
                $matches = [];
                $hasDiacritics = preg_match('/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻ]/u', $stripped, $matches);
                $lineNum = $idx + 1;
                $this->assertSame(0, $hasDiacritics, "Found hardcoded Polish characters on line {$lineNum} of {$viewPath}: " . trim($line));
            }
        }
    }
}

