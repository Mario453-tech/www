<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/BankAccountService.php';

/**
 * Brief § "Nowe pole w tabeli graczy" + § "Migracja istniejacych graczy":
 * generator numerow rachunkow oraz backfill.
 *
 * Brief § "New player field" + § "Migration of existing players":
 * account number generator and backfill.
 *
 * Sprawdzamy: format (OC-NNNNNN-YYYY), unikalnosc, idempotentnosc ensureAccount,
 * lookup po numerze, backfill, race-safe UNIQUE.
 *
 * Verifies: format (OC-NNNNNN-YYYY), uniqueness, ensureAccount idempotency,
 * lookup by number, backfill, race-safe UNIQUE constraint.
 */
final class BankAccountServiceTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private BankAccountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->service = new BankAccountService($this->db);
    }

    // ------------------------------------------------------ Generator: format

    public function testFirstAccountFollowsFormatOcSixDigitsYear(): void
    {
        $this->seedPlayer(1);
        $number = $this->service->ensureAccount(1);

        $this->assertNotNull($number, 'Powinien zostac przypisany numer rachunku.');
        $this->assertMatchesRegularExpression(
            '/^OC-\d{6}-\d{4}$/',
            (string)$number,
            'Format powinien byc OC-{6 cyfr}-{rok}.'
        );
    }

    public function testFirstAccountStartsAtSequenceOne(): void
    {
        $this->seedPlayer(1);
        $number = $this->service->ensureAccount(1);
        $year = date('Y');
        $this->assertSame("OC-000001-{$year}", $number, 'Pierwszy gracz dostaje numer 000001.');
    }

    public function testSequenceIncrementsPerPlayer(): void
    {
        $year = date('Y');
        $this->seedPlayer(1);
        $this->seedPlayer(2);
        $this->seedPlayer(3);

        $this->assertSame("OC-000001-{$year}", $this->service->ensureAccount(1));
        $this->assertSame("OC-000002-{$year}", $this->service->ensureAccount(2));
        $this->assertSame("OC-000003-{$year}", $this->service->ensureAccount(3));
    }

    // -------------------------------------------------- Idempotencja / immutability

    public function testEnsureAccountIsIdempotent(): void
    {
        $this->seedPlayer(1);
        $first  = $this->service->ensureAccount(1);
        $second = $this->service->ensureAccount(1);
        $third  = $this->service->ensureAccount(1);

        $this->assertNotNull($first);
        $this->assertSame($first, $second, 'Drugie wywolanie zwraca ten sam numer.');
        $this->assertSame($first, $third,  'Numer jest niezmienny (immutable).');
    }

    public function testEnsureAccountReturnsExistingNumber(): void
    {
        // Numer juz istnieje w DB (np. import danych) — service nie nadpisuje.
        // Number already exists in DB (e.g. data import) — service does not overwrite.
        $this->db->prepare("INSERT INTO players (id, bank_account_number) VALUES (1, ?)")
                 ->execute(['OC-009999-2020']);

        $this->assertSame('OC-009999-2020', $this->service->ensureAccount(1));
        $this->assertSame('OC-009999-2020', $this->service->getAccount(1));
    }

    public function testEnsureAccountSkipsEmptyStringAsMissing(): void
    {
        // Pusty string traktujemy jak brak numeru (zgodnie z getAccount).
        // Empty string treated as missing (matches getAccount behaviour).
        $this->db->prepare("INSERT INTO players (id, bank_account_number) VALUES (1, '')")->execute();
        $year = date('Y');
        $this->assertSame("OC-000001-{$year}", $this->service->ensureAccount(1));
    }

    // ---------------------------------------------------------- Reader

    public function testGetAccountReturnsNullForMissingPlayer(): void
    {
        $this->assertNull($this->service->getAccount(999));
    }

    public function testGetAccountReturnsNullWhenNumberIsNull(): void
    {
        $this->seedPlayer(1);
        $this->assertNull($this->service->getAccount(1));
    }

    public function testFindPlayerIdByAccount(): void
    {
        $this->seedPlayer(42);
        $number = $this->service->ensureAccount(42);

        $this->assertSame(42, $this->service->findPlayerIdByAccount((string)$number));
        $this->assertSame(42, $this->service->findPlayerIdByAccount('  ' . $number . ' '), 'Trim spacji.');
        $this->assertNull($this->service->findPlayerIdByAccount(''));
        $this->assertNull($this->service->findPlayerIdByAccount('OC-NIEISTNIEJE-2026'));
    }

    // ---------------------------------------------------------- Unikalnosc

    public function testNumbersAreUniqueAcrossManyPlayers(): void
    {
        $numbers = [];
        for ($i = 1; $i <= 25; $i++) {
            $this->seedPlayer($i);
            $numbers[] = $this->service->ensureAccount($i);
        }
        $this->assertCount(25, $numbers);
        $this->assertSame($numbers, array_unique($numbers), 'Wszystkie numery sa unikalne.');
    }

    public function testUniqueConstraintEnforcedByDb(): void
    {
        $this->seedPlayer(1);
        $number = $this->service->ensureAccount(1);

        $this->expectException(PDOException::class);
        $this->db->prepare("INSERT INTO players (id, bank_account_number) VALUES (2, ?)")
                 ->execute([$number]);
    }

    // ---------------------------------------------------------- Backfill

    public function testMigrateExistingBackfillsAllNulls(): void
    {
        $this->seedPlayer(1);
        $this->seedPlayer(2);
        $this->seedPlayer(3);

        $stats = $this->service->migrateExisting();

        $this->assertSame(3, $stats['generated']);
        $this->assertSame(0, $stats['skipped']);
        $this->assertSame(0, $stats['errors']);
        $this->assertSame(3, $stats['total']);
        $this->assertNotNull($this->service->getAccount(1));
        $this->assertNotNull($this->service->getAccount(2));
        $this->assertNotNull($this->service->getAccount(3));
    }

    public function testMigrateExistingSkipsAlreadyAssigned(): void
    {
        $this->db->prepare("INSERT INTO players (id, bank_account_number) VALUES (1, ?)")
                 ->execute(['OC-000050-2025']);
        $this->seedPlayer(2);

        $stats = $this->service->migrateExisting();

        $this->assertSame(1, $stats['generated']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertSame(0, $stats['errors']);
        $this->assertSame('OC-000050-2025', $this->service->getAccount(1), 'Nie nadpisuje istniejacych.');
    }

    public function testMigrateIsIdempotent(): void
    {
        $this->seedPlayer(1);
        $this->seedPlayer(2);

        $first  = $this->service->migrateExisting();
        $second = $this->service->migrateExisting();

        $this->assertSame(2, $first['generated']);
        $this->assertSame(0, $second['generated']);
        $this->assertSame(2, $second['skipped']);
    }

    // ============================================================== Helpers

    private function seedPlayer(int $id): void
    {
        $this->db->prepare("INSERT INTO players (id, bank_account_number) VALUES (?, NULL)")
                 ->execute([$id]);
    }

    private function createSchema(): void
    {
        // Schema minimalny dla testow — w produkcji pole dodaje ensureSchema() na MySQL.
        // Minimal schema for tests — production adds the column via ensureSchema() on MySQL.
        $this->db->exec(
            'CREATE TABLE players (
                id INTEGER PRIMARY KEY,
                bank_account_number TEXT NULL UNIQUE
            )'
        );
    }
}
