<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/Tick/TickCoordinator.php';

final class TickSequenceTemporaryPDO extends PDO
{
    public function exec(string $statement): int|false
    {
        return parent::exec(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE IF NOT EXISTS', $statement));
    }
}

final class MySqlTickSequenceTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $dsn = getenv('API_TEST_MYSQL_DSN');
        if (!$dsn) self::markTestSkipped('Set API_TEST_MYSQL_DSN to a test database.');
        $this->db = new TickSequenceTemporaryPDO($dsn, getenv('API_TEST_DB_USER') ?: 'root', getenv('API_TEST_DB_PASS') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        // Shadow game configuration without changing permanent tables.
        // Przeslon konfiguracje gry bez zmiany trwalych tabel.
        $this->db->exec('CREATE TEMPORARY TABLE well_config (
            `key` VARCHAR(100) PRIMARY KEY, `value` VARCHAR(255),
            `label` VARCHAR(255), `category` VARCHAR(100)
        ) ENGINE=InnoDB');
    }

    public function testFullTickSequenceAdvancesInsteadOfResetting(): void
    {
        $coordinator = new TickCoordinator($this->db);
        $next = new ReflectionMethod(TickCoordinator::class, 'nextRunSequence');
        self::assertSame(1, $next->invoke($coordinator));
        self::assertSame(2, $next->invoke($coordinator));
        self::assertSame(3, $next->invoke($coordinator));
    }

    public function testExistingSequenceSurvivesNewCoordinatorAndManualRead(): void
    {
        $this->db->exec("INSERT INTO well_config (`key`, `value`) VALUES ('tick_run_sequence', '41')");
        $next = new ReflectionMethod(TickCoordinator::class, 'nextRunSequence');
        $current = new ReflectionMethod(TickCoordinator::class, 'currentRunSequence');
        self::assertSame(42, $next->invoke(new TickCoordinator($this->db)));
        self::assertSame(42, $current->invoke(new TickCoordinator($this->db)));
        self::assertSame(43, $next->invoke(new TickCoordinator($this->db)));
        self::assertSame('43', $this->db->query("SELECT `value` FROM well_config WHERE `key` = 'tick_run_sequence'")->fetchColumn());
    }

    public function testIntervalOneRemainsDueAfterPreviousSuccessfulTick(): void
    {
        $this->db->exec('CREATE TEMPORARY TABLE tick_module_config (
            module_key VARCHAR(100) PRIMARY KEY, enabled INT, interval_ticks INT,
            max_items_per_run INT, last_status VARCHAR(20), last_run_tick BIGINT DEFAULT 0
        ) ENGINE=InnoDB');
        $this->db->exec("INSERT INTO tick_module_config
            VALUES ('training', 1, 1, 500, 'success', 0)");
        $coordinator = new TickCoordinator($this->db);
        $next = new ReflectionMethod(TickCoordinator::class, 'nextRunSequence');
        $first = $next->invoke($coordinator);
        $this->db->prepare("UPDATE tick_module_config SET last_run_tick = ? WHERE module_key = 'training'")
            ->execute([$first]);
        $ctx = new TickContext($this->db, new DateTimeImmutable(), 'test');
        $ctx->runSequence = $next->invoke($coordinator);
        $module = TickRegistry::find('training');
        self::assertInstanceOf(TickModule::class, $module);
        $scheduler = new TickModuleScheduler(new TickModuleConfigRepository($this->db));
        self::assertTrue($scheduler->decision($module, $ctx)['run']);
    }
}
