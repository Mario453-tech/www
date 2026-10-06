<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ChatWidgetResilienceTest extends TestCase
{
    public function testDashboardWidgetHasEmptyAndErrorStates(): void
    {
        $root = dirname(__DIR__, 2);
        $view = (string) file_get_contents($root . '/templates/components/chat.php');
        $script = (string) file_get_contents($root . '/assets/js/chat.js');

        self::assertStringContainsString('data-empty=', $view);
        self::assertStringContainsString('data-error=', $view);
        self::assertStringContainsString('if (!response.ok || data.error)', $script);
        self::assertStringContainsString('showWidgetState(widgetBox.dataset.error', $script);
    }
}
