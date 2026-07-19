<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/TechnicalPage/DataTrait.php';

final class TechnicalDisasterStatusTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();

        $this->db->exec('CREATE TABLE wells (
            id INTEGER PRIMARY KEY,
            player_id INTEGER NOT NULL,
            location_name TEXT NOT NULL,
            status TEXT NOT NULL
        )');
        $this->db->exec('CREATE TABLE technical_tasks (
            id INTEGER PRIMARY KEY,
            player_id INTEGER NOT NULL,
            well_id INTEGER NULL,
            task_type TEXT NOT NULL,
            status TEXT NOT NULL
        )');
        $this->db->exec('CREATE TABLE industrial_disasters (
            id INTEGER PRIMARY KEY,
            player_id INTEGER NOT NULL,
            well_id INTEGER NULL,
            disaster_type TEXT NOT NULL,
            status TEXT NOT NULL,
            resolved_at TEXT NULL,
            occurred_at TEXT NOT NULL
        )');
    }

    public function testStaleContaminationIsResolvedAndHidden(): void
    {
        $this->db->exec("INSERT INTO wells VALUES (10, 1, 'Pole 10', 'active')");
        $this->db->exec("INSERT INTO industrial_disasters
            VALUES (100, 1, 10, 'reservoir_contamination', 'active', NULL, '2026-07-18 14:50:00')");

        $rows = $this->loadDisasters(1);

        $this->assertSame([], $rows);
        $status = $this->db->query('SELECT status FROM industrial_disasters WHERE id = 100')->fetchColumn();
        $this->assertSame('resolved', $status);
    }

    public function testCurrentContaminationRemainsActive(): void
    {
        $this->db->exec("INSERT INTO wells VALUES (11, 1, 'Pole 11', 'contaminated')");
        $this->db->exec("INSERT INTO industrial_disasters
            VALUES (101, 1, 11, 'reservoir_contamination', 'active', NULL, '2026-07-19 10:00:00')");

        $rows = $this->loadDisasters(1);

        $this->assertCount(1, $rows);
        $this->assertSame(101, (int)$rows[0]['id']);
        $this->assertSame('active', $rows[0]['status']);
    }

    public function testRehabilitationInProgressKeepsDisasterVisible(): void
    {
        $this->db->exec("INSERT INTO wells VALUES (12, 1, 'Pole 12', 'servicing')");
        $this->db->exec("INSERT INTO technical_tasks
            VALUES (200, 1, 12, 'reservoir_rehabilitation', 'in_progress')");
        $this->db->exec("INSERT INTO industrial_disasters
            VALUES (102, 1, 12, 'reservoir_contamination', 'being_repaired', NULL, '2026-07-19 11:00:00')");

        $rows = $this->loadDisasters(1);

        $this->assertCount(1, $rows);
        $this->assertSame('being_repaired', $rows[0]['status']);
    }

    public function testReconciliationDoesNotModifyAnotherPlayersDisaster(): void
    {
        $this->db->exec("INSERT INTO wells VALUES (13, 2, 'Pole 13', 'active')");
        $this->db->exec("INSERT INTO industrial_disasters
            VALUES (103, 2, 13, 'reservoir_contamination', 'active', NULL, '2026-07-17 09:00:00')");

        $rows = $this->loadDisasters(1);

        $this->assertSame([], $rows);
        $status = $this->db->query('SELECT status FROM industrial_disasters WHERE id = 103')->fetchColumn();
        $this->assertSame('active', $status);
    }

    private function loadDisasters(int $playerId): array
    {
        $loader = new class($playerId) {
            use TechnicalPageDataTrait;

            public function __construct(private int $playerId)
            {
            }

            public function load(PDO $db): array
            {
                return $this->loadActiveDisasters($db);
            }
        };

        return $loader->load($this->db);
    }
}
