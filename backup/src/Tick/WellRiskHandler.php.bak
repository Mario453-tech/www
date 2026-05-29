<?php

/**
 * WellRiskHandler - degradacja, katastrofy i incydenty odwiertu.
 * WellRiskHandler - well degradation, disasters and incidents.
 *
 * Odpowiada za: / Responsible for:
 *   - degradacje stanu technicznego, risk score i zuzycie spiralne / technical degradation, risk score and spiral wear
 *   - rzut katastrofy (blowout, pozar itp.) / disaster roll (blowout, fire, etc.)
 *   - incydenty produkcyjne (awarie, wycieki, przestoje) / production incidents (failures, leaks, downtime)
 */
class WellRiskHandler
{
    private WellProductionSection $ctx;

    public function __construct(WellProductionSection $ctx)
    {
        $this->ctx = $ctx;
    }

    /**
     * Degradacja stanu technicznego, risk score i zuzycie (wear + spiral decay).
     * Technical condition degradation, risk score update and wear (wear + spiral decay).
     *
     * @param array<string, mixed> $well
     * @param array<string, mixed> $hseBonus
     * @param array<string, mixed> $mults
     */
    public function processDegradationAndRisk(
        array $well, int $wellId, float $deltaHours, array $hseBonus,
        array $mults, float $transportWearMult, float $offlineRiskMult,
        ?int $technicianId
    ): void {
        $ws = $this->ctx->wellService;
        try { $ws->processDegradation($wellId, $deltaHours, $hseBonus,
            $mults['techDegradefMult'] * $mults['wearDegMult'] * $mults['spiralMultEffective']
            * $mults['eqMults']['wear'] * $this->ctx->gBalanceMults['degradation'] * $offlineRiskMult
            * (float)($this->ctx->financeTechnicalMods['degradation_mult'] ?? 1.0));
        } catch (Throwable $e) { GameLog::error('tick', 'processDegradation FAILED', $e, ['well_id' => $wellId]); }

        try { $ws->updateRiskScore($wellId, $deltaHours, $hseBonus); }
        catch (Throwable $e) { GameLog::error('tick', 'updateRiskScore FAILED', $e, ['well_id' => $wellId]); }

        if (in_array($well['status'], ['active','contaminated','no_technician','paused_storage','paused_cash'])) {
            try { $ws->processWear($wellId, $deltaHours, (float)$well['base_production_per_hour'],
                (float)($well['oil_richness'] ?? 1.0), false,
                $mults['spiralWearMult'] * $mults['perkWearMult'] * $mults['eqMults']['wear']
                * $transportWearMult * $this->ctx->gBalanceMults['wear'] * $mults['layerWearMult']
                * (float)($this->ctx->financeTechnicalMods['wear_mult'] ?? 1.0));
            } catch (Throwable $e) { GameLog::error('tick', 'well wear FAILED', $e, ['well_id' => $wellId]); }

            try { $ws->processSpiralDecay($wellId, $deltaHours, $hseBonus); }
            catch (Throwable $e) { GameLog::error('tick', 'spiral decay FAILED', $e, ['well_id' => $wellId]); }
        }
    }

    /**
     * Rzut katastrofy; zwraca true jezeli katastrofa wystapila (well zostaje pominiety).
     * Disaster roll; returns true if a disaster occurred (well is skipped).
     *
     * @param array<string, mixed> $well
     * @param array<string, mixed> $hseBonus
     * @param array<string, mixed> $mults
     */
    public function processDisasterRoll(
        array $well, int $wellId, int $playerId, float $deltaHours,
        array $hseBonus, array $mults,
        float $transportDisasterMult, float $offlineRiskMult,
        ?object $tsvc
    ): bool {
        try {
            $hseForDisaster = $hseBonus;
            $hseForDisaster['catastrophe_mult'] =
                ($hseBonus['catastrophe_mult'] ?? 1.0)
                * $mults['techSpecCatMult']
                * (float)($this->ctx->financeSafetyMods['disaster_mult'] ?? 1.0);

            $disaster = $this->ctx->wellService->processDisasterRoll(
                $wellId, $deltaHours, $hseForDisaster,
                $transportDisasterMult * $this->ctx->gBalanceMults['disaster']
                * $offlineRiskMult * (float)($this->ctx->financeSafetyMods['disaster_mult'] ?? 1.0)
            );

            if (!empty($disaster['disaster'])) {
                $this->ctx->loopCtx->disastersTriggered++;
                GameLog::error('tick', 'INDUSTRIAL DISASTER', null, [
                    'type'      => $disaster['disaster'],
                    'well_id'   => $wellId,
                    'player_id' => $playerId,
                    'cost'      => $disaster['cost']     ?? 0,
                    'env_fine'  => $disaster['env_fine'] ?? 0,
                ]);
                if ($tsvc) {
                    $disasterFull = DisasterMessages::getFull($disaster['disaster'], ($hseBonus['active_hse'] ?? 0) > 0, ['well' => $wellId]);
                    $tsvc->notify('disaster_' . $disaster['disaster'], $wellId,
                        $disasterFull['icon'] . ' ' . $disasterFull['title'] . ': '
                        . ($disaster['desc'] ?? $disasterFull['message']));
                }
                return true;
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'processDisasterRoll FAILED', $e, ['well_id' => $wellId]);
        }
        return false;
    }

    /**
     * Incydenty produkcyjne. Zwraca prod_drop [0.0-1.0] lub 0.0.
     * Production incidents. Returns prod_drop [0.0-1.0] or 0.0.
     *
     * @param  array<string, mixed>      $well
     * @param  array<string, mixed>      $hseBonus
     * @param  array<string, mixed>      $mults
     * @param  array<string, mixed>|null $opRow
     * @param  array<string, mixed>|null $techRow
     * @param  array<string, mixed>|null $opPerk
     * @param  array<string, mixed>|null $techPerk
     */
    public function processIncidents(
        array $well, int $wellId, int $playerId, float $deltaHours,
        array $hseBonus, ?int $operatorId, ?int $technicianId,
        ?array $opRow, ?array $techRow, ?array $opPerk, ?array $techPerk,
        array $mults, float $transportIncidentMult, ?object $tsvc
    ): float {
        try {
            $perkIncidentReduction = 0.0;
            if ($opPerk   && (float)($opPerk['incident_reduction']   ?? 0) > 0) $perkIncidentReduction += (float)$opPerk['incident_reduction'];
            if ($techPerk && (float)($techPerk['incident_reduction'] ?? 0) > 0) $perkIncidentReduction += (float)$techPerk['incident_reduction'];
            $perkCatRed = $techPerk ? (float)($techPerk['catastrophe_reduction'] ?? 0.0) : 0.0;

            $incidentStaffData = [
                'operator_skill'            => $operatorId   ? ($opRow['skill_level']   ?? null) : null,
                'technician_skill'          => $technicianId ? ($techRow['skill_level'] ?? null) : null,
                'no_technician'             => !$technicianId,
                'spiral_mult'               => $mults['spiralMultEffective'],
                'wear_mult'                 => $mults['wearDegMult'],
                'perk_incident_reduction'   => $perkIncidentReduction,
                'perk_catastrophe_reduction'=> $perkCatRed,
                'transport_incident_mult'   => $transportIncidentMult
                    * $this->ctx->gBalanceMults['incident']
                    * (float)($this->ctx->financeLogisticsMods['incident_mult'] ?? 1.0)
                    * (float)($this->ctx->financeSafetyMods['incident_mult']    ?? 1.0),
            ];

            $incidentResult = $this->ctx->incidentSvc !== null
                ? $this->ctx->incidentSvc->processTick($wellId, $playerId, $deltaHours, $well, $incidentStaffData, $hseBonus)
                : ['incident' => null];

            if (!empty($incidentResult['incident'])) {
                $inc  = $incidentResult['incident'];
                $drop = (float)($inc['prod_drop'] ?? 0) / 100.0;
                $this->ctx->loopCtx->incidentsTriggered++;
                if ($inc['cost'] > 0) {
                    $this->ctx->loopCtx->finIncident += (float)$inc['cost'];
                    $this->ctx->loopCtx->playerCash  -= (float)$inc['cost'];
                }
                if ($tsvc) {
                    $tsvc->notify('incident', $wellId, '⚠ ' . ($inc['message'] ?? t('tick.notify.incident_generic', ['id' => $wellId])));
                }
                GameLog::info('tick', 'incident_processed', ['well_id' => $wellId, 'level' => $inc['level'], 'drop_pct' => $inc['prod_drop'], 'cost' => $inc['cost']]);
                return $drop;
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'IncidentService::processTick FAILED', $e, ['well_id' => $wellId]);
        }
        return 0.0;
    }
}
