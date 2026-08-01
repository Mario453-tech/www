<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/EmployeeSystemBootstrap.php';
require_once dirname(__DIR__, 2) . '/src/HR/EmployeeActionReceiptService.php';

final class EmployeeActionReceiptServiceTest extends SqliteIntegrationTestCase
{
    public function testPayloadHashIsOrderIndependentAndChangedPayloadConflicts(): void
    {
        $db = $this->createSqlitePdo();
        EmployeeSystemBootstrap::ensure($db);
        $service = new EmployeeActionReceiptService($db);
        $token = 'canonical-receipt-token';

        $receipt = $service->claim(1, 'grant_bonus', $token, [
            'employee' => ['source_id' => 20, 'source_type' => 'technical_staff'],
            'amount' => 5000.0,
        ]);
        $service->complete((int)$receipt['id'], 1, ['success' => true]);

        $replayed = $service->claim(1, 'grant_bonus', $token, [
            'amount' => 5000.0,
            'employee' => ['source_type' => 'technical_staff', 'source_id' => 20],
        ]);
        self::assertTrue($replayed['replayed']);

        $this->expectException(RuntimeException::class);
        $service->claim(1, 'grant_bonus', $token, [
            'amount' => 5001.0,
            'employee' => ['source_type' => 'technical_staff', 'source_id' => 20],
        ]);
    }
}
