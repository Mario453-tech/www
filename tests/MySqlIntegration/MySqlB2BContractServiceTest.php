<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';
require_once dirname(__DIR__, 2) . '/src/B2BContractService.php';

/**
 * MySQL regression tests for B2B contracts.
 * Testy regresyjne MySQL dla kontraktow B2B.
 */
final class MySqlB2BContractServiceTest extends MySqlIntegrationTestCase
{
    protected function tearDown(): void
    {
        $ids = $this->getB2BIds();
        try {
            $this->db->prepare('DELETE FROM b2b_contract_logs WHERE player_id IN (?, ?)')->execute([$ids['buyer'], $ids['seller']]);
            $this->db->prepare('DELETE FROM b2b_reputation_logs WHERE player_id IN (?, ?)')->execute([$ids['buyer'], $ids['seller']]);
            $this->db->prepare('DELETE FROM b2b_reputation_scores WHERE player_id IN (?, ?)')->execute([$ids['buyer'], $ids['seller']]);
            $this->db->prepare('DELETE FROM b2b_contract_deliveries WHERE buyer_player_id IN (?, ?) OR seller_player_id IN (?, ?)')->execute([
                $ids['buyer'], $ids['seller'], $ids['buyer'], $ids['seller'],
            ]);
            $this->db->prepare('DELETE FROM b2b_contract_offers WHERE buyer_player_id IN (?, ?) OR seller_player_id IN (?, ?)')->execute([
                $ids['buyer'], $ids['seller'], $ids['buyer'], $ids['seller'],
            ]);
            $this->db->prepare('DELETE FROM bank_transactions WHERE from_player_id IN (?, ?) OR to_player_id IN (?, ?)')->execute([
                $ids['buyer'], $ids['seller'], $ids['buyer'], $ids['seller'],
            ]);
            $this->db->prepare('DELETE FROM storage WHERE player_id IN (?, ?)')->execute([$ids['buyer'], $ids['seller']]);
            $this->db->prepare('DELETE FROM players WHERE id IN (?, ?)')->execute([$ids['buyer'], $ids['seller']]);
        } catch (Throwable) {
        }

        parent::tearDown();
    }

    public function testRealConcurrentAcceptOfferOnlyOneWinsMysql(): void
    {
        $ids = $this->getB2BIds();
        $this->seedB2BPlayer($ids['buyer'], 0.0, 50000.0);
        $this->seedB2BPlayer($ids['seller'], 0.0, 0.0);
        $this->db->prepare('INSERT INTO storage (player_id, capacity, used) VALUES (?, ?, ?)')
            ->execute([$ids['seller'], 2000.0, 500.0]);

        $service = new B2BContractService($this->db);
        $created = $service->createBuyOffer($ids['buyer'], 100.0, 100.0, 120);
        $this->assertTrue($created['success'], (string)($created['status'] ?? 'create_failed'));
        $offerId = (int)$created['offer_id'];

        $this->lockOfferInParent($offerId);
        $workers = $this->startConcurrentB2BWorkers('accept', $ids['seller'], $offerId, 30.0, 2);
        $this->waitForWorkersReady($workers);
        usleep(200000);
        $this->db->commit();

        $results = $this->collectB2BWorkers($workers);
        $successes = array_values(array_filter($results, static fn (array $row): bool => !empty($row['result']['success'])));
        $failures = array_values(array_filter($results, static fn (array $row): bool => empty($row['result']['success'])));

        $this->assertCount(1, $successes, json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'accept race results');
        $this->assertCount(1, $failures, json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'accept race failures');
        $this->assertSame('accepted', (string)$successes[0]['result']['status']);
        $this->assertSame('not_open', (string)$failures[0]['result']['status']);

        $stmt = $this->db->prepare('SELECT status, delivered_bbl, remaining_bbl, released_amount, remaining_escrow_amount FROM b2b_contract_offers WHERE id = ?');
        $stmt->execute([$offerId]);
        $offer = $stmt->fetch();
        $this->assertSame('accepted', (string)$offer['status']);
        $this->assertSame(30.0, round((float)$offer['delivered_bbl'], 2));
        $this->assertSame(70.0, round((float)$offer['remaining_bbl'], 2));
        $this->assertSame(3000.0, round((float)$offer['released_amount'], 2));
        $this->assertSame(7000.0, round((float)$offer['remaining_escrow_amount'], 2));
        $this->assertSame(1, $this->deliveryCount($offerId));
        $this->assertSame(1, $this->countTx(FinancialTransactionService::TYPE_B2B_TRADE_REVENUE, $ids['seller']));
        $this->assertSame(3000.0, $this->bankOf($ids['seller']));
        $this->assertSame(470.0, $this->storageOf($ids['seller']));
    }

    public function testRealConcurrentDeliverPartialOnlyOneWinsMysql(): void
    {
        $ids = $this->getB2BIds();
        $this->seedB2BPlayer($ids['buyer'], 0.0, 50000.0);
        $this->seedB2BPlayer($ids['seller'], 0.0, 0.0);
        $this->db->prepare('INSERT INTO storage (player_id, capacity, used) VALUES (?, ?, ?)')
            ->execute([$ids['seller'], 2000.0, 500.0]);

        $service = new B2BContractService($this->db);
        $created = $service->createBuyOffer($ids['buyer'], 100.0, 100.0, 120);
        $this->assertTrue($created['success'], (string)($created['status'] ?? 'create_failed'));
        $offerId = (int)$created['offer_id'];

        $accepted = $service->acceptOffer($ids['seller'], $offerId, 30.0);
        $this->assertTrue($accepted['success'], json_encode($accepted) ?: 'accept_failed');

        $this->lockOfferInParent($offerId);
        $workers = $this->startConcurrentB2BWorkers('deliver', $ids['seller'], $offerId, 70.0, 2);
        $this->waitForWorkersReady($workers);
        usleep(200000);
        $this->db->commit();

        $results = $this->collectB2BWorkers($workers);
        $successes = array_values(array_filter($results, static fn (array $row): bool => !empty($row['result']['success'])));
        $failures = array_values(array_filter($results, static fn (array $row): bool => empty($row['result']['success'])));

        $this->assertCount(1, $successes, json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'delivery race results');
        $this->assertCount(1, $failures, json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'delivery race failures');
        $this->assertSame('completed', (string)$successes[0]['result']['status']);
        $this->assertSame('not_accepted', (string)$failures[0]['result']['status']);

        $stmt = $this->db->prepare('SELECT status, delivered_bbl, remaining_bbl, released_amount, remaining_escrow_amount FROM b2b_contract_offers WHERE id = ?');
        $stmt->execute([$offerId]);
        $offer = $stmt->fetch();
        $this->assertSame('completed', (string)$offer['status']);
        $this->assertSame(100.0, round((float)$offer['delivered_bbl'], 2));
        $this->assertSame(0.0, round((float)$offer['remaining_bbl'], 2));
        $this->assertSame(10000.0, round((float)$offer['released_amount'], 2));
        $this->assertSame(0.0, round((float)$offer['remaining_escrow_amount'], 2));
        $this->assertSame(2, $this->deliveryCount($offerId));
        $this->assertSame(2, $this->countTx(FinancialTransactionService::TYPE_B2B_TRADE_REVENUE, $ids['seller']));
        $this->assertSame(10000.0, $this->bankOf($ids['seller']));
        $this->assertSame(400.0, $this->storageOf($ids['seller']));
    }

    /**
     * Weryfikuje ze FOR UPDATE blokuje double-delivery.
     * Test symuluje rase condition przez dwa sequentiale wywolania
     * na tle stanu ktory powinien pozwolic tylko jednej dostawie przejsc.
     * W produkcji SELECT FOR UPDATE zapewnia atomowosc — tutaj testujemy
     * poprawnosc state machine (drugi deliverPartial widzi completed).
     */
    public function testConcurrentDeliverPartialOnlyOneSucceedsMysql(): void
    {
        $ids = $this->getB2BIds();
        $this->seedB2BPlayer($ids['buyer'], 0.0, 50000.0);
        $this->seedB2BPlayer($ids['seller'], 0.0, 0.0);
        $this->db->prepare('INSERT INTO storage (player_id, capacity, used) VALUES (?, ?, ?)')
            ->execute([$ids['seller'], 2000.0, 500.0]);

        $service = new B2BContractService($this->db);
        $created = $service->createBuyOffer($ids['buyer'], 100.0, 100.0, 120);
        $this->assertTrue($created['success'], (string)($created['status'] ?? 'create_failed'));
        $offerId = (int)$created['offer_id'];

        // Seller accepts with 30 bbl first delivery
        $accepted = $service->acceptOffer($ids['seller'], $offerId, 30.0);
        $this->assertTrue($accepted['success'], json_encode($accepted) ?: 'accept_failed');

        // Simulated race: two deliveries for remaining 70 bbl
        // First call delivers exactly remaining 70 and completes the offer
        $first = $service->deliverPartial($ids['seller'], $offerId, 70.0);
        // Second call arrives after — should fail because offer is now 'completed'
        $second = $service->deliverPartial($ids['seller'], $offerId, 70.0);

        $this->assertTrue($first['success'], json_encode($first) ?: 'first_delivery_failed');
        $this->assertSame('completed', $first['status']);
        $this->assertFalse($second['success'], 'Second deliverPartial must fail after completion');
        $this->assertSame('not_accepted', $second['status']);

        // Total delivered must equal total_bbl (100), never more
        $stmt = $this->db->prepare('SELECT delivered_bbl, total_bbl FROM b2b_contract_offers WHERE id = ?');
        $stmt->execute([$offerId]);
        $data = $stmt->fetch();
        $this->assertSame(100.0, round((float)$data['delivered_bbl'], 2));

        // Exactly 2 delivery records: initial acceptOffer + one deliverPartial
        $stmt2 = $this->db->prepare('SELECT COUNT(*) FROM b2b_contract_deliveries WHERE offer_id = ?');
        $stmt2->execute([$offerId]);
        $this->assertSame(2, (int)$stmt2->fetchColumn());
    }

    public function testSameOfferCanBeAcceptedOnlyOnce(): void
    {
        $ids = $this->getB2BIds();
        $this->seedB2BPlayer($ids['buyer'], 0.0, 50000.0);
        $this->seedB2BPlayer($ids['seller'], 0.0, 100.0);
        $this->db->prepare('INSERT INTO storage (player_id, capacity, used) VALUES (?, ?, ?)')
            ->execute([$ids['seller'], 1000.0, 250.0]);

        $service = new B2BContractService($this->db);
        $created = $service->createBuyOffer($ids['buyer'], 100.0, 100.0, 120);
        $this->assertTrue($created['success'], (string)($created['status'] ?? 'create_failed'));
        $offerId = (int)$created['offer_id'];

        $first = $service->acceptAndDeliver($ids['seller'], $offerId);
        $second = $service->acceptAndDeliver($ids['seller'], $offerId);

        $this->assertTrue($first['success'], json_encode($first, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'first accept failed');
        $this->assertFalse($second['success']);
        $this->assertSame('not_open', $second['status']);
        $this->assertSame(10100.0, $this->bankOf($ids['seller']));
        $this->assertSame(1, $this->countTx(FinancialTransactionService::TYPE_B2B_TRADE_REVENUE, $ids['seller']));
        $this->assertSame(53, $this->b2bScore($ids['seller']));
    }

    /** @return array{buyer:int,seller:int} */
    private function getB2BIds(): array
    {
        $base = $this->getTrackedIds()['playerId'];
        return ['buyer' => $base + 80, 'seller' => $base + 81];
    }

    private function seedB2BPlayer(int $id, float $cash, float $bank): void
    {
        $username = 'phpunit_b2b_' . $id;
        $this->db->prepare(
            'INSERT INTO players (id, username, email, password_hash, cash, bank_balance, status, created_at, last_tick_at)
             VALUES (?, ?, ?, ?, ?, ?, \'active\', NOW(), NOW())'
        )->execute([$id, $username, $username . '@example.test', password_hash('secret', PASSWORD_BCRYPT), $cash, $bank]);
    }

    private function bankOf(int $playerId): float
    {
        $stmt = $this->db->prepare('SELECT bank_balance FROM players WHERE id = ?');
        $stmt->execute([$playerId]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    private function countTx(string $type, int $toPlayerId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM bank_transactions WHERE transaction_type = ? AND to_player_id = ?');
        $stmt->execute([$type, $toPlayerId]);
        return (int)$stmt->fetchColumn();
    }

    private function b2bScore(int $playerId): int
    {
        $stmt = $this->db->prepare('SELECT score FROM b2b_reputation_scores WHERE player_id = ?');
        $stmt->execute([$playerId]);
        return (int)$stmt->fetchColumn();
    }

    private function storageOf(int $playerId): float
    {
        $stmt = $this->db->prepare('SELECT used FROM storage WHERE player_id = ?');
        $stmt->execute([$playerId]);
        return round((float)$stmt->fetchColumn(), 2);
    }

    private function deliveryCount(int $offerId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM b2b_contract_deliveries WHERE offer_id = ?');
        $stmt->execute([$offerId]);
        return (int)$stmt->fetchColumn();
    }

    private function lockOfferInParent(int $offerId): void
    {
        $this->db->beginTransaction();
        $stmt = $this->db->prepare('SELECT id FROM b2b_contract_offers WHERE id = ? FOR UPDATE');
        $stmt->execute([$offerId]);
        $this->assertSame($offerId, (int)$stmt->fetchColumn());
    }

    /**
     * @return list<array{process:resource,pipes:array<int,resource>,ready:string}>
     */
    private function startConcurrentB2BWorkers(string $action, int $sellerId, int $offerId, float $bbl, int $count): array
    {
        $root = dirname(__DIR__, 2);
        $worker = $root . '/tests/fixtures/b2b_concurrent_worker.php';
        $workers = [];

        for ($i = 0; $i < $count; $i++) {
            $ready = tempnam(sys_get_temp_dir(), 'b2b_ready_');
            $this->assertIsString($ready);
            @unlink($ready);

            $descriptors = [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open(
                [PHP_BINARY, $worker, $action, (string)$sellerId, (string)$offerId, (string)$bbl, $ready],
                $descriptors,
                $pipes,
                $root
            );
            $this->assertIsResource($process);
            $workers[] = ['process' => $process, 'pipes' => $pipes, 'ready' => $ready];
        }

        return $workers;
    }

    /**
     * @param list<array{process:resource,pipes:array<int,resource>,ready:string}> $workers
     */
    private function waitForWorkersReady(array $workers): void
    {
        $deadline = microtime(true) + 10.0;
        do {
            $allReady = true;
            foreach ($workers as $worker) {
                if (!is_file($worker['ready'])) {
                    $allReady = false;
                    break;
                }
            }
            if ($allReady) {
                return;
            }
            usleep(25000);
        } while (microtime(true) < $deadline);

        $this->fail('Timed out waiting for B2B concurrent workers to become ready');
    }

    /**
     * @param list<array{process:resource,pipes:array<int,resource>,ready:string}> $workers
     * @return list<array{exit:int,stdout:string,stderr:string,result:array<string,mixed>}>
     */
    private function collectB2BWorkers(array $workers): array
    {
        $results = [];
        foreach ($workers as $worker) {
            $stdout = stream_get_contents($worker['pipes'][1]);
            $stderr = stream_get_contents($worker['pipes'][2]);
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            $exit = proc_close($worker['process']);
            @unlink($worker['ready']);

            $decoded = json_decode(trim((string)$stdout), true);
            $this->assertIsArray($decoded, 'Worker stdout: ' . $stdout . ' stderr: ' . $stderr);
            $results[] = [
                'exit' => $exit,
                'stdout' => (string)$stdout,
                'stderr' => (string)$stderr,
                'result' => $decoded,
            ];
        }
        return $results;
    }
}
