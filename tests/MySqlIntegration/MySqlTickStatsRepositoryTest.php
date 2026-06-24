<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Tick/TickStatsRepository.php';

/**
 * Testy MySQL dla TickStatsRepository (naprawa C12).
 * MySQL tests for TickStatsRepository (C12 fix).
 *
 * C12: INSERT IGNORE wymaga UNIQUE KEY na ran_at, zeby deduplikowac
 * rownoczesne uruchomienia tika (admin force-tick + cron).
 * ensureSchema() tworzy lub zamienia zwykly indeks na UNIQUE KEY.
 *
 * C12: INSERT IGNORE requires a UNIQUE KEY on ran_at to deduplicate
 * concurrent tick runs (admin force-tick + cron).
 * ensureSchema() creates or replaces the plain index with a UNIQUE KEY.
 *
 * Uwaga: TickStatsRepository uzywa Database::getInstance()->getConnection()
 * (singleton), wiec operuje na tym samym MySQL co $this->db w tym tescie.
 * Note: TickStatsRepository uses Database::getInstance()->getConnection()
 * (singleton), so it operates on the same MySQL as $this->db in this test.
 */
final class MySqlTickStatsRepositoryTest extends MySqlIntegrationTestCase
{
    private const TEST_RAN_AT_PREFIX = '2099-12-31 00:';

    protected function tearDown(): void
    {
        // Czyszczenie wierszy tick_stats wstawionych przez testy / Clean up tick_stats rows inserted by tests
        try {
            $this->db->prepare(
                "DELETE FROM tick_stats WHERE ran_at LIKE '2099-12-31%'"
            )->execute();
        } catch (Throwable) {}

        parent::tearDown();
    }

    private function makeRepo(): TickStatsRepository
    {
        return new TickStatsRepository();
    }

    private function ranAt(string $suffix = '00:00'): string
    {
        return self::TEST_RAN_AT_PREFIX . $suffix;
    }

    private function minimalStats(string $ranAt): array
    {
        return [
            'ran_at'    => $ranAt,
            'source'    => 'phpunit',
            'trend_new' => false,
        ];
    }

    // =========================================================================
    // C12 fix: ensureSchema tworzy UNIQUE KEY na ran_at
    // C12 fix: ensureSchema creates UNIQUE KEY on ran_at
    // =========================================================================

    /**
     * C12 fix: po skonstruowaniu repozytorium indeks idx_ran_at musi byc UNIQUE.
     * C12 fix: after constructing the repository, idx_ran_at index must be UNIQUE.
     *
     * ensureSchema() sprawdza Non_unique i w razie potrzeby:
     *   - dodaje UNIQUE KEY (brak indeksu)
     *   - lub usuwa duplikaty i zamienia zwykly indeks na UNIQUE
     *
     * ensureSchema() checks Non_unique and if needed:
     *   - adds UNIQUE KEY (no index at all)
     *   - or purges duplicates and replaces plain index with UNIQUE
     */
    public function testEnsureSchemaCreatesUniqueIndexOnRanAt(): void
    {
        $this->makeRepo(); // ensureSchema() uruchamia sie w konstruktorze

        $stmt = $this->db->prepare(
            "SELECT Non_unique FROM INFORMATION_SCHEMA.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'tick_stats'
                AND INDEX_NAME   = 'idx_ran_at'
              LIMIT 1"
        );
        $stmt->execute();
        $nonUnique = $stmt->fetchColumn();

        $this->assertNotFalse($nonUnique, 'Indeks idx_ran_at musi istniec na tick_stats');
        $this->assertSame(0, (int)$nonUnique,
            'C12: idx_ran_at musi byc UNIQUE (Non_unique=0) po ensureSchema()');
    }

    // =========================================================================
    // Podstawowy zapis / Basic save
    // =========================================================================

    public function testSavePersistsRow(): void
    {
        $repo  = $this->makeRepo();
        $ranAt = $this->ranAt('00:10');
        $repo->save($this->minimalStats($ranAt));

        $stmt = $this->db->prepare("SELECT source FROM tick_stats WHERE ran_at = ?");
        $stmt->execute([$ranAt]);
        $this->assertSame('phpunit', $stmt->fetchColumn(),
            'save() musi wstawic wiersz do tick_stats');
    }

    public function testSavePersistsAllNumericFields(): void
    {
        $repo  = $this->makeRepo();
        $ranAt = $this->ranAt('00:11');
        $repo->save([
            'ran_at'               => $ranAt,
            'source'               => 'phpunit',
            'trend_new'            => false,
            'duration_ms'          => 1234,
            'players_processed'    => 5,
            'wells_active'         => 12,
            'total_production_bbl' => 99.5,
        ]);

        $stmt = $this->db->prepare(
            "SELECT duration_ms, players_processed, wells_active, total_production_bbl
               FROM tick_stats WHERE ran_at = ?"
        );
        $stmt->execute([$ranAt]);
        $row = $stmt->fetch();

        $this->assertSame('1234', (string)$row['duration_ms']);
        $this->assertSame('5',    (string)$row['players_processed']);
        $this->assertSame('12',   (string)$row['wells_active']);
        $this->assertEqualsWithDelta(99.5, (float)$row['total_production_bbl'], 0.001);
    }

    // =========================================================================
    // C12 fix: INSERT IGNORE deduplikacja rownoczesnych tikow / concurrent tick dedup
    // =========================================================================

    /**
     * C12 fix: drugie wywolanie save() z tym samym ran_at jest ignorowane (INSERT IGNORE).
     * Bez UNIQUE KEY na ran_at drugie wywolanie nadpisaloby wiersz lub wyrzucilo wyjatek.
     *
     * C12 fix: second save() with the same ran_at is ignored (INSERT IGNORE).
     * Without UNIQUE KEY on ran_at the second call would overwrite or throw.
     */
    public function testInsertIgnoreDeduplicatesOnSameRanAt(): void
    {
        $repo  = $this->makeRepo();
        $ranAt = $this->ranAt('00:20');

        $repo->save(array_merge($this->minimalStats($ranAt), ['source' => 'cron']));
        $repo->save(array_merge($this->minimalStats($ranAt), ['source' => 'admin'])); // ignorowany

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM tick_stats WHERE ran_at = ?");
        $stmt->execute([$ranAt]);
        $this->assertSame(1, (int)$stmt->fetchColumn(),
            'C12: INSERT IGNORE musi zachowac dokladnie 1 wiersz dla tego samego ran_at');
    }

    /**
     * C12 fix: INSERT IGNORE zachowuje oryginalny wiersz (source='cron'), nie nadpisuje.
     * C12 fix: INSERT IGNORE keeps the original row (source='cron'), not the duplicate.
     */
    public function testInsertIgnorePreservesOriginalRow(): void
    {
        $repo  = $this->makeRepo();
        $ranAt = $this->ranAt('00:21');

        $repo->save(array_merge($this->minimalStats($ranAt), ['source' => 'cron',  'duration_ms' => 100]));
        $repo->save(array_merge($this->minimalStats($ranAt), ['source' => 'admin', 'duration_ms' => 999]));

        $stmt = $this->db->prepare("SELECT source, duration_ms FROM tick_stats WHERE ran_at = ?");
        $stmt->execute([$ranAt]);
        $row = $stmt->fetch();

        $this->assertSame('cron', $row['source'],
            'C12: INSERT IGNORE musi zachowac oryginalny source (cron), nie nadpisac przez admin');
        $this->assertSame('100', (string)$row['duration_ms'],
            'C12: INSERT IGNORE musi zachowac oryginalny duration_ms (100), nie 999');
    }

    /**
     * Rozne ran_at: oba wiersze zapisane niezaleznie.
     * Different ran_at values: both rows persisted independently.
     */
    public function testDifferentRanAtBothPersisted(): void
    {
        $repo   = $this->makeRepo();
        $ranAt1 = $this->ranAt('00:30');
        $ranAt2 = $this->ranAt('00:31');

        $repo->save($this->minimalStats($ranAt1));
        $repo->save($this->minimalStats($ranAt2));

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM tick_stats WHERE ran_at IN (?, ?)"
        );
        $stmt->execute([$ranAt1, $ranAt2]);
        $this->assertSame(2, (int)$stmt->fetchColumn(),
            'Rozne ran_at musza generowac oddzielne wiersze');
    }
}
