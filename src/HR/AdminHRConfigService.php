<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Employee/EmployeeSystemConfigService.php';
require_once __DIR__ . '/EmployeeDialogueTemplateService.php';

final class AdminHRConfigService
{
    /** @var array<string,list<string>> */
    private const ALLOWED_CONFIG_KEYS = [
        'morale' => [
            'morale_default', 'morale_cycle_up_max', 'morale_cycle_down_max',
            'salary_min_factor', 'salary_max_factor', 'skill_factor_min',
            'skill_factor_max', 'experience_factor_max', 'ambition_factor_min',
            'ambition_factor_max', 'tenure_year_pct', 'tenure_pct_max',
            'training_pct_each', 'training_pct_max', 'responsibility_pct_max',
        ],
        'relations' => [
            'unhappy_morale_threshold', 'unhappy_satisfaction_threshold',
            'leave_risk_threshold', 'leave_risk_cycles_required', 'leave_notice_hours',
            'raise_morale_threshold', 'raise_satisfaction_threshold',
            'raise_cooldown_hours', 'raise_response_hours',
            'raise_accept_morale_gain', 'raise_accept_loyalty_gain',
            'raise_accept_leave_risk_reduction', 'raise_salary_negotiator_chance_bonus',
            'raise_negotiated_morale_gain', 'raise_negotiation_fail_morale_penalty',
            'raise_reject_morale_penalty', 'raise_reject_support_gain',
            'raise_reject_leave_risk_gain', 'raise_postpone_morale_penalty',
            'raise_postpone_leave_risk_gain', 'raise_postpone_hours',
            'raise_max_postponements',
        ],
        'strikes' => [
            'threat_morale_threshold', 'strike_morale_threshold',
            'threat_min_disputes', 'threat_support_threshold',
            'strike_support_threshold', 'strike_member_support',
            'threat_cycles_required', 'feature_threats', 'feature_strikes',
            'feature_strike_effects',
        ],
        'negotiations' => [
            'feature_negotiations', 'negotiation_rounds',
            'negotiation_round_hours', 'negotiation_raise_min',
            'negotiation_raise_max', 'negotiation_bonus_max',
            'negotiation_cooldown_hours', 'negotiation_offer_weight',
            'negotiation_support_weight', 'negotiation_morale_weight',
            'negotiation_hr_weight', 'negotiation_reject_support_gain',
            'settlement_morale_gain',
        ],
        'effects' => [
            'strike_logistics_capacity_cap', 'strike_road_cost_multiplier',
            'strike_road_delay_risk_multiplier', 'strike_logistics_response_multiplier',
            'strike_technical_repair_time_multiplier',
            'strike_technical_emergency_cost_multiplier',
            'strike_hr_recruitment_time_multiplier',
            'strike_hr_negotiation_effectiveness',
            'strike_legal_case_time_multiplier', 'strike_legal_effectiveness',
            'strike_legal_deadline_risk_multiplier',
            'strike_hr_negative_morale_multiplier',
        ],
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array<string,array{
     *   definitions:array<string,array<string,mixed>>,
     *   values:array<string,float|int|bool>
     * }>
     */
    public function groupedSettings(): array
    {
        $service = new EmployeeSystemConfigService($this->db);
        $definitions = $service->definitions();
        $values = $service->all();
        $groups = [];
        foreach (self::ALLOWED_CONFIG_KEYS as $group => $keys) {
            $allowed = array_flip($keys);
            $groups[$group] = [
                'definitions' => array_intersect_key($definitions, $allowed),
                'values' => array_intersect_key($values, $allowed),
            ];
        }
        return $groups;
    }

    /**
     * @param array<string,mixed> $submitted
     * @return array<string,array{old:float|int|bool,new:float|int|bool}>
     */
    public function saveSettings(string $group, array $submitted): array
    {
        $keys = self::ALLOWED_CONFIG_KEYS[$group] ?? null;
        if ($keys === null) {
            throw new InvalidArgumentException('Unknown HR configuration group.');
        }
        $allowed = array_flip($keys);
        $input = array_intersect_key($submitted, $allowed);
        if ($input === [] || count($input) !== count($submitted)) {
            throw new InvalidArgumentException('HR configuration contains disallowed keys.');
        }
        return (new EmployeeSystemConfigService($this->db))->save($input);
    }

    /** @param array<string,mixed> $data */
    public function saveDialogue(array $data, ?int $id): int
    {
        $service = new EmployeeDialogueTemplateService($this->db);
        return $this->transactional(fn(): int => $service->save($data, $id));
    }

    public function duplicateDialogue(int $id): int
    {
        $service = new EmployeeDialogueTemplateService($this->db);
        return $this->transactional(fn(): int => $service->duplicate($id));
    }

    public function toggleDialogue(int $id, bool $active): void
    {
        $service = new EmployeeDialogueTemplateService($this->db);
        $this->transactional(function () use ($service, $id, $active): void {
            $service->setActive($id, $active);
        });
    }

    public function resetDialogues(): void
    {
        $service = new EmployeeDialogueTemplateService($this->db);
        $this->transactional(function () use ($service): void {
            $service->restoreSeededDefaults();
        });
    }

    private function transactional(callable $operation): mixed
    {
        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) {
            $this->db->beginTransaction();
        }
        try {
            $result = $operation();
            if ($ownTransaction) {
                $this->db->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }
}
