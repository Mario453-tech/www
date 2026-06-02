<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/LegalService.php';
require_once dirname(__DIR__, 2) . '/src/WorldMap.php';

/**
 * Etap 2 działu prawnego: bramka zakupu odwiertów na mapie.
 * Testuje WorldMap::regionPurchaseBlock() — twardą zasadę P1:
 * bez aktywnego zezwolenia (granted/transitional) zakup jest zablokowany.
 */
final class WorldMapPermitGateTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private WorldMap $map;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->db->exec("INSERT INTO world_regions (id, code, name, political_risk) VALUES (5, 'me', 'Bliski Wschód', 3)");
        $this->map = new WorldMap($this->db);
    }

    public function testBlocksPurchaseWhenNoPermit(): void
    {
        $block = $this->map->regionPurchaseBlock(100, 5);

        $this->assertIsArray($block);
        $this->assertFalse($block['success']);
        $this->assertTrue($block['no_permit']);
        $this->assertSame(5, $block['region_id']);
        $this->assertStringContainsString('Bliski Wschód', $block['message']);
    }

    public function testAllowsPurchaseWithGrantedPermit(): void
    {
        $this->insertApplication(100, 5, LegalService::STATUS_GRANTED);
        $this->assertNull($this->map->regionPurchaseBlock(100, 5));
    }

    public function testAllowsPurchaseWithTransitionalPermit(): void
    {
        $this->insertApplication(100, 5, LegalService::STATUS_TRANSITIONAL);
        $this->assertNull($this->map->regionPurchaseBlock(100, 5));
    }

    public function testBlocksPurchaseWhenApplicationStillPending(): void
    {
        $this->insertApplication(100, 5, LegalService::STATUS_PENDING);
        $block = $this->map->regionPurchaseBlock(100, 5);
        $this->assertIsArray($block);
        $this->assertTrue($block['no_permit']);
    }

    public function testPermitIsScopedPerPlayerAndRegion(): void
    {
        // Gracz 100 ma zezwolenie na region 5, ale nie 999; gracz 200 nie ma nic.
        $this->insertApplication(100, 5, LegalService::STATUS_GRANTED);

        $this->assertNull($this->map->regionPurchaseBlock(100, 5));
        $this->assertIsArray($this->map->regionPurchaseBlock(100, 999));
        $this->assertIsArray($this->map->regionPurchaseBlock(200, 5));
    }

    // --------------------------------------------------------------- Helpers

    private function createSchema(): void
    {
        $this->db->exec('CREATE TABLE world_regions (id INTEGER PRIMARY KEY, code TEXT, name TEXT, political_risk INTEGER DEFAULT 1)');
        $this->db->exec(
            "CREATE TABLE drilling_permit_applications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                region_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                cost REAL DEFAULT 0,
                submitted_at TEXT NULL,
                decision_due_at TEXT NULL,
                decided_at TEXT NULL,
                refusal_cooldown_until TEXT NULL,
                delay_count INTEGER DEFAULT 0,
                source TEXT DEFAULT 'player',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    private function insertApplication(int $playerId, int $regionId, string $status): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO drilling_permit_applications (player_id, region_id, status, submitted_at)
             VALUES (?, ?, ?, datetime('now'))"
        );
        $stmt->execute([$playerId, $regionId, $status]);
    }
}
