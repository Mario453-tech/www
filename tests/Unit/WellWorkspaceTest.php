<?php
declare(strict_types=1);
require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/WellService.php';
require_once dirname(__DIR__, 2) . '/src/WellWorkspaceData.php';
final class WellWorkspaceTest extends BaseTestCase
{
    public function testWorkspaceHasSixAccessiblePanelsWithoutInlineHandlers(): void
    {
        $path = dirname(__DIR__, 2) . '/templates/views/wells/main.php';
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertStringContainsString('role="tablist"', $source);
        self::assertStringContainsString('role="tabpanel"', $source);
        self::assertStringContainsString('data-well-select', $source);
        self::assertStringContainsString('tabindex="-1"', $source);
        $transportSource = file_get_contents(dirname(__DIR__, 2) . '/templates/views/wells/transport.php');
        self::assertStringContainsString('_pipelineBuildCost', $transportSource);
        self::assertStringContainsString("\$reason === '' && \$code === 'rurociag'", $transportSource);
        self::assertStringContainsString("t('ww.well')", file_get_contents(dirname(__DIR__, 2) . '/templates/views/wells/overview.php'));
        $css = file_get_contents(dirname(__DIR__, 2) . '/assets/css/well-workspace.css');
        self::assertStringContainsString('.ww-history-list', $css);
        self::assertStringContainsString('.ww-overview-grid', $css);
        self::assertStringContainsString('.ww-section-heading', $css);
        self::assertStringContainsString('.ww-page-number', $css);
        self::assertStringContainsString('.ww-message--error', $css);
        self::assertDoesNotMatchRegularExpression('/(?:onclick|style)=/', $source);
        self::assertStringNotContainsString('Szybki podgląd', $source);
        self::assertStringContainsString('ww-selector-status', $source);
        $historySource = file_get_contents(dirname(__DIR__, 2) . '/templates/views/wells/history.php');
        self::assertStringContainsString('role="status"', $historySource);
        self::assertStringContainsString('aria-live="polite"', $historySource);
    }

    public function testDisplayedEquipmentCostsComeFromTheActionCatalog(): void
    {
        self::assertTrue(method_exists(WellService::class, 'equipmentPrices'), 'Missing shared equipment price catalog');
        self::assertSame([1 => 1000000, 2 => 2500000, 3 => 5000000], WellService::equipmentPrices()['levels']);
        self::assertSame(8000000, WellService::equipmentPrices()['tiers']['premium']);
    }

    public function testDashboardAlertLinksUseTheNewWellSelectorWithoutInlineHandlers(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/templates/views/index/main.php');
        self::assertStringContainsString('data-well-jump=', $source);
        self::assertStringNotContainsString('wgFocusWell(', $source);
        self::assertDoesNotMatchRegularExpression('/onclick=/', $source);
        self::assertStringNotContainsString('window.confirm(', file_get_contents(dirname(__DIR__, 2) . '/assets/js/well-workspace.js'));
    }

    public function testWorkspaceTranslationsAreLoadedAndCompleteInPolishEnglishAndGerman(): void
    {
        $root = dirname(__DIR__, 2);
        $pl = require $root . '/lang/pl.php';
        $en = require $root . '/lang/en.php';
        $de = require $root . '/lang/de.php';
        $plModule = require $root . '/lang/pl/well_workspace.php';
        $enModule = require $root . '/lang/en/well_workspace.php';
        $deModule = require $root . '/lang/de/well_workspace.php';
        self::assertSame('Zarządzanie wydobyciem', $pl['ww.eyebrow'] ?? null);
        self::assertSame('Production management', $en['ww.eyebrow'] ?? null);
        self::assertSame('Produktionsverwaltung', $de['ww.eyebrow'] ?? null);
        self::assertSame('Historia', $pl['ww.history'] ?? null);
        self::assertSame('History', $en['ww.history'] ?? null);
        self::assertSame('Verlauf', $de['ww.history'] ?? null);
        self::assertSame(array_keys($plModule), array_keys($enModule));
        self::assertSame(array_keys($plModule), array_keys($deModule));
    }

    public function testWorkspaceIconsRemainThemableWithCurrentColor(): void
    {
        $icons = glob(dirname(__DIR__, 2) . '/assets/img/icons/wells/*.svg') ?: [];
        self::assertCount(27, $icons);
        foreach ($icons as $icon) {
            $source = file_get_contents($icon);
            self::assertStringContainsString('currentColor', $source, basename($icon));
            self::assertDoesNotMatchRegularExpression('/(?:fill|stroke)="#[0-9a-f]{3,8}"/i', $source, basename($icon));
        }
    }

    public function testOverviewUsesRealTechnicalTaskRangesForThePrimaryProblemCard(): void
    {
        $dataSource = file_get_contents(dirname(__DIR__, 2) . '/src/WellWorkspaceData.php');
        $overviewSource = file_get_contents(dirname(__DIR__, 2) . '/templates/views/wells/overview.php');
        self::assertStringContainsString('TechnicalTeamService::getTaskDefinition', $dataSource);
        self::assertStringNotContainsString('TechnicalTeamService::TASKS[', $dataSource);
        self::assertStringContainsString("['_problem']", $dataSource);
        self::assertStringContainsString("t('ww.problem')", $overviewSource);
        self::assertStringContainsString("t('ww.estimated_time')", $overviewSource);
        self::assertStringContainsString("t('ww.estimated_cost')", $overviewSource);
        self::assertStringContainsString("'task_type' => \$problem['task']", $overviewSource);
        $mainSource = file_get_contents(dirname(__DIR__, 2) . '/templates/views/wells/main.php');
        self::assertStringContainsString("'task_type' => \$headerTask", $mainSource);
        self::assertStringNotContainsString("repair_well' => \$wid])", $mainSource);
        $technicalJs = file_get_contents(dirname(__DIR__, 2) . '/assets/js/technical.js');
        self::assertStringContainsString("params.get('task_type')", $technicalJs);
        self::assertStringContainsString("'blowout_control': 'drilling_engineer'", $technicalJs);
        self::assertStringContainsString("'reservoir_rehabilitation': 'reservoir_engineer'", $technicalJs);
        self::assertStringNotContainsString("techl('repair_no_mnt')", $technicalJs);
        self::assertStringNotContainsString("techl('repair_mnt_busy')", $technicalJs);
    }

    public function testEveryKnownInactiveStatusGetsATruthfulProblemAction(): void
    {
        $cases = [
            'active' => ['equipment', null],
            'paused_storage' => ['market', null],
            'paused_cash' => ['market', null],
            'paused_staff' => ['hr', null],
            'no_operator' => ['hr', null],
            'no_technician' => ['hr', null],
            'broken' => ['technical_mnt', 'well_repair'],
            'blowout' => ['technical_drl', 'blowout_control'],
            'contaminated' => ['technical_res', 'reservoir_rehabilitation'],
            'seized' => ['recovery', null],
            'equipment_swap' => ['equipment', null],
            'layer_switch' => ['geology', null],
            'servicing' => ['technical_tasks', null],
        ];
        foreach ($cases as $status => [$action, $task]) {
            $problem = WellWorkspaceData::problemForWell(['_status' => $status, '_cond' => 90]);
            self::assertSame($action, $problem['action'], $status);
            self::assertSame($task, $problem['task'], $status);
            if ($status !== 'active') self::assertNotSame('ww.no_urgent_problem', $problem['title_key'], $status);
        }
        self::assertSame('well_maintenance', WellWorkspaceData::problemForWell(['_status' => 'active', '_cond' => 50])['task']);
    }

    public function testHistoryEventsExposeAndRenderAControlledTypeIcon(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertStringContainsString("'icon' =>", file_get_contents($root . '/public/well_history.php'));
        self::assertStringContainsString('ww-history-event-icon', file_get_contents($root . '/assets/js/well-workspace.js'));
        self::assertStringContainsString('.ww-history-event-icon', file_get_contents($root . '/assets/css/well-workspace.css'));
    }

    public function testHistoryQueuesTheLatestCompleteRequestAndDisablesStaleControls(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/assets/js/well-workspace.js');
        self::assertStringContainsString('pendingRequest: null', $source);
        self::assertStringContainsString('state.pendingRequest = request', $source);
        self::assertStringContainsString('loadHistory(well, pendingRequest.page, pendingRequest.filter)', $source);
        self::assertStringContainsString('retry.disabled = state.loading', $source);
        self::assertStringNotContainsString('pendingPage', $source);
        self::assertStringContainsString('data-history-pages', $source);
        self::assertStringContainsString('total_pages', $source);
    }
}
