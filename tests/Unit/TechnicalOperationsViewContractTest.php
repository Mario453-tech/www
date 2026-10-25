<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';

final class TechnicalOperationsViewContractTest extends BaseTestCase
{
    private function tab(string $name): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2) . '/templates/views/technical/tabs/' . $name . '.php');
    }

    public function testProductionAndReportDefineTheirOwnWellStatusLabels(): void
    {
        foreach (['prod', 'report'] as $tab) {
            $view = $this->tab($tab);
            self::assertStringNotContainsString('$statusLabels[', $view, $tab);
            self::assertStringContainsString("technical.ws_", $view, $tab);
        }
    }

    public function testGermanTechnicalNavigationAndWellStatusesAreTranslated(): void
    {
        $de = require dirname(__DIR__, 2) . '/lang/de/technical.php';
        $en = require dirname(__DIR__, 2) . '/lang/en/technical.php';
        foreach (['technical.tab_well_staff', 'technical.tab_candidates', 'technical.tab_tasks',
            'technical.tab_wells', 'technical.tab_prod', 'technical.tab_infra', 'technical.tab_incidents',
            'technical.ws_active', 'technical.ws_paused_staff', 'technical.ws_broken',
            'technical.ws_servicing'] as $key) {
            self::assertNotSame($en[$key], $de[$key], $key);
        }
    }

    public function testTeamWellSelectorDoesNotDependOnWellsTabVariables(): void
    {
        $view = $this->tab('team');
        self::assertStringNotContainsString('$statusLabels[', $view);
        self::assertStringContainsString("technical.ws_", $view);
    }

    public function testStaffStateIsRefreshedAfterProcessingTick(): void
    {
        $view = (string)file_get_contents(dirname(__DIR__, 2) . '/src/TechnicalPage/ViewDataTrait.php');
        $tick = strpos($view, '$this->svc->processTick();');
        $refresh = strpos($view, '$staff = $this->svc->getStaff();', $tick ?: 0);
        self::assertNotFalse($tick);
        self::assertNotFalse($refresh);
        self::assertGreaterThan($tick, $refresh);
    }

    public function testWellStateIsRefreshedAfterProcessingTick(): void
    {
        $view = (string)file_get_contents(dirname(__DIR__, 2) . '/src/TechnicalPage/ViewDataTrait.php');
        $tick = strpos($view, '$this->svc->processTick();');
        $refresh = strpos($view, '$wells = $this->wellSvc->getPlayerWells($this->playerId);', $tick ?: 0);
        self::assertNotFalse($tick);
        self::assertNotFalse($refresh);
        self::assertGreaterThan($tick, $refresh);
    }

    public function testActiveTaskDataIsRefreshedAfterProcessingTick(): void
    {
        $view = (string)file_get_contents(dirname(__DIR__, 2) . '/src/TechnicalPage/ViewDataTrait.php');
        $tick = strpos($view, '$this->svc->processTick();');
        $refresh = strpos($view, '$activeTasks = $this->svc->getActiveTasks();', $tick ?: 0);
        self::assertNotFalse($tick);
        self::assertNotFalse($refresh);
        self::assertGreaterThan($tick, $refresh);
    }

    public function testProductionTotalsAndRowsUseTheAuthoritativeEffectiveRate(): void
    {
        $data = (string)file_get_contents(dirname(__DIR__, 2) . '/src/TechnicalPage/ViewDataTrait.php');
        self::assertStringContainsString('$this->wellSvc->getEffectiveProduction($well)', $data);
        self::assertStringContainsString("['_technical_production']", $data);
        $view = $this->tab('prod');
        self::assertStringContainsString("(float)" . '$w' . "['_technical_production']", $view);
        self::assertStringNotContainsString('forecast_30d', $view);
        self::assertStringContainsString('foreach ($wells as $w)', $view);
        self::assertStringContainsString("technical.col_status_hdr", $view);
    }

    public function testWellPressureTooltipUsesEveryLocale(): void
    {
        $view = $this->tab('wells');
        self::assertStringContainsString("technical.pressure_tooltip", $view);
        self::assertStringNotContainsString('title="Baza:', $view);
        foreach (['pl', 'en', 'de'] as $locale) {
            $messages = require dirname(__DIR__, 2) . '/lang/' . $locale . '/technical.php';
            self::assertArrayHasKey('technical.pressure_tooltip', $messages);
        }
    }

    public function testWellCardsUseTheSameEffectiveRateAsProductionTab(): void
    {
        $view = $this->tab('wells');
        self::assertStringContainsString("(float)" . '$w' . "['_technical_production']", $view);
        self::assertStringNotContainsString('base_production_per_hour\'] * $effPressure', $view);
    }

    public function testReportIsShortAndShowsRealTasksAndSafetyStatus(): void
    {
        $view = $this->tab('report');
        self::assertStringContainsString('$activeTasks', $view);
        self::assertStringContainsString('$procStatus', $view);
        self::assertStringContainsString('$reportNeedsAction', $view);
        self::assertStringNotContainsString('cols-staff', $view);
        self::assertStringNotContainsString('foreach ($wells as $w)', $view);
    }

    public function testIncidentCardsCanFilterActiveAndResolvedRows(): void
    {
        $view = $this->tab('incidents');
        self::assertStringContainsString('data-tech-incident-status', $view);
        self::assertStringContainsString('name="inc_status"', $view);
        self::assertStringContainsString('data-incident-status=', $view);
        self::assertStringContainsString('method="get"', $view);
        foreach (['pl', 'en', 'de'] as $locale) {
            $messages = require dirname(__DIR__, 2) . '/lang/' . $locale . '/technical.php';
            self::assertArrayHasKey('technical.incidents_filter_status', $messages);
        }
    }

    public function testGermanTrainingNamesUseGermanTranslationsForBuiltInCourses(): void
    {
        $view = $this->tab('trainings');
        self::assertStringContainsString("'technical.training_program.' . " . '$code', $view);
        self::assertStringContainsString("if ($" . "locale === 'de' && $" . "code !== ''", $view);
        self::assertStringContainsString('$trainingProgramName($cert)', $view);
        $service = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Training/TrainingService.php');
        self::assertStringContainsString('tp.name_en, tp.code, tp.target_skill, tp.duration_hours', $service);
        $messages = require dirname(__DIR__, 2) . '/lang/de/technical.php';
        foreach (['tech_drilling_basic', 'tech_drilling_advanced', 'tech_maintenance_basic',
            'tech_maintenance_advanced', 'tech_safety_basic', 'tech_safety_advanced',
            'tech_analysis_basic', 'tech_analysis_advanced'] as $code) {
            self::assertArrayHasKey('technical.training_program.' . $code, $messages);
        }
    }

    public function testIncidentNavigationCountUsesFilteredTotal(): void
    {
        $view = (string)file_get_contents(dirname(__DIR__, 2) . '/templates/views/technical/main.php');
        self::assertStringContainsString("t('technical.tab_incidents'), $" . "incTotal, 'incidents'", $view);
    }

    public function testIncidentStatusFilterAppliesBeforePagination(): void
    {
        $data = (string)file_get_contents(dirname(__DIR__, 2) . '/src/TechnicalPage/DataTrait.php');
        self::assertStringContainsString("$" . "_GET['inc_status']", $data);
        self::assertStringContainsString('countPlayerIncidents($this->playerId, $incStatus)', $data);
        self::assertStringContainsString('($incPage - 1) * $incPerPage, $incStatus)', $data);
        $view = $this->tab('incidents');
        self::assertStringContainsString("'inc_status' => $" . "incStatus", $view);
    }

    public function testActiveIncidentRepairOpensAssignmentForItsWell(): void
    {
        $view = $this->tab('incidents');
        self::assertStringContainsString("'repair_well' => (int)$" . "inc['well_id'], 'task_type' => 'well_repair'", $view);
        self::assertStringNotContainsString('?>#tech-mnt', $view);
    }

    public function testIncidentCostUsesLocalizedCurrency(): void
    {
        $view = $this->tab('incidents');
        self::assertStringContainsString("t('common.currency')", $view);
        self::assertStringNotContainsString("'technical.inc_cost') ?>: $", $view);
    }

    public function testIncidentStatusRespectsAutomaticExpiryAndRepair(): void
    {
        $view = $this->tab('incidents');
        self::assertStringContainsString("$" . "inc['auto_repair']", $view);
        self::assertStringContainsString("$" . "inc['repaired_at']", $view);
        self::assertStringContainsString("technical.incidents_resolved", $view);
    }

    public function testSafetyDisasterRepairStateRequiresMatchingActiveObjectTask(): void
    {
        $view = $this->tab('safety');
        self::assertStringContainsString("$" . "activeTasks", $view);
        self::assertStringContainsString("technical.disaster_being_repaired", $view);
        self::assertStringContainsString("'pipeline_explosion' => 'pipeline_repair'", $view);
        self::assertStringContainsString("$" . "repairTask['pipeline_id']", $view);
        self::assertStringContainsString("$" . "d['pipeline_id']", $view);
    }

    public function testTaskCancellationUsesExistingAuthorizedPostAction(): void
    {
        $view = $this->tab('tasks');
        self::assertStringContainsString('name="action" value="cancel_task"', $view);
        self::assertStringContainsString('name="_token"', $view);
        self::assertStringContainsString("technical.confirm_cancel_task", $view);
        self::assertStringNotContainsString('Szczegóły', $view);
        self::assertStringNotContainsString('&times;', $view);
        self::assertStringContainsString("['in_progress', 'paused_strike', 'cancelled']", $view);
    }

    public function testPipelineCardIdentifiesItsHub(): void
    {
        $view = $this->tab('infra');
        self::assertStringContainsString("$" . "pipe['hub_id']", $view);
        self::assertStringContainsString('technical.pipe_hub_id', $view);
        self::assertStringContainsString("(int)(" . '$pipe' . "['source_well_id'] ?? 0) > 0", $view);
    }

    public function testPipelineEstimateUsesEffectiveCapacityBeforeApplyingLoss(): void
    {
        $view = $this->tab('infra');
        self::assertStringContainsString('$capacityBbl * (1 - $lossPct / 100)', $view);
        self::assertStringContainsString('number_format($capacityBbl, 1', $view);
        self::assertStringNotContainsString('$pipe[\'nominal_capacity_bph\'] ?? $capacityBbl', $view);
    }

    public function testTeamStatusFilterUsesAuthoritativeWorkerState(): void
    {
        $view = $this->tab('team');
        self::assertStringContainsString("in_array((string)$" . "s['status'], ['active', 'busy'], true)", $view);
        self::assertStringContainsString("['on_strike', 'leaving', 'inactive']", $view);
        self::assertStringContainsString("$" . "displayStatus = !$" . "canWork ? 'unavailable'", $view);
        self::assertStringContainsString('!$isBusy && $canWork && $manager', $view);
        self::assertStringNotContainsString("data-status=\"<?= $" . "isBusy ? 'busy' : 'available' ?>\"", $view);
    }

    public function testSafetyActionsLinkToMatchingTaskAndObject(): void
    {
        $view = $this->tab('safety');
        self::assertStringContainsString("'paused_staff' => 'candidates'", $view);
        self::assertStringContainsString("'paused_cash' => 'market'", $view);
        self::assertStringContainsString("'contaminated' => 'reservoir_rehabilitation'", $view);
        self::assertStringContainsString("'blowout' => 'blowout_control'", $view);
        self::assertStringContainsString("'pipeline_explosion' => 'team'", $view);
        self::assertStringContainsString("'surface_spill' => null", $view);
        self::assertStringContainsString("$" . "disasterParams['repair_pipeline'] = (int)$" . "d['pipeline_id']", $view);
        self::assertStringContainsString("$" . "disasterParams['task_type'] = 'pipeline_repair'", $view);
        self::assertStringContainsString("'repair_well' => (int)$" . "w['id']", $view);
        self::assertStringContainsString("'task_type' => $" . "wellRepairType", $view);
        self::assertStringNotContainsString('$actionAnchor[$stCode] ?? \'tech-mnt\'', $view);
    }

    public function testDisasterHintsDescribeTheActualDestinationInEveryLocale(): void
    {
        foreach (['pl', 'en', 'de'] as $locale) {
            $messages = require dirname(__DIR__, 2) . '/lang/' . $locale . '/technical.php';
            foreach (['blowout', 'contamination', 'pipeline'] as $kind) {
                self::assertStringNotContainsString($messages['technical.tab_tasks'], $messages['technical.repair_hint_' . $kind], $locale . ':' . $kind);
            }
            self::assertStringNotContainsString($messages['technical.tab_tasks'], $messages['technical.repair_hint_spill'], $locale . ':spill');
        }
    }

    public function testDisasterWellCardActionsOpenAssignmentForThatWell(): void
    {
        $view = $this->tab('wells');
        self::assertStringNotContainsString('href="?tab=tasks"', $view);
        self::assertStringContainsString("'repair_well' => " . '$wId' . ", 'task_type' => 'blowout_control'", $view);
        self::assertStringContainsString("'repair_well' => " . '$wId' . ", 'task_type' => 'reservoir_rehabilitation'", $view);
    }

    public function testPipelineCardLinksToItsOwnRepairAssignment(): void
    {
        $view = $this->tab('infra');
        self::assertStringContainsString("'repair_pipeline' => (int)$" . "pipe['id']", $view);
        self::assertStringContainsString("'task_type' => 'pipeline_repair'", $view);
        $script = (string)file_get_contents(dirname(__DIR__, 2) . '/assets/js/technical.js');
        self::assertStringContainsString("params.get('repair_pipeline')", $script);
        self::assertStringContainsString("'pipeline_repair': 'pipeline_engineer'", $script);
        $main = (string)file_get_contents(dirname(__DIR__, 2) . '/templates/views/technical/main.php');
        self::assertStringContainsString("'spec_pipeline_engineer'", $main);
        self::assertStringContainsString("select[name=\"pipeline_id\"]", $script);
    }

    public function testPipelineRepairShowsOnlyItsOwnActiveTaskInsideItsCard(): void
    {
        $view = $this->tab('infra');
        self::assertStringContainsString("$" . "task['pipeline_id']", $view);
        self::assertStringContainsString("$" . "pipe['id']", $view);
        self::assertStringContainsString('technical.active_tasks_label', $view);
        self::assertStringContainsString('technical.task_remaining', $view);
        self::assertStringContainsString('well-task-row', $view);
        self::assertStringNotContainsString('technical.pipe_build_cost', $view);
    }
}
