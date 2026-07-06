<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/init.php';

/**
 * L5: processApplication musi atomowo "zaklepac" wniosek, zeby tick (BankSection)
 * i osobny cron (cron/process_loan_decisions.php) nie przetworzyly go podwojnie.
 * L5: processApplication must atomically claim the application so the tick and the
 * standalone cron do not double-process it.
 */
final class MySqlLoanDecisionClaimTest extends MySqlIntegrationTestCase
{
    private function insertApplication(int $playerId, string $decisionAtExpr): int
    {
        $this->db->prepare(
            "INSERT INTO loan_applications (player_id, requested_amount, status, created_at, decision_at)
             VALUES (?, 100000.00, 'pending', NOW(), {$decisionAtExpr})"
        )->execute([$playerId]);

        return (int)$this->db->lastInsertId();
    }

    /** @return array{status:string,due:int} */
    private function fetchApp(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT status, (decision_at <= NOW()) AS due FROM loan_applications WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return ['status' => (string)$row['status'], 'due' => (int)$row['due']];
    }

    protected function tearDown(): void
    {
        $playerId = $this->getTrackedIds()['playerId'];
        try {
            $this->db->prepare('DELETE FROM loan_applications WHERE player_id = ?')->execute([$playerId]);
        } catch (\Throwable $e) {
        }
        parent::tearDown();
    }

    public function testFutureDatedApplicationIsNotProcessed(): void
    {
        $playerId = $this->seedPlayer();
        // decision_at w przyszlosci -> jeszcze nie wymagalny; claim (decision_at <= NOW()) nie lapie.
        // decision_at in the future -> not due yet; the claim (decision_at <= NOW()) must not match.
        $appId = $this->insertApplication($playerId, 'DATE_ADD(NOW(), INTERVAL 1 HOUR)');

        $svc = new LoanDecisionService();
        $result = $svc->processApplication($appId);

        $this->assertFalse($result, 'Wniosek z decision_at w przyszlosci nie jest przetwarzany');
        $this->assertSame('pending', $this->fetchApp($appId)['status'], 'Status pozostaje pending');
    }

    public function testDueApplicationIsClaimedAndNotReprocessable(): void
    {
        $playerId = $this->seedPlayer();
        $appId = $this->insertApplication($playerId, 'DATE_SUB(NOW(), INTERVAL 1 MINUTE)');

        $svc = new LoanDecisionService();
        $svc->processApplication($appId);

        // Po zaklepaniu wniosek wypada ze zbioru "pending AND decision_at <= NOW()":
        // albo decyzja zmienila status, albo (gdy silnik ryzyka rzucil) claim przesunal
        // decision_at w przyszlosc. Tak czy siak okno na podwojne przetworzenie jest zamkniete.
        // After the claim the row leaves the "pending AND decision_at <= NOW()" set: either the
        // decision changed the status, or (if the risk engine threw) the claim bumped decision_at
        // into the future. Either way the double-processing window is closed.
        $row = $this->fetchApp($appId);
        $stillClaimable = ($row['status'] === 'pending' && $row['due'] === 1);
        $this->assertFalse($stillClaimable, 'Zaklepany wniosek nie moze byc ponownie pobrany do przetworzenia');

        // Drugie wywolanie nie zwraca decyzji (brak duplikatu decyzji).
        // A second call yields no decision (no duplicate decision).
        $this->assertFalse($svc->processApplication($appId), 'Drugie przetworzenie zwraca false');
    }
}
