<?php

/**
 * WellProductionSection - orkiestrator przetwarzania jednego odwiertu w ticku.
 * WellProductionSection - orchestrator for processing a single well within a tick.
 *
 * Odpowiada za: / Responsible for:
 * - delegowanie do handlerow: status, ryzyko, produkcja / delegating to handlers: status, risk, production
 * - przechowywanie wspolnego stanu (db, serwisy, cache) / holding shared state (db, services, caches)
 *
 * Pola sa publiczne, by handlery mogly dostepu przez $this->ctx->*.
 * Fields are public so handlers can access them via $this->ctx->*.
 */
class WellProductionSection
{
 // Kontekst petli (finanse, produkcja, liczniki) / Loop context (financials, production, counters)
    public WellLoopSection $loopCtx;

 // Wspolne zaleznosci / Shared dependencies
    public PDO         $db;
    public WellService $wellService;
    public float       $oilPrice;

 /** @var array<string, mixed> */
    public array $gBalanceMults;
 /** @var array<string, float|string> */
    public array $financeTechnicalMods;
 /** @var array<string, float|string> */
    public array $financeLogisticsMods;
 /** @var array<string, float|string> */
    public array $financeSafetyMods;
 /** @var array<string, array<string, float>> */
    public array $transportConfig;

 // Cache / Cache
 /** @var array<int, array<string, mixed>> */
    public array $staffCache;
 /** @var array<int, array<string, mixed>> */
    public array $wellPipelineCache;
 /** @var array<int, array<string, mixed>> */
    public array $roadConfigCache;
 /** @var array<int, array<string, mixed>> */
    public array $offshoreConfigCache;

 // Serwisy opcjonalne / Optional services
    public ?object                   $geoSvc;
    public ?object                   $incidentSvc;
    public ?RoadTransportService     $roadTransportSvc;
    public ?OffshoreTransportService $offshoreTransportSvc;
    public ?MarineDeliveryService    $marineDeliverySvc;   // Etap 5: dostawy morskie / Etap 5: marine deliveries

 // Handlery / Handlers
    private WellStatusHandler     $statusHandler;
    private WellRiskHandler       $riskHandler;
    private WellProductionHandler $productionHandler;

 /**
 * @param array<string, mixed> $gBalanceMults
 * @param array<string, float|string> $financeTechnicalMods
 * @param array<string, float|string> $financeLogisticsMods
 * @param array<string, float|string> $financeSafetyMods
 * @param array<string, array<string,float>>$transportConfig
 * @param array<int, array<string, mixed>> $staffCache
 * @param array<int, array<string, mixed>> $wellPipelineCache
 * @param array<int, array<string, mixed>> $roadConfigCache
 * @param array<int, array<string, mixed>> $offshoreConfigCache
 */
    public function __construct(
        WellLoopSection              $loopCtx,
        PDO                          $db,
        WellService                  $wellService,
        float                        $oilPrice,
        array                        $gBalanceMults,
        array                        $financeTechnicalMods,
        array                        $financeLogisticsMods,
        array                        $financeSafetyMods,
        array                        $transportConfig,
        array                        $staffCache,
        array                        $wellPipelineCache,
        array                        $roadConfigCache,
        array                        $offshoreConfigCache,
        ?object                      $geoSvc,
        ?object                      $incidentSvc,
        ?RoadTransportService        $roadTransportSvc,
        ?OffshoreTransportService    $offshoreTransportSvc,
        ?MarineDeliveryService       $marineDeliverySvc = null
    ) {
        $this->loopCtx              = $loopCtx;
        $this->db                   = $db;
        $this->wellService          = $wellService;
        $this->oilPrice             = $oilPrice;
        $this->gBalanceMults        = $gBalanceMults;
        $this->financeTechnicalMods = $financeTechnicalMods;
        $this->financeLogisticsMods = $financeLogisticsMods;
        $this->financeSafetyMods    = $financeSafetyMods;
        $this->transportConfig      = $transportConfig;
        $this->staffCache           = $staffCache;
        $this->wellPipelineCache    = $wellPipelineCache;
        $this->roadConfigCache      = $roadConfigCache;
        $this->offshoreConfigCache  = $offshoreConfigCache;
        $this->geoSvc               = $geoSvc;
        $this->incidentSvc          = $incidentSvc;
        $this->roadTransportSvc     = $roadTransportSvc;
        $this->offshoreTransportSvc = $offshoreTransportSvc;
        $this->marineDeliverySvc    = $marineDeliverySvc;

        $this->statusHandler     = new WellStatusHandler($this);
        $this->riskHandler       = new WellRiskHandler($this);
        $this->productionHandler = new WellProductionHandler($this);
    }

 /**
 * Przetwarza jeden odwiert: produkcja, OPEX, transport, zdarzenia.
 * Processes one well: production, OPEX, transport, events.
 *
 * @param array<string, mixed> $well
 * @param array<string, mixed> $hseBonus
 * @param array<string, mixed> $staffCheck
 * @param list<array<string, mixed>> $activeRegEvents
 */
    public function process(
        int     $playerId,
        array   $well,
        int     $wellId,
        float   $deltaHours,
        array   $hseBonus,
        array   $staffCheck,
        float   $offlineProdMult,
        float   $offlineRiskMult,
        ?object $tsvc,
        ?object $regionalSvc,
        array   $activeRegEvents,
        float   $storageCapacity
    ): void {
        if (in_array($well['status'], ['seized', 'blowout', 'broken'])) return;

 // Montaz sprzetu - odwiert wstrzymany przez 1h / Equipment installation - well paused for 1h
        if ($well['status'] === 'equipment_swap') {
            $well = $this->statusHandler->handleEquipmentSwap($well, $wellId, $tsvc);
            if ($well === null) return;
        }

 // Zakonczenie wiercenia warstwy geologicznej / Geological layer drilling completed
        if (!empty($well['layer_switch_until']) && $this->geoSvc !== null) {
            $well = $this->statusHandler->handleGeoLayerSwitch($well, $wellId);
        }

 // Kontrola personelu / Staff check
        $well = $this->statusHandler->handleStaffCheck($well, $wellId, $playerId, $staffCheck, $tsvc);

 // Operator / Technician
        [$operatorId, $technicianId, $opRow, $techRow, $opPerk, $techPerk, $opSkill]
            = $this->statusHandler->resolveStaff($well, $wellId, $playerId);

 // Mnozniki warstwy geologicznej, sprzetu, spirali i perkow / Layer, equipment, spiral and perk multipliers
        $mults = $this->statusHandler->calcMultipliers($well, $wellId, $operatorId, $technicianId, $opRow, $techRow, $opPerk, $techPerk, $opSkill);

 // Transport config / Pipeline status
        [$transportType, $transportCfg, $transportCapPct, $transportOpexPct,
         $transportIncidentMult, $transportDisasterMult, $transportWearMult, $wellPipeline]
            = $this->productionHandler->resolveTransportConfig($well, $wellId);

 // Degradacja, ryzyko, zuzycie / Degradation, risk, wear
        $statusAllowsDegradation = in_array($well['status'], ['active','contaminated','paused_staff','paused_cash','paused_storage','no_technician','no_operator']);
 // Katastrofy i incydenty - tylko gdy odwiert realnie pracuje / Disasters and incidents - only when well is actually running
 // (nie gdy jest wstrzymany brakiem kadry, cashu lub miejsca w magazynie) / (not when paused due to missing staff, cash, or storage space)
        $statusAllowsIncidents   = in_array($well['status'], ['active','contaminated','no_technician','no_operator']);

        if ($statusAllowsDegradation) {
            $this->riskHandler->processDegradationAndRisk(
                $well, $wellId, $deltaHours, $hseBonus,
                $mults, $transportWearMult, $offlineRiskMult,
                $technicianId
            );
        }

        $incidentProdDrop = 0.0;
        if ($statusAllowsIncidents) {
 // Katastrofa / Disaster
            if ($this->riskHandler->processDisasterRoll($well, $wellId, $playerId, $deltaHours, $hseBonus, $mults, $transportDisasterMult, $offlineRiskMult, $tsvc)) {
                return; // dotkniety katastrofa � pomijamy produkcje / disaster hit � skip production
            }
 // Incydenty produkcyjne / Production incidents
            $incidentProdDrop = $this->riskHandler->processIncidents(
                $well, $wellId, $playerId, $deltaHours, $hseBonus,
                $operatorId, $technicianId, $opRow, $techRow,
                $opPerk, $techPerk, $mults, $transportIncidentMult, $tsvc
            );
        }

 // OPEX
        if (!$this->productionHandler->processOpex($well, $wellId, $playerId, $deltaHours, $storageCapacity, $tsvc)) {
            return;
        }

        if ($well['status'] === 'no_operator') {
            GameLog::info('tick', 'no operator - skipping production', ['well_id' => $wellId]);
            return;
        }
        if (!in_array($well['status'], ['active','contaminated','no_technician'])) return;

 // Produkcja - uwzglednij richness_mult warstwy geologicznej / Production - apply geological layer richness_mult
        $this->productionHandler->processProduction(
            $well, $wellId, $playerId, $deltaHours,
            $storageCapacity, $hseBonus,
            $operatorId, $mults,
            $transportType, $transportCfg, $transportCapPct, $transportOpexPct,
            $wellPipeline,
            $offlineProdMult, $incidentProdDrop,
            $regionalSvc, $activeRegEvents, $tsvc
        );

        if (in_array($well['status'], ['active','no_technician','contaminated'])) {
            $this->loopCtx->finWellsActive++;
        }
    }
}
