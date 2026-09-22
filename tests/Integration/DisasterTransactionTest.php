<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/WellService.php';
require_once dirname(__DIR__, 2) . '/src/Tick/WellProductionSection.php';
require_once dirname(__DIR__, 2) . '/src/Tick/WellRiskHandler.php';

final class DisasterTransactionTest extends SqliteIntegrationTestCase
{
    private function service(PDO $db): WellService
    {
        $service = (new ReflectionClass(WellService::class))->newInstanceWithoutConstructor();
        $this->setPrivateProperty($service, WellService::class, 'db', $db);
        return $service;
    }

    public function testSkippedBlowoutPreservesOuterTransaction(): void
    {
        $db = $this->createSqlitePdo();
        $db->exec('CREATE TABLE wells (id INTEGER, player_id INTEGER, status TEXT, technical_condition REAL, marine_buffer_bbl REAL)');
        $db->exec("INSERT INTO wells VALUES (1, 1, 'contaminated', 30, 4)");
        $service = $this->service($db);
        $db->beginTransaction();
        $db->exec('UPDATE wells SET marine_buffer_bbl = 8 WHERE id = 1');
        self::assertNull($service->triggerBlowout(1, 1)['disaster']);
        self::assertTrue($db->inTransaction());
        self::assertSame(8.0, (float) $db->query('SELECT marine_buffer_bbl FROM wells')->fetchColumn());
        $db->rollBack();
        self::assertSame(4.0, (float) $db->query('SELECT marine_buffer_bbl FROM wells')->fetchColumn());
    }

    /** @dataProvider disasterCallPaths */
    public function testDisasterInsertFailurePropagatesToCallerRollback(bool $throughTickHandler): void
    {
        $db = $this->createSqlitePdo();
        $db->exec('CREATE TABLE wells (id INTEGER, player_id INTEGER, status TEXT, technical_condition REAL, marine_buffer_bbl REAL, risk_score REAL)');
        $db->exec("INSERT INTO wells VALUES (1, 1, 'active', 30, 4, 100)");
        $service = $this->service($db);
        for ($seed = 0; $seed < 10000; $seed++) {
            mt_srand($seed);
            if (mt_rand(1, 1000000) <= 50000) break;
        }
        mt_srand($seed);
        $db->beginTransaction();
        try {
            if ($throughTickHandler) {
                $ctx = (new ReflectionClass(WellProductionSection::class))->newInstanceWithoutConstructor();
                $ctx->wellService = $service;
                $ctx->gBalanceMults = ['disaster' => 1.0];
                $ctx->financeSafetyMods = [];
                (new WellRiskHandler($ctx))->processDisasterRoll([], 1, 1, 24.0, [], ['techSpecCatMult' => 1.0], 10.0, 1.0, null);
            } else {
                $service->processDisasterRoll(1, 24.0, [], 10.0, 1);
            }
            self::fail('A failed disaster write must propagate');
        } catch (PDOException $e) {
            self::assertTrue($db->inTransaction());
        } finally {
            if ($db->inTransaction()) $db->rollBack();
            mt_srand();
        }
        self::assertSame('active', $db->query('SELECT status FROM wells')->fetchColumn());
        self::assertSame(4.0, (float) $db->query('SELECT marine_buffer_bbl FROM wells')->fetchColumn());
    }

    /** @return array<string, array{bool}> */
    public static function disasterCallPaths(): array
    {
        return ['service' => [false], 'tick handler' => [true]];
    }
}
