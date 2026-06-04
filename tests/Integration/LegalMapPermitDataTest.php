<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/LegalService.php';

/**
 * Brief §5-§7: LegalService::getMapPermitData() — batch status zezwoleń per
 * region dla mapy. Pokrywa wszystkie warianty statusu zwracane do frontendu:
 * active / pending / delayed / no_decision / refused / locked / none.
 *
 * Brief §5-§7: batch permit status per region for the map. Covers every status
 * variant returned to the frontend.
 */
final class LegalMapPermitDataTest extends SqliteIntegrationTestCase
{
    private PDO $db;
    private LegalService $service;

    /** Stały „teraz" dla deterministycznych obliczeń minut. */
    private DateTime $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = $this->createSqlitePdo();
        $this->createSchema();
        $this->service = new LegalService($this->db);
        $this->now = new DateTime('2026-06-04 12:00:00');
    }

    public function testEmptyRegionIdsReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->service->getMapPermitData(100, [], 0.0, $this->now));
    }

    public function testNoneWhenNoApplicationAndNoCapitalRequirement(): void
    {
        $this->seedConfig(1, ['required_capital' => 0.0]);

        $data = $this->service->getMapPermitData(100, [1], 1000.0, $this->now);

        $this->assertSame('none', $data[1]['status']);
        $this->assertNull($data[1]['minutes_left']);
        $this->assertNull($data[1]['cooldown_minutes']);
        $this->assertNull($data[1]['required_capital']);
    }

    public function testActiveForGrantedAndTransitional(): void
    {
        $this->seedConfig(1);
        $this->seedConfig(2);
        $this->insertApp(100, 1, 'granted');
        $this->insertApp(100, 2, 'transitional');

        $data = $this->service->getMapPermitData(100, [1, 2], 0.0, $this->now);

        $this->assertSame('active', $data[1]['status']);
        $this->assertSame('active', $data[2]['status']);
    }

    public function testPendingIncludesMinutesLeft(): void
    {
        $this->seedConfig(1);
        // Termin decyzji 30 minut po „teraz".
        $this->insertApp(100, 1, 'pending', ['decision_due_at' => '2026-06-04 12:30:00']);

        $data = $this->service->getMapPermitData(100, [1], 0.0, $this->now);

        $this->assertSame('pending', $data[1]['status']);
        $this->assertSame(30, $data[1]['minutes_left']);
    }

    public function testDelayedIncludesMinutesLeft(): void
    {
        $this->seedConfig(1);
        $this->insertApp(100, 1, 'delayed', ['decision_due_at' => '2026-06-04 12:45:00']);

        $data = $this->service->getMapPermitData(100, [1], 0.0, $this->now);

        $this->assertSame('delayed', $data[1]['status']);
        $this->assertSame(45, $data[1]['minutes_left']);
    }

    public function testPendingPastDueClampsMinutesToZero(): void
    {
        $this->seedConfig(1);
        // Termin minął — minutes_left nie może być ujemne.
        $this->insertApp(100, 1, 'pending', ['decision_due_at' => '2026-06-04 11:30:00']);

        $data = $this->service->getMapPermitData(100, [1], 0.0, $this->now);

        $this->assertSame('pending', $data[1]['status']);
        $this->assertSame(0, $data[1]['minutes_left']);
    }

    public function testNoDecisionStatus(): void
    {
        $this->seedConfig(1);
        $this->insertApp(100, 1, 'no_decision');

        $data = $this->service->getMapPermitData(100, [1], 0.0, $this->now);

        $this->assertSame('no_decision', $data[1]['status']);
    }

    public function testRefusedWithinCooldownReturnsCooldownMinutes(): void
    {
        $this->seedConfig(1);
        // Cooldown do 60 minut po „teraz".
        $this->insertApp(100, 1, 'refused', ['refusal_cooldown_until' => '2026-06-04 13:00:00']);

        $data = $this->service->getMapPermitData(100, [1], 0.0, $this->now);

        $this->assertSame('refused', $data[1]['status']);
        $this->assertSame(60, $data[1]['cooldown_minutes']);
    }

    public function testRefusedPastCooldownFallsBackToNone(): void
    {
        $this->seedConfig(1, ['required_capital' => 0.0]);
        // Cooldown minął — region znów dostępny (none).
        $this->insertApp(100, 1, 'refused', ['refusal_cooldown_until' => '2026-06-04 11:00:00']);

        $data = $this->service->getMapPermitData(100, [1], 1000.0, $this->now);

        $this->assertSame('none', $data[1]['status']);
        $this->assertNull($data[1]['cooldown_minutes']);
    }

    public function testRefusedPastCooldownBecomesLockedWhenCapitalMissing(): void
    {
        // Po cooldownie, ale firma nie spełnia wymogu kapitału → locked (§7.3).
        $this->seedConfig(1, ['required_capital' => 5000000.0]);
        $this->insertApp(100, 1, 'refused', ['refusal_cooldown_until' => '2026-06-04 11:00:00']);

        $data = $this->service->getMapPermitData(100, [1], 1000000.0, $this->now);

        $this->assertSame('locked', $data[1]['status']);
        $this->assertEqualsWithDelta(5000000.0, (float)$data[1]['required_capital'], 0.001);
    }

    public function testLockedWhenCapitalRequiredAndInsufficient(): void
    {
        $this->seedConfig(2, ['required_capital' => 5000000.0]);

        $data = $this->service->getMapPermitData(100, [2], 1000000.0, $this->now);

        $this->assertSame('locked', $data[2]['status']);
        $this->assertEqualsWithDelta(5000000.0, (float)$data[2]['required_capital'], 0.001);
        $this->assertNull($data[2]['minutes_left']);
    }

    public function testCapitalMetReturnsNoneNotLocked(): void
    {
        $this->seedConfig(2, ['required_capital' => 5000000.0]);

        // Gracz ma dokładnie wymagany kapitał → nie zablokowany.
        $data = $this->service->getMapPermitData(100, [2], 5000000.0, $this->now);

        $this->assertSame('none', $data[2]['status']);
        $this->assertNull($data[2]['required_capital']);
    }

    public function testLegalLevelMissingReturnsLegalLocked(): void
    {
        $this->createBoardSchema();
        $this->seedConfig(2, ['required_legal_level' => 7, 'required_capital' => 0.0]);
        $this->seedLegalDirector(100, 5, 5, 5);

        $data = $this->service->getMapPermitData(100, [2], 5000000.0, $this->now);

        $this->assertSame('legal_locked', $data[2]['status']);
        $this->assertSame(7, $data[2]['required_legal_level']);
        $this->assertSame(5, $data[2]['legal_level']);
    }

    public function testLegalLevelMetReturnsNoneNotLegalLocked(): void
    {
        $this->createBoardSchema();
        $this->seedConfig(2, ['required_legal_level' => 7, 'required_capital' => 0.0]);
        $this->seedLegalDirector(100, 8, 8, 8);

        $data = $this->service->getMapPermitData(100, [2], 5000000.0, $this->now);

        $this->assertSame('none', $data[2]['status']);
        $this->assertNull($data[2]['required_legal_level']);
    }

    public function testActiveOverridesCapitalLock(): void
    {
        // Aktywne zezwolenie ma pierwszeństwo nad blokadą kapitałową.
        $this->seedConfig(2, ['required_capital' => 5000000.0]);
        $this->insertApp(100, 2, 'granted');

        $data = $this->service->getMapPermitData(100, [2], 0.0, $this->now);

        $this->assertSame('active', $data[2]['status']);
    }

    public function testMixedRegionsInSingleCall(): void
    {
        $this->seedConfig(1, ['required_capital' => 0.0]);
        $this->seedConfig(2, ['required_capital' => 5000000.0]);
        $this->seedConfig(3, ['required_capital' => 0.0]);
        $this->insertApp(100, 1, 'granted');
        $this->insertApp(100, 3, 'pending', ['decision_due_at' => '2026-06-04 12:15:00']);

        $data = $this->service->getMapPermitData(100, [1, 2, 3], 1000000.0, $this->now);

        $this->assertSame('active', $data[1]['status']);
        $this->assertSame('locked', $data[2]['status']);
        $this->assertSame('pending', $data[3]['status']);
        $this->assertSame(15, $data[3]['minutes_left']);
    }

    public function testOtherPlayersApplicationsAreIgnored(): void
    {
        $this->seedConfig(1, ['required_capital' => 0.0]);
        $this->insertApp(999, 1, 'granted'); // inny gracz

        $data = $this->service->getMapPermitData(100, [1], 1000.0, $this->now);

        $this->assertSame('none', $data[1]['status'], 'Zezwolenie innego gracza nie wpływa na status.');
    }

    // --------------------------------------------------------------- Helpers

    private function createSchema(): void
    {
        $this->db->exec(
            "CREATE TABLE legal_region_config (
                region_id INTEGER PRIMARY KEY,
                enabled INTEGER DEFAULT 1,
                is_offshore INTEGER DEFAULT 0,
                risk_level TEXT DEFAULT 'low',
                application_cost REAL DEFAULT 100000,
                base_review_minutes INTEGER DEFAULT 60,
                delay_risk_pct REAL DEFAULT 0,
                delay_min_minutes INTEGER DEFAULT 10,
                delay_max_minutes INTEGER DEFAULT 30,
                no_decision_risk_pct REAL DEFAULT 0,
                refusal_risk_pct REAL DEFAULT 0,
                refusal_cooldown_minutes INTEGER DEFAULT 120,
                required_capital REAL DEFAULT 0,
                required_legal_level INTEGER DEFAULT 0
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
                delay_count INTEGER DEFAULT 0,
                source TEXT DEFAULT 'player',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    /** @param array<string,mixed> $over */
    private function seedConfig(int $regionId, array $over = []): void
    {
        $cfg = array_merge([
            'enabled' => 1, 'risk_level' => 'low', 'application_cost' => 100000.0,
            'base_review_minutes' => 60, 'required_capital' => 0.0,
            'required_legal_level' => 0,
        ], $over);
        $this->db->prepare(
            "INSERT INTO legal_region_config
                (region_id, enabled, risk_level, application_cost, base_review_minutes,
                 required_capital, required_legal_level)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $regionId, $cfg['enabled'], $cfg['risk_level'], $cfg['application_cost'],
            $cfg['base_review_minutes'], $cfg['required_capital'], $cfg['required_legal_level'],
        ]);
    }

    private function createBoardSchema(): void
    {
        $this->db->exec('CREATE TABLE board_roles (id INTEGER PRIMARY KEY, code TEXT NOT NULL)');
        $this->db->exec(
            'CREATE TABLE board_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id INTEGER NOT NULL,
                role_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                skill_organization INTEGER DEFAULT 0,
                skill_analysis INTEGER DEFAULT 0,
                skill_ethics INTEGER DEFAULT 0
            )'
        );
        $this->db->exec("INSERT INTO board_roles (id, code) VALUES (1, 'legal')");
    }

    private function seedLegalDirector(
        int $playerId,
        int $organization,
        int $analysis,
        int $ethics
    ): void {
        $this->db->prepare(
            'INSERT INTO board_members
                (player_id, role_id, status, skill_organization, skill_analysis, skill_ethics)
             VALUES (?, 1, \'active\', ?, ?, ?)'
        )->execute([$playerId, $organization, $analysis, $ethics]);
    }

    /** @param array<string,mixed> $over */
    private function insertApp(int $playerId, int $regionId, string $status, array $over = []): void
    {
        $row = array_merge([
            'decision_due_at' => null,
            'refusal_cooldown_until' => null,
        ], $over);
        $this->db->prepare(
            "INSERT INTO drilling_permit_applications
                (player_id, region_id, status, decision_due_at, refusal_cooldown_until)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$playerId, $regionId, $status, $row['decision_due_at'], $row['refusal_cooldown_until']]);
    }
}
