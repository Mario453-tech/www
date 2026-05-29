<?php

require_once __DIR__ . '/Hub/ViewSummaryTrait.php';
require_once __DIR__ . '/Hub/ViewHubsTrait.php';

/**
 * HubViewService prepares view-ready data for the Logistics section.
 *
 * Traits:
 * HubViewSummaryTrait getRegionSummary + well grouping helpers
 * HubViewHubsTrait getHubCards, getAlerts, getHubDetail, getAssignableHubs, getAvailableHubsByRegion
 */
class HubViewService
{
    use HubViewSummaryTrait;
    use HubViewHubsTrait;

    private PDO               $db;
    private HubService        $hubSvc;
    private HubEconomyService $econSvc;

    public function __construct(PDO $db, HubService $hubSvc, HubEconomyService $econSvc)
    {
        $this->db      = $db;
        $this->hubSvc  = $hubSvc;
        $this->econSvc = $econSvc;
    }
}

