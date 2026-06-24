<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';

/**
 * Testy jednostkowe (SQLite) dla FinancialStateSection::saveCashAndTick (naprawa C3).
 * Unit tests (SQLite) for FinancialStateSection::saveCashAndTick (C3 fix).
 *
 * C3: stara implementacja uzywala delty roznicowej (finalCash - initialCash), ktora byla
 * przycinana do 0 gdy koszty > poczatkowa gotowka. Przy rownoczesnym przyroscie gotowki
 * (kredyt, sprzedaz ropy) gracz uchodzil bez pelnej oplaty. Nowa implementacja uzywa
 * totalCosts (suma WSZYSTKICH zamierzonych odliczen) i wykonuje:
 *   cash = GREATEST(0, cash - totalCosts)
 *
 * C3: old implementation used a differential delta (finalCash - initialCash), clipped to 0
 * when costs exceeded initial cash. On concurrent cash increase (loan, oil sale) the player
 * could escape full liability. New implementation uses totalCosts (sum of ALL intended
 * deductions) and executes: cash = GREATEST(0, cash - totalCosts)
 */
final class FinancialStateSectionTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private FinancialStateSection $section;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->db->exec(
            "CREATE TABLE players (
                id           INTEGER PRIMARY KEY,
                cash         REAL    NOT NULL DEFAULT 0.0,
                last_tick_at TEXT    NULL
            )"
        );
        $this->section = new FinancialStateSection($this->db, new DateTime('2026-06-24 10:00:00'));
    }

    private function seedCash(int $id, float $cash): void
    {
        $this->db->prepare("INSERT INTO players (id, cash) VALUES (?, ?)")->execute([$id, $cash]);
    }

    private function readCash(int $id): float
    {
        $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
        $stmt->execute([$id]);
        return (float)$stmt->fetchColumn();
    }

    private function readLastTickAt(int $id): ?string
    {
        $stmt = $this->db->prepare("SELECT last_tick_at FROM players WHERE id = ?");
        $stmt->execute([$id]);
        $val = $stmt->fetchColumn();
        return $val === false ? null : (string)$val;
    }

    // -------------------------------------------------------------------------
    // Podstawowe przypadki / Basic cases
    // -------------------------------------------------------------------------

    /**
     * Zero kosztow: saldo bez zmian.
     * Zero costs: balance unchanged.
     */
    public function testZeroTotalCostsLeavesBalanceUnchanged(): void
    {
        $this->seedCash(1, 50000.0);
        $this->section->saveCashAndTick(1, 0.0);
        $this->assertEqualsWithDelta(50000.0, $this->readCash(1), 0.001);
    }

    /**
     * Koszty < saldo: normalne odliczenie.
     * Costs < balance: normal deduction.
     */
    public function testPartialDeductionReducesCashByTotalCosts(): void
    {
        $this->seedCash(1, 10000.0);
        $this->section->saveCashAndTick(1, 3000.0);
        $this->assertEqualsWithDelta(7000.0, $this->readCash(1), 0.001);
    }

    /**
     * Koszty > saldo: podloga na 0 (GREATEST(0,...)).
     * Costs > balance: floor at 0 (GREATEST(0,...)).
     */
    public function testTotalCostsExceedingBalanceFloorsAtZero(): void
    {
        $this->seedCash(1, 5000.0);
        $this->section->saveCashAndTick(1, 8000.0);
        $this->assertEqualsWithDelta(0.0, $this->readCash(1), 0.001);
    }

    /**
     * Koszty == saldo: wyzerowanie.
     * Costs == balance: zeroed out.
     */
    public function testTotalCostsEqualToBalanceYieldsZero(): void
    {
        $this->seedCash(1, 1000.0);
        $this->section->saveCashAndTick(1, 1000.0);
        $this->assertEqualsWithDelta(0.0, $this->readCash(1), 0.001);
    }

    // -------------------------------------------------------------------------
    // C3: scenariusz rownoleglosci / C3: concurrent-access scenario
    // -------------------------------------------------------------------------

    /**
     * C3 fix: koszty > initialCash, wspolbiezny przyrost gotowki NIE daje wolnych srodkow.
     *
     * Scenariusz:
     *   - gracz zaczal tick z initialCash = 100
     *   - laczne koszty ticka = 150 (totalCosts), wiec in-memory playerCash = 0
     *   - w trakcie ticka kredyt doplacil +100 → DB cash = 200
     *
     * Stary kod (delta = finalCash - initialCash = 0 - 100 = -100):
     *   GREATEST(0, 200 + (-100)) = 100  ← gracz "dostaje" 100 za darmo
     *
     * Nowy kod (cash = GREATEST(0, cash - totalCosts)):
     *   GREATEST(0, 200 - 150) = 50  ← gracz placi pelne koszty, zachowuje nadwyzke
     *
     * C3 fix: costs > initialCash, concurrent cash increase does NOT grant free balance.
     *
     * Scenario:
     *   - player started tick with initialCash = 100
     *   - total tick costs = 150 (totalCosts), so in-memory playerCash = 0
     *   - during tick a loan credited +100 → DB cash = 200
     *
     * Old code (delta = finalCash - initialCash = 0 - 100 = -100):
     *   GREATEST(0, 200 + (-100)) = 100  <- player "gets" 100 for free
     *
     * New code (cash = GREATEST(0, cash - totalCosts)):
     *   GREATEST(0, 200 - 150) = 50  <- player pays full costs, keeps the surplus
     */
    public function testC3ConcurrentIncreaseFullyCoveredByCosts(): void
    {
        // DB cash JUZPO wspolbieznym przyroscie (100 poczatkowe + 100 kredyt)
        // DB cash ALREADY after concurrent increase (100 initial + 100 loan)
        $this->seedCash(1, 200.0);

        // totalCosts = 150 (wiecej niz poczatkowe 100, ale mniej niz DB 200)
        // totalCosts = 150 (more than initial 100, less than DB 200)
        $this->section->saveCashAndTick(1, 150.0);

        // Oczekiwane: GREATEST(0, 200 - 150) = 50
        // Expected:   GREATEST(0, 200 - 150) = 50
        $this->assertEqualsWithDelta(50.0, $this->readCash(1), 0.001,
            'C3: gracz placi pelne totalCosts=150, zachowuje 200-150=50 (nie 100 jak w starym kodzie)');
    }

    /**
     * C3 fix: koszty < initialCash — rownolegy przyrost jest zachowany w calosci.
     * C3 fix: costs < initialCash — concurrent increase is fully preserved.
     *
     * Gdy totalCosts < initialCash, stary i nowy kod daja ten sam wynik.
     * Gracz zarabia na wspolbieznym przyroscie (nie jest on objety karami ticka).
     * When totalCosts < initialCash, old and new code give the same result.
     * The player benefits from concurrent increase (it is not subject to tick penalties).
     */
    public function testNonC3ConcurrentIncreasePreserved(): void
    {
        // initialCash = 1000, przyrost +500, DB cash = 1500, totalCosts = 300
        // initialCash = 1000, increase +500, DB cash = 1500, totalCosts = 300
        $this->seedCash(1, 1500.0);
        $this->section->saveCashAndTick(1, 300.0);
        // GREATEST(0, 1500 - 300) = 1200
        $this->assertEqualsWithDelta(1200.0, $this->readCash(1), 0.001,
            'Gdy koszty < initialCash, wspolbiezny przyrost jest zachowany (1500-300=1200)');
    }

    /**
     * C3 fix: totalCosts >> DB cash (wielokrotnie wiecej) — zawsze podloga na 0.
     * C3 fix: totalCosts >> DB cash (many times more) — always floors at 0.
     */
    public function testC3TotalCostsFarExceedsDbCashStaysAtZero(): void
    {
        $this->seedCash(1, 100.0);
        $this->section->saveCashAndTick(1, 99999.0);
        $this->assertEqualsWithDelta(0.0, $this->readCash(1), 0.001);
    }

    // -------------------------------------------------------------------------
    // Izolacja i metadane / Isolation and metadata
    // -------------------------------------------------------------------------

    /**
     * Tylko wiersz gracza o podanym ID jest modyfikowany.
     * Only the row of the specified player is modified.
     */
    public function testSaveCashOnlyUpdatesSpecifiedPlayer(): void
    {
        $this->seedCash(1, 10000.0);
        $this->seedCash(2, 99999.0);
        $this->section->saveCashAndTick(1, 5000.0);
        $this->assertEqualsWithDelta(99999.0, $this->readCash(2), 0.001,
            'Gotowka gracza 2 nie moze sie zmienic / Player 2 cash must not change');
    }

    /**
     * last_tick_at jest ustawiany na czas przekazany do konstruktora.
     * last_tick_at is set to the time passed to the constructor.
     */
    public function testLastTickAtIsWritten(): void
    {
        $this->seedCash(1, 1000.0);
        $this->section->saveCashAndTick(1, 0.0);
        $this->assertSame('2026-06-24 10:00:00', $this->readLastTickAt(1));
    }
}
