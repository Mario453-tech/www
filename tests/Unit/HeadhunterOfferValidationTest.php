<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/HeadhunterService.php';

final class HeadhunterOfferValidationTest extends PHPUnit\Framework\TestCase
{
    public function testRejectsNegativeSalaryBeforeDatabaseAccess(): void
    {
        $service = (new ReflectionClass(HeadhunterService::class))->newInstanceWithoutConstructor();

        $this->expectException(InvalidArgumentException::class);
        $service->makeOffer(1, -1.0, 0.0, 'negative-salary-token');
    }

    public function testRejectsNegativeBonusBeforeDatabaseAccess(): void
    {
        $service = (new ReflectionClass(HeadhunterService::class))->newInstanceWithoutConstructor();

        $this->expectException(InvalidArgumentException::class);
        $service->makeOffer(1, 10000.0, -1.0, 'negative-bonus-token');
    }
}
