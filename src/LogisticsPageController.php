<?php

require_once __DIR__ . '/LogisticsPage/ActionsTrait.php';
require_once __DIR__ . '/LogisticsPage/ViewDataTrait.php';
require_once __DIR__ . '/LogisticsService.php';
require_once __DIR__ . '/HubService.php';
require_once __DIR__ . '/HubIncidentService.php';
require_once __DIR__ . '/HubViewService.php';
require_once __DIR__ . '/HubEconomyService.php';
require_once __DIR__ . '/RoadTransportService.php';
require_once __DIR__ . '/TechnicalTeamService.php';
require_once __DIR__ . '/WellPipelineService.php';
require_once __DIR__ . '/PortService.php';
require_once __DIR__ . '/MarineDeliveryService.php';

final class LogisticsPageController
{
    use LogisticsPageActionsTrait;
    use LogisticsPageViewDataTrait;

    public const MODULE_VIEW_DATA_KEYS = [
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
    ];

    private int $playerId;
    private PDO $db;
    private LogisticsService $logisticsSvc;
    private HubService $hubSvc;
    private HubViewService $viewSvc;
    private HubStaffingManagementService $hubStaffingMgmt;
    private PipelineStaffingManagementService $pipelineStaffingMgmt;
    /** @var array<int,bool> */
    private array $lockedRegionSet;

    public function __construct(int $playerId, PDO $db)
    {
        $this->playerId = $playerId;
        $this->db = $db;
        $this->lockedRegionSet = $this->loadLockedRegionSet();

        // Remove orphan voyages left by the legacy micro-delivery flow.
        // Usuwa osierocone rejsy pozostale po starej logice mikro-dostaw.
        MarineDeliveryService::purgeOrphanActiveForPlayer($db, $playerId);

        $this->logisticsSvc = new LogisticsService($playerId);
        $this->hubSvc = new HubService($db);
        $economyService = new HubEconomyService($this->hubSvc);
        $this->viewSvc = new HubViewService($db, $this->hubSvc, $economyService);
        $this->hubStaffingMgmt = new HubStaffingManagementService($db);
        $this->pipelineStaffingMgmt = new PipelineStaffingManagementService($db);
    }
}
