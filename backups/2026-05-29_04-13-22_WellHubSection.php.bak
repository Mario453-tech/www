<?php

/**
 * WellHubSection - finalizacja hubow po petli odwiertow.
 * WellHubSection - hub finalization after the well loop.
 *
 * Dla kazdego aktywnego huba: / For each active hub:
 *  1. processTick()       -> wear, condition, status, przetworzone/stracone barylki
 *                         -> wear, condition, status, processed/lost barrels
 *  2. persistTickResult() -> zapisuje stan huba / persists hub state
 *  3. Reconciliacja strat -> koryguje currentStorage i fin* / reconciles currentStorage and fin*
 *  4. Hub OPEX            -> koszt per slot, per gracz / per-slot cost per player
 */
class WellHubSection
{
    private WellLoopSection   $ctx;
    private DateTime          $now;
    private ?HubTickService   $hubTickSvc;
    private ?HubIncidentService $hubIncidentSvc;
    private ?HubService       $hubSvc;
    /** @var array<string, float|string> */
    private array             $financeLogisticsMods;
    /** @var array<string, mixed> */
    private array             $gBalanceMults;
    private float             $oilPrice;

    /**
     * @param array<string, float|string> $financeLogisticsMods
     * @param array<string, mixed>        $gBalanceMults
     */
    public function __construct(
        WellLoopSection       $ctx,
        DateTime              $now,
        ?HubTickService       $hubTickSvc,
        ?HubIncidentService   $hubIncidentSvc,
        ?HubService           $hubSvc,
        array                 $financeLogisticsMods,
        array                 $gBalanceMults,
        float                 $oilPrice
    ) {
        $this->ctx                  = $ctx;
        $this->now                  = $now;
        $this->hubTickSvc           = $hubTickSvc;
        $this->hubIncidentSvc       = $hubIncidentSvc;
        $this->hubSvc               = $hubSvc;
        $this->financeLogisticsMods = $financeLogisticsMods;
        $this->gBalanceMults        = $gBalanceMults;
        $this->oilPrice             = $oilPrice;
    }

    /**
     * Przetwarza tick kazdego aktywnego huba i koryguje stan finansowy gracza.
     * Processes the tick for every active hub and reconciles player financial state.
     *
     * @param array<string, mixed> $hseBonus
     */
    public function finalize(int $playerId, float $deltaHours, array $hseBonus): void
    {
        if ($this->hubTickSvc === null || empty($this->ctx->hubCache)) {
            return;
        }

        foreach ($this->ctx->hubCache as $hubId => $hub) {
            try {
                $inputBbl = $this->ctx->hubInputAccum[$hubId] ?? 0.0;

                $result = $this->hubTickSvc->processTick($hub, $inputBbl, $deltaHours, $hseBonus);
                $this->hubTickSvc->persistTickResult($hub, $result, $this->now);

                // Reconciliacja strat. / Loss reconciliation.
                // Podczas petli wells dodalismy $inputBbl do storage/revenue.
                // During the well loop we added $inputBbl to storage/revenue.
                // Teraz odejmujemy to, co hub stracil (ponad przepustowosc).
                // Now we subtract what the hub lost (above throughput capacity).
                $hubLostBbl        = (float)$result['lost_bbl'];
                $logisticsLossMult = (float)($this->financeLogisticsMods['loss_mult'] ?? 1.0);
                if ($hubLostBbl > 0.0 && $logisticsLossMult !== 1.0) {
                    $hubLostBbl = min($inputBbl, round($hubLostBbl * $logisticsLossMult, 4));
                }

                if ($hubLostBbl > 0.001) {
                    $lostVal = round($hubLostBbl * $this->oilPrice, 2);
                    $this->ctx->currentStorage   = max(0.0, $this->ctx->currentStorage - $hubLostBbl);
                    $this->ctx->finBbl          -= $hubLostBbl;
                    $this->ctx->deliveredBbl    -= $hubLostBbl;
                    $this->ctx->finRevenue      -= $lostVal;
                    $this->ctx->finLossBbl      += $hubLostBbl;
                    $this->ctx->finLossValue    += $lostVal;
                    $this->ctx->finHubLossBbl   += $hubLostBbl;
                    $this->ctx->finHubLossValue += $lostVal;
                    GameLog::info('tick', 'hub_tick_loss', [
                        'hub_id'     => $hubId,
                        'player_id'  => $playerId,
                        'input_bbl'  => round($inputBbl, 2),
                        'processed'  => round($result['processed_bbl'], 2),
                        'buffered'   => round((float)$result['buffered_bbl'], 2),
                        'lost_bbl'   => round($hubLostBbl, 2),
                        'load_pct'   => $result['load_pct'],
                        'new_buffer' => round((float)$result['new_buffer'], 2),
                        'new_status' => $result['new_status'],
                    ]);
                } elseif (($result['buffered_bbl'] ?? 0.0) > 0.001) {
                    GameLog::info('tick', 'hub_tick_buffered', [
                        'hub_id'     => $hubId,
                        'player_id'  => $playerId,
                        'input_bbl'  => round($inputBbl, 2),
                        'processed'  => round($result['processed_bbl'], 2),
                        'buffered'   => round((float)$result['buffered_bbl'], 2),
                        'new_buffer' => round((float)$result['new_buffer'], 2),
                        'load_pct'   => $result['load_pct'],
                    ]);
                }

                // Liczba studni gracza w tym hubie (uzywana przez incident i OPEX) / Number of player wells in this hub (used by incident and OPEX)
                $playerWellCount = count(array_filter(
                    $this->ctx->wellHubMap,
                    fn($hId) => $hId === $hubId
                ));

                $this->processHubIncident($hubId, $hub, $inputBbl, $result, $deltaHours, $playerId, $hseBonus, $playerWellCount);
                $this->processHubUsageFee($hubId, $hub, $playerId, $playerWellCount);

            } catch (Throwable $e) {
                GameLog::error('tick', 'finalizeHubTicks FAILED', $e, [
                    'hub_id'    => $hubId,
                    'player_id' => $playerId,
                ]);
            }
        }
    }

    /**
     * Incydenty logistyczne huba / Hub logistics incidents.
     * Losowe zdarzenia (awaria, wyciek, przeciazenie) generowane per hub.
     * Random events (breakdown, spill, overload) generated per hub.
     *
     * @param array<string, mixed> $hub
     * @param array<string, mixed> $result
     * @param array<string, mixed> $hseBonus
     */
    private function processHubIncident(
        int   $hubId,
        array $hub,
        float $inputBbl,
        array $result,
        float $deltaHours,
        int   $playerId,
        array $hseBonus,
        int   $playerWellCount
    ): void {
        if ($this->hubIncidentSvc === null || $playerWellCount <= 0) {
            return;
        }
        try {
            $incident = $this->hubIncidentSvc->processTick(
                $hub, $inputBbl, $result, $deltaHours, $playerId, $hseBonus
            );
            if ($incident !== null && $incident['extra_loss'] > 0.0) {
                $incLoss = (float)$incident['extra_loss'] * (float)($this->financeLogisticsMods['loss_mult'] ?? 1.0);
                $incVal  = round($incLoss * $this->oilPrice, 2);
                $this->ctx->currentStorage          = max(0.0, $this->ctx->currentStorage - $incLoss);
                $this->ctx->finBbl                 -= $incLoss;
                $this->ctx->deliveredBbl           -= $incLoss;
                $this->ctx->finRevenue             -= $incVal;
                $this->ctx->finLossBbl             += $incLoss;
                $this->ctx->finLossValue           += $incVal;
                $this->ctx->finHubIncidentLossBbl  += $incLoss;
                $this->ctx->finHubIncidentLossValue += $incVal;
                $this->ctx->incidentsTriggered++;
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'hub incident check FAILED', $e, [
                'hub_id'    => $hubId,
                'player_id' => $playerId,
            ]);
        }
    }

    /**
     * Hub usage fee — private ownership model.
     * Owner (player_id > 0) pays full opex_per_tick each tick.
     * Tenant (player_id = 0, tenant_player_id > 0) pays full lease_fee_per_tick each tick.
     * Condition penalty applied to owner OPEX: degraded hub costs more to maintain.
     * Wlasciciel placi pelny OPEX; najemca placi pelny czynsz co tick.
     *
     * @param array<string, mixed> $hub
     */
    private function processHubUsageFee(int $hubId, array $hub, int $playerId, int $playerWellCount): void
    {
        if ($playerWellCount <= 0 || $this->hubSvc === null) {
            return;
        }

        $hubOwner  = (int)($hub['player_id']        ?? 0);
        $hubTenant = (int)($hub['tenant_player_id'] ?? 0);

        // Only charge the controlling player (owner or tenant).
        // If neither matches, no charge (legacy or market hub not yet acquired).
        if ($hubOwner !== $playerId && $hubTenant !== $playerId) {
            return;
        }

        $costMult = (float)($this->financeLogisticsMods['hub_cost_mult'] ?? 1.0)
                  * (float)($this->gBalanceMults['opex'] ?? 1.0);

        if ($hubOwner === $playerId) {
            // Owner pays full OPEX with condition penalty
            $modeMultipliers = $this->hubSvc->getWorkModeMultipliers($hub['work_mode'] ?? 'standard');
            $opexMult        = (float)($modeMultipliers['opex_mult'] ?? 1.0);
            $condPct         = (float)($hub['condition_pct'] ?? 100.0);
            $condMult        = match (true) {
                $condPct <= 20.0 => 1.80,
                $condPct <= 30.0 => 1.50,
                $condPct <= 50.0 => 1.25,
                $condPct <  70.0 => 1.10,
                default          => 1.00,
            };
            $usageFee = round((float)$hub['opex_per_tick'] * $opexMult * $condMult * $costMult, 2);
            if ($usageFee > 0.0) {
                $this->ctx->finOpex         += $usageFee;
                $this->ctx->finHubUsageCost += $usageFee;
                $this->ctx->playerCash      -= $usageFee;
                GameLog::info('tick', 'hub_owner_opex', [
                    'hub_id'    => $hubId,
                    'player_id' => $playerId,
                    'opex'      => $usageFee,
                    'cond_pct'  => $condPct,
                    'cond_mult' => $condMult,
                ]);
            }
            return;
        }

        // Tenant pays full lease_fee_per_tick (flat rate, no condition modifier)
        $leaseFee = round((float)($hub['lease_fee_per_tick'] ?? 0.0) * $costMult, 2);
        if ($leaseFee > 0.0) {
            $this->ctx->finOpex         += $leaseFee;
            $this->ctx->finHubUsageCost += $leaseFee;
            $this->ctx->playerCash      -= $leaseFee;
            GameLog::info('tick', 'hub_tenant_lease', [
                'hub_id'    => $hubId,
                'player_id' => $playerId,
                'lease_fee' => $leaseFee,
            ]);
        }
    }
}
