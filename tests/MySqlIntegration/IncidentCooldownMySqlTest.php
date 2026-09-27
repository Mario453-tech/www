<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/IncidentService.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class IncidentCooldownMySqlTest extends TestCase
{
    private PDO $db;
    private const NOW = 1800000000;

    protected function setUp(): void
    {
        $dsn = getenv('API_TEST_MYSQL_DSN');
        if (!$dsn) {
            self::markTestSkipped('Set API_TEST_MYSQL_DSN to an isolated MySQL test database.');
        }
        $this->db = new PDO($dsn, getenv('API_TEST_DB_USER') ?: 'root', getenv('API_TEST_DB_PASS') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->db->exec("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE'");
        $this->db->exec('SET timestamp=' . self::NOW);
        $this->db->exec('CREATE TEMPORARY TABLE wells (id INT PRIMARY KEY, player_id INT, created_at DATETIME,
            ticks_since_incident INT DEFAULT 0, post_incident_risk_boost FLOAT DEFAULT 0,
            technical_condition FLOAT DEFAULT 100, risk_score FLOAT DEFAULT 0, active_layer_id INT NULL,
            incident_cooldown_started_at DATETIME NULL)');
        $this->db->exec('CREATE TEMPORARY TABLE players (id INT PRIMARY KEY, last_tick_at DATETIME)');
        $this->db->exec('INSERT INTO players VALUES (1,NOW())');
        $this->db->exec('CREATE TEMPORARY TABLE well_incidents (id INT AUTO_INCREMENT PRIMARY KEY,
            well_id INT, player_id INT, level VARCHAR(10), cause_type VARCHAR(20), prod_drop INT, hours INT,
            deg_damage INT, cost INT, risk_add INT, auto_repair INT, hse_active INT, message TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP, repaired_at DATETIME NULL,
            KEY idx_well(well_id,created_at))');
        $this->db->exec('CREATE TEMPORARY TABLE well_config (`key` VARCHAR(100) PRIMARY KEY, `value` VARCHAR(100))');
        $this->db->exec("INSERT INTO well_config VALUES ('incident_immunity_ticks','6'),
            ('incident_pressure_growth_pct','0.5'),('incident_pressure_cap_pct','100')");
        $ref = new ReflectionClass(Database::class);
        $database = $ref->newInstanceWithoutConstructor();
        $ref->getProperty('pdo')->setValue($database, $this->db);
        $ref->getProperty('instance')->setValue(null, $database);
        GameLog::setEnabled(false);
    }

    private function seed(int $age, int $counter = 9999): void
    {
        $this->db->prepare('INSERT INTO wells (id,player_id,created_at,ticks_since_incident) VALUES (1,1,DATE_SUB(NOW(), INTERVAL ? SECOND),?)')
            ->execute([$age + 86400, $counter]);
        $this->db->prepare("INSERT INTO well_incidents (well_id,player_id,level,created_at) VALUES (1,1,'minor',DATE_SUB(NOW(), INTERVAL ? SECOND))")
            ->execute([$age]);
    }

    private function service(string $forcedLevel = 'minor'): IncidentService
    {
        foreach (['micro','minor','medium','major'] as $level) {
            $this->db->prepare('REPLACE INTO well_config VALUES (?,?)')
                ->execute(['incident_cfg_' . $level . '_base_chance', $level === $forcedLevel ? '100000' : '0']);
        }
        return new IncidentService();
    }

    private function tick(IncidentService $service, float $hours = 1 / 12, int $player = 1): array
    {
        $well = $this->db->query('SELECT * FROM wells WHERE id=1')->fetch();
        $well += ['reservoir_remaining'=>1000,'reservoir_max'=>1000,'equipment_tier'=>'standard'];
        return $service->processTick(1, $player, $hours, $well);
    }

    /** @dataProvider protectedLevels */
    public function testTimestampPreventsCounterAndCatchupFromEndingCooldownEarly(string $level): void
    {
        $this->seed(1799);
        self::assertNull($this->tick($this->service($level), 4.0)['incident']);
        self::assertSame(5, (int)$this->db->query('SELECT ticks_since_incident FROM wells')->fetchColumn());
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM well_incidents')->fetchColumn());
    }

    public static function protectedLevels(): array
    {
        return [['minor'], ['medium'], ['major']];
    }

    public function testShortRunsDoNotConsumeFiveMinutesEach(): void
    {
        $this->seed(0, 0);
        for ($minute = 1; $minute <= 10; $minute++) {
            $this->db->exec('SET timestamp=' . (self::NOW + $minute * 60));
            self::assertNull($this->tick($this->service(), 1 / 60)['incident']);
        }
        self::assertSame(2, (int)$this->db->query('SELECT ticks_since_incident FROM wells')->fetchColumn());
    }

    public function testBoundaryAndExpiryUseConfiguredDuration(): void
    {
        $this->seed(1800, 9999);
        self::assertNull($this->tick($this->service())['incident']);
        $this->db->exec('SET timestamp=' . (self::NOW + 1));
        self::assertSame('minor', $this->tick($this->service())['incident']['level']);
        self::assertSame(0, (int)$this->db->query('SELECT ticks_since_incident FROM wells')->fetchColumn());
        self::assertNull($this->tick($this->service())['incident']);
    }

    public function testMicroCanFireDuringCooldownWithoutResettingIt(): void
    {
        $this->seed(600, 2);
        self::assertSame('micro', $this->tick($this->service('micro'))['incident']['level']);
        self::assertSame(2, (int)$this->db->query('SELECT ticks_since_incident FROM wells')->fetchColumn());
        $this->db->exec('SET timestamp=' . (self::NOW + 1201));
        self::assertSame('minor', $this->tick($this->service())['incident']['level']);
    }

    public function testCooldownSettingIsLoadedAndZeroDisablesProtection(): void
    {
        $this->seed(300, 1);
        $this->db->exec("UPDATE well_config SET `value`='12' WHERE `key`='incident_immunity_ticks'");
        $service = $this->service();
        self::assertSame(12, $service->immunityTicks);
        self::assertNull($this->tick($service)['incident']);
        $this->db->exec("UPDATE well_config SET `value`='0' WHERE `key`='incident_immunity_ticks'");
        self::assertSame('minor', $this->tick($this->service())['incident']['level']);
    }

    public function testNewWellAndForeignHistoryRemainIsolated(): void
    {
        $this->db->exec('INSERT INTO wells (id,player_id,created_at,ticks_since_incident) VALUES (1,1,NOW(),999)');
        $this->db->exec("INSERT INTO well_incidents (well_id,player_id,level,created_at) VALUES (1,2,'minor',DATE_SUB(NOW(),INTERVAL 10 DAY))");
        self::assertNull($this->tick($this->service(), 24)['incident']);
        self::assertSame(0, (int)$this->db->query('SELECT ticks_since_incident FROM wells')->fetchColumn());
        self::assertNull($this->tick($this->service('micro'), 1, 2)['incident']);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM well_incidents')->fetchColumn());
    }

    public function testZeroElapsedTimeDoesNotAdvanceOrRoll(): void
    {
        $this->seed(0, 0);
        self::assertNull($this->tick($this->service('micro'), 0)['incident']);
        self::assertSame(0, (int)$this->db->query('SELECT ticks_since_incident FROM wells')->fetchColumn());
    }

    public function testCatchupRollExcludesProtectedPartOfInterval(): void
    {
        $this->seed(1860, 0);
        $this->service();
        $this->db->exec("UPDATE well_config SET `value`='1' WHERE `key`='incident_cfg_minor_base_chance'");
        mt_srand(1234);
        self::assertNull($this->tick(new IncidentService(), 4)['incident']);
        self::assertSame(6, (int)$this->db->query('SELECT ticks_since_incident FROM wells')->fetchColumn());
    }

    public function testFailedHistoryWriteCannotSilentlyApplyAnIncident(): void
    {
        $this->seed(7200);
        $service = $this->service();
        $service->preloadCooldowns(1);
        $this->db->exec('ALTER TABLE well_incidents MODIFY message VARCHAR(1)');
        $this->expectException(PDOException::class);
        $this->tick($service);
    }

    public function testHistoryDeletionDoesNotChangeCooldownOrPressureClock(): void
    {
        $this->seed(600, 2);
        $this->service()->preloadCooldowns(1);
        $this->db->exec('DELETE FROM well_incidents');
        self::assertNull($this->tick($this->service())['incident']);
        $this->db->exec('SET timestamp=' . (self::NOW + 4 * 86400));
        $service = $this->service('none');
        self::assertNull($this->tick($service)['incident']);
        self::assertSame(1154, (int)$this->db->query('SELECT ticks_since_incident FROM wells')->fetchColumn());
    }

    public function testSchemaBootstrapIsIdempotentAndCannotCommitAnActiveTransaction(): void
    {
        $this->db->exec('ALTER TABLE wells DROP COLUMN incident_cooldown_started_at');
        IncidentService::ensureCooldownSchema($this->db);
        IncidentService::ensureCooldownSchema($this->db);
        self::assertNotFalse($this->db->query("SHOW COLUMNS FROM wells LIKE 'incident_cooldown_started_at'")->fetch());
        $this->db->exec('ALTER TABLE wells DROP COLUMN incident_cooldown_started_at');
        $this->db->beginTransaction();
        try {
            IncidentService::ensureCooldownSchema($this->db);
            self::fail('Schema changes inside an active tick must fail');
        } catch (RuntimeException $e) {
            self::assertTrue($this->db->inTransaction());
        } finally {
            $this->db->rollBack();
        }
    }
}
