<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HRPlayerViewContractTest extends TestCase
{
    public function testCanonicalTabsAndExternalHandlersRemainInView(): void
    {
        $view = (string)file_get_contents(dirname(__DIR__, 2) . '/templates/views/hr/main.php');

        foreach (['employees', 'recruitment', 'raises', 'morale', 'conflicts', 'training', 'history'] as $tab) {
            self::assertStringContainsString("data-hr-tab=\"<?= htmlspecialchars(\$tabCode", $view);
            self::assertStringContainsString("data-canonical-panel=\"{$tab}\"", $view);
        }
        self::assertStringNotContainsString('onclick=', $view);
        self::assertStringNotContainsString('onsubmit=', $view);
        self::assertStringNotContainsString('style="width:', $view);
        self::assertStringContainsString('employee_deep_link', $view);
        self::assertStringContainsString('hr-event-row--unread', $view);
        self::assertDoesNotMatchRegularExpression('/&#(?:x[0-9a-f]+|[0-9]+);/i', $view);
    }
}
