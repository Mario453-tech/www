<?php
declare(strict_types=1);

/**
 * Admin hub staffing diagnostics and configuration.
 * Diagnostyka i konfiguracja obsady hubow w panelu admina.
 */
trait AdminHubStaffingTrait
{
    /** @return array{enabled:bool,small:int,medium:int,large:int} */
    public function loadStaffingConfig(PDO $db): array
    {
        $config = [
            'enabled' => false,
            'small' => 1,
            'medium' => 2,
            'large' => 3,
        ];
        $keys = [
            'employee_hub_staffing_enabled',
            'employee_hub_staff_required_small',
            'employee_hub_staff_required_medium',
            'employee_hub_staff_required_large',
        ];
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $db->prepare(
            "SELECT `key`, `value`
               FROM well_config
              WHERE `key` IN ({$placeholders})"
        );
        $stmt->execute($keys);
        $values = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $values[(string)$row['key']] = (string)$row['value'];
        }

        if (isset($values['employee_hub_staffing_enabled'])) {
            $runtimeValue = strtolower(trim($values['employee_hub_staffing_enabled']));
            $config['enabled'] = is_numeric($runtimeValue)
                ? (float)$runtimeValue > 0.0
                : in_array($runtimeValue, ['true', 'yes', 'on'], true);
        }
        foreach (['small', 'medium', 'large'] as $type) {
            $key = 'employee_hub_staff_required_' . $type;
            if (isset($values[$key]) && is_numeric($values[$key])) {
                $config[$type] = max(1, min(10, (int)$values[$key]));
            }
        }

        return $config;
    }

    /** @param array{enabled:bool,small:int,medium:int,large:int} $config */
    public function saveStaffingConfig(PDO $db, array $config): void
    {
        $stmt = $db->prepare(
            'INSERT INTO well_config (`key`, `value`, `label`, `category`)
             VALUES (:config_key, :config_value, :label, :category)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
        );
        $values = [
            'employee_hub_staffing_enabled' => $config['enabled'] ? '1' : '0',
            'employee_hub_staff_required_small' => (string)max(1, min(10, $config['small'])),
            'employee_hub_staff_required_medium' => (string)max(1, min(10, $config['medium'])),
            'employee_hub_staff_required_large' => (string)max(1, min(10, $config['large'])),
        ];
        foreach ($values as $key => $value) {
            $stmt->execute([
                'config_key' => $key,
                'config_value' => $value,
                'label' => $key,
                'category' => 'employees',
            ]);
        }
    }

    /**
     * @param list<array<string,mixed>> $hubs
     * @param array{player_id:int,hub_id:int,employee:string,status:string,page:int} $filters
     * @return array<string,mixed>
     */
    public function buildStaffingDiagnostics(PDO $db, array $hubs, array $filters, int $perPage = 10): array
    {
        $controlledHubs = array_values(array_filter(
            $hubs,
            static fn(array $hub): bool => (int)($hub['tenant_player_id'] ?? 0) > 0
                || (int)($hub['player_id'] ?? 0) > 0
        ));
        $staffingByHub = (new LogisticsStaffingService($db))->hubStaffingForHubs($controlledHubs);
        $coverageRows = [];
        foreach ($controlledHubs as $hub) {
            $hubId = (int)$hub['id'];
            if (!isset($staffingByHub[$hubId])) {
                continue;
            }
            $coverageRows[] = array_merge($staffingByHub[$hubId], [
                'hub_name' => (string)($hub['name'] ?? ('#' . $hubId)),
                'hub_type' => (string)($hub['hub_type'] ?? ''),
                'region_name' => (string)($hub['region_name'] ?? ''),
            ]);
        }
        usort($coverageRows, static function (array $left, array $right): int {
            return [(float)$left['coverage_pct'], (int)$left['hub_id']]
                <=> [(float)$right['coverage_pct'], (int)$right['hub_id']];
        });

        $totalCoverage = array_sum(array_column($coverageRows, 'coverage_pct'));
        $summary = [
            'controlled_hubs' => count($coverageRows),
            'fully_staffed' => count(array_filter($coverageRows, static fn(array $row): bool => (float)$row['coverage_pct'] >= 100.0)),
            'understaffed' => count(array_filter($coverageRows, static fn(array $row): bool => (float)$row['coverage_pct'] > 0.0 && (float)$row['coverage_pct'] < 100.0)),
            'unstaffed' => count(array_filter($coverageRows, static fn(array $row): bool => (float)$row['coverage_pct'] <= 0.0)),
            'average_coverage' => $coverageRows === [] ? 0.0 : round($totalCoverage / count($coverageRows), 1),
        ];

        [$whereSql, $params] = $this->staffingAssignmentFilter($filters);
        $countStmt = $db->prepare(
            "SELECT COUNT(*)
               FROM employee_assignments ea
               JOIN logistics_hubs h ON h.id = ea.target_id AND ea.target_type = 'hub'
               {$whereSql}"
        );
        $countStmt->execute($params);
        $totalAssignments = (int)$countStmt->fetchColumn();
        $page = max(1, $filters['page']);
        $totalPages = max(1, (int)ceil($totalAssignments / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT ea.*,
                       h.name AS hub_name,
                       p.username,
                       p.company_name,
                       CASE
                           WHEN ea.source_type = 'board_member' THEN CONCAT(bm.first_name, ' ', bm.last_name)
                           WHEN ea.source_type = 'technical_staff' THEN CONCAT(ts.first_name, ' ', ts.last_name)
                           ELSE CONCAT('#', ea.source_id)
                       END AS employee_name
                  FROM employee_assignments ea
                  JOIN logistics_hubs h ON h.id = ea.target_id AND ea.target_type = 'hub'
             LEFT JOIN players p ON p.id = ea.player_id
             LEFT JOIN board_members bm
                    ON ea.source_type = 'board_member' AND bm.id = ea.source_id AND bm.player_id = ea.player_id
             LEFT JOIN technical_staff ts
                    ON ea.source_type = 'technical_staff' AND ts.id = ea.source_id AND ts.player_id = ea.player_id
                  {$whereSql}
              ORDER BY (ea.status = 'active') DESC, ea.updated_at DESC, ea.id DESC
                 LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'summary' => $summary,
            'coverage_rows' => array_slice($coverageRows, 0, 10),
            'assignments' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'assignments_total' => $totalAssignments,
            'page' => $page,
            'total_pages' => $totalPages,
        ];
    }

    /**
     * @param array{player_id:int,hub_id:int,employee:string,status:string,page:int} $filters
     * @return array{0:string,1:array<string,int|string>}
     */
    private function staffingAssignmentFilter(array $filters): array
    {
        $conditions = ["ea.target_type = 'hub'"];
        $params = [];
        if ($filters['player_id'] > 0) {
            $conditions[] = 'ea.player_id = :staff_player_id';
            $params['staff_player_id'] = $filters['player_id'];
        }
        if ($filters['hub_id'] > 0) {
            $conditions[] = 'ea.target_id = :staff_hub_id';
            $params['staff_hub_id'] = $filters['hub_id'];
        }
        if (in_array($filters['status'], ['active', 'released'], true)) {
            $conditions[] = 'ea.status = :staff_status';
            $params['staff_status'] = $filters['status'];
        }
        if ($filters['employee'] !== '') {
            $conditions[] = "(
                (ea.source_type = 'board_member' AND EXISTS (
                    SELECT 1 FROM board_members bm_filter
                     WHERE bm_filter.id = ea.source_id
                       AND bm_filter.player_id = ea.player_id
                       AND CONCAT(bm_filter.first_name, ' ', bm_filter.last_name) LIKE :staff_employee_board
                ))
                OR
                (ea.source_type = 'technical_staff' AND EXISTS (
                    SELECT 1 FROM technical_staff ts_filter
                     WHERE ts_filter.id = ea.source_id
                       AND ts_filter.player_id = ea.player_id
                       AND CONCAT(ts_filter.first_name, ' ', ts_filter.last_name) LIKE :staff_employee_technical
                ))
            )";
            $params['staff_employee_board'] = '%' . $filters['employee'] . '%';
            $params['staff_employee_technical'] = '%' . $filters['employee'] . '%';
        }

        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }
}
