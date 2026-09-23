<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/DirectorNotificationService.php';

final class MarketNotificationRouteTest extends TestCase
{
    public function testMarketNotificationsLinkToExistingRouteAndLegacyLinksRedirect(): void
    {
        $templates = (new ReflectionClass(DirectorNotificationService::class))
            ->getReflectionConstant('TEMPLATES')->getValue();

        foreach (['market_price_drop', 'market_price_surge', 'storage_full'] as $type) {
            self::assertSame('/market', $templates[$type]['action_url'], $type);
        }

        $rules = (string) file_get_contents(dirname(__DIR__, 2) . '/.htaccess');
        self::assertStringContainsString('RewriteRule ^market\.php$ /market [R=301,L]', $rules);
        self::assertStringContainsString('RewriteRule ^market$          /public/market.php [L,PT]', $rules);
    }
}
