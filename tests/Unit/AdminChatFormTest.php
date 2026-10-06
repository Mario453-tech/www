<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminChatFormTest extends TestCase
{
    public function testBroadcastDoesNotConfirmABanAndBanStillRequiresConfirmation(): void
    {
        require_once dirname(__DIR__, 2) . '/src/CSRF.php';
        $viewData = [
            'msg' => '', 'err' => '', 'stats' => [], 'topSenders' => [],
            'autoClearLastAt' => null, 'autoClearEnabled' => 0, 'autoClearInterval' => 30,
            'playerList' => [], 'banDurations' => [], 'activeBans' => [],
            'filterPlayer' => '', 'messages' => [], 'reports' => [], 'blockedWords' => [],
        ];
        ob_start();
        require dirname(__DIR__, 2) . '/templates/views/admin/chat/main.php';
        $html = (string)ob_get_clean();
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($doc);
        $send = $xpath->query('//form[input[@name="action" and @value="send_admin"]]')->item(0);
        $ban = $xpath->query('//form[input[@name="action" and @value="ban_player"]]')->item(0);
        self::assertInstanceOf(DOMElement::class, $send);
        self::assertFalse($send->hasAttribute('data-confirm'), 'Sending a broadcast must never confirm a player ban.');
        self::assertInstanceOf(DOMElement::class, $ban);
        self::assertSame(tPlain('admin.chat.ban_confirm'), $ban->getAttribute('data-confirm'));
        self::assertSame(0, $xpath->query('//*[@style or @onclick or @onchange or @onsubmit or @onload]')->length);
        self::assertStringContainsString('translations[__INDEX__][locale]', $html);
        self::assertStringNotContainsString('name="name_pl"', $html);
    }
}
