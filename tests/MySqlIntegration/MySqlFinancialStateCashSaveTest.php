<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/FinancialStateSection.php';

/**
 * Testy MySQL dla FinancialStateSection::saveCashAndTick (naprawa C3).
 * MySQL tests for FinancialStateSection::saveCashAndTick (C3 fix).
 *
 * Testy weryfikuja atomowy zapis gotowki na prawdziwej bazie MySQL,
 * w tym odpornosc na wspolbiezne operacje (scenariusz C3).
 *
 * Tests verify atomic cash write on a real MySQL database,
 * including resilience to concurrent operations (C3 scenario).
 */
final class MySqlFinancialStateCashSaveTest extends MySqlIntegrationTestCase
{
    // -------------------------------------------------------------------------
    // Podstawowe przypadki / Basic cases
    // -------------------------------------------------------------------------

    /**
     * totalCosts < saldo: poprawne odliczenie na MySQL.
     * totalCosts < balance: correct deduction on MySQL.
     */
    public function testSaveCashDeductsFromCurrentBalance(): void
    {
        $playerId = $this->seedPlayer(); // domyslne cash = 50_000_000

        $section = new FinancialStateSection($this->db, new DateTime('2026-06-24 10:00:00'));
        $section->saveCashAndTick($playerId, 100_000.0);

        $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
        $stmt->execute([$playerId]);
        $this->assertEqualsWithDelta(50_000_000.0 - 100_000.0, (float)$stmt->fetchColumn(), 0.01,
            'Gotowka musi spasc o totalCosts / Cash must decrease by totalCosts');
    }

    /**
     * totalCosts > saldo: GREATEST(0,...) na MySQL.
     * totalCosts > balance: GREATEST(0,...) on MySQL.
     */
    public function testSaveCashFloorsAtZeroWhenCostsExceedBalance(): void
    {
        $playerId = $this->seedPlayer();
        $this->db->prepare("UPDATE players SET cash = 500.0 WHERE id = ?")->execute([$playerId]);

        $section = new FinancialStateSection($this->db, new DateTime());
        $section->saveCashAndTick($playerId, 999_999.0);

        $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
        $stmt->execute([$playerId]);
        $this->assertEqualsWithDelta(0.0, (float)$stmt->fetchColumn(), 0.001,
            'Gotowka musi byc 0 gdy koszty > saldo / Cash must be 0 when costs exceed balance');
    }

    /**
     * Zero kosztow: saldo bez zmian.
     * Zero costs: balance unchanged.
     */
    public function testZeroTotalCostsLeavesBalanceUnchanged(): void
    {
        $playerId = $this->seedPlayer();
        $this->db->prepare("UPDATE players SET cash = 123456.78 WHERE id = ?")->execute([$playerId]);

        $section = new FinancialStateSection($this->db, new DateTime());
        $section->saveCashAndTick($playerId, 0.0);

        $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
        $stmt->execute([$playerId]);
        $this->assertEqualsWithDelta(123456.78, (float)$stmt->fetchColumn(), 0.01);
    }

    // -------------------------------------------------------------------------
    // C3: scenariusz wspolbieznosci na MySQL / C3: concurrent-access scenario on MySQL
    // -------------------------------------------------------------------------

    /**
     * C3 fix: symulacja rownoleglosci — gracz placi pelne totalCosts nawet gdy DB cash wzroslo.
     *
     * Scenariusz (zminimalizowany):
     *   1. Gracz zaczyna tick z cash_db = 1000
     *   2. Tick oblicza totalCosts = 1500 (koszty > initialCash -> in-memory playerCash = 0)
     *   3. W trakcie ticka wspolbiezna operacja (kredyt) zwieksza cash_db o 800 -> cash_db = 1800
     *   4. saveCashAndTick(totalCosts=1500) wykonuje:
     *        GREATEST(0, 1800 - 1500) = 300
     *
     * Stary kod (delta = 0 - 1000 = -1000):
     *        GREATEST(0, 1800 + (-1000)) = 800  ← gracz "zyska" 500 za darmo
     *
     * C3 fix: simulated concurrency — player pays full totalCosts even when DB cash grew.
     *
     * Scenario (minimized):
     *   1. Player starts tick with cash_db = 1000
     *   2. Tick computes totalCosts = 1500 (costs > initialCash -> in-memory playerCash = 0)
     *   3. During tick a concurrent op (loan) increases cash_db by 800 -> cash_db = 1800
     *   4. saveCashAndTick(totalCosts=1500) executes:
     *        GREATEST(0, 1800 - 1500) = 300
     *
     * Old code (delta = 0 - 1000 = -1000):
     *        GREATEST(0, 1800 + (-1000)) = 800  <- player "gains" 500 for free
     */
    public function testC3ConcurrentCashIncreaseFullyCoveredByCosts(): void
    {
        $playerId = $this->seedPlayer();
        $this->db->prepare("UPDATE players SET cash = 1000.0 WHERE id = ?")->execute([$playerId]);

        // Symuluj wspolbiezna operacje (kredyt +800) zanim tick zapisze gotowke.
        // Simulate concurrent operation (loan +800) before tick saves cash.
        $this->db->prepare("UPDATE players SET cash = cash + 800.0 WHERE id = ?")->execute([$playerId]);
        // cash_db = 1800 teraz / now

        // Tick skonczyl sie z totalCosts=1500 (wiecej niz initialCash=1000)
        // Tick ended with totalCosts=1500 (more than initialCash=1000)
        $section = new FinancialStateSection($this->db, new DateTime());
        $section->saveCashAndTick($playerId, 1500.0);

        $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
        $stmt->execute([$playerId]);
        $cash = (float)$stmt->fetchColumn();

        // Oczekiwane: GREATEST(0, 1800 - 1500) = 300
        // Stary kod dal by: GREATEST(0, 1800 + (0-1000)) = 800 (blednie — gracz zyska 500 za darmo)
        $this->assertEqualsWithDelta(300.0, $cash, 0.01,
            'C3: gracz zachowuje 1800-1500=300, a nie 800 jak w starym kodzie (GREATEST+delta)');

        // Potwierdz ze wynik NIE jest 800 (regresja starego kodu)
        // Confirm the result is NOT 800 (regression of old code)
        $this->assertLessThan(800.0, $cash,
            'C3 regresja: cash nie moze byc 800 (stary kod z delta=-1000)');
    }

    /**
     * C3 fix: totalCosts < initialCash — wspolbiezny przyrost jest zachowany.
     * C3 fix: totalCosts < initialCash — concurrent increase is preserved.
     *
     * Gdy koszty sa mniejsze od poczatkowej gotowki, gracz normalnie korzysta
     * z wspolbieznego przyrostu (np. zysk ze sprzedazy ropy w trakcie ticka).
     * When costs < initialCash, the player normally benefits from concurrent increase
     * (e.g. oil sale profit during the tick).
     */
    public function testNonC3ConcurrentIncreaseIsPreserved(): void
    {
        $playerId = $this->seedPlayer();
        // initialCash = 5000, koszty = 1000 (totalCosts < initialCash)
        $this->db->prepare("UPDATE players SET cash = 5000.0 WHERE id = ?")->execute([$playerId]);

        // Wspolbiezna sprzedaz ropy: +2000
        $this->db->prepare("UPDATE players SET cash = cash + 2000.0 WHERE id = ?")->execute([$playerId]);
        // cash_db = 7000

        $section = new FinancialStateSection($this->db, new DateTime());
        $section->saveCashAndTick($playerId, 1000.0); // totalCosts < initialCash(5000)

        $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
        $stmt->execute([$playerId]);
        $cash = (float)$stmt->fetchColumn();

        // GREATEST(0, 7000 - 1000) = 6000
        $this->assertEqualsWithDelta(6000.0, $cash, 0.01,
            'Gdy totalCosts < initialCash: wspolbiezny przyrost jest zachowany (7000-1000=6000)');
    }

    // -------------------------------------------------------------------------
    // Izolacja i metadane / Isolation and metadata
    // -------------------------------------------------------------------------

    /**
     * last_tick_at jest aktualizowany do czasu przekazanego do konstruktora.
     * last_tick_at is updated to the time passed to the constructor.
     */
    public function testLastTickAtIsUpdatedToConstructorTime(): void
    {
        $playerId = $this->seedPlayer();
        $now      = new DateTime('2030-01-15 08:30:00');
        $section  = new FinancialStateSection($this->db, $now);
        $section->saveCashAndTick($playerId, 0.0);

        $stmt = $this->db->prepare("SELECT last_tick_at FROM players WHERE id = ?");
        $stmt->execute([$playerId]);
        $this->assertSame('2030-01-15 08:30:00', (string)$stmt->fetchColumn(),
            'last_tick_at musi byc ustawiony na czas z konstruktora');
    }

    /**
     * Zapis dotyczy tylko gracza o podanym ID — drugi gracz jest nienaruszony.
     * Write only affects the specified player — the second player is untouched.
     */
    public function testSaveCashOnlyUpdatesSpecifiedPlayer(): void
    {
        $player1 = $this->seedPlayer();
        // Drugi gracz — tymczasowy wiersz z pomocniczym seed ID
        $player2 = $this->seed + 50;
        $u2 = 'phpunit_c3_p2_' . $player2;
        $this->db->prepare(
            "INSERT INTO players (id, username, email, password_hash, cash, status, created_at, last_tick_at)
             VALUES (?, ?, ?, ?, 77777.0, 'active', NOW(), NOW())"
        )->execute([$player2, $u2, $u2 . '@test.x', password_hash('x', PASSWORD_BCRYPT)]);

        $section = new FinancialStateSection($this->db, new DateTime());
        $section->saveCashAndTick($player1, 1000.0);

        $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
        $stmt->execute([$player2]);
        $this->assertEqualsWithDelta(77777.0, (float)$stmt->fetchColumn(), 0.01,
            'Gotowka gracza 2 nie moze sie zmienic / Player 2 cash must not change');

        // Czyszczenie pomocniczego gracza / Clean up auxiliary player
        $this->db->prepare("DELETE FROM players WHERE id = ?")->execute([$player2]);
    }
}
