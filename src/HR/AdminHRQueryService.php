<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeSystemBootstrap.php';
require_once __DIR__ . '/EmployeeDialogueTemplateService.php';

final class AdminHRQueryService
{
    public function __construct(private readonly PDO $db)
    {
        EmployeeSystemBootstrap::ensure($db);
    }

    /** @return array<string,int> */
    public function dashboard(): array
    {
        return [
            'employees' => $this->count('employee_state', "relation_status <> 'inactive'"),
            'unhappy' => $this->count('employee_state', "relation_status IN ('unhappy','raise_requested','dispute')"),
            'raises' => $this->count('employee_raise_requests', "status IN ('open','postponed')"),
            'strikes' => $this->count('employee_strikes', "status IN ('threat','active','negotiating')"),
            'dialogues' => $this->count('employee_dialogue_templates', 'is_active = 1'),
            'assignments' => $this->count('employee_assignments', "status = 'active'"),
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int}
     */
    public function employees(array $filters, int $page): array
    {
        [$where, $params] = $this->employeeFilters($filters, 'es');
        $employeeName = $this->employeeNameExpression('es');
        $sql = "SELECT es.*,
                    {$employeeName} AS employee_name,
                    p.email AS player_email
                FROM employee_state es
                LEFT JOIN players p ON p.id=es.player_id
                {$where}
                ORDER BY es.updated_at DESC, es.id DESC";
        return $this->paginate($sql, $params, $page, 40);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int}
     */
    public function assignments(array $filters, int $page): array
    {
        $conditions = ['1=1'];
        $params = [];
        $this->addExact($conditions, $params, 'ea.player_id', $filters['player_id'] ?? null, true);
        $this->addExact($conditions, $params, 'ea.status', $filters['status'] ?? null);
        $this->addExact($conditions, $params, 'ea.target_type', $filters['target_type'] ?? null);
        $where = 'WHERE ' . implode(' AND ', $conditions);
        $employeeName = $this->employeeNameExpression('ea');
        $sql = "SELECT ea.*, p.email AS player_email,
                    {$employeeName} AS employee_name
                FROM employee_assignments ea
                LEFT JOIN players p ON p.id=ea.player_id
                {$where}
                ORDER BY ea.updated_at DESC, ea.id DESC";
        return $this->paginate($sql, $params, $page, 40);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int}
     */
    public function raises(array $filters, int $page): array
    {
        $conditions = ['1=1'];
        $params = [];
        $this->addExact($conditions, $params, 'rr.player_id', $filters['player_id'] ?? null, true);
        $this->addExact($conditions, $params, 'rr.status', $filters['status'] ?? null);
        $where = 'WHERE ' . implode(' AND ', $conditions);
        $sql = "SELECT rr.*, es.department_code, p.email AS player_email
                FROM employee_raise_requests rr
                LEFT JOIN employee_state es
                  ON es.player_id=rr.player_id AND es.source_type=rr.source_type AND es.source_id=rr.source_id
                LEFT JOIN players p ON p.id=rr.player_id
                {$where}
                ORDER BY rr.created_at DESC, rr.id DESC";
        return $this->paginate($sql, $params, $page, 40);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int}
     */
    public function strikes(array $filters, int $page): array
    {
        $conditions = ['1=1'];
        $params = [];
        $this->addExact($conditions, $params, 's.player_id', $filters['player_id'] ?? null, true);
        $this->addExact($conditions, $params, 's.department_code', $filters['department'] ?? null);
        $this->addExact($conditions, $params, 's.status', $filters['status'] ?? null);
        $where = 'WHERE ' . implode(' AND ', $conditions);
        $sql = "SELECT s.*, p.email AS player_email,
                    COUNT(DISTINCT sm.id) AS participant_count,
                    n.current_round, n.max_rounds, n.round_deadline_at,
                    n.status AS negotiation_status
                FROM employee_strikes s
                LEFT JOIN players p ON p.id=s.player_id
                LEFT JOIN employee_strike_members sm ON sm.strike_id=s.id AND sm.left_at IS NULL
                LEFT JOIN employee_strike_negotiations n ON n.strike_id=s.id
                {$where}
                GROUP BY s.id, p.email, n.current_round, n.max_rounds, n.round_deadline_at, n.status
                ORDER BY s.updated_at DESC, s.id DESC";
        return $this->paginate($sql, $params, $page, 30);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int}
     */
    public function events(array $filters, int $page): array
    {
        $conditions = ['1=1'];
        $params = [];
        $this->addExact($conditions, $params, 'ev.player_id', $filters['player_id'] ?? null, true);
        $this->addExact($conditions, $params, 'ev.event_key', $filters['event_key'] ?? null);
        $where = 'WHERE ' . implode(' AND ', $conditions);
        $sql = "SELECT ev.*, p.email AS player_email
                FROM employee_events ev
                LEFT JOIN players p ON p.id=ev.player_id
                {$where}
                ORDER BY ev.created_at DESC, ev.id DESC";
        return $this->paginate($sql, $params, $page, 50);
    }

    /** @return list<array<string,mixed>> */
    public function roleEffects(): array
    {
        return $this->db->query(
            'SELECT * FROM employee_role_effects ORDER BY specialization_code, target_scope, effect_key'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int}
     */
    public function dialogues(array $filters, int $page): array
    {
        $conditions = ['1=1'];
        $params = [];
        foreach (['context_key','department_code','tone'] as $field) {
            $this->addExact($conditions, $params, $field, $filters[$field] ?? null);
        }
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '') {
            $conditions[] = 'is_active = ?';
            $params[] = !empty($filters['is_active']) ? 1 : 0;
        }
        $sql = 'SELECT * FROM employee_dialogue_templates WHERE '
            . implode(' AND ', $conditions)
            . ' ORDER BY context_key, department_code, round_no, tone, id';
        return $this->paginate($sql, $params, $page, 30);
    }

    /** @return list<array<string,mixed>> */
    public function negotiationRounds(int $strikeId): array
    {
        if ($strikeId <= 0) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT round_no, raise_pct, bonus_per_member, counter_raise_pct,
                    counter_bonus_per_member, result, created_at
               FROM employee_strike_negotiation_rounds
              WHERE strike_id=?
              ORDER BY round_no ASC'
        );
        $stmt->execute([$strikeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function count(string $table, string $where): int
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    }

    private function employeeNameExpression(string $alias): string
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $boardName = $driver === 'sqlite'
            ? "bm.first_name || ' ' || bm.last_name"
            : "CONCAT(bm.first_name, ' ', bm.last_name)";
        $technicalName = $driver === 'sqlite'
            ? "ts.first_name || ' ' || ts.last_name"
            : "CONCAT(ts.first_name, ' ', ts.last_name)";
        return "CASE {$alias}.source_type
                    WHEN 'board_member' THEN
                        (SELECT {$boardName} FROM board_members bm WHERE bm.id={$alias}.source_id)
                    ELSE
                        (SELECT {$technicalName} FROM technical_staff ts WHERE ts.id={$alias}.source_id)
                END";
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:list<mixed>}
     */
    private function employeeFilters(array $filters, string $alias): array
    {
        $conditions = ['1=1'];
        $params = [];
        $this->addExact($conditions, $params, "{$alias}.player_id", $filters['player_id'] ?? null, true);
        $this->addExact($conditions, $params, "{$alias}.department_code", $filters['department'] ?? null);
        $this->addExact($conditions, $params, "{$alias}.relation_status", $filters['status'] ?? null);
        $this->addExact($conditions, $params, "{$alias}.source_type", $filters['source_type'] ?? null);
        return ['WHERE ' . implode(' AND ', $conditions), $params];
    }

    /**
     * @param list<string> $conditions
     * @param list<mixed> $params
     */
    private function addExact(array &$conditions, array &$params, string $column, mixed $value, bool $integer = false): void
    {
        if ($value === null || $value === '' || ($integer && (int)$value <= 0)) {
            return;
        }
        $conditions[] = "{$column} = ?";
        $params[] = $integer ? (int)$value : (string)$value;
    }

    /**
     * @param list<mixed> $params
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,pages:int}
     */
    private function paginate(string $sql, array $params, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $count = $this->db->prepare("SELECT COUNT(*) FROM ({$sql}) admin_hr_rows");
        $count->execute($params);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $stmt = $this->db->prepare($sql . ' LIMIT ? OFFSET ?');
        $position = 1;
        foreach ($params as $value) {
            $stmt->bindValue($position++, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue($position++, $perPage, PDO::PARAM_INT);
        $stmt->bindValue($position, ($page - 1) * $perPage, PDO::PARAM_INT);
        $stmt->execute();
        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }
}
