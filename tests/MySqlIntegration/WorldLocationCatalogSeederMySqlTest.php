<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/WorldLocationCatalogSeeder.php';

final class WorldLocationCatalogSeederMySqlTest extends MySqlIntegrationTestCase
{
    public function testSeedsAllRegionsWithoutChangingExistingEntries(): void
    {
        $this->db->beginTransaction();
        try {
            $codes = ['middle_east', 'russia', 'africa', 'usa_canada', 'north_europe', 'southeast_asia', 'latam'];
            $present = $this->db->query('SELECT code FROM world_regions')->fetchAll(PDO::FETCH_COLUMN);
            $insert = $this->db->prepare('INSERT INTO world_regions (code, name) VALUES (?, ?)');
            foreach ($codes as $code) {
                if (!in_array($code, $present, true)) {
                    $insert->execute([$code, $code]);
                }
            }

            $this->db->exec("DELETE FROM well_config WHERE `key` = 'world_locations_small_medium_v2'");
            $before = (int) $this->db->query('SELECT COUNT(*) FROM world_locations')->fetchColumn();
            $seeder = new WorldLocationCatalogSeeder($this->db);
            $seeder->seed();
            $after = (int) $this->db->query('SELECT COUNT(*) FROM world_locations')->fetchColumn();
            $this->assertGreaterThanOrEqual($before, $after);
            $this->assertLessThanOrEqual($before + 140, $after);
            $seeder->seed();
            $this->assertSame($after, (int) $this->db->query('SELECT COUNT(*) FROM world_locations')->fetchColumn());
            $this->assertSame(1, (int) $this->db->query(
                "SELECT COUNT(*) FROM well_config WHERE `key` = 'world_locations_small_medium_v2'"
            )->fetchColumn());
        } finally {
            $this->db->rollBack();
        }
    }
}
