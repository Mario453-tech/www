<?php

trait LogisticsPageViewDataTrait
{
    /**
     * Build the complete logistics view contract.
     * Buduje kompletny kontrakt danych widoku logistyki.
     *
     * @param array<string,string> $staffingFlash
     * @return array<string,mixed>
     */
    public function buildViewData(array $staffingFlash = []): array
    {
        $playerId = $this->playerId;
        $db = $this->db;
        $logisticsSvc = $this->logisticsSvc;
        $hubSvc = $this->hubSvc;
        $viewSvc = $this->viewSvc;
        $hubStaffingMgmt = $this->hubStaffingMgmt;
        $pipelineStaffingMgmt = $this->pipelineStaffingMgmt;
        $srcDir = dirname(__DIR__);

        $lockedRegionSet = $this->lockedRegionSet;
        $isLocalRegionLocked = static function ($regionId) use ($lockedRegionSet): bool {
            return isset($lockedRegionSet[(int)$regionId]);
        };

        require __DIR__ . '/ViewData/OverviewAndProtectionData.php';
        $viewData = require __DIR__ . '/ViewData/PipelineAndInsightsData.php';
        foreach (self::MODULE_VIEW_DATA_KEYS as $key) {
            if (!array_key_exists($key, $viewData)) {
                throw new LogicException("Missing logistics view data key: {$key}");
            }
        }

        return $viewData;
    }

    /**
     * Load regions blocked by missing local work permits.
     * Laduje regiony zablokowane przez brak pozwolen lokalnych.
     *
     * @return array<int,bool>
     */
    private function loadLockedRegionSet(): array
    {
        try {
            $enabledRegions = array_map(
                'intval',
                $this->db->query(
                    'SELECT region_id FROM legal_region_config WHERE hub_permit_enabled = 1'
                )->fetchAll(PDO::FETCH_COLUMN)
            );
            if ($enabledRegions === []) {
                return [];
            }

            $statement = $this->db->prepare(
                "SELECT region_id
                   FROM hub_permit_applications
                  WHERE player_id = ?
                    AND status = 'granted'"
            );
            $statement->execute([$this->playerId]);
            $grantedRegions = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));

            return array_fill_keys(
                array_values(array_diff($enabledRegions, $grantedRegions)),
                true
            );
        } catch (Throwable) {
            // Missing permit schema keeps local works visible.
            // Brak schematu pozwolen pozostawia lokalne prace widoczne.
            return [];
        }
    }
}
