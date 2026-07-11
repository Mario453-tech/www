<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/TickModuleAdminService.php';

final class TickModuleAdminServiceTest extends BaseTestCase
{
    private PDO $db;
    private TickModuleAdminService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new PDO('sqlite::memory:');
        $this->service = new TickModuleAdminService($this->db);
        TickRegistry::clearCache();
    }

    protected function tearDown(): void
    {
        TickRegistry::clearCache();
        parent::tearDown();
    }

    public function testCriticalModuleCannotBeDisabledFromAdminService(): void
    {
        $this->service->modules();

        $this->service->updateSettings('market', false, 7, 9);
        $config = (new TickModuleConfigRepository($this->db))->find('market');

        $this->assertSame(1, (int)$config['enabled']);
        $this->assertSame(7, (int)$config['interval_ticks']);
        $this->assertSame(9, (int)$config['max_items_per_run']);
    }

    public function testOptionalModuleSettingsCanBeSavedAndRestored(): void
    {
        $this->service->modules();

        $this->service->updateSettings('legal', false, 9, 77);
        $repo = new TickModuleConfigRepository($this->db);
        $config = $repo->find('legal');

        $this->assertSame(0, (int)$config['enabled']);
        $this->assertSame(9, (int)$config['interval_ticks']);
        $this->assertSame(77, (int)$config['max_items_per_run']);

        $this->service->restoreRecommended('legal');
        $restored = $repo->find('legal');

        $this->assertSame(1, (int)$restored['enabled']);
        $this->assertSame(TickModuleCatalog::recommendedInterval('legal'), (int)$restored['interval_ticks']);
        $this->assertSame(TickModuleCatalog::recommendedLimit('legal'), (int)$restored['max_items_per_run']);
    }

    public function testRecentLogsDecodeStatsAndExposeLabelKey(): void
    {
        $this->service->modules();
        $repo = new TickModuleConfigRepository($this->db);
        $repo->markFinished(
            'legal',
            12,
            'test',
            TickModuleConfigRepository::STATUS_SUCCESS,
            14,
            ['decided' => 3],
            null,
            true
        );

        $logs = $this->service->recentLogs('legal', 10);

        $this->assertCount(1, $logs);
        $this->assertSame('admin.tick_modules.module.legal', $logs[0]['label_key']);
        $this->assertSame(['decided' => 3], $logs[0]['stats']);
        $this->assertSame(1, (int)$logs[0]['forced']);
    }

    public function testUnknownModuleIsRejectedBeforeCoordinatorRun(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->assertModuleExists('missing_module');
    }
}
