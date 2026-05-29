<?php

/**
 * WellGridData — prepares all data needed by the well_grid component template.
 */
class WellGridData
{
    /**
     * @param array      $wells      Raw well rows from the DB / controller
     * @param array      $playerData Player row (cash, bankruptcy_status, …)
     * @param mixed|null $storage    Storage object (has getData() method) or null
     *
     * @return array  Keys: groups, storageData, storageCap, storageAfter, upgradeCost,
     *                      canAffordUpg, playerCash, statusMap, specNames, regionIcons,
     *                      showWells, glAll
     */
    public static function prepare(array $wells, array $playerData, $storage): array
    {
        //  Status map 
        $statusMap = [
            'active'         => [t('technical.ws_active'),          'wbadge--active',  ''],
            'paused_storage' => [t('well.status.paused_storage'),   'wbadge--warn',    ''],
            'paused_cash'    => [t('well.status.paused_cash'),      'wbadge--danger',  ''],
            'paused_staff'   => [t('technical.ws_paused_staff'),    'wbadge--warn',    ''],
            'no_operator'    => [t('technical.ws_no_operator'),     'wbadge--danger',  ''],
            'no_technician'  => [t('technical.ws_no_technician'),   'wbadge--warn',    ''],
            'broken'         => [t('map_js.well_status_broken'),    'wbadge--danger',  ''],
            'blowout'        => [t('technical.ws_blowout'),         'wbadge--danger',  ''],
            'contaminated'   => [t('technical.ws_contaminated'),    'wbadge--danger',  ''],
            'seized'         => [t('technical.ws_seized'),          'wbadge--danger',  ''],
            'equipment_swap' => [t('well.status.equipment_swap'),   'wbadge--warn',    ''],
        ];

        //  Specialist names 
        $specNames = [
            'safety_officer'       => t('hr.spec.safety_officer'),
            'safety_engineer'      => t('hr.spec.safety_engineer'),
            'maintenance_engineer' => t('hr.spec.maintenance_engineer'),
            'production_engineer'  => t('hr.spec.production_engineer'),
        ];

        //  Filter wells by bankruptcy status 
        $playerStatus = $playerData['bankruptcy_status'] ?? 'none';
        $showWells = in_array($playerStatus, ['none', 'recovered'])
            ? array_filter($wells, fn($w) => !in_array($w['status'] ?? '', ['seized', 'sold']))
            : array_filter($wells, fn($w) => ($w['status'] ?? '') !== 'sold');

        //  Storage computations 
        $storageData  = isset($storage) ? $storage->getData() : null;
        $storageCap   = $storageData ? (float)$storageData['capacity'] : 0;
        $upgradeCost  = $storageCap * 50;
        $storageAfter = $storageCap + 100;
        $playerCash   = (float)($playerData['cash'] ?? 0);
        $canAffordUpg = $playerCash >= $upgradeCost && $upgradeCost > 0;

        //  Region icons 
        $regionIcons = [
            'middle_east'    => '',
            'russia'         => '',
            'africa'         => '',
            'usa_canada'     => '',
            'north_europe'   => '',
            'southeast_asia' => '',
            'latam'          => '',
            ''               => '',
        ];

        //  Geological layers (loaded once for all wells) 
        $glAll = class_exists('GeologicalLayerService')
            ? (new GeologicalLayerService())->getAllLayers()
            : [];

        // Preload owned pipelines for all visible wells.
        // Preload zakupionych rurociagow dla wszystkich widocznych odwiertow.
        $pipelineByWell = [];
        $hubAssignmentByWell = [];
        if (!empty($showWells)) {
            $wellIds = array_values(array_filter(array_map(static fn($w): int => (int)($w['id'] ?? 0), $showWells)));
            if ($wellIds !== []) {
                try {
                    $db = Database::getInstance()->getConnection();
                    $pipelineSvc = new WellPipelineService($db);
                    $playerId = (int)($playerData['id'] ?? 0);
                    if ($playerId > 0) {
                        $pipelineByWell = $pipelineSvc->getByPlayerAndWellIds($playerId, $wellIds);
                    }
                } catch (Throwable $e) {
                    GameLog::error('WellGridData', 'pipeline preload FAILED', $e);
                }

                try {
                    $db = Database::getInstance()->getConnection();
                    $placeholders = implode(',', array_fill(0, count($wellIds), '?'));
                    $stmt = $db->prepare(
                        "SELECT a.well_id, a.hub_id, h.name AS hub_name
                           FROM logistics_hub_assignments a
                           JOIN logistics_hubs h ON h.id = a.hub_id
                          WHERE a.status = 'active'
                            AND a.well_id IN ({$placeholders})"
                    );
                    $stmt->execute($wellIds);
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $hubAssignmentByWell[(int)($row['well_id'] ?? 0)] = $row;
                    }
                } catch (Throwable $e) {
                    GameLog::error('WellGridData', 'hub assignment preload FAILED', $e);
                }
            }
        }

        //  Group wells by region, enriching each well 
        $groups = [];
        foreach ($showWells as $w) {
            $regionKey   = !empty($w['region_name']) ? $w['region_name'] : t('wg.no_location');
            $regionColor = $w['region_color'] ?? '#c8a84b';
            if (!isset($groups[$regionKey])) {
                $groups[$regionKey] = [
                    'wells' => [],
                    'color' => $regionColor,
                    'code'  => $w['region_code'] ?? '',
                ];
            }
            $groups[$regionKey]['wells'][] = self::prepareWell($w, $statusMap, $specNames, $playerCash, $pipelineByWell, $hubAssignmentByWell);
        }
        ksort($groups);

        return [
            'groups'       => $groups,
            'storageData'  => $storageData,
            'storageCap'   => $storageCap,
            'storageAfter' => $storageAfter,
            'upgradeCost'  => $upgradeCost,
            'canAffordUpg' => $canAffordUpg,
            'playerCash'   => $playerCash,
            'statusMap'    => $statusMap,
            'specNames'    => $specNames,
            'regionIcons'  => $regionIcons,
            'showWells'    => $showWells,
            'glAll'        => $glAll,
        ];
    }

    /**
     * Enriches a single well row with all computed display data.
     */
    private static function prepareWell(array $w, array $statusMap, array $specNames, float $playerCash, array $pipelineByWell, array $hubAssignmentByWell): array
    {
        //  Status & condition 
        $status   = $w['status'] ?? 'active';
        $st       = $statusMap[$status] ?? [ucfirst($status), 'wbadge--warn', ''];
        $isActive = $status === 'active';
        $cond     = (float)($w['technical_condition'] ?? 100);
        $condCls  = $cond >= 70 ? 'cg-good' : ($cond >= 40 ? 'cg-warn' : 'cg-bad');

        //  Pressure & reservoir 
        $wEffPress = class_exists('WellService')
            ? WellService::getEffectivePressure($w)
            : ['effective' => (float)($w['pressure'] ?? 1.0), 'depletion' => 1.0];
        $wEffPct   = round($wEffPress['effective'] * 100, 1);
        $wEffCls   = $wEffPct >= 80 ? 'cv-good' : ($wEffPct >= 50 ? 'cv-warn' : 'cv-bad');
        $wResPct   = (float)($w['reservoir_max'] ?? 0) > 0
            ? round((float)($w['reservoir_remaining'] ?? 0) / (float)($w['reservoir_max']) * 100, 1)
            : 0;
        $wResCls   = $wResPct >= 40 ? 'cv-good' : ($wResPct >= 20 ? 'cv-warn' : 'cv-bad');

        //  Missing specialists 
        $missingSpecs = [];
        if ($status === 'paused_staff') {
            if (!empty($w['paused_staff_reason'])) {
                foreach (explode(',', $w['paused_staff_reason']) as $code) {
                    $code = trim($code);
                    if ($code) $missingSpecs[] = $specNames[$code] ?? $code;
                }
            } else {
                $missingSpecs = array_values($specNames);
            }
        }

        $spiralBoost = (float)($w['post_incident_risk_boost'] ?? 0);

        //  Transport 
        $wellId     = (int)($w['id'] ?? 0);
        $wellType   = $w['well_type'] ?? 'onshore';
        $isOffshore = ($wellType === 'offshore');
        $defaultTransport = $isOffshore ? 'tankowiec' : 'nieustawiony';
        $trType     = (string)($w['transport_type'] ?? $defaultTransport);
        $trCapPct   = (float)($w['transport_capacity_pct'] ?? ($trType === 'nieustawiony' ? 0.0 : 120.0));
        $trOpexPct  = (float)($w['transport_opex_pct']     ?? ($trType === 'nieustawiony' ? 0.0 : 7.5));
        $ownedPipeline = $pipelineByWell[$wellId] ?? null;
        $hubAssignment = $hubAssignmentByWell[$wellId] ?? null;
        $hasOwnedPipeline = $ownedPipeline !== null;
        $hasHubAssignment = $hubAssignment !== null;
        $pipelineStatus = (string)($ownedPipeline['status'] ?? '');
        $hasOperationalPipeline = (bool)($ownedPipeline['_is_operational'] ?? ($hasOwnedPipeline && $pipelineStatus !== 'building'));
        $legacyPipelineSelection = (!$isOffshore && $trType === 'rurociag' && !$hasOwnedPipeline);
        $pipelineBuildCost = (float)($ownedPipeline['build_cost'] ?? 0.0);
        if ($pipelineBuildCost <= 0.0) {
            try {
                $db = Database::getInstance()->getConnection();
                $pipelineSvc = new WellPipelineService($db);
                $pipelineBuildCost = (float)$pipelineSvc->getProfile('standard')['build_cost'];
            } catch (Throwable $e) {
                $pipelineBuildCost = 18000.0;
            }
        }
        $displayTransportType = $legacyPipelineSelection ? 'nieustawiony' : $trType;
        $effectiveTransportType = $displayTransportType;
        if (!$isOffshore && $displayTransportType === 'rurociag' && !$hasOperationalPipeline) {
            $effectiveTransportType = $hasOwnedPipeline ? 'ciezarowki' : 'nieustawiony';
        }
        $pipelineProfile = ['capacity_pct' => 120.0];
        try {
            $db = Database::getInstance()->getConnection();
            $pipelineSvc = new WellPipelineService($db);
            $pipelineProfile = $pipelineSvc->getProfile((string)($ownedPipeline['pipeline_type'] ?? $w['pipeline_type'] ?? 'standard'));
        } catch (Throwable $e) {
            $pipelineProfile = ['capacity_pct' => 120.0];
        }
        $pipelineCapacityPct = (float)($pipelineProfile['capacity_pct'] ?? 120.0);
        $trDefs = [
            'nieustawiony' => [t('wg.transport_unset_label'), 'cv-bad',    0.0,   0.0, t('wg.transport_unset_desc'), !$isOffshore],
            'rurociag'   => [t('wg.transport_pipe_label'),  'cv-good', $pipelineCapacityPct,  7.5, t('wg.transport_pipe_desc'),  !$isOffshore],
            'ciezarowki' => [t('wg.transport_truck_label'), 'cv-warn',  70.0, 20.0, t('wg.transport_truck_desc'), !$isOffshore],
            'tankowiec'  => [t('wg.transport_ship_label'),  'cv-good', 110.0, 12.0, t('wg.transport_ship_desc'),  $isOffshore],
        ];
        $displayTrCapPct = $displayTransportType === 'nieustawiony' ? 0.0 : $trCapPct;
        $displayTrOpexPct = $displayTransportType === 'nieustawiony' ? 0.0 : $trOpexPct;
        $trLabel = $trDefs[$effectiveTransportType][0] ?? t('wg.transport_unset_label');
        $trCls   = $trDefs[$effectiveTransportType][1] ?? 'cv-bad';

        //  Equipment 
        $eqTier  = $w['equipment_tier'] ?? 'standard';
        $eqLevel = (int)($w['equipment_upgrade_level'] ?? 0);
        $eqMults = (class_exists('WellService') && method_exists('WellService', 'getEquipmentMultipliers'))
            ? WellService::getEquipmentMultipliers($eqTier, $eqLevel)
            : ['prod' => 1.0, 'incident' => 1.0, 'wear' => 1.0, 'spiral' => 1.0];
        $tierLabel = match($eqTier) {
            'black_market' => [t('wg.eq_tier_bm_label'),   'cv-bad'],
            'premium'      => [t('wg.eq_tier_prem_label'),  'cv-good'],
            default        => [t('wg.eq_tier_std_label'),   'cv-warn'],
        };
        $tierCosts   = ['black_market' => 500_000, 'standard' => 2_000_000, 'premium' => 8_000_000];
        $upgCosts    = [1 => 30_000_000, 2 => 60_000_000, 3 => 100_000_000];
        $nextUpgCost = $eqLevel < 3 ? ($upgCosts[$eqLevel + 1] ?? null) : null;

        //  Geological layer 
        $glCur             = null;
        $glCurId           = (int)($w['active_layer_id'] ?? 1);
        $glSwitchHoursLeft = 0;
        if (class_exists('GeologicalLayerService')) {
            $glSvc = new GeologicalLayerService();
            $glCur = $glSvc->getActiveLayer((int)$w['id']);
            $glSwitchUntil = $w['layer_switch_until'] ?? null;
            if (!empty($glSwitchUntil)) {
                $ts = strtotime((string)$glSwitchUntil);
                if ($ts !== false && $ts > time()) {
                    $glSwitchHoursLeft = (int)ceil(($ts - time()) / 3600);
                }
            }
        }

        return array_merge($w, [
            '_status'             => $status,
            '_st'                 => $st,
            '_isActive'           => $isActive,
            '_cond'               => $cond,
            '_condCls'            => $condCls,
            '_wEffPct'            => $wEffPct,
            '_wEffCls'            => $wEffCls,
            '_wResPct'            => $wResPct,
            '_wResCls'            => $wResCls,
            '_missingSpecs'       => $missingSpecs,
            '_spiralBoost'        => $spiralBoost,
            '_isOffshore'         => $isOffshore,
            '_trType'             => $displayTransportType,
            '_trTypeStored'       => $trType,
            '_trTypeEffective'    => $effectiveTransportType,
            '_trCapPct'           => $displayTrCapPct,
            '_trOpexPct'          => $displayTrOpexPct,
            '_trDefs'             => $trDefs,
            '_trLabel'            => $trLabel,
            '_trCls'              => $trCls,
            '_transportSelectionRequired' => (!$isOffshore && $displayTransportType === 'nieustawiony'),
            '_hasHubAssignment'   => $hasHubAssignment,
            '_hubAssignment'      => $hubAssignment,
            '_hubName'            => (string)($hubAssignment['hub_name'] ?? ''),
            '_pipelineOwned'      => $hasOwnedPipeline,
            '_pipelineOperational'=> $hasOperationalPipeline,
            '_pipelineBindingInvalid' => (!$isOffshore && $displayTransportType === 'rurociag' && $hasOwnedPipeline && !$hasOperationalPipeline),
            '_pipelineBuilding'   => $pipelineStatus === 'building',
            '_legacyPipelineSelection' => $legacyPipelineSelection,
            '_pipelineBuildCost'  => $pipelineBuildCost,
            '_pipelineData'       => $ownedPipeline,
            '_eqTier'             => $eqTier,
            '_eqLevel'            => $eqLevel,
            '_eqMults'            => $eqMults,
            '_tierLabel'          => $tierLabel,
            '_tierCosts'          => $tierCosts,
            '_upgCosts'           => $upgCosts,
            '_nextUpgCost'        => $nextUpgCost,
            '_glCur'              => $glCur,
            '_glCurId'            => $glCurId,
            '_glSwitchHoursLeft'  => $glSwitchHoursLeft,
        ]);
    }
}

