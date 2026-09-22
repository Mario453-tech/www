<?php
trait PlayerTickProcessingTrait
{
 /**
  * @param array<string, mixed> $playerData
  * @param array{well: WellService, technical: TechnicalTeamService, offline: OfflineSection, pipelines: PipelineSection, well_loop: WellLoopSection, protection: ?ProtectionService, sabotage: ?SabotageService, road: ?RoadTransportService, finance: FinanceService, transactions: FinancialTransactionService} $services
  */
    private function processLockedPlayer(array $playerData, array $services): void
    {
        $db       = $this->db;
        $now      = $this->now;
        $playerId = (int)$playerData['id'];

 // State was reloaded under the player lock; all effects share one transaction.
 // Stan odczytano pod blokada gracza; wszystkie efekty maja wspolna transakcje.

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
            $this->markPlayerSkipped($playerId, 'no_storage', false);
            return;
        }
        $this->addSectionTiming('player_state_fetch', (int)round((microtime(true) - $fetchPlayerStateStarted) * 1000));

        $playerCash      = (float)$playerData['cash'];
        $initialCash     = $playerCash; // gotowka na poczatku ticka (do roznicowego zapisu) / cash at tick start (for differential save)
        $storageCapacity = (float)$storage['capacity'];
        $currentStorage  = (float)$storage['used'];
        $initialStorage  = $currentStorage; // Storage at tick start - delta for differential save.

 // 1. OFFLINE
        $offlineStarted = microtime(true);
        $offline = $services['offline'];
        if (!$offline->process($playerId, $playerData, $playerCash)) {
            $this->addSectionTiming('offline', (int)round((microtime(true) - $offlineStarted) * 1000));
            $this->markPlayerSkipped($playerId, 'offline_freeze', true);
            return; // freeze mode - skip tick
        }
        $this->addSectionTiming('offline', (int)round((microtime(true) - $offlineStarted) * 1000));

 // BHP + zdarzenia regionalne / HSE + regional events
        $hseBonus   = [];
        $staffCheck = ['meets_minimum' => true, 'missing' => [], 'missing_labels' => []];
        $tsvc       = null;
        $technicalStarted = microtime(true);
        try {
            $tsvc       = $services['technical'];
            $hseBonus   = $tsvc->getHSEBonus();
            $staffCheck = $tsvc->getStaffRequirementCheck();
            $tsvc->processProcedureDecay($deltaHours);
            try {
                $tsvc->processTick();
            } catch (Throwable $e) {
                GameLog::error('tick', 'TTS::processTick FAILED', $e, ['player_id' => $playerId]);
                throw $e;
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'TechnicalTeamService FAILED', $e, ['player_id' => $playerId]);
            throw $e;
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
            throw $e;
        }
        $this->addSectionTiming('regional_events', (int)round((microtime(true) - $regionalStarted) * 1000));

 // 2. PETLA ODWIERTOW / Well loop
        $wellService = $services['well'];
        $pipelines = $services['pipelines'];
        try {
            $pipelines->completeBuilds($playerId, $tsvc);
        } catch (Throwable $e) {
            GameLog::error('tick', 'pipeline build completion FAILED', $e, ['player_id' => $playerId]);
            throw $e;
        }
        $pipelineStaffing = [];
        try {
            $pipelineStaffing = (new PipelineStaffingService($db))->pipelineStaffingForPlayer($playerId);
        } catch (Throwable $e) {
            GameLog::error('tick', 'pipeline staffing preload FAILED', $e, ['player_id' => $playerId]);
            throw $e;
        }
        $wellLoop    = $services['well_loop'];
        $wellLoopStarted = microtime(true);
        $wellLoop->run(
            $playerId, $wells, $playerCash, $currentStorage, $storageCapacity,
            $deltaHours, $hseBonus, $staffCheck,
            $offline->offlineProdMult, $offline->offlineRiskMult,
            $tsvc, $regionalSvc, $activeRegEvents,
            $pipelines->completedActiveHours(),
            $pipelineStaffing
        );
        $this->addSectionTiming('well_loop', (int)round((microtime(true) - $wellLoopStarted) * 1000));

 // Synchronizuj stan po pEtli odwiertow / Sync state after the well loop
        $playerCash     = $wellLoop->playerCash;
        $currentStorage = $wellLoop->currentStorage;
        $this->disastersTriggered += $wellLoop->disastersTriggered;
        $this->incidentsTriggered += $wellLoop->incidentsTriggered;

 // Jedna instancja ochrony na gracza (wygasanie raz, wspolna dla rurociagow/hubow/drogi).
 // One protection instance per player (expiry once, shared by pipelines/hubs/road).
        $protectionSvc = $services['protection'];
        $sabotageSvc = $services['sabotage'];

 // 3. RUROCIAGI / Pipelines
        $pipelinesStarted = microtime(true);
        $pipelines->process(
            $playerId,
            $currentStorage,
            $hseBonus,
            $deltaHours,
            $tsvc,
            $protectionSvc,
            $pipelineStaffing
        );
        $this->addSectionTiming('pipelines', (int)round((microtime(true) - $pipelinesStarted) * 1000));
        $wellLoop->markPipelinesUnavailable($pipelines->unavailablePipelineIds);
 // Floor na 0 jak pozostale odliczenia gotowki (DB i tak ma GREATEST(0,...)).
 // Floor at 0 like the other cash deductions (DB also applies GREATEST(0,...)).
        $wellLoop->totalCosts     += abs($pipelines->cashDelta);
        $playerCash               = max(0.0, $playerCash - abs($pipelines->cashDelta));
        $this->disastersTriggered += $pipelines->disastersTriggered;
        $pipelineOilLost = min($currentStorage, max(0.0, $pipelines->oilLostBbl));
        if ($pipelineOilLost > 0.001) {
            $remainingAttributedLoss = $pipelineOilLost;
            foreach ($pipelines->oilLostByHubBbl as $hubId => $hubLossBbl) {
                if ($remainingAttributedLoss <= 0.001) {
                    break;
                }
                $appliedToHub = min($remainingAttributedLoss, max(0.0, (float)$hubLossBbl));
                $wellLoop->consumeHubInputForLoss((int)$hubId, $appliedToHub, $this->oilPrice);
                $remainingAttributedLoss -= $appliedToHub;
            }
            $currentStorage = max(0.0, $currentStorage - $pipelineOilLost);
            $wellLoop->finLossBbl += $pipelineOilLost;
            $wellLoop->finLossValue += round($pipelineOilLost * $this->oilPrice, 2);
            GameLog::info('tick', 'pipeline_explosion_storage_loss_applied', [
                'player_id' => $playerId,
                'lost_bbl' => round($pipelineOilLost, 4),
            ]);
        }

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
                throw $e;
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
                $roadSvc        = $services['road'];
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
                throw $e;
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
                throw $e;
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
        $finSvc = $services['finance'];
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
            throw $e;
        }
        $this->addSectionTiming('storage_save', (int)round((microtime(true) - $storageSaveStarted) * 1000));

        $settlement = $services['transactions']->settleTickCosts(
            $playerId, $now->format('Y-m-d H:i:s'), [
                FinancialTransactionService::TYPE_TAX => $wellLoop->finTax,
                FinancialTransactionService::TYPE_TICK_OPEX => max(0.0, $wellLoop->finOpex - $wellLoop->finHubUsageCost),
                FinancialTransactionService::TYPE_HUB_USAGE => $wellLoop->finHubUsageCost,
                FinancialTransactionService::TYPE_TICK_SALARY => $wellLoop->finSalary,
                FinancialTransactionService::TYPE_TICK_TRANSPORT => $wellLoop->finTransport,
                FinancialTransactionService::TYPE_TICK_INCIDENT => $wellLoop->finIncident + abs($pipelines->cashDelta) + abs($spill->cashDelta),
            ], $wellLoop->totalCosts
        );
        $playerCash = $settlement['cash_after'];

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
            throw $e;
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
        $this->addSectionTiming('financial_state', (int)round((microtime(true) - $financialStateStarted) * 1000));

 // Aktualizuj liczniki globalne / Update global counters
        $this->playersProcessed++;
        $this->wellsActive  += $wellLoop->finWellsActive;
        $this->totalBbl     += $wellLoop->finBbl;
        $this->totalRevenue += $wellLoop->finRevenue;
        $this->totalOpex    += ($wellLoop->finOpex + $wellLoop->finSalary + $wellLoop->finTransport);

    }
}
