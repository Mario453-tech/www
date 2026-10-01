<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';

final class AdminFinanceReadPathTest extends BaseTestCase
{
    public function testDashboardDoesNotStartSchemaMaintenance(): void
    {
        $page = file_get_contents(dirname(__DIR__, 2) . '/admin/finance.php');
        self::assertStringContainsString('new FinanceService(false)', $page);
        self::assertStringContainsString('new FinancePolicyService($db, false)', $page);
    }

    public function testDerivedMetricsReuseReadOnlyPolicyService(): void
    {
        $metrics = file_get_contents(dirname(__DIR__, 2) . '/admin/partials/finance_admin_metrics.php');
        self::assertStringContainsString('getLiquidityOverview($playerId, $settings, $last, $summary24, $policySvc)', $metrics);
        self::assertStringContainsString('getPolicyImpactOverview($playerId, $settings, $last, $summary24, $policySnapshot, $policySvc)', $metrics);
    }
}
