<?php
/**
 * TechnicalTeamService.php
 * Technical team management and task system.
 * Technical team management and task system.
 *
 * Hierarchy / Hierarchia:
 *   board_members (role=technical) Technical Manager
 *   technical_staff Drilling/Reservoir/Production/Maintenance/Pipeline/HSE
 *
 * Logic split into traits in src/TTS/:
 * Logika podzielona na traity w src/TTS/:
 *   ManagerTrait.php       getManager, getManagerBonus, getHSEBonus
 *   ProceduresTrait.php    getProcedureStatus, upgradeProcedures, repairProcedureIntegrity,
 *                           processProcedureDecay, getStaffRequirementCheck
 *   StaffTrait.php         getStaff, getStaffMember, getStaffBonus, hireEngineer, fireEngineer
 *   TasksTrait.php         getTasks, getActiveTasks, assignTask, processTick, completeTask,
 *                           getQueue, cancelTask, cancelQueueItem
 *   NotificationsTrait.php  countUnreadNotifications, getUnreadNotifications, markRead, notify
 *   RecruitmentTrait.php   getTechnicalCandidates, reviewCandidate, completeRecruitment,
 *                           getActiveRecruitment, requestRecruitment, formatTime, fmt
 */

require_once __DIR__ . '/TTS/ManagerTrait.php';
require_once __DIR__ . '/TTS/ProceduresTrait.php';
require_once __DIR__ . '/TTS/StaffTrait.php';
require_once __DIR__ . '/TTS/TasksTrait.php';
require_once __DIR__ . '/TTS/NotificationsTrait.php';
require_once __DIR__ . '/TTS/RecruitmentTrait.php';

class TechnicalTeamService
{
    use TTSManagerTrait;
    use TTSProceduresTrait;
    use TTSStaffTrait;
    use TTSTasksTrait;
    use TTSNotificationsTrait;
    use TTSRecruitmentTrait;

    private PDO $db;
    private int $playerId;

    // Engineer specs catalog / Katalog specjalizacji inzyniera
    const SPECS = [
        'drilling_engineer'    => [
            'name_key'  => 'technical.spec.drilling_engineer',
            'icon'      => 'DRL',
            'tasks'     => ['install_module','blowout_control'],
            'salary_range' => [8000, 18000],
            'description_key' => 'technical.spec_desc.drilling_engineer',
        ],
        'reservoir_engineer'   => [
            'name_key'  => 'technical.spec.reservoir_engineer',
            'icon'      => 'RES',
            'tasks'     => ['reservoir_analysis'],
            'salary_range' => [9000, 20000],
            'description_key' => 'technical.spec_desc.reservoir_engineer',
        ],
        'production_engineer'  => [
            'name_key'  => 'technical.spec.production_engineer',
            'icon'      => 'PRD',
            'tasks'     => ['production_optimization'],
            'salary_range' => [7000, 15000],
            'description_key' => 'technical.spec_desc.production_engineer',
        ],
        'maintenance_engineer' => [
            'name_key'  => 'technical.spec.maintenance_engineer',
            'icon'      => 'MNT',
            'tasks'     => ['well_maintenance','well_repair','hub_maintenance','hub_repair'],
            'salary_range' => [6000, 13000],
            'description_key' => 'technical.spec_desc.maintenance_engineer',
        ],
        'pipeline_engineer'    => [
            'name_key'  => 'technical.spec.pipeline_engineer',
            'icon'      => 'RUR',
            'tasks'     => ['pipeline_maintenance','pipeline_repair'],
            'salary_range' => [7000, 15000],
            'description_key' => 'technical.spec_desc.pipeline_engineer',
        ],
        'safety_engineer'      => [
            'name_key'  => 'technical.spec.safety_engineer',
            'icon'      => 'HSE',
            'tasks'     => ['safety_audit'],
            'salary_range' => [7500, 16000],
            'description_key' => 'technical.spec_desc.safety_engineer',
        ],
        'safety_officer'       => [
            'name_key'  => 'technical.spec.safety_officer',
            'icon'      => 'BHP',
            'tasks'     => ['safety_audit'],
            'salary_range' => [6000, 12000],
            'description_key' => 'technical.spec_desc.safety_officer',
        ],
    ];

    // HSE procedure upgrade costs / Koszty ulepszenia procedur BHP
    const PROCEDURE_UPGRADE_COSTS = [
        1 => 500_000,
        2 => 1_000_000,
        3 => 2_000_000,
        4 => 4_000_000,
        5 => 8_000_000,
    ];

    // Task catalog / Katalog zadan
    const TASKS = [
        'well_maintenance' => [
            'label_key'   => 'technical.task.well_maintenance',
            'icon'        => 'MNT',
            'assignable'  => ['maintenance_engineer'],
            'hours_min'   => 6,
            'hours_max'   => 12,
            'cost_min'    => 200_000,
            'cost_max'    => 800_000,
            'needs_well'  => true,
            'effect_key'  => 'technical.task_effect.well_maintenance',
        ],
        'well_repair' => [
            'label_key'   => 'technical.task.well_repair',
            'icon'        => 'FIX',
            'assignable'  => ['maintenance_engineer'],
            'hours_min'   => 12,
            'hours_max'   => 36,
            'cost_min'    => 1_000_000,
            'cost_max'    => 6_000_000,
            'needs_well'  => true,
            'effect_key'  => 'technical.task_effect.well_repair',
        ],
        'hub_maintenance' => [
            'label_key'   => 'technical.task.hub_maintenance',
            'icon'        => 'HUB',
            'assignable'  => ['maintenance_engineer'],
            'hours_min'   => 8,
            'hours_max'   => 16,
            'cost_min'    => 1_000_000,
            'cost_max'    => 3_000_000,
            'needs_well'  => false,
            'needs_hub'   => true,
            'effect_key'  => 'technical.task_effect.hub_maintenance',
        ],
        'hub_repair' => [
            'label_key'   => 'technical.task.hub_repair',
            'icon'        => 'HUB',
            'assignable'  => ['maintenance_engineer'],
            'hours_min'   => 14,
            'hours_max'   => 36,
            'cost_min'    => 3_000_000,
            'cost_max'    => 8_000_000,
            'needs_well'  => false,
            'needs_hub'   => true,
            'effect_key'  => 'technical.task_effect.hub_repair',
        ],
        'reservoir_analysis' => [
            'label_key'   => 'technical.task.reservoir_analysis',
            'icon'        => 'RES',
            'assignable'  => ['reservoir_engineer'],
            'hours_min'   => 8,
            'hours_max'   => 24,
            'cost_min'    => 0,
            'cost_max'    => 0,
            'needs_well'  => true,
            'effect_key'  => 'technical.task_effect.reservoir_analysis',
        ],
        'production_optimization' => [
            'label_key'   => 'technical.task.production_optimization',
            'icon'        => 'OPT',
            'assignable'  => ['production_engineer'],
            'hours_min'   => 4,
            'hours_max'   => 10,
            'cost_min'    => 50_000,
            'cost_max'    => 200_000,
            'needs_well'  => true,
            'effect_key'  => 'technical.task_effect.production_optimization',
        ],
        'install_module' => [
            'label_key'   => 'technical.task.install_module',
            'icon'        => 'MOD',
            'assignable'  => ['drilling_engineer'],
            'hours_min'   => 24,
            'hours_max'   => 72,
            'cost_min'    => 1_000_000,
            'cost_max'    => 6_000_000,
            'needs_well'  => true,
            'effect_key'  => 'technical.task_effect.install_module',
        ],
        'pipeline_maintenance' => [
            'label_key'   => 'technical.task.pipeline_maintenance',
            'icon'        => 'RUR',
            'assignable'  => ['pipeline_engineer'],
            'hours_min'   => 6,
            'hours_max'   => 12,
            'cost_min'    => 0,
            'cost_max'    => 0,
            'needs_well'  => false,
            'effect_key'  => 'technical.task_effect.pipeline_maintenance',
        ],
        'pipeline_inspection' => [
            'label_key'   => 'technical.task.pipeline_inspection',
            'icon'        => 'RUR',
            'assignable'  => ['pipeline_engineer'],
            'hours_min'   => 6,
            'hours_max'   => 12,
            'cost_min'    => 0,
            'cost_max'    => 0,
            'needs_well'  => false,
            'effect_key'  => 'technical.task_effect.pipeline_inspection',
        ],
        'safety_audit' => [
            'label_key'   => 'technical.task.safety_audit',
            'icon'        => 'HSE',
            'assignable'  => ['safety_engineer', 'safety_officer'],
            'hours_min'   => 12,
            'hours_max'   => 24,
            'cost_min'    => 0,
            'cost_max'    => 0,
            'needs_well'  => false,
            'effect_key'  => 'technical.task_effect.safety_audit',
        ],

        // Emergency tasks / Zadania ratunkowe
        'blowout_control' => [
            'label_key'   => 'technical.task.blowout_control',
            'icon'        => 'EMR',
            'assignable'  => ['drilling_engineer'],
            'hours_min'   => 72,
            'hours_max'   => 120,
            'cost_min'    => 0,
            'cost_max'    => 0,
            'needs_well'  => true,
            'effect_key'  => 'technical.task_effect.blowout_control',
            'emergency'   => true,
        ],
        'pipeline_repair' => [
            'label_key'   => 'technical.task.pipeline_repair',
            'icon'        => 'RUR',
            'assignable'  => ['pipeline_engineer'],
            'hours_min'   => 24,
            'hours_max'   => 48,
            'cost_min'    => 0,
            'cost_max'    => 0,
            'needs_well'  => false,
            'effect_key'  => 'technical.task_effect.pipeline_repair',
            'emergency'   => true,
        ],
        'reservoir_rehabilitation' => [
            'label_key'   => 'technical.task.reservoir_rehabilitation',
            'icon'        => 'REH',
            'assignable'  => ['reservoir_engineer'],
            'hours_min'   => 72,
            'hours_max'   => 120,
            'cost_min'    => 0,
            'cost_max'    => 0,
            'needs_well'  => true,
            'effect_key'  => 'technical.task_effect.reservoir_rehabilitation',
            'emergency'   => true,
        ],
    ];

    const MODULES = [
        'pump_electric'    => ['label_key' => 'technical.module.pump_electric',    'cost' => 2_000_000, 'effect_key' => 'technical.module_effect.pump_electric'],
        'monitoring'       => ['label_key' => 'technical.module.monitoring',       'cost' => 1_500_000, 'effect_key' => 'technical.module_effect.monitoring'],
        'water_injection'  => ['label_key' => 'technical.module.water_injection',  'cost' => 3_000_000, 'effect_key' => 'technical.module_effect.water_injection'],
        'pressure_booster' => ['label_key' => 'technical.module.pressure_booster', 'cost' => 2_500_000, 'effect_key' => 'technical.module_effect.pressure_booster'],
    ];

    public static function getSpecsCatalog(): array
    {
        $specs = self::SPECS;
        foreach ($specs as $code => $spec) {
            $specs[$code]['name'] = t($spec['name_key']);
            $specs[$code]['description'] = t($spec['description_key']);
        }
        return $specs;
    }

    public static function getSpecDefinition(string $code): ?array
    {
        $specs = self::getSpecsCatalog();
        return $specs[$code] ?? null;
    }

    public static function getTasksCatalog(): array
    {
        $tasks = self::TASKS;
        foreach ($tasks as $code => $task) {
            $tasks[$code]['label'] = t($task['label_key']);
            $tasks[$code]['effect'] = t($task['effect_key']);
        }
        return $tasks;
    }

    public static function getTaskDefinition(string $code): ?array
    {
        $tasks = self::getTasksCatalog();
        return $tasks[$code] ?? null;
    }

    public static function getModulesCatalog(): array
    {
        $modules = self::MODULES;
        foreach ($modules as $code => $module) {
            $modules[$code]['label'] = t($module['label_key']);
            $modules[$code]['effect'] = t($module['effect_key']);
        }
        return $modules;
    }

    public static function getModuleDefinition(string $code): ?array
    {
        $modules = self::getModulesCatalog();
        return $modules[$code] ?? null;
    }

    /** @return list<string> */
    private static function getTaskTypeEnumValues(): array
    {
        return [
            'well_maintenance',
            'well_repair',
            'hub_maintenance',
            'hub_repair',
            'reservoir_analysis',
            'production_optimization',
            'install_module',
            'pipeline_maintenance',
            'pipeline_inspection',
            'safety_audit',
            'blowout_control',
            'pipeline_repair',
            'reservoir_rehabilitation',
            'maintenance_service',
            'implement_procedures',
            'crisis_management',
            'assign_operator',
            'assign_technician',
        ];
    }

    /** @return list<string> */
    private static function getQueueTaskTypeEnumValues(): array
    {
        return self::getTaskTypeEnumValues();
    }

    private function ensureHubTaskSchema(): void
    {
        Database::addColumnIfMissing('technical_tasks', 'hub_id', 'INT NULL DEFAULT NULL AFTER well_id');
        Database::addColumnIfMissing('technical_task_queue', 'hub_id', 'INT NULL DEFAULT NULL AFTER well_id');

        $this->ensureEnumContainsValues('technical_tasks', 'task_type', self::getTaskTypeEnumValues(), 'well_maintenance');
        $this->ensureEnumContainsValues('technical_task_queue', 'task_type', self::getQueueTaskTypeEnumValues(), 'well_maintenance');
    }

    /**
     * @param list<string> $expectedValues
     */
    private function ensureEnumContainsValues(string $table, string $column, array $expectedValues, string $defaultValue): void
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COLUMN_TYPE
                   FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?
                  LIMIT 1"
            );
            $stmt->execute([$table, $column]);
            $columnType = (string)($stmt->fetchColumn() ?: '');
            if ($columnType === '' || !str_starts_with($columnType, 'enum(')) {
                return;
            }

            $missing = array_filter(
                $expectedValues,
                static fn(string $value): bool => strpos($columnType, "'" . $value . "'") === false
            );

            if ($missing === []) {
                return;
            }

            $enumSql = implode(
                ',',
                array_map(
                    static fn(string $value): string => "'" . str_replace("'", "\\'", $value) . "'",
                    $expectedValues
                )
            );

            $this->db->exec(
                "ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` ENUM({$enumSql}) NOT NULL DEFAULT '{$defaultValue}'"
            );

            GameLog::info('TechnicalTeamService', 'Task enum extended for hub support', [
                'table' => $table,
                'column' => $column,
                'added' => array_values($missing),
            ]);
        } catch (Throwable $e) {
            GameLog::error('TechnicalTeamService', 'ensureEnumContainsValues failed', $e, [
                'table' => $table,
                'column' => $column,
            ]);
        }
    }

    public function __construct(int $playerId)
    {
        try {
            $this->db       = Database::getInstance()->getConnection();
            $this->playerId = $playerId;
            $this->ensureHubTaskSchema();
            GameLog::info('TechnicalTeamService', 'Service initialized', ['player_id' => $playerId]);
        } catch (Throwable $e) {
            GameLog::error('TechnicalTeamService', 'Initialization failed', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
