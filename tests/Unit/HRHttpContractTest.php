<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HRHttpContractTest extends TestCase
{
    public function testApiHasExplicitJsonBoundaryAndDeliveryActions(): void
    {
        $root = dirname(__DIR__, 2);
        $api = (string)file_get_contents($root . '/src/HRApi.php');

        self::assertStringContainsString("header('Content-Type: application/json; charset=utf-8')", $api);
        foreach ([401, 403, 405, 409, 419, 422, 500] as $status) {
            self::assertStringContainsString((string)$status, $api);
        }
        self::assertStringContainsString("'mark_events_notified'", $api);
        self::assertStringContainsString("'mark_events_read'", $api);
        self::assertStringNotContainsString(
            "respondJson(['success' => false, 'error' => \$e->getMessage()]",
            $api
        );
    }

    public function testPlayerGetDoesNotMutateNotificationsOrContracts(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string)file_get_contents($root . '/public/hr.php');
        $query = (string)file_get_contents($root . '/src/HR/EmployeeDashboardQueryService.php');

        self::assertStringNotContainsString('checkExpiringContracts(', $controller);
        self::assertStringNotContainsString('SET notified_at=CURRENT_TIMESTAMP', $query);
        self::assertStringNotContainsString('wait_minutes', $controller);
    }

    public function testRequestPathsDoNotRunSchemaChangesOrAcceptClientWaitTime(): void
    {
        $root = dirname(__DIR__, 2);
        $boardAccess = (string)file_get_contents($root . '/src/BoardAccess.php');
        $recruitmentApi = (string)file_get_contents($root . '/src/RecruitmentAPI.php');
        $recruitmentJs = (string)file_get_contents($root . '/assets/js/recruitment.js');

        self::assertStringNotContainsString('addColumnIfMissing', $boardAccess);
        self::assertStringNotContainsString('CREATE TABLE', strtoupper($boardAccess));
        self::assertStringNotContainsString('ALTER TABLE', strtoupper($boardAccess));
        self::assertStringNotContainsString('wait_minutes', $recruitmentApi);
        self::assertStringNotContainsString('wait_minutes', $recruitmentJs);
    }
}
