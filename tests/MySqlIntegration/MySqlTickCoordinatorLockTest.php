<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/TickCoordinator.php';

final class MySqlTickCoordinatorLockTest extends MySqlIntegrationTestCase
{
    private ?PDO $lockDb = null;

    protected function tearDown(): void
    {
        if ($this->lockDb instanceof PDO) {
            try {
                $stmt = $this->lockDb->prepare('SELECT RELEASE_LOCK(?)');
                $stmt->execute(['oilcorp_tick']);
            } catch (Throwable) {
            }
            $this->lockDb = null;
        }

        parent::tearDown();
    }

    public function testCoordinatorDoesNotRunWhenGlobalTickLockIsHeld(): void
    {
        $this->lockDb = $this->newConnection();
        $stmt = $this->lockDb->prepare('SELECT GET_LOCK(?, 0)');
        $stmt->execute(['oilcorp_tick']);
        $this->assertSame(1, (int)$stmt->fetchColumn());

        $coordinator = new TickCoordinator($this->db);
        $result = $coordinator->run('phpunit_lock');

        $this->assertTrue($coordinator->wasBusy());
        $this->assertFalse($coordinator->hadLockError());
        $this->assertSame(TickRunResult::STATUS_FAILED, $result->status);
        $this->assertSame("Tick skipped: another run in progress\n", $coordinator->summary());
    }

    public function testSingleModuleRunDoesNotWriteFullTickStatsOrSystemTickTimestamp(): void
    {
        $this->upsertConfig('last_tick_oil_price', '88.5');
        $this->upsertConfig('last_system_tick_at', '12345');
        $this->upsertConfig('tick_in_progress', '0');

        $beforeCount = (int)$this->db->query('SELECT COUNT(*) FROM tick_stats')->fetchColumn();

        $repo = new TickModuleConfigRepository($this->db);
        $module = TickRegistry::find('legal');
        $this->assertInstanceOf(TickModule::class, $module);
        $repo->syncModules([$module]);
        $repo->update('legal', false, 1, 10);

        $coordinator = new TickCoordinator($this->db);
        $result = $coordinator->runModule('legal', 'phpunit_module');

        $afterCount = (int)$this->db->query('SELECT COUNT(*) FROM tick_stats')->fetchColumn();

        $this->assertSame(TickRunResult::STATUS_SUCCESS, $result->status);
        $this->assertSame(TickRunResult::STATUS_DISABLED, $result->moduleRuns['legal']['status']);
        $this->assertSame(88.5, $result->oilPrice);
        $this->assertSame($beforeCount, $afterCount);
        $this->assertSame(12345.0, (float)$this->configValue('last_system_tick_at'));
        $this->assertSame(0.0, (float)($this->configValue('tick_in_progress') ?? '0'));
    }

    private function upsertConfig(string $key, string $value): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO well_config (`key`, `value`, `label`, `category`)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $stmt->execute([$key, $value, $key, 'phpunit']);
    }

    private function configValue(string $key): ?string
    {
        $stmt = $this->db->prepare('SELECT `value` FROM well_config WHERE `key` = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string)$value;
    }

    private function newConnection(): PDO
    {
        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        $dsn = 'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=' . $cfg['charset'];

        return new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}
