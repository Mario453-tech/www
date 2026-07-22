<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/EmployeeSystemBootstrap.php';

final class EmployeeDialogueTemplateService
{
    public const CONTEXTS = [
        'dissatisfaction_started','raise_requested','dispute_started','strike_threat',
        'strike_started','round_opening','offer_very_low','offer_weak','offer_near',
        'offer_good','counteroffer','accepted','rejected','expired','final_failure',
        'settlement_signed','return_to_work',
    ];
    public const TONES = ['calm','formal','firm','disappointed','angry','conciliatory','exhausted'];
    public const PLACEHOLDERS = [
        'employee_name','department','round','max_rounds','morale','support_pct',
        'raise_pct','bonus','counter_raise_pct','counter_bonus','deadline',
        'participant_count','company_name',
    ];

    public function __construct(private readonly PDO $db)
    {
        EmployeeSystemBootstrap::ensure($db);
        $this->seedDefaults();
    }

    /** @return list<array<string,mixed>> */
    public function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        foreach (['context_key','department_code','tone'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $where[] = $field . ' = ?';
                $params[] = $filters[$field];
            }
        }
        if (isset($filters['round_no']) && (int)$filters['round_no'] > 0) {
            $where[] = 'round_no = ?';
            $params[] = (int)$filters['round_no'];
        }
        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '') {
            $where[] = 'is_active = ?';
            $params[] = !empty($filters['is_active']) ? 1 : 0;
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM employee_dialogue_templates WHERE ' . implode(' AND ', $where)
            . ' ORDER BY context_key, department_code, round_no, tone, id LIMIT 500'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @param array<string,mixed> $data */
    public function save(array $data, ?int $id = null): int
    {
        $row = $this->validate($data);
        if ($id !== null && $id > 0) {
            $stmt = $this->db->prepare(
                'UPDATE employee_dialogue_templates SET context_key=?, department_code=?, round_no=?,
                    tone=?, text_pl=?, text_en=?, weight=?, is_active=?, updated_at=CURRENT_TIMESTAMP
                  WHERE id=?'
            );
            $stmt->execute([
                $row['context_key'],$row['department_code'],$row['round_no'],$row['tone'],
                $row['text_pl'],$row['text_en'],$row['weight'],$row['is_active'],$id,
            ]);
            if ($stmt->rowCount() < 1 && !$this->exists($id)) {
                throw new RuntimeException('Dialogue template does not exist.');
            }
            return $id;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO employee_dialogue_templates
                (context_key, department_code, round_no, tone, text_pl, text_en, weight, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $row['context_key'],$row['department_code'],$row['round_no'],$row['tone'],
            $row['text_pl'],$row['text_en'],$row['weight'],$row['is_active'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function duplicate(int $id): int
    {
        $stmt = $this->db->prepare('SELECT * FROM employee_dialogue_templates WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Dialogue template does not exist.');
        }
        unset($row['id'], $row['seed_key'], $row['created_at'], $row['updated_at']);
        return $this->save($row);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = $this->db->prepare(
            'UPDATE employee_dialogue_templates SET is_active=?, updated_at=CURRENT_TIMESTAMP WHERE id=?'
        );
        $stmt->execute([$active ? 1 : 0, $id]);
        if ($stmt->rowCount() < 1 && !$this->exists($id)) {
            throw new RuntimeException('Dialogue template does not exist.');
        }
    }

    /** @return array<string,mixed>|null */
    public function choose(
        string $context,
        ?string $department,
        ?int $round,
        ?string $tone,
        int $strikeId
    ): ?array {
        if (!in_array($context, self::CONTEXTS, true)) {
            throw new InvalidArgumentException('Unknown dialogue context.');
        }
        $used = $this->usedTemplateIds($strikeId);
        $rows = $this->candidates($context, $department, $round, $tone, true);
        if ($rows === []) {
            $rows = $this->candidates($context, null, $round, $tone, false);
        }
        $unused = array_values(array_filter(
            $rows,
            static fn(array $row): bool => !isset($used[(int)$row['id']])
        ));
        return $this->weightedPick($unused !== [] ? $unused : $rows);
    }

    /** @param array<string,mixed> $values */
    public function render(array $template, string $language, array $values): string
    {
        $field = $language === 'en' ? 'text_en' : 'text_pl';
        $text = (string)($template[$field] ?? '');
        foreach ($values as $key => $value) {
            if (in_array((string)$key, self::PLACEHOLDERS, true)) {
                $text = str_replace('{' . $key . '}', (string)$value, $text);
            }
        }
        return $text;
    }

    public function restoreSeededDefaults(): void
    {
        $this->db->exec("DELETE FROM employee_dialogue_templates WHERE seed_key IS NOT NULL");
        $this->seedDefaults();
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function validate(array $data): array
    {
        $context = (string)($data['context_key'] ?? '');
        $tone = (string)($data['tone'] ?? '');
        $textPl = trim((string)($data['text_pl'] ?? ''));
        $textEn = trim((string)($data['text_en'] ?? ''));
        if (!in_array($context, self::CONTEXTS, true) || !in_array($tone, self::TONES, true)) {
            throw new InvalidArgumentException('Dialogue context or tone is invalid.');
        }
        if ($textPl === '' || $textEn === '' || $textPl !== strip_tags($textPl) || $textEn !== strip_tags($textEn)) {
            throw new InvalidArgumentException('Dialogue texts must be non-empty plain text in both languages.');
        }
        $this->validatePlaceholders($textPl);
        $this->validatePlaceholders($textEn);
        $round = isset($data['round_no']) && (int)$data['round_no'] > 0 ? (int)$data['round_no'] : null;
        if ($round !== null && ($round < 1 || $round > 5)) {
            throw new InvalidArgumentException('Dialogue round is outside the allowed range.');
        }
        $weight = (float)($data['weight'] ?? 1);
        if ($weight <= 0 || $weight > 1000) {
            throw new InvalidArgumentException('Dialogue weight must be positive.');
        }
        return [
            'context_key'=>$context,
            'department_code'=>trim((string)($data['department_code'] ?? '')) ?: null,
            'round_no'=>$round,
            'tone'=>$tone,
            'text_pl'=>$textPl,
            'text_en'=>$textEn,
            'weight'=>$weight,
            'is_active'=>!empty($data['is_active']) ? 1 : 0,
        ];
    }

    private function validatePlaceholders(string $text): void
    {
        preg_match_all('/\{([a-z_]+)\}/', $text, $matches);
        foreach ($matches[1] as $placeholder) {
            if (!in_array($placeholder, self::PLACEHOLDERS, true)) {
                throw new InvalidArgumentException('Unknown dialogue placeholder: ' . $placeholder);
            }
        }
        if (substr_count($text, '{') !== substr_count($text, '}')) {
            throw new InvalidArgumentException('Dialogue placeholder braces are not balanced.');
        }
    }

    /** @return list<array<string,mixed>> */
    private function candidates(string $context, ?string $department, ?int $round, ?string $tone, bool $specific): array
    {
        $where = ['context_key=?', 'is_active=1'];
        $params = [$context];
        if ($specific && $department !== null && $department !== '') {
            $where[] = 'department_code=?';
            $params[] = $department;
        } else {
            $where[] = 'department_code IS NULL';
        }
        if ($round !== null) {
            $where[] = '(round_no=? OR round_no IS NULL)';
            $params[] = $round;
        } else {
            $where[] = 'round_no IS NULL';
        }
        if ($tone !== null && $tone !== '') {
            $where[] = '(tone=? OR tone=? OR tone=?)';
            array_push($params, $tone, 'formal', 'calm');
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM employee_dialogue_templates WHERE ' . implode(' AND ', $where)
            . ' ORDER BY CASE WHEN round_no IS NULL THEN 1 ELSE 0 END, id'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,true> */
    private function usedTemplateIds(int $strikeId): array
    {
        if ($strikeId <= 0) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT dialogue_template_id FROM employee_strike_negotiation_rounds
              WHERE strike_id=? AND dialogue_template_id IS NOT NULL
             UNION SELECT dialogue_template_id FROM employee_events
              WHERE strike_id=? AND dialogue_template_id IS NOT NULL'
        );
        $stmt->execute([$strikeId, $strikeId]);
        return array_fill_keys(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    /** @param list<array<string,mixed>> $rows @return array<string,mixed>|null */
    private function weightedPick(array $rows): ?array
    {
        if ($rows === []) {
            return null;
        }
        $total = array_sum(array_map(static fn(array $row): int => max(1, (int)round((float)$row['weight'] * 1000)), $rows));
        $roll = random_int(1, max(1, $total));
        foreach ($rows as $row) {
            $roll -= max(1, (int)round((float)$row['weight'] * 1000));
            if ($roll <= 0) {
                return $row;
            }
        }
        return $rows[array_key_last($rows)];
    }

    private function exists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM employee_dialogue_templates WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        return (bool)$stmt->fetchColumn();
    }

    private function seedDefaults(): void
    {
        $contexts = [
            'dissatisfaction_started'=>['Narasta niezadowolenie z warunków pracy.','Dissatisfaction with working conditions is growing.'],
            'raise_requested'=>['Oczekujemy rozmowy o wynagrodzeniu.','We expect a discussion about compensation.'],
            'dispute_started'=>['Brak porozumienia przerodził się w spór.','The lack of agreement has become a dispute.'],
            'strike_threat'=>['Załoga rozważa rozpoczęcie strajku.','The workforce is considering strike action.'],
            'strike_started'=>['Pracownicy wstrzymali wykonywanie obowiązków.','Employees have stopped performing their duties.'],
            'round_opening'=>['Rozpoczynamy rundę {round} z {max_rounds}.','We are opening round {round} of {max_rounds}.'],
            'offer_very_low'=>['Ta oferta jest zdecydowanie zbyt niska.','This offer is far too low.'],
            'offer_weak'=>['Oferta nie odpowiada skali naszych oczekiwań.','The offer does not match the scale of our expectations.'],
            'offer_near'=>['Jesteśmy blisko rozwiązania, ale potrzebna jest korekta.','We are close to a solution, but an adjustment is needed.'],
            'offer_good'=>['Oferta stanowi solidną podstawę porozumienia.','The offer provides a solid basis for agreement.'],
            'counteroffer'=>['Przedstawiamy kontrofertę: {counter_raise_pct}% i {counter_bonus}.','We propose a counteroffer: {counter_raise_pct}% and {counter_bonus}.'],
            'accepted'=>['Załoga przyjmuje przedstawione warunki.','The workforce accepts the proposed terms.'],
            'rejected'=>['Załoga odrzuca przedstawione warunki.','The workforce rejects the proposed terms.'],
            'expired'=>['Termin odpowiedzi minął bez porozumienia.','The response deadline passed without agreement.'],
            'final_failure'=>['Ostatnia runda zakończyła się bez ugody.','The final round ended without a settlement.'],
            'settlement_signed'=>['Ugoda została podpisana przez obie strony.','The settlement has been signed by both parties.'],
            'return_to_work'=>['Pracownicy wracają do wykonywania obowiązków.','Employees are returning to work.'],
        ];
        $prefixPl = ['Spokojnie informujemy: ','Stanowisko zespołu jest jasne: ','Po analizie sytuacji: ','W imieniu pracowników: '];
        $prefixEn = ['We calmly state: ','The team position is clear: ','After reviewing the situation: ','On behalf of the employees: '];
        $tones = ['calm','formal','firm','disappointed'];
        $rows = [];
        foreach ($contexts as $context => $text) {
            for ($variant=1; $variant<=4; $variant++) {
                $rows[] = [
                    'generic.' . $context . '.' . $variant,
                    $context,
                    null,
                    null,
                    $tones[$variant-1],
                    $prefixPl[$variant-1] . $text[0],
                    $prefixEn[$variant-1] . $text[1],
                ];
            }
        }
        for ($round=1; $round<=3; $round++) {
            for ($variant=1; $variant<=4; $variant++) {
                $rows[] = [
                    'round.' . $round . '.' . $variant,
                    'round_opening',
                    null,
                    $round,
                    $tones[$variant-1],
                    $prefixPl[$variant-1] . 'Runda {round} wymaga konkretnych decyzji przed {deadline}.',
                    $prefixEn[$variant-1] . 'Round {round} requires concrete decisions before {deadline}.',
                ];
            }
        }
        $departmentTexts = [
            'technical'=>['Bezpieczna praca techniczna wymaga odpowiedniej obsady i wynagrodzenia.','Safe technical work requires proper staffing and compensation.'],
            'logistics'=>['Ciągłość dostaw zależy od uczciwych warunków pracy logistyki.','Delivery continuity depends on fair conditions for logistics staff.'],
            'hr'=>['Dział HR oczekuje standardów, które sam ma egzekwować.','The HR department expects the standards it is asked to enforce.'],
            'legal'=>['Dział prawny oczekuje formalnego i wykonalnego porozumienia.','The legal department expects a formal and enforceable agreement.'],
            'finance'=>['Dział finansowy oczekuje przewidywalnych zasad wynagradzania.','The finance department expects predictable compensation rules.'],
        ];
        foreach ($departmentTexts as $department => $text) {
            for ($variant=1; $variant<=4; $variant++) {
                $rows[] = [
                    'department.' . $department . '.' . $variant,
                    'counteroffer',
                    $department,
                    null,
                    $tones[$variant-1],
                    $prefixPl[$variant-1] . $text[0],
                    $prefixEn[$variant-1] . $text[1],
                ];
            }
        }
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO employee_dialogue_templates
                (seed_key, context_key, department_code, round_no, tone, text_pl, text_en, weight, is_active)
               VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1) ON CONFLICT(seed_key) DO NOTHING'
            : 'INSERT IGNORE INTO employee_dialogue_templates
                (seed_key, context_key, department_code, round_no, tone, text_pl, text_en, weight, is_active)
               VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)';
        $stmt = $this->db->prepare($sql);
        foreach ($rows as $row) {
            $stmt->execute($row);
        }
    }
}
