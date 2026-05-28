<?php
declare(strict_types=1);

final class MySqlFinanceDeliveredVolumeTest extends MySqlIntegrationTestCase
{
    public function testSaveTickPersistsProducedDeliveredAndPreStorageLossMetrics(): void
    {
        $playerId = $this->seedPlayer();

        $service = new FinanceService();
        $tickAt = date('Y-m-d H:i:s');

        $service->saveTick(
            $playerId,
            $tickAt,
            7700.00,
            10000.00,
            1200.00,
            300.00,
            450.00,
            50.00,
            200.00,
            23.0000,
            2300.00,
            123456.00,
            100.00,
            77.0000,
            2,
            90.00,
            4.0000,
            400.00,
            6.0000,
            600.00,
            2.0000,
            200.00,
            100.0000,
            77.0000,
            15.0000,
            8.0000,
            5.0000
        );

        $row = $service->getLastTick($playerId);

        self::assertNotNull($row);
        self::assertSame('77.0000', (string)$row['bbl_produced']);
        self::assertSame('100.0000', (string)$row['produced_bbl']);
        self::assertSame('77.0000', (string)$row['delivered_bbl']);
        self::assertSame('15.0000', (string)$row['pre_storage_loss_bbl']);
        self::assertSame('8.0000', (string)$row['transport_loss_bbl']);
        self::assertSame('5.0000', (string)$row['transport_event_loss_bbl']);

        $summary = $service->getSummary($playerId, 24);

        self::assertEquals(77.0, (float)$summary['total_bbl']);
        self::assertEquals(100.0, (float)$summary['total_produced_bbl']);
        self::assertEquals(15.0, (float)$summary['total_pre_storage_loss_bbl']);
        self::assertEquals(8.0, (float)$summary['total_transport_loss_bbl']);
        self::assertEquals(5.0, (float)$summary['total_transport_event_loss_bbl']);
    }
}
