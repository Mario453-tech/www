<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/ContractService.php';
require_once dirname(__DIR__, 2) . '/src/FinancialTransactionService.php';

/**
 * ContractService Faza 1 — bonus za realizacje, kaucja, zerwanie, ubezpieczenie, renegocjacja.
 * ContractService Phase 1 — completion bonus, deposit, cancellation, insurance, renegotiation.
 */
final class ContractServiceFaza1Test extends SqliteIntegrationTestCase
{
    private PDO $db;
    private ContractService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->service = new ContractService($this->db);
        $this->service->setModuleEnabled(true);
    }

    // ================================================================== security deposit — signing

    public function testSecurityDepositDebitedOnSigning(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);
        $optionId = $this->optionId('medium_fuel_network');

        $result = $this->service->acceptContract(1, $optionId, ContractService::TARGET_STORAGE, null, ContractService::CONTEXT_STORAGE_DELIVERY);

        $this->assertTrue($result['success'], $result['status']);
        // 100 000 bank − 50 000 deposit = 50 000
        $this->assertSame(50000.0, $this->bankOf(1));
        $deposit = (float)$this->db->query("SELECT security_deposit FROM player_contracts WHERE player_id = 1")->fetchColumn();
        $this->assertSame(50000.0, $deposit);
    }

    public function testSigningFailsWithInsufficientFundsForDeposit(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);

        $result = $this->service->acceptContract(1, $this->optionId('medium_fuel_network'), ContractService::TARGET_STORAGE, null, ContractService::CONTEXT_STORAGE_DELIVERY);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_funds_deposit', $result['status']);
        $this->assertSame(0, (int)$this->db->query('SELECT COUNT(*) FROM player_contracts')->fetchColumn());
    }

    // ================================================================== security deposit — completion / failure

    public function testSecurityDepositRefundedOnContractCompletion(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $this->seedStorage(1, 200.0);
        // 60 of 100 bbl already delivered; this tick delivers the remaining 40 and completes.
        $contractId = $this->insertContract(1, [
            'next_delivery_at' => '2025-06-01 11:00:00',
            'ends_at'          => '2099-12-31 00:00:00',
            'total_bbl'        => 100.0,
            'delivered_bbl'    => 60.0,
            'security_deposit' => 50000.0,
            'extra_terms'      => [
                'delivery_bbl' => ['type' => 'number',  'value' => 50.0, 'text' => null],
                'penalty_pct'  => ['type' => 'percent', 'value' => 0.0,  'text' => null],
            ],
        ]);

        $this->service->processDueContracts(100.0);

        $status = $this->db->query("SELECT status FROM player_contracts WHERE id = {$contractId}")->fetchColumn();
        $this->assertSame('completed', $status);
        // revenue = 40 bbl * 100 = 4 000; deposit refund = 50 000
        $this->assertSame(54000.0, $this->bankOf(1));
    }

    public function testSecurityDepositNotRefundedOnContractFailure(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $this->seedStorage(1, 0.0);
        // ends_at in the past → contract fails on this tick.
        $contractId = $this->insertContract(1, [
            'next_delivery_at' => '2025-06-01 11:00:00',
            'ends_at'          => '2025-06-01 10:00:00',
            'total_bbl'        => 500.0,
            'delivered_bbl'    => 0.0,
            'security_deposit' => 50000.0,
            'extra_terms'      => [
                'delivery_bbl' => ['type' => 'number',  'value' => 50.0, 'text' => null],
                'penalty_pct'  => ['type' => 'percent', 'value' => 0.0,  'text' => null],
            ],
        ]);

        $this->service->processDueContracts(100.0);

        $status = $this->db->query("SELECT status FROM player_contracts WHERE id = {$contractId}")->fetchColumn();
        $this->assertSame('failed', $status);
        // deposit forfeited — bank stays at 0
        $this->assertSame(0.0, $this->bankOf(1));
    }

    // ================================================================== cancellation

    public function testCancelNotAllowedWhenFlagIsZero(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $contractId = $this->insertContract(1, [
            'extra_terms' => [
                'allow_cancel' => ['type' => 'number', 'value' => 0.0, 'text' => null],
            ],
        ]);

        $result = $this->service->cancelContract(1, $contractId);

        $this->assertFalse($result['success']);
        $this->assertSame('cancel_not_allowed', $result['status']);
        $status = $this->db->query("SELECT status FROM player_contracts WHERE id = {$contractId}")->fetchColumn();
        $this->assertSame('active', $status);
    }

    public function testCancelDeductsPenaltyAndForfeitsDeposit(): void
    {
        $this->seedPlayer(1, 0.0, 200000.0);
        $contractId = $this->insertContract(1, [
            'security_deposit' => 50000.0,
            'extra_terms'      => [
                'allow_cancel'           => ['type' => 'number', 'value' => 1.0,     'text' => null],
                'cancel_penalty_fixed'   => ['type' => 'number', 'value' => 25000.0, 'text' => null],
                'cancel_forfeit_deposit' => ['type' => 'number', 'value' => 1.0,     'text' => null],
            ],
        ]);

        $result = $this->service->cancelContract(1, $contractId);

        $this->assertTrue($result['success']);
        $this->assertSame('cancelled', $result['status']);
        // 200 000 − 25 000 penalty = 175 000; deposit forfeited (no refund)
        $this->assertSame(175000.0, $this->bankOf(1));
    }

    public function testCancelRefundsDepositWhenForfeitFlagIsZero(): void
    {
        $this->seedPlayer(1, 0.0, 200000.0);
        $contractId = $this->insertContract(1, [
            'security_deposit' => 50000.0,
            'extra_terms'      => [
                'allow_cancel'           => ['type' => 'number', 'value' => 1.0, 'text' => null],
                'cancel_penalty_fixed'   => ['type' => 'number', 'value' => 0.0, 'text' => null],
                'cancel_forfeit_deposit' => ['type' => 'number', 'value' => 0.0, 'text' => null],
            ],
        ]);

        $result = $this->service->cancelContract(1, $contractId);

        $this->assertTrue($result['success']);
        // 200 000 + 50 000 deposit refund = 250 000
        $this->assertSame(250000.0, $this->bankOf(1));
    }

    // ================================================================== completion bonus

    public function testCompletionBonusPaidWhenNoMissRequired(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $this->seedStorage(1, 200.0);
        $this->insertContract(1, [
            'next_delivery_at' => '2025-06-01 11:00:00',
            'ends_at'          => '2099-12-31 00:00:00',
            'total_bbl'        => 100.0,
            'delivered_bbl'    => 60.0, // 40 remaining → completes this tick
            'extra_terms'      => [
                'delivery_bbl'                 => ['type' => 'number',  'value' => 50.0, 'text' => null],
                'penalty_pct'                  => ['type' => 'percent', 'value' => 0.0,  'text' => null],
                'bonus_on_full_completion_pct' => ['type' => 'percent', 'value' => 10.0, 'text' => null],
                'bonus_requires_no_miss'       => ['type' => 'number',  'value' => 0.0,  'text' => null],
            ],
        ]);

        $this->service->processDueContracts(100.0);

        // revenue = 40 * 100 = 4 000; bonus = 100 * 100 * 10% = 1 000
        $this->assertSame(5000.0, $this->bankOf(1));
    }

    public function testCompletionBonusNotPaidWhenMissOccurredAndFlagSet(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $this->seedStorage(1, 200.0);
        // missed_bbl = 20 already recorded → totalMissedAfter > 0 → bonus suppressed
        $this->insertContract(1, [
            'next_delivery_at' => '2025-06-01 11:00:00',
            'ends_at'          => '2099-12-31 00:00:00',
            'total_bbl'        => 100.0,
            'delivered_bbl'    => 60.0,
            'missed_bbl'       => 20.0,
            'extra_terms'      => [
                'delivery_bbl'                 => ['type' => 'number',  'value' => 50.0, 'text' => null],
                'penalty_pct'                  => ['type' => 'percent', 'value' => 0.0,  'text' => null],
                'bonus_on_full_completion_pct' => ['type' => 'percent', 'value' => 10.0, 'text' => null],
                'bonus_requires_no_miss'       => ['type' => 'number',  'value' => 1.0,  'text' => null],
            ],
        ]);

        $this->service->processDueContracts(100.0);

        // revenue = 40 * 100 = 4 000; bonus suppressed (missed_bbl > 0)
        $this->assertSame(4000.0, $this->bankOf(1));
    }

    // ================================================================== insurance

    public function testInsuranceDebitedOnEnable(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);
        // medium_fuel_network: deposit=50 000, insurance_cost_pct=20, coverage=50%
        $accepted = $this->service->acceptContract(1, $this->optionId('medium_fuel_network'), ContractService::TARGET_STORAGE, null, ContractService::CONTEXT_STORAGE_DELIVERY);
        $this->assertTrue($accepted['success'], $accepted['status']);
        $contractId = (int)$accepted['contract_id'];
        $this->assertSame(50000.0, $this->bankOf(1)); // 100 000 − 50 000 deposit

        $result = $this->service->enableInsurance(1, $contractId);

        $this->assertTrue($result['success'], $result['status']);
        $this->assertSame('insurance_enabled', $result['status']);
        // cost = 50 000 * 20% = 10 000
        $this->assertSame(10000.0, $result['cost']);
        $this->assertSame(40000.0, $this->bankOf(1)); // 50 000 − 10 000

        $row = $this->db->query("SELECT insurance_enabled, insurance_cost FROM player_contracts WHERE id = {$contractId}")->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(1, (int)$row['insurance_enabled']);
        $this->assertSame(10000.0, (float)$row['insurance_cost']);
    }

    public function testInsuranceReducesPenaltyOnMissedDelivery(): void
    {
        $this->seedPlayer(1, 0.0, 1000000.0);
        $this->seedStorage(1, 0.0); // no oil → full miss
        $contractId = $this->insertContract(1, [
            'next_delivery_at'       => '2025-06-01 11:00:00',
            'ends_at'                => '2099-12-31 00:00:00',
            'total_bbl'              => 500.0,
            'delivered_bbl'          => 0.0,
            'insurance_enabled'      => 1,
            'insurance_coverage_pct' => 50.0,
            'extra_terms'            => [
                'delivery_bbl' => ['type' => 'number',  'value' => 50.0,  'text' => null],
                'penalty_pct'  => ['type' => 'percent', 'value' => 10.0,  'text' => null],
            ],
        ]);

        $result = $this->service->processDueContracts(100.0);

        // Full miss: 50 bbl * 100 * 10% = 500; insurance covers 50% → actual = 250
        $this->assertSame(250.0, $result['penalties']);
        $this->assertSame(999750.0, $this->bankOf(1));
    }

    public function testInsuranceNotAvailableBlocked(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);
        $contractId = $this->insertContract(1, [
            'extra_terms' => [
                'insurance_available' => ['type' => 'number', 'value' => 0.0, 'text' => null],
            ],
        ]);

        $result = $this->service->enableInsurance(1, $contractId);

        $this->assertFalse($result['success']);
        $this->assertSame('insurance_not_available', $result['status']);
    }

    public function testAlreadyInsuredIsBlocked(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);
        $contractId = $this->insertContract(1, [
            'insurance_enabled' => 1,
            'extra_terms'       => [
                'insurance_available' => ['type' => 'number', 'value' => 1.0, 'text' => null],
            ],
        ]);

        $result = $this->service->enableInsurance(1, $contractId);

        $this->assertFalse($result['success']);
        $this->assertSame('already_insured', $result['status']);
    }

    // ================================================================== renegotiation

    public function testRenegotiationChangesTermsAndWritesLog(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);
        $accepted = $this->service->acceptContract(1, $this->optionId('medium_fuel_network'), ContractService::TARGET_STORAGE, null, ContractService::CONTEXT_STORAGE_DELIVERY);
        $this->assertTrue($accepted['success'], $accepted['status']);
        $contractId = (int)$accepted['contract_id'];

        $result = $this->service->renegotiateContract(1, $contractId, ['penalty_pct' => 5.0]);

        $this->assertTrue($result['success'], $result['status']);
        $this->assertSame('renegotiated', $result['status']);
        $this->assertSame(1, $result['renegotiations_used']);

        $termsJson = $this->db->query("SELECT terms_json FROM player_contracts WHERE id = {$contractId}")->fetchColumn();
        $terms = json_decode((string)$termsJson, true);
        $this->assertSame(5.0, (float)$terms['penalty_pct']['value']);

        $reneg = (int)$this->db->query("SELECT COUNT(*) FROM contract_renegotiations WHERE player_contract_id = {$contractId}")->fetchColumn();
        $this->assertSame(1, $reneg);
    }

    public function testRenegotiationLimitIsEnforced(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);
        $accepted = $this->service->acceptContract(1, $this->optionId('medium_fuel_network'), ContractService::TARGET_STORAGE, null, ContractService::CONTEXT_STORAGE_DELIVERY);
        $this->assertTrue($accepted['success'], $accepted['status']);
        $contractId = (int)$accepted['contract_id'];

        // First renegotiation — allowed (max = 1).
        $r1 = $this->service->renegotiateContract(1, $contractId, ['penalty_pct' => 5.0]);
        $this->assertTrue($r1['success']);

        // Second renegotiation — limit reached.
        $r2 = $this->service->renegotiateContract(1, $contractId, ['penalty_pct' => 2.0]);
        $this->assertFalse($r2['success']);
        $this->assertSame('renegotiation_limit_reached', $r2['status']);
    }

    public function testRenegotiationNotAllowedWhenFlagIsZero(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $contractId = $this->insertContract(1, [
            'extra_terms' => [
                'allow_renegotiation' => ['type' => 'number', 'value' => 0.0, 'text' => null],
            ],
        ]);

        $result = $this->service->renegotiateContract(1, $contractId, ['penalty_pct' => 5.0]);

        $this->assertFalse($result['success']);
        $this->assertSame('renegotiation_not_allowed', $result['status']);
    }

    public function testRenegotiationTooSoonAfterPreviousRenegotiation(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        // max = 2 and interval = 720 min so the second call triggers "too soon" (not limit).
        $contractId = $this->insertContract(1, [
            'extra_terms' => [
                'allow_renegotiation'            => ['type' => 'number',  'value' => 1.0,   'text' => null],
                'max_renegotiations'             => ['type' => 'number',  'value' => 2.0,   'text' => null],
                'renegotiation_interval_minutes' => ['type' => 'minutes', 'value' => 720.0, 'text' => null],
            ],
        ]);

        $r1 = $this->service->renegotiateContract(1, $contractId, ['penalty_pct' => 5.0]);
        $this->assertTrue($r1['success']);

        // Immediately try again — interval has not elapsed.
        $r2 = $this->service->renegotiateContract(1, $contractId, ['penalty_pct' => 2.0]);
        $this->assertFalse($r2['success']);
        $this->assertSame('renegotiation_too_soon', $r2['status']);
    }

    // ================================================================== bug-fix regressions

    /**
     * Renegocjacja NIE moze nadpisac flag bezpieczenstwa (allow_cancel / cancel_forfeit_deposit),
     * co wczesniej pozwalalo wyjsc z niezrywalnego kontraktu i odzyskac kaucje.
     * Renegotiation must NOT overwrite security flags — previously this let a player escape a
     * non-cancellable contract and recover the deposit.
     */
    public function testRenegotiationCannotOverrideSecurityFlags(): void
    {
        $this->seedPlayer(1, 0.0, 200000.0);
        $contractId = $this->insertContract(1, [
            'security_deposit' => 50000.0,
            'extra_terms'      => [
                'allow_cancel'           => ['type' => 'number', 'value' => 0.0, 'text' => null],
                'cancel_forfeit_deposit' => ['type' => 'number', 'value' => 1.0, 'text' => null],
                'allow_renegotiation'    => ['type' => 'number', 'value' => 1.0, 'text' => null],
                'max_renegotiations'     => ['type' => 'number', 'value' => 2.0, 'text' => null],
            ],
        ]);

        // Attempt to unlock cancellation and disable forfeit via renegotiation.
        $reneg = $this->service->renegotiateContract(1, $contractId, [
            'allow_cancel'           => 1.0,
            'cancel_forfeit_deposit' => 0.0,
        ]);
        // No renegotiable key was supplied → rejected, terms untouched.
        $this->assertFalse($reneg['success']);
        $this->assertSame('renegotiation_no_valid_terms', $reneg['status']);

        $terms = json_decode((string)$this->db->query("SELECT terms_json FROM player_contracts WHERE id = {$contractId}")->fetchColumn(), true);
        $this->assertSame(0.0, (float)$terms['allow_cancel']['value'], 'allow_cancel must stay protected');
        $this->assertSame(1.0, (float)$terms['cancel_forfeit_deposit']['value'], 'forfeit flag must stay protected');

        // Cancellation is still blocked.
        $cancel = $this->service->cancelContract(1, $contractId);
        $this->assertFalse($cancel['success']);
        $this->assertSame('cancel_not_allowed', $cancel['status']);
    }

    /**
     * Renegocjacja dozwolonego terminu obok chronionego: stosuje tylko dozwolony, ignoruje chroniony.
     * Renegotiating an allowed term alongside a protected one applies only the allowed change.
     */
    public function testRenegotiationAppliesAllowedTermAndIgnoresProtectedOne(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);
        $contractId = $this->insertContract(1, [
            'extra_terms' => [
                'allow_renegotiation' => ['type' => 'number',  'value' => 1.0,  'text' => null],
                'max_renegotiations'  => ['type' => 'number',  'value' => 2.0,  'text' => null],
                'penalty_pct'         => ['type' => 'percent', 'value' => 10.0, 'text' => null],
                'allow_cancel'        => ['type' => 'number',  'value' => 0.0,  'text' => null],
            ],
        ]);

        $reneg = $this->service->renegotiateContract(1, $contractId, [
            'penalty_pct'  => 4.0,
            'allow_cancel' => 1.0, // protected — must be ignored
        ]);
        $this->assertTrue($reneg['success'], $reneg['status']);

        $terms = json_decode((string)$this->db->query("SELECT terms_json FROM player_contracts WHERE id = {$contractId}")->fetchColumn(), true);
        $this->assertSame(4.0, (float)$terms['penalty_pct']['value']);
        $this->assertSame(0.0, (float)$terms['allow_cancel']['value'], 'allow_cancel must remain protected');
    }

    /**
     * Ubezpieczenie nie jest przyznawane za darmo, gdy kaucja = 0 (skladka = 0).
     * Insurance is not granted for free when the deposit is 0 (premium = 0).
     */
    public function testInsuranceNotGrantedWhenCostWouldBeZero(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);
        // insurance_available=1 but no deposit → cost basis is 0.
        $contractId = $this->insertContract(1, [
            'security_deposit' => 0.0,
            'extra_terms'      => [
                'insurance_available'            => ['type' => 'number',  'value' => 1.0,  'text' => null],
                'insurance_cost_pct'             => ['type' => 'percent', 'value' => 20.0, 'text' => null],
                'insurance_penalty_coverage_pct' => ['type' => 'percent', 'value' => 50.0, 'text' => null],
            ],
        ]);

        $result = $this->service->enableInsurance(1, $contractId);

        $this->assertFalse($result['success']);
        $this->assertSame('insurance_no_cost_basis', $result['status']);
        $enabled = (int)$this->db->query("SELECT insurance_enabled FROM player_contracts WHERE id = {$contractId}")->fetchColumn();
        $this->assertSame(0, $enabled, 'insurance must NOT be enabled for free');
        $this->assertSame(100000.0, $this->bankOf(1));
    }

    /**
     * Zerwanie kontraktu bez srodkow na kare zwraca czytelny status, a kontrakt zostaje aktywny.
     * Cancelling without funds for the penalty returns a clear status and leaves the contract active.
     */
    public function testCancelWithInsufficientFundsForPenaltyIsRejectedCleanly(): void
    {
        $this->seedPlayer(1, 0.0, 0.0); // no money
        $contractId = $this->insertContract(1, [
            'security_deposit' => 50000.0,
            'extra_terms'      => [
                'allow_cancel'           => ['type' => 'number', 'value' => 1.0,     'text' => null],
                'cancel_penalty_fixed'   => ['type' => 'number', 'value' => 25000.0, 'text' => null],
                'cancel_forfeit_deposit' => ['type' => 'number', 'value' => 0.0,     'text' => null],
            ],
        ]);

        $result = $this->service->cancelContract(1, $contractId);

        $this->assertFalse($result['success']);
        $this->assertSame('insufficient_funds_penalty', $result['status']);
        // Contract stays active — no partial cancellation, no deposit refund.
        $status = $this->db->query("SELECT status FROM player_contracts WHERE id = {$contractId}")->fetchColumn();
        $this->assertSame('active', $status);
        $this->assertSame(0.0, $this->bankOf(1));
    }

    /**
     * enableInsurance / renegotiateContract zwracaja 'not_active' (a nie 'cancel_status')
     * dla kontraktu, ktory nie jest aktywny.
     * enableInsurance / renegotiateContract return 'not_active' for a non-active contract.
     */
    public function testInsuranceAndRenegotiationReturnNotActiveForCancelledContract(): void
    {
        $this->seedPlayer(1, 0.0, 100000.0);
        $contractId = $this->insertContract(1, [
            'extra_terms' => [
                'insurance_available' => ['type' => 'number', 'value' => 1.0, 'text' => null],
                'allow_renegotiation' => ['type' => 'number', 'value' => 1.0, 'text' => null],
                'allow_cancel'        => ['type' => 'number', 'value' => 1.0, 'text' => null],
            ],
        ]);
        $this->service->cancelContract(1, $contractId);

        $ins = $this->service->enableInsurance(1, $contractId);
        $this->assertFalse($ins['success']);
        $this->assertSame('not_active', $ins['status']);

        $reneg = $this->service->renegotiateContract(1, $contractId, ['penalty_pct' => 3.0]);
        $this->assertFalse($reneg['success']);
        $this->assertSame('not_active', $reneg['status']);
    }

    /**
     * onContractCancelled inkrementuje licznik cancelled_contracts nawet gdy strata reputacji = 0
     * i gracz nie ma jeszcze wiersza reputacji.
     * onContractCancelled increments the cancelled_contracts counter even when the reputation loss
     * is 0 and the player has no reputation row yet.
     */
    public function testCancelCounterIncrementsWhenReputationLossIsZero(): void
    {
        $this->seedPlayer(1, 0.0, 0.0);
        $contractId = $this->insertContract(1, [
            'extra_terms' => [
                'allow_cancel'            => ['type' => 'number', 'value' => 1.0, 'text' => null],
                'reputation_loss_on_cancel' => ['type' => 'number', 'value' => 0.0, 'text' => null],
            ],
        ]);

        $result = $this->service->cancelContract(1, $contractId);
        $this->assertTrue($result['success'], $result['status']);

        $stats = $this->service->reputation()->getStats(1);
        $this->assertSame(1, $stats['cancelled_contracts'], 'cancelled counter must not be lost');
    }

    // ================================================================== helpers

    private function seedPlayer(int $id, float $cash, float $bank): void
    {
        $this->db->prepare(
            'INSERT INTO players (id, cash, bank_balance, company_credibility) VALUES (?, ?, ?, 50)'
        )->execute([$id, $cash, $bank]);
    }

    private function seedStorage(int $playerId, float $used, float $capacity = 1000.0): void
    {
        $this->db->prepare(
            "INSERT INTO storage (player_id, used, capacity, updated_at) VALUES (?, ?, ?, '2025-01-01 00:00:00')"
        )->execute([$playerId, $used, $capacity]);
    }

    /**
     * @param array<string,mixed> $opts
     *   Keys: total_bbl, delivered_bbl, missed_bbl, next_delivery_at, ends_at,
     *         security_deposit, insurance_enabled, insurance_coverage_pct, extra_terms
     */
    private function insertContract(int $playerId, array $opts): int
    {
        $totalBbl     = (float)($opts['total_bbl'] ?? 500.0);
        $missedBbl    = (float)($opts['missed_bbl'] ?? 0.0);
        $deposit      = (float)($opts['security_deposit'] ?? 0.0);
        $insEnabled   = (int)($opts['insurance_enabled'] ?? 0);
        $insCovPct    = (float)($opts['insurance_coverage_pct'] ?? 0.0);

        /** @var array<string,array{type:string,value:float,text:?string}> $extraTerms */
        $extraTerms = (array)($opts['extra_terms'] ?? []);
        $terms = array_merge([
            'total_bbl'                 => ['type' => 'number',  'value' => $totalBbl, 'text' => null],
            'delivery_bbl'              => ['type' => 'number',  'value' => 50.0,      'text' => null],
            'delivery_interval_minutes' => ['type' => 'minutes', 'value' => 60.0,      'text' => null],
            'duration_minutes'          => ['type' => 'minutes', 'value' => 43200.0,   'text' => null],
            'penalty_pct'               => ['type' => 'percent', 'value' => 0.0,       'text' => null],
        ], $extraTerms);

        $this->db->prepare(
            "INSERT INTO player_contracts
                (player_id, contract_option_id, target_type, target_id, context, buyer_name, contract_name,
                 status, total_bbl, delivered_bbl, missed_bbl, next_delivery_at, starts_at, ends_at,
                 terms_json, security_deposit, insurance_enabled, insurance_coverage_pct,
                 created_at, updated_at)
             VALUES (?, 1, 'storage', NULL, 'storage_oil_delivery', 'Test Buyer', 'Test Contract',
                     'active', ?, ?, ?, ?, '2025-01-01 00:00:00', ?, ?, ?, ?, ?,
                     '2025-01-01 00:00:00', '2025-01-01 00:00:00')"
        )->execute([
            $playerId,
            $totalBbl,
            (float)($opts['delivered_bbl'] ?? 0.0),
            $missedBbl,
            (string)($opts['next_delivery_at'] ?? '2099-12-31 00:00:00'),
            (string)($opts['ends_at'] ?? '2099-12-31 00:00:00'),
            json_encode($terms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $deposit,
            $insEnabled,
            $insCovPct,
        ]);

        return (int)$this->db->lastInsertId();
    }

    private function optionId(string $code): int
    {
        $stmt = $this->db->prepare('SELECT id FROM contract_options WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        return (int)$stmt->fetchColumn();
    }

    private function bankOf(int $id): float
    {
        return (float)$this->db->query("SELECT bank_balance FROM players WHERE id = {$id}")->fetchColumn();
    }

    private function createSchema(): void
    {
        $this->db->exec(
            'CREATE TABLE players (
                id INTEGER PRIMARY KEY,
                cash REAL NOT NULL DEFAULT 0,
                bank_balance REAL NOT NULL DEFAULT 0,
                company_credibility INTEGER NOT NULL DEFAULT 50
            )'
        );
        $this->db->exec(
            'CREATE TABLE well_config (
                "key" TEXT PRIMARY KEY,
                "value" TEXT NOT NULL,
                label TEXT NULL,
                category TEXT NULL
            )'
        );
        $this->db->exec(
            'CREATE TABLE board_roles (
                id INTEGER PRIMARY KEY,
                code TEXT NOT NULL
            )'
        );
        $this->db->exec(
            'CREATE TABLE board_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                skill_organization INTEGER NOT NULL DEFAULT 0,
                skill_analysis INTEGER NOT NULL DEFAULT 0,
                skill_ethics INTEGER NOT NULL DEFAULT 0
            )'
        );
        $this->db->exec(
            'CREATE TABLE storage (
                player_id INTEGER PRIMARY KEY,
                used REAL NOT NULL DEFAULT 0,
                capacity REAL NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL
            )'
        );
    }
}
