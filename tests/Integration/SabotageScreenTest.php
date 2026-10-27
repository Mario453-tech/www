<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/SabotageService.php';

final class SabotageScreenTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private SabotageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->db->exec('CREATE TABLE well_config (`key` TEXT PRIMARY KEY, `value` TEXT, label TEXT, category TEXT)');
        $this->db->exec('CREATE TABLE players (id INTEGER PRIMARY KEY, username TEXT, company_name TEXT, status TEXT, cash REAL, black_market_score REAL)');
        $this->db->exec("INSERT INTO players VALUES (1, 'attacker', 'Attack Co', 'active', 1000000, 40), (2, 'Stefan', 'Stefan Co', 'active', 1000000, 0), (3, 'Silvia', 'Silvia Co', 'inactive', 0, 0), (4, 'Other', 'St Company', 'active', 0, 0)");
        $this->service = new SabotageService($this->db);
        $this->service->setModuleEnabled(true);
    }

    public function testSearchOnlyReturnsEligibleTargetsAndRequiresQuery(): void
    {
        self::assertSame([], $this->service->searchPlayerTargets(1, ''));
        $rows = $this->service->searchPlayerTargets(1, 'st');
        self::assertSame([4, 2], array_column($rows, 'id'));
        self::assertSame([], $this->service->searchPlayerTargets(1, 'Silvia'));
        self::assertSame([], $this->service->searchPlayerTargets(1, 'attacker'));
    }

    public function testTargetFiltersSearchAcrossPagesAndExposeInactiveWithoutAllowingAttack(): void
    {
        self::assertSame(3, $this->service->countPlayerTargets(1, 'all'));
        self::assertSame(1, $this->service->countPlayerTargets(1, 'inactive'));
        self::assertSame([3], array_column($this->service->getPlayerTargets(1, 12, 0, 'inactive'), 'id'));
        self::assertSame([2], array_column($this->service->getPlayerTargets(1, 12, 0, 'active', 'Stefan'), 'id'));
        self::assertSame(1, $this->service->countPlayerTargets(1, 'active', 'Stefan'));
        self::assertSame([], $this->service->getPlayerTargets(1, 12, 0, 'active', 'Silvia'));
        $option = $this->service->getAvailableOptions(SabotageService::TARGET_PLAYER_COMPANY, SabotageService::CONTEXT_PLAYER_COMPANY)[0];
        $result = $this->service->executePlayerSabotage(1, 3, (int)$option['id']);
        self::assertFalse($result['success']);
        self::assertSame(1000000.0, (float)$this->db->query('SELECT cash FROM players WHERE id = 1')->fetchColumn());
        self::assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM sabotage_attempts')->fetchColumn());
    }

    public function testQuoteUsesSameConfiguredCostRuleAsExecution(): void
    {
        $option = $this->service->getAvailableOptions(SabotageService::TARGET_PLAYER_COMPANY, SabotageService::CONTEXT_PLAYER_COMPANY)[0];
        self::assertSame(150000.0, $this->service->estimatePlayerCost($option, 500000.0));
        $option['cost_type'] = 'percent_reference';
        $option['cost_value'] = 12.5;
        self::assertSame(62500.0, $this->service->estimatePlayerCost($option, 500000.0));
    }

    public function testBlackMarketRequirementIsSourcedFromServerConfig(): void
    {
        self::assertSame(30.0, $this->service->getRequiredBlackMarketScore());
        $this->db->prepare("INSERT INTO well_config (`key`, `value`, label, category) VALUES (?, ?, '', '')")->execute(['bm_sabotage_threshold', '60']);
        self::assertSame(60.0, $this->service->getRequiredBlackMarketScore());
    }

    public function testHistoryPaginatesFiveAndFiltersFortyEightHours(): void
    {
        $option = $this->service->getAvailableOptions(SabotageService::TARGET_PLAYER_COMPANY, SabotageService::CONTEXT_PLAYER_COMPANY)[0];
        $insert = $this->db->prepare("INSERT INTO sabotage_attempts (player_id, source_type, target_player_id, target_type, target_id, sabotage_option_id, context, status, created_at) VALUES (?, 'player', 2, 'player_company', 2, ?, 'player_company_sabotage', 'success', ?)");
        for ($i = 0; $i < 7; $i++) {
            $insert->execute([1, $option['id'], gmdate('Y-m-d H:i:s', time() - ($i + 1) * 60)]);
        }
        $insert->execute([1, $option['id'], gmdate('Y-m-d H:i:s', time() - 49 * 3600)]);
        $insert->execute([4, $option['id'], gmdate('Y-m-d H:i:s')]);
        $this->db->prepare("INSERT INTO sabotage_attempts (player_id, source_type, target_player_id, target_type, target_id, sabotage_option_id, context, status, created_at) VALUES (1, 'system', 2, 'player_company', 2, ?, 'player_company_sabotage', 'success', ?)")
            ->execute([$option['id'], gmdate('Y-m-d H:i:s')]);
        self::assertSame(7, $this->service->countRecentPlayerAttempts(1));
        self::assertCount(5, $this->service->getRecentPlayerAttempts(1, 1));
        self::assertCount(2, $this->service->getRecentPlayerAttempts(1, 2));
        self::assertSame([], $this->service->getRecentPlayerAttempts(1, 3));
        $this->expectException(InvalidArgumentException::class);
        $this->service->getRecentPlayerAttempts(1, 0);
    }

    public function testRetentionRemovesExpiredRecordsButPreservesUnexpiredCooldown(): void
    {
        $option = $this->service->getAvailableOptions(SabotageService::TARGET_PLAYER_COMPANY, SabotageService::CONTEXT_PLAYER_COMPANY)[0];
        $this->db->prepare('UPDATE sabotage_options SET cooldown_minutes = 5000 WHERE id = ?')->execute([$option['id']]);
        $insert = $this->db->prepare("INSERT INTO sabotage_attempts (player_id, source_type, target_player_id, target_type, target_id, sabotage_option_id, context, status, created_at) VALUES (1, 'player', 2, 'player_company', 2, ?, 'player_company_sabotage', 'success', ?)");
        $insert->execute([$option['id'], gmdate('Y-m-d H:i:s', time() - 49 * 3600)]);
        $insert->execute([$option['id'], gmdate('Y-m-d H:i:s', time() - 90 * 3600)]);
        $this->service->pruneExpiredPlayerAttempts();
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM sabotage_attempts')->fetchColumn());
        self::assertSame(0, $this->service->countRecentPlayerAttempts(1));
    }
}
