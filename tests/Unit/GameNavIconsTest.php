<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GameNavIconsTest extends TestCase
{
    public function testNavigationIconsAreStandaloneSvgDocuments(): void
    {
        $root = dirname(__DIR__, 2);
        $header = (string) file_get_contents($root . '/templates/header.php');
        self::assertStringNotContainsString('/assets/img/icons/nav/', $header);
        foreach (['home', 'mapa', 'rynek', 'dyrektor', 'pomoc', 'czat'] as $name) {
            $xml = new DOMDocument();
            self::assertTrue($xml->load($root . '/assets/img/icons/game-nav/' . $name . '.svg'));
            self::assertSame('http://www.w3.org/2000/svg', $xml->documentElement->namespaceURI);
            self::assertSame('svg', $xml->documentElement->localName);
        }
    }

    public function testChatOmitsAdministrativeAndLanguageRoomDescriptions(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/templates/views/chat/main.php');
        self::assertStringNotContainsString('chat-admin-box', $view);
        self::assertStringNotContainsString("t('chat.subtitle')", $view);
        foreach (['pl', 'en'] as $locale) {
            $lang = require $root . '/lang/' . $locale . '/chat.php';
            self::assertStringNotContainsString(':room', $lang['chat.placeholder_room']);
            self::assertSame(':count ' . ($locale === 'pl' ? 'uczestników' : 'participants'), $lang['chat.room_lang_desc']);
        }
    }
}
