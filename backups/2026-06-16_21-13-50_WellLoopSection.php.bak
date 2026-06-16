<?php
require_once __DIR__ . '/../FinancePolicyService.php';
require_once __DIR__ . '/../OutboundLegService.php';
require_once __DIR__ . '/WellProductionSection.php';
require_once __DIR__ . '/WellHubSection.php';

/**
 * WellLoopSection - petla odwiertow gracza: produkcja, OPEX, transport i zdarzenia.
 * WellLoopSection - player well loop: production, OPEX, transport and events.
 *
 * Deleguje logike do: / Delegates logic to:
 * WellProductionSection - przetwarzanie per odwiert (produkcja, OPEX, transport, incydenty)
 * - per-well processing (production, OPEX, transport, incidents)
 * WellHubSection - finalizacja hubow (wear, reconciliacja strat, OPEX hubow)
 * - hub finalization (wear, loss reconciliation, hub OPEX)
 */
class WellLoopSection
{
 // Wyniki petli (eksponowane do PlayersSection) / Loop results (exposed to PlayersSection)
    public float $finRevenue             = 0.0;
    public float $finOpex                = 0.0;
    public float $finSalary              = 0.0;
    public float $finTransport           = 0.0;
    public float $finIncident            = 0.0;
    public float $finTax                 = 0.0;
    public float $finBbl                 = 0.0;
    public float $finGross               = 0.0;
    public float $finLossBbl             = 0.0;
    public float $finLossValue           = 0.0;
    public float $finHubUsageCost        = 0.0;
    public float $finHubLossBbl          = 0.0;
    public float $finHubLossValue        = 0.0;
    public float $finFallbackLossBbl     = 0.0;
    public float $finFallbackLossValue   = 0.0;
    public float $finHubIncidentLossBbl  = 0.0;
    public float $finHubIncidentLossValue = 0.0;
    public int   $finWellsActive         = 0;
    public int   $disastersTriggered     = 0;
    public int   $incidentsTriggered     = 0;
    public float $producedBbl            = 0.0;
    public float $deliveredBbl           = 0.0;
    public float $preStorageLossBbl      = 0.0;
    public float $transportLossBbl       = 0.0;
    public float $transportEventLossBbl  = 0.0;
    public float $transportCapacityLossBbl = 0.0;
    public float $storageBlockedBbl        = 0.0;
    public float $currentStorage           = 0.0;
    public float $storageCapacity          = 0.0;
    public float $playerCash               = 0.0;
 // Stage 5: oil dispatched by sea and not yet delivered
 // Etap 5: ropa wyslana morzem i jeszcze niedostarczona
    public float $marineInTransitBbl       = 0.0;
 // P1.2: oil dispatched by road and not yet delivered
 // P1.2: ropa wyslana ciezarowkami i jeszcze niedostarczona
    public float $roadInTransitBbl         = 0.0;

 // Stan hubow - wspoldzielony z WellProductionSection i WellHubSection
 // Hub state - shared with WellProductionSection and WellHubSection
 /** @var array<int, int> well_id -> hub_id for this player's assigned wells */
    public array $wellHubMap    = [];
 /** @var array<int, array<string, mixed>> hub_id -> full hub row */
    public array $hubCache      = [];
 /** @var array<int, float> hub_id -> accumulated oil input (bbl) for this tick */
    public array $hubInputAccum = [];
 // ETAP 11: second transport leg (hub -> storage), tracked per hub.
 /** @var array<int, float> well_id -> bbl delivered to storage through its hub this tick */
    public array $hubWellDelivered = [];
 /** @var array<int, string> hub_id -> outbound_transport_type (leg 2 choice per hub) */
    public array $hubOutboundType = [];
 /** @var array<int, array<string, mixed>> hub_id -> outbound pipeline row (leg='outbound') */
    public array $hubOutboundPipelineCache = [];
 // Second-leg transport losses (already folded into finLossBbl/finLossValue; kept for reporting).
    public float $finOutboundLossBbl   = 0.0;
    public float $finOutboundLossValue = 0.0;

    private PDO         $db;
    private DateTime    $now;
    private float       $oilPrice;
 /** @var array<string, mixed> */
    private array       $gBalanceMults;
    private WellService $wellService;

 // Cache preloadowanych danych per gracz / Preloaded data cache per player
 /** @var array<int, array<string, mixed>> preloaded technical_staff indexed by id */
    private array $staffCache = [];
 /** @var array<int, array<string, mixed>> preloaded well pipelines indexed by well_id */
    private array $wellPipelineCache = [];
 /** @var ?GeologicalLayerService reused per player loop */
    private ?object $geoSvc = null;
 /** @var ?IncidentService reused per player loop */
    private ?object $incidentSvc = null;
 /** @var array<string, array<string, float>> konfiguracja transportu per typ (z transport_config lub domyslna) / transport config per type (from transport_config or default) */
    private array $transportConfig = [];
    private ?FinancePolicyService $financePolicySvc = null;
 /** @var array<string, float|string> */
    private array $financeTechnicalMods = [];
 /** @var array<string, float|string> */
    private array $financeLogisticsMods = [];
 /** @var array<string, float|string> */
    private array $financeSafetyMods = [];

 // Hub logistics - initialized in constructor, null if module not installed
 /** @var ?HubService */
    private ?HubService          $hubSvc               = null;
 /** @var ?HubTickService */
    private ?HubTickService      $hubTickSvc           = null;
 /** @var ?HubIncidentService */
    private ?HubIncidentService  $hubIncidentSvc       = null;
    private ?WellPipelineService        $wellPipelineSvc      = null;
    private ?RoadTransportService       $roadTransportSvc     = null;
    private ?OffshoreTransportService   $offshoreTransportSvc = null;
    private ?MarineDeliveryService      $marineDeliverySvc    = null; // Etap 5
 /** @var array<int, array<string, mixed>> preloaded road configs indexed by well_id */
    private array $roadConfigCache = [];
 /** @var array<int, array<string, mixed>> preloaded offshore configs indexed by well_id */
    private array $offshoreConfigCache = [];

 /** @param array<string, mixed> $gBalanceMults */
    public function __construct(
        PDO         $db,
        DateTime    $now,
        float       $oilPrice,
        array       $gBalanceMults,
        WellService $wellService
    ) {
        $this->db            = $db;
        $this->now           = $now;
        $this->oilPrice      = $oilPrice;
        $this->gBalanceMults = $gBalanceMults;
        $this->wellService   = $wellService;

 // Zaladuj cost_per_bbl z transport_config (globalny config admina) / Load cost_per_bbl from transport_config (global admin config)
        try {
            $this->transportConfig = TransportConfigService::load($db);
        } catch (Throwable $e) {
 // Tabela opcjonalna - uzywamy wartosci domyslnych. / Table is optional - using defaults.
        }

        if (class_exists('FinancePolicyService')) {
            try {
                $this->financePolicySvc = new FinancePolicyService($db);
            } catch (Throwable $e) {
                GameLog::error('tick', 'WellLoopSection: FinancePolicyService init FAILED', $e);
            }
        }

 // Hub logistics - optional module, fail gracefully if not installed.
        if (class_exists('HubService') && class_exists('HubTickService')) {
            try {
                $this->hubSvc     = new HubService($db);
                $this->hubTickSvc = new HubTickService($db, $this->hubSvc);
            } catch (Throwable $e) {
                GameLog::error('tick', 'WellLoopSection: hub services init FAILED', $e);
            }
        }
        if (class_exists('HubIncidentService')) {
            try {
                $this->hubIncidentSvc = new HubIncidentService($db);
            } catch (Throwable $e) {
                GameLog::error('tick', 'WellLoopSection: HubIncidentService init FAILED', $e);
            }
        }
        if (class_exists('WellPipelineService')) {
            try {
                $this->wellPipelineSvc = new WellPipelineService($db);
            } catch (Throwable $e) {
                GameLog::error('tick', 'WellLoopSection: WellPipelineService init FAILED', $e);
            }
        }
        if (class_exists('RoadTransportService')) {
            try {
                $this->roadTransportSvc = new RoadTransportService($db);
            } catch (Throwable $e) {
                GameLog::error('tick', 'WellLoopSection: RoadTransportService init FAILED', $e);
            }
        }
        if (class_exists('OffshoreTransportService')) {
            try {
                $this->offshoreTransportSvc = new OffshoreTransportService($db);
            } catch (Throwable $e) {
                GameLog::error('tick', 'WellLoopSection: OffshoreTransportService init FAILED', $e);
            }
        }
 // Etap 5: dostawy morskie w czasie / Etap 5: time-based marine deliveries
        if (class_exists('MarineDeliveryService')) {
            try {
                $this->marineDeliverySvc = new MarineDeliveryService($db);
            } catch (Throwable $e) {
                GameLog::error('tick', 'WellLoopSection: MarineDeliveryService init FAILED', $e);
            }
        }
    }

 /**
 * Uruchamia petle odwiertow dla jednego gracza.
 * Runs the well loop for a single player.
 *
 * @param list<array<string, mixed>> $wells
 * @param array<string, mixed> $hseBonus
 * @param array<string, mixed> $staffCheck
 * @param list<array<string, mixed>> $activeRegEvents
 */
    public function run(
        int     $playerId,
        array   $wells,
        float   $playerCash,
        float   $currentStorage,
        float   $storageCapacity,
        float   $deltaHours,
        array   $hseBonus,
        array   $staffCheck,
        float   $offlineProdMult,
        float   $offlineRiskMult,
        ?object $tsvc,
        ?object $regionalSvc,
        array   $activeRegEvents
    ): void {
        $this->playerCash     = $playerCash;
        $this->currentStorage = $currentStorage;
        $this->storageCapacity = $storageCapacity;
        $this->preloadFinancePolicies($playerId);

 // Pensje / Salaries
        $this->processSalaries($playerId, $deltaHours);

 // Preload danych przed petla odwiertow (w tym przypisania hubow). / Preload data before well loop (including hub assignments).
        $this->preloadPlayerData($playerId, $wells);

 // Petla odwiertow / Well loop
        $wellProd = new WellProductionSection(
            $this,
            $this->db,
            $this->wellService,
            $this->oilPrice,
            $this->gBalanceMults,
            $this->financeTechnicalMods,
            $this->financeLogisticsMods,
            $this->financeSafetyMods,
            $this->transportConfig,
            $this->staffCache,
            $this->wellPipelineCache,
            $this->roadConfigCache,
            $this->offshoreConfigCache,
            $this->geoSvc,
            $this->incidentSvc,
            $this->roadTransportSvc,
            $this->offshoreTransportSvc,
            $this->marineDeliverySvc
        );

        foreach ($wells as $well) {
            $wellId = (int)$well['id'];
            try {
                $wellProd->process(
                    $playerId, $well, $wellId, $deltaHours,
                    $hseBonus, $staffCheck,
                    $offlineProdMult, $offlineRiskMult,
                    $tsvc, $regionalSvc, $activeRegEvents,
                    $storageCapacity
                );
            } catch (Throwable $e) {
                GameLog::error('tick', 'well loop FAILED', $e, ['well_id' => $wellId, 'player_id' => $playerId]);
            }
        }

    }

 /**
 * Finalizacja hubow po wszystkich dostawach gracza w ticku.
 * Finalizes hubs after all player deliveries in this tick.
 *
 * @param array<string, mixed> $hseBonus
 */
    public function finalizeHubTicks(int $playerId, float $deltaHours, array $hseBonus, ?ProtectionService $protectionSvc = null): void
    {
        $wellHub = new WellHubSection(
            $this,
            $this->now,
            $this->hubTickSvc,
            $this->hubIncidentSvc,
            $this->hubSvc,
            $this->financeLogisticsMods,
            $this->gBalanceMults,
            $this->oilPrice,
            new OutboundLegService($this->transportConfig),
            $protectionSvc
        );
        $wellHub->finalize($playerId, $deltaHours, $hseBonus);
    }

 /**
 * Aplikuje hub logistics lub fallback cap dla wolumenu odwiertu.
 * Applies hub logistics or fallback cap to a well's production volume.
 * Wywolywane z WellProductionSection. / Called from WellProductionSection.
 *
 * @param float $actual passed by reference - may be reduced for no-hub wells
 */
    public function applyHubOrFallback(int $wellId, float &$actual, float $deltaHours): void
    {
        if ($this->hubTickSvc === null || $actual <= 0.0) {
            return;
        }

        $hubId = $this->wellHubMap[$wellId] ?? null;

        if ($hubId !== null) {
 // Well ma aktywne przypisanie do huba - akumuluj input, reconciliacja po petli.
 // Well has an active hub assignment - accumulate input, reconcile after the loop.
            $this->hubInputAccum[$hubId] = ($this->hubInputAccum[$hubId] ?? 0.0) + $actual;
 // $actual nie jest redukowany tutaj - straty hubowe korygowane sa w WellHubSection.
 // $actual is not reduced here - hub losses are reconciled in WellHubSection.
        } else {
 // Brak huba - natychmiastowy fallback cap (jak odwiert bez infrastruktury).
 // No hub - immediate fallback cap (well without infrastructure).
            $fallback          = $this->hubTickSvc->applyFallback($actual, $deltaHours);
            $logisticsLossMult = (float)($this->financeLogisticsMods['loss_mult'] ?? 1.0);
            if ($logisticsLossMult !== 1.0 && $fallback['lost_bbl'] > 0.0) {
                $scaledLost              = min($actual, round($fallback['lost_bbl'] * $logisticsLossMult, 4));
                $fallback['lost_bbl']    = $scaledLost;
                $fallback['effective_bbl'] = max(0.0, $actual - $scaledLost);
            }
            if ($fallback['lost_bbl'] > 0.001) {
                $this->finLossBbl           += $fallback['lost_bbl'];
                $this->finLossValue         += round($fallback['lost_bbl'] * $this->oilPrice, 2);
                $this->finFallbackLossBbl   += $fallback['lost_bbl'];
                $this->finFallbackLossValue += round($fallback['lost_bbl'] * $this->oilPrice, 2);
                GameLog::info('tick', 'hub_fallback_loss', [
                    'well_id'   => $wellId,
                    'input_bbl' => round($actual, 2),
                    'lost_bbl'  => round($fallback['lost_bbl'], 2),
                ]);
            }
            $actual = $fallback['effective_bbl'];
        }
    }

 /**
 * Dodaje realnie dostarczona rope do wejscia huba.
 * Adds physically delivered oil to hub input.
 */
    public function addDeliveredHubInput(int $wellId, float $bbl): bool
    {
        if ($bbl <= 0.0) {
            return false;
        }

        $hubId = $this->wellHubMap[$wellId] ?? null;
        if ($hubId === null) {
            return false;
        }

        $this->hubInputAccum[$hubId] = ($this->hubInputAccum[$hubId] ?? 0.0) + $bbl;
        return true;
    }

 /**
 * Rejestruje strate barylkowa przed magazynem (transport capacity, storage block, pipeline).
 * Records a pre-storage barrel loss (transport capacity, storage block, pipeline loss).
 * Wywolywane z WellProductionSection. / Called from WellProductionSection.
 */
    public function recordPreStorageLoss(float $bbl, float $price): void
    {
        if ($bbl <= 0.0) return;
        $this->preStorageLossBbl += $bbl;
        $this->finLossBbl        += $bbl;
        $this->finLossValue      += round($bbl * $price, 2);
    }

 /**
 * Records barrels a well delivered to storage via its hub this tick.
 * Used by WellHubSection to apply the second transport leg (hub -> storage).
 * Only hub-assigned wells are tracked; no-hub wells have no second leg.
 * Rejestruje barylki dostarczone do magazynu przez hub (podstawa odcinka 2).
 */
    public function recordHubWellDelivered(int $wellId, float $bbl): void
    {
        if ($bbl <= 0.0 || !isset($this->wellHubMap[$wellId])) {
            return;
        }
        $this->hubWellDelivered[$wellId] = ($this->hubWellDelivered[$wellId] ?? 0.0) + $bbl;
    }

 /**
 * Returns the multiplier set used by the second transport leg (hub -> storage),
 * so delivery sections (road/marine) can apply leg-2 economics consistently.
 * Zwraca mnozniki odcinka 2 dla sekcji dostaw czasowych.
 *
 * @return array<string, float>
 */
    public function outboundMults(): array
    {
        return [
            'loss_mult'           => (float)($this->financeLogisticsMods['loss_mult'] ?? 1.0),
            'global_loss'         => (float)($this->gBalanceMults['loss'] ?? 1.0),
            'opex'                => (float)($this->gBalanceMults['opex'] ?? 1.0),
            'transport_cost_mult' => (float)($this->financeLogisticsMods['transport_cost_mult'] ?? 1.0),
        ];
    }

 /**
 * Returns the well's chosen second-leg transport type (hub -> storage).
 * ETAP 11: looks up the well's hub and returns hub-level outbound_transport_type.
 */
    public function outboundTypeFor(int $wellId): string
    {
        $hubId = $this->wellHubMap[$wellId] ?? null;
        if ($hubId === null) return 'nieustawiony';
        return (string)($this->hubOutboundType[$hubId] ?? 'nieustawiony');
    }

 /**
 * Returns the well's operational outbound pipeline row (leg='outbound'), or null.
 * ETAP 11: looks up the well's hub and returns the hub-level outbound pipeline.
 * @return array<string, mixed>|null
 */
    public function outboundPipelineFor(int $wellId): ?array
    {
        $hubId = $this->wellHubMap[$wellId] ?? null;
        if ($hubId === null) return null;
        return $this->hubOutboundPipelineCache[$hubId] ?? null;
    }

 // ------------------------------------------------------------------ private

    private function processSalaries(int $playerId, float $deltaHours): void
    {
        try {
            GameLog::step('tick', 'salary', 1, 'start', ['player_id' => $playerId]);

            $boardSalaryStmt = $this->db->prepare("
                SELECT COALESCE(SUM(ec.salary + ec.bonus), 0) AS total
                FROM board_members bm
                JOIN employee_contracts ec ON ec.member_id = bm.id
                WHERE bm.player_id = :pid AND bm.status = 'active'
                  AND ec.status = 'active' AND ec.contract_end >= CURDATE()
            ");
            $boardSalaryStmt->execute([':pid' => $playerId]);
            $boardSalaryMonth = (float)$boardSalaryStmt->fetchColumn();

            $techSalaryStmt = $this->db->prepare("
                SELECT COALESCE(SUM(salary), 0) AS total
                FROM technical_staff
                WHERE player_id = :pid AND status IN ('active','busy')
            ");
            $techSalaryStmt->execute([':pid' => $playerId]);
            $techSalaryMonth  = (float)$techSalaryStmt->fetchColumn();
            $totalSalaryMonth = $boardSalaryMonth + $techSalaryMonth;

            if ($totalSalaryMonth > 0) {
                $salaryPerHour     = $totalSalaryMonth / 720.0;
                $salaryCost        = round($salaryPerHour * $deltaHours, 2);
                $this->finSalary  += $salaryCost;
                $this->playerCash -= $salaryCost;
                GameLog::info('tick', 'salary_deducted', [
                    'player_id'       => $playerId,
                    'board_monthly'   => $boardSalaryMonth,
                    'tech_monthly'    => $techSalaryMonth,
                    'salary_per_hour' => round($salaryPerHour, 2),
                    'delta_hours'     => round($deltaHours, 4),
                    'cost'            => $salaryCost,
                    'cash_after'      => round($this->playerCash, 2),
                ]);
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'salary deduction FAILED', $e, ['player_id' => $playerId]);
        }
    }

    private function preloadFinancePolicies(int $playerId): void
    {
        $this->financeTechnicalMods = ['opex_mult' => 1.0, 'wear_mult' => 1.0, 'degradation_mult' => 1.0];
        $this->financeLogisticsMods = ['transport_cost_mult' => 1.0, 'hub_cost_mult' => 1.0, 'loss_mult' => 1.0, 'incident_mult' => 1.0];
        $this->financeSafetyMods    = ['incident_mult' => 1.0, 'disaster_mult' => 1.0];

        if ($this->financePolicySvc === null) return;

        try {
            $this->financeTechnicalMods = $this->financePolicySvc->getTechnicalModifiers($playerId);
            $this->financeLogisticsMods = $this->financePolicySvc->getLogisticsModifiers($playerId);
            $this->financeSafetyMods    = $this->financePolicySvc->getSafetyModifiers($playerId);
        } catch (Throwable $e) {
            GameLog::error('tick', 'preloadFinancePolicies FAILED', $e, ['player_id' => $playerId]);
        }
    }

 /**
 * @param list<array<string, mixed>> $wells odwierty gracza (potrzebne do batch-query hubow)
 * player wells (needed for hub batch queries)
 */
    private function preloadPlayerData(int $playerId, array $wells): void
    {
 // 1. Preload technical_staff + staff_specializations w jednym SELECT / in one SELECT
        $this->staffCache = [];
        try {
            $stmt = $this->db->prepare("
                SELECT ts.id, ts.status, ts.skill_level, ts.specialization,
                       ss.prod_bonus, ss.wear_reduction, ss.incident_reduction,
                       ss.spiral_reduction, ss.only_deep_layers, ss.repair_speed,
                       ss.incident_return_reduction, ss.catastrophe_reduction
                FROM technical_staff ts
                LEFT JOIN staff_specializations ss ON ss.code = ts.specialization
                WHERE ts.player_id = ?
                  AND ts.status IN ('active', 'busy')
            ");
            $stmt->execute([$playerId]);
            foreach ($stmt->fetchAll() as $row) {
                $this->staffCache[(int)$row['id']] = $row;
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'preloadPlayerData staff FAILED', $e, ['player_id' => $playerId]);
        }

 // 2. Preload owned pipelines per well for all player wells.
 // 2. Preload zakupionych rurociagow per odwiert dla odwiertow gracza.
        $this->wellPipelineCache = [];
        if ($this->wellPipelineSvc !== null && !empty($wells)) {
            try {
                $wellIds = array_map('intval', array_column($wells, 'id'));
 // Inbound pipelines only (well -> hub); outbound rows fetched separately below.
                $this->wellPipelineCache = $this->wellPipelineSvc->getByPlayerAndWellIds($playerId, $wellIds, 'inbound');
            } catch (Throwable $e) {
                GameLog::error('tick', 'preloadPlayerData well pipelines FAILED', $e, ['player_id' => $playerId]);
            }
        }

 // 2a. ETAP 11: second transport leg (hub -> storage) - per-hub choice + outbound pipelines per hub.
 // 2a. ETAP 11: odcinek 2 (hub -> magazyn) - wybor per hub + rurociagi outbound per hub.
        $this->hubOutboundType          = [];
        $this->hubOutboundPipelineCache = [];
        $this->hubWellDelivered         = [];
 // Hub outbound types are loaded from hubCache (logistics_hubs.outbound_transport_type),
 // populated in step 4 below. Actual pipeline loading happens after step 4.

 // 2b. Preload road transport configs for wells using trucks or falling back from missing pipelines.
 // 2b. Preload konfiguracji transportu drogowego dla odwiertow na ciezarowkach lub fallbacku bez rurociagu.
        $this->roadConfigCache = [];
        if ($this->roadTransportSvc !== null && !empty($wells)) {
            try {
                $this->roadTransportSvc->ensureConfigsForPlayerWells($playerId, $wells);
                $truckWellIds = [];
                foreach ($wells as $well) {
                    $wellId = (int)($well['id'] ?? 0);
                    if ($wellId <= 0) {
                        continue;
                    }

                    $selectedTransport = (string)($well['transport_type'] ?? '');
                    $wellType = (string)($well['well_type'] ?? 'onshore');
                    $pipelineRow = $this->wellPipelineCache[$wellId] ?? null;
                    $hasOwnedPipeline = (bool)($pipelineRow['_is_operational'] ?? isset($this->wellPipelineCache[$wellId]));
                    $usesRoadFallback = $wellType !== 'offshore'
                        && $selectedTransport === 'rurociag'
                        && $pipelineRow !== null
                        && !$hasOwnedPipeline;

                    if ($selectedTransport === 'ciezarowki' || $usesRoadFallback) {
                        $truckWellIds[] = $wellId;
                    }
                }

                $truckWellIds = array_values(array_unique($truckWellIds));
                if ($truckWellIds !== []) {
                    $this->roadConfigCache = $this->roadTransportSvc->getConfigsByWellIds($playerId, $truckWellIds);
                }
            } catch (Throwable $e) {
                GameLog::error('tick', 'preloadPlayerData road configs FAILED', $e, ['player_id' => $playerId]);
            }
        }

 // 2c. Preload konfiguracji transportu morskiego (rejsy) dla odwiertow tankowiec. / Preload offshore transport configs (voyages) for tanker wells.
        $this->offshoreConfigCache = [];
        if ($this->offshoreTransportSvc !== null && !empty($wells)) {
            try {
                $this->offshoreTransportSvc->ensureConfigsForPlayerWells($playerId, $wells);
                $offshoreWellIds = array_values(array_map('intval', array_column(
                    array_filter($wells, static fn($w) => ($w['transport_type'] ?? '') === 'tankowiec'), 'id'
                )));
                if ($offshoreWellIds !== []) {
                    $this->offshoreConfigCache = $this->offshoreTransportSvc->getConfigsByWellIds($playerId, $offshoreWellIds);
                }
            } catch (Throwable $e) {
                GameLog::error('tick', 'preloadPlayerData offshore configs FAILED', $e, ['player_id' => $playerId]);
            }
        }

 // 3. Inicjalizuj serwisy raz per gracz / Initialize services once per player
        $this->geoSvc      = class_exists('GeologicalLayerService') ? new GeologicalLayerService() : null;
        $this->incidentSvc = class_exists('IncidentService')         ? new IncidentService()        : null;

 // 4. Batch-load przypisan hubow dla odwiertow gracza (1 query na gracza). / Batch-load hub assignments for all player wells (1 query per player).
        $this->wellHubMap    = [];
        $this->hubCache      = [];
        $this->hubInputAccum = [];

        if ($this->hubSvc !== null && !empty($wells)) {
            try {
                $wellIds      = array_map('intval', array_column($wells, 'id'));
                $placeholders = implode(',', array_fill(0, count($wellIds), '?'));
                $stmt = $this->db->prepare(
                    "SELECT a.well_id, h.*
                       FROM logistics_hub_assignments a
                       JOIN logistics_hubs h ON h.id = a.hub_id
                      WHERE a.well_id IN ({$placeholders})
                        AND a.status   = 'active'
                        AND h.status  NOT IN ('disabled','building')"
                );
                $stmt->execute($wellIds);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $wId   = (int)$row['well_id'];
                    $hubId = (int)$row['id'];
                    unset($row['well_id']); // Wyjmij dodatkowa kolumne z wiersza huba. / Remove extra column from hub row.
                    $this->wellHubMap[$wId] = $hubId;
                    if (!isset($this->hubCache[$hubId])) {
                        $this->hubCache[$hubId]      = $row;
                        $this->hubInputAccum[$hubId] = 0.0;
                    }
                }
                if (!empty($this->hubCache)) {
                    GameLog::info('tick', 'hub_assignments_preloaded', [
                        'player_id'    => $playerId,
                        'assigned_cnt' => count($this->wellHubMap),
                        'hub_cnt'      => count($this->hubCache),
                    ]);
                }
            } catch (Throwable $e) {
                GameLog::error('tick', 'preloadHubAssignments FAILED', $e, ['player_id' => $playerId]);
            }
        }

 // Load hub outbound transport types from the already-loaded hub cache.
        $outboundHubIds = [];
        foreach ($this->hubCache as $hubId => $hub) {
            $otype = (string)($hub['outbound_transport_type'] ?? 'nieustawiony');
            $this->hubOutboundType[$hubId] = $otype;
            if ($otype === 'rurociag') {
                $outboundHubIds[] = $hubId;
            }
        }
        if ($this->wellPipelineSvc !== null && $outboundHubIds !== []) {
            try {
                $this->hubOutboundPipelineCache = $this->wellPipelineSvc->getByPlayerHubIds($playerId, $outboundHubIds);
            } catch (Throwable $e) {
                GameLog::error('tick', 'preloadPlayerData hub outbound pipelines FAILED', $e, ['player_id' => $playerId]);
            }
        }
    }
}
