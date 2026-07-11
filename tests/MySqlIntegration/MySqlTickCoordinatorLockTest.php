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
