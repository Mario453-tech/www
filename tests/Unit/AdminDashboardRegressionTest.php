<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/AdminAlertThresholds.php';
require_once dirname(__DIR__, 2) . '/src/AdminNewsHtml.php';
require_once dirname(__DIR__, 2) . '/src/DirectorNotificationService.php';
require_once dirname(__DIR__, 2) . '/src/AdminBankAdjustment.php';

final class AdminDashboardRegressionTest extends TestCase
{
    private function thresholds(): array
    {
        return array_combine(array_keys(AdminAlertThresholds::RANGES), [8, 15, 140, 70, 2, 6, 40, 80, 90]);
    }

    public function testValidThresholds(): void
    {
        self::assertSame(8.0, AdminAlertThresholds::validate($this->thresholds())['alert_loss_pct_warn']);
    }

    /** @dataProvider invalidThresholds */
    public function testInvalidThresholds(string $key, mixed $value): void
    {
        $input = $this->thresholds();
        $input[$key] = $value;
        $this->expectException(InvalidArgumentException::class);
        AdminAlertThresholds::validate($input);
    }

    public static function invalidThresholds(): array
    {
        return [
            ['alert_loss_pct_warn', 'abc'], ['alert_loss_pct_warn', []],
            ['alert_loss_pct_warn', '1e999'], ['alert_loss_pct_warn', INF],
            ['alert_loss_pct_warn', NAN], ['alert_loss_pct_warn', -1],
            ['alert_storage_full_pct', 101], ['alert_price_low', 140],
            ['alert_roi_days_min', 7], ['alert_loss_pct_warn', 15],
            ['alert_wear_critical', null], ['alert_price_high', true],
        ];
    }

    public function testHelpSanitizerPreservesFormattingAndBlocksActiveContent(): void
    {
        $html = AdminNewsHtml::sanitizeContent('<h2>Help</h2><ul><li><strong>Safe</strong></li></ul><a href="https://example.com">Link</a><script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)" onclick="alert(1)">Bad</a><p style="color: red; background-image:url(x)">Text</p>');
        self::assertStringContainsString('<strong>Safe</strong>', $html);
        self::assertStringContainsString('<ul>', $html);
        self::assertStringContainsString('href="https://example.com"', $html);
        foreach (['<script', 'onerror', 'onclick', 'javascript:', 'background-image', '<img'] as $unsafe) {
            self::assertStringNotContainsString($unsafe, $html);
        }
        self::assertSame($html, AdminNewsHtml::sanitizeContent($html));
    }

    public function testNotificationsUseCurrentLocaleAndKeepLegacyText(): void
    {
        $previous = $_SESSION['locale'] ?? 'pl';
        try {
            $row = ['title' => '', 'message' => '', 'title_key' => 'director.admin_adjustment.title',
                'message_key' => 'director.admin_adjustment.message',
                'message_params' => json_encode(['direction' => 'credit', 'amount' => '12.00', 'note' => 'A & B'])];
            $_SESSION['locale'] = 'pl';
            $pl = DirectorNotificationService::localize($row);
            $_SESSION['locale'] = 'en';
            $en = DirectorNotificationService::localize($row);
            self::assertNotSame($pl['title'], $en['title']);
            self::assertStringContainsString('credited', $en['message']);
            self::assertStringContainsString('A & B', $en['message']);
            self::assertStringNotContainsString('&amp;', $en['message']);
            $legacy = ['title' => 'Old title', 'message' => 'Old message'];
            self::assertSame($legacy, DirectorNotificationService::localize($legacy));
        } finally {
            $_SESSION['locale'] = $previous;
        }
    }

    /** @dataProvider invalidAmounts */
    public function testInvalidBankAmount(mixed $amount): void
    {
        $this->expectException(InvalidArgumentException::class);
        AdminBankAdjustment::amount($amount);
    }

    public static function invalidAmounts(): array
    {
        return [[[]], ['1abc'], ['1e999'], ['0'], ['-1'], ['0.001'], ['10000000000'], [INF], [true]];
    }

    public function testLocalizedBankAmount(): void
    {
        self::assertSame(1234.56, AdminBankAdjustment::amount('1 234,56'));
    }

    public function testOwnedViewsHaveNoInlineHandlersOrBlocks(): void
    {
        foreach (['templates/views/admin/bank/main.php', 'templates/views/admin/news/main.php',
            'templates/views/admin/alerts/main.php', 'templates/views/admin/help_editor/main.php',
            'templates/views/dashboard/main.php', 'templates/components/tech_notifications.php', 'public/help.php'] as $file) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
            self::assertDoesNotMatchRegularExpression('/<script\s*>|<style\b|\s(?:onclick|onsubmit|onchange|style)\s*=/i', $source, $file);
        }
    }
}
