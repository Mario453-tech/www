<?php

/**
 * TickStatsRepository zapis i odczyt statystyk tickow gry.
 * Tick stats repository save and read game tick statistics.
 */
class TickStatsRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

 /**
 * Zapisuje wiersz statystyk po zakonczeniu ticka.
 * Saves a stats row after tick completion.
 */
 /** @param array<string, mixed> $stats */
    public function save(array $stats): void
    {
        $this->db->prepare("
            INSERT INTO tick_stats (
                ran_at, source, duration_ms,
                oil_price, trend_name, trend_new,
                bank_interest_processed, bank_installments_processed,
                bank_negotiations_resolved, bank_loan_decisions,
                hr_recruitments_processed,
                bankruptcy_processed, bankruptcy_recovered,
                players_processed, wells_active,
                total_production_bbl, total_revenue_pln, total_opex_pln,
                disasters_triggered, incidents_triggered
            ) VALUES (
                :ran_at, :source, :duration_ms,
                :oil_price, :trend_name, :trend_new,
                :bank_interest_processed, :bank_installments_processed,
                :bank_negotiations_resolved, :bank_loan_decisions,
                :hr_recruitments_processed,
                :bankruptcy_processed, :bankruptcy_recovered,
                :players_processed, :wells_active,
                :total_production_bbl, :total_revenue_pln, :total_opex_pln,
                :disasters_triggered, :incidents_triggered
            )
        ")->execute([
            ':ran_at'                      => $stats['ran_at']                      ?? date('Y-m-d H:i:s'),
            ':source'                      => $stats['source']                      ?? 'cron',
            ':duration_ms'                 => $stats['duration_ms']                 ?? null,
            ':oil_price'                   => $stats['oil_price']                   ?? null,
            ':trend_name'                  => $stats['trend_name']                  ?? null,
            ':trend_new'                   => $stats['trend_new']                   ? 1 : 0,
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
        ]);
    }

 /**
 * Zwraca ostatnie N tickow posortowane malejaco.
 * Returns last N ticks sorted descending.
 */
 /** @return list<array<string, mixed>> */
    public function getRecent(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM tick_stats
            ORDER BY ran_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

 /**
 * Zwraca zagregowane statystyki z ostatnich 24h.
 * Returns aggregated stats from last 24h.
 */
 /** @return array<string, mixed>|false */
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
 * Usuwa wpisy starsze niz N dni (cleanup).
 * Deletes entries older than N days (cleanup).
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
