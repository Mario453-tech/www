<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/HomeDashboardQuery.php';

final class HomeDashboardQueryTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = class_exists(\Pdo\Sqlite::class)
            ? new \Pdo\Sqlite('sqlite::memory:') : new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $dateFormat = static fn(string $date): string => substr($date, 0, 7);
        if (method_exists($this->db, 'createFunction')) {
            $this->db->createFunction('DATE_FORMAT', $dateFormat, 2);
        } else {
            $this->db->sqliteCreateFunction('DATE_FORMAT', $dateFormat, 2);
        }
        $this->db->exec('CREATE TABLE finance_logs (
            player_id INTEGER, tick_at TEXT, produced_bbl REAL, revenue REAL, net_profit REAL,
            opex REAL, salary_cost REAL, transport_cost REAL, hub_usage_cost REAL,
            incident_cost REAL, tax REAL, transport_loss_bbl REAL
        )');
        $this->db->exec('CREATE TABLE well_road_trips (
            player_id INTEGER, status TEXT, arrived_at TEXT, eta_at TEXT
        )');
    }

    public function testPeriodIsAllowlisted(): void
    {
        self::assertSame('7d', HomeDashboardQuery::normalizePeriod('7d'));
        self::assertSame('1y', HomeDashboardQuery::normalizePeriod('1y'));
        self::assertSame('30d', HomeDashboardQuery::normalizePeriod('1d'));
        self::assertSame('30d', HomeDashboardQuery::normalizePeriod("7d' OR 1=1"));
    }

    public function testPlayerScopedHistoryAndWellSummary(): void
    {
        $date = (new DateTimeImmutable('-2 hours'))->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare('INSERT INTO finance_logs VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([7, $date, 20, 1000, 700, 100, 50, 50, 50, 25, 25, 2]);
        $stmt->execute([8, $date, 99999, 99999, 99999, 0, 0, 0, 0, 0, 0, 0]);
        $trip = $this->db->prepare('INSERT INTO well_road_trips VALUES (?, ?, ?, ?)');
        $trip->execute([7, 'delivered', $date, $date]);
        $trip->execute([8, 'delivered', $date, $date]);

        $wells = [
            ['id' => 11, 'status' => 'active', 'technical_condition' => 95,
                'transport_type' => 'rurociag', 'well_name' => 'E-1', 'base_production_per_hour' => 48],
            ['id' => 12, 'status' => 'broken', 'technical_condition' => 0,
                'transport_type' => 'ciezarowki', 'base_production_per_hour' => 20],
        ];
        $model = (new HomeDashboardQuery($this->db))->get(7, '7d', $wells);

        self::assertSame('ready', $model['state']);
        self::assertSame(240.0, $model['overview']['production_rate']);
        self::assertSame(12000.0, $model['overview']['revenue_rate']);
        self::assertSame(1, $model['wells']['active']);
        self::assertSame(1, $model['wells']['critical']);
        self::assertSame('E-1', $model['wells']['top'][0]['name']);
        self::assertSame(1, $model['logistics']['mix']['pipeline']);
        self::assertSame(100.0, $model['logistics']['on_time_pct']);
        self::assertCount(1, $model['series']);
    }

    public function testEmptyHistoryIsNotPresentedAsZeroRate(): void
    {
        $model = (new HomeDashboardQuery($this->db))->get(7, '30d', []);
        self::assertSame('empty', $model['state']);
        self::assertNull($model['overview']['production_rate']);
        self::assertNull($model['finance']['revenue_rate']);
        self::assertSame([], $model['series']);
    }

    public function testYearPeriodUsesMonthlyBuckets(): void
    {
        $date = (new DateTimeImmutable('-40 days'))->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare('INSERT INTO finance_logs VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([7, $date, 10, 100, 80, 5, 5, 5, 2, 1, 2, 0]);
        $model = (new HomeDashboardQuery($this->db))->get(7, '1y', []);
        self::assertSame('ready', $model['state']);
        self::assertSame(substr($date, 0, 7), $model['series'][0]['label']);
    }
}
