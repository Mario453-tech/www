<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/FinanceService.php';
require_once dirname(__DIR__, 2) . '/src/FinancePolicyService.php';
require_once dirname(__DIR__, 2) . '/admin/partials/finance_admin_actions.php';
require_once dirname(__DIR__, 2) . '/admin/partials/finance_admin_metrics.php';

final class MySqlFinanceDashboardReadTest extends MySqlIntegrationTestCase
{
    /** @dataProvider ranges */
    public function testDashboardReadsRealMetricsWithoutWrites(int $hours): void
    {
        $playerId = $this->seedPlayer();
        new FinanceService();
        new FinancePolicyService($this->db);
        $this->db->prepare('INSERT INTO finance_logs (player_id, tick_at, revenue, gross_revenue, net_profit, oil_price, bbl_produced, produced_bbl, delivered_bbl) VALUES (?, NOW(), 100, 100, 80, 10, 10, 0, 0)')->execute([$playerId]);
        $connections = [$this->db, Database::getInstance()->getConnection()];
        $oldModes = [];
        foreach ($connections as $connection) {
            $oldModes[] = $connection->query('SELECT @@SESSION.sql_mode')->fetchColumn();
            $connection->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ONLY_FULL_GROUP_BY'");
        }
        try {
            // A fresh request has no schema guards set; detect even swallowed writes.
            // Nowe zadanie nie ma ustawionych straznikow schematu; wykryj tez ukryte zapisy.
            foreach ([FinanceService::class, FinancePolicyService::class] as $class) {
                (new ReflectionProperty($class, 'schemaEnsured'))->setValue(null, []);
            }
            $before = array_map($this->writeCounters(...), $connections);
            $finance = new FinanceService(false);
            $policy = new FinancePolicyService($this->db, false);
            $data = adminFinanceBuildViewData($this->db, $finance, $policy, $hours, adminFinanceLoadConfig($this->db), adminFinanceLoadSavingsMultipliers($this->db));
            self::assertSame($before, array_map($this->writeCounters(...), $connections));
            $row = $finance->getLastTick($playerId);
            self::assertEquals(0, $row['delivered_bbl']);
            self::assertEquals(0, $row['produced_bbl']);
            $players = array_column($data['perPlayer'], null, 'player_id');
            self::assertEquals(100, $players[$playerId]['total_revenue']);
            self::assertEquals(80, $players[$playerId]['total_net']);
            self::assertArrayHasKey($playerId, array_column($data['policyImpactPlayers'], null, 'player_id'));
            self::assertNotEmpty($data['globalHistory']);
            self::assertEquals(10, $finance->getSummary($playerId, 24)['total_bbl']);
        } finally {
            foreach ($connections as $i => $connection) {
                $connection->exec('SET SESSION sql_mode = ' . $connection->quote($oldModes[$i]));
            }
        }
    }

    private function writeCounters(PDO $db): array
    {
        return $db->query("SHOW SESSION STATUS WHERE Variable_name IN ('Com_update', 'Com_insert', 'Com_delete', 'Com_create_table', 'Com_alter_table')")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public static function ranges(): array
    {
        return [[24], [168], [720]];
    }
}
