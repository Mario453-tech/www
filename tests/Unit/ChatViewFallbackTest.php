<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/src/CSRF.php';

final class ChatViewFallbackTest extends TestCase
{
    public function testHistoryAndProtectedFormAreRenderedWithoutJavaScript(): void
    {
        $locale = 'pl';
        $room = ['id'=>1, 'name'=>'Polski', 'slug'=>'polski', 'status'=>'active', 'member_count'=>0, 'message_count'=>1];
        $viewData = ['activeRoom'=>$room,'rooms'=>[$room], 'presenceData'=>['players'=>[], 'total_online'=>0],
            'withPartnerId'=>null,'withPartnerName'=>'','directThreads'=>[],'playerId'=>1,'flash'=>'',
            'history'=>[['id'=>1,'sender_id'=>2,'sender_name'=>'Other', 'time'=>'10:00','message'=>'<script>unsafe</script>']]];
        ob_start();
        require dirname(__DIR__, 2) . '/templates/views/chat/main.php';
        $html = (string) ob_get_clean();
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($doc);
        self::assertSame(0, $xpath->query('//script')->length);
        self::assertSame(1, $xpath->query('//form[@id="chatComposer" and @method="post"]/input[@name="csrf_token"]')->length);
        self::assertSame(1, $xpath->query('//textarea[@name="message" and @maxlength="1000"]')->length);
        self::assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $html);
        self::assertSame(1, $xpath->query('//label[@for="chatMsgInput"]')->length);
        self::assertSame(1, $xpath->query('//dialog[@id="chatDrawer"]')->length);
        $config = $xpath->query('//*[@id="chatConfig"]')->item(0);
        self::assertSame('Polski', json_decode($config->getAttribute('data-config'), true, 512, JSON_THROW_ON_ERROR)['activeRoomName']);
    }
}
