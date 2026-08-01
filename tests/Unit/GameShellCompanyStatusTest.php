<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GameShellCompanyStatusTest extends TestCase
{
    public function testDamagedWellOverridesActiveCompanyStatus(): void
    {
        self::assertSame('well_failure', GameShell::companyStatusCode('active', 4, 7, 3));
    }

    public function testFinancialStatusHasPriorityOverWellFailure(): void
    {
        self::assertSame('bankrupt', GameShell::companyStatusCode('bankrupt', 0, 3, 3));
        self::assertSame('financial_risk', GameShell::companyStatusCode('financial_risk', 2, 3, 1));
    }

    public function testCompanyIsIdleOnlyWithoutActiveAndDamagedWells(): void
    {
        self::assertSame('idle', GameShell::companyStatusCode('active', 0, 3, 0));
        self::assertSame('active', GameShell::companyStatusCode('active', 0, 0, 0));
    }
}