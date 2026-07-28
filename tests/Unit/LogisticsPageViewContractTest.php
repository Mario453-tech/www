<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/LogisticsPageController.php';

final class LogisticsPageViewContractTest extends BaseTestCase
{
    public function testModuleViewDataContractMatchesLegacyController(): void
    {
        self::assertSame([
            'wells',
            'totals',
            'transportMix',
            'efficiency',
            'lossPct',
            'alerts',
            'hubAvailByRegion',
            'hubCards',
            'hubStaffingViewByHub',
            'hubAlerts',
            'hubUnassigned',
            'hubIncidents',
            'playerHubRegions',
            'hubTypeOptions',
            'unassignedPage',
            'unassignedTotalPages',
            'unassignedTotal',
            'pipelines',
            'pipelineStaffingViewByPipeline',
            'pipelineStaffingClientPayload',
            'pipelineSummary',
            'pipelineHse',
            'logisticsInsights',
            'activeRoadTrips',
            'activeRoadTripsTotal',
            'activeRoadTripsPage',
            'activeRoadTripsTotalPages',
            'roadProtectionWells',
            'roadProtectionOptions',
            'hubProtectionTargets',
            'hubProtectionOptions',
            'pipelineProtectionTargets',
            'pipelineProtectionOptions',
            'marineDeliveries',
            'marineBuffers',
            'marineMinLoadBbl',
            'marineHistory',
            'marineInTransitBbl',
            'storageBbl',
            'storagePct',
            'staffingFlash',
        ], LogisticsPageController::MODULE_VIEW_DATA_KEYS);
    }

    public function testViewKeepsAllLegacySectionsInOrder(): void
    {
        $template = (string)file_get_contents(
            dirname(__DIR__, 2) . '/templates/views/logistics/main.php'
        );
        preg_match_all(
            "/require __DIR__ \\. '\\/(?:sections|modals)\\/([^']+\\.php)'/",
            $template,
            $matches
        );

        self::assertSame([
            'flash_kpi.php',
            'flow_section.php',
            'available_hubs_market.php',
            'alerts.php',
            'pipelines_section.php',
            'insights_section.php',
            'transport_mix_section.php',
            'marine_section.php',
            'optimizer_cta.php',
            'transport_table.php',
            'road_trips_section.php',
            'protection_sections.php',
            'owned_hubs_section.php',
            'unassigned_wells_section.php',
            'hub_incidents_section.php',
            'hub_modals.php',
            'optimizer_modal.php',
            'pipeline_modals.php',
            'pipeline_staffing_modal.php',
        ], $matches[1]);
    }

    public function testPublicEntrypointDelegatesActionsAndViewData(): void
    {
        $entrypoint = (string)file_get_contents(
            dirname(__DIR__, 2) . '/public/logistics.php'
        );

        self::assertStringContainsString('$controller->handlePost();', $entrypoint);
        self::assertStringContainsString('$controller->buildViewData($staffingFlash);', $entrypoint);
        self::assertStringNotContainsString('SELECT ', $entrypoint);
        self::assertStringNotContainsString('assignToHub(', $entrypoint);
        self::assertStringNotContainsString('getCurrentSummary(', $entrypoint);
    }
}
