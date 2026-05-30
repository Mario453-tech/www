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
        $hasOperationalPipeline = (bool)($wellPipeline['_is_operational'] ?? ($wellPipeline !== null && $pipelineStatus !== 'building'));

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

 // Land wells with an existing but not-yet-operational pipeline fall back to road transport.
 // Odwierty ladowe z istniejacym, ale nieaktywnym rurociagiem przechodza tymczasowo na fallback drogowy.
        if ($transportType === 'rurociag' && !$hasOperationalPipeline && $wellType !== 'offshore') {
            $transportType = 'ciezarowki';
            $transportCfg = $this->ctx->transportConfig[$transportType] ?? TransportConfigService::getDefaults()['ciezarowki'];
            $transportCapPct = (float)($transportCfg['capacity'] ?? 100.0);
            $transportOpexPct = (float)($transportCfg['opex'] ?? 0.0);
            $transportIncidentMult = (float)($transportCfg['incident'] ?? 1.0);
            $transportDisasterMult = (float)($transportCfg['disaster'] ?? 1.0);
            $transportWearMult = (float)($transportCfg['wear'] ?? 1.0);
        }

        if ($transportType === 'rurociag' && $hasOperationalPipeline) {
            if (in_array($pipelineStatus, ['damaged','disabled'], true)) {
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
        $opexPerHour = $this->ctx->wellService->getOpexPerHour($well);
        if ($well['status'] === 'paused_storage') $opexPerHour *= 0.30;
        $opexTotal = $opexPerHour * $deltaHours
 * $this->ctx->gBalanceMults['opex']
 * (float)($this->ctx->financeTechnicalMods['opex_mult'] ?? 1.0);

        if ($this->ctx->loopCtx->playerCash < $opexTotal) {
            if (in_array($well['status'], ['active','contaminated','no_technician','paused_storage','paused_cash'])) {
                $this->ctx->db->prepare("UPDATE wells SET status = 'paused_cash' WHERE id = :id")->execute([':id' => $wellId]);
                GameLog::info('tick', 'well paused_cash (no cash)', ['well_id' => $wellId, 'player_id' => $playerId, 'cash' => $this->ctx->loopCtx->playerCash, 'opex' => $opexTotal]);
            }
            $this->ctx->loopCtx->playerCash = max(0.0, $this->ctx->loopCtx->playerCash - $opexTotal);
            return false;
        }
        $this->ctx->loopCtx->finOpex    += $opexTotal;
        $this->ctx->loopCtx->playerCash -= $opexTotal;

        if ($well['status'] === 'paused_storage') {
            $freeSpace = $storageCapacity - $this->ctx->loopCtx->currentStorage;
            if ($freeSpace > 0) {
                $this->ctx->db->prepare("UPDATE wells SET status = 'active' WHERE id = :id")->execute([':id' => $wellId]);
                $well['status'] = 'active';
                GameLog::info('tick', 'well resumed (storage has space)', ['well_id' => $wellId, 'free_space' => round($freeSpace, 1)]);
            } else {
                return false;
            }
        }

        if ($well['status'] === 'paused_cash') {
            $this->ctx->db->prepare("UPDATE wells SET status = 'active' WHERE id = :id")->execute([':id' => $wellId]);
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

 // Transport capacity limit
        $transportLimitedBbl = min($producedBbl, $producedBbl * ($transportCapPct / 100.0));
        $freeSpace           = $storageCapacity - $this->ctx->loopCtx->currentStorage;

        $transportCapacityLoss = max(0.0, round($producedBbl - $transportLimitedBbl, 4));
        if ($transportCapacityLoss > 0.0) {
            $this->ctx->loopCtx->transportCapacityLossBbl += $transportCapacityLoss;
            $this->ctx->loopCtx->recordPreStorageLoss($transportCapacityLoss, $price);
        }

 // Pelny magazyn / Full storage
        if ($freeSpace <= 0) {
            if ($transportLimitedBbl > 0.0) {
                $this->ctx->loopCtx->storageBlockedBbl += $transportLimitedBbl;
                $this->ctx->loopCtx->recordPreStorageLoss($transportLimitedBbl, $price);
            }
            $this->ctx->db->prepare("UPDATE wells SET status = 'paused_storage' WHERE id = :id")->execute([':id' => $wellId]);
            GameLog::info('tick', 'well paused_storage (storage full)', ['well_id' => $wellId, 'player_id' => $playerId]);
            return;
        }

        $actual = min($transportLimitedBbl, $freeSpace);
        $storageBlocked = max(0.0, round($transportLimitedBbl - $actual, 4));
        if ($storageBlocked > 0.0) {
            $this->ctx->loopCtx->storageBlockedBbl += $storageBlocked;
            $this->ctx->loopCtx->recordPreStorageLoss($storageBlocked, $price);
        }

 // Hub logistics / Fallback cap
        $this->ctx->loopCtx->applyHubOrFallback($wellId, $actual, $deltaHours);

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

        if ($actual <= 0) return;

        $this->ctx->db->prepare("UPDATE wells SET status = 'active', last_production_at = NOW() WHERE id = :id")->execute([':id' => $wellId]);

 // Zdarzenia transportowe - przed dodaniem do magazynu i liczeniem finansow.
 // Transport events - before adding to storage and calculating financials.
        $actualBeforeEvent = $actual;
        $storageLossBbl    = 0.0;

        if ($transportType === 'ciezarowki' && $this->ctx->roadTransportSvc !== null) {
            $roadCfg       = $this->ctx->roadConfigCache[$wellId] ?? null;
            $politicalRisk = (int)($well['region_political_risk'] ?? 1);
            $isMysql       = $this->ctx->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite';

            if ($isMysql) {
 // Model czasowy: kurs zapisywany w well_road_trips, ropa kreditowana po dostawie.
 // Time-based model: trip saved in well_road_trips, oil credited at delivery.
                $dispatch = $this->ctx->roadTransportSvc->dispatchTrips(
                    $playerId, $wellId, $actual, $roadCfg, $politicalRisk
                );
                if ($dispatch['cost'] > 0.0) {
                    $this->ctx->loopCtx->finTransport += $dispatch['cost'];
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
                $this->ctx->loopCtx->finOpex   += $roadCost;
                $this->ctx->loopCtx->playerCash = max(0.0, $this->ctx->loopCtx->playerCash - $roadCost);
            }
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
 // Etap 5: nowy model ropa jedzie morzem jako oddzielna dostawa w czasie.
 // Etap 5: new model oil travels by sea as a separate time-based delivery.
 // Ropa trafi do magazynu dopiero gdy port ja przetworzy (PortSection).
 // Oil credited to storage only when port processes it (PortSection).
                $this->ctx->marineDeliverySvc->createDelivery(
                    $playerId, $wellId, $actual, $deltaHours, $well, $hseBonus
                );
                $this->ctx->loopCtx->marineInTransitBbl += $actual;
 // Nalicz koszt rejsu (staly per bbl konfigurowany w transport_config).
 // Charge voyage cost (fixed per-bbl configured in transport_config).
                $costPerBbl = (float)($transportCfg['cost_per_bbl'] ?? 0.0);
                if ($costPerBbl > 0.0) {
                    $voyageCost = round($actual * $costPerBbl * $this->ctx->gBalanceMults['opex']
 * (float)($this->ctx->financeLogisticsMods['transport_cost_mult'] ?? 1.0), 2);
                    $this->ctx->loopCtx->finTransport += $voyageCost;
                    $this->ctx->loopCtx->playerCash    = max(0.0, $this->ctx->loopCtx->playerCash - $voyageCost);
                }
 // Ropa nie trafia teraz do storage koniec przetwarzania odwiertu.
 // Oil not added to storage now end of well processing.
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
                $this->ctx->loopCtx->finOpex   += $offshoreCost;
                $this->ctx->loopCtx->playerCash = max(0.0, $this->ctx->loopCtx->playerCash - $offshoreCost);
            }
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

        if ($actual <= 0) return;

        $this->ctx->loopCtx->finBbl         += $actual;
        $this->ctx->loopCtx->deliveredBbl   += $actual;
        $this->ctx->loopCtx->finRevenue     += round($actual * $price, 2);
        $this->ctx->loopCtx->currentStorage += $actual;

 // ETAP 4: record what reached storage via this well's hub so WellHubSection
 // can apply the second transport leg (hub -> storage). No-hub wells are ignored.
        $this->ctx->loopCtx->recordHubWellDelivered($wellId, $actual);

        if ($transportType === 'rurociag' && $wellPipeline !== null) {
            $pipelineTickCost = round(
                (float)($wellPipeline['opex_per_tick'] ?? 0.0)
 * $this->ctx->gBalanceMults['opex']
 * (float)($this->ctx->financeLogisticsMods['transport_cost_mult'] ?? 1.0),
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
                $this->ctx->loopCtx->finTransport += $pipelineCost;
                $this->ctx->loopCtx->playerCash   -= $pipelineCost;
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
        if ($transportOpexPct > 0) {
            $transportOpex = round($actual * $price * ($transportOpexPct / 100.0) * $this->ctx->gBalanceMults['opex'] * (float)($this->ctx->financeLogisticsMods['transport_cost_mult'] ?? 1.0), 2);
            $this->ctx->loopCtx->finTransport += $transportOpex;
            $this->ctx->loopCtx->playerCash   -= $transportOpex;
            GameLog::info('tick', 'transport_opex', ['well_id' => $wellId, 'transport' => $transportType, 'bbl' => round($actual, 2), 'opex_pct' => $transportOpexPct, 'opex_pln' => $transportOpex]);
        }

 // Koszt staly transportu: PLN za kazda przetransportowana barylke. / Fixed transport cost: PLN per transported barrel.
        $costPerBbl = (float)($transportCfg['cost_per_bbl'] ?? 0.0);
        if ($costPerBbl > 0) {
            $transportFixedCost = round($actual * $costPerBbl * $this->ctx->gBalanceMults['opex'] * (float)($this->ctx->financeLogisticsMods['transport_cost_mult'] ?? 1.0), 2);
            $this->ctx->loopCtx->finTransport += $transportFixedCost;
            $this->ctx->loopCtx->playerCash   -= $transportFixedCost;
            GameLog::info('tick', 'transport_cost_per_bbl', ['well_id' => $wellId, 'transport' => $transportType, 'bbl' => round($actual, 2), 'cost_per_bbl' => $costPerBbl, 'total_pln' => $transportFixedCost]);
        }

 // Podatek regionalny / Regional tax
        $taxRate = (float)($well['regional_tax_rate'] ?? 0.0);
        if ($taxRate <= 0 && !empty($well['region_tax_rate'])) $taxRate = (float)$well['region_tax_rate'];
        $taxRate += $regEventTaxExtra;
        if ($taxRate > 0) {
            try {
                $grossRevenue = $actual * $price;
                $taxAmount    = round($grossRevenue * $taxRate * $this->ctx->gBalanceMults['tax'], 2);
                if ($taxAmount > 0) {
                    $this->ctx->loopCtx->playerCash -= $taxAmount;
                    $this->ctx->loopCtx->finTax     += $taxAmount;
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
            $oilPriceLocal = $this->ctx->oilPrice > 0 ? $this->ctx->oilPrice : 70.0;

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
                    $this->ctx->db->prepare("UPDATE wells SET status = 'paused_cash' WHERE id = ? AND status = 'active'")->execute([$wellId]);
                    $tsvc?->notify('incident', $wellId, t('tick.notify.transport_accident', ['id' => $wellId]));
                    break;
                case 'storm':
                    $stormLoss   = round($actual * 0.30, 2);
                    $actual      = max(0, $actual - $stormLoss);
                    $eventImpact = ['type' => 'storm', 'lost_bbl' => $stormLoss];
                    $tsvc?->notify('incident', $wellId, t('tick.notify.transport_storm', ['id' => $wellId, 'bbl' => $stormLoss]));
                    break;
                case 'leak':
                    $leakPct                       = mt_rand(10, 20) / 100.0;
                    $leakLoss                      = round($this->ctx->loopCtx->currentStorage * $leakPct, 2);
                    $storageLossBbl                = $leakLoss;
                    $this->ctx->loopCtx->currentStorage = max(0, $this->ctx->loopCtx->currentStorage - $leakLoss);
                    $this->ctx->db->prepare("UPDATE storage SET used = GREATEST(0, used - ?) WHERE player_id = ?")->execute([$leakLoss, $playerId]);
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
