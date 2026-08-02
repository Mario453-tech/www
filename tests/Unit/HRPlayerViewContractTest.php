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
        self::assertStringContainsString("\$event['deep_link']", $view);
        self::assertStringContainsString('hr-event-row--unread', $view);
        self::assertStringContainsString('role="tablist"', $view);
        self::assertStringContainsString('hr-morale-overview', $view);
        self::assertStringContainsString('hr-morale-alert', $view);
        self::assertStringContainsString('hr-morale-kpis', $view);
        self::assertStringContainsString('hr-morale-row--attention', $view);
        self::assertStringContainsString('style="--bar-w:', $view);
        self::assertStringContainsString('aria-expanded="false"', $view);
        self::assertDoesNotMatchRegularExpression('/&#(?:x[0-9a-f]+|[0-9]+);/i', $view);
    }

    public function testMoraleStylesUseProjectVisualTokens(): void
    {
        $css = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/css/hr_morale.css');

        foreach (['var(--gold)', 'var(--green)', 'var(--red)', 'var(--bg2)', 'var(--border2)'] as $token) {
            self::assertStringContainsString($token, $css);
        }
        self::assertStringContainsString('@media (max-width: 640px)', $css);
        self::assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}/i', $css);
    }
}
