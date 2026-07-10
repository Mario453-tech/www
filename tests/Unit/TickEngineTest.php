<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/TickEngine.php';

final class TickEngineTest extends BaseTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new PDO('sqlite::memory:');
        $GLOBALS['tick_engine_order'] = [];
        TickRegistry::clearCache();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['tick_engine_order']);
        TickRegistry::clearCache();
        parent::tearDown();
    }

    public function testRunAllUsesOrderAndMergesStats(): void
    {
        $dir = $this->temporaryModuleDir();
        $this->writeModule($dir, 'EngineSecond', 'second', 20, TickFailurePolicy::CONTINUE, false, 2);
        $this->writeModule($dir, 'EngineFirst', 'first', 10, TickFailurePolicy::CONTINUE, false, 1);

        $ctx = $this->context();
        $result = (new TickEngine($dir))->runAll($ctx);

        $this->assertSame(['first', 'second'], $GLOBALS['tick_engine_order']);
        $this->assertSame(TickRunResult::STATUS_SUCCESS, $result->status);
        $this->assertSame(1, $ctx->collectStats()['first']['value']);
        $this->assertSame(2, $ctx->collectStats()['second']['value']);
        $this->assertSame(['first', 'second'], array_keys($result->moduleRuns));
    }

    public function testContinueFailureRunsLaterModules(): void
    {
        $dir = $this->temporaryModuleDir();
        $this->writeModule($dir, 'EngineContinueFail', 'continue_fail', 10, TickFailurePolicy::CONTINUE, true, 0);
        $this->writeModule($dir, 'EngineAfterContinue', 'after_continue', 20, TickFailurePolicy::CONTINUE, false, 2);

        $result = (new TickEngine($dir))->runAll($this->context());

        $this->assertSame(TickRunResult::STATUS_PARTIAL, $result->status);
        $this->assertSame(['continue_fail', 'after_continue'], $GLOBALS['tick_engine_order']);
        $this->assertArrayHasKey('after_continue', $result->moduleRuns);
    }

    public function testStopFailureSkipsLaterModules(): void
    {
        $dir = $this->temporaryModuleDir();
        $this->writeModule($dir, 'EngineStopFail', 'stop_fail', 10, TickFailurePolicy::STOP, true, 0);
        $this->writeModule($dir, 'EngineAfterStop', 'after_stop', 20, TickFailurePolicy::CONTINUE, false, 2);

        $result = (new TickEngine($dir))->runAll($this->context());

        $this->assertTrue($result->hasCriticalFailure());
        $this->assertSame(['stop_fail'], $GLOBALS['tick_engine_order']);
        $this->assertArrayNotHasKey('after_stop', $result->moduleRuns);
    }

    public function testRunOneMissingModuleFailsClosed(): void
    {
        $result = (new TickEngine($this->temporaryModuleDir()))->runOne('missing', $this->context());

        $this->assertTrue($result->hasCriticalFailure());
        $this->assertSame('registry', $result->errors[0]['module']);
    }

    public function testRunAllWithNoModulesFailsClosed(): void
    {
        $result = (new TickEngine($this->temporaryModuleDir()))->runAll($this->context());

        $this->assertTrue($result->hasCriticalFailure());
        $this->assertSame('No tick modules discovered.', $result->errors[0]['message']);
    }

    public function testFailureRollsBackDanglingTransaction(): void
    {
        $dir = $this->temporaryModuleDir();
        $this->writeModule(
            $dir,
            'EngineTransactionFail',
            'transaction_fail',
            10,
            TickFailurePolicy::CONTINUE,
            true,
            0,
            true
        );

        (new TickEngine($dir))->runAll($this->context());

        $this->assertFalse($this->db->inTransaction());
    }

    private function context(): TickContext
    {
        return new TickContext($this->db, new DateTimeImmutable('2026-07-10 12:00:00'), 'test');
    }

    private function temporaryModuleDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'oil_tick_engine_' . str_replace('.', '', uniqid('', true));
        mkdir($dir, 0700, true);
        return $dir;
    }

    private function writeModule(
        string $dir,
        string $baseClass,
        string $key,
        int $order,
        TickFailurePolicy $policy,
        bool $throws,
        int $value,
        bool $startsTransaction = false
    ): void {
        $className = $baseClass . str_replace('.', '', uniqid('', true));
        $runBody = "\$GLOBALS['tick_engine_order'][] = '{$key}';";
        if ($startsTransaction) {
            $runBody .= "\$ctx->db->beginTransaction();";
        }
        if ($throws) {
            $runBody .= "throw new RuntimeException('planned failure');";
        }
        $code = "<?php\nfinal class {$className} implements TickModule {"
            . "public function key(): string { return '{$key}'; }"
            . "public function order(): int { return {$order}; }"
            . "public function failurePolicy(): TickFailurePolicy { return TickFailurePolicy::{$policy->name}; }"
            . "public function run(TickContext \$ctx): void { {$runBody} }"
            . "public function stats(): array { return ['value' => {$value}]; }"
            . "}\n";
        file_put_contents($dir . DIRECTORY_SEPARATOR . $className . '.php', $code);
    }
}
