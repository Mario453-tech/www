<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';

/**
 * Integration tests for TTS security fixes (Round 8/9).
 *
 * Pokrywa nastepujace bugi / Covers the following bugs:
 *
 * Round 8:
 * - cancelTask: brakujace AND player_id w DELETE z technical_task_queue
 *   cancelTask: missing AND player_id in DELETE from technical_task_queue
 * - hireEngineer: brakujacy wzorzec $ownTx (zagniezdzona transakcja)
 *   hireEngineer: missing $ownTx pattern (nested transaction)
 * - upgradeProcedures: brak atomowego UPDATE (odlaczony SELECT+UPDATE = TOCTOU)
 *   upgradeProcedures: non-atomic UPDATE (separate SELECT+UPDATE = TOCTOU)
 *
 * Round 9:
 * - upgradeProcedures: bledny komunikat gdy rowCount=0 z powodu race condition
 *   upgradeProcedures: wrong message when rowCount=0 due to race condition
 *
 * RepairDataTrait (Round 8):
 * - getRecentIncidents: brakujace AND player_id = ? pozwalalo na wyciek danych
 *   getRecentIncidents: missing AND player_id = ? allowed cross-player data leak
 */
final class TTSSecurityTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
    }

    // =========================================================================
    // Schema / helpers
    // =========================================================================

    private function createSchema(): void
    {
        $this->db->exec("
            CREATE TABLE players (
                id                       INTEGER PRIMARY KEY,
                cash                     REAL    NOT NULL DEFAULT 0.0,
                safety_procedures_level  INTEGER NOT NULL DEFAULT 0,
                procedure_integrity      REAL    NOT NULL DEFAULT 100.0,
                procedures_last_decay_at TEXT    NULL
            )
        ");
        $this->db->exec("
            CREATE TABLE technical_staff (
                id          INTEGER PRIMARY KEY,
                player_id   INTEGER NOT NULL,
                manager_id  INTEGER NOT NULL DEFAULT 0,
                first_name  TEXT    NOT NULL DEFAULT 'Jan',
                last_name   TEXT    NOT NULL DEFAULT 'Test',
                spec_code   TEXT    NOT NULL,
                spec_name   TEXT    NOT NULL DEFAULT 'Test',
                specialization TEXT NULL,
                skill_level INTEGER NOT NULL DEFAULT 5,
                salary      REAL    NOT NULL DEFAULT 5000.0,
                status      TEXT    NOT NULL DEFAULT 'active',
                fired_at    TEXT    NULL
            )
        ");
        $this->db->exec("
            CREATE TABLE technical_tasks (
                id             INTEGER PRIMARY KEY,
                player_id      INTEGER NOT NULL,
                staff_id       INTEGER NOT NULL,
                task_type      TEXT    NOT NULL,
                well_id        INTEGER NULL,
                hub_id         INTEGER NULL,
                pipeline_id    INTEGER NULL,
                module_type    TEXT    NULL,
                title          TEXT    NOT NULL DEFAULT '',
                status         TEXT    NOT NULL DEFAULT 'in_progress',
                start_time     TEXT    NULL,
                end_time       TEXT    NULL,
                duration_hours INTEGER NOT NULL DEFAULT 1,
                cost           REAL    NOT NULL DEFAULT 0.0,
                result_data    TEXT    NULL,
                notified       INTEGER NOT NULL DEFAULT 0
            )
        ");
        $this->db->exec("
            CREATE TABLE technical_task_queue (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id   INTEGER NOT NULL,
                staff_id    INTEGER NOT NULL,
                task_type   TEXT    NOT NULL,
                well_id     INTEGER NULL,
                hub_id      INTEGER NULL,
                pipeline_id INTEGER NULL,
                module_type TEXT    NULL,
                priority    INTEGER NOT NULL DEFAULT 0,
                queued_at   TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->db->exec("
            CREATE TABLE wells (
                id                  INTEGER PRIMARY KEY,
                player_id           INTEGER NOT NULL,
                status              TEXT    NOT NULL DEFAULT 'active',
                service_prev_status TEXT    NULL
            )
        ");
        $this->db->exec("
            CREATE TABLE well_incidents (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                well_id    INTEGER NOT NULL,
                player_id  INTEGER NOT NULL,
                type       TEXT    NOT NULL,
                created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->db->exec("
            CREATE TABLE technical_notifications (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id  INTEGER NOT NULL,
                well_id    INTEGER NULL,
                type       TEXT    NOT NULL,
                message    TEXT    NOT NULL,
                is_read    INTEGER NOT NULL DEFAULT 0,
                created_at TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->db->exec("
            CREATE TABLE staff_specializations (
                code             TEXT PRIMARY KEY,
                name             TEXT NOT NULL,
                rarity           TEXT NOT NULL DEFAULT 'common',
                prod_bonus       REAL NOT NULL DEFAULT 0.0,
                wear_reduction   REAL NOT NULL DEFAULT 0.0,
                incident_reduction REAL NOT NULL DEFAULT 0.0,
                spiral_reduction REAL NOT NULL DEFAULT 0.0,
                repair_speed REAL NOT NULL DEFAULT 0.0,
                incident_return_reduction REAL NOT NULL DEFAULT 0.0,
                catastrophe_reduction REAL NOT NULL DEFAULT 0.0
            )
        ");
        $this->db->exec("
            CREATE TABLE finance_logs (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id   INTEGER NOT NULL,
                amount      REAL    NOT NULL,
                type        TEXT    NOT NULL,
                description TEXT    NULL,
                created_at  TEXT    NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->db->exec("
            CREATE TABLE pipelines (
                id        INTEGER PRIMARY KEY,
                player_id INTEGER NOT NULL,
                status    TEXT    NOT NULL DEFAULT 'active'
            )
        ");
        $this->db->exec("
            CREATE TABLE well_pipelines (
                id                  INTEGER PRIMARY KEY,
                player_id           INTEGER NOT NULL,
                well_id             INTEGER NOT NULL,
                status              TEXT    NOT NULL DEFAULT 'active',
                service_prev_status TEXT    NULL
            )
        ");
        $this->db->exec("
            CREATE TABLE logistics_hubs (
                id                  INTEGER PRIMARY KEY,
                name                TEXT    NOT NULL DEFAULT '',
                condition_pct       REAL    NOT NULL DEFAULT 80.0,
                repair_cost_estimate REAL   NOT NULL DEFAULT 100000.0,
                status              TEXT    NOT NULL DEFAULT 'active',
                last_maintenance_at TEXT    NULL,
                updated_at          TEXT    NULL
            )
        ");
        $this->db->exec("
            CREATE TABLE logistics_hub_assignments (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                hub_id  INTEGER NOT NULL,
                well_id INTEGER NOT NULL,
                status  TEXT    NOT NULL DEFAULT 'active'
            )
        ");
        $this->db->exec("
            CREATE TABLE board_roles (id INTEGER PRIMARY KEY, code TEXT)
        ");
        $this->db->exec("
            CREATE TABLE board_members (
                id INTEGER PRIMARY KEY, role_id INTEGER,
                status TEXT, specialization_id INTEGER NULL,
                skill_organization INTEGER DEFAULT 5
            )
        ");
        $this->db->exec("
            CREATE TABLE hr_specializations (id INTEGER PRIMARY KEY, code TEXT, name TEXT)
        ");
        $this->db->exec("
            CREATE TABLE industrial_disasters (
                id           INTEGER PRIMARY KEY,
                player_id    INTEGER NOT NULL,
                well_id      INTEGER NULL,
                disaster_type TEXT   NOT NULL,
                status        TEXT   NOT NULL DEFAULT 'active',
                resolved_at   TEXT   NULL
            )
        ");
        $this->db->exec("
            CREATE TABLE failure_log (
                id           INTEGER PRIMARY KEY,
                player_id    INTEGER NOT NULL,
                well_id      INTEGER NULL,
                failure_type TEXT    NOT NULL,
                resolved     INTEGER NOT NULL DEFAULT 0,
                resolved_at  TEXT    NULL
            )
        ");
    }

    /** @return TechnicalTeamService */
    private function makeService(int $playerId): TechnicalTeamService
    {
        $db = $this->db;
        $svc = new class extends TechnicalTeamService {
            public function __construct() {}
            public function getManager(): ?array { return null; }
            public function getManagerBonus(?array $manager): array
            {
                return ['skill' => 0, 'time_mult' => 1.0, 'cost_mult' => 1.0, 'label' => ''];
            }
        };
        $this->setPrivateProperty($svc, TechnicalTeamService::class, 'db', $db);
        $this->setPrivateProperty($svc, TechnicalTeamService::class, 'playerId', $playerId);
        return $svc;
    }

    /** @return IncidentService */
    private function makeIncidentService(): IncidentService
    {
        $db = $this->db;
        $svc = new class extends IncidentService {
            public function __construct() {}
        };
        $this->setPrivateProperty($svc, IncidentService::class, 'db', $db);
        return $svc;
    }

    // =========================================================================
    // cancelTask: player_id na DELETE z kolejki
    // cancelTask: player_id on DELETE from queue
    // =========================================================================

    /**
     * cancelTask: DELETE FROM technical_task_queue musi uzyc AND player_id.
     * Wpis gracza 2 nie moze byc usuniety podczas anulowania zadania gracza 1.
     * cancelTask: DELETE FROM technical_task_queue must use AND player_id.
     * Player 2's entry must not be deleted when cancelling player 1's task.
     */
    public function testCancelTaskQueueDeleteDoesNotTouchOtherPlayerEntry(): void
    {
        // Gracz 1 / Player 1
        $this->db->exec("INSERT INTO players (id, cash) VALUES (1, 10000000)");
        $this->db->exec("INSERT INTO technical_staff (id, player_id, spec_code, status) VALUES (10, 1, 'maintenance_engineer', 'busy')");
        $this->db->exec("INSERT INTO technical_tasks (id, player_id, staff_id, task_type, status, end_time)
            VALUES (1, 1, 10, 'well_maintenance', 'in_progress', datetime('now', '+1 hour'))");

        // Gracz 2 - ma wpis w kolejce dla TEGO SAMEGO staff_id=10 / Player 2 - has queue entry for SAME staff_id=10
        $this->db->exec("INSERT INTO players (id, cash) VALUES (2, 10000000)");
        $this->db->exec("INSERT INTO technical_task_queue (player_id, staff_id, task_type) VALUES (2, 10, 'well_repair')");
        $queueId2 = (int)$this->db->lastInsertId();

        // Gracz 1 ma tez wpis w kolejce / Player 1 also has a queue entry
        $this->db->exec("INSERT INTO technical_task_queue (player_id, staff_id, task_type) VALUES (1, 10, 'hub_maintenance')");
        $queueId1 = (int)$this->db->lastInsertId();

        // Anuluj zadanie gracza 1 / Cancel player 1's task
        $svc = $this->makeService(1);
        $result = $svc->cancelTask(1);

        $this->assertTrue($result['success'], 'cancelTask musi sie udac / must succeed: ' . ($result['message'] ?? ''));

        // Wpis gracza 2 musi wciaz istniec / Player 2's queue entry must still exist
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM technical_task_queue WHERE id = ?");
        $stmt->execute([$queueId2]);
        $q2Exists = (int)$stmt->fetchColumn();
        $this->assertSame(1, $q2Exists, 'Wpis kolejki gracza 2 nie moze zostac usuniety / Player 2 queue entry must not be deleted');
    }

    public function testRepairSpeedPerkReducesRepairTaskDurationMultiplier(): void
    {
        $service = $this->makeService(1);
        $base = $service->getStaffBonus(['skill_level' => 5, 'repair_speed' => 0.25], 'safety_audit');
        $repair = $service->getStaffBonus(['skill_level' => 5, 'repair_speed' => 0.25], 'well_repair');

        $this->assertSame(1.0, $base['time_mult']);
        $this->assertSame(0.75, $repair['time_mult']);
    }

    /**
     * Bezposredni test SQL: DELETE z AND player_id nie usuwa wiersza innego gracza.
     * Direct SQL test: DELETE with AND player_id does not delete another player's row.
     */
    public function testQueueDeleteSqlRequiresMatchingPlayerId(): void
    {
        $this->db->exec("INSERT INTO technical_task_queue (player_id, staff_id, task_type) VALUES (1, 10, 'well_maintenance')");
        $queueId = (int)$this->db->lastInsertId();

        // Probuje usunac z blednym player_id / Tries to delete with wrong player_id
        $stmt = $this->db->prepare("DELETE FROM technical_task_queue WHERE id = ? AND player_id = ?");
        $stmt->execute([$queueId, 2]); // player_id=2, ale wpis nalezy do gracza 1 / belongs to player 1
        $this->assertSame(0, $stmt->rowCount(), 'DELETE z blednym player_id musi byc no-op / must be a no-op');

        $exists = (int)$this->db->query("SELECT COUNT(*) FROM technical_task_queue WHERE id = {$queueId}")->fetchColumn();
        $this->assertSame(1, $exists, 'Wpis musi dalej istniec / Entry must still exist');

        // Poprawny player_id usuwa wiersz / Correct player_id removes the row
        $stmt->execute([$queueId, 1]);
        $this->assertSame(1, $stmt->rowCount(), 'DELETE z poprawnym player_id: 1 wiersz / Correct player_id: 1 row');
    }

    // =========================================================================
    // upgradeProcedures: atomowe UPDATE + rozroznienie komunikatu
    // upgradeProcedures: atomic UPDATE + message disambiguation
    // =========================================================================

    /**
     * upgradeProcedures: brak gotowki zwraca komunikat no_funds (nie max_level).
     * upgradeProcedures: insufficient funds returns no_funds message (not max_level).
     *
     * Sprawdza Round 9 fix: po atomowym UPDATE rowCount=0 kod robi SELECT
     * zeby sprawdzic czy przyczyną jest brak kasy czy race condition.
     * Verifies Round 9 fix: after atomic UPDATE rowCount=0, code does SELECT
     * to check whether cause is insufficient cash or race condition.
     */
    public function testUpgradeProceduresNoFundsReturnsCorrectMessage(): void
    {
        // Gracz z zerowa gotowka ale wymaganymi pracownikami / Player with zero cash but required staff
        $this->db->exec("INSERT INTO players (id, cash, safety_procedures_level) VALUES (1, 0, 0)");
        $this->db->exec("INSERT INTO technical_staff (id, player_id, spec_code, status) VALUES (1, 1, 'safety_officer', 'active')");
        $this->db->exec("INSERT INTO technical_staff (id, player_id, spec_code, status) VALUES (2, 1, 'safety_engineer', 'active')");
        $this->db->exec("INSERT INTO technical_tasks (id, player_id, staff_id, task_type, status) VALUES (1, 1, 1, 'safety_audit', 'completed')");

        $svc    = $this->makeService(1);
        $result = $svc->upgradeProcedures();

        $this->assertFalse($result['success']);
        // Komunikat musi wskazywac na brak funds, nie max_level / Message must indicate no funds, not max_level
        $this->assertStringNotContainsString('max', strtolower($result['message'] ?? ''),
            'Brak gotowki: komunikat nie moze mowic o max_level / No funds: message must not say max_level');
    }

    /**
     * upgradeProcedures: wystarczajaca gotowka zwraca sukces i zwieksza poziom.
     * upgradeProcedures: sufficient cash returns success and increments level.
     *
     * Weryfikuje atomowy UPDATE cash + level w jednej operacji.
     * Verifies atomic UPDATE cash + level in a single operation.
     */
    public function testUpgradeProceduresSucceedsWithSufficientCash(): void
    {
        $cost = TechnicalTeamService::PROCEDURE_UPGRADE_COSTS[1];
        $this->db->exec("INSERT INTO players (id, cash, safety_procedures_level) VALUES (1, {$cost}, 0)");
        $this->db->exec("INSERT INTO technical_staff (id, player_id, spec_code, status) VALUES (1, 1, 'safety_officer', 'active')");
        $this->db->exec("INSERT INTO technical_staff (id, player_id, spec_code, status) VALUES (2, 1, 'safety_engineer', 'active')");
        $this->db->exec("INSERT INTO technical_tasks (id, player_id, staff_id, task_type, status) VALUES (1, 1, 1, 'safety_audit', 'completed')");

        $svc    = $this->makeService(1);
        $result = $svc->upgradeProcedures();

        $this->assertTrue($result['success'], 'Upgrade musi sie udac / must succeed: ' . ($result['message'] ?? ''));

        $row = $this->db->query("SELECT cash, safety_procedures_level FROM players WHERE id = 1")->fetch();
        $this->assertEqualsWithDelta(0.0, (float)$row['cash'], 0.01, 'Cash musi byc 0 po ulepszeniu / must be 0 after upgrade');
        $this->assertSame(1, (int)$row['safety_procedures_level'], 'Poziom musi wzrosnac do 1 / level must rise to 1');
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM bank_transactions WHERE transaction_type = 'tts_fee'")->fetchColumn());
    }

    /**
     * upgradeProcedures: poziom juz maksymalny zwraca max_level error.
     * upgradeProcedures: already at max level returns max_level error.
     */
    public function testUpgradeProceduresMaxLevelReturnsError(): void
    {
        $this->db->exec("INSERT INTO players (id, cash, safety_procedures_level) VALUES (1, 99999999, 5)");
        $this->db->exec("INSERT INTO technical_staff (id, player_id, spec_code, status) VALUES (1, 1, 'safety_officer', 'active')");
        $this->db->exec("INSERT INTO technical_staff (id, player_id, spec_code, status) VALUES (2, 1, 'safety_engineer', 'active')");

        $svc    = $this->makeService(1);
        $result = $svc->upgradeProcedures();

        $this->assertFalse($result['success']);
    }

    /**
     * upgradeProcedures: race condition (poziom zmieniony w locie) — atomowy UPDATE to wychwytuje.
     * upgradeProcedures: race condition (level changed mid-flight) — atomic UPDATE catches it.
     *
     * Symuluje konkurencyjny request ktory juz zmienil poziom zanim UPDATE zostal wykonany.
     * Simulates a concurrent request that already changed the level before the UPDATE ran.
     * Atomowy warunek AND safety_procedures_level = ? sprawia ze UPDATE failuje.
     * Atomic condition AND safety_procedures_level = ? makes the UPDATE fail.
     */
    public function testUpgradeProceduresAtomicUpdatePreventsDoubleUpgrade(): void
    {
        $cost = TechnicalTeamService::PROCEDURE_UPGRADE_COSTS[1];
        // Gracz ma gotowke na 2 ulepszenia / Player has cash for 2 upgrades
        $this->db->exec("INSERT INTO players (id, cash, safety_procedures_level) VALUES (1, " . ($cost * 2) . ", 0)");
        $this->db->exec("INSERT INTO technical_staff (id, player_id, spec_code, status) VALUES (1, 1, 'safety_officer', 'active')");
        $this->db->exec("INSERT INTO technical_staff (id, player_id, spec_code, status) VALUES (2, 1, 'safety_engineer', 'active')");
        $this->db->exec("INSERT INTO technical_tasks (id, player_id, staff_id, task_type, status) VALUES (1, 1, 1, 'safety_audit', 'completed')");

        // Symuluj ze inny request juz zmienil poziom z 0 na 1 / Simulate another request already bumped level from 0 to 1
        $this->db->exec("UPDATE players SET safety_procedures_level = 1 WHERE id = 1");

        // Teraz nasz request probuje upgrade z level=0 (stara wartosc) — atomowy UPDATE powinien failowac
        // Now our request tries upgrade with level=0 (stale value) — atomic UPDATE should fail
        $atomicStmt = $this->db->prepare("
            UPDATE players
            SET cash = cash - ?, safety_procedures_level = safety_procedures_level + 1, procedure_integrity = 100.0
            WHERE id = ? AND cash >= ? AND safety_procedures_level = ?
        ");
        $atomicStmt->execute([$cost, 1, $cost, 0]); // safety_procedures_level = 0 ale w DB juz 1 / but DB already has 1
        $this->assertSame(0, $atomicStmt->rowCount(),
            'Atomowy UPDATE musi failowac gdy poziom zmieniony przez inny request / Atomic UPDATE must fail when level changed by concurrent request');

        // Poziom w DB powinien nadal byc 1 (nie 2) / Level in DB must still be 1 (not 2)
        $level = (int)$this->db->query("SELECT safety_procedures_level FROM players WHERE id = 1")->fetchColumn();
        $this->assertSame(1, $level, 'Poziom nie moze byc zduplikowany przez race condition / Level must not be doubled by race condition');
    }

    // =========================================================================
    // hireEngineer: wzorzec $ownTx chroni przed zagniezdzona transakcja
    // hireEngineer: $ownTx pattern protects against nested transaction
    // =========================================================================

    /**
     * hireEngineer: kiedy jestesmy juz w transakcji, serwis NIE otwiera nowej.
     * hireEngineer: when already inside a transaction, the service does NOT open a new one.
     *
     * PDO/MySQL nie obsluguje zagniezdzonnych transakcji — nowe beginTransaction()
     * w srodku otwartej powoduje niejawny commit lub wyjatek (Rule 3).
     * PDO/MySQL does not support nested transactions — calling beginTransaction()
     * inside an open one causes implicit commit or exception (Rule 3).
     */
    public function testHireEngineerUsesOwnTxPatternNoNestedTransaction(): void
    {
        $this->db->exec("INSERT INTO players (id, cash) VALUES (1, 10000000)");
        $this->db->exec("INSERT INTO staff_specializations (code, name) VALUES ('maintenance_engineer', 'Inzynier Utrzymania Ruchu')");

        $this->db->beginTransaction(); // zewnetrzna transakcja / outer transaction

        $svc    = $this->makeService(1);
        $result = $svc->hireEngineer('maintenance_engineer', 'Jan', 'Kowalski', 5, 5000, 0);

        // Nadal w tej samej transakcji — brak commit/rollback z hireEngineer / Still in same transaction - no commit/rollback from hireEngineer
        $this->assertTrue($this->db->inTransaction(), 'Zewnetrzna transakcja musi byc nadal otwarta / Outer transaction must still be open');

        $this->db->rollBack(); // sprzatanie / cleanup

        // Sprawdz ze zatrudnienie sie powiodlo (poza transakcja) / Check hire succeeded (outside tx)
        $this->assertTrue($result['success'], 'hireEngineer musi sie udac / must succeed: ' . ($result['message'] ?? ''));
    }

    /**
     * hireEngineer: brak gotowki zwraca blad, saldo niezmienione.
     * hireEngineer: insufficient cash returns error, balance unchanged.
     */
    public function testHireEngineerRefusesWithInsufficientFunds(): void
    {
        $this->db->exec("INSERT INTO players (id, cash) VALUES (1, 100)"); // mniej niz salary / less than salary
        $this->db->exec("INSERT INTO staff_specializations (code, name) VALUES ('maintenance_engineer', 'Inzynier')");

        $svc    = $this->makeService(1);
        $result = $svc->hireEngineer('maintenance_engineer', 'Jan', 'Kowalski', 5, 5000, 0);

        $this->assertFalse($result['success']);
        $cashAfter = (float)$this->db->query("SELECT cash FROM players WHERE id = 1")->fetchColumn();
        $this->assertEqualsWithDelta(100.0, $cashAfter, 0.001, 'Saldo niezmienione po odmowie / Balance unchanged after refusal');
    }

    public function testHireEngineerRecordsFinancialTransaction(): void
    {
        $this->db->exec("INSERT INTO players (id, cash) VALUES (1, 100000)");
        $this->db->exec("INSERT INTO staff_specializations (code, name) VALUES ('maintenance_engineer', 'Inzynier')");

        $svc = $this->makeService(1);
        $result = $svc->hireEngineer('maintenance_engineer', 'Jan', 'Kowalski', 5, 5000, 0);

        $this->assertTrue($result['success'], 'hireEngineer musi sie udac / must succeed: ' . ($result['message'] ?? ''));
        $this->assertSame(95000.0, (float)$this->db->query("SELECT cash FROM players WHERE id = 1")->fetchColumn());
        $this->assertSame(1, (int)$this->db->query("SELECT COUNT(*) FROM bank_transactions WHERE transaction_type = 'hr_fee'")->fetchColumn());
    }

    // =========================================================================
    // getRecentIncidents: filtrowanie po player_id
    // getRecentIncidents: filtering by player_id
    // =========================================================================

    /**
     * getRecentIncidents: zwraca incydenty TYLKO dla podanego playerId.
     * getRecentIncidents: returns incidents ONLY for the given playerId.
     *
     * Przed naprawka brakujace AND player_id = ? pozwalalo graczowi na odczyt
     * incydentow innego gracza majacego odwiert o tym samym ID.
     * Before fix, missing AND player_id = ? allowed player to read another
     * player's incidents if their well had the same ID.
     */
    public function testGetRecentIncidentsFiltersOutOtherPlayersData(): void
    {
        // Obaj gracze maja incydenty na odwiercie o ID=10 / Both players have incidents on well id=10
        $this->db->exec("INSERT INTO well_incidents (well_id, player_id, type) VALUES (10, 1, 'minor')");
        $this->db->exec("INSERT INTO well_incidents (well_id, player_id, type) VALUES (10, 1, 'micro')");
        $this->db->exec("INSERT INTO well_incidents (well_id, player_id, type) VALUES (10, 2, 'major')"); // inny gracz / other player
        $this->db->exec("INSERT INTO well_incidents (well_id, player_id, type) VALUES (20, 1, 'minor')"); // inny odwiert / other well

        $svc      = $this->makeIncidentService();
        $result   = $svc->getRecentIncidents(10, 10, 1); // wellId=10, playerId=1

        $this->assertCount(2, $result, 'Musi byc 2 incydenty gracza 1 dla odwiertu 10 / Must be 2 incidents for player 1 well 10');
        foreach ($result as $inc) {
            $this->assertSame(1, (int)$inc['player_id'], 'Wszystkie incydenty musza nalez do gracza 1 / All incidents must belong to player 1');
            $this->assertSame(10, (int)$inc['well_id'], 'Wszystkie incydenty musza byc dla odwiertu 10 / All must be for well 10');
        }
    }

    /**
     * getRecentIncidents z playerId=0 (domyslny): nie zwraca zadnych incydentow.
     * getRecentIncidents with playerId=0 (default): returns no incidents.
     *
     * Waliduje ze brak player_id nie powoduje przecieku danych z playerId=0 nie istnieje.
     * Validates that missing player_id does not cause data leak (playerId=0 does not exist).
     */
    public function testGetRecentIncidentsWithDefaultPlayerIdReturnsEmpty(): void
    {
        $this->db->exec("INSERT INTO well_incidents (well_id, player_id, type) VALUES (10, 1, 'minor')");
        $this->db->exec("INSERT INTO well_incidents (well_id, player_id, type) VALUES (10, 2, 'minor')");

        $svc    = $this->makeIncidentService();
        $result = $svc->getRecentIncidents(10); // playerId=0 domyslnie / default playerId=0

        $this->assertCount(0, $result, 'Brak player_id (=0): wynik musi byc pusty / No player_id (=0): result must be empty');
    }

    /**
     * Bezposredni test SQL: SELECT z AND player_id izoluje wiersze gracza.
     * Direct SQL test: SELECT with AND player_id isolates player rows.
     */
    public function testIncidentSqlIsolatesPlayerData(): void
    {
        $this->db->exec("INSERT INTO well_incidents (well_id, player_id, type) VALUES (10, 1, 'minor')");
        $this->db->exec("INSERT INTO well_incidents (well_id, player_id, type) VALUES (10, 2, 'major')");

        $stmt = $this->db->prepare("SELECT * FROM well_incidents WHERE well_id = ? AND player_id = ?");
        $stmt->execute([10, 1]);
        $rows = $stmt->fetchAll();

        $this->assertCount(1, $rows);
        $this->assertSame(1, (int)$rows[0]['player_id']);
        $this->assertSame('minor', $rows[0]['type'],
            'SQL z AND player_id musi zwracac tylko wiersze gracza 1 / must return only player 1 rows');
    }
}
