<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once __DIR__ . '/../../admin/partials/finance_admin_actions.php';

final class AdminBalanceConfigTest extends BaseTestCase
{
    public function testFinanceConfigNoLongerWritesGlobalBalanceFields(): void
    {
        $config = adminFinanceConfigDefaults();
        $fields = adminFinanceConfigFields($config);
        $keys = array_column($fields, 'key');

        self::assertNotContains('global_tax_modifier', $keys);
        self::assertNotContains('global_cost_modifier', $keys);
        self::assertNotContains('global_loss_modifier', $keys);
        self::assertNotContains('global_tax_modifier', array_keys($config));
        self::assertNotContains('global_cost_modifier', array_keys($config));
        self::assertNotContains('global_loss_modifier', array_keys($config));

        self::assertContains('savings_plan_cooldown_hours', $keys);
        self::assertContains('alert_loss_pct', $keys);
        self::assertContains('alert_hub_loss_pct', $keys);
        self::assertContains('alert_fallback_min_pln', $keys);
        self::assertContains('alert_loss_player_min', $keys);
    }

    public function testBalanceLabelsUsePlainPolishNames(): void
    {
        $lang = require __DIR__ . '/../../lang/pl/admin/balance.php';

        self::assertSame('Koszty utrzymania', $lang['admin.balance.key_opex']);
        self::assertSame('Straty ropy w transporcie', $lang['admin.balance.key_loss']);
        self::assertSame('Podatki od wydobycia', $lang['admin.balance.key_tax']);
        self::assertSame('Balans gry', $lang['admin.balance.title']);
    }
}
