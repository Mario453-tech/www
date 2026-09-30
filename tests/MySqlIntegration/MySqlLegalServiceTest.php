<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';
require_once dirname(__DIR__, 2) . '/src/LegalService.php';
require_once dirname(__DIR__, 2) . '/src/Tick/LegalSection.php';

/**
 * Etap 1 działu prawnego na prawdziwym MySQL:
 *  - ensureSchema() tworzy poprawne tabele (ENUM, UNIQUE),
 *  - seedRegionConfig() mapuje political_risk -> risk_level,
 *  - getPermitStatus / hasActivePermit czytają poprawnie.
 */
final class MySqlLegalServiceTest extends MySqlIntegrationTestCase
{
    private LegalService $service;
    private int $legalRegionId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->legalRegionId = $this->seed + 20;
        $this->legalCleanup();
        $this->service = new LegalService($this->db); // ensureSchema() na MySQL
    }

    protected function tearDown(): void
    {
        $this->legalCleanup();
        parent::tearDown();
    }

    public function testEnsureSchemaCreatesTablesOnRealMySql(): void
    {
        foreach (['legal_region_config', 'drilling_permit_applications'] as $table) {
            $exists = $this->db->query("SHOW TABLES LIKE " . $this->db->quote($table))->fetch();
            $this->assertNotFalse($exists, "Tabela {$table} powinna istnieć po ensureSchema()");
        }
    }

    public function testSeedRegionConfigMapsPoliticalRiskOnRealMySql(): void
    {
        $this->insertRegion($this->legalRegionId, 4); // political_risk = 4 -> critical
        $this->service->seedRegionConfig();

        $config = $this->service->getRegionConfig($this->legalRegionId);
        $this->assertNotNull($config);
        $this->assertSame('critical', $config['risk_level']);
        $this->assertGreaterThan(0.0, (float)$config['application_cost']);
        $this->assertSame('Region testowy ' . $this->legalRegionId, $config['region_name']);
    }

    public function testPermitStatusAndActiveGateOnRealMySql(): void
    {
        $playerId = $this->seed;

        // Brak wniosku -> none, brak aktywnego zezwolenia.
        $status = $this->service->getPermitStatus($playerId, $this->legalRegionId);
        $this->assertSame('none', $status['status']);
        $this->assertFalse($this->service->hasActivePermit($playerId, $this->legalRegionId));

        // Zezwolenie aktywne -> has_active = true.
        $this->insertApplication($playerId, $this->legalRegionId, LegalService::STATUS_GRANTED);
        $status = $this->service->getPermitStatus($playerId, $this->legalRegionId);
        $this->assertSame('granted', $status['status']);
        $this->assertTrue($status['has_active']);
        $this->assertTrue($this->service->hasActivePermit($playerId, $this->legalRegionId));
    }

    public function testUniquePlayerRegionConstraintOnRealMySql(): void
    {
        $playerId = $this->seed;
        $this->insertApplication($playerId, $this->legalRegionId, LegalService::STATUS_PENDING);

        $this->expectException(PDOException::class);
        // Drugi wniosek dla tej samej pary (gracz, region) łamie UNIQUE.
        $this->insertApplication($playerId, $this->legalRegionId, LegalService::STATUS_PENDING);
    }

    public function testSubmitApplicationChargesAndCreatesPendingOnRealMySql(): void
    {
        $playerId = $this->seedPlayer();          // cash 50 000 000
        $this->insertRegion($this->legalRegionId, 2);
        $this->insertConfig($this->legalRegionId, 'medium', 250000.00, 60, 0.00);

        $cashBefore = (float)$this->db->query("SELECT cash FROM players WHERE id = {$playerId}")->fetchColumn();

        $res = $this->service->submitApplication($playerId, $this->legalRegionId);
        $this->assertTrue($res['success'], $res['message'] ?? '');
        $this->assertSame('submitted', $res['code']);

        $cashAfter = (float)$this->db->query("SELECT cash FROM players WHERE id = {$playerId}")->fetchColumn();
        $this->assertEqualsWithDelta($cashBefore - 250000.0, $cashAfter, 0.01);

        $status = $this->service->getPermitStatus($playerId, $this->legalRegionId);
        $this->assertSame('pending', $status['status']);
        $this->assertNotEmpty($status['application']['decision_due_at']);
    }

    // --------------------------------------------------------------- Helpers

    private function insertConfig(int $regionId, string $risk, float $cost, int $reviewMin, float $reqCapital): void
    {
        $this->db->prepare(
            "INSERT INTO legal_region_config
                (region_id, enabled, risk_level, application_cost, base_review_minutes, required_capital)
             VALUES (?, 1, ?, ?, ?, ?)"
        )->execute([$regionId, $risk, $cost, $reviewMin, $reqCapital]);
    }

    public function testRetryPreservesHistoryOnRealMySql(): void
    {
        $playerId = $this->seedPlayer();
        $this->insertRegion($this->legalRegionId, 2);
        $this->insertConfig($this->legalRegionId, 'medium', 250000, 60, 0);
        $first = new DateTimeImmutable('2026-01-01 12:00:00');
        $this->assertTrue($this->service->submitApplication($playerId, $this->legalRegionId, $first)['success']);
        $firstApplication = $this->service->getPermitStatus($playerId, $this->legalRegionId)['application'];
        $this->assertGreaterThan(0, (int)$firstApplication['fee_transaction_id']);
        $this->db->prepare("UPDATE bank_transactions SET created_at='2026-01-01 12:01:00' WHERE id=? AND from_player_id=?")
            ->execute([$firstApplication['fee_transaction_id'], $playerId]);
        $this->db->prepare("UPDATE drilling_permit_applications SET status='no_decision' WHERE player_id=? AND region_id=?")->execute([$playerId, $this->legalRegionId]);
        $this->assertTrue($this->service->submitApplication($playerId, $this->legalRegionId, $first->modify('+1 day'))['success']);
        $current = $this->service->getPermitStatus($playerId, $this->legalRegionId)['application'];
        $reader = new LegalApplicationHistory($this->db);
        $history = $reader->forPlayer($playerId, [$this->legalRegionId => ['drilling' => $current]]);
        $this->assertSame('no_decision', $history[$this->legalRegionId]['drilling'][0]['status']);
        $this->assertCount(1, $history[$this->legalRegionId]['drilling']);
        $this->assertSame((int)$firstApplication['fee_transaction_id'], (int)$history[$this->legalRegionId]['drilling'][0]['fee_transaction_id']);
        $this->db->beginTransaction();
        $reader->ensureSchema();
        $this->assertTrue($this->db->inTransaction());
        $this->db->rollBack();

        $this->assertSame([], $reader->forPlayer($playerId + 1, []));
        $this->assertSame('in_progress', $this->service->submitApplication($playerId, $this->legalRegionId)['code']);
    }

    public function testLocalPermitRetryArchivesOnlyThisPlayersAttempt(): void
    {
        $playerId = $this->seedPlayer();
        $this->insertRegion($this->legalRegionId, 2);
        $this->insertConfig($this->legalRegionId, 'medium', 250000, 60, 0);
        $this->db->prepare('UPDATE legal_region_config SET hub_permit_enabled=1, hub_permit_cost=250000, hub_review_minutes=120 WHERE region_id=?')->execute([$this->legalRegionId]);
        $first = new DateTimeImmutable('2026-01-01 12:00:00');
        $this->assertTrue($this->service->submitHubApplication($playerId, $this->legalRegionId, $first)['success']);
        $this->db->prepare("UPDATE hub_permit_applications SET status='no_decision' WHERE player_id=? AND region_id=?")->execute([$playerId, $this->legalRegionId]);
        $this->assertTrue($this->service->submitHubApplication($playerId, $this->legalRegionId, $first->modify('+1 day'))['success']);
        $current = $this->service->getHubPermitStatus($playerId, $this->legalRegionId)['application'];
        $history = (new LegalApplicationHistory($this->db))->forPlayer($playerId, [$this->legalRegionId => ['local' => $current]]);
        $this->assertCount(1, $history[$this->legalRegionId]['local']);
        $this->assertSame('no_decision', $history[$this->legalRegionId]['local'][0]['status']);
        $this->assertGreaterThan(0, (int)$history[$this->legalRegionId]['local'][0]['fee_transaction_id']);
        $this->assertNotSame($history[$this->legalRegionId]['local'][0]['fee_transaction_id'], $current['fee_transaction_id']);
        $this->assertArrayNotHasKey('drilling', $history[$this->legalRegionId]);
        $this->assertSame('in_progress', $this->service->submitHubApplication($playerId, $this->legalRegionId)['code']);
    }

    public function testUpgradeOutcomeAndDeadlineSurviveRetryOnRealMySql(): void
    {
        $playerId = $this->seedPlayer();
        $this->insertRegion($this->legalRegionId, 2);
        $this->insertConfig($this->legalRegionId, 'medium', 250000, 60, 0);
        $this->insertApplication($playerId, $this->legalRegionId, 'transitional');
        $this->db->prepare("UPDATE drilling_permit_applications SET submitted_at=NULL WHERE player_id=? AND region_id=?")->execute([$playerId, $this->legalRegionId]);
        $first = new DateTimeImmutable('2026-01-01 12:00:00');
        $this->assertTrue($this->service->submitApplication($playerId, $this->legalRegionId, $first)['success']);
        $app = $this->service->getPermitStatus($playerId, $this->legalRegionId)['application'];
        $app += ['no_decision_risk_pct'=>100, 'refusal_risk_pct'=>0, 'delay_risk_pct'=>0,
            'delay_min_minutes'=>10, 'delay_max_minutes'=>30, 'refusal_cooldown_minutes'=>120, 'risk_level'=>'medium'];
        $section = new LegalSection($this->db, new DateTime('2026-01-01 13:00:00'));
        (new ReflectionMethod($section, 'processTransitionalUpgrade'))->invoke($section, $app, '2026-01-01 13:00:00');
        $finished = $this->service->getPermitStatus($playerId, $this->legalRegionId);
        $this->assertTrue($finished['has_active']);
        $this->assertSame('no_decision', LegalApplicationHistory::applicationStatus($finished['application']));
        $this->assertSame('2026-01-01 13:00:00', $finished['application']['upgrade_decision_due_at']);
        $this->assertTrue($this->service->submitApplication($playerId, $this->legalRegionId, $first->modify('+1 day'))['success']);
        $current = $this->service->getPermitStatus($playerId, $this->legalRegionId)['application'];
        $rows = (new LegalApplicationHistory($this->db))->forPlayer($playerId, [$this->legalRegionId=>['drilling'=>$current]])[$this->legalRegionId]['drilling'];
        $this->assertSame('no_decision', $rows[0]['status']);
        $this->assertSame('2026-01-01 13:00:00', $rows[0]['decision_due_at']);
        $this->assertSame((int)$app['fee_transaction_id'], (int)$rows[0]['fee_transaction_id']);
        $this->assertNull($current['decided_at']);
    }

    private function insertRegion(int $regionId, int $politicalRisk): void
    {
        $this->db->prepare(
            "INSERT INTO world_regions (id, code, name, political_risk)
             VALUES (?, ?, ?, ?)"
        )->execute([$regionId, 'tst' . $regionId, 'Region testowy ' . $regionId, $politicalRisk]);
    }

    private function insertApplication(int $playerId, int $regionId, string $status): void
    {
        $this->db->prepare(
            "INSERT INTO drilling_permit_applications (player_id, region_id, status, submitted_at)
             VALUES (?, ?, ?, NOW())"
        )->execute([$playerId, $regionId, $status]);
    }

    private function legalCleanup(): void
    {
        $playerId = $this->seed;
        try {
            $this->db->prepare("DELETE FROM drilling_permit_applications WHERE player_id = ? OR region_id = ?")
                ->execute([$playerId, $this->legalRegionId]);
            $this->db->prepare('DELETE FROM hub_permit_applications WHERE player_id=? OR region_id=?')->execute([$playerId, $this->legalRegionId]);
            $exists = $this->db->query("SHOW TABLES LIKE 'legal_application_history'")->fetchColumn();
            if ($exists) $this->db->prepare('DELETE FROM legal_application_history WHERE player_id=? OR region_id=?')->execute([$playerId, $this->legalRegionId]);
            $this->db->prepare("DELETE FROM legal_region_config WHERE region_id = ?")
                ->execute([$this->legalRegionId]);
            $this->db->prepare("DELETE FROM world_regions WHERE id = ?")
                ->execute([$this->legalRegionId]);
        } catch (PDOException $e) {
            // Tabele mogą jeszcze nie istnieć przy pierwszym uruchomieniu — ignorujemy 1146.
            if (!str_contains($e->getMessage(), '1146') && !str_contains($e->getMessage(), '42S02')) {
                throw $e;
            }
        }
    }
}
