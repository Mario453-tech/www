<?php

/**
 * PlayersSection fasada sekcji 5 ticka (v2, pelny podzial na podsekecje).
 * PlayersSection tick section 5 facade (v2, fully split into subsections).
*
 * Deleguje logike do: / Delegates logic to:
 * OfflineSection detekcja offline + freeze mode / offline detection + freeze mode
 * WellLoopSection petla odwiertow, produkcja, OPEX, transport / well loop, production, OPEX, transport
 * PipelineSection degradacja + eksplozje rurociagow / degradation + pipeline explosions
 * SpillSection skazenie powierzchniowe (overflow magazynu) / surface contamination (storage overflow)
 * FinancialStateSection crisis detection + zapis last_tick_at / crisis detection + last_tick_at save
 */
class PlayersSection
{
 // Liczniki statystyk (eksponowane do TickStatsRepository) / Stat counters (exposed to TickStatsRepository)
    public int   $playersProcessed   = 0;
    public int   $wellsActive        = 0;
    public float $totalBbl           = 0.0;
    public float $totalRevenue       = 0.0;
    public float $totalOpex          = 0.0;
    public int   $disastersTriggered = 0;
    public int   $incidentsTriggered = 0;

    private PDO      $db;
    private DateTime $now;
    private float    $oilPrice;
 /** @var array<string, mixed> */
    private array    $gBalanceMults;

 /** @param array<string, mixed> $gBalanceMults */
    public function __construct(PDO $db, DateTime $now, float $oilPrice, array $gBalanceMults)
    {
        $this->db            = $db;
        $this->now           = $now;
        $this->oilPrice      = $oilPrice;
        $this->gBalanceMults = $gBalanceMults;
    }

    public function run(): void
    {
        try {
            $players = $this->db->query("
                SELECT id,
                       COALESCE(last_tick_at, '2000-01-01 00:00:00') AS last_tick_at,
                       cash,
                       COALESCE(financial_state, 'normal') AS financial_state,
                       COALESCE(crisis_ticks, 0)           AS crisis_ticks,
                       COALESCE(last_crisis_tick_at, NULL) AS last_crisis_tick_at,
                       COALESCE(credit_score, 50)          AS credit_score,
                       COALESCE(bankruptcy_status, 'none') AS bankruptcy_status,
                       last_active_at,
                       COALESCE(offline_mode, 0)           AS offline_mode,
                       offline_since
                FROM players
                WHERE status != 'bankrupt'
            ")->fetchAll();
            GameLog::dbResult('tick', 'active players', count($players));
        } catch (Throwable $e) {
            GameLog::error('tick', 'player fetch FAILED', $e);
            $players = [];
        }

        foreach ($players as $playerData) {
            try {
                $this->processPlayer($playerData);
            } catch (Throwable $e) {
                GameLog::error('tick', 'player loop FAILED', $e, ['player_id' => $playerData['id'] ?? null]);
 // Rollback wiszacej transakcji zeby nastepny gracz mogl zaczac.
 // Roll back any dangling transaction so the next player can begin one.
                if ($this->db->inTransaction()) {
                    try { $this->db->rollBack(); } catch (Throwable $re) {}
                }
            }
        }
    }

 /** @param array<string, mixed> $playerData */
    private function processPlayer(array $playerData): void
    {
        $db       = $this->db;
        $now      = $this->now;
        $playerId = (int)$playerData['id'];

 // No outer per-player transaction.
 // MySQL 8.x implicitly commits on nested BEGIN (e.g. TechnicalTeamService::startTask
 // called inside processTick), which caused "There is no active transaction" on commit.
 // Subsections manage their own short-lived transactions for atomic writes.
 // Brak zewnetrznej transakcji: MySQL 8.x robi implicit commit przy zagniezdzonej BEGIN.

 // Delta czasu / Time delta
        $lastTick     = new DateTime($playerData['last_tick_at']);
        $deltaSeconds = $now->getTimestamp() - $lastTick->getTimestamp();
        if ($deltaSeconds <= 0) {
            return;
        }
        if ($deltaSeconds > 86400) $deltaSeconds = 86400;
        $deltaHours = $deltaSeconds / 3600;

 // Odwierty i magazyn / Wells and storage
        $wellsStmt = $db->prepare("
            SELECT w.*,
                   GROUP_CONCAT(wu.upgrade_type) AS installed_upgrades,
                   wl.oil_richness,
                   wr.production_bonus  AS region_production_bonus,
                   wr.political_risk    AS region_political_risk,
                   wr.tax_rate          AS region_tax_rate,
                   wr.opex_mult         AS region_opex_mult,
                   wr.stability_bonus   AS region_stability_bonus
            FROM wells w
            LEFT JOIN well_upgrades wu   ON wu.well_id  = w.id
            LEFT JOIN world_locations wl ON wl.id       = w.location_id
            LEFT JOIN world_regions   wr ON wr.id       = w.region_id
            WHERE w.player_id = :pid
            GROUP BY w.id
        ");
        $wellsStmt->execute([':pid' => $playerId]);
        $wells = $wellsStmt->fetchAll();

        $storStmt = $db->prepare("SELECT capacity, used FROM storage WHERE player_id = :pid");
        $storStmt->execute([':pid' => $playerId]);
        $storage = $storStmt->fetch();

        if (!$storage) {
            GameLog::warn('tick', 'no storage for player', ['player_id' => $playerId]);
            return;
        }

        $playerCash      = (float)$playerData['cash'];
        $initialCash     = $playerCash; // gotowka na poczatku ticka (do roznicowego zapisu) / cash at tick start (for differential save)
        $storageCapacity = (float)$storage['capacity'];
        $currentStorage  = (float)$storage['used'];

 // 1. OFFLINE 
        $offline = new OfflineSection($db, $now);
        if (!$offline->process($playerId, $playerData, $playerCash)) {
            return; // freeze mode - skip tick
        }

 // BHP + zdarzenia regionalne / HSE + regional events
        $hseBonus   = [];
        $staffCheck = ['meets_minimum' => true, 'missing' => [], 'missing_labels' => []];
        $tsvc       = null;
        try {
            $tsvc       = new TechnicalTeamService($playerId);
            $hseBonus   = $tsvc->getHSEBonus();
            $staffCheck = $tsvc->getStaffRequirementCheck();
            $tsvc->processProcedureDecay($deltaHours);
            try {
                $tsvc->processTick();
            } catch (Throwable $e) {
                GameLog::error('tick', 'TTS::processTick FAILED', $e, ['player_id' => $playerId]);
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'TechnicalTeamService FAILED', $e, ['player_id' => $playerId]);
        }

        $regionalSvc     = null;
        $activeRegEvents = [];
        try {
            $regionalSvc = new RegionalEventService();
            $regionalSvc->resolveExpired();
            $regionalSvc->processTick($playerId, $deltaHours);
            $activeRegEvents = $regionalSvc->getActiveEvents($playerId);
        } catch (Throwable $e) {
            GameLog::error('tick', 'RegionalEventService FAILED', $e, ['player_id' => $playerId]);
        }

 // 2. PETLA ODWIERTOW / Well loop
        $wellService = new WellService();
        $wellLoop    = new WellLoopSection($db, $now, $this->oilPrice, $this->gBalanceMults, $wellService);
        $wellLoop->run(
            $playerId, $wells, $playerCash, $currentStorage, $storageCapacity,
            $deltaHours, $hseBonus, $staffCheck,
            $offline->offlineProdMult, $offline->offlineRiskMult,
            $tsvc, $regionalSvc, $activeRegEvents
        );

 // Synchronizuj stan po pEtli odwiertow / Sync state after the well loop
        $playerCash     = $wellLoop->playerCash;
        $currentStorage = $wellLoop->currentStorage;
        $this->disastersTriggered += $wellLoop->disastersTriggered;
        $this->incidentsTriggered += $wellLoop->incidentsTriggered;

 // Jedna instancja ochrony na gracza (wygasanie raz, wspolna dla rurociagow/hubow/drogi).
 // One protection instance per player (expiry once, shared by pipelines/hubs/road).
        $protectionSvc = class_exists('ProtectionService') ? new ProtectionService($db) : null;
        $sabotageSvc   = class_exists('SabotageService') ? new SabotageService($db) : null;

 // 3. RUROCIAGI / Pipelines
        $pipelines = new PipelineSection($db, $now, $wellService);
        $pipelines->process($playerId, $currentStorage, $hseBonus, $deltaHours, $tsvc, $protectionSvc);
 // Floor na 0 jak pozostale odliczenia gotowki (DB i tak ma GREATEST(0,...)).
 // Floor at 0 like the other cash deductions (DB also applies GREATEST(0,...)).
        $playerCash               = max(0.0, $playerCash - abs($pipelines->cashDelta));
        $this->disastersTriggered += $pipelines->disastersTriggered;

 // 3b. DOSTAWY MORSKIE aktualizacja statusow rejsow / Marine deliveries voyage status updates
        if (class_exists('MarineDeliverySection')) {
            try {
                $marineSec = new MarineDeliverySection($db, $now);
                $marineSec->process($playerId, $hseBonus, $deltaHours);
                if ($marineSec->lostBbl > 0.0) {
                    $wellLoop->transportEventLossBbl += $marineSec->lostBbl;
                    $wellLoop->recordPreStorageLoss($marineSec->lostBbl, $this->oilPrice);
                    GameLog::info('tick', 'marine_delivery_loss_finance_recorded', [
                        'player_id' => $playerId,
                        'lost_bbl' => round($marineSec->lostBbl, 4),
                        'lost_deliveries' => $marineSec->lostDeliveries,
                    ]);
                }
            } catch (Throwable $e) {
                GameLog::error('tick', 'MarineDeliverySection FAILED', $e, ['player_id' => $playerId]);
            }
        }

 // Second-leg service (hub -> storage), shared by the time-based delivery sections.
        $outboundSvc = new OutboundLegService(TransportConfigService::load($db));

 // 3c. KURSY DROGOWE ukonczone dostawy ciezarowkami (P1.2) / Road trips completed truck deliveries (P1.2)
        if (class_exists('WellRoadTripSection') && class_exists('RoadTransportService')) {
            try {
                $roadSvc        = new RoadTransportService($db);
 // Ochrona kursow (theft/raid/sabotage) - wspolna instancja gracza.
 // Trip protection (theft/raid/sabotage) - shared per-player instance.
                $roadTripSec    = new WellRoadTripSection($db, $now);
                $currentStorage = $roadTripSec->process($playerId, $currentStorage, $storageCapacity, $hseBonus, $roadSvc, $protectionSvc, $sabotageSvc);
                if ($roadTripSec->deliveredBbl > 0.0) {
                    $wellLoop->finBbl       += $roadTripSec->deliveredBbl;
                    $wellLoop->deliveredBbl += $roadTripSec->deliveredBbl;
                    $wellLoop->finRevenue   += round($roadTripSec->deliveredBbl * $this->oilPrice, 2);
                }
                if ($roadTripSec->lostBbl > 0.0) {
                    $wellLoop->transportEventLossBbl += $roadTripSec->lostBbl;
                    $wellLoop->finLossBbl            += $roadTripSec->lostBbl;
                    $wellLoop->finLossValue          += round($roadTripSec->lostBbl * $this->oilPrice, 2);
                }
 // Dostawy do hubow przechodza przez finalizacje huba; bez huba zostaja przy starym drugim odcinku.
 // Deliveries to hubs go through hub finalization; no-hub deliveries keep the legacy second leg path.
                $roadSecondLegByWell = $this->queueHubDeliveredInputs($roadTripSec->deliveredByWell, $wellLoop);
 // Second transport leg (hub -> storage) on the oil just delivered by road.
                $currentStorage = $this->applyOutboundLeg(
                    $roadSecondLegByWell, $wellLoop, $outboundSvc,
                    $currentStorage, $playerCash, $deltaHours, $hseBonus
                );
            } catch (Throwable $e) {
                GameLog::error('tick', 'WellRoadTripSection FAILED', $e, ['player_id' => $playerId]);
            }
        }

 // 3d. PORT przetwarzanie kolejki, kredytowanie magazynu / Port queue processing, storage credit
        if (class_exists('PortSection')) {
            try {
                $portSec        = new PortSection($db, $now);
                $currentStorage = $portSec->process($playerId, $currentStorage, $storageCapacity, $this->oilPrice);
 // Dolacz wyniki portowe do sum finansowych / Add port results to financial sums
                if ($portSec->deliveredBbl > 0.0) {
                    $wellLoop->finBbl       += $portSec->deliveredBbl;
                    $wellLoop->deliveredBbl += $portSec->deliveredBbl;
                    $wellLoop->finRevenue   += round($portSec->deliveredBbl * $this->oilPrice, 2);
                }
                if ($portSec->handlingCost > 0.0) {
                    $wellLoop->finTransport += $portSec->handlingCost;
                    $playerCash              = max(0.0, $playerCash - $portSec->handlingCost);
                }
 // Dostawy do hubow przechodza przez finalizacje huba; bez huba zostaja przy starym drugim odcinku.
 // Deliveries to hubs go through hub finalization; no-hub deliveries keep the legacy second leg path.
                $portSecondLegByWell = $this->queueHubDeliveredInputs($portSec->deliveredByWell, $wellLoop);
 // Second transport leg (hub -> storage) on the oil just delivered by sea.
                $currentStorage = $this->applyOutboundLeg(
                    $portSecondLegByWell, $wellLoop, $outboundSvc,
                    $currentStorage, $playerCash, $deltaHours, $hseBonus
                );
            } catch (Throwable $e) {
                GameLog::error('tick', 'PortSection FAILED', $e, ['player_id' => $playerId]);
            }
        }

 // Finalizacja hubow po produkcji synchronicznej oraz realnie dotartych dostawach czasowych.
 // Hub finalization after synchronous production and physically arrived time-based deliveries.
        $wellLoop->currentStorage = $currentStorage;
        $wellLoop->playerCash     = $playerCash;
        $wellLoop->finalizeHubTicks($playerId, $deltaHours, $hseBonus, $protectionSvc);
        $currentStorage = $wellLoop->currentStorage;
        $playerCash     = $wellLoop->playerCash;

 // 4. SKAZENIE POWIERZCHNIOWE / Surface spill
        $finSvc = new FinanceService();
        $spill  = new SpillSection($db, $wellService);
        $currentStorage            = $spill->process($playerId, $currentStorage, $storageCapacity, $hseBonus, $tsvc);
 // Floor na 0 jak pozostale odliczenia gotowki. / Floor at 0 like other cash deductions.
        $playerCash               = max(0.0, $playerCash - abs($spill->cashDelta));
        $this->disastersTriggered += $spill->disastersTriggered;

 // Zapis magazynu / Save storage
        $db->prepare("UPDATE storage SET used = :used, updated_at = NOW() WHERE player_id = :pid")
           ->execute([':used' => $currentStorage, ':pid' => $playerId]);

 // Zapis finansowy / Financial save
        try {
            $finSvc->saveTick(
                $playerId,
                $now->format('Y-m-d H:i:s'),
                $wellLoop->finRevenue,
                $wellLoop->finGross,
                $wellLoop->finOpex,
                $wellLoop->finSalary,
                $wellLoop->finTransport,
                $wellLoop->finIncident,
                $wellLoop->finTax,
                $wellLoop->finLossBbl,
                $wellLoop->finLossValue,
                $playerCash,
                (float)($this->oilPrice ?: 70),
                $wellLoop->finBbl,
                $wellLoop->finWellsActive,
                $wellLoop->finHubUsageCost,
                $wellLoop->finHubLossBbl,
                $wellLoop->finHubLossValue,
                $wellLoop->finFallbackLossBbl,
                $wellLoop->finFallbackLossValue,
                $wellLoop->finHubIncidentLossBbl,
                $wellLoop->finHubIncidentLossValue,
                $wellLoop->producedBbl,
                $wellLoop->deliveredBbl,
                $wellLoop->preStorageLossBbl,
                $wellLoop->transportLossBbl,
                $wellLoop->transportEventLossBbl
            );
        } catch (Throwable $e) {
            GameLog::error('tick', 'FinanceService::saveTick FAILED', $e, ['player_id' => $playerId]);
        }

 // 5. STAN FINANSOWY + ZAPIS / Financial state + save
 // Pelny koszt incydentow = incydenty odwiertow + katastrofy rurociagow + kary za wyciek.
 // Bez tego eksplozja rurociagu nie wyzwalala kryzysu mimo wyzerowania gotowki.
 // Full incident cost = well incidents + pipeline disasters + spill fines.
 // Without this a pipeline explosion would not trigger crisis despite draining cash.
        $totalIncidentCost = $wellLoop->finIncident
            + abs($pipelines->cashDelta)
            + abs($spill->cashDelta);
        $finState = new FinancialStateSection($db, $now);
        $finState->process(
            $playerId, $playerData, $playerCash,
            $wellLoop->finRevenue, $wellLoop->finOpex, $wellLoop->finSalary,
            $wellLoop->finTransport, $totalIncidentCost, $wellLoop->finTax
        );
        $finState->saveCashAndTick($playerId, $playerCash, $initialCash);

 // 6. AUDIT BANKOWY zbiorcze koszty ticku do bank_transactions (brief: "podatki" itd.).
 // Gotowka juz zeszla roznicowo w saveCashAndTick - tu tylko logTransaction (audit trail).
 // 6. BANK AUDIT aggregated tick costs into bank_transactions (brief: "taxes" etc.).
 // Cash already saved differentially in saveCashAndTick - logTransaction only (audit trail).
        $this->logTickBankAudit(
            $playerId, $wellLoop,
            abs($pipelines->cashDelta), abs($spill->cashDelta)
        );

 // Aktualizuj liczniki globalne / Update global counters
        $this->playersProcessed++;
        $this->wellsActive  += $wellLoop->finWellsActive;
        $this->totalBbl     += $wellLoop->finBbl;
        $this->totalRevenue += $wellLoop->finRevenue;
        $this->totalOpex    += ($wellLoop->finOpex + $wellLoop->finSalary + $wellLoop->finTransport);

    }

 /**
 * Zapisuje zbiorcze koszty ticku do bank_transactions jako audit trail (bez ruszania salda;
 * gotowka schodzi roznicowo w FinancialStateSection::saveCashAndTick). Wpis tylko gdy kwota > 0.
 * OPEX pomniejszony o oplaty hubowe (WellHubSection dodaje je do OBU akumulatorow), zeby nie dublowac.
 * Incydenty = incydenty odwiertow + katastrofy rurociagow + kary srodowiskowe (spill).
 *
 * Writes aggregated tick costs into bank_transactions as an audit trail (no balance change;
 * cash is saved differentially in FinancialStateSection::saveCashAndTick). Entry only when > 0.
 * OPEX is reduced by hub fees (WellHubSection adds them to BOTH accumulators) to avoid double-logging.
 * Incidents = well incidents + pipeline disasters + environmental fines (spill).
 */
    private function logTickBankAudit(
        int             $playerId,
        WellLoopSection $wellLoop,
        float           $pipelineDisasterCost,
        float           $spillFineCost
    ): void {
        if (!class_exists('FinancialTransactionService')) {
            return;
        }

        try {
            $entries = [
                [FinancialTransactionService::TYPE_TAX,            $wellLoop->finTax,                                              'bank.tx_tick_tax'],
                [FinancialTransactionService::TYPE_TICK_OPEX,      max(0.0, $wellLoop->finOpex - $wellLoop->finHubUsageCost),      'bank.tx_tick_opex'],
                [FinancialTransactionService::TYPE_HUB_USAGE,      $wellLoop->finHubUsageCost,                                     'bank.tx_tick_hub_usage'],
                [FinancialTransactionService::TYPE_TICK_SALARY,    $wellLoop->finSalary,                                           'bank.tx_tick_salary'],
                [FinancialTransactionService::TYPE_TICK_TRANSPORT, $wellLoop->finTransport,                                        'bank.tx_tick_transport'],
                [FinancialTransactionService::TYPE_TICK_INCIDENT,  $wellLoop->finIncident + $pipelineDisasterCost + $spillFineCost, 'bank.tx_tick_incident'],
            ];

            $fts = null;
            foreach ($entries as [$type, $amount, $descKey]) {
                $amount = round((float)$amount, 2);
                if ($amount < 0.01) {
                    continue;
                }
                $fts ??= new FinancialTransactionService($this->db);
                $fts->logTransaction(
                    $playerId, null, $amount, $type,
                    tPlain($descKey), 'tick', null
                );
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'logTickBankAudit FAILED', $e, ['player_id' => $playerId]);
        }
    }

 /**
 * Applies the second transport leg (hub -> storage) to oil delivered this tick by a
 * time-based path (road trips / marine). Mirrors WellHubSection's synchronous handling,
 * reducing storage by leg-2 losses and charging leg-2 cost, while folding the result
 * into the shared finance accumulators.
 *
 * @param array<int, float> $deliveredByWell well_id => credited bbl
 * @param array<string, mixed> $hseBonus
 */
    private function applyOutboundLeg(
        array              $deliveredByWell,
        WellLoopSection    $wellLoop,
        OutboundLegService $svc,
        float              $currentStorage,
        float              &$playerCash,
        float              $deltaHours,
        array              $hseBonus
    ): float {
        if ($deliveredByWell === []) {
            return $currentStorage;
        }

        $mults = $wellLoop->outboundMults();

        foreach ($deliveredByWell as $wellId => $bbl) {
            $wellId = (int)$wellId;
            $bbl    = (float)$bbl;
            if ($bbl <= 0.001) {
                continue;
            }

            $res = $svc->compute(
                $wellLoop->outboundTypeFor($wellId),
                $wellLoop->outboundPipelineFor($wellId),
                $bbl,
                $this->oilPrice,
                $mults,
                $deltaHours,
                $hseBonus
            );
            if ($res['kind'] === 'direct') {
                continue;
            }

            $lossBbl = (float)$res['loss_bbl'];
            if ($lossBbl > 0.001) {
                $lossVal = (float)$res['loss_value'];
                $currentStorage                  = max(0.0, $currentStorage - $lossBbl);
                $wellLoop->finBbl               -= $lossBbl;
                $wellLoop->deliveredBbl         -= $lossBbl;
                $wellLoop->finRevenue           -= $lossVal;
                $wellLoop->finLossBbl           += $lossBbl;
                $wellLoop->finLossValue         += $lossVal;
                $wellLoop->finOutboundLossBbl   += $lossBbl;
                $wellLoop->finOutboundLossValue += $lossVal;
            }

            $cost = (float)$res['cost'];
            if ($cost > 0.0) {
                $wellLoop->finTransport += $cost;
                $playerCash              = max(0.0, $playerCash - $cost);
            }

            GameLog::info('tick', 'outbound_leg_delivery', [
                'well_id'   => $wellId,
                'kind'      => $res['kind'],
                'bbl'       => round($bbl, 2),
                'lost_bbl'  => round($lossBbl, 2),
                'cost'      => $cost,
            ]);
        }

        return $currentStorage;
    }

 /**
 * Przekazuje dostarczona rope do huba, jesli odwiert ma aktywne przypisanie.
 * Sends delivered oil into the hub if the well has an active assignment.
 *
 * @param array<int, float> $deliveredByWell
 * @return array<int, float>
 */
    private function queueHubDeliveredInputs(array $deliveredByWell, WellLoopSection $wellLoop): array
    {
        $directByWell = [];
        foreach ($deliveredByWell as $wellId => $bbl) {
            $wellId = (int)$wellId;
            $bbl    = (float)$bbl;
            if ($bbl <= 0.001) {
                continue;
            }
            if (!$wellLoop->addDeliveredHubInput($wellId, $bbl)) {
                $directByWell[$wellId] = ($directByWell[$wellId] ?? 0.0) + $bbl;
            }
        }

        return $directByWell;
    }
}
