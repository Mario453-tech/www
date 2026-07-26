<?php
declare(strict_types=1);

final class EmployeeSystemConfigService
{
    /** @var array<string,array{type:string,min:float,max:float,step:float,default:float|int|bool,group:string,label_key:string,description_key:string}> */
    private const DEFINITIONS = [
        'morale_default' => ['type'=>'float','min'=>0,'max'=>100,'step'=>1,'default'=>65.0,'group'=>'morale','label_key'=>'admin.hr.config.morale_default','description_key'=>'admin.hr.config.morale_default_desc'],
        'morale_cycle_up_max' => ['type'=>'float','min'=>0,'max'=>20,'step'=>0.5,'default'=>3.0,'group'=>'morale','label_key'=>'admin.hr.config.morale_up','description_key'=>'admin.hr.config.morale_up_desc'],
        'morale_cycle_down_max' => ['type'=>'float','min'=>0,'max'=>20,'step'=>0.5,'default'=>5.0,'group'=>'morale','label_key'=>'admin.hr.config.morale_down','description_key'=>'admin.hr.config.morale_down_desc'],
        'salary_min_factor' => ['type'=>'float','min'=>0.1,'max'=>2,'step'=>0.05,'default'=>0.4,'group'=>'morale','label_key'=>'admin.hr.config.salary_min_factor','description_key'=>'admin.hr.config.salary_min_factor_desc'],
        'salary_max_factor' => ['type'=>'float','min'=>0.1,'max'=>2,'step'=>0.05,'default'=>0.9,'group'=>'morale','label_key'=>'admin.hr.config.salary_max_factor','description_key'=>'admin.hr.config.salary_max_factor_desc'],
        'skill_factor_min' => ['type'=>'float','min'=>0.1,'max'=>2,'step'=>0.05,'default'=>0.85,'group'=>'morale','label_key'=>'admin.hr.config.skill_min','description_key'=>'admin.hr.config.skill_min_desc'],
        'skill_factor_max' => ['type'=>'float','min'=>0.1,'max'=>2,'step'=>0.05,'default'=>1.15,'group'=>'morale','label_key'=>'admin.hr.config.skill_max','description_key'=>'admin.hr.config.skill_max_desc'],
        'experience_factor_max' => ['type'=>'float','min'=>1,'max'=>2,'step'=>0.05,'default'=>1.2,'group'=>'morale','label_key'=>'admin.hr.config.experience_max','description_key'=>'admin.hr.config.experience_max_desc'],
        'ambition_factor_min' => ['type'=>'float','min'=>0.5,'max'=>2,'step'=>0.05,'default'=>0.95,'group'=>'morale','label_key'=>'admin.hr.config.ambition_min','description_key'=>'admin.hr.config.ambition_min_desc'],
        'ambition_factor_max' => ['type'=>'float','min'=>0.5,'max'=>2,'step'=>0.05,'default'=>1.1,'group'=>'morale','label_key'=>'admin.hr.config.ambition_max','description_key'=>'admin.hr.config.ambition_max_desc'],
        'tenure_year_pct' => ['type'=>'float','min'=>0,'max'=>5,'step'=>0.1,'default'=>0.5,'group'=>'morale','label_key'=>'admin.hr.config.tenure_year','description_key'=>'admin.hr.config.tenure_year_desc'],
        'tenure_pct_max' => ['type'=>'float','min'=>0,'max'=>25,'step'=>1,'default'=>5.0,'group'=>'morale','label_key'=>'admin.hr.config.tenure_max','description_key'=>'admin.hr.config.tenure_max_desc'],
        'training_pct_each' => ['type'=>'float','min'=>0,'max'=>5,'step'=>0.1,'default'=>1.0,'group'=>'morale','label_key'=>'admin.hr.config.training_each','description_key'=>'admin.hr.config.training_each_desc'],
        'training_pct_max' => ['type'=>'float','min'=>0,'max'=>25,'step'=>1,'default'=>5.0,'group'=>'morale','label_key'=>'admin.hr.config.training_max','description_key'=>'admin.hr.config.training_max_desc'],
        'responsibility_pct_max' => ['type'=>'float','min'=>0,'max'=>25,'step'=>1,'default'=>10.0,'group'=>'morale','label_key'=>'admin.hr.config.responsibility_max','description_key'=>'admin.hr.config.responsibility_max_desc'],
        'unhappy_morale_threshold' => ['type'=>'float','min'=>0,'max'=>100,'step'=>1,'default'=>45.0,'group'=>'relations','label_key'=>'admin.hr.config.unhappy_morale','description_key'=>'admin.hr.config.unhappy_morale_desc'],
        'unhappy_satisfaction_threshold' => ['type'=>'float','min'=>0,'max'=>120,'step'=>1,'default'=>70.0,'group'=>'relations','label_key'=>'admin.hr.config.unhappy_salary','description_key'=>'admin.hr.config.unhappy_salary_desc'],
        'raise_morale_threshold' => ['type'=>'float','min'=>0,'max'=>100,'step'=>1,'default'=>40.0,'group'=>'relations','label_key'=>'admin.hr.config.raise_morale','description_key'=>'admin.hr.config.raise_morale_desc'],
        'raise_satisfaction_threshold' => ['type'=>'float','min'=>0,'max'=>120,'step'=>1,'default'=>75.0,'group'=>'relations','label_key'=>'admin.hr.config.raise_salary','description_key'=>'admin.hr.config.raise_salary_desc'],
        'raise_cooldown_hours' => ['type'=>'int','min'=>1,'max'=>8760,'step'=>1,'default'=>168,'group'=>'relations','label_key'=>'admin.hr.config.raise_cooldown','description_key'=>'admin.hr.config.raise_cooldown_desc'],
        'raise_response_hours' => ['type'=>'int','min'=>1,'max'=>720,'step'=>1,'default'=>48,'group'=>'relations','label_key'=>'admin.hr.config.raise_response','description_key'=>'admin.hr.config.raise_response_desc'],
        'threat_morale_threshold' => ['type'=>'float','min'=>0,'max'=>100,'step'=>1,'default'=>35.0,'group'=>'strikes','label_key'=>'admin.hr.config.threat_morale','description_key'=>'admin.hr.config.threat_morale_desc'],
        'strike_morale_threshold' => ['type'=>'float','min'=>0,'max'=>100,'step'=>1,'default'=>40.0,'group'=>'strikes','label_key'=>'admin.hr.config.strike_morale','description_key'=>'admin.hr.config.strike_morale_desc'],
        'threat_min_disputes' => ['type'=>'int','min'=>1,'max'=>20,'step'=>1,'default'=>2,'group'=>'strikes','label_key'=>'admin.hr.config.threat_disputes','description_key'=>'admin.hr.config.threat_disputes_desc'],
        'threat_support_threshold' => ['type'=>'float','min'=>0,'max'=>100,'step'=>1,'default'=>55.0,'group'=>'strikes','label_key'=>'admin.hr.config.threat_support','description_key'=>'admin.hr.config.threat_support_desc'],
        'strike_support_threshold' => ['type'=>'float','min'=>0,'max'=>100,'step'=>1,'default'=>65.0,'group'=>'strikes','label_key'=>'admin.hr.config.strike_support','description_key'=>'admin.hr.config.strike_support_desc'],
        'strike_member_support' => ['type'=>'float','min'=>0,'max'=>100,'step'=>1,'default'=>50.0,'group'=>'strikes','label_key'=>'admin.hr.config.member_support','description_key'=>'admin.hr.config.member_support_desc'],
        'threat_cycles_required' => ['type'=>'int','min'=>1,'max'=>20,'step'=>1,'default'=>2,'group'=>'strikes','label_key'=>'admin.hr.config.threat_cycles','description_key'=>'admin.hr.config.threat_cycles_desc'],
        'feature_threats' => ['type'=>'bool','min'=>0,'max'=>1,'step'=>1,'default'=>false,'group'=>'strikes','label_key'=>'admin.hr.config.feature_threats','description_key'=>'admin.hr.config.feature_threats_desc'],
        'feature_strikes' => ['type'=>'bool','min'=>0,'max'=>1,'step'=>1,'default'=>false,'group'=>'strikes','label_key'=>'admin.hr.config.feature_strikes','description_key'=>'admin.hr.config.feature_strikes_desc'],
        'feature_strike_effects' => ['type'=>'bool','min'=>0,'max'=>1,'step'=>1,'default'=>false,'group'=>'strikes','label_key'=>'admin.hr.config.feature_effects','description_key'=>'admin.hr.config.feature_effects_desc'],
        'feature_negotiations' => ['type'=>'bool','min'=>0,'max'=>1,'step'=>1,'default'=>false,'group'=>'negotiations','label_key'=>'admin.hr.config.feature_negotiations','description_key'=>'admin.hr.config.feature_negotiations_desc'],
        'negotiation_rounds' => ['type'=>'int','min'=>1,'max'=>5,'step'=>1,'default'=>3,'group'=>'negotiations','label_key'=>'admin.hr.config.rounds','description_key'=>'admin.hr.config.rounds_desc'],
        'negotiation_round_hours' => ['type'=>'int','min'=>1,'max'=>168,'step'=>1,'default'=>24,'group'=>'negotiations','label_key'=>'admin.hr.config.round_hours','description_key'=>'admin.hr.config.round_hours_desc'],
        'negotiation_raise_min' => ['type'=>'float','min'=>0,'max'=>30,'step'=>0.5,'default'=>0.0,'group'=>'negotiations','label_key'=>'admin.hr.config.raise_min','description_key'=>'admin.hr.config.raise_min_desc'],
        'negotiation_raise_max' => ['type'=>'float','min'=>0,'max'=>30,'step'=>0.5,'default'=>30.0,'group'=>'negotiations','label_key'=>'admin.hr.config.raise_max','description_key'=>'admin.hr.config.raise_max_desc'],
        'negotiation_bonus_max' => ['type'=>'float','min'=>0,'max'=>100000,'step'=>100,'default'=>100000.0,'group'=>'negotiations','label_key'=>'admin.hr.config.bonus_max','description_key'=>'admin.hr.config.bonus_max_desc'],
        'negotiation_cooldown_hours' => ['type'=>'int','min'=>1,'max'=>720,'step'=>1,'default'=>72,'group'=>'negotiations','label_key'=>'admin.hr.config.negotiation_cooldown','description_key'=>'admin.hr.config.negotiation_cooldown_desc'],
        'negotiation_offer_weight' => ['type'=>'float','min'=>0.01,'max'=>10,'step'=>0.05,'default'=>0.5,'group'=>'negotiations','label_key'=>'admin.hr.config.offer_weight','description_key'=>'admin.hr.config.offer_weight_desc'],
        'negotiation_support_weight' => ['type'=>'float','min'=>0.01,'max'=>10,'step'=>0.05,'default'=>0.2,'group'=>'negotiations','label_key'=>'admin.hr.config.support_weight','description_key'=>'admin.hr.config.support_weight_desc'],
        'negotiation_morale_weight' => ['type'=>'float','min'=>0.01,'max'=>10,'step'=>0.05,'default'=>0.15,'group'=>'negotiations','label_key'=>'admin.hr.config.morale_weight','description_key'=>'admin.hr.config.morale_weight_desc'],
        'negotiation_hr_weight' => ['type'=>'float','min'=>0.01,'max'=>10,'step'=>0.05,'default'=>0.15,'group'=>'negotiations','label_key'=>'admin.hr.config.hr_weight','description_key'=>'admin.hr.config.hr_weight_desc'],
        'negotiation_reject_support_gain' => ['type'=>'float','min'=>0,'max'=>25,'step'=>1,'default'=>5.0,'group'=>'negotiations','label_key'=>'admin.hr.config.reject_support','description_key'=>'admin.hr.config.reject_support_desc'],
        'settlement_morale_gain' => ['type'=>'float','min'=>0,'max'=>100,'step'=>1,'default'=>20.0,'group'=>'negotiations','label_key'=>'admin.hr.config.settlement_morale','description_key'=>'admin.hr.config.settlement_morale_desc'],
    ];
    /** @var array<string,float|int|bool> */
    private array $values = [];


    public function __construct(private readonly PDO $db)
    {
        EmployeeSystemBootstrap::ensure($db);
        $this->seedDefaults();
        $this->loadValues();
    }

    /** @return array<string,array<string,mixed>> */
    public function definitions(): array
    {
        return self::DEFINITIONS;
    }

    public function getFloat(string $key): float
    {
        return (float)$this->get($key);
    }

    public function getInt(string $key): int
    {
        return (int)$this->get($key);
    }

    public function getBool(string $key): bool
    {
        return (bool)$this->get($key);
    }

    public function get(string $key): float|int|bool
    {
        if (!isset(self::DEFINITIONS[$key])) {
            throw new InvalidArgumentException('Unknown employee system config key.');
        }
        return $this->values[$key];
    }
    /** @return array<string,float|int|bool> */
    public function all(): array
    {
        return $this->values;
    }
    /**
     * @param array<string,mixed> $input
     * @return array<string,array{old:float|int|bool,new:float|int|bool}>
     */
    public function save(array $input): array
    {
        $values = $this->all();
        foreach ($input as $key => $value) {
            if (!isset(self::DEFINITIONS[$key])) {
                throw new InvalidArgumentException('Unknown employee system config key.');
            }
            $values[$key] = $this->validateValue($key, $value);
        }
        $this->validateRelations($values);

        $changes = [];
        foreach ($input as $key => $_) {
            $old = $this->get($key);
            $new = $values[$key];
            if ($old === $new) {
                continue;
            }
            $stmt = $this->db->prepare('UPDATE employee_system_config SET config_value = ?, updated_at = CURRENT_TIMESTAMP WHERE config_key = ?');
            $stmt->execute([$this->serialize($new), $key]);
            $this->values[$key] = $new;
            $changes[$key] = ['old' => $old, 'new' => $new];
        }
        return $changes;
    }

    public function reset(): void
    {
        $stmt = $this->db->prepare('UPDATE employee_system_config SET config_value = ?, updated_at = CURRENT_TIMESTAMP WHERE config_key = ?');
        foreach (self::DEFINITIONS as $key => $definition) {
            $stmt->execute([$this->serialize($definition['default']), $key]);
        }
        $this->loadValues();
    }

    private function loadValues(): void
    {
        $rows = $this->db->query('SELECT config_key, config_value FROM employee_system_config')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->values = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $this->values[$key] = $this->cast(
                $definition,
                array_key_exists($key, $rows) ? $rows[$key] : $definition['default']
            );
        }
    }

    private function seedDefaults(): void
    {
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql = $driver === 'sqlite'
            ? 'INSERT INTO employee_system_config (config_key, config_value) VALUES (?, ?) ON CONFLICT(config_key) DO NOTHING'
            : 'INSERT IGNORE INTO employee_system_config (config_key, config_value) VALUES (?, ?)';
        $stmt = $this->db->prepare($sql);
        foreach (self::DEFINITIONS as $key => $definition) {
            $stmt->execute([$key, $this->serialize($definition['default'])]);
        }
    }

    /** @param array<string,mixed> $definition */
    private function cast(array $definition, mixed $value): float|int|bool
    {
        return match ($definition['type']) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            'int' => (int)$value,
            default => (float)$value,
        };
    }

    private function validateValue(string $key, mixed $value): float|int|bool
    {
        $definition = self::DEFINITIONS[$key];
        $cast = $this->cast($definition, $value);
        $number = (float)$cast;
        if ($number < $definition['min'] || $number > $definition['max']) {
            throw new InvalidArgumentException('Employee system config value is outside the allowed range.');
        }
        return $cast;
    }

    /** @param array<string,float|int|bool> $values */
    private function validateRelations(array $values): void
    {
        if ((float)$values['salary_min_factor'] > (float)$values['salary_max_factor']
            || (float)$values['skill_factor_min'] > (float)$values['skill_factor_max']
            || (float)$values['ambition_factor_min'] > (float)$values['ambition_factor_max']
            || (float)$values['negotiation_raise_min'] > (float)$values['negotiation_raise_max']
            || (float)$values['threat_support_threshold'] >= (float)$values['strike_support_threshold']) {
            throw new InvalidArgumentException('Employee system config relations are invalid.');
        }
    }

    private function serialize(float|int|bool $value): string
    {
        return is_bool($value) ? ($value ? '1' : '0') : (string)$value;
    }
}
