<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/TickEngine.php';
require_once dirname(__DIR__, 2) . '/src/Tick/TickModuleScheduler.php';

final class TickModuleSchedulerTest extends BaseTestCase
{
    private PDO $db;
    private TickModuleConfigRepository $repository;
    private TickModuleScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new PDO('sqlite::memory:');
        $this->repository = new TickModuleConfigRepository($this->db);
        $this->scheduler = new TickModuleScheduler($this->repository);
        $GLOBALS['scheduled_module_runs'] = 0;
        TickRegistry::clearCache();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['scheduled_module_runs']);
        TickRegistry::clearCache();
        parent::tearDown();
    }

    public function testIntervalUsesTickSequenceAndPersistsLogs(): void
    {
        $dir = $this->moduleDir('ScheduledIntervalModule', 'scheduled_interval');
        $module = TickRegistry::find('scheduled_interval', $dir);
        $this->assertInstanceOf(TickModule::class, $module);
        $this->scheduler->sync([$module]);
        $this->repository->update('scheduled_interval', true, 2, 75);

        $ctx = $this->context(10);
        $first = (new TickEngine($dir, $this->scheduler))->runAll($ctx);
        $this->assertSame(TickRunResult::STATUS_SUCCESS, $first->status);
        $this->assertSame(1, $GLOBALS['scheduled_module_runs']);
        $this->assertSame(75, $ctx->moduleLimit('scheduled_interval'));

        $second = (new TickEngine($dir, $this->scheduler))->runAll($this->context(11));
        $this->assertSame(TickRunResult::STATUS_SKIPPED, $second->moduleRuns['scheduled_interval']['status']);
        $this->assertSame(1, $GLOBALS['scheduled_module_runs']);
        $this->assertSame('skipped', $this->repository->find('scheduled_interval')['last_status']);

        (new TickEngine($dir, $this->scheduler))->runAll($this->context(12));
        $this->assertSame(2, $GLOBALS['scheduled_module_runs']);

        $logs = $this->repository->logs('scheduled_interval');
        $this->assertCount(2, $logs);
        $this->assertSame('success', $logs[0]['status']);
        $this->assertSame('success', $logs[1]['status']);
    }

    public function testDisabledModuleIsNotExecuted(): void
    {
        $dir = $this->moduleDir('ScheduledDisabledModule', 'scheduled_disabled');
        $module = TickRegistry::find('scheduled_disabled', $dir);
        $this->assertInstanceOf(TickModule::class, $module);
        $this->scheduler->sync([$module]);
        $this->repository->update('scheduled_disabled', false, 1, 50);

        $result = (new TickEngine($dir, $this->scheduler))->runAll($this->context(1));

        $this->assertSame(TickRunResult::STATUS_DISABLED, $result->moduleRuns['scheduled_disabled']['status']);
        $this->assertSame(0, $GLOBALS['scheduled_module_runs']);
        $this->assertSame('disabled', $this->repository->find('scheduled_disabled')['last_status']);
    }

    public function testForcedRunBypassesIntervalButNotDisabledState(): void
    {
        $dir = $this->moduleDir('ScheduledForcedModule', 'scheduled_forced');
        $module = TickRegistry::find('scheduled_forced', $dir);
        $this->assertInstanceOf(TickModule::class, $module);
        $this->scheduler->sync([$module]);
        $this->repository->update('scheduled_forced', true, 24, 33);
        $this->repository->markStarted('scheduled_forced', 100, new DateTimeImmutable());

        $ctx = $this->context(101);
        $result = (new TickEngine($dir, $this->scheduler))->runOne('scheduled_forced', $ctx, null, true);

        $this->assertSame(TickRunResult::STATUS_SUCCESS, $result->moduleRuns['scheduled_forced']['status']);
        $this->assertSame(1, $GLOBALS['scheduled_module_runs']);
        $this->assertSame(33, $ctx->moduleLimit('scheduled_forced'));

        $this->repository->update('scheduled_forced', false, 24, 33);
        $disabled = (new TickEngine($dir, $this->scheduler))->runOne(
            'scheduled_forced',
            $this->context(102),
            null,
            true
        );
        $this->assertSame(TickRunResult::STATUS_DISABLED, $disabled->moduleRuns['scheduled_forced']['status']);
        $this->assertSame(1, $GLOBALS['scheduled_module_runs']);
    }

    public function testRecommendedSettingsAreRestored(): void
    {
        $dir = $this->moduleDir('ScheduledRecommendedB2BModule', 'b2b_contracts');
        $module = TickRegistry::find('b2b_contracts', $dir);
        $this->assertInstanceOf(TickModule::class, $module);
        $this->scheduler->sync([$module]);
        $this->repository->update('b2b_contracts', false, 99, 7);

        $this->repository->restoreRecommended('b2b_contracts');
        $config = $this->repository->find('b2b_contracts');

        $this->assertSame(1, (int)$config['enabled']);
        $this->assertSame(2, (int)$config['interval_ticks']);
        $this->assertSame(200, (int)$config['max_items_per_run']);
    }

    public function testRunAllWithSchedulerRequiresPositiveSequence(): void
    {
        $dir = $this->moduleDir('ScheduledSequenceModule', 'scheduled_sequence');
        $result = (new TickEngine($dir, $this->scheduler))->runAll($this->context(0));

        $this->assertSame(TickRunResult::STATUS_FAILED, $result->status);
        $this->assertSame('registry', $result->errors[0]['module']);
        $this->assertSame(0, $GLOBALS['scheduled_module_runs']);
    }

    public function testCriticalModuleCannotBeDisabledOrSkipped(): void
    {
        $dir = $this->moduleDir('ScheduledCriticalModule', 'scheduled_critical', 'STOP');
        $module = TickRegistry::find('scheduled_critical', $dir);
        $this->assertInstanceOf(TickModule::class, $module);
        $this->scheduler->sync([$module]);
        $this->repository->update('scheduled_critical', false, 1, 50);

        $disabled = (new TickEngine($dir, $this->scheduler))->runAll($this->context(1));
        $this->assertSame(TickRunResult::STATUS_FAILED, $disabled->status);
        $this->assertSame('scheduled_critical', $disabled->errors[0]['module']);
        $this->assertSame('error', $this->repository->find('scheduled_critical')['last_status']);

        $this->repository->update('scheduled_critical', true, 10, 50);
        $this->repository->markFinished(
            'scheduled_critical',
            10,
            'test',
            TickModuleConfigRepository::STATUS_SUCCESS,
            1,
            [],
            null,
            false
        );

        $skipped = (new TickEngine($dir, $this->scheduler))->runAll($this->context(11));
        $this->assertSame(TickRunResult::STATUS_FAILED, $skipped->status);
        $this->assertSame('scheduled_critical', $skipped->errors[0]['module']);
        $this->assertSame(0, $GLOBALS['scheduled_module_runs']);
    }

    public function testFailedRunDoesNotAdvanceInterval(): void
    {
        $dir = $this->moduleDir('ScheduledFailingModule', 'scheduled_failing', 'CONTINUE', true);
        $module = TickRegistry::find('scheduled_failing', $dir);
        $this->assertInstanceOf(TickModule::class, $module);
        $this->scheduler->sync([$module]);
        $this->repository->update('scheduled_failing', true, 10, 50);

        $first = (new TickEngine($dir, $this->scheduler))->runAll($this->context(20));
        $this->assertSame(TickRunResult::STATUS_PARTIAL, $first->status);
        $this->assertSame('error', $this->repository->find('scheduled_failing')['last_status']);
        $this->assertSame(0, (int)$this->repository->find('scheduled_failing')['last_run_tick']);

        $second = (new TickEngine($dir, $this->scheduler))->runAll($this->context(21));
        $this->assertSame(TickRunResult::STATUS_PARTIAL, $second->status);
        $this->assertSame(2, $GLOBALS['scheduled_module_runs']);
    }

    private function context(int $sequence): TickContext
    {
        $ctx = new TickContext($this->db, new DateTimeImmutable('2026-07-11 02:00:00'), 'test');
        $ctx->runSequence = $sequence;
        return $ctx;
    }

    private function moduleDir(
        string $className,
        string $key,
        string $policy = 'CONTINUE',
        bool $throws = false
    ): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oil_tick_schedule_' . str_replace('.', '', uniqid('', true));
        mkdir($dir, 0700, true);
        $runBody = $throws
            ? "\$GLOBALS['scheduled_module_runs']++; throw new RuntimeException('planned failure');"
            : "\$GLOBALS['scheduled_module_runs']++;";
        $code = "<?php\nfinal class {$className} implements TickModule {"
            . "public function key(): string { return '{$key}'; }"
            . 'public function order(): int { return 10; }'
            . "public function failurePolicy(): TickFailurePolicy { return TickFailurePolicy::{$policy}; }"
            . "public function run(TickContext \$ctx): void { {$runBody} }"
            . "public function stats(): array { return ['done' => 1]; }"
            . "}\n";
        file_put_contents($dir . DIRECTORY_SEPARATOR . $className . '.php', $code);
        return $dir;
    }
}
