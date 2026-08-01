<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HRUiLanguageContractTest extends TestCase
{
    public function testRecruitmentScriptUsesDelegatedActionsAndServerTiming(): void
    {
        $script = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/recruitment.js');

        self::assertStringNotContainsString('onclick=', $script);
        self::assertStringNotContainsString('wait_minutes', $script);
        self::assertStringNotContainsString('&#127', $script);
        self::assertStringNotContainsString('&#128', $script);
        self::assertStringContainsString('data-recruitment-action', $script);
    }

    public function testPlayerAndAdminLanguageKeysStayInParity(): void
    {
        $root = dirname(__DIR__, 2);
        $pairs = [
            [$root . '/lang/pl/hr.php', $root . '/lang/en/hr.php'],
            [$root . '/lang/pl/admin/hr.php', $root . '/lang/en/admin/hr.php'],
        ];

        foreach ($pairs as [$polishPath, $englishPath]) {
            $polish = require $polishPath;
            $english = require $englishPath;
            self::assertSame([], array_diff_key($polish, $english));
            self::assertSame([], array_diff_key($english, $polish));
        }

        $polish = require $root . '/lang/pl/hr.php';
        $english = require $root . '/lang/en/hr.php';
        self::assertSame('Bardzo rzadka', $polish['hr.rarity.very_rare']);
        self::assertSame('Very rare', $english['hr.rarity.very_rare']);
    }

    public function testPlayerViewUsesPlainJsonDeepLinksUnreadBadgeAndAria(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string)file_get_contents($root . '/templates/views/hr/main.php');
        $script = (string)file_get_contents($root . '/assets/js/hr.js');

        self::assertStringContainsString("tPlain('hr_js.", $view);
        self::assertStringContainsString("\$event['deep_link']", $view);
        self::assertStringContainsString("\$unreadEventCount", $view);
        self::assertStringContainsString('role="tablist"', $view);
        self::assertStringContainsString('aria-expanded="false"', $view);
        self::assertStringContainsString("'mark_events_notified'", $script);
        self::assertStringContainsString("'mark_events_read'", $script);
        self::assertStringContainsString("setAttribute('aria-selected'", $script);
        self::assertStringNotContainsString('wait_minutes', $view . $script);
    }
}
