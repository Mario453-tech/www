<?php
declare(strict_types=1);

require_once __DIR__ . '/SqliteIntegrationTestCase.php';

/**
 * Testy SQLite dla OfflineSection::process (naprawa C13).
 * SQLite tests for OfflineSection::process (C13 fix).
 *
 * C13: gracz offline z cash=0 wchodzi w freeze mode.
 * Bit offline_frozen ustawiany jest w wierszu gracza i odczytywany
 * przy powrocie online — zapisywany jako was_frozen w offline_reports.
 *
 * C13: offline player with cash=0 enters freeze mode.
 * The offline_frozen bit is set on the player row and read on return —
 * saved as was_frozen in offline_reports.
 */
final class OfflineSectionTest extends SqliteIntegrationTestCase
{
    private PDO      $db;
    private DateTime $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db  = $this->createSqlitePdo();
        $this->now = new DateTime('2026-06-24 12:00:00');

        $this->db->exec("
            CREATE TABLE players (
                id             INTEGER PRIMARY KEY,
                cash           REAL    NOT NULL DEFAULT 0.0,
                offline_mode   INTEGER NOT NULL DEFAULT 0,
                offline_since  TEXT    NULL,
                last_active_at TEXT    NULL,
                offline_frozen INTEGER NOT NULL DEFAULT 0
            )
        ");
        $this->db->exec("
            CREATE TABLE offline_reports (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                player_id     INTEGER NOT NULL,
                offline_from  TEXT    NOT NULL,
                offline_to    TEXT    NOT NULL,
                offline_hours REAL    NOT NULL DEFAULT 0.0,
                was_frozen    INTEGER NOT NULL DEFAULT 0,
                summary_json  TEXT    NULL,
                shown         INTEGER NOT NULL DEFAULT 0,
                created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
            )
        ");
    }

    private function seedPlayer(
        int     $id,
        float   $cash          = 1000.0,
        ?string $lastActiveAt  = null,
        int     $offlineMode   = 0,
        ?string $offlineSince  = null,
        int     $offlineFrozen = 0
    ): void {
        $this->db->prepare(
            "INSERT INTO players (id, cash, offline_mode, offline_since, last_active_at, offline_frozen)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$id, $cash, $offlineMode, $offlineSince, $lastActiveAt, $offlineFrozen]);
    }

    private function makeSection(): OfflineSection
    {
        return new OfflineSection($this->db, $this->now);
    }

    private function minutesAgo(int $minutes): string
    {
        return date('Y-m-d H:i:s', strtotime($this->now->format('Y-m-d H:i:s')) - $minutes * 60);
    }

    private function hoursAgo(float $hours): string
    {
        return date('Y-m-d H:i:s', strtotime($this->now->format('Y-m-d H:i:s')) - (int)($hours * 3600));
    }

    private function playerData(
        string  $lastActiveAt,
        ?string $offlineSince = null,
        int     $offlineMode  = 0
    ): array {
        return [
            'last_active_at' => $lastActiveAt,
            'offline_since'  => $offlineSince,
            'offline_mode'   => $offlineMode,
        ];
    }

    // =========================================================================
    // Detekcja online / offline / Online – offline detection
    // =========================================================================

    public function testIsOnlineWhenRecentlyActive(): void
    {
        $lastActive = $this->minutesAgo(5);
        $this->seedPlayer(1, 1000.0, $lastActive);

        $sec = $this->makeSection();
        $ok  = $sec->process(1, $this->playerData($lastActive), 1000.0);

        $this->assertTrue($ok, 'Aktywny 5 min temu — tick musi byc kontynuowany');
        $this->assertFalse($sec->isOffline, 'Gracz aktywny 5 min temu nie jest offline');
        $this->assertFalse($sec->offlineProtectionActive);
        $this->assertFalse($sec->freezeMode);
    }

    public function testIsOfflineAfter45MinInactivity(): void
    {
        $lastActive = $this->minutesAgo(45);
        $this->seedPlayer(1, 1000.0, $lastActive, 1, $lastActive);

        $sec = $this->makeSection();
        $sec->process(1, $this->playerData($lastActive, $lastActive, 1), 1000.0);

        $this->assertTrue($sec->isOffline, 'Gracz nieaktywny od 45 min powinien byc offline');
    }

    // =========================================================================
    // Ochrona offline (< 24h) / Offline protection (< 24h)
    // =========================================================================

    public function testOfflineProtectionMultipliersApplied(): void
    {
        $lastActive = $this->hoursAgo(2.0);
        $this->seedPlayer(1, 1000.0, $lastActive, 1, $lastActive);

        $sec = $this->makeSection();
        $sec->process(1, $this->playerData($lastActive, $lastActive, 1), 1000.0);

        $this->assertTrue($sec->offlineProtectionActive, 'Ochrona offline aktywna w ciagu 24h');
        $this->assertEqualsWithDelta(0.80, $sec->offlineProdMult, 0.001,
            'Mnoznik produkcji = 0.80 gdy ochrona offline aktywna');
        $this->assertEqualsWithDelta(0.50, $sec->offlineRiskMult, 0.001,
            'Mnoznik ryzyka = 0.50 gdy ochrona offline aktywna');
    }

    public function testOfflineProtectionExpiresAfter24Hours(): void
    {
        $lastActive = $this->hoursAgo(25.0);
        $this->seedPlayer(1, 1000.0, $lastActive, 1, $lastActive);

        $sec = $this->makeSection();
        $sec->process(1, $this->playerData($lastActive, $lastActive, 1), 1000.0);

        $this->assertTrue($sec->isOffline, 'Gracz nadal offline po 25h');
        $this->assertFalse($sec->offlineProtectionActive, 'Ochrona offline wygasa po 24h');
        $this->assertEqualsWithDelta(1.0, $sec->offlineProdMult, 0.001,
            'Mnoznik produkcji wraca do 1.0 po wyganieciu ochrony');
    }

    // =========================================================================
    // C13 fix: freeze mode — offline + cash=0 → pominiecie ticka / skip tick
    // =========================================================================

    public function testFreezeModeSkipsTickWhenOfflineAndCashIsZero(): void
    {
        $lastActive = $this->hoursAgo(2.0);
        $this->seedPlayer(1, 0.0, $lastActive, 1, $lastActive);

        $sec = $this->makeSection();
        $ok  = $sec->process(1, $this->playerData($lastActive, $lastActive, 1), 0.0);

        $this->assertFalse($ok, 'C13: freeze mode musi zwrocic false (pominac tick)');
        $this->assertTrue($sec->freezeMode, 'freezeMode musi byc true gdy offline i cash=0');
    }

    /**
     * C13 fix: freeze mode ustawia offline_frozen=1 w wierszu gracza.
     * Bit ten jest pozniej odczytywany gdy gracz wraca online (patrz testReturnOnlineReadsWasFrozen*).
     *
     * C13 fix: freeze mode sets offline_frozen=1 on the player row.
     * This bit is later read when the player comes back online (see testReturnOnlineReadsWasFrozen*).
     */
    public function testFreezeModeSetsOfflineFrozenColumn(): void
    {
        $lastActive = $this->hoursAgo(2.0);
        $this->seedPlayer(1, 0.0, $lastActive, 1, $lastActive, 0);

        $sec = $this->makeSection();
        $sec->process(1, $this->playerData($lastActive, $lastActive, 1), 0.0);

        $stmt = $this->db->prepare("SELECT offline_frozen FROM players WHERE id = ?");
        $stmt->execute([1]);
        $this->assertSame(1, (int)$stmt->fetchColumn(),
            'C13: freeze mode musi ustawic offline_frozen=1 w wierszu gracza');
    }

    public function testNoFreezeModeWhenOfflineButHasCash(): void
    {
        $lastActive = $this->hoursAgo(2.0);
        $this->seedPlayer(1, 5000.0, $lastActive, 1, $lastActive);

        $sec = $this->makeSection();
        $ok  = $sec->process(1, $this->playerData($lastActive, $lastActive, 1), 5000.0);

        $this->assertTrue($ok, 'Brak freeze mode gdy cash > 0 — tick normalny');
        $this->assertFalse($sec->freezeMode);
    }

    public function testNoFreezeModeWhenProtectionExpiredEvenWithZeroCash(): void
    {
        // Ochrona wygas po 24h — freeze mode nie dziala nawet przy cash=0.
        // Protection expired after 24h — freeze mode does not activate even with cash=0.
        $lastActive = $this->hoursAgo(25.0);
        $this->seedPlayer(1, 0.0, $lastActive, 1, $lastActive);

        $sec = $this->makeSection();
        $ok  = $sec->process(1, $this->playerData($lastActive, $lastActive, 1), 0.0);

        $this->assertTrue($ok, 'Po wyganieciu ochrony (25h) tick nie jest pomijany mimo cash=0');
        $this->assertFalse($sec->freezeMode, 'Brak freeze mode po wyganieciu ochrony offline');
    }

    // =========================================================================
    // Zapis offline_mode / offline_mode persistence
    // =========================================================================

    public function testSetsOfflineModeWhenJustWentOffline(): void
    {
        // Gracz byl online (offline_mode=0), teraz nieaktywny od 45 min.
        // Player was online (offline_mode=0), now inactive for 45 min.
        $lastActive = $this->minutesAgo(45);
        $this->seedPlayer(1, 1000.0, $lastActive, 0, null);

        $sec = $this->makeSection();
        $sec->process(1, $this->playerData($lastActive, null, 0), 1000.0);

        $stmt = $this->db->prepare("SELECT offline_mode, offline_since FROM players WHERE id = ?");
        $stmt->execute([1]);
        $row = $stmt->fetch();

        $this->assertSame('1', (string)$row['offline_mode'],
            'offline_mode musi byc 1 gdy gracz wlasnie przeszedl offline');
        $this->assertNotNull($row['offline_since'],
            'offline_since musi byc ustawiony gdy gracz wlasnie przeszedl offline');
    }

    // =========================================================================
    // C13: powrot online — raport z offline_frozen / return online — offline_frozen report
    // =========================================================================

    public function testReturnOnlineInsertsOfflineReport(): void
    {
        $offlineSince = $this->hoursAgo(2.0);
        $lastActive   = $this->minutesAgo(5);
        $this->seedPlayer(1, 1000.0, $lastActive, 1, $offlineSince);

        $sec = $this->makeSection();
        $sec->process(1, $this->playerData($lastActive, $offlineSince, 1), 1000.0);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM offline_reports WHERE player_id = ?");
        $stmt->execute([1]);
        $this->assertSame(1, (int)$stmt->fetchColumn(),
            'Raport offline musi zostac wstawiony gdy gracz wraca online');
    }

    /**
     * C13 fix: offline_reports.was_frozen=1 gdy gracz mial offline_frozen=1 w players.
     * C13 fix: offline_reports.was_frozen=1 when player had offline_frozen=1 in players.
     */
    public function testReturnOnlineReadsWasFrozenOneFromPlayersRow(): void
    {
        $offlineSince = $this->hoursAgo(2.0);
        $lastActive   = $this->minutesAgo(5);
        $this->seedPlayer(1, 1000.0, $lastActive, 1, $offlineSince, 1); // offline_frozen=1

        $sec = $this->makeSection();
        $sec->process(1, $this->playerData($lastActive, $offlineSince, 1), 1000.0);

        $stmt = $this->db->prepare("SELECT was_frozen FROM offline_reports WHERE player_id = ?");
        $stmt->execute([1]);
        $this->assertSame(1, (int)$stmt->fetchColumn(),
            'C13: was_frozen musi byc 1 w raporcie gdy gracz mial offline_frozen=1');
    }

    public function testReturnOnlineReadsWasFrozenZeroWhenNotFrozen(): void
    {
        $offlineSince = $this->hoursAgo(2.0);
        $lastActive   = $this->minutesAgo(5);
        $this->seedPlayer(1, 1000.0, $lastActive, 1, $offlineSince, 0); // offline_frozen=0

        $sec = $this->makeSection();
        $sec->process(1, $this->playerData($lastActive, $offlineSince, 1), 1000.0);

        $stmt = $this->db->prepare("SELECT was_frozen FROM offline_reports WHERE player_id = ?");
        $stmt->execute([1]);
        $this->assertSame(0, (int)$stmt->fetchColumn(),
            'was_frozen musi byc 0 w raporcie gdy gracz nie byl zamrozony');
    }

    public function testReturnOnlineResetsOfflineFrozen(): void
    {
        $offlineSince = $this->hoursAgo(2.0);
        $lastActive   = $this->minutesAgo(5);
        $this->seedPlayer(1, 1000.0, $lastActive, 1, $offlineSince, 1);

        $sec = $this->makeSection();
        $sec->process(1, $this->playerData($lastActive, $offlineSince, 1), 1000.0);

        $stmt = $this->db->prepare("SELECT offline_frozen FROM players WHERE id = ?");
        $stmt->execute([1]);
        $this->assertSame(0, (int)$stmt->fetchColumn(),
            'offline_frozen musi byc zresetowany do 0 gdy gracz wraca online');
    }

    public function testReturnOnlineResetsOfflineMode(): void
    {
        $offlineSince = $this->hoursAgo(2.0);
        $lastActive   = $this->minutesAgo(5);
        $this->seedPlayer(1, 1000.0, $lastActive, 1, $offlineSince);

        $sec = $this->makeSection();
        $sec->process(1, $this->playerData($lastActive, $offlineSince, 1), 1000.0);

        $stmt = $this->db->prepare("SELECT offline_mode, offline_since FROM players WHERE id = ?");
        $stmt->execute([1]);
        $row = $stmt->fetch();

        $this->assertSame('0', (string)$row['offline_mode'],
            'offline_mode musi byc zresetowany do 0 gdy gracz wraca online');
        $this->assertNull($row['offline_since'],
            'offline_since musi byc NULL gdy gracz wraca online');
    }
}
