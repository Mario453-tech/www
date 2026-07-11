<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/TickModuleAdminService.php';
require_once dirname(__DIR__, 2) . '/src/Tick/TickStatsRepository.php';

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

    public function testRecentLogsSupportPagination(): void
    {
        $this->service->modules();
        $repo = new TickModuleConfigRepository($this->db);

        for ($i = 1; $i <= 12; $i++) {
            $repo->markFinished(
                'legal',
                $i,
                'test',
                TickModuleConfigRepository::STATUS_SUCCESS,
                10 + $i,
                ['decided' => $i],
                null,
                false
            );
        }

        $this->assertSame(12, $this->service->countRecentLogs('legal'));
        $pageOne = $this->service->recentLogs('legal', 10, 0);
        $pageTwo = $this->service->recentLogs('legal', 10, 10);

        $this->assertCount(10, $pageOne);
        $this->assertCount(2, $pageTwo);
        $this->assertSame(['decided' => 12], $pageOne[0]['stats']);
        $this->assertSame(['decided' => 2], $pageTwo[0]['stats']);
    }

    public function testRecentTickStatsExposePlayersProfileAndPagination(): void
    {
        $repo = new TickStatsRepository($this->db);
        for ($i = 1; $i <= 11; $i++) {
            $repo->save([
                'ran_at' => '2026-07-12 00:' . str_pad((string)$i, 2, '0', STR_PAD_LEFT) . ':00',
                'tick_sequence' => $i,
                'source' => 'test',
                'duration_ms' => 100 + $i,
                'module_stats_data' => [
                    'players' => [
                        'players_processed' => 2,
                        'section_ms_well_loop' => 150 + $i,
                        'section_ms_pipelines' => 30 + $i,
                        'slowest_player_ms' => 400 + $i,
                        'slowest_player_id' => 9000 + $i,
                    ],
                ],
                'module_runs_data' => [
                    'players' => [
                        'status' => 'success',
                        'duration_ms' => 100 + $i,
                    ],
                ],
            ]);
        }

        $this->assertSame(11, $this->service->countRecentTickStats());
        $pageOne = $this->service->recentTickStats(10, 0);
        $pageTwo = $this->service->recentTickStats(10, 10);

        $this->assertCount(10, $pageOne);
        $this->assertCount(1, $pageTwo);
        $this->assertSame(11, (int)$pageOne[0]['tick_sequence']);
        $this->assertSame(161, (int)$pageOne[0]['players_profile']['sections']['well_loop']);
        $this->assertSame(9011, (int)$pageOne[0]['players_profile']['slowest_player_id']);
    }

    public function testCleanupHistoryDeletesOldTickStatsAndLogs(): void
    {
        $this->service->modules();
        $configRepo = new TickModuleConfigRepository($this->db);
        $statsRepo = new TickStatsRepository($this->db);

        $configRepo->markFinished(
            'legal',
            1,
            'test',
            TickModuleConfigRepository::STATUS_SUCCESS,
            20,
            ['decided' => 1],
            null,
            false
        );
        $this->db->exec("UPDATE tick_module_run_logs SET created_at = '2000-01-01 00:00:00'");

        $statsRepo->save([
            'ran_at' => '2000-01-01 00:00:00',
            'tick_sequence' => 1,
            'source' => 'test',
            'duration_ms' => 10,
            'module_stats_data' => ['players' => ['players_processed' => 1]],
            'module_runs_data' => ['players' => ['status' => 'success', 'duration_ms' => 10]],
        ]);

        $deleted = $this->service->cleanupHistory(2);

        $this->assertSame(1, $deleted['stats_deleted']);
        $this->assertSame(1, $deleted['logs_deleted']);
        $this->assertSame(0, $this->service->countRecentTickStats());
        $this->assertSame(0, $this->service->countRecentLogs('legal'));
    }
}
