<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/MarketSaleService.php';
require_once dirname(__DIR__, 2) . '/src/WorldMap.php';
require_once dirname(__DIR__, 2) . '/src/LegalService.php';
require_once dirname(__DIR__) . '/fixtures/TransactionRaceFixtures.php';

final class MySqlTransactionRacesTest extends TestCase
{
    private PDO $db;
    private TransactionRaceFixtures $fixtures;
    private int $player;
    private int $other;
    private int $region;
    private int $location;

    protected function setUp(): void
    {
        if (!TransactionRaceFixtures::isDedicatedName((string)getenv('DB_NAME'))) {
            $this->markTestSkipped('Requires an approved isolated test DB_NAME.');
        }
        $this->db = Database::getInstance()->getConnection();
        $this->fixtures = new TransactionRaceFixtures($this->db);
        new FinancialTransactionService($this->db);
        new LegalService($this->db);
        new CompanyCredibilityService($this->db);
        new MarketOffer($this->db);
        WorldMapSchema::ensure($this->db);
        $this->player = random_int(700000000, 799000000);
        $this->other = $this->player + 1;
        $this->region = $this->player + 2;
        $this->location = $this->player + 3;
        try {
            foreach ([$this->player, $this->other] as $id) {
                $this->fixtures->insert('players', ['id' => $id, 'username' => 'race_' . $id,
                    'email' => 'race_' . $id . '@example.test', 'password_hash' => 'test',
                    'cash' => 10000000, 'bank_balance' => 0, 'status' => 'active']);
                $this->db->prepare('INSERT INTO storage (player_id, capacity, used) VALUES (?, 1000, 100)')->execute([$id]);
            }
            $this->fixtures->insert('world_regions', ['id' => $this->region, 'code' => 'race_' . $this->region,
                'name' => 'Race region', 'entry_cost' => 1000]);
            $this->fixtures->insert('legal_region_config', ['region_id' => $this->region,
                'enabled' => 1, 'risk_level' => 'low', 'application_cost' => 100,
                'hub_permit_enabled' => 1, 'hub_permit_cost' => 100]);
            foreach ([$this->location, $this->location + 1] as $id) {
                $this->fixtures->insert('world_locations', ['id' => $id, 'region_id' => $this->region,
                    'name' => 'Race location', 'country_code' => 'PL', 'latitude' => 1, 'longitude' => 1]);
            }
            $this->db->exec('INSERT IGNORE INTO market_state (id, base_price, current_price, volatility) VALUES (1, 100, 70, 1)');
        } catch (Throwable $e) {
            $this->fixtures->cleanup();
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->fixtures)) $this->fixtures->cleanup();
    }

    /** @dataProvider fixtureCollisionTables */
    public function testCleanupNeverDeletesPreexistingIdsAfterInsertCollision(string $table): void
    {
        $snapshots = [
            'players' => $this->rows('SELECT * FROM players WHERE id IN (?, ?) ORDER BY id', [$this->player, $this->other]),
            'storage' => $this->rows('SELECT * FROM storage WHERE player_id IN (?, ?) ORDER BY player_id', [$this->player, $this->other]),
            'world_regions' => $this->rows('SELECT * FROM world_regions WHERE id = ?', [$this->region]),
            'legal_region_config' => $this->rows('SELECT * FROM legal_region_config WHERE region_id = ?', [$this->region]),
            'world_locations' => $this->rows('SELECT * FROM world_locations WHERE region_id = ? ORDER BY id', [$this->region]),
        ];
        $partial = new TransactionRaceFixtures($this->db);
        $newId = $this->player + 10;
        try {
            $partial->insert('players', ['id' => $newId, 'username' => 'partial_' . $newId,
                'email' => 'partial_' . $newId . '@example.test', 'password_hash' => 'test']);
            $duplicate = match ($table) {
                'players' => ['id' => $this->other, 'username' => 'duplicate_' . $this->other,
                    'email' => 'duplicate_' . $this->other . '@example.test', 'password_hash' => 'test'],
                'world_regions' => ['id' => $this->region, 'code' => 'duplicate_' . $this->region, 'name' => 'Duplicate'],
                'legal_region_config' => ['region_id' => $this->region],
                'world_locations' => ['id' => $this->location, 'region_id' => $this->region,
                    'name' => 'Duplicate', 'country_code' => 'PL', 'latitude' => 1, 'longitude' => 1],
            };
            try {
                $partial->insert($table, $duplicate);
                $this->fail('The duplicate fixture ID must be rejected.');
            } catch (PDOException $e) {
                $this->assertSame(1062, (int)$e->errorInfo[1]);
            }
        } finally {
            $partial->cleanup();
        }
        $partial->cleanup();
        $this->assertSame(0.0, $this->scalar('SELECT COUNT(*) FROM players WHERE id = ?', [$newId]));
        $this->assertSame($snapshots['players'], $this->rows('SELECT * FROM players WHERE id IN (?, ?) ORDER BY id', [$this->player, $this->other]));
        $this->assertSame($snapshots['storage'], $this->rows('SELECT * FROM storage WHERE player_id IN (?, ?) ORDER BY player_id', [$this->player, $this->other]));
        $this->assertSame($snapshots['world_regions'], $this->rows('SELECT * FROM world_regions WHERE id = ?', [$this->region]));
        $this->assertSame($snapshots['legal_region_config'], $this->rows('SELECT * FROM legal_region_config WHERE region_id = ?', [$this->region]));
        $this->assertSame($snapshots['world_locations'], $this->rows('SELECT * FROM world_locations WHERE region_id = ? ORDER BY id', [$this->region]));
    }

    public static function fixtureCollisionTables(): array
    {
        return [['players'], ['world_regions'], ['legal_region_config'], ['world_locations']];
    }

    public function testDatabaseGuardRejectsConnectionMismatchAndNonTestNames(): void
    {
        $configured = (string)getenv('DB_NAME');
        $actual = (string)$this->db->query('SELECT DATABASE()')->fetchColumn();
        $this->assertSame($configured, $actual);
        $before = $this->rows('SELECT * FROM players WHERE id = ?', [$this->player]);
        try {
            foreach (['gra1', 'contest', 'oil_testproduction', $configured . '_different'] as $wrong) {
                putenv('DB_NAME=' . $wrong);
                try {
                    new TransactionRaceFixtures($this->db);
                    $this->fail('Unsafe database configuration must be rejected before fixture writes.');
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('actual database', $e->getMessage());
                }
                try {
                    $this->fixtures->cleanup();
                    $this->fail('Cleanup must also reject a mismatched database.');
                } catch (RuntimeException $e) {
                    $this->assertStringContainsString('actual database', $e->getMessage());
                }
            }
        } finally {
            putenv('DB_NAME=' . $configured);
        }
        $this->assertSame($before, $this->rows('SELECT * FROM players WHERE id = ?', [$this->player]));
        $this->assertSame($actual, $this->db->query('SELECT DATABASE()')->fetchColumn());
    }

    public function testInstantSalesCannotOversell(): void
    {
        $results = $this->pair($this->job('sale', ['amount' => 80]), $this->job('sale', ['amount' => 80]));
        $this->assertSame(1, $this->successes($results));
        $this->assertSame(20.0, $this->scalar('SELECT used FROM storage WHERE player_id = ?', [$this->player]));
        $price = $this->scalar('SELECT current_price FROM market_state WHERE id = 1');
        $this->assertSame(10000000.0 + 80 * $price, $this->funds());
        $this->assertSame(1.0, $this->transactions('market_sale'));
    }

    public function testInstantSaleRejectsMissingStorageAndInvalidAmount(): void
    {
        $service = new MarketSaleService($this->db);
        $this->assertFalse($service->sellInstant($this->player, 0)['success']);
        $this->assertFalse($service->sellInstant($this->player, -1)['success']);
        $this->db->prepare('DELETE FROM storage WHERE player_id = ?')->execute([$this->player]);
        $this->assertFalse($service->sellInstant($this->player, 1)['success']);
        $this->assertFalse($this->db->inTransaction());
        $this->assertSame(10000000.0, $this->funds());
    }

    public function testCancellationRefundsOnce(): void
    {
        $id = $this->offer();
        $results = $this->pair($this->job('cancel', ['id' => $id]), $this->job('cancel', ['id' => $id]));
        $this->assertSame(1, $this->successes($results));
        $this->assertSame(190.0, $this->scalar('SELECT used FROM storage WHERE player_id = ?', [$this->player]));
        $this->assertSame(0.0, $this->transactions('market_sale'));
    }

    public function testCancelVersusExecutionNeverRefundsAndPays(): void
    {
        $id = $this->offer();
        $this->pair($this->job('cancel', ['id' => $id]), $this->job('execute', ['id' => $id, 'price' => 70]));
        $sold = $this->scalar('SELECT COUNT(*) FROM market_sale_history WHERE offer_id = ?', [$id]);
        $this->assertContains($sold, [0.0, 1.0]);
        $this->assertSame(100.0 + (1 - $sold) * 90, $this->scalar('SELECT used FROM storage WHERE player_id = ?', [$this->player]));
        $this->assertSame(10000000.0 + $sold * 7000, $this->funds());
        $this->assertSame($sold, $this->transactions('market_sale'));
    }

    public function testEditVersusExecutionRechecksPriceAndStatus(): void
    {
        $id = $this->offer();
        $this->pair($this->job('edit', ['id' => $id, 'price' => 100]), $this->job('execute', ['id' => $id, 'price' => 70]));
        $stmt = $this->db->prepare('SELECT status, limit_price FROM market_offers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row['status'] === 'completed') {
            $this->assertSame(30.0, (float)$row['limit_price']);
            $this->assertSame(10007000.0, $this->funds());
        } else {
            $this->assertSame('pending', $row['status']);
            $this->assertSame(100.0, (float)$row['limit_price']);
            $this->assertSame(10000000.0, $this->funds());
        }
    }

    public function testCancellationFailureAndOwnershipLeaveOfferUntouched(): void
    {
        $id = $this->offer();
        $service = new MarketOffer($this->db);
        $this->assertFalse($service->cancelOffer($id, $this->other)['success']);
        $this->assertFalse($service->updateOffer($id, $this->other, 100)['success']);
        $this->db->prepare('UPDATE storage SET used = capacity WHERE player_id = ?')->execute([$this->player]);
        $this->assertFalse($service->cancelOffer($id, $this->player)['success']);
        $this->assertSame(100.0, $this->scalar('SELECT locked_amount FROM market_offers WHERE id = ?', [$id]));
        $this->db->prepare('DELETE FROM storage WHERE player_id = ?')->execute([$this->player]);
        $this->assertFalse($service->cancelOffer($id, $this->player)['success']);
        $this->assertFalse($this->db->inTransaction());
    }

    public function testFullRepaymentIsChargedOnce(): void
    {
        $id = $this->loan(100);
        $results = $this->pair($this->job('repay', ['id' => $id, 'mode' => 'full']), $this->job('repay', ['id' => $id, 'mode' => 'full']));
        $this->assertSame(1, $this->successes($results));
        $this->assertSame(9999900.0, $this->funds());
        $this->assertSame(0.0, $this->scalar('SELECT remaining_amount FROM loans WHERE id = ?', [$id]));
        $this->assertSame(1.0, $this->transactions('loan_payment'));
    }

    public function testInstallmentsRecomputeRemainingUnderLock(): void
    {
        $id = $this->loan(150);
        $results = $this->pair($this->job('repay', ['id' => $id, 'mode' => 'installment']), $this->job('repay', ['id' => $id, 'mode' => 'installment']));
        $this->assertSame(2, $this->successes($results));
        $this->assertSame(9999850.0, $this->funds());
        $this->assertSame(0.0, $this->scalar('SELECT remaining_amount FROM loans WHERE id = ?', [$id]));
    }

    /** @dataProvider applications */
    public function testApplicationsChargeOnlyOnce(string $action, string $status): void
    {
        $table = $action === 'hub' ? 'hub_permit_applications' : 'drilling_permit_applications';
        if ($status !== 'none') {
            $this->db->prepare("INSERT INTO {$table} (player_id, region_id, status) VALUES (?, ?, ?)")
                ->execute([$this->player, $this->region, $status]);
        }
        $results = $this->pair($this->job($action, ['id' => $this->region]), $this->job($action, ['id' => $this->region]));
        $this->assertSame(1, $this->successes($results), json_encode($results));
        $this->assertSame(9999900.0, $this->funds());
        $this->assertSame(1.0, $this->transactions('legal_fee'));
        $this->assertSame(1.0, $this->scalar("SELECT COUNT(*) FROM {$table} WHERE player_id = ?", [$this->player]));
    }

    public static function applications(): array
    {
        return [['legal', 'none'], ['legal', 'refused'], ['legal', 'transitional'], ['hub', 'none'], ['hub', 'refused']];
    }

    public function testDifferentPlayersCannotBuySameLocation(): void
    {
        $this->permit($this->player);
        $this->permit($this->other);
        $results = $this->pair($this->job('map', ['id' => $this->location]),
            $this->job('map', ['id' => $this->location, 'player' => $this->other]), true);
        $this->assertSame(1, $this->successes($results), json_encode($results));
        $this->assertSame(1.0, $this->scalar("SELECT COUNT(*) FROM wells WHERE location_id = ? AND status != 'sold'", [$this->location]));
        $this->assertSame(19999000.0, $this->funds() + $this->funds($this->other));
    }

    public function testConcurrentLocationsRespectPlayerWellLimit(): void
    {
        $this->permit($this->player);
        for ($i = 0; $i < 9; $i++) {
            $this->db->prepare("INSERT INTO wells (player_id, status) VALUES (?, 'active')")->execute([$this->player]);
        }
        $results = $this->pair($this->job('map', ['id' => $this->location]), $this->job('map', ['id' => $this->location + 1]));
        $this->assertSame(1, $this->successes($results), json_encode($results));
        $this->assertSame(10.0, $this->scalar('SELECT COUNT(*) FROM wells WHERE player_id = ?', [$this->player]));
        $this->assertSame(9999000.0, $this->funds());
    }

    public function testSoldHistoryDoesNotBlockRepurchaseAndPreservesReservoir(): void
    {
        $this->permit($this->player);
        $this->db->prepare("INSERT INTO wells (player_id, location_id, status, reservoir_remaining, reservoir_max, sold_at)
            VALUES (?, ?, 'sold', 123, 300000, NOW())")->execute([$this->player, $this->location]);
        $old = (int)$this->db->lastInsertId();
        $result = (new WorldMap($this->db))->buyWellAtLocation($this->player, $this->location);
        $this->assertTrue($result['success'], json_encode($result));
        $this->assertSame(123.0, $this->scalar('SELECT reservoir_remaining FROM wells WHERE id = ?', [$result['well_id']]));
        $this->assertSame(1.0, $this->scalar("SELECT COUNT(*) FROM wells WHERE id = ? AND status = 'sold'", [$old]));
    }

    public function testMapRejectsMissingPermitAndUnavailableLocationWithoutCharge(): void
    {
        $service = new WorldMap($this->db);
        $this->assertFalse($service->buyWellAtLocation($this->player, $this->location)['success']);
        $this->permit($this->player);
        $this->db->prepare('UPDATE world_locations SET available = 0 WHERE id = ?')->execute([$this->location]);
        $this->assertFalse($service->buyWellAtLocation($this->player, $this->location)['success']);
        $this->assertSame(10000000.0, $this->funds());
        $this->assertFalse($this->db->inTransaction());
    }

    public function testMapIndexIsNonuniqueAndBootstrapDoesNotCommitTransactions(): void
    {
        WorldMapSchema::ensure($this->db);
        WorldMapSchema::ensure($this->db);
        $this->assertSame(2.0, $this->scalar("SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'wells' AND index_name = 'idx_wells_location_status' AND non_unique = 1"));
        $this->db->beginTransaction();
        try {
            WorldMapSchema::ensure($this->db);
            $this->fail('DDL bootstrap must reject an active transaction.');
        } catch (LogicException $e) {
            $this->assertTrue($this->db->inTransaction());
        } finally {
            $this->db->rollBack();
        }
    }

    public function testFinancialWriteFailureRollsBackInstantSale(): void
    {
        $this->withFailureTrigger('bank_transactions', 'INSERT', 'NEW.to_player_id', function (): void {
            $result = (new MarketSaleService($this->db))->sellInstant($this->player, 50);
            $this->assertFalse($result['success']);
            $this->assertSame(100.0, $this->scalar('SELECT used FROM storage WHERE player_id = ?', [$this->player]));
            $this->assertSame(10000000.0, $this->funds());
            $this->assertSame(0.0, $this->transactions('market_sale'));
        });
    }

    public function testStorageWriteFailureRollsBackCancellation(): void
    {
        $id = $this->offer();
        $this->withFailureTrigger('storage', 'UPDATE', 'NEW.player_id', function () use ($id): void {
            $result = (new MarketOffer($this->db))->cancelOffer($id, $this->player);
            $this->assertFalse($result['success']);
            $this->assertSame(100.0, $this->scalar('SELECT used FROM storage WHERE player_id = ?', [$this->player]));
            $this->assertSame(1.0, $this->scalar("SELECT COUNT(*) FROM market_offers WHERE id = ? AND status = 'pending'", [$id]));
            $this->assertSame(100.0, $this->scalar('SELECT locked_amount FROM market_offers WHERE id = ?', [$id]));
        });
    }

    public function testWellInsertFailureRollsBackMapCharge(): void
    {
        $this->permit($this->player);
        $this->withFailureTrigger('wells', 'INSERT', 'NEW.player_id', function (): void {
            $result = (new WorldMap($this->db))->buyWellAtLocation($this->player, $this->location);
            $this->assertFalse($result['success']);
            $this->assertSame(10000000.0, $this->funds());
            $this->assertSame(0.0, $this->transactions('map_purchase'));
            $this->assertSame(0.0, $this->scalar('SELECT COUNT(*) FROM wells WHERE location_id = ?', [$this->location]));
        });
    }

    public function testReadOnlyAuditReportsSeededDuplicatesWithoutRepair(): void
    {
        foreach ([$this->player, $this->other] as $id) {
            $this->db->prepare("INSERT INTO wells (player_id, location_id, status) VALUES (?, ?, 'active')")
                ->execute([$id, $this->location]);
        }
        $this->db->prepare('UPDATE storage SET used = -1 WHERE player_id = ?')->execute([$this->player]);
        $pipes = [];
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/tools/transaction_race_audit.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        $this->assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) fclose($pipe);
        $this->assertSame(0, proc_close($process), $stderr . $stdout);
        $report = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($report['read_only']);
        $this->assertGreaterThanOrEqual(1, $report['checks']['duplicate_active_locations']['count']);
        $this->assertGreaterThanOrEqual(1, $report['checks']['negative_oil']['count']);
        $this->assertSame(-1.0, $this->scalar('SELECT used FROM storage WHERE player_id = ?', [$this->player]));
        $this->assertSame(2.0, $this->scalar('SELECT COUNT(*) FROM wells WHERE location_id = ?', [$this->location]));
    }

    /** @dataProvider repaymentFaultModes */
    public function testLoanWriteFailureAfterFeeRollsBackEverything(string $mode, int $remaining): void
    {
        $id = $this->loan($remaining);
        $this->db->prepare("UPDATE loans SET status='late', late_since='2020-01-01 00:00:00' WHERE id=?")
            ->execute([$id]);
        $beforeLoan = $this->rows('SELECT * FROM loans WHERE id = ?', [$id]);
        $beforeBalances = $this->rows('SELECT cash, bank_balance FROM players WHERE id = ?', [$this->player]);
        $service = new BankService();
        $this->withFailureTrigger('loans', 'UPDATE', 'NEW.player_id', function () use ($service, $mode, $id, $beforeLoan, $beforeBalances): void {
            $result = $service->repay($id, $this->player, $mode);
            $this->assertFalse($result['success']);
            $this->assertSame($beforeBalances, $this->rows('SELECT cash, bank_balance FROM players WHERE id = ?', [$this->player]));
            $this->assertSame($beforeLoan, $this->rows('SELECT * FROM loans WHERE id = ?', [$id]));
            $this->assertSame(0.0, $this->scalar('SELECT COUNT(*) FROM bank_transactions WHERE from_player_id = ?', [$this->player]));
            $this->assertFeeReachedBeforeRollback(9999900.0, 0.0, $mode === 'full' ? 'paid_off' : 'active');
        }, 'loan_payment');
        $this->assertTrue($service->repay($id, $this->player, $mode)['success']);
        $this->assertSame(9999900.0, $this->funds());
        $this->assertSame(1.0, $this->transactions('loan_payment'));
        $this->assertSame((float)($remaining - 100), $this->scalar('SELECT remaining_amount FROM loans WHERE id = ?', [$id]));
    }

    public static function repaymentFaultModes(): array
    {
        return [['full', 100], ['installment', 150]];
    }

    /** @dataProvider applications */
    public function testApplicationWriteFailureAfterFeeRollsBackEverything(string $action, string $status): void
    {
        $table = $action === 'hub' ? 'hub_permit_applications' : 'drilling_permit_applications';
        if ($status !== 'none') {
            $this->db->prepare("INSERT INTO {$table} (player_id, region_id, status, cost, delay_count) VALUES (?, ?, ?, 7, 2)")
                ->execute([$this->player, $this->region, $status]);
        }
        $this->db->prepare('UPDATE players SET cash = 150, bank_balance = 60 WHERE id = ?')->execute([$this->player]);
        $beforeApplication = $this->rows("SELECT * FROM {$table} WHERE player_id = ? AND region_id = ?", [$this->player, $this->region]);
        $beforeBalances = $this->rows('SELECT cash, bank_balance FROM players WHERE id = ?', [$this->player]);
        $service = new LegalService($this->db);
        $submit = fn(): array => $action === 'hub'
            ? $service->submitHubApplication($this->player, $this->region)
            : $service->submitApplication($this->player, $this->region);
        $this->withFailureTrigger($table, $status === 'none' ? 'INSERT' : 'UPDATE', 'NEW.player_id',
            function () use ($submit, $status, $table, $beforeApplication, $beforeBalances): void {
                $result = $submit();
                $this->assertFalse($result['success']);
                $this->assertSame('error', $result['code']);
                $this->assertSame($beforeBalances, $this->rows('SELECT cash, bank_balance FROM players WHERE id = ?', [$this->player]));
                $this->assertSame($beforeApplication, $this->rows("SELECT * FROM {$table} WHERE player_id = ? AND region_id = ?", [$this->player, $this->region]));
                $this->assertSame(0.0, $this->scalar('SELECT COUNT(*) FROM bank_transactions WHERE from_player_id = ?', [$this->player]));
                $this->assertSame(0.0, $this->scalar('SELECT COUNT(*) FROM director_notifications WHERE player_id = ?', [$this->player]));
                $this->assertFeeReachedBeforeRollback(110.0, 0.0, $status === 'transitional' ? 'transitional' : 'pending');
            }, 'legal_fee');
        $this->assertTrue($submit()['success']);
        $this->assertSame(110.0, $this->funds());
        $this->assertSame(100.0, $this->scalar("SELECT SUM(amount) FROM bank_transactions WHERE from_player_id = ? AND transaction_type = 'legal_fee'", [$this->player]));
        $application = $this->rows("SELECT * FROM {$table} WHERE player_id = ? AND region_id = ?", [$this->player, $this->region]);
        $this->assertCount(1, $application);
        $this->assertSame($status === 'transitional' ? 'transitional' : 'pending', $application[0]['status']);
        if ($status === 'transitional') $this->assertSame(1, (int)$application[0]['upgrade_pending']);
    }

    private function assertFeeReachedBeforeRollback(float $cash, float $bank, string $status): void
    {
        $captured = $this->db->query('SELECT @race_fault_cash AS cash, @race_fault_bank AS bank_balance,
            @race_fault_audits AS audits, @race_fault_amount AS amount, @race_fault_status AS status')->fetch(PDO::FETCH_ASSOC);
        $this->assertNotNull($captured['cash'], 'The injected fault must occur after the fee and domain write.');
        $this->assertSame($cash, (float)$captured['cash']);
        $this->assertSame($bank, (float)$captured['bank_balance']);
        $this->assertGreaterThanOrEqual(1, (int)$captured['audits']);
        $this->assertSame(100.0, (float)$captured['amount']);
        $this->assertSame($status, $captured['status']);
    }

    private function withFailureTrigger(string $table, string $event, string $playerColumn, callable $test, ?string $feeType = null): void
    {
        $name = 'race_failure_' . $this->player;
        $capture = '';
        $timing = $feeType === null ? 'BEFORE' : 'AFTER';
        if ($feeType !== null) {
            // Session variables survive rollback and prove the fee was written first.
            // Zmienne sesji przezywaja rollback i potwierdzaja wczesniejsze pobranie oplaty.
            $this->db->exec('SET @race_fault_cash=NULL, @race_fault_bank=NULL, @race_fault_audits=NULL, @race_fault_amount=NULL, @race_fault_status=NULL');
            $quotedType = $this->db->quote($feeType);
            $capture = "SET @race_fault_cash=(SELECT cash FROM players WHERE id={$this->player});
                SET @race_fault_bank=(SELECT bank_balance FROM players WHERE id={$this->player});
                SET @race_fault_audits=(SELECT COUNT(*) FROM bank_transactions WHERE from_player_id={$this->player} AND transaction_type={$quotedType});
                SET @race_fault_amount=(SELECT SUM(amount) FROM bank_transactions WHERE from_player_id={$this->player} AND transaction_type={$quotedType});
                SET @race_fault_status=NEW.status;";
        }
        $this->db->exec("CREATE TRIGGER {$name} {$timing} {$event} ON {$table} FOR EACH ROW
            BEGIN IF {$playerColumn} = {$this->player} THEN {$capture} SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Injected regression failure'; END IF; END");
        try {
            $test();
            $this->assertFalse($this->db->inTransaction());
        } finally {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->db->exec("DROP TRIGGER {$name}");
        }
    }

    private function offer(): int
    {
        $this->db->prepare("INSERT INTO market_offers
            (player_id, amount, locked_amount, limit_price, status, editable, auto_execute, cancellation_fee, created_at)
            VALUES (?, 100, 100, 30, 'pending', 1, 1, 0.1, NOW())")->execute([$this->player]);
        return (int)$this->db->lastInsertId();
    }

    private function loan(int $remaining): int
    {
        $this->db->prepare('INSERT INTO loans (player_id, principal_amount, remaining_amount, interest_rate, installment_amount)
            VALUES (?, ?, ?, 0, 100)')->execute([$this->player, $remaining, $remaining]);
        return (int)$this->db->lastInsertId();
    }

    private function permit(int $player): void
    {
        $this->db->prepare("INSERT INTO drilling_permit_applications (player_id, region_id, status) VALUES (?, ?, 'granted')")
            ->execute([$player, $this->region]);
    }

    private function job(string $action, array $data): array
    {
        return $data + ['action' => $action, 'player' => $this->player];
    }

    private function scalar(string $sql, array $params = []): float
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    /**
     * @param list<int|string> $params
     * @return list<array<string,mixed>>
     */
    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function funds(?int $player = null): float
    {
        return $this->scalar('SELECT cash + bank_balance FROM players WHERE id = ?', [$player ?? $this->player]);
    }

    private function transactions(string $type): float
    {
        return $this->scalar('SELECT COUNT(*) FROM bank_transactions WHERE transaction_type = ? AND (from_player_id = ? OR to_player_id = ?)',
            [$type, $this->player, $this->player]);
    }

    private function successes(array $results): int
    {
        return count(array_filter($results, static fn(array $r): bool => !empty($r['result']['success'])));
    }

    private function pair(array $left, array $right, bool $lockLocation = false): array
    {
        $root = dirname(__DIR__, 2);
        $gate = tempnam(sys_get_temp_dir(), 'oil_race_gate_');
        unlink($gate);
        $workers = [];
        try {
            foreach ([$left, $right] as $job) {
                $ready = tempnam(sys_get_temp_dir(), 'oil_race_ready_');
                unlink($ready);
                $pipes = [];
                $process = proc_open([PHP_BINARY, $root . '/tests/fixtures/transaction_race_worker.php',
                    base64_encode(json_encode($job, JSON_THROW_ON_ERROR)), $ready, $gate],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
                $this->assertIsResource($process);
                $workers[] = compact('process', 'pipes', 'ready');
            }
            $this->waitFiles(array_column($workers, 'ready'));
            $this->db->beginTransaction();
            $stmt = $this->db->prepare($lockLocation
                ? 'SELECT id FROM world_locations WHERE id = ? FOR UPDATE'
                : 'SELECT id FROM players WHERE id = ? FOR UPDATE');
            $stmt->execute([$lockLocation ? $this->location : $this->player]);
            file_put_contents($gate, 'go');
            $this->waitFiles(array_map(static fn(array $w): string => $w['ready'] . '.started', $workers));
            usleep(350000);
            $this->db->commit();
            $results = [];
            foreach ($workers as &$worker) {
                $stdout = stream_get_contents($worker['pipes'][1]);
                $stderr = stream_get_contents($worker['pipes'][2]);
                foreach ($worker['pipes'] as $pipe) fclose($pipe);
                $exit = proc_close($worker['process']);
                $worker['process'] = null;
                $worker['pipes'] = [];
                $result = json_decode(trim($stdout), true);
                $this->assertSame(0, $exit, $stdout . $stderr);
                $this->assertIsArray($result, $stdout . $stderr);
                $this->assertFalse($result['transaction_open']);
                $this->assertFalse($result['session_active'], 'Workers must not serialize through PHP sessions.');
                $results[] = $result;
            }
            unset($worker);
            return $results;
        } finally {
            if ($this->db->inTransaction()) $this->db->rollBack();
            foreach ($workers as $worker) {
                if (is_resource($worker['process'])) {
                    proc_terminate($worker['process']);
                    foreach ($worker['pipes'] as $pipe) if (is_resource($pipe)) fclose($pipe);
                    proc_close($worker['process']);
                }
                @unlink($worker['ready']);
                @unlink($worker['ready'] . '.started');
            }
            @unlink($gate);
        }
    }

    private function waitFiles(array $paths): void
    {
        $deadline = microtime(true) + 20;
        do {
            clearstatcache();
            if (count(array_filter($paths, 'is_file')) === count($paths)) return;
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Concurrent workers did not reach the barrier.');
    }
}
