<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Hub/PlayerQueryTrait.php';

final class HubMarketPaginationTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->db->exec('CREATE TABLE world_regions (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE wells (id INTEGER PRIMARY KEY, player_id INTEGER NOT NULL, region_id INTEGER, status TEXT NOT NULL)');
        $this->db->exec('CREATE TABLE logistics_hubs (
            id INTEGER PRIMARY KEY, region_id INTEGER NOT NULL, player_id INTEGER NOT NULL,
            tenant_player_id INTEGER NOT NULL, status TEXT NOT NULL, name TEXT NOT NULL,
            zone_key TEXT NOT NULL, hub_type TEXT NOT NULL, acquisition_type TEXT NOT NULL,
            slot_limit INTEGER NOT NULL
        )');
        $this->db->exec('CREATE TABLE logistics_hub_assignments (hub_id INTEGER NOT NULL, status TEXT NOT NULL)');
        $this->db->exec("INSERT INTO world_regions VALUES (7, 'Bliski Wschód'), (8, 'Europa'), (9, 'Azja')");
        $this->db->exec("INSERT INTO wells VALUES (1, 1, 7, 'active'), (2, 1, 8, 'paused_cash'), (3, 2, 9, 'active')");
        $insert = $this->db->prepare('INSERT INTO logistics_hubs VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        for ($id = 1; $id <= 12; $id++) {
            $insert->execute([$id, 7, 0, 0, 'active', 'Hub ' . $id, sprintf('A%02d', $id),
                'small', $id === 12 ? 'used' : 'new', $id === 11 ? 0 : 3]);
        }
        $insert->execute([21, 8, 0, 0, 'active', 'Hub Europa', 'E01', 'medium', 'rental', 4]);
        $insert->execute([22, 8, 0, 0, 'active', 'Hub 50%', 'E02', 'large', 'used', 6]);
        $insert->execute([30, 9, 0, 0, 'active', 'Obcy region', 'Z01', 'small', 'new', 3]);
        $insert->execute([31, 7, 1, 0, 'active', 'Własny', 'A31', 'small', 'new', 3]);
        $insert->execute([32, 7, 0, 2, 'active', 'Wynajęty', 'A32', 'small', 'rental', 3]);
        $insert->execute([33, 7, 0, 0, 'disabled', 'Wyłączony', 'A33', 'small', 'new', 3]);
        $this->db->exec("INSERT INTO logistics_hub_assignments VALUES (21, 'active'), (21, 'active'), (21, 'active'), (21, 'active')");
    }

    private function service(): object
    {
        return new class($this->db) {
            use HubPlayerQueryTrait;
            public function __construct(private PDO $db) {}
        };
    }

    public function testPagesCoverOnlyAccessibleMarketHubsWithoutDuplicates(): void
    {
        $service = $this->service();
        $pages = [];
        foreach ([1, 2, 3] as $pageNumber) {
            $page = $service->getMarketHubsPageForPlayer(1, '', 'all', $pageNumber, 5);
            self::assertSame(14, $page['total']);
            self::assertSame(3, $page['pages']);
            $pages[] = array_map('intval', array_column($page['hubs'], 'id'));
        }
        self::assertCount(14, array_unique(array_merge(...$pages)));
        self::assertNotContains(30, array_merge(...$pages));
        self::assertNotContains(31, array_merge(...$pages));
        self::assertNotContains(32, array_merge(...$pages));
        self::assertNotContains(33, array_merge(...$pages));
    }

    public function testSearchAndFiltersApplyBeforePaginationAndCount(): void
    {
        $service = $this->service();
        $used = $service->getMarketHubsPageForPlayer(1, '', 'used', 1, 1);
        self::assertSame(2, $used['total']);
        self::assertSame(2, $used['pages']);
        self::assertCount(1, $used['hubs']);
        $searched = $service->getMarketHubsPageForPlayer(1, 'Hub 12', 'used', 5, 5);
        self::assertSame(1, $searched['total']);
        self::assertSame(1, $searched['page']);
        self::assertSame(12, (int)$searched['hubs'][0]['id']);
        $literal = $service->getMarketHubsPageForPlayer(1, '%', 'all', 1, 5);
        self::assertSame(1, $literal['total']);
        self::assertSame(22, (int)$literal['hubs'][0]['id']);
        self::assertSame(12, $service->getMarketHubsPageForPlayer(1, '', 'free', 1, 20)['total']);
    }

    public function testNoOperatingRegionsReturnsNoOffers(): void
    {
        self::assertSame(
            ['hubs' => [], 'total' => 0, 'page' => 1, 'pages' => 1],
            $this->service()->getMarketHubsPageForPlayer(4, '', 'all', 1)
        );
    }
}
