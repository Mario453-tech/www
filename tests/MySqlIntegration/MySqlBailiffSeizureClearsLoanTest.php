<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';

/**
 * H1/M1 (runda 5) — komornik: zajecie ktore pokrywa dlug musi oznaczyc pozyczke jako
 * splacona (paid_off) i zakonczyc postepowanie, zamiast eskalowac do zajecia odwiertow
 * i bankructwa mimo zerowego salda. Etap 2 nie moze zejsc ponizej zera (GREATEST(0)).
 *
 * H1/M1 (round 5) — bailiff: a seizure that covers the debt must mark the loan paid_off and
 * complete the proceeding, instead of escalating to well seizure / bankruptcy on a zero
 * balance. Stage 2 must not go below zero (GREATEST(0)).
 */
final class MySqlBailiffSeizureClearsLoanTest extends MySqlIntegrationTestCase
{
    private int $loanId;
    private int $procId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loanId = $this->seed + 50;
        $this->procId = $this->seed + 51;
        $this->cleanupLoanRows();
    }

    protected function tearDown(): void
    {
        $this->cleanupLoanRows();
        parent::tearDown();
    }

    private function cleanupLoanRows(): void
    {
        $pid = $this->getTrackedIds()['playerId'];
        foreach (['loan_payments', 'bailiff_proceedings', 'loans'] as $t) {
            try {
                $this->db->prepare("DELETE FROM `{$t}` WHERE player_id = ?")->execute([$pid]);
            } catch (Throwable $e) {}
        }
    }

    private function seedLateLoan(float $remaining): void
    {
        $pid = $this->getTrackedIds()['playerId'];
        $this->db->prepare(
            "INSERT INTO loans
                (id, player_id, principal_amount, remaining_amount, interest_rate,
                 installment_amount, installment_frequency, next_installment_at, status,
                 late_since, created_at, last_interest_calc_at)
             VALUES (?, ?, ?, ?, 5.00, 50.00, 12, DATE_ADD(NOW(), INTERVAL 12 HOUR), 'late',
                     DATE_SUB(NOW(), INTERVAL 72 HOUR), DATE_SUB(NOW(), INTERVAL 30 DAY), NOW())"
        )->execute([$this->loanId, $pid, $remaining, $remaining]);
    }

    private function seedProceeding(int $stage): void
    {
        $pid = $this->getTrackedIds()['playerId'];
        $this->db->prepare(
            "INSERT INTO bailiff_proceedings
                (id, loan_id, player_id, stage, status, next_action_at, started_at)
             VALUES (?, ?, ?, ?, 'active', DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 24 HOUR))"
        )->execute([$this->procId, $this->loanId, $pid, $stage]);
    }

    /** @return array{status:string, remaining:float} */
    private function loanState(): array
    {
        $row = $this->db->query("SELECT status, remaining_amount FROM loans WHERE id = {$this->loanId}")->fetch();
        return ['status' => (string)$row['status'], 'remaining' => (float)$row['remaining_amount']];
    }

    private function procStatus(): string
    {
        return (string)$this->db->query("SELECT status FROM bailiff_proceedings WHERE id = {$this->procId}")->fetchColumn();
    }

    public function testStage2SeizureClearsSmallDebtAndMarksPaidOff(): void
    {
        $this->seedPlayer(); // cash 50 000 000 -> 30% = 15 000 000 >> 100 remaining
        $this->seedLateLoan(100.0);
        $this->seedProceeding(2);

        (new BailiffService())->process();

        $loan = $this->loanState();
        $this->assertSame('paid_off', $loan['status'],
            'H1: dlug pokryty zajeciem musi zostac oznaczony paid_off (nie zostac late)');
        $this->assertEqualsWithDelta(0.0, $loan['remaining'], 0.001,
            'M1: remaining_amount nie moze byc ujemny (GREATEST(0)) — dokladnie 0 po pokryciu');
        $this->assertSame('completed', $this->procStatus(),
            'H1: postepowanie musi sie zakonczyc, nie eskalowac do etapu 3/bankructwa');
    }

    public function testStage2LargeDebtDoesNotClearButStaysNonNegative(): void
    {
        $this->seedPlayer(); // 30% z 50M = 15M
        $this->seedLateLoan(80_000_000.0); // dlug > zajecie -> nie splacony
        $this->seedProceeding(2);

        (new BailiffService())->process();

        $loan = $this->loanState();
        $this->assertSame('late', $loan['status'],
            'Duzy dlug pozostaje late po czesciowym zajeciu');
        $this->assertGreaterThan(0.0, $loan['remaining'],
            'Czesciowe zajecie zostawia dodatnie saldo');
        $this->assertSame('active', $this->procStatus(),
            'Postepowanie eskaluje (pozostaje aktywne w etapie 3)');
    }
}
