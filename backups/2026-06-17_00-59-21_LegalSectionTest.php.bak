<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/LegalService.php';
require_once dirname(__DIR__, 2) . '/src/Tick/LegalSection.php';

/**
 * Etap 4 działu prawnego: tick decyzji.
 * Testuje LegalSection::run() — logikę rozpatrywania wniosków
 * (granted / refused / delayed / no_decision).
 */
final class LegalSectionTest extends SqliteIntegrationTestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        // Region 1: wszelkie ryzyko = 0 -> zawsze granted
        $this->insertRegionConfig(1, 'medium', 0, 0, 0, 10, 30, 120);
    }

    // ---------------------------------------------------------- granted (default)

    public function testRunGrantsApplicationWhenAllRisksZero(): void
    {
        $this->insertApplication(101, 1, 'pending', '-10 minutes');

        $this->runSection();

        $row = $this->fetchByPlayer(101);
        $this->assertSame('granted', $row['status']);
        $this->assertNotNull($row['decided_at']);
    }

    public function testRunGrantsDelayedApplicationToo(): void
    {
        $this->insertApplication(102, 1, 'delayed', '-30 minutes');

        $this->runSection();

        $row = $this->fetchByPlayer(102);
        $this->assertSame('granted', $row['status']);
    }

    // ---------------------------------------------------------- not yet due

    public function testRunSkipsApplicationsNotYetDue(): void
    {
        $this->insertApplication(103, 1, 'pending', '+1 hour');

        $section = $this->runSection();

        $this->assertSame(0, $section->decided);
        $this->assertSame('pending', $this->fetchByPlayer(103)['status']);
    }

    // ---------------------------------------------------------- already decided (status not in pending/delayed)

    public function testRunSkipsAlreadyGrantedApplications(): void
    {
        $this->insertApplication(104, 1, 'granted', '-1 hour');

        $section = $this->runSection();

        $this->assertSame(0, $section->decided);
        $this->assertSame('granted', $this->fetchByPlayer(104)['status']);
    }

    public function testRunSkipsRefusedApplications(): void
    {
        $this->insertApplication(105, 1, 'refused', '-1 hour');

        $section = $this->runSection();

        $this->assertSame(0, $section->decided);
    }

    // ---------------------------------------------------------- delayed outcome

    public function testRunAppliesDelayWhenConfigForces100PctDelay(): void
    {
        // delay_risk_pct = 100, no_dec = 0, refusal = 0 → always delayed
        $this->insertRegionConfig(10, 'high', 0, 0, 100, 15, 15, 240);
        $this->insertApplication(110, 10, 'pending', '-5 minutes');

        $this->runSection();

        $row = $this->fetchByPlayer(110);
        $this->assertSame('delayed', $row['status']);
        $this->assertNull($row['decided_at']);
        $this->assertSame('1', (string)$row['delay_count']);
        $this->assertGreaterThan(date('Y-m-d H:i:s'), $row['decision_due_at']);
    }

    public function testRunIncrementsDelayCountOnSubsequentDelay(): void
    {
        $this->insertRegionConfig(11, 'high', 0, 0, 100, 5, 5, 240);
        $this->insertApplicationWithDelayCount(111, 11, 'delayed', '-5 minutes', 1);

        $this->runSection();

        $row = $this->fetchByPlayer(111);
        $this->assertSame('delayed', $row['status']);
        $this->assertSame('2', (string)$row['delay_count']);
    }

    // ---------------------------------------------------------- refused outcome

    public function testRunRefusesApplicationWhenConfigForces100PctRefusal(): void
    {
        // no_dec = 0, refusal = 100 → always refused
        $this->insertRegionConfig(20, 'critical', 0, 100, 0, 20, 60, 480);
        $this->insertApplication(120, 20, 'pending', '-2 hours');

        $this->runSection();

        $row = $this->fetchByPlayer(120);
        $this->assertSame('refused', $row['status']);
        $this->assertNotNull($row['decided_at']);
        $this->assertNotNull($row['refusal_cooldown_until']);
        $this->assertGreaterThan($row['decided_at'], $row['refusal_cooldown_until']);
    }

    // ---------------------------------------------------------- no_decision outcome

    public function testRunSetsNoDecisionWhenConfigForces100PctNoDecision(): void
    {
        // no_dec = 100 → always no_decision (checked first)
        $this->insertRegionConfig(30, 'critical', 100, 0, 0, 20, 60, 480);
        $this->insertApplication(130, 30, 'pending', '-3 hours');

        $this->runSection();

        $row = $this->fetchByPlayer(130);
        $this->assertSame('no_decision', $row['status']);
        $this->assertNotNull($row['decided_at']);
    }

    // ---------------------------------------------------------- counters

    public function testDecidedCounterReflectsProcessedApplications(): void
    {
        $this->insertApplication(201, 1, 'pending', '-1 hour');
        $this->insertApplication(202, 1, 'pending', '-2 hours');

        $section = $this->runSection();

        $this->assertSame(2, $section->decided);
    }

    public function testNotifiedCounterMatchesDecided(): void
    {
        $this->insertApplication(301, 1, 'pending', '-1 hour');

        $section = $this->runSection();

        $this->assertSame(1, $section->notified);
        $notify = $this->db->prepare(
            "SELECT * FROM director_notifications WHERE player_id = 301"
        );
        $notify->execute();
        $row = $notify->fetch();
        $this->assertIsArray($row);
        $this->assertSame('legal', $row['type']);
    }

    public function testNotificationContainsCorrectOutcomeInfo(): void
    {
        $this->insertRegionConfig(40, 'medium', 0, 100, 0, 10, 30, 120); // always refused
        $this->insertApplication(302, 40, 'pending', '-1 hour');

        $this->runSection();

        $notify = $this->db->prepare(
            "SELECT * FROM director_notifications WHERE player_id = 302"
        );
        $notify->execute();
        $row = $notify->fetch();
        $this->assertIsArray($row);
        $this->assertSame('high', $row['priority']);
        $this->assertStringContainsString('odrzucony', strtolower($row['title']));
    }

    // ---------------------------------------------------------- helpers

    private function runSection(): LegalSection
    {
        $section = new LegalSection($this->db, new DateTime());
        $section->run();
        return $section;
    }

    /** @return array<string,mixed> */
    private function fetchByPlayer(int $playerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM drilling_permit_applications WHERE player_id = ? LIMIT 1"
        );
        $stmt->execute([$playerId]);
        $row = $stmt->fetch();
        $this->assertIsArray($row, "No application found for player {$playerId}");
        return $row;
    }

    private function createSchema(): void
    {
        $this->db->exec(
            "CREATE TABLE legal_region_config (
                region_id INTEGER PRIMARY KEY,
                enabled INTEGER NOT NULL DEFAULT 1,
                is_offshore INTEGER NOT NULL DEFAULT 0,
                risk_level TEXT NOT NULL DEFAULT 'low',
                application_cost REAL NOT NULL DEFAULT 100000.0,
                base_review_minutes INTEGER NOT NULL DEFAULT 60,
                delay_risk_pct REAL NOT NULL DEFAULT 0.0,
                delay_min_minutes INTEGER NOT NULL DEFAULT 10,
                delay_max_minutes INTEGER NOT NULL DEFAULT 30,
                no_decision_risk_pct REAL NOT NULL DEFAULT 0.0,
                refusal_risk_pct REAL NOT NULL DEFAULT 0.0,
                refusal_cooldown_minutes INTEGER NOT NULL DEFAULT 120,
                required_capital REAL NOT NULL DEFAULT 0.0,
                required_legal_level INTEGER NOT NULL DEFAULT 0
            )"
        );
        $this->db->exec(
            "CREATE TABLE drilling_permit_applications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                region_id INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending',
                cost REAL DEFAULT 0,
                submitted_at TEXT NULL,
                decision_due_at TEXT NULL,
                decided_at TEXT NULL,
                refusal_cooldown_until TEXT NULL,
                delay_count INTEGER NOT NULL DEFAULT 0,
                source TEXT DEFAULT 'player',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $this->db->exec(
            "CREATE TABLE director_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                type TEXT NOT NULL DEFAULT 'info',
                priority TEXT NOT NULL DEFAULT 'medium',
                title TEXT NOT NULL DEFAULT '',
                message TEXT NOT NULL DEFAULT '',
                icon TEXT NOT NULL DEFAULT '',
                requires_action INTEGER NOT NULL DEFAULT 0,
                action_url TEXT NULL,
                action_label TEXT NULL,
                expires_at TEXT NULL,
                is_read INTEGER NOT NULL DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    private function insertRegionConfig(
        int    $regionId,
        string $risk,
        float  $noDecRisk,
        float  $refusRisk,
        float  $delayRisk,
        int    $delayMin,
        int    $delayMax,
        int    $cooldown
    ): void {
        $this->db->prepare(
            "INSERT OR REPLACE INTO legal_region_config
                (region_id, risk_level, no_decision_risk_pct, refusal_risk_pct,
                 delay_risk_pct, delay_min_minutes, delay_max_minutes,
                 refusal_cooldown_minutes, application_cost, base_review_minutes,
                 required_capital, required_legal_level)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 100000, 60, 0, 0)"
        )->execute([$regionId, $risk, $noDecRisk, $refusRisk, $delayRisk, $delayMin, $delayMax, $cooldown]);
    }

    private function insertApplication(int $playerId, int $regionId, string $status, string $dueDelta): void
    {
        $dueAt = (new DateTime())->modify($dueDelta)->format('Y-m-d H:i:s');
        $this->db->prepare(
            "INSERT INTO drilling_permit_applications
                (player_id, region_id, status, submitted_at, decision_due_at)
             VALUES (?, ?, ?, datetime('now'), ?)"
        )->execute([$playerId, $regionId, $status, $dueAt]);
    }

    private function insertApplicationWithDelayCount(
        int    $playerId,
        int    $regionId,
        string $status,
        string $dueDelta,
        int    $delayCount
    ): void {
        $dueAt = (new DateTime())->modify($dueDelta)->format('Y-m-d H:i:s');
        $this->db->prepare(
            "INSERT INTO drilling_permit_applications
                (player_id, region_id, status, submitted_at, decision_due_at, delay_count)
             VALUES (?, ?, ?, datetime('now'), ?, ?)"
        )->execute([$playerId, $regionId, $status, $dueAt, $delayCount]);
    }
}
