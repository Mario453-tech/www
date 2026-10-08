<?php
declare(strict_types=1);

final class HomeDashboardQuery
{
    private const PERIOD_DAYS = ['7d' => 7, '30d' => 30, '1y' => 365];

    public function __construct(private PDO $db)
    {
    }

    public static function normalizePeriod(string $period): string
    {
        return array_key_exists($period, self::PERIOD_DAYS) ? $period : '30d';
    }

    /** @param list<array<string, mixed>> $wells
     *  @return array<string, mixed>
     */
    public function get(int $playerId, string $period, array $wells): array
    {
        $period = self::normalizePeriod($period);
        $days = self::PERIOD_DAYS[$period];
        $now = new DateTimeImmutable();
        $start = $now->modify('-' . $days . ' days');
        $previousStart = $start->modify('-' . $days . ' days');
        $wellData = self::summarizeWells($wells);

        try {
            $current = $this->aggregate($playerId, $start, $now);
            $previous = $this->aggregate($playerId, $previousStart, $start);
            $series = $this->series($playerId, $start, $now, $period);
        } catch (Throwable $e) {
            GameLog::error('HomeDashboardQuery', 'Statistics loading failed', $e, ['player_id' => $playerId]);
            return ['period' => $period, 'state' => 'error', 'wells' => $wellData, 'series' => []];
        }

        $hasHistory = (int)($current['tick_count'] ?? 0) > 0;
        $currentHours = self::observedHours($current);
        $previousHours = self::observedHours($previous);
        $productionRate = $hasHistory ? (float)$current['production'] / $currentHours : null;
        $revenueRate = $hasHistory ? (float)$current['revenue'] / $currentHours : null;
        $priorProduction = $previousHours > 0 ? (float)($previous['production'] ?? 0) / $previousHours : null;
        $priorRevenue = $previousHours > 0 ? (float)($previous['revenue'] ?? 0) / $previousHours : null;
        $cost = (float)($current['opex'] ?? 0) + (float)($current['salary'] ?? 0)
            + (float)($current['transport'] ?? 0) + (float)($current['hub'] ?? 0)
            + (float)($current['incident'] ?? 0) + (float)($current['tax'] ?? 0);

        $model = [
            'period' => $period,
            'state' => $hasHistory ? 'ready' : 'empty',
            'overview' => [
                'production_rate' => $productionRate,
                'revenue_rate' => $revenueRate,
                'production_change' => self::percentageChange($productionRate, $priorProduction),
                'revenue_change' => self::percentageChange($revenueRate, $priorRevenue),
                'active_wells' => $wellData['active'],
            ],
            'series' => $series,
            'chart_points' => self::chartPoints($series),
            'wells' => $wellData,
            'logistics' => [
                'mix' => $wellData['transport_mix'],
                'active_routes' => $wellData['active_routes'],
                'loss_bbl' => $hasHistory ? (float)$current['loss_bbl'] : null,
                'cost_rate' => $hasHistory ? ((float)$current['transport'] + (float)$current['hub']) / $currentHours : null,
                'on_time_pct' => null,
            ],
            'finance' => [
                'revenue_rate' => $revenueRate,
                'cost_rate' => $hasHistory ? $cost / $currentHours : null,
                'net_rate' => $hasHistory ? (float)$current['net'] / $currentHours : null,
                'costs' => [
                    'extraction' => (float)($current['opex'] ?? 0),
                    'logistics' => (float)($current['transport'] ?? 0) + (float)($current['hub'] ?? 0),
                    'staff' => (float)($current['salary'] ?? 0),
                    'other' => (float)($current['incident'] ?? 0) + (float)($current['tax'] ?? 0),
                ],
            ],
        ];

        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN status = 'delivered' AND arrived_at <= eta_at THEN 1 ELSE 0 END) AS on_time
                   FROM well_road_trips
                  WHERE player_id = ? AND status = 'delivered' AND arrived_at >= ? AND arrived_at < ?"
            );
            $stmt->execute([$playerId, $start->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]);
            $delivery = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            if ((int)($delivery['total'] ?? 0) > 0) {
                $model['logistics']['on_time_pct'] = round(100 * (int)$delivery['on_time'] / (int)$delivery['total'], 1);
            }
        } catch (Throwable $e) {
            GameLog::error('HomeDashboardQuery', 'Road delivery statistics unavailable', $e, ['player_id' => $playerId]);
        }

        return $model;
    }

    /** @return array<string, mixed> */
    private function aggregate(int $playerId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS tick_count, MIN(tick_at) AS first_tick, MAX(tick_at) AS last_tick,
                    COALESCE(SUM(produced_bbl), 0) AS production,
                    COALESCE(SUM(revenue), 0) AS revenue,
                    COALESCE(SUM(net_profit), 0) AS net,
                    COALESCE(SUM(opex), 0) AS opex,
                    COALESCE(SUM(salary_cost), 0) AS salary,
                    COALESCE(SUM(transport_cost), 0) AS transport,
                    COALESCE(SUM(hub_usage_cost), 0) AS hub,
                    COALESCE(SUM(incident_cost), 0) AS incident,
                    COALESCE(SUM(tax), 0) AS tax,
                    COALESCE(SUM(transport_loss_bbl), 0) AS loss_bbl
               FROM finance_logs
              WHERE player_id = ? AND tick_at >= ? AND tick_at < ?"
        );
        $stmt->execute([$playerId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array{label:string,value:float}> */
    private function series(int $playerId, DateTimeImmutable $start, DateTimeImmutable $end, string $period): array
    {
        $bucket = $period === '1y' ? "DATE_FORMAT(tick_at, '%Y-%m')" : 'DATE(tick_at)';
        $stmt = $this->db->prepare(
            "SELECT {$bucket} AS bucket, SUM(produced_bbl) AS produced,
                    MIN(tick_at) AS first_tick, MAX(tick_at) AS last_tick, COUNT(*) AS tick_count
               FROM finance_logs
              WHERE player_id = ? AND tick_at >= ? AND tick_at < ?
              GROUP BY {$bucket} ORDER BY bucket ASC"
        );
        $stmt->execute([$playerId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = [
                'label' => (string)$row['bucket'],
                'value' => round((float)$row['produced'] / self::observedHours($row), 2),
            ];
        }
        return $rows;
    }

    /** @param array<string, mixed> $row */
    private static function observedHours(array $row): float
    {
        if ((int)($row['tick_count'] ?? 0) === 0) return 0.0;
        $first = strtotime((string)($row['first_tick'] ?? ''));
        $last = strtotime((string)($row['last_tick'] ?? ''));
        return max(1 / 12, (($last ?: 0) - ($first ?: 0) + 300) / 3600);
    }

    private static function percentageChange(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null || $previous <= 0) return null;
        return round(($current - $previous) / $previous * 100, 1);
    }

    /** @param list<array{label:string,value:float}> $series */
    private static function chartPoints(array $series): string
    {
        if ($series === []) return '';
        $max = max(1.0, max(array_column($series, 'value')));
        $last = max(1, count($series) - 1);
        $points = [];
        foreach ($series as $index => $point) {
            $x = 24 + 592 * $index / $last;
            $y = 180 - 150 * $point['value'] / $max;
            $points[] = round($x, 1) . ',' . round($y, 1);
        }
        return implode(' ', $points);
    }

    /** @param list<array<string, mixed>> $wells
     *  @return array<string, mixed>
     */
    private static function summarizeWells(array $wells): array
    {
        $summary = ['total' => 0, 'active' => 0, 'attention' => 0, 'critical' => 0,
            'top' => [], 'transport_mix' => ['road' => 0, 'pipeline' => 0, 'sea' => 0], 'active_routes' => 0];
        foreach ($wells as $well) {
            $status = (string)($well['status'] ?? '');
            if (in_array($status, ['sold', 'seized'], true)) continue;
            $condition = (float)($well['technical_condition'] ?? 100);
            $summary['total']++;
            if ($status === 'active' && $condition > 1) $summary['active']++;
            if ($condition < 50 || in_array($status, ['broken', 'blowout', 'contaminated'], true)) $summary['attention']++;
            if ($condition < 30 || in_array($status, ['broken', 'blowout', 'contaminated'], true)) $summary['critical']++;
            $transport = (string)($well['transport_type'] ?? '');
            $transportKey = ['ciezarowki' => 'road', 'rurociag' => 'pipeline', 'tankowiec' => 'sea'][$transport] ?? null;
            if ($transportKey !== null && $status === 'active') {
                $summary['transport_mix'][$transportKey]++;
                $summary['active_routes']++;
            }
            if ($status !== 'active') continue;
            $summary['top'][] = [
                'id' => (int)$well['id'],
                'name' => (string)(!empty($well['well_name']) ? $well['well_name'] : ($well['location_name'] ?? ('#' . $well['id']))),
                'production' => (float)($well['base_production_per_hour'] ?? 0),
                'condition' => $condition,
            ];
        }
        usort($summary['top'], static fn(array $a, array $b): int => $b['production'] <=> $a['production']);
        $summary['top'] = array_slice($summary['top'], 0, 5);
        return $summary;
    }
}
