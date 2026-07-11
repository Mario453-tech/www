<?php

/**
 * TickStatsRepository zapis i odczyt statystyk tickow gry.
 * TickStatsRepository saves and reads game tick statistics.
 */
class TickStatsRepository
{
    private PDO $db;

    /** @var array<int,bool> */
    private static array $schemaEnsured = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->ensureSchema();
    }

    /**
     * Zapewnia indeks UNIQUE i kolumny metryk kontraktow.
     * Ensures UNIQUE index and contract metric columns.
     */
    private function ensureSchema(): void
    {
        $connId = spl_object_id($this->db);
        if (isset(self::$schemaEnsured[$connId])) {
            return;
        }
        self::$schemaEnsured[$connId] = true;

        try {
            $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $this->db->exec("CREATE TABLE IF NOT EXISTS tick_stats (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    ran_at TEXT NOT NULL,
                    tick_sequence INTEGER NOT NULL DEFAULT 0,
                    source TEXT NULL,
                    duration_ms INTEGER NULL,
                    oil_price REAL NULL,
                    trend_name TEXT NULL,
                    trend_new INTEGER NULL,
                    bank_interest_processed INTEGER NULL,
                    bank_installments_processed INTEGER NULL,
                    bank_negotiations_resolved INTEGER NULL,
                    bank_loan_decisions INTEGER NULL,
                    hr_recruitments_processed INTEGER NULL,
                    bankruptcy_processed INTEGER NULL,
                    bankruptcy_recovered INTEGER NULL,
                    players_processed INTEGER NULL,
                    wells_active INTEGER NULL,
                    total_production_bbl REAL NULL,
                    total_revenue_pln REAL NULL,
                    total_opex_pln REAL NULL,
                    disasters_triggered INTEGER NULL,
                    incidents_triggered INTEGER NULL,
                    contracts_processed INTEGER NULL,
                    contracts_revenue_pln REAL NULL,
                    contracts_penalties_pln REAL NULL,
                    module_stats_data TEXT NULL,
                    module_runs_data TEXT NULL
                )");
                $this->db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_ran_at ON tick_stats (ran_at, tick_sequence)');
                return;
            }
            if ($driver !== 'mysql') {
                return;
            }
            if ($this->db->inTransaction()) {
                return;
            }

            Database::addColumnIfMissing('tick_stats', 'contracts_processed', 'INT NULL DEFAULT NULL AFTER incidents_triggered');
            Database::addColumnIfMissing('tick_stats', 'contracts_revenue_pln', 'DECIMAL(16,2) NULL DEFAULT NULL AFTER contracts_processed');
            Database::addColumnIfMissing('tick_stats', 'contracts_penalties_pln', 'DECIMAL(16,2) NULL DEFAULT NULL AFTER contracts_revenue_pln');
            Database::addColumnIfMissing('tick_stats', 'tick_sequence', 'BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER ran_at');
            Database::addColumnIfMissing('tick_stats', 'module_stats_data', 'LONGTEXT NULL AFTER contracts_penalties_pln');
            Database::addColumnIfMissing('tick_stats', 'module_runs_data', 'LONGTEXT NULL AFTER module_stats_data');
            $this->normalizeTickSequenceColumn();

            if ($this->hasExpectedUniqueIndex()) {
                return;
            }

            $this->db->exec(
                "DELETE t1 FROM tick_stats t1
                   JOIN tick_stats t2
                     ON t1.ran_at = t2.ran_at
                    AND COALESCE(t1.tick_sequence, 0) = COALESCE(t2.tick_sequence, 0)
                    AND t1.id < t2.id"
            );
            if ($this->indexExists('idx_ran_at')) {
                $this->db->exec("ALTER TABLE tick_stats DROP INDEX idx_ran_at");
            }
            $this->db->exec("ALTER TABLE tick_stats ADD UNIQUE KEY idx_ran_at (ran_at, tick_sequence)");
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('TickStatsRepository', 'ensureSchema FAILED', $e);
            }
        }
    }

    /**
     * Zapisuje wiersz statystyk po zakonczeniu ticka.
     * Saves a stats row after tick completion.
     *
     * @param array<string, mixed> $stats
     */
    public function save(array $stats): void
    {
        $ranAt = $stats['ran_at'] ?? date('Y-m-d H:i:s');
        $tickSequence = max(0, (int)($stats['tick_sequence'] ?? 0));
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $insert = $driver === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE';

        $this->db->prepare("
            {$insert} INTO tick_stats (
                ran_at, tick_sequence, source, duration_ms,
                oil_price, trend_name, trend_new,
                bank_interest_processed, bank_installments_processed,
                bank_negotiations_resolved, bank_loan_decisions,
                hr_recruitments_processed,
                bankruptcy_processed, bankruptcy_recovered,
                players_processed, wells_active,
                total_production_bbl, total_revenue_pln, total_opex_pln,
                disasters_triggered, incidents_triggered,
                contracts_processed, contracts_revenue_pln, contracts_penalties_pln,
                module_stats_data, module_runs_data
            ) VALUES (
                :ran_at, :tick_sequence, :source, :duration_ms,
                :oil_price, :trend_name, :trend_new,
                :bank_interest_processed, :bank_installments_processed,
                :bank_negotiations_resolved, :bank_loan_decisions,
                :hr_recruitments_processed,
                :bankruptcy_processed, :bankruptcy_recovered,
                :players_processed, :wells_active,
                :total_production_bbl, :total_revenue_pln, :total_opex_pln,
                :disasters_triggered, :incidents_triggered,
                :contracts_processed, :contracts_revenue_pln, :contracts_penalties_pln,
                :module_stats_data, :module_runs_data
            )
        ")->execute([
            ':ran_at'                      => $ranAt,
            ':tick_sequence'               => $tickSequence,
            ':source'                      => $stats['source']                      ?? 'cron',
            ':duration_ms'                 => $stats['duration_ms']                 ?? null,
            ':oil_price'                   => $stats['oil_price']                   ?? null,
            ':trend_name'                  => $stats['trend_name']                  ?? null,
            ':trend_new'                   => !empty($stats['trend_new']) ? 1 : 0,
            ':bank_interest_processed'     => $stats['bank_interest_processed']     ?? null,
            ':bank_installments_processed' => $stats['bank_installments_processed'] ?? null,
            ':bank_negotiations_resolved'  => $stats['bank_negotiations_resolved']  ?? null,
            ':bank_loan_decisions'         => $stats['bank_loan_decisions']         ?? null,
            ':hr_recruitments_processed'   => $stats['hr_recruitments_processed']   ?? null,
            ':bankruptcy_processed'        => $stats['bankruptcy_processed']        ?? null,
            ':bankruptcy_recovered'        => $stats['bankruptcy_recovered']        ?? null,
            ':players_processed'           => $stats['players_processed']           ?? null,
            ':wells_active'                => $stats['wells_active']                ?? null,
            ':total_production_bbl'        => $stats['total_production_bbl']        ?? null,
            ':total_revenue_pln'           => $stats['total_revenue_pln']           ?? null,
            ':total_opex_pln'              => $stats['total_opex_pln']              ?? null,
            ':disasters_triggered'         => $stats['disasters_triggered']         ?? null,
            ':incidents_triggered'         => $stats['incidents_triggered']         ?? null,
            ':contracts_processed'         => $stats['contracts_processed']         ?? null,
            ':contracts_revenue_pln'       => $stats['contracts_revenue_pln']       ?? null,
            ':contracts_penalties_pln'     => $stats['contracts_penalties_pln']     ?? null,
            ':module_stats_data'           => $this->encodeJson($stats['module_stats_data'] ?? null),
            ':module_runs_data'            => $this->encodeJson($stats['module_runs_data'] ?? null),
        ]);
    }

    /**
     * Zwraca zagregowane statystyki z ostatnich 24h.
     * Returns aggregated stats from last 24h.
     *
     * @return array<string, mixed>|false
     */
    public function getSummary24h(): array|false
    {
        return $this->db->query("
            SELECT
                COUNT(*)                        AS tick_count,
                AVG(duration_ms)                AS avg_duration_ms,
                MAX(duration_ms)                AS max_duration_ms,
                SUM(players_processed)          AS total_players,
                SUM(wells_active)               AS total_wells,
                SUM(total_production_bbl)       AS total_bbl,
                SUM(total_revenue_pln)          AS total_revenue,
                SUM(contracts_processed)        AS total_contracts_processed,
                SUM(contracts_revenue_pln)      AS total_contracts_revenue,
                SUM(contracts_penalties_pln)    AS total_contracts_penalties,
                SUM(disasters_triggered)        AS total_disasters,
                SUM(incidents_triggered)        AS total_incidents,
                MAX(oil_price)                  AS price_max,
                MIN(oil_price)                  AS price_min,
                AVG(oil_price)                  AS price_avg
            FROM tick_stats
            WHERE ran_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ")->fetch();
    }

    /**
     * Usuwa wpisy starsze niz N dni.
     * Deletes entries older than N days.
     */
    public function cleanup(int $keepDays = 7): int
    {
        $keepDays = max(1, $keepDays);
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'mysql') {
            $stmt = $this->db->prepare("
                DELETE FROM tick_stats
                WHERE ran_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->bindValue(':days', $keepDays, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount();
        }

        $cutoff = (new DateTimeImmutable("-{$keepDays} days"))->format('Y-m-d H:i:s');
        $stmt = $this->db->prepare("
            DELETE FROM tick_stats
            WHERE ran_at < :cutoff
        ");
        $stmt->bindValue(':cutoff', $cutoff);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function countModuleStatsRows(): int
    {
        return (int)$this->db->query(
            'SELECT COUNT(*)
               FROM tick_stats
              WHERE module_stats_data IS NOT NULL OR module_runs_data IS NOT NULL'
        )->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public function recentModuleStatsRows(int $limit = 10, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $stmt = $this->db->prepare(
            "SELECT id, ran_at, tick_sequence, source, duration_ms, module_stats_data, module_runs_data
               FROM tick_stats
              WHERE module_stats_data IS NOT NULL OR module_runs_data IS NOT NULL
              ORDER BY id DESC
              LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function encodeJson(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    private function hasExpectedUniqueIndex(): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COLUMN_NAME, Non_unique, SEQ_IN_INDEX
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'tick_stats'
                AND INDEX_NAME = 'idx_ran_at'
              ORDER BY SEQ_IN_INDEX"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) !== 2) {
            return false;
        }
        return (int)$rows[0]['Non_unique'] === 0
            && (int)$rows[1]['Non_unique'] === 0
            && (string)$rows[0]['COLUMN_NAME'] === 'ran_at'
            && (string)$rows[1]['COLUMN_NAME'] === 'tick_sequence';
    }

    private function indexExists(string $indexName): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'tick_stats'
                AND INDEX_NAME = ?"
        );
        $stmt->execute([$indexName]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function normalizeTickSequenceColumn(): void
    {
        try {
            $this->db->exec('UPDATE tick_stats SET tick_sequence = 0 WHERE tick_sequence IS NULL');
            $this->db->exec('ALTER TABLE tick_stats MODIFY COLUMN tick_sequence BIGINT UNSIGNED NOT NULL DEFAULT 0');
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('TickStatsRepository', 'tick_sequence normalization FAILED', $e);
            }
        }
    }
}
