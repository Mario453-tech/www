<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ChatWidgetResilienceTest extends TestCase
{
    public function testDashboardUsesFullSharedChatWorkspace(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/templates/components/chat.php');
        $index = (string) file_get_contents($root . '/public/index.php');
        $chatView = (string) file_get_contents($root . '/templates/views/chat/main.php');
        $styles = (string) file_get_contents($root . '/assets/css/chat.css');

        self::assertStringContainsString("require __DIR__ . '/../views/chat/main.php'", $view);
        self::assertStringContainsString("'dashboardEmbed' => true", $index);
        self::assertStringContainsString("'dashboardChatViewData'", $index);
        self::assertStringContainsString('chat-container--dashboard', $chatView);
        self::assertStringContainsString('.chat-container--dashboard .chat-shell', $styles);
        self::assertStringNotContainsString('chat-open-full-btn', $view);
    }
}
