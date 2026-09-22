<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/WorldLocationCatalogSeeder.php';

final class WorldLocationCatalogSeederTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->db->exec('CREATE TABLE world_regions (id INTEGER PRIMARY KEY, code TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE world_locations (
            id INTEGER PRIMARY KEY AUTOINCREMENT, region_id INTEGER NOT NULL, name TEXT NOT NULL,
            country_code TEXT, latitude REAL, longitude REAL, oil_richness REAL,
            well_type TEXT, tier TEXT, entry_cost_override REAL, available INTEGER
        )');
        $this->db->exec('CREATE TABLE well_config (`key` TEXT PRIMARY KEY, `value` TEXT, label TEXT, category TEXT)');
        $codes = ['middle_east', 'russia', 'africa', 'usa_canada', 'north_europe', 'southeast_asia', 'latam'];
        $insert = $this->db->prepare('INSERT INTO world_regions (id, code) VALUES (?, ?)');
        foreach ($codes as $index => $code) {
            $insert->execute([$index + 1, $code]);
        }
    }

    public function testAddsSmallAndMediumLocationsOnceInEveryRegion(): void
    {
        $seeder = new WorldLocationCatalogSeeder($this->db);
        $seeder->seed();
        $seeder->seed();

        $counts = $this->db->query(
            'SELECT region_id, tier, COUNT(*) AS total FROM world_locations GROUP BY region_id, tier'
        )->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(14, $counts);
        foreach ($counts as $row) {
            $this->assertSame(10, (int) $row['total']);
        }
        $this->assertSame(140, (int) $this->db->query('SELECT COUNT(*) FROM world_locations')->fetchColumn());
    }

    public function testPreservesExistingDisabledLocation(): void
    {
        $this->db->exec("INSERT INTO world_locations (region_id, name, available) VALUES (1, 'Awali', 0)");
        (new WorldLocationCatalogSeeder($this->db))->seed();

        $existing = $this->db->query("SELECT available FROM world_locations WHERE name = 'Awali'")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([0], array_map('intval', $existing));
        $this->assertSame(140, (int) $this->db->query('SELECT COUNT(*) FROM world_locations')->fetchColumn());
    }

    public function testMissingRegionRollsBackAndCanBeRetried(): void
    {
        $this->db->exec("DELETE FROM world_regions WHERE code = 'latam'");
        $seeder = new WorldLocationCatalogSeeder($this->db);
        try {
            $seeder->seed();
            $this->fail('Missing region must fail');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('latam', $e->getMessage());
        }
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM world_locations')->fetchColumn());
        $this->db->exec("INSERT INTO world_regions (id, code) VALUES (7, 'latam')");
        $seeder->seed();
        $this->assertSame(140, (int) $this->db->query('SELECT COUNT(*) FROM world_locations')->fetchColumn());
    }
}
