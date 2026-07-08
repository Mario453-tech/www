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

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
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
            if ($driver !== 'mysql') {
                return;
            }
            if ($this->db->inTransaction()) {
                return;
            }

            $stmt = $this->db->query(
                "SELECT Non_unique FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'tick_stats'
                    AND INDEX_NAME   = 'idx_ran_at'
                  LIMIT 1"
            );
            $nonUnique = $stmt ? $stmt->fetchColumn() : null;

            Database::addColumnIfMissing('tick_stats', 'contracts_processed', 'INT NULL DEFAULT NULL AFTER incidents_triggered');
            Database::addColumnIfMissing('tick_stats', 'contracts_revenue_pln', 'DECIMAL(16,2) NULL DEFAULT NULL AFTER contracts_processed');
            Database::addColumnIfMissing('tick_stats', 'contracts_penalties_pln', 'DECIMAL(16,2) NULL DEFAULT NULL AFTER contracts_revenue_pln');

            if ($nonUnique === false || $nonUnique === null) {
                $this->db->exec("ALTER TABLE tick_stats ADD UNIQUE KEY idx_ran_at (ran_at)");
                return;
            }
            if ((int)$nonUnique === 0) {
                return;
            }

            $this->db->exec(
                "DELETE t1 FROM tick_stats t1
                   JOIN tick_stats t2 ON t1.ran_at = t2.ran_at AND t1.id < t2.id"
            );
            $this->db->exec("ALTER TABLE tick_stats DROP INDEX idx_ran_at");
            $this->db->exec("ALTER TABLE tick_stats ADD UNIQUE KEY idx_ran_at (ran_at)");
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

        $this->db->prepare("
            INSERT IGNORE INTO tick_stats (
                ran_at, source, duration_ms,
                oil_price, trend_name, trend_new,
                bank_interest_processed, bank_installments_processed,
                bank_negotiations_resolved, bank_loan_decisions,
                hr_recruitments_processed,
                bankruptcy_processed, bankruptcy_recovered,
                players_processed, wells_active,
                total_production_bbl, total_revenue_pln, total_opex_pln,
                disasters_triggered, incidents_triggered,
                contracts_processed, contracts_revenue_pln, contracts_penalties_pln
            ) VALUES (
                :ran_at, :source, :duration_ms,
                :oil_price, :trend_name, :trend_new,
                :bank_interest_processed, :bank_installments_processed,
                :bank_negotiations_resolved, :bank_loan_decisions,
                :hr_recruitments_processed,
                :bankruptcy_processed, :bankruptcy_recovered,
                :players_processed, :wells_active,
                :total_production_bbl, :total_revenue_pln, :total_opex_pln,
                :disasters_triggered, :incidents_triggered,
                :contracts_processed, :contracts_revenue_pln, :contracts_penalties_pln
            )
        ")->execute([
            ':ran_at'                      => $ranAt,
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
        $stmt = $this->db->prepare("
            DELETE FROM tick_stats
            WHERE ran_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->bindValue(':days', $keepDays, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
