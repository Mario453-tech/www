<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/src/ChatMessageHtml.php';

final class ChatMessageHtmlTest extends TestCase
{
    public function testBasicFormattingSurvivesWithoutAttributes(): void
    {
        $html = ChatMessageHtml::sanitize('<p onclick="bad()">Dzień <strong style="color:red">dobry</strong></p><ul><li>Test</li></ul>');
        self::assertSame('<p>Dzień <strong>dobry</strong></p><ul><li>Test</li></ul>', $html);
        self::assertSame("Dzień dobry\nTest", ChatMessageHtml::plainText($html));
        self::assertSame($html, ChatMessageHtml::sanitize($html));
    }

    /** @dataProvider payloads */
    public function testNoExecutableMarkupSurvives(string $input): void
    {
        $safe = ChatMessageHtml::sanitize($input);
        $doc = new DOMDocument();
        @$doc->loadHTML('<body>' . $safe . '</body>');
        foreach ($doc->getElementsByTagName('*') as $node) {
            self::assertContains($node->tagName, ['html', 'body', 'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li']);
            self::assertSame(0, $node->attributes->length);
        }
        self::assertSame($safe, ChatMessageHtml::sanitize($safe));
    }

    public static function payloads(): array
    {
        return [
            ['<script>alert(1)</script><p>Safe</p>'],
            ['<img src=x onerror=alert(1)><a href="javascript:alert(1)">Safe</a>'],
            ['<svg><foreignObject><p onload="bad()">x</p></foreignObject></svg><p>Safe</p>'],
            ['<math><mtext><table><mglyph><style><!--</style><img title="--><img src=1 onerror=alert(1)>">'],
            ['</body><style>body{display:none}</style><p id="location">Safe</p><!-- test -->'],
        ];
    }

    public function testPlayerMessagesNeverBecomeHtmlAndAdminApiKeepsPlainText(): void
    {
        $player = ['is_admin' => 0, 'message' => '<b>hello</b>', 'message_html' => '<script>bad()</script>'];
        self::assertSame(['is_admin' => 0, 'message' => '<b>hello</b>'], ChatMessageHtml::forApi($player));
        $admin = ChatMessageHtml::forApi(['is_admin' => 1, 'message' => '<p>Hello <strong>players</strong></p>']);
        self::assertSame('Hello players', $admin['message']);
        self::assertSame('<p>Hello <strong>players</strong></p>', $admin['message_html']);
        self::assertSame('', ChatMessageHtml::plainText(ChatMessageHtml::sanitize('<p>&nbsp;</p>')));
    }
}
