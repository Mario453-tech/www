<?php

/**
 * WellProductionHandler - transport, OPEX i produkcja odwiertu.
 * WellProductionHandler - well transport, OPEX and production.
 *
 * Odpowiada za: / Responsible for:
 * - ustalanie konfiguracji transportu i statusu rurociagu / resolving transport config and pipeline status
 * - obliczanie i pobieranie OPEX-u, pauzowanie/wznawianie odwiertu / OPEX calculation, charging and well pause/resume
 * - produkcje ropy z uwzglednieniem mnoznikow / oil production with multipliers applied
 * - zdarzenia transportowe (stary model + drogowy + morski) / transport events (old model + road + offshore)
 */
class WellProductionHandler
{
    private WellProductionSection $ctx;

    public function __construct(WellProductionSection $ctx)
    {
        $this->ctx = $ctx;
    }

 /**
 * Ustala typ transportu, config i status rurociagu.
 * Resolves transport type, config and pipeline status.
 *
 * @param array<string, mixed> $well
 * @return array<int, mixed>
 */
    public function resolveTransportConfig(array $well, int $wellId): array
    {
        $wellType         = (string)($well['well_type'] ?? 'onshore');
        $defaultTransport = $wellType === 'offshore' ? 'tankowiec' : 'nieustawiony';
        $transportType    = (string)($well['transport_type'] ?? $defaultTransport);
        $transportCfg     = $this->ctx->transportConfig[$transportType] ?? TransportConfigService::getDefaults()[$defaultTransport];
        $transportCapPct  = (float)($well['transport_capacity_pct'] ?? $transportCfg['capacity']);
        $transportOpexPct = (float)($well['transport_opex_pct']     ?? $transportCfg['opex']);
        $transportIncidentMult = (float)($transportCfg['incident'] ?? 1.0);
        $transportDisasterMult = (float)($transportCfg['disaster'] ?? 1.0);
        $transportWearMult     = (float)($transportCfg['wear']     ?? 1.0);
        $wellPipeline          = $this->ctx->wellPipelineCache[$wellId] ?? null;
        $pipelineStatus        = $wellPipeline !== null ? (string)($wellPipeline['status'] ?? 'active') : '';

        if ($wellType !== 'offshore' && $transportType === 'nieustawiony') {
            return [
                'nieustawiony',
                $transportCfg,
                0.0,
                0.0,
                1.0,
                1.0,
                1.0,
                $wellPipeline,
            ];
        }

 // Legacy stale selection: pipeline was prefilled in old data, but no pipeline exists.
 // Stary autopreset: w danych siedzi rurociag, ale odwiert nie ma zadnego wpisu pipeline.
        if ($transportType === 'rurociag' && $wellPipeline === null && $wellType !== 'offshore') {
            return [
                'nieustawiony',
                $this->ctx->transportConfig['nieustawiony'] ?? TransportConfigService::getDefaults()['nieustawiony'],
                0.0,
                0.0,
                1.0,
                1.0,
                1.0,
                null,
            ];
        }

 // Land wells with a pipeline in build or deliberately suspended fall back to road transport
 // (suspend promises "well switches to road transport"). 'damaged'/'disabled' do NOT fall back:
 // a destroyed pipeline stops the flow (capPct=0 below) instead of silently hauling by road
 // at full truck capacity with no repair incentive.
 // Odwierty ladowe z rurociagiem w budowie lub celowo wstrzymanym przechodza na fallback
 // drogowy (suspend obiecuje przejscie na transport drogowy). 'damaged'/'disabled' NIE
 // przechodza: zniszczony rurociag zatrzymuje przesyl (capPct=0 nizej), zamiast po cichu
 // jezdzic ciezarowkami bez motywacji do naprawy.
        if ($transportType === 'rurociag' && $wellType !== 'offshore'
            && in_array($pipelineStatus, ['building', 'suspended'], true)) {
            $transportType = 'ciezarowki';
            $transportCfg = $this->ctx->transportConfig[$transportType] ?? TransportConfigService::getDefaults()['ciezarowki'];
            $transportCapPct = (float)($transportCfg['capacity'] ?? 100.0);
            $transportOpexPct = (float)($transportCfg['opex'] ?? 0.0);
            $transportIncidentMult = (float)($transportCfg['incident'] ?? 1.0);
            $transportDisasterMult = (float)($transportCfg['disaster'] ?? 1.0);
            $transportWearMult = (float)($transportCfg['wear'] ?? 1.0);
        }

        if ($transportType === 'rurociag' && $wellPipeline !== null) {
 // 'servicing' = rurociag w naprawie: brak przesylu (jak przy damaged/disabled).
 // 'servicing' = pipeline under repair: no throughput (like damaged/disabled).
            if (in_array($pipelineStatus, ['damaged','disabled','servicing'], true)) {
                $transportCapPct = 0.0;
            } elseif ($pipelineStatus === 'leak') {
 // Leaking pipeline: reduced throughput; extra transport_loss applied in PipelineSection
                $transportCapPct *= 0.75;
            } elseif ($pipelineStatus === 'critical') {
                $transportCapPct *= 0.60;
            } elseif ($pipelineStatus === 'degraded') {
                $transportCapPct *= 0.85;
            }
        }

        if ($transportType === 'ciezarowki' && ($well['equipment_tier'] ?? 'standard') === 'black_market') {
            $transportIncidentMult *= 1.25;
            $transportDisasterMult *= 1.10;
        }

        return [$transportType, $transportCfg, $transportCapPct, $transportOpexPct,
                $transportIncidentMult, $transportDisasterMult, $transportWearMult, $wellPipeline];
    }

 /**
 * OPEX - pobiera koszt, pauzuje lub wznawia odwiert.
 * OPEX - charges cost, pauses or resumes well.
 * Zwraca false jezeli produkcja ma byc pominieta (brak kasy / pelny magazyn).
 * Returns false if production should be skipped (no cash / full storage).
 *
 * @param array<string, mixed> $well
 */
    public function processOpex(array &$well, int $wellId, int $playerId, float $deltaHours, float $storageCapacity, ?object $tsvc): bool
    {
 // BRAMKA PORTU przed OPEX: odwiert tankowcowy w regionie bez aktywnego portu nie moze
 // produkowac (bramka portu w processProduction), wiec naliczanie pelnego OPEX co tick
 // tylko wykrwawialoby gracza w nieskonczonosc. Zero produkcji = zero OPEX.
 // PORT GATE before OPEX: a tanker well in a region with no active port cannot produce
 // (port gate in processProduction), so charging full OPEX every tick would just bleed
 // the player forever. No production = no OPEX.
        $wellTypeForPort  = (string)($well['well_type'] ?? 'onshore');
        $transportForPort = (string)($well['transport_type'] ?? ($wellTypeForPort === 'offshore' ? 'tankowiec' : 'nieustawiony'));
        if ($transportForPort === 'tankowiec'
            && $this->ctx->marineDeliverySvc !== null
            && !$this->ctx->marineDeliverySvc->regionHasPort((int)($well['region_id'] ?? 0))
        ) {
            GameLog::info('tick', 'marine well no-port: OPEX and production skipped', [
                'well_id'   => $wellId,
                'player_id' => $playerId,
                'region_id' => (int)($well['region_id'] ?? 0),
            ]);
            return false;
        }

        $opexPerHour = $this->ctx->wellService->getOpexPerHour($well);
        if ($well['status'] === 'paused_storage') $opexPerHour *= 0.30;
        $opexTotal = $opexPerHour * $deltaHours
 * $this->ctx->gBalanceMults['opex']
 * (float)($this->ctx->financeTechnicalMods['opex_mult'] ?? 1.0);

        if ($this->ctx->loopCtx->playerCash < $opexTotal) {
            if (in_array($well['status'], ['active','contaminated','no_technician','paused_storage','paused_cash'])) {
                $this->ctx->db->prepare("UPDATE wells SET status = 'paused_cash' WHERE id = :id AND player_id = :pid")->execute([':id' => $wellId, ':pid' => $playerId]);
                GameLog::info('tick', 'well paused_cash (no cash)', ['well_id' => $wellId, 'player_id' => $playerId, 'cash' => $this->ctx->loopCtx->playerCash, 'opex' => $opexTotal]);
            }
            $charged = min($opexTotal, $this->ctx->loopCtx->playerCash);
            if ($charged > 0.0) {
                $this->ctx->loopCtx->finOpex += $charged;
            }
            $this->ctx->loopCtx->totalCosts += $opexTotal;
            $this->ctx->loopCtx->playerCash = 0.0;
            return false;
        }
        $this->ctx->loopCtx->finOpex    += $opexTotal;
        $this->ctx->loopCtx->totalCosts += $opexTotal;
        $this->ctx->loopCtx->playerCash -= $opexTotal;

        if ($well['status'] === 'paused_storage') {
            $freeSpace = $storageCapacity - $this->ctx->loopCtx->currentStorage;
            // Transport odroczony (bufor drogowy MySQL / bufor morski) nie uzywa lokalnego
            // magazynu — pelny magazyn nie moze wiecznie wstrzymywac odwiertu, ktory po zmianie
            // typu transportu wysyla rope do wlasnego bufora (wczesniej: wieczna pauza + 30% OPEX).
            // Deferred transport (MySQL road buffer / marine buffer) does not use local storage —
            // a full tank must not keep pausing a well that, after a transport switch, ships oil
            // to its own staging buffer (previously: permanent pause + 30% OPEX).
            $isDeferredTransport = (
                $transportForPort === 'ciezarowki'
                && $this->ctx->roadTransportSvc !== null
                && $this->ctx->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite'
            ) || (
                $transportForPort === 'tankowiec'
                && $this->ctx->marineDeliverySvc !== null
            );
            if ($freeSpace > 0 || $isDeferredTransport) {
                $this->ctx->db->prepare("UPDATE wells SET status = 'active' WHERE id = :id AND player_id = :pid")->execute([':id' => $wellId, ':pid' => $playerId]);
                $well['status'] = 'active';
                GameLog::info('tick', 'well resumed (storage has space or deferred transport)', ['well_id' => $wellId, 'free_space' => round($freeSpace, 1), 'deferred' => $isDeferredTransport]);
            } else {
                return false;
            }
        }

        if ($well['status'] === 'paused_cash') {
            $this->ctx->db->prepare("UPDATE wells SET status = 'active' WHERE id = :id AND player_id = :pid")->execute([':id' => $wellId, ':pid' => $playerId]);
            $well['status'] = 'active';
            GameLog::info('tick', 'well resumed (cash available)', ['well_id' => $wellId, 'player_id' => $playerId]);
        }
        return true;
    }

 /**
 * Produkcja, transport i finanse odwiertu.
 * Well production, transport and financials.
 *
 * @param array<string, mixed> $well
 * @param array<string, mixed> $hseBonus
 * @param array<string, mixed> $mults
 * @param array<string, mixed> $transportCfg
 * @param array<string, mixed>|null $wellPipeline
 * @param list<array<string, mixed>> $activeRegEvents
 */
    public function processProduction(
        array   $well, int $wellId, int $playerId, float $deltaHours,
        float   $storageCapacity, array $hseBonus,
        ?int    $operatorId, array $mults,
        string  $transportType, array $transportCfg,
        float   $transportCapPct, float $transportOpexPct,
        ?array  $wellPipeline,
        float   $offlineProdMult, float $incidentProdDrop,
        ?object $regionalSvc, array $activeRegEvents, ?object $tsvc
    ): void {
        if (($well['well_type'] ?? 'onshore') !== 'offshore' && $transportType === 'nieustawiony') {
            GameLog::info('tick', 'well waiting for transport selection', [
                'well_id' => $wellId,
                'player_id' => $playerId,
            ]);
            return;
        }

 // BRAMKA PORTU: odwiert tankowcowy produkuje i wysyla rope dopiero gdy w jego
 // regionie istnieje aktywny port. Brak portu = produkcja wstrzymana (zero ropy,
 // zero kosztu, zero rejsu) — nie tworzymy dostaw, ktore i tak nie maja dokad plynac.
 // PORT GATE: a tanker well produces and ships oil only once an active port exists
 // in its region. No port = production paused (no oil, no cost, no voyage) — we do
 // not create deliveries that would have nowhere to go.
        if ($transportType === 'tankowiec'
            && $this->ctx->marineDeliverySvc !== null
            && !$this->ctx->marineDeliverySvc->regionHasPort((int)($well['region_id'] ?? 0))
        ) {
            GameLog::info('tick', 'marine well paused (no port in region)', [
                'well_id'   => $wellId,
                'player_id' => $playerId,
                'region_id' => (int)($well['region_id'] ?? 0),
            ]);
            return;
        }

 // BRAMKA TRANSPORTU: rurociag uszkodzony/wylaczony/w naprawie = zerowa przepustowosc
 // (resolveTransportConfig ustawia transportCapPct=0 dla damaged/disabled/servicing).
 // Bez tej bramki odwiert co tick wyczerpywalby rezerwuar i naliczalby finGross, tracac
 // cala produkcje (transportCapPct=0 -> transportLimitedBbl=0 -> actual=0), bez przychodu
 // i bez sygnalu. Wstrzymujemy produkcje do naprawy rurociagu (przepustowosc wraca > 0).
 // TRANSPORT GATE: a damaged/disabled/servicing pipeline has zero throughput
 // (resolveTransportConfig sets transportCapPct=0). Without this gate the well would drain
 // its reservoir and book finGross every tick, losing all production
 // (transportCapPct=0 -> transportLimitedBbl=0 -> actual=0) with zero revenue and no signal.
 // Pause output until the pipeline is repaired (throughput returns > 0).
        if ($transportType === 'rurociag' && $transportCapPct <= 0.0) {
            GameLog::info('tick', 'pipeline well paused (no throughput - pipeline down)', [
                'well_id'   => $wellId,
                'player_id' => $playerId,
            ]);
            return;
        }

        if ($transportType === 'ciezarowki' && $transportCapPct <= 0.0) {
            return; // brak konfiguracji transportu
        }

        if ($this->ctx->oilPrice <= 0) {
 // M11: Cena ropy jest 0 lub ujemna — uzywam awaryjnej 70 USD/bbl.
 // M11: Oil price is 0 or negative — using emergency fallback 70 USD/bbl.
            GameLog::warn('tick', 'oil_price_fallback_used', ['well_id' => $wellId, 'ctx_price' => $this->ctx->oilPrice]);
        }
        $price = $this->ctx->oilPrice > 0 ? $this->ctx->oilPrice : 70.0;

        $effectiveProd  = $this->ctx->wellService->getEffectiveProduction($well) * $this->ctx->gBalanceMults['production'];
        $effectiveProd *= $mults['opEfficiencyMult'] * $mults['eqMults']['prod'] * $mults['opProdPerkMult'] * $offlineProdMult * $mults['layerRichnessMult'];
        if ($incidentProdDrop > 0) $effectiveProd *= max(0, 1.0 - $incidentProdDrop);

 // Zdarzenia regionalne / Regional events
        $regEventTaxExtra = 0.0;
        $regionCode       = $well['region_code'] ?? null;
        if ($regionCode && $regionalSvc && !empty($activeRegEvents)) {
            $regMods = $regionalSvc->getWellModifiers($playerId, $regionCode, $activeRegEvents);
            if ($regMods['prod_mult'] < 1.0) {
                $effectiveProd *= $regMods['prod_mult'];
                GameLog::info('tick', 'regional_event_prod_reduction', ['well_id' => $wellId, 'region' => $regionCode, 'prod_mult' => $regMods['prod_mult']]);
            }
            $regEventTaxExtra = $regMods['tax_extra'];
        }

        $producedBbl = max(0.0, round($effectiveProd * $deltaHours, 4));
        $this->ctx->loopCtx->producedBbl += $producedBbl;
        $this->ctx->loopCtx->finGross    += round($producedBbl * $price, 2);

        // Pomniejsz rezerwuar o wydobyte baryłki w tym tiku. / Deplete reservoir by barrels produced in this tick.
        if ($producedBbl > 0) {
            $this->ctx->db->prepare(
                "UPDATE wells SET reservoir_remaining = GREATEST(0, reservoir_remaining - ?) WHERE id = ? AND player_id = ?"
            )->execute([round($producedBbl, 4), $wellId, $playerId]);
        }

 // Transport capacity limit
        $transportLimitedBbl = min($producedBbl, $producedBbl * ($transportCapPct / 100.0));

 // Absolutny cap przepustowosci rurociagu leg-1 (real_capacity_bph * deltaHours):
 // procentowy limit skaluje sie z produkcja, wiec mnozniki (operator, sprzet, warstwa)
 // przepychaly przez rure wielokrotnosc jej nominalnej przepustowosci.
 // Absolute leg-1 pipeline throughput cap (real_capacity_bph * deltaHours): the
 // percentage limit scales with production, so multipliers (operator, equipment, layer)
 // pushed a multiple of the pipe's rated capacity through it.
        if ($transportType === 'rurociag' && $wellPipeline !== null) {
            $pipeCapBph = (float)($wellPipeline['real_capacity_bph'] ?? 0.0);
            $pipeCapBbl = max(0.0, $pipeCapBph * $deltaHours);
            if ($transportLimitedBbl > $pipeCapBbl) {
                GameLog::info('tick', 'pipeline_leg1_capacity_cap', [
                    'well_id'      => $wellId,
                    'player_id'    => $playerId,
                    'requested'    => round($transportLimitedBbl, 2),
                    'cap_bbl'      => round($pipeCapBbl, 2),
                    'capacity_bph' => $pipeCapBph,
                ]);
                $transportLimitedBbl = $pipeCapBbl;
            }
        }

        $freeSpace           = $storageCapacity - $this->ctx->loopCtx->currentStorage;

        $transportCapacityLoss = max(0.0, round($producedBbl - $transportLimitedBbl, 4));
        if ($transportCapacityLoss > 0.0) {
            $this->ctx->loopCtx->transportCapacityLossBbl += $transportCapacityLoss;
            $this->ctx->loopCtx->recordPreStorageLoss($transportCapacityLoss, $price);
        }

 // Odroczone typy transportu (bufor drogowymorski) nie uzywaja lokalnego magazynu —
 // ropa jedzie do bufora i kreditowana jest dopiero po dostawie. Dlatego limit wolnego
 // miejsca i pauza paused_storage nie dotycza ich: pelny magazyn nie blokuje wysylki.
 // Deferred transport types (road/marine buffer) do not use local storage —
 // oil goes to a staging buffer and is credited only after delivery. Therefore the
 // free-space cap and paused_storage pause do NOT apply to them.
        $isMysqlDriver = $this->ctx->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite';
        $deferredHubInput = (
            $transportType === 'ciezarowki'
            && $this->ctx->roadTransportSvc !== null
            && $isMysqlDriver
        ) || (
            $transportType === 'tankowiec'
            && $this->ctx->marineDeliverySvc !== null
        );

        if (!$deferredHubInput) {
 // Pelny magazyn — wstrzymaj odwiert (tylko transport bezposredni) / Full storage — pause well (direct transport only)
            if ($freeSpace <= 0) {
                if ($transportLimitedBbl > 0.0) {
                    $this->ctx->loopCtx->storageBlockedBbl += $transportLimitedBbl;
                    $this->ctx->loopCtx->recordPreStorageLoss($transportLimitedBbl, $price);
                }
                $this->ctx->db->prepare("UPDATE wells SET status = 'paused_storage' WHERE id = :id AND player_id = :pid")->execute([':id' => $wellId, ':pid' => $playerId]);
                GameLog::info('tick', 'well paused_storage (storage full)', ['well_id' => $wellId, 'player_id' => $playerId]);
                return;
            }
            $actual = min($transportLimitedBbl, $freeSpace);
            $storageBlocked = max(0.0, round($transportLimitedBbl - $actual, 4));
            if ($storageBlocked > 0.0) {
                $this->ctx->loopCtx->storageBlockedBbl += $storageBlocked;
                $this->ctx->loopCtx->recordPreStorageLoss($storageBlocked, $price);
            }
 // Akumulacje wejscia hubu przenosimy PONIZEJ odjecia straty rurociagu leg-1, aby hub
 // dostawal wolumen netto — inaczej hub przerabia (i leg-2 nalicza) barylki utracone w leg-1.
 // Hub input accumulation is deferred to AFTER the leg-1 pipeline loss deduction so the hub
 // receives net volume — otherwise it processes (and leg-2 charges) barrels lost in leg-1.
            $applyHubSync = true;
        } else {
 // Odroczone: pojemnosc lokalna nieistotna — cala przetransportowana porcja idzie do bufora.
 // Deferred: local capacity irrelevant — the full transported volume goes to the staging buffer.
            $actual = $transportLimitedBbl;
            $applyHubSync = false;
        }

 // Straty transportowe (rurociag) / Pipeline transport losses
        if ($actual > 0 && $transportType === 'rurociag' && $wellPipeline !== null) {
            $transportLossPct = (float)($wellPipeline['transport_loss'] ?? 0.0);
            if ($transportLossPct > 0) {
                $lostOil = round($actual * ($transportLossPct / 100) * $this->ctx->gBalanceMults['loss'] * (float)($this->ctx->financeLogisticsMods['loss_mult'] ?? 1.0), 4);
                $actual  = max(0, $actual - $lostOil);
                if ($lostOil > 0.0) {
                    $this->ctx->loopCtx->transportLossBbl += $lostOil;
                    $this->ctx->loopCtx->recordPreStorageLoss($lostOil, $price);
                }
                if ($lostOil > 0.01) {
                    GameLog::info('tick', 'transport_loss', [
                        'well_id'      => $wellId, 'player_id' => $playerId,
                        'loss_pct'     => $transportLossPct,
                        'lost_bbl'     => round($lostOil, 3),
                        'actual_after' => round($actual, 3),
                    ]);
                }
            }
        }

 // Akumulacja wejscia hubu przeniesiona NIZEJ — za zdarzenia transportowe (leak/pressure_drop
 // w handleTransportEvent i straty fallbackow), obok recordHubWellDelivered. Hub musi dostac
 // dokladnie to, co dotarlo do magazynu — wczesniej dostawal brutto sprzed zdarzen, wiec
 // przerabial (i leg-2 naliczal koszty od) barylki utracone w drodze, a straty liczyly sie 2x.
 // Hub input accumulation moved BELOW — past the transport events (leak/pressure_drop in
 // handleTransportEvent and fallback losses), next to recordHubWellDelivered. The hub must
 // receive exactly what reached storage — previously it got the pre-event gross volume, so it
 // processed (and leg-2 charged for) barrels lost in transit, double-counting the loss.

        if ($actual <= 0) return;

        $this->ctx->db->prepare("UPDATE wells SET status = 'active', last_production_at = NOW() WHERE id = :id AND player_id = :pid")->execute([':id' => $wellId, ':pid' => $playerId]);

 // Zdarzenia transportowe - przed dodaniem do magazynu i liczeniem finansow.
 // Transport events - before adding to storage and calculating financials.
        $actualBeforeEvent = $actual;
        $storageLossBbl    = 0.0;
 // Sciezki fallbackowe (SQLite road processTick, offshore processTick) naliczaja SWOJ pelny
 // koszt transportu; flaga wylacza pozniejsze wspolne bloki % opex i cost_per_bbl, aby te
 // same barylki nie placily kilku nakladajacych sie oplat.
 // Fallback paths (SQLite road processTick, offshore processTick) charge their OWN full
 // transport cost; the flag disables the later shared % opex and cost_per_bbl blocks so the
 // same barrels do not pay several overlapping fees.
        $fallbackFullyCharged = false;

        if ($transportType === 'ciezarowki' && $this->ctx->roadTransportSvc !== null) {
            $roadCfg       = $this->ctx->roadConfigCache[$wellId] ?? null;
            $politicalRisk = (int)($well['region_political_risk'] ?? 1);
            $isMysql       = $isMysqlDriver;

            if ($isMysql) {
 // Model czasowy: kurs zapisywany w well_road_trips, ropa kreditowana po dostawie.
 // Time-based model: trip saved in well_road_trips, oil credited at delivery.
 //
 // Model akumulacji bufora (taki sam jak tankowiec): ropa gromadzi sie w buforze
 // odwiertu az osiagnie prog min_load_bbl, wtedy ciezarowki wyruszaja z pelnym ladunkiem.
 // Dzieki temu jeden kurs nie zabiera 20 bbl tylko czeka az uzbiera sie np. 250-300 bbl.
 // min_load_bbl = 0 oznacza wysylke natychmiastowa (stary model, per tick).
 // Buffer accumulation model (same as the tanker): oil builds up in the well buffer until
 // it reaches min_load_bbl, then trucks depart with a full load. min_load_bbl = 0 = immediate.
                $minLoadBbl = max(0.0, (float)($transportCfg['min_load_bbl'] ?? 0.0));

                if ($minLoadBbl > 0.0) {
 // Bufor: wartosc sprzed ticka (z SELECT w.* w PlayersSection) + produkcja tego ticka.
 // Buffer: pre-tick value (from SELECT w.* in PlayersSection) + this tick's production.
                    $bufferBbl = (float)($well['road_buffer_bbl'] ?? 0.0) + round($actual, 4);

 // Zapisz nowy stan bufora / Persist updated buffer level
                    $this->ctx->db->prepare(
                        "UPDATE wells SET road_buffer_bbl = COALESCE(road_buffer_bbl, 0) + ? WHERE id = ? AND player_id = ?"
                    )->execute([round($actual, 4), $wellId, $playerId]);

                    GameLog::info('tick', 'road_buffer_add', [
                        'well_id'    => $wellId,
                        'player_id'  => $playerId,
                        'added_bbl'  => round($actual, 3),
                        'buffer_bbl' => round($bufferBbl, 3),
                        'threshold'  => $minLoadBbl,
                    ]);

                    if ($bufferBbl >= $minLoadBbl) {
 // Bufor pelny: dispatch + reset bufora opakowane w transakcje (Fix: nie-atomowe bylo bugiem).
 // Buffer full: dispatch + buffer reset wrapped in a transaction (Fix: non-atomic was a bug).
 // Jezeli INSERT kursow powiedzie sie ale UPDATE bufora rzuci, transakcja jest cofana — brak duplikatow.
 // If trip INSERT succeeds but buffer reset throws, the transaction rolls back — no duplicates.
                        $ownTxRoad = !$this->ctx->db->inTransaction();
                        if ($ownTxRoad) $this->ctx->db->beginTransaction();
                        try {
                            $dispatch = $this->ctx->roadTransportSvc->dispatchTrips(
                                $playerId, $wellId, $bufferBbl, $roadCfg, $politicalRisk
                            );
                            $this->ctx->db->prepare(
                                "UPDATE wells SET road_buffer_bbl = 0 WHERE id = ? AND player_id = ?"
                            )->execute([$wellId, $playerId]);

                            if ($ownTxRoad) $this->ctx->db->commit();

                            if ($dispatch['cost'] > 0.0) {
                                $charged = min($dispatch['cost'], $this->ctx->loopCtx->playerCash);
                                $this->ctx->loopCtx->finTransport += $charged;
                                $this->ctx->loopCtx->totalCosts   += $dispatch['cost'];
                                $this->ctx->loopCtx->playerCash    = max(0.0, $this->ctx->loopCtx->playerCash - $dispatch['cost']);
                            }
                            $this->ctx->loopCtx->roadInTransitBbl += $bufferBbl;
                            GameLog::info('tick', 'road_trips_dispatched', [
                                'well_id'     => $wellId,     'player_id'   => $playerId,
                                'trips_count' => $dispatch['trips_count'],
                                'volume_bbl'  => round($bufferBbl, 2),
                                'cost'        => $dispatch['cost'],
                                'eta_at'      => $dispatch['eta_at'],
                            ]);
                        } catch (Throwable $e) {
                            if ($ownTxRoad && $this->ctx->db->inTransaction()) $this->ctx->db->rollBack();
                            GameLog::error('tick', 'road_dispatch_failed — buffer retained for next tick', $e, [
                                'well_id'    => $wellId,
                                'player_id'  => $playerId,
                                'buffer_bbl' => round($bufferBbl, 3),
                            ]);
 // Bufor pozostaje niezerowany — przy nastepnym ticku kolejna proba wysylki.
 // Buffer stays non-zero — dispatch will be retried next tick.
                        }
                    }
 // Ropa w buforze lub w tranzycie, nie trafia teraz do magazynu.
 // Oil in buffer or in transit, not added to storage now.
                    return;
                }

 // min_load_bbl = 0: model natychmiastowy (stare zachowanie, per tick).
 // min_load_bbl = 0: immediate model (legacy behaviour, per tick).
                $dispatch = $this->ctx->roadTransportSvc->dispatchTrips(
                    $playerId, $wellId, $actual, $roadCfg, $politicalRisk
                );
                if ($dispatch['cost'] > 0.0) {
                    $charged = min($dispatch['cost'], $this->ctx->loopCtx->playerCash);
                    $this->ctx->loopCtx->finTransport += $charged;
                    $this->ctx->loopCtx->totalCosts   += $dispatch['cost'];
                    $this->ctx->loopCtx->playerCash    = max(0.0, $this->ctx->loopCtx->playerCash - $dispatch['cost']);
                }
                $this->ctx->loopCtx->roadInTransitBbl += $actual;
                GameLog::info('tick', 'road_trips_dispatched', [
                    'well_id'     => $wellId,     'player_id'   => $playerId,
                    'trips_count' => $dispatch['trips_count'],
                    'volume_bbl'  => round($actual, 2),
                    'cost'        => $dispatch['cost'],
                    'eta_at'      => $dispatch['eta_at'],
                ]);
                return; // Ropa w tranzycie, nie trafia teraz do magazynu. / Oil in transit, not added to storage now.
            }

 // Fallback SQLite: bezstanowe przetwarzanie per tick (testy jednostkowe).
 // SQLite fallback: stateless per-tick processing (unit tests).
            $roadResult  = $this->ctx->roadTransportSvc->processTick($playerId, $wellId, $actual, $deltaHours, $roadCfg, $hseBonus, $politicalRisk);
            $actual      = $roadResult['delivered_bbl'];
            $roadLostBbl = $roadResult['lost_bbl'];
            $roadCost    = $roadResult['cost'];
            if ($roadLostBbl > 0.0) {
                $this->ctx->loopCtx->transportEventLossBbl += $roadLostBbl;
                $this->ctx->loopCtx->recordPreStorageLoss($roadLostBbl, $price);
            }
            if ($roadCost > 0.0) {
                $this->ctx->loopCtx->finOpex    += $roadCost;
                $this->ctx->loopCtx->totalCosts += $roadCost;
                $this->ctx->loopCtx->playerCash  = max(0.0, $this->ctx->loopCtx->playerCash - $roadCost);
            }
 // Parytet z MySQL: sciezka dispatchowa placi tylko koszt per-trip, wiec fallback
 // rowniez pomija dalsze % opex i cost_per_bbl — inaczej te same barylki bylyby
 // obciazane potrojnie zaleznie od srodowiska.
 // Parity with MySQL: the dispatch path charges per-trip cost only, so the fallback
 // also skips the later % opex and cost_per_bbl — otherwise the same barrels would
 // be charged triple depending on the environment.
            $fallbackFullyCharged = true;
            if (!empty($roadResult['incidents'])) {
                GameLog::info('tick', 'road_transport_incidents', [
                    'well_id'        => $wellId, 'player_id'   => $playerId,
                    'trips_total'    => $roadResult['trips_total'],
                    'trips_lost'     => $roadResult['trips_lost'],
                    'lost_bbl'       => round($roadLostBbl, 2),
                    'incident_types' => array_column($roadResult['incidents'], 'type'),
                ]);
            }

        } elseif ($transportType === 'tankowiec') {
            if ($this->ctx->marineDeliverySvc !== null) {
 // Etap 5 — model akumulacji: ropa gromadzi sie w buforze odwiertu az osiagnie
 // prog min_load_bbl, wtedy tankowiec wyrusza z pelnym ladunkiem.
 // Stage 5 — accumulation model: oil builds up in the well buffer until it reaches
 // min_load_bbl threshold, then the tanker departs with a full load.
 // min_load_bbl = 0 oznacza wysylke natychmiastowa (per tick), wieksze wartosci = akumulacja.
 // min_load_bbl = 0 means immediate dispatch (per tick); larger values = buffer accumulation.
                $minLoadBbl = max(0.0, (float)($transportCfg['min_load_bbl'] ?? 5000.0));

 // Increment bufora, swiezy odczyt (FOR UPDATE) i dispatch w JEDNEJ transakcji.
 // Wczesniej: increment poza transakcja + dispatch ze snapshotu sprzed ticka + 'SET marine_buffer_bbl = 0'
 // — rownolegle przebiegi (ADMIN_FORCE_TICK omija GET_LOCK) duplikowaly ladunek albo zerowaly
 // rope dodana przez drugi przebieg. Teraz: blokada wiersza, dispatch swiezej wartosci
 // i DEKREMENTACJA o wyslana ilosc zamiast zerowania.
 // Buffer increment, fresh read (FOR UPDATE) and dispatch in ONE transaction.
 // Previously: increment outside the tx + dispatch from a pre-tick snapshot + 'SET marine_buffer_bbl = 0'
 // — overlapping runs (ADMIN_FORCE_TICK bypasses GET_LOCK) duplicated cargo or wiped oil added
 // by the other run. Now: row lock, dispatch the fresh value, DECREMENT by dispatched instead of reset.
                $addedBbl      = round($actual, 4);
                $dispatchedBbl = 0.0;
                $isMysqlMar    = $this->ctx->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite';

 // Increment POZA transakcja dispatchu: atomowy '+=' jest bezpieczny, a rollback
 // nieudanego dispatchu nie moze cofnac zapisu wyprodukowanej ropy do bufora.
 // Increment OUTSIDE the dispatch tx: atomic '+=' is safe, and a failed-dispatch
 // rollback must not revert this tick's produced oil from the buffer.
                $this->ctx->db->prepare(
                    "UPDATE wells SET marine_buffer_bbl = COALESCE(marine_buffer_bbl, 0) + ? WHERE id = ? AND player_id = ?"
                )->execute([$addedBbl, $wellId, $playerId]);

 // Optymistyczna bramka: przy min_load_bbl=5000 i ~10 bbl/tick odwiert spedzalby ~499 z 500
 // tikow otwierajac transakcje + SELECT FOR UPDATE (blokada wiersza wells) tylko po to, by
 // stwierdzic, ze bufor nie jest pelny. Szacujemy z wartosci sprzed ticka (dokladnej w typowym
 // przebiegu bez rownoleglosci) i otwieramy transakcje tylko gdy prog jest prawdopodobnie
 // przekroczony. Niedoszacowanie przy rownoleglym przebiegu = dispatch w kolejnym ticku (bufor
 // zostaje), bez utraty ropy ani bledu pieniedzy.
 // Optimistic gate: with min_load_bbl=5000 and ~10 bbl/tick a well would spend ~499 of every 500
 // ticks opening a transaction + SELECT FOR UPDATE (a wells row lock) just to learn the buffer is
 // not full. We estimate from the pre-tick value (exact in the common non-concurrent run) and open
 // the transaction only when the threshold is plausibly crossed. An underestimate under a
 // concurrent run just defers dispatch one tick (buffer persists), with no oil loss or money error.
                $optimisticBuffer = (float)($well['marine_buffer_bbl'] ?? 0.0) + $addedBbl;
                if ($optimisticBuffer < $minLoadBbl) {
                    GameLog::info('tick', 'marine_buffer_add', [
                        'well_id'    => $wellId,
                        'player_id'  => $playerId,
                        'added_bbl'  => round($actual, 3),
                        'buffer_bbl' => round($optimisticBuffer, 3),
                        'threshold'  => $minLoadBbl,
                    ]);
                } else {
                $ownTxMar = !$this->ctx->db->inTransaction();
                if ($ownTxMar) $this->ctx->db->beginTransaction();
                try {
 // Swiezy stan bufora pod blokada wiersza (SQLite w testach nie zna FOR UPDATE).
 // Fresh buffer level under row lock (SQLite in tests has no FOR UPDATE).
                    $selStmt = $this->ctx->db->prepare(
                        "SELECT COALESCE(marine_buffer_bbl, 0) FROM wells WHERE id = ? AND player_id = ?"
                        . ($isMysqlMar ? ' FOR UPDATE' : '')
                    );
                    $selStmt->execute([$wellId, $playerId]);
                    $bufferBbl = (float)$selStmt->fetchColumn();

                    GameLog::info('tick', 'marine_buffer_add', [
                        'well_id'    => $wellId,
                        'player_id'  => $playerId,
                        'added_bbl'  => round($actual, 3),
                        'buffer_bbl' => round($bufferBbl, 3),
                        'threshold'  => $minLoadBbl,
                    ]);

                    if ($bufferBbl >= $minLoadBbl) {
                        $this->ctx->marineDeliverySvc->createDelivery(
                            $playerId, $wellId, $bufferBbl, $deltaHours, $well, $hseBonus
                        );
 // Dekrementacja (nie zerowanie): ropa dodana rownolegle po naszym SELECT nie ginie.
 // Decrement (not reset): oil added concurrently after our SELECT is not wiped.
                        $this->ctx->db->prepare(
                            "UPDATE wells SET marine_buffer_bbl = marine_buffer_bbl - ? WHERE id = ? AND player_id = ?"
                        )->execute([$bufferBbl, $wellId, $playerId]);
                        $dispatchedBbl = $bufferBbl;
                    }

                    if ($ownTxMar) $this->ctx->db->commit();
                } catch (Throwable $e) {
                    if ($ownTxMar && $this->ctx->db->inTransaction()) $this->ctx->db->rollBack();
                    GameLog::error('tick', 'marine_dispatch_failed — buffer retained for next tick', $e, [
                        'well_id'   => $wellId,
                        'player_id' => $playerId,
                        'added_bbl' => round($actual, 3),
                    ]);
 // Bufor pozostaje niezerowany — przy nastepnym ticku kolejna proba wysylki.
 // Buffer stays non-zero — dispatch will be retried next tick.
                    $dispatchedBbl = 0.0;
                }
                }

                if ($dispatchedBbl > 0.0) {
 // Nalicz koszt rejsu od pelnego ladunku / Charge voyage cost for the full load
                    $costPerBbl = (float)($transportCfg['cost_per_bbl'] ?? 0.0);
                    if ($costPerBbl > 0.0) {
                        $voyageCost = round($dispatchedBbl * $costPerBbl * $this->ctx->gBalanceMults['opex']
 * (float)($this->ctx->financeLogisticsMods['transport_cost_mult'] ?? 1.0), 2);
                        $charged = min($voyageCost, $this->ctx->loopCtx->playerCash);
                        $this->ctx->loopCtx->finTransport += $charged;
                        $this->ctx->loopCtx->totalCosts   += $voyageCost;
                        $this->ctx->loopCtx->playerCash    = max(0.0, $this->ctx->loopCtx->playerCash - $voyageCost);
                    }
                }
 // Ropa nie trafia do storage (w buforze lub w tranzycie) / Oil not added to storage now (in buffer or in transit)
                return;
            }
 // Fallback: stary model natychmiastowy (offshoreTransportSvc).
 // Fallback: old immediate model (offshoreTransportSvc).
            if ($this->ctx->offshoreTransportSvc !== null) {
            $offshoreCfg    = $this->ctx->offshoreConfigCache[$wellId] ?? null;
            $politicalRisk  = (int)($well['region_political_risk'] ?? 1);
            $offshoreResult = $this->ctx->offshoreTransportSvc->processTick($playerId, $wellId, $actual, $deltaHours, $offshoreCfg, $hseBonus, $politicalRisk);
            $actual         = $offshoreResult['delivered_bbl'];
            $offshoreLostBbl = $offshoreResult['lost_bbl'];
            $offshoreCost   = $offshoreResult['cost'];
            if ($offshoreLostBbl > 0.0) {
                $this->ctx->loopCtx->transportEventLossBbl += $offshoreLostBbl;
                $this->ctx->loopCtx->recordPreStorageLoss($offshoreLostBbl, $price);
            }
            if ($offshoreCost > 0.0) {
                $charged = min($offshoreCost, $this->ctx->loopCtx->playerCash);
                $this->ctx->loopCtx->finOpex    += $charged;
                $this->ctx->loopCtx->totalCosts += $offshoreCost;
                $this->ctx->loopCtx->playerCash  = max(0.0, $this->ctx->loopCtx->playerCash - $offshoreCost);
            }
 // cost_per_shipment pokrywa caly transport tej porcji: pomin dalsze % opex
 // i cost_per_bbl (wczesniej te same barylki placily trzy nakladajace sie oplaty).
 // cost_per_shipment covers this batch's entire transport: skip the later % opex
 // and cost_per_bbl (previously the same barrels paid three overlapping fees).
            $fallbackFullyCharged = true;
            if (!empty($offshoreResult['incidents'])) {
                GameLog::info('tick', 'offshore_transport_incidents', [
                    'well_id' => $wellId, 'player_id' => $playerId,
                    'shipments_total' => $offshoreResult['shipments_total'],
                    'shipments_lost'  => $offshoreResult['shipments_lost'],
                    'lost_bbl' => round($offshoreLostBbl, 2),
                    'incident_types' => array_column($offshoreResult['incidents'], 'type'),
                ]);
            }
            } // end fallback

        } else {
 // Stary model: rurociag i fallback (tankowiec bez serwisu = stary model).
 // Old model: pipeline and fallback (tanker without service = old model).
            $eventResult = $this->handleTransportEvent($playerId, $wellId, $transportType, $deltaHours, $hseBonus, $well, $actual, $tsvc);
            $lostBbl = $actualBeforeEvent - $actual;
            if ($lostBbl > 0) {
                $this->ctx->loopCtx->transportEventLossBbl += $lostBbl;
                $this->ctx->loopCtx->recordPreStorageLoss($lostBbl, $price);
            }
            $storageLossBbl = (float)($eventResult['storage_loss_bbl'] ?? 0.0);
        }

        if ($storageLossBbl > 0.0) {
            $this->ctx->loopCtx->transportEventLossBbl += $storageLossBbl;
            $this->ctx->loopCtx->finLossBbl            += $storageLossBbl;
            $this->ctx->loopCtx->finLossValue          += round($storageLossBbl * $price, 2);
        }

 // Akumulacja wejscia hubu z wolumenu NETTO (po stratach leg-1 i zdarzeniach transportowych)
 // — hub przerabia dokladnie to, co faktycznie do niego dotarlo. Wariant bez huba dodatkowo
 // CAPUJE $actual limitem fallbacku, dlatego wywolanie musi byc PRZED kredytem magazynu.
 // Hub input accumulation from the NET volume (after leg-1 losses and transport events)
 // — the hub processes exactly what actually arrived. The no-hub variant additionally CAPS
 // $actual with the fallback limit, so this call must run BEFORE the storage credit.
        if ($applyHubSync) {
            $this->ctx->loopCtx->applyHubOrFallback($wellId, $actual, $deltaHours);
        }

        if ($actual <= 0) return;

        $this->ctx->loopCtx->finBbl         += $actual;
        $this->ctx->loopCtx->deliveredBbl   += $actual;
        $this->ctx->loopCtx->finRevenue     += round($actual * $price, 2);
        $this->ctx->loopCtx->currentStorage += $actual;

 // ETAP 4: record what reached storage via this well's hub so WellHubSection
 // can apply the second transport leg (hub -> storage). No-hub wells are ignored.
        $this->ctx->loopCtx->recordHubWellDelivered($wellId, $actual);

        if ($transportType === 'rurociag' && $wellPipeline !== null) {
            // Skalowanie deltaHours (opex_per_tick = PLN na GODZINE, podloga 1 ticka) — bez tego
            // koszt godzinowy rurociagu zalezal od kadencji crona, nie od czasu gry.
            // deltaHours scaling (opex_per_tick = PLN per HOUR, floored at one tick) — without it
            // the pipeline's hourly cost depended on cron cadence, not game time.
            $pipelineTickCost = round(
                (float)($wellPipeline['opex_per_tick'] ?? 0.0)
 * $this->ctx->gBalanceMults['opex']
 * (float)($this->ctx->financeLogisticsMods['transport_cost_mult'] ?? 1.0)
 * max(1.0, $deltaHours),
                2
            );
            $pipelineFlowCost = round(
                $actual
 * (float)($wellPipeline['opex_per_bbl'] ?? 0.0)
 * $this->ctx->gBalanceMults['opex']
 * (float)($this->ctx->financeLogisticsMods['transport_cost_mult'] ?? 1.0),
                2
            );
            $pipelineCost = round($pipelineTickCost + $pipelineFlowCost, 2);
            if ($pipelineCost > 0.0) {
                $charged = min($pipelineCost, $this->ctx->loopCtx->playerCash);
                $this->ctx->loopCtx->finTransport += $charged;
                $this->ctx->loopCtx->totalCosts   += $pipelineCost;
                $this->ctx->loopCtx->playerCash    = max(0.0, $this->ctx->loopCtx->playerCash - $pipelineCost);
                GameLog::info('tick', 'pipeline_transport_cost', [
                    'well_id' => $wellId,
                    'pipeline_id' => (int)($wellPipeline['id'] ?? 0),
                    'tick_cost' => $pipelineTickCost,
                    'flow_cost' => $pipelineFlowCost,
                    'total_cost' => $pipelineCost,
                ]);
            }
        }

 // Transport OPEX (procentowy od przychodu) / Transport OPEX (percentage of revenue)
        if ($transportOpexPct > 0 && !$fallbackFullyCharged) {
            $transportOpex = round($actual * $price * ($transportOpexPct / 100.0) * $this->ctx->gBalanceMults['opex'] * (float)($this->ctx->financeLogisticsMods['transport_cost_mult'] ?? 1.0), 2);
            $charged = min($transportOpex, $this->ctx->loopCtx->playerCash);
            $this->ctx->loopCtx->finTransport += $charged;
            $this->ctx->loopCtx->totalCosts   += $transportOpex;
            $this->ctx->loopCtx->playerCash    = max(0.0, $this->ctx->loopCtx->playerCash - $transportOpex);
            GameLog::info('tick', 'transport_opex', ['well_id' => $wellId, 'transport' => $transportType, 'bbl' => round($actual, 2), 'opex_pct' => $transportOpexPct, 'opex_pln' => $transportOpex]);
        }

 // Koszt staly transportu: PLN za kazda przetransportowana barylke. / Fixed transport cost: PLN per transported barrel.
        $costPerBbl = (float)($transportCfg['cost_per_bbl'] ?? 0.0);
        if ($costPerBbl > 0 && !$fallbackFullyCharged) {
            $transportFixedCost = round($actual * $costPerBbl * $this->ctx->gBalanceMults['opex'] * (float)($this->ctx->financeLogisticsMods['transport_cost_mult'] ?? 1.0), 2);
            $charged = min($transportFixedCost, $this->ctx->loopCtx->playerCash);
            $this->ctx->loopCtx->finTransport += $charged;
            $this->ctx->loopCtx->totalCosts   += $transportFixedCost;
            $this->ctx->loopCtx->playerCash    = max(0.0, $this->ctx->loopCtx->playerCash - $transportFixedCost);
            GameLog::info('tick', 'transport_cost_per_bbl', ['well_id' => $wellId, 'transport' => $transportType, 'bbl' => round($actual, 2), 'cost_per_bbl' => $costPerBbl, 'total_pln' => $transportFixedCost]);
        }

 // Podatek regionalny / Regional tax
        $taxRate = (float)($well['region_tax_rate'] ?? 0.0);
        $taxRate += $regEventTaxExtra;
        if ($taxRate > 0) {
            try {
                $grossRevenue = $actual * $price;
                $taxAmount    = round($grossRevenue * $taxRate * $this->ctx->gBalanceMults['tax'], 2);
                if ($taxAmount > 0) {
                    $charged = min($taxAmount, $this->ctx->loopCtx->playerCash);
                    $this->ctx->loopCtx->totalCosts += $taxAmount;
                    $this->ctx->loopCtx->playerCash  = max(0.0, $this->ctx->loopCtx->playerCash - $taxAmount);
                    $this->ctx->loopCtx->finTax     += $charged;
                    GameLog::info('tick', 'regional_tax', ['well_id' => $wellId, 'player_id' => $playerId, 'tax_rate_pct' => round($taxRate * 100, 2), 'event_tax' => round($regEventTaxExtra * 100, 2), 'gross_rev' => round($grossRevenue, 2), 'tax_amount' => $taxAmount]);
                }
            } catch (Throwable $e) { GameLog::error('tick', 'regional_tax FAILED', $e, ['well_id' => $wellId]); }
        }
    }

 /**
 * Stary model zdarzen transportowych (rurociag + fallback tankowiec).
 * Old model transport events (pipeline + tanker fallback).
 *
 * @param array<string, mixed> $hseBonus
 * @param array<string, mixed> $well
 * @return array{storage_loss_bbl: float}
 */
    public function handleTransportEvent(
        int $playerId, int $wellId, string $transportType, float $deltaHours,
        array $hseBonus, array $well, float &$actual, ?object $tsvc
    ): array {
        $storageLossBbl = 0.0;
        try {
            $politicalRiskLevel   = (int)($well['region_political_risk'] ?? 1);
            $transportEventChance = match($transportType) {
                'ciezarowki' => 0.36 * $deltaHours,
                'tankowiec'  => 0.18 * $deltaHours,
                default      => 0.11 * $deltaHours,
            };
            if ($transportType === 'ciezarowki' && $politicalRiskLevel >= 3) $transportEventChance *= 1.30;
            $transportEventChance *= ($hseBonus['failure_reduction'] ?? 1.0);

            if (mt_rand(1, 100000) > (int)($transportEventChance * 100000)) {
                return ['storage_loss_bbl' => 0.0];
            }

            $eventType     = match($transportType) {
                'ciezarowki' => (mt_rand(0,1) ? 'theft' : 'accident'),
                'tankowiec'  => 'storm',
                default      => (mt_rand(0,1) ? 'leak' : 'pressure_drop'),
            };
            $eventImpact   = [];
            $oilPriceLocal = $this->ctx->oilPrice > 0 ? $this->ctx->oilPrice : 70.0; // M11: fallback logged at production entry

            switch ($eventType) {
                case 'theft':
                    $theftPct    = mt_rand(5, 15) / 100.0;
                    $theftLoss   = round($actual * $theftPct, 2);
                    $actual      = max(0, $actual - $theftLoss);
                    $eventImpact = ['type' => 'theft', 'lost_bbl' => $theftLoss, 'revenue_loss' => round($theftLoss * $oilPriceLocal, 2), 'pct' => round($theftPct * 100, 1)];
                    $tsvc?->notify('incident', $wellId, t('tick.notify.transport_theft', ['id' => $wellId, 'bbl' => $theftLoss, 'pct' => $eventImpact['pct']]));
                    break;
                case 'accident':
                    $eventImpact = ['type' => 'accident', 'lost_bbl' => round($actual, 2)];
                    $actual      = 0;
                    $this->ctx->db->prepare("UPDATE wells SET status = 'paused_cash' WHERE id = ? AND player_id = ? AND status = 'active'")->execute([$wellId, $playerId]);
                    $tsvc?->notify('incident', $wellId, t('tick.notify.transport_accident', ['id' => $wellId]));
                    break;
                case 'storm':
                    $stormLoss   = round($actual * 0.30, 2);
                    $actual      = max(0, $actual - $stormLoss);
                    $eventImpact = ['type' => 'storm', 'lost_bbl' => $stormLoss];
                    $tsvc?->notify('incident', $wellId, t('tick.notify.transport_storm', ['id' => $wellId, 'bbl' => $stormLoss]));
                    break;
                case 'leak':
                    // Wyciek dotyczy ropy w transporcie (biezaca wysylka $actual), nie calego
                    // magazynu gracza. Wczesniej liczony od currentStorage — pojedynczy wyciek
                    // niszczyl 10-20% CALEGO zbiornika. Teraz jak theft/storm: redukcja $actual
                    // (przez referencje) trafia do strat przez $lostBbl = actualBeforeEvent - actual.
                    // A leak affects oil in transport (this shipment $actual), not the whole player
                    // tank. It was computed from currentStorage — a single leak destroyed 10-20% of
                    // the ENTIRE tank. Now, like theft/storm: the $actual reduction (by reference) is
                    // booked as loss via $lostBbl = actualBeforeEvent - actual.
                    $leakPct     = mt_rand(10, 20) / 100.0;
                    $leakLoss    = round($actual * $leakPct, 2);
                    $actual      = max(0, $actual - $leakLoss);
                    $eventImpact = ['type' => 'leak', 'lost_bbl' => $leakLoss, 'pct' => round($leakPct * 100)];
                    $tsvc?->notify('incident', $wellId, t('tick.notify.transport_leak', ['id' => $wellId, 'bbl' => $leakLoss, 'pct' => $eventImpact['pct']]));
                    break;
                case 'pressure_drop':
                    $pdLoss      = round($actual * 0.15, 2);
                    $actual      = max(0, $actual - $pdLoss);
                    $eventImpact = ['type' => 'pressure_drop', 'lost_bbl' => $pdLoss];
                    $tsvc?->notify('task', $wellId, t('tick.notify.transport_pressure_drop', ['id' => $wellId, 'bbl' => $pdLoss]));
                    break;
            }
            GameLog::info('tick', 'transport_event', ['well_id' => $wellId, 'player_id' => $playerId, 'transport' => $transportType, 'event' => $eventType, 'impact' => $eventImpact, 'chance_pct' => round($transportEventChance * 100, 4)]);
        } catch (Throwable $e) {
            GameLog::error('tick', 'transport_event FAILED', $e, ['well_id' => $wellId]);
        }
        return ['storage_loss_bbl' => $storageLossBbl];
    }
}
