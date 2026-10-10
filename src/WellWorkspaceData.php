<?php
declare(strict_types=1);

// Presentation data from existing services / Dane widoku z istniejacych serwisow.
final class WellWorkspaceData
{
    /**
     * @param array<string, mixed> $grid
     * @return array<string, mixed>
     */
    public static function prepare(array $grid, WellService $service): array
    {
        $items = [];
        $transportConfig = TransportConfigService::load(Database::getInstance()->getConnection());
        $grid['transportConfig'] = $transportConfig;
        foreach ($grid['groups'] as $group) {
            foreach ($group['wells'] as $w) {
                $w['_production'] = $w['_isActive'] ? $service->getEffectiveProduction($w) : 0.0;
                $w['_productionDelta'] = $w['_production'] - (float)$w['base_production_per_hour'];
                $w['_severity'] = !$w['_isActive'] ? 'danger' : ($w['_cond'] < 40 ? 'warning' : 'good');
                $w['_blocked'] = in_array($w['_status'], ['equipment_swap', 'seized', 'sold', 'blowout'], true);
                $w['_problem'] = self::problemForWell($w);
                $w['_tierOptions'] = [];
                foreach ($w['_tierCosts'] as $tier => $cost) {
                    $w['_tierOptions'][$tier] = ['cost' => $cost, 'effects' => WellService::getEquipmentMultipliers($tier, 0)];
                }
                $w['_nextEffects'] = WellService::getEquipmentMultipliers($w['_eqTier'], min(3, $w['_eqLevel'] + 1));
                $items[] = $w;
            }
        }
        $grid['items'] = $items;
        $grid['storageUsed'] = (float)($grid['storageData']['used'] ?? 0);
        $grid['storagePct'] = $grid['storageCap'] > 0 ? min(100, round($grid['storageUsed'] / $grid['storageCap'] * 100, 1)) : 0;
        return $grid;
    }

    /**
     * @param array<string, mixed> $well
     * @return array{title_key:string,severity:string,action:string,task:?string,hours_min:?int,hours_max:?int,cost_min:?float,cost_max:?float}
     */
    public static function problemForWell(array $well): array
    {
        $status = (string)($well['_status'] ?? 'active');
        $condition = (float)($well['_cond'] ?? 100.0);
        $taskCode = null;
        $titleKey = 'ww.no_urgent_problem';
        $severity = 'good';
        $action = 'equipment';

        if ($status === 'blowout') {
            $taskCode = 'blowout_control';
            $titleKey = 'ww.problem_blowout';
            $severity = 'danger';
            $action = 'technical_drl';
        } elseif ($status === 'broken') {
            $taskCode = 'well_repair';
            $titleKey = 'ww.problem_repair';
            $severity = 'danger';
            $action = 'technical_mnt';
        } elseif ($status === 'contaminated') {
            $taskCode = 'reservoir_rehabilitation';
            $titleKey = 'ww.problem_contamination';
            $severity = 'danger';
            $action = 'technical_res';
        } elseif ($status === 'paused_storage') {
            $titleKey = 'ww.problem_storage';
            $severity = 'warning';
            $action = 'market';
        } elseif ($status === 'paused_cash') {
            $titleKey = 'ww.problem_cash';
            $severity = 'warning';
            $action = 'market';
        } elseif ($status === 'paused_staff') {
            $titleKey = 'ww.problem_staff';
            $severity = 'warning';
            $action = 'hr';
        } elseif (in_array($status, ['no_operator', 'no_technician'], true)) {
            $titleKey = $status === 'no_operator' ? 'ww.problem_operator' : 'ww.problem_technician';
            $severity = 'warning';
            $action = 'hr';
        } elseif ($status === 'servicing') {
            $titleKey = 'ww.problem_servicing';
            $severity = 'warning';
            $action = 'technical_tasks';
        } elseif ($status === 'equipment_swap') {
            $titleKey = 'ww.problem_equipment_swap';
            $severity = 'warning';
            $action = 'equipment';
        } elseif ($status === 'layer_switch') {
            $titleKey = 'ww.problem_layer_switch';
            $severity = 'warning';
            $action = 'geology';
        } elseif ($status === 'seized') {
            $titleKey = 'ww.problem_seized';
            $severity = 'danger';
            $action = 'recovery';
        } elseif ($status === 'active' && $condition < 70.0) {
            $taskCode = 'well_maintenance';
            $titleKey = 'ww.problem_maintenance';
            $severity = $condition < 40.0 ? 'danger' : 'warning';
            $action = 'technical_mnt';
        } elseif ($status !== 'active') {
            $titleKey = 'ww.problem_inactive';
            $severity = 'warning';
            $action = 'technical_tasks';
        }

        $task = $taskCode !== null ? TechnicalTeamService::getTaskDefinition($taskCode) : null;
        return [
            'title_key' => $titleKey,
            'severity' => $severity,
            'action' => $action,
            'task' => $taskCode,
            'hours_min' => isset($task['hours_min']) ? (int)$task['hours_min'] : null,
            'hours_max' => isset($task['hours_max']) ? (int)$task['hours_max'] : null,
            'cost_min' => isset($task['cost_min']) ? (float)$task['cost_min'] : null,
            'cost_max' => isset($task['cost_max']) ? (float)$task['cost_max'] : null,
        ];
    }
}
