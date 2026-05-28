<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/TransportConfigService.php';

final class TransportConfigServiceTest extends BaseTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function testGetDefaultsReturnsKnownTransportTypes(): void
    {
        $defaults = TransportConfigService::getDefaults();

        $this->assertArrayHasKey('rurociag', $defaults);
        $this->assertArrayHasKey('ciezarowki', $defaults);
        $this->assertArrayHasKey('tankowiec', $defaults);
        $this->assertSame(120.0, $defaults['rurociag']['capacity']);
    }

    public function testTableExistsReturnsFalseWithoutTable(): void
    {
        $this->assertFalse(TransportConfigService::tableExists($this->db));
    }

    public function testLoadReturnsDefaultsWhenTableIsMissing(): void
    {
        $config = TransportConfigService::load($this->db);

        $this->assertSame(0.50, $config['rurociag']['cost_per_bbl']);
        $this->assertSame(20.0, $config['ciezarowki']['opex']);
    }

    public function testLoadMergesOverridesFromDatabase(): void
    {
        $this->db->exec(
            'CREATE TABLE transport_config (
                transport_type TEXT NOT NULL,
                config_key TEXT NOT NULL,
                config_value REAL NOT NULL
            )'
        );
        $this->db->exec("INSERT INTO transport_config (transport_type, config_key, config_value) VALUES ('rurociag', 'cost_per_bbl', 1.25)");
        $this->db->exec("INSERT INTO transport_config (transport_type, config_key, config_value) VALUES ('ciezarowki', 'capacity', 88.0)");

        $config = TransportConfigService::load($this->db);

        $this->assertSame(1.25, $config['rurociag']['cost_per_bbl']);
        $this->assertSame(88.0, $config['ciezarowki']['capacity']);
        $this->assertSame(12.0, $config['tankowiec']['opex']);
    }

    public function testGetTypeConfigFallsBackToUnsetForUnknownType(): void
    {
        $config = TransportConfigService::getTypeConfig($this->db, 'nieznany');

        $this->assertSame(0.0, $config['capacity']);
        $this->assertSame(0.0, $config['opex']);
    }
}
