<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/PlayersSection.php';

final class TickFaultPdo extends PDO
{
    public string $failSql = '';
    /** @var list<string> */
    public array $unsafeStatements = [];

    public function exec(string $statement): int|false
    {
        if ($this->inTransaction() && preg_match('/^\s*(?:ALTER|CREATE|DROP|TRUNCATE|BEGIN|START|COMMIT)\b/i', $statement)) {
            $this->unsafeStatements[] = $statement;
        }
        return parent::exec($statement);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if ($this->failSql !== '' && str_contains($query, $this->failSql)) {
            throw new PDOException('Injected tick persistence failure');
        }
        return parent::prepare($query, $options);
    }
}

final class MySqlPlayerTickAtomicityTest extends MySqlIntegrationTestCase
{
    private PDO $originalPdo;

    protected function setUp(): void
    {
        parent::setUp();
        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        $this->db = new TickFaultPdo('mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=' . $cfg['charset'],
            $cfg['user'], $cfg['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $property = new ReflectionProperty(Database::class, 'pdo');
        $this->originalPdo = $property->getValue(Database::getInstance());
        $property->setValue(Database::getInstance(), $this->db);
    }

    protected function tearDown(): void
    {
        $this->db->failSql = '';
        (new ReflectionProperty(Database::class, 'pdo'))->setValue(Database::getInstance(), $this->originalPdo);
        $id = $this->getTrackedIds()['playerId'];
        foreach (['bank_transactions' => 'from_player_id', 'storage' => 'player_id', 'player_meta' => 'player_id'] as $table => $column) {
            $this->db->prepare("DELETE FROM {$table} WHERE {$column} = ?")->execute([$id]);
        }
        parent::tearDown();
    }

    /** @dataProvider failures */
    public function testPersistenceFailureDoesNotAdvancePeriodAndCanBeRetried(string $failure): void
    {
        $id = $this->seedPlayer();
        $wellId = $this->getTrackedIds()['wellId'];
        $this->seedWell($id, $wellId, 'equipment_swap');
        $this->db->prepare("UPDATE wells SET equipment_swap_until = '2000-01-01 00:00:00', equipment_swap_prev_status = 'active' WHERE id = ? AND player_id = ?")->execute([$wellId, $id]);
        $before = '2026-09-23 10:00:00';
        $this->db->prepare('UPDATE players SET last_tick_at = ?, last_active_at = ? WHERE id = ?')->execute([$before, $before, $id]);
        $this->db->prepare('INSERT INTO storage (player_id, capacity, used) VALUES (?, 1000, 100)')->execute([$id]);
        $section = new PlayersSection($this->db, new DateTime('2026-09-23 10:05:00'), 70.0, ['production' => 1.0, 'opex' => 1.0, 'risk' => 1.0]);
        $process = new ReflectionMethod(PlayersSection::class, 'processPlayer');
        $this->db->failSql = $failure;
        try {
            $process->invoke($section, ['id' => $id]);
            self::fail('Expected injected persistence failure');
        } catch (PDOException $e) {
            self::assertSame('Injected tick persistence failure', $e->getMessage());
        }
        $this->db->failSql = '';
        $state = $this->db->query('SELECT cash, last_tick_at FROM players WHERE id = ' . $id)->fetch();
        self::assertSame([], $this->db->unsafeStatements, 'No implicit commit statements in a player transaction');
        self::assertSame($before, $state['last_tick_at']);
        self::assertSame(50000000.0, (float) $state['cash']);
        self::assertSame('equipment_swap', $this->db->query('SELECT status FROM wells WHERE id = ' . $wellId)->fetchColumn());
        self::assertSame('2000-01-01 00:00:00', $this->db->query('SELECT equipment_swap_until FROM wells WHERE id = ' . $wellId)->fetchColumn());
        self::assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM finance_logs WHERE player_id = ' . $id)->fetchColumn());
        $process->invoke($section, ['id' => $id]);
        $process->invoke($section, ['id' => $id]);
        self::assertSame('2026-09-23 10:05:00', $this->db->query('SELECT last_tick_at FROM players WHERE id = ' . $id)->fetchColumn());
        self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM finance_logs WHERE player_id = ' . $id)->fetchColumn());
        self::assertNotSame('equipment_swap', $this->db->query('SELECT status FROM wells WHERE id = ' . $wellId)->fetchColumn());
        self::assertNull($this->db->query('SELECT equipment_swap_until FROM wells WHERE id = ' . $wellId)->fetchColumn());
    }

    /** @return array<string, array{string}> */
    public static function failures(): array
    {
        return ['storage' => ['UPDATE storage SET used'], 'finance history' => ['INSERT INTO finance_logs'],
            'payroll' => ['SUM(ec.salary + ec.bonus)'], 'road recovery' => ['SELECT id, well_id, delivered_bbl'],
            'financial state' => ['UPDATE players SET financial_state']];
    }
}
