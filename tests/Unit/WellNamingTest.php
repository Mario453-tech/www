<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/WellNaming.php';
require_once dirname(__DIR__, 2) . '/src/WellNamingMigrationService.php';

final class WellNamingTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('CREATE TABLE world_regions (id INTEGER PRIMARY KEY, code TEXT)');
        $this->db->exec('CREATE TABLE wells (id INTEGER PRIMARY KEY, player_id INTEGER, region_id INTEGER, well_name TEXT)');
        $this->db->exec('INSERT INTO world_regions VALUES (1, "north_europe"), (2, "southeast_asia")');
        $this->db->exec('INSERT INTO wells VALUES
            (11, 7, 1, "Rumaila"), (12, 7, 1, "Bobrka"),
            (13, 7, 2, "Bula"), (14, 8, 1, "Wietze")');
    }

    public function testRegionPrefixesAndPerPlayerRegionSequences(): void
    {
        self::assertSame('E-1', WellNaming::name('north_europe', 1));
        self::assertSame('AP-2', WellNaming::name('southeast_asia', 2));
        self::assertSame('ME-1', WellNaming::name('middle_east', 1));
        self::assertSame('W-1', WellNaming::name(null, 1));
        $this->expectException(InvalidArgumentException::class);
        WellNaming::name('africa', 0);
    }

    public function testDryRunDoesNotCreateSchemaOrRename(): void
    {
        $result = (new WellNamingMigrationService($this->db))->run(false);
        self::assertSame(4, $result['renamed']);
        self::assertSame('E-1', $result['preview'][0]['to']);
        self::assertSame('Rumaila', $this->db->query('SELECT well_name FROM wells WHERE id = 11')->fetchColumn());
        self::assertSame(false, $this->db->query("SELECT name FROM sqlite_master WHERE name = 'well_name_counters'")->fetchColumn());
    }

    public function testApplyIsIdempotentAndAllocationContinuesAfterDeletion(): void
    {
        $this->db->exec('CREATE TABLE well_name_counters (player_id INTEGER, region_id INTEGER, last_number INTEGER,
            PRIMARY KEY (player_id, region_id))');
        $service = new WellNamingMigrationService($this->db);
        self::assertSame(4, $service->run(true)['renamed']);
        self::assertSame(0, $service->run(true)['renamed']);
        self::assertSame('E-1', $this->db->query('SELECT well_name FROM wells WHERE id = 11')->fetchColumn());
        self::assertSame('E-2', $this->db->query('SELECT well_name FROM wells WHERE id = 12')->fetchColumn());
        self::assertSame('AP-1', $this->db->query('SELECT well_name FROM wells WHERE id = 13')->fetchColumn());
        self::assertSame('E-1', $this->db->query('SELECT well_name FROM wells WHERE id = 14')->fetchColumn());
        $this->db->exec('DELETE FROM wells WHERE id = 12');
        $this->db->beginTransaction();
        self::assertSame('E-3', WellNaming::allocate($this->db, 7, 1, 'north_europe'));
        $this->db->commit();
        self::assertSame(0, $service->run(false)['renamed']);
    }

    public function testApplyRequiresCounterSchemaAndKeepsNames(): void
    {
        $this->expectException(RuntimeException::class);
        try {
            (new WellNamingMigrationService($this->db))->run(true);
        } finally {
            self::assertSame('Rumaila', $this->db->query('SELECT well_name FROM wells WHERE id = 11')->fetchColumn());
        }
    }
}
