<?php
declare(strict_types=1);

// Read-only history; schema maintenance belongs to the tick writer.
// Historia tylko do odczytu; utrzymanie schematu nalezy do zapisu ticka.
final class TickHistoryQuery
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array{rows:list<array<string,mixed>>,total:int,pages:int,page:int} */
    public function page(string $source, int $page, int $perPage = 50): array
    {
        $source = in_array($source, ['cron', 'force', 'cron_http'], true) ? $source : '';
        $where = $source !== '' ? 'WHERE source = :source' : '';
        $params = $source !== '' ? ['source' => $source] : [];
        $count = $this->db->prepare("SELECT COUNT(*) FROM tick_stats {$where}");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $perPage = max(1, min(100, $perPage));
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));

        // Do not transfer large diagnostic JSON documents for a summary list.
        // Nie przesylaj duzych dokumentow diagnostycznych JSON dla listy podsumowan.
        $list = $this->db->prepare("SELECT
            id, ran_at, tick_sequence, source, duration_ms, oil_price,
            trend_name, trend_new, players_processed, total_production_bbl,
            total_revenue_pln, disasters_triggered, incidents_triggered,
            bank_negotiations_resolved, bank_loan_decisions
            FROM tick_stats {$where}
            ORDER BY ran_at DESC, tick_sequence DESC, id DESC
            LIMIT :lim OFFSET :off");
        if ($source !== '') {
            $list->bindValue(':source', $source);
        }
        $list->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $list->bindValue(':off', ($page - 1) * $perPage, PDO::PARAM_INT);
        $list->execute();

        return ['rows' => $list->fetchAll(PDO::FETCH_ASSOC), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /** @return array<string,mixed>|false */
    public function summary24h(): array|false
    {
        $since = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "datetime('now', '-24 hours')"
            : 'DATE_SUB(NOW(), INTERVAL 24 HOUR)';
        return $this->db->query("SELECT
            COUNT(*) AS tick_count,
            AVG(duration_ms) AS avg_duration_ms,
            MAX(duration_ms) AS max_duration_ms,
            SUM(players_processed) AS total_players,
            SUM(wells_active) AS total_wells,
            SUM(total_production_bbl) AS total_bbl,
            SUM(total_revenue_pln) AS total_revenue,
            SUM(contracts_processed) AS total_contracts_processed,
            SUM(contracts_revenue_pln) AS total_contracts_revenue,
            SUM(contracts_penalties_pln) AS total_contracts_penalties,
            SUM(disasters_triggered) AS total_disasters,
            SUM(incidents_triggered) AS total_incidents,
            MAX(oil_price) AS price_max,
            MIN(oil_price) AS price_min,
            AVG(oil_price) AS price_avg
            FROM tick_stats WHERE ran_at >= {$since}")->fetch(PDO::FETCH_ASSOC);
    }
}
