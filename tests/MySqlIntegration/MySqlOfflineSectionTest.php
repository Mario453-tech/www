<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/OfflineSection.php';

/**
 * Testy MySQL dla OfflineSection (naprawa C13).
 * MySQL tests for OfflineSection (C13 fix).
 *
 * C13: gracz offline z cash=0 wchodzi w freeze mode — bit offline_frozen
 * jest ustawiany w wierszu gracza i odczytywany przy powrocie online,
 * zapisywany jako was_frozen w offline_reports.
 *
 * C13: offline player with cash=0 enters freeze mode — offline_frozen bit
 * is set on the player row and read on return, saved as was_frozen in offline_reports.
 *
 * Testy te weryfikuja zachowanie zalezne od MySQL:
 * - ensureSchema() dodaje kolumne offline_frozen przez ALTER TABLE (MySQL-only DDL)
 * - freeze mode zapisuje offline_frozen=1 do prawdziwej bazy
 * - powrot online odczytuje offline_frozen i wstawia was_frozen do offline_reports
 */
final class MySqlOfflineSectionTest extends MySqlIntegrationTestCase
{
    protected function tearDown(): void
    {
        $playerId = $this->getTrackedIds()['playerId'];
        // Czyszczenie raportow offline stworzonych w testach / Clean up offline reports created in tests
        try {
            $this->db->prepare("DELETE FROM offline_reports WHERE player_id = ?")->execute([$playerId]);
        } catch (Throwable) {}

        // Resetuj offline_frozen (jesli kolumna istnieje) przed usunieciem gracza
        // Reset offline_frozen (if column exists) before deleting the player
        try {
            $this->db->prepare("UPDATE players SET offline_frozen=0, offline_mode=0, offline_since=NULL WHERE id=?")
                     ->execute([$playerId]);
        } catch (Throwable) {}

        parent::tearDown();
    }

    private function minutesAgo(int $minutes): string
    {
        return date('Y-m-d H:i:s', time() - $minutes * 60);
    }

    private function hoursAgo(float $hours): string
    {
        return date('Y-m-d H:i:s', time() - (int)($hours * 3600));
    }

    // =========================================================================
    // C13 fix: ensureSchema dodaje kolumne offline_frozen (MySQL DDL)
    // C13 fix: ensureSchema adds offline_frozen column (MySQL DDL)
    // =========================================================================

    /**
     * ensureSchema() przez Database::addColumnIfMissing dodaje offline_frozen do players.
     * ensureSchema() via Database::addColumnIfMissing adds offline_frozen to players.
     */
    public function testEnsureSchemaAddsOfflineFrozenColumn(): void
    {
        // Konstruktor uruchamia ensureSchema() → addColumnIfMissing('players','offline_frozen',...).
        // The constructor runs ensureSchema() → addColumnIfMissing('players','offline_frozen',...).
        new OfflineSection($this->db, new DateTime());

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'players'
                AND COLUMN_NAME  = 'offline_frozen'"
        );
        $stmt->execute();
        $this->assertSame(1, (int)$stmt->fetchColumn(),
            'Kolumna offline_frozen musi istniec w players po ensureSchema()');
    }

    // =========================================================================
    // C13 fix: freeze mode → offline_frozen=1 w MySQL
    // C13 fix: freeze mode → offline_frozen=1 in MySQL
    // =========================================================================

    /**
     * C13 fix: freeze mode (offline + cash=0) zapisuje offline_frozen=1 w MySQL.
     * C13 fix: freeze mode (offline + cash=0) writes offline_frozen=1 to MySQL.
     */
    public function testFreezeModeSetsOfflineFrozenInMysql(): void
    {
        // Upewnij sie ze kolumna istnieje / Ensure column exists
        new OfflineSection($this->db, new DateTime());

        $playerId    = $this->seedPlayer();
        $lastActive  = $this->hoursAgo(2.0);
        $offlineSince = $lastActive;

        // Ustaw gracza na offline z gotowka = 0 / Set player to offline with cash = 0
        $this->db->prepare("UPDATE players SET cash=0, offline_mode=1, offline_since=?, last_active_at=? WHERE id=?")
                 ->execute([$offlineSince, $lastActive, $playerId]);

        // Skasuj potencjalnie stare offline_frozen / Clear any leftover offline_frozen
        $this->db->prepare("UPDATE players SET offline_frozen=0 WHERE id=?")->execute([$playerId]);

        $section = new OfflineSection($this->db, new DateTime());
        $ok = $section->process($playerId, [
            'last_active_at' => $lastActive,
            'offline_since'  => $offlineSince,
            'offline_mode'   => 1,
        ], 0.0);

        $this->assertFalse($ok, 'C13: freeze mode musi zwrocic false (pominac tick)');

        $stmt = $this->db->prepare("SELECT offline_frozen FROM players WHERE id = ?");
        $stmt->execute([$playerId]);
        $this->assertSame(1, (int)$stmt->fetchColumn(),
            'C13: offline_frozen musi byc 1 w MySQL po freeze mode');
    }

    // =========================================================================
    // C13 fix: powrot online — was_frozen w offline_reports z offline_frozen
    // C13 fix: return online — was_frozen in offline_reports from offline_frozen
    // =========================================================================

    /**
     * C13 fix: gracz wraca online majac offline_frozen=1 → was_frozen=1 w offline_reports.
     * C13 fix: player returns online with offline_frozen=1 → was_frozen=1 in offline_reports.
     */
    public function testReturnOnlineWithFrozenFlagSavesWasFrozenOne(): void
    {
        new OfflineSection($this->db, new DateTime()); // ensure column exists

        $playerId     = $this->seedPlayer();
        $offlineSince = $this->hoursAgo(2.0);
        $lastActive   = $this->minutesAgo(5);

        // Gracz byl offline przez 2h, teraz wraca online; offline_frozen=1 (byl zamrozony)
        // Player was offline for 2h, now returns online; offline_frozen=1 (was frozen)
        $this->db->prepare(
            "UPDATE players SET offline_mode=1, offline_since=?, last_active_at=?, offline_frozen=1 WHERE id=?"
        )->execute([$offlineSince, $lastActive, $playerId]);

        $section = new OfflineSection($this->db, new DateTime());
        $section->process($playerId, [
            'last_active_at' => $lastActive,
            'offline_since'  => $offlineSince,
            'offline_mode'   => 1,
        ], 1000.0); // gotowka > 0, wiec mozna bylo wrocic normalnie

        $stmt = $this->db->prepare(
            "SELECT was_frozen FROM offline_reports WHERE player_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$playerId]);
        $this->assertSame(1, (int)$stmt->fetchColumn(),
            'C13: was_frozen musi byc 1 w offline_reports gdy offline_frozen=1');
    }

    /**
     * Gracz wraca online bez zamrozenia → was_frozen=0 w offline_reports.
     * Player returns online without freeze flag → was_frozen=0 in offline_reports.
     */
    public function testReturnOnlineWithoutFrozenFlagSavesWasFrozenZero(): void
    {
        new OfflineSection($this->db, new DateTime());

        $playerId     = $this->seedPlayer();
        $offlineSince = $this->hoursAgo(2.0);
        $lastActive   = $this->minutesAgo(5);

        // offline_frozen=0 — gracz mial gotowke przez caly czas offline
        // offline_frozen=0 — player had cash throughout the offline period
        $this->db->prepare(
            "UPDATE players SET offline_mode=1, offline_since=?, last_active_at=?, offline_frozen=0 WHERE id=?"
        )->execute([$offlineSince, $lastActive, $playerId]);

        $section = new OfflineSection($this->db, new DateTime());
        $section->process($playerId, [
            'last_active_at' => $lastActive,
            'offline_since'  => $offlineSince,
            'offline_mode'   => 1,
        ], 5000.0);

        $stmt = $this->db->prepare(
            "SELECT was_frozen FROM offline_reports WHERE player_id = ? ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$playerId]);
        $this->assertSame(0, (int)$stmt->fetchColumn(),
            'was_frozen musi byc 0 w offline_reports gdy gracz nie byl zamrozony');
    }

    /**
     * Powrot online zeruje offline_frozen i offline_mode w MySQL.
     * Return online resets offline_frozen and offline_mode in MySQL.
     */
    public function testReturnOnlineResetsOfflineFieldsInMysql(): void
    {
        new OfflineSection($this->db, new DateTime());

        $playerId     = $this->seedPlayer();
        $offlineSince = $this->hoursAgo(2.0);
        $lastActive   = $this->minutesAgo(5);

        $this->db->prepare(
            "UPDATE players SET offline_mode=1, offline_since=?, last_active_at=?, offline_frozen=1 WHERE id=?"
        )->execute([$offlineSince, $lastActive, $playerId]);

        $section = new OfflineSection($this->db, new DateTime());
        $section->process($playerId, [
            'last_active_at' => $lastActive,
            'offline_since'  => $offlineSince,
            'offline_mode'   => 1,
        ], 2000.0);

        $stmt = $this->db->prepare(
            "SELECT offline_mode, offline_since, offline_frozen FROM players WHERE id = ?"
        );
        $stmt->execute([$playerId]);
        $row = $stmt->fetch();

        $this->assertSame('0', (string)$row['offline_mode'],
            'offline_mode musi byc zresetowany do 0 po powrocie online');
        $this->assertNull($row['offline_since'],
            'offline_since musi byc NULL po powrocie online');
        $this->assertSame('0', (string)$row['offline_frozen'],
            'C13: offline_frozen musi byc zresetowany do 0 po powrocie online');
    }
}
