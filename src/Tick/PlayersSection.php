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
    /** @var array<string,int> */
    public array $sectionTimingsMs = [];
    public int $slowestPlayerMs = 0;
    public int $slowestPlayerId = 0;
    private int $playersFetchMs = 0;

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
        $playersFetchStarted = microtime(true);
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
        $this->playersFetchMs = (int)round((microtime(true) - $playersFetchStarted) * 1000);
        $this->addSectionTiming('players_fetch', $this->playersFetchMs);

        foreach ($players as $playerData) {
            $playerStarted = microtime(true);
            try {
                $this->processPlayer($playerData);
            } catch (Throwable $e) {
                GameLog::error('tick', 'player loop FAILED', $e, ['player_id' => $playerData['id'] ?? null]);
 // Rollback wiszacej transakcji zeby nastepny gracz mogl zaczac.
 // Roll back any dangling transaction so the next player can begin one.
                if ($this->db->inTransaction()) {
                    try { $this->db->rollBack(); } catch (Throwable $re) {}
                }
            } finally {
                $playerDurationMs = (int)round((microtime(true) - $playerStarted) * 1000);
                $this->addSectionTiming('player_total', $playerDurationMs);
                if ($playerDurationMs > $this->slowestPlayerMs) {
                    $this->slowestPlayerMs = $playerDurationMs;
                    $this->slowestPlayerId = (int)($playerData['id'] ?? 0);
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
        $fetchPlayerStateStarted = microtime(true);
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
        $this->addSectionTiming('player_state_fetch', (int)round((microtime(true) - $fetchPlayerStateStarted) * 1000));

        $playerCash      = (float)$playerData['cash'];
        $initialCash     = $playerCash; // gotowka na poczatku ticka (do roznicowego zapisu) / cash at tick start (for differential save)
        $storageCapacity = (float)$storage['capacity'];
        $currentStorage  = (float)$storage['used'];
        $initialStorage  = $currentStorage; // magazyn na poczatku ticka — delta do roznicowego zapisu / storage at tick start — delta for differential save

 // 1. OFFLINE 
        $offlineStarted = microtime(true);
        $offline = new OfflineSection($db, $now);
        if (!$offline->process($playerId, $playerData, $playerCash)) {
            $this->addSectionTiming('offline', (int)round((microtime(true) - $offlineStarted) * 1000));
            return; // freeze mode - skip tick
        }
        $this->addSectionTiming('offline', (int)round((microtime(true) - $offlineStarted) * 1000));

 // BHP + zdarzenia regionalne / HSE + regional events
        $hseBonus   = [];
        $staffCheck = ['meets_minimum' => true, 'missing' => [], 'missing_labels' => []];
        $tsvc       = null;
        $technicalStarted = microtime(true);
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
        $this->addSectionTiming('technical_team', (int)round((microtime(true) - $technicalStarted) * 1000));

        $regionalSvc     = null;
        $activeRegEvents = [];
        $regionalStarted = microtime(true);
        try {
            $regionalSvc = new RegionalEventService();
            $regionalSvc->resolveExpired();
            $regionalSvc->processTick($playerId, $deltaHours);
            $activeRegEvents = $regionalSvc->getActiveEvents($playerId);
        } catch (Throwable $e) {
            GameLog::error('tick', 'RegionalEventService FAILED', $e, ['player_id' => $playerId]);
        }
        $this->addSectionTiming('regional_events', (int)round((microtime(true) - $regionalStarted) * 1000));

 // 2. PETLA ODWIERTOW / Well loop
        $wellService = new WellService();
        $wellLoop    = new WellLoopSection($db, $now, $this->oilPrice, $this->gBalanceMults, $wellService);
        $wellLoopStarted = microtime(true);
        $wellLoop->run(
            $playerId, $wells, $playerCash, $currentStorage, $storageCapacity,
            $deltaHours, $hseBonus, $staffCheck,
            $offline->offlineProdMult, $offline->offlineRiskMult,
            $tsvc, $regionalSvc, $activeRegEvents
        );
        $this->addSectionTiming('well_loop', (int)round((microtime(true) - $wellLoopStarted) * 1000));

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
        $pipelinesStarted = microtime(true);
        $pipelines->process($playerId, $currentStorage, $hseBonus, $deltaHours, $tsvc, $protectionSvc);
        $this->addSectionTiming('pipelines', (int)round((microtime(true) - $pipelinesStarted) * 1000));
 // Floor na 0 jak pozostale odliczenia gotowki (DB i tak ma GREATEST(0,...)).
 // Floor at 0 like the other cash deductions (DB also applies GREATEST(0,...)).
        $wellLoop->totalCosts     += abs($pipelines->cashDelta);
        $playerCash               = max(0.0, $playerCash - abs($pipelines->cashDelta));
        $this->disastersTriggered += $pipelines->disastersTriggered;

 // 3b. DOSTAWY MORSKIE aktualizacja statusow rejsow / Marine deliveries voyage status updates
        if (class_exists('MarineDeliverySection')) {
            $marineStarted = microtime(true);
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
            $this->addSectionTiming('marine_delivery', (int)round((microtime(true) - $marineStarted) * 1000));
        }

 // Second-leg service (hub -> storage), shared by the time-based delivery sections.
        $outboundSvc = new OutboundLegService(TransportConfigService::load($db));

 // 3c. KURSY DROGOWE ukonczone dostawy ciezarowkami (P1.2) / Road trips completed truck deliveries (P1.2)
 // M3: $roadSvc widoczny przy zapisie magazynu, by atomowo potwierdzic dostawy.
 // M3: $roadSvc visible at storage save so road deliveries can be confirmed atomically.
        $roadSvc = null;
        if (class_exists('WellRoadTripSection') && class_exists('RoadTransportService')) {
            $roadStarted = microtime(true);
            try {
                $roadSvc        = new RoadTransportService($db);
 // Ochrona kursow (theft/raid/sabotage) - wspolna instancja gracza.
 // Trip protection (theft/raid/sabotage) - shared per-player instance.
                $roadTripSec    = new WellRoadTripSection($db, $now);
                // M4: wellHubMap pozwala sekcji drogowej nie capowac magazynem ropy odwiertow
                // przypisanych do huba — trafia pelna do bufora huba, nie ginie przy pelnym magazynie.
                // M4: wellHubMap lets the road section skip the storage cap for hub-assigned wells —
                // their oil goes fully to the hub buffer instead of being lost on full storage.
                $currentStorage = $roadTripSec->process($playerId, $currentStorage, $storageCapacity, $hseBonus, $roadSvc, $protectionSvc, $sabotageSvc, $wellLoop->wellHubMap);
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
            $this->addSectionTiming('road_trips', (int)round((microtime(true) - $roadStarted) * 1000));
        }

 // 3d. PORT przetwarzanie kolejki, kredytowanie magazynu / Port queue processing, storage credit
        if (class_exists('PortSection')) {
            $portStarted = microtime(true);
            try {
                $portSec        = new PortSection($db, $now);
                $currentStorage = $portSec->process($playerId, $currentStorage, $storageCapacity, $this->oilPrice, $deltaHours);
 // Dolacz wyniki portowe do sum finansowych / Add port results to financial sums
                if ($portSec->deliveredBbl > 0.0) {
                    $wellLoop->finBbl       += $portSec->deliveredBbl;
                    $wellLoop->deliveredBbl += $portSec->deliveredBbl;
                    $wellLoop->finRevenue   += round($portSec->deliveredBbl * $this->oilPrice, 2);
                }
                if ($portSec->handlingCost > 0.0) {
                    $wellLoop->finTransport += $portSec->handlingCost;
                    $wellLoop->totalCosts   += $portSec->handlingCost;
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
            $this->addSectionTiming('port_queue', (int)round((microtime(true) - $portStarted) * 1000));
        }

 // Finalizacja hubow po produkcji synchronicznej oraz realnie dotartych dostawach czasowych.
 // Hub finalization after synchronous production and physically arrived time-based deliveries.
        $wellLoop->currentStorage = $currentStorage;
        $wellLoop->playerCash     = $playerCash;
        $hubFinalizeStarted = microtime(true);
        $wellLoop->finalizeHubTicks($playerId, $deltaHours, $hseBonus, $protectionSvc);
        $this->addSectionTiming('hub_finalize', (int)round((microtime(true) - $hubFinalizeStarted) * 1000));
        $currentStorage = $wellLoop->currentStorage;
        $playerCash     = $wellLoop->playerCash;

 // 4. SKAZENIE POWIERZCHNIOWE / Surface spill
        $finSvc = new FinanceService();
        $spill  = new SpillSection($db, $wellService);
        $spillStarted = microtime(true);
        $currentStorage            = $spill->process($playerId, $currentStorage, $storageCapacity, $hseBonus, $tsvc);
        $this->addSectionTiming('spill', (int)round((microtime(true) - $spillStarted) * 1000));
 // Floor na 0 jak pozostale odliczenia gotowki. / Floor at 0 like other cash deductions.
        $wellLoop->totalCosts     += abs($spill->cashDelta);
        $playerCash               = max(0.0, $playerCash - abs($spill->cashDelta));
        $this->disastersTriggered += $spill->disastersTriggered;

 // H4: Cap storage — uniemozliwia zapis wartosci powyzej max_capacity gdy spill sie nie wyzwolil.
 // Bez tego currentStorage > storageCapacity moze trafic do bazy po intensywnym tiku.
 // H4: Cap storage — prevents writing above max_capacity when spill was not triggered.
 // Without this, currentStorage > storageCapacity can reach the DB after a heavy tick.
        $storageOverflow = max(0.0, $currentStorage - $storageCapacity);
        $currentStorage  = min($currentStorage, $storageCapacity);
        if ($storageOverflow > 0.001) {
            GameLog::warn('tick', 'storage_overflow_capped', [
                'player_id'    => $playerId,
                'overflow_bbl' => round($storageOverflow, 2),
                'capacity'     => round($storageCapacity, 2),
            ]);
        }

 // Zapis magazynu + atomowe potwierdzenie dostaw drogowych (M3).
 // Kursy oznaczone 'crediting' w tym tiku potwierdzamy jako 'delivered' w tej samej
 // transakcji co zapis magazynu — kredyt do magazynu i potwierdzenie dostawy commituja
 // sie razem. Crash przed commitem zostawia kurs 'crediting', a nastepny tick go
 // ponownie kredytuje (recovery w processCompletedTrips). Potwierdzenie tylko dla MySQL
 // (well_road_trips istnieje wylacznie w MySQL). Jesli juz jestesmy w transakcji
 // (np. harness testowy), nie otwieramy wlasnej — operacje i tak commituja sie razem.
 // Storage save + atomic road-trip delivery confirmation (M3). Trips marked 'crediting'
 // this tick are confirmed 'delivered' in the same transaction as the storage write, so
 // both commit together. A crash before commit leaves the trip 'crediting' and the next
 // tick re-credits it (recovery in processCompletedTrips). Confirmation is MySQL-only
 // (well_road_trips exists only in MySQL). If already in a transaction (e.g. test
 // harness) we do not open our own — the statements still commit together.
        $isMysql = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        $ownTx   = !$db->inTransaction();
        $storageSaveStarted = microtime(true);
        try {
            if ($ownTx) {
                $db->beginTransaction();
            }
            // Roznicowy zapis magazynu: used = used + delta zamiast absolutnego nadpisania.
            // Chroni rownoczesnych pisaczy (BlackMarket, Komornik, MarketOffer) przed utrata zmian.
            // Differential storage write: used = used + delta instead of absolute overwrite.
            // Protects concurrent writers (BlackMarket, Bailiff, MarketOffer) from losing their changes.
            $storageDelta = round($currentStorage - $initialStorage, 4);
            $db->prepare("UPDATE storage SET used = LEAST(capacity, GREATEST(0, used + :delta)), updated_at = NOW() WHERE player_id = :pid")
               ->execute([':delta' => $storageDelta, ':pid' => $playerId]);
            if ($roadSvc !== null && $isMysql) {
                $roadSvc->confirmCreditedTrips($playerId);
            }
            if ($ownTx) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $db->inTransaction()) {
                try { $db->rollBack(); } catch (Throwable $re) {}
            }
            GameLog::error('tick', 'storage save + road confirm FAILED', $e, ['player_id' => $playerId]);
        }
        $this->addSectionTiming('storage_save', (int)round((microtime(true) - $storageSaveStarted) * 1000));

 // Zapis finansowy / Financial save
        $financeSaveStarted = microtime(true);
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
        $this->addSectionTiming('finance_save', (int)round((microtime(true) - $financeSaveStarted) * 1000));

 // 5. STAN FINANSOWY + ZAPIS / Financial state + save
 // Pelny koszt incydentow = incydenty odwiertow + katastrofy rurociagow + kary za wyciek.
 // Bez tego eksplozja rurociagu nie wyzwalala kryzysu mimo wyzerowania gotowki.
 // Full incident cost = well incidents + pipeline disasters + spill fines.
 // Without this a pipeline explosion would not trigger crisis despite draining cash.
        $totalIncidentCost = $wellLoop->finIncident
            + abs($pipelines->cashDelta)
            + abs($spill->cashDelta);
        $financialStateStarted = microtime(true);
        $finState = new FinancialStateSection($db, $now);
        $finState->process(
            $playerId, $playerData, $playerCash,
            $wellLoop->finRevenue, $wellLoop->finOpex, $wellLoop->finSalary,
            $wellLoop->finTransport, $totalIncidentCost, $wellLoop->finTax
        );
        $finState->saveCashAndTick($playerId, $wellLoop->totalCosts);
        $this->addSectionTiming('financial_state', (int)round((microtime(true) - $financialStateStarted) * 1000));

 // 6. AUDIT BANKOWY zbiorcze koszty ticku do bank_transactions (brief: "podatki" itd.).
 // Gotowka juz zeszla roznicowo w saveCashAndTick - tu tylko logTransaction (audit trail).
 // 6. BANK AUDIT aggregated tick costs into bank_transactions (brief: "taxes" etc.).
 // Cash already saved differentially in saveCashAndTick - logTransaction only (audit trail).
        $bankAuditStarted = microtime(true);
        $this->logTickBankAudit(
            $playerId, $wellLoop,
            abs($pipelines->cashDelta), abs($spill->cashDelta)
        );
        $this->addSectionTiming('bank_audit', (int)round((microtime(true) - $bankAuditStarted) * 1000));

 // Aktualizuj liczniki globalne / Update global counters
        $this->playersProcessed++;
        $this->wellsActive  += $wellLoop->finWellsActive;
        $this->totalBbl     += $wellLoop->finBbl;
        $this->totalRevenue += $wellLoop->finRevenue;
        $this->totalOpex    += ($wellLoop->finOpex + $wellLoop->finSalary + $wellLoop->finTransport);

    }

    private function addSectionTiming(string $key, int $durationMs): void
    {
        $this->sectionTimingsMs[$key] = ($this->sectionTimingsMs[$key] ?? 0) + max(0, $durationMs);
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

            // Ryzyko polityczne regionu huba skaluje incydenty drogowe leg-2 (jak w WellHubSection).
            // The hub region's political risk scales leg-2 road incidents (as in WellHubSection).
            $res = $svc->compute(
                $wellLoop->outboundTypeFor($wellId),
                $wellLoop->outboundPipelineFor($wellId),
                $bbl,
                $this->oilPrice,
                $mults,
                $deltaHours,
                $hseBonus,
                $wellLoop->outboundPoliticalRiskFor($wellId)
            );
            // 'blocked' (uszkodzony rurociag leg-2) traktujemy jak 'direct': ta sciezka dotyczy
            // ropy juz FIZYCZNIE dostarczonej ciezarowkami/tankowcem (brak bufora hubu, w ktorym
            // moglaby czekac) — throttling do bufora robi tylko synchroniczny WellHubSection.
            // W praktyce nieosiagalne: odwierty bez huba nie maja rurociagu wylotowego (H7).
            // 'blocked' (damaged leg-2 pipeline) is treated like 'direct' here: this path handles
            // oil already PHYSICALLY delivered by truck/tanker (no hub buffer to wait in) — buffer
            // throttling is done only by the synchronous WellHubSection. Effectively unreachable:
            // hubless wells have no outbound pipeline (H7).
            if ($res['kind'] === 'direct' || $res['kind'] === 'blocked') {
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
                $wellLoop->totalCosts   += $cost;
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
