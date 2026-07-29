<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminHRViewContractTest extends TestCase
{
    public function testStrikeAttemptsAndLegacyPaginationAreRendered(): void
    {
        $root = dirname(__DIR__, 2);
        $strikes = (string)file_get_contents($root . '/templates/views/admin/hr/strikes.php');
        $logs = (string)file_get_contents($root . '/templates/views/admin/hr/logs.php');
        $pagination = (string)file_get_contents($root . '/templates/views/admin/hr/_pagination.php');

        self::assertStringContainsString("['attempt_no']", $strikes);
        self::assertStringContainsString("\$paginationQueryKey = 'hpage'", $logs);
        self::assertStringContainsString('$pageQueryKey', $pagination);
        self::assertStringNotContainsString('style="', $strikes . $logs . $pagination);
    }
}
