<?php

require_once __DIR__ . '/Incident/MessagesTrait.php';
require_once __DIR__ . '/Incident/TickTrait.php';
require_once __DIR__ . '/Incident/RepairDataTrait.php';

/**
 * IncidentService System mikro i drobnych zdarze per odwiert.
 *
 * Typy zdarze (4 poziomy):
 * micro -5-10% produkcji, auto-naprawa, ~40-50% udziau
 * minor -10-30% produkcji, auto-naprawa, ~25-30%
 * medium -40-60% produkcji, wymaga technika, ~15-20%
 * major -100% lub zatrzymanie, wymaga naprawy + koszt, ~5-10%
 *
 * error_rate per pracownik: skill 1->12%, skill 5->6%, skill 10->2%
 * Floor risk: globalnie 2-3% / tick nie do zbicia przez BHP/skill
 *
 * Logika podzielona na traity w src/Incident/:
 * - MessagesTrait.php const MESSAGES, generateIncident, getWorkerName, interpolate
 * - TickTrait.php processTick, calcErrorRate
 * - RepairDataTrait.php repairIncident, getRecentIncidents, getPlayerIncidents,
 * saveIncident, applyEffects, weightedRand
 */
class IncidentService
{
    use IncidentMessagesTrait;
    use IncidentTickTrait;
    use IncidentRepairDataTrait;

    private \PDO $db;

 // Konfiguracja poziomw
    private const LEVEL_CONFIG = [
        'micro'  => ['prod_drop_min' => 5,  'prod_drop_max' => 10,  'auto_repair' => true,  'hours_min' => 1,  'hours_max' => 2,  'deg_min' => 0, 'deg_max' => 1,  'cost_min' => 0,      'cost_max' => 0,       'risk_add' => 0],
        'minor'  => ['prod_drop_min' => 10, 'prod_drop_max' => 30,  'auto_repair' => true,  'hours_min' => 2,  'hours_max' => 6,  'deg_min' => 1, 'deg_max' => 3,  'cost_min' => 0,      'cost_max' => 0,       'risk_add' => 0],
        'medium' => ['prod_drop_min' => 40, 'prod_drop_max' => 60,  'auto_repair' => false, 'hours_min' => 6,  'hours_max' => 24, 'deg_min' => 3, 'deg_max' => 8,  'cost_min' => 50000,  'cost_max' => 500000,  'risk_add' => 5],
        'major'  => ['prod_drop_min' => 80, 'prod_drop_max' => 100, 'auto_repair' => false, 'hours_min' => 24, 'hours_max' => 72, 'deg_min' => 8, 'deg_max' => 20, 'cost_min' => 200000, 'cost_max' => 2000000, 'risk_add' => 15],
    ];

 // Szanse bazowe incydentu per godzin neutralne (przed eq/layer/wear mnonikami).
 // Skalibrowane: micro co ~40min, minor co ~2h, medium co ~8h, major co ~40h
 // (przy cond=100%, risk=30, standard, shallow, bez BHP)
    private const BASE_CHANCE_PER_HOUR = [
        'micro'  => 0.15,
        'minor'  => 0.055,
        'medium' => 0.018,
        'major'  => 0.004,
    ];

 // Floor = staa szansa per tick (NIE skalowana przez deltaHours)
 // Gwarantuje minimum ~3%/tick dla micro niezalenie od przerw
    private const FLOOR_CHANCE_PER_TICK = 0.030;

 /** @var array<string, array<string, float>> */
    private array $levelCfg = [];
 /** @var array<string, float> */
    private array $baseChance = [];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->loadConfigOverrides();
    }

 /**
 * Load per-level overrides from well_config (set via admin/incidents.php).
 * Falls back to LEVEL_CONFIG / BASE_CHANCE_PER_HOUR constants if key absent.
 */
    private function loadConfigOverrides(): void
    {
        $this->levelCfg   = [];
        $this->baseChance = [];
        foreach (self::LEVEL_CONFIG as $lvl => $def) {
            $this->levelCfg[$lvl]   = $def;
            $this->baseChance[$lvl] = self::BASE_CHANCE_PER_HOUR[$lvl];
        }
        try {
            $rows = $this->db->query(
                "SELECT `key`, `value` FROM well_config WHERE `key` LIKE 'incident_cfg_%'"
            )->fetchAll(\PDO::FETCH_KEY_PAIR);
            foreach ($rows as $key => $val) {
 // key format: incident_cfg_{level}_{field}
                $parts = explode('_', str_replace('incident_cfg_', '', $key), 2);
                if (count($parts) !== 2) continue;
                [$lvl, $field] = $parts;
                if (!isset($this->levelCfg[$lvl])) continue;
                if ($field === 'base_chance') {
                    $this->baseChance[$lvl] = max(0.0001, (float)$val);
                } elseif (array_key_exists($field, $this->levelCfg[$lvl])) {
                    $this->levelCfg[$lvl][$field] = (float)$val;
                }
            }
        } catch (\Throwable $e) {
            GameLog::error('IncidentService', 'loadConfigOverrides FAILED � using defaults', $e);
        }
    }

 /**
 * Returns effective level config (DB overrides applied).
 * @return array<string, mixed>
 */
    public function getLevelConfig(string $level): array
    {
        return $this->levelCfg[$level] ?? (self::LEVEL_CONFIG[$level] ?? []);
    }

 /**
 * Returns effective base chance per hour for a level.
 */
    public function getBaseChance(string $level): float
    {
        return $this->baseChance[$level] ?? (self::BASE_CHANCE_PER_HOUR[$level] ?? 0.0);
    }
}
