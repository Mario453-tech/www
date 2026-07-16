<?php
declare(strict_types=1);

/**
 * PipelineSection - pipeline degradation and explosions during tick.
 * PipelineSection - degradacja i eksplozje rurociagow podczas ticka.
 */
class PipelineSection
{
    public int $disastersTriggered = 0;
    public float $cashDelta = 0.0; // Negative disaster cost sum / Ujemna suma kosztow katastrof.
    public float $oilLostBbl = 0.0;
    /** @var array<int, float> */
    public array $oilLostByHubBbl = [];
    /** @var list<int> */
    public array $unavailablePipelineIds = [];

    private PDO $db;
    private DateTime $now;
    private WellService $wellService;
    private WellPipelineService $wellPipelineService;
    /** @var array<int, array<string, mixed>> */
    private array $pipelineProtectionCache = [];
    /** @var array<int, float> */
    private array $completedActiveHours = [];

    public function __construct(PDO $db, DateTime $now, WellService $wellService)
    {
        $this->db = $db;
        $this->now = $now;
        $this->wellService = $wellService;
        $this->wellPipelineService = new WellPipelineService($db);
    }

    /**
     * Activates finished builds before production reads pipeline state.
     * Aktywuje zakonczone budowy przed odczytem stanu rurociagu przez produkcje.
     */
    public function completeBuilds(int $playerId, ?object $tsvc): void
    {
        $completed = $this->wellPipelineService->completeBuildingPipelines($playerId);
        foreach ($completed as $done) {
            $pipelineId = (int)$done['id'];
            $this->completedActiveHours[$pipelineId] = max(
                0.0,
                (float)($done['active_seconds'] ?? 0.0) / 3600.0
            );

            GameLog::info('tick', 'Pipeline build complete', [
                'pipeline_id' => $pipelineId,
                'player_id' => $playerId,
                'type' => $done['pipeline_type'] ?? 'unknown',
            ]);
            $tsvc?->notify(
                'pipeline_build_complete',
                null,
                t('tick.notify.pipeline_build_complete', ['id' => $pipelineId])
            );
        }
    }

    /** @return array<int, float> */
    public function completedActiveHours(): array
    {
        return $this->completedActiveHours;
    }

 /**
 * Processes player pipelines - degradation and explosion chance.
 * Przetwarza rurociagi gracza - degradacja i szansa eksplozji.
 *
 * @param float $currentStorage Current storage contents (bbl) / Aktualna zawartosc magazynu (bbl)
 * @param array<string, mixed> $hseBonus Active HSE bonuses / Aktywne bonusy BHP
 * @param float $deltaHours Time since last tick (h) / Czas od ostatniego ticka (h)
 * @param ?object $tsvc TechnicalTeamService for notifications / do powiadomien
 */
    public function process(
        int $playerId,
        float $currentStorage,
        array $hseBonus,
        float $deltaHours,
        ?object $tsvc,
        ?ProtectionService $protection = null
    ): void {
        if ($deltaHours <= 0.0) return;
        $deltaHours = min($deltaHours, 48.0);
        $this->pipelineProtectionCache = [];
        $remainingStorageForDisasters = max(0.0, $currentStorage);
        try {
            $this->completeBuilds($playerId, $tsvc);

 // ETAP 11: degrade and roll incidents for BOTH transport legs independently.
 // Each leg is its own well_pipelines row, so inbound and outbound roll separately.
 // inbound -> wells.transport_type = 'rurociag' (well -> hub)
 // outbound -> logistics_hubs.outbound_transport_type = 'rurociag' (hub -> storage)
 // keyed by well_id=0, hub_id (one pipeline per hub, ETAP 11)

 // Inbound pipelines: keyed by well_id > 0, joined to wells and hub assignment.
            $inboundStmt = $this->db->prepare(
                "SELECT wp.id, wp.well_id, wp.hub_id, wp.leg,
                        wp.condition_pct, wp.transport_loss, wp.status,
                        wp.opex_per_tick, wp.degradation_rate_per_hour, wp.incident_risk_mult
                   FROM well_pipelines wp
                   JOIN wells w ON w.id = wp.well_id
                   JOIN logistics_hub_assignments a ON a.well_id = wp.well_id AND a.status = 'active'
                   JOIN logistics_hubs h ON h.id = wp.hub_id
                  WHERE wp.player_id = ?
                    AND wp.leg = 'inbound'
                    AND wp.well_id > 0
                    AND a.hub_id = wp.hub_id
                    AND h.status NOT IN ('planned', 'disabled', 'building', 'paused', 'maintenance')
                    AND w.transport_type = 'rurociag'
                    AND w.status NOT IN ('seized', 'sold', 'disabled')
                    AND wp.status IN ('active', 'degraded', 'critical', 'leak')
                  ORDER BY wp.condition_pct ASC"
            );
            $inboundStmt->execute([$playerId]);
            $inboundPipelines = $inboundStmt->fetchAll(PDO::FETCH_ASSOC);

 // Outbound pipelines (ETAP 11): keyed by well_id=0, hub_id; joined to logistics_hubs.
            $outboundStmt = $this->db->prepare(
                "SELECT wp.id, wp.well_id, wp.hub_id, wp.leg,
                        wp.condition_pct, wp.transport_loss, wp.status,
                        wp.opex_per_tick, wp.degradation_rate_per_hour, wp.incident_risk_mult
                   FROM well_pipelines wp
                   JOIN logistics_hubs h ON h.id = wp.hub_id
                  WHERE wp.player_id = ?
                    AND wp.leg = 'outbound'
                    AND wp.well_id = 0
                    AND h.status NOT IN ('planned', 'disabled', 'building', 'paused', 'maintenance')
                    AND h.outbound_transport_type = 'rurociag'
                    AND wp.status IN ('active', 'degraded', 'critical', 'leak')
                  ORDER BY wp.condition_pct ASC"
            );
            $outboundStmt->execute([$playerId]);
            $outboundPipelines = $outboundStmt->fetchAll(PDO::FETCH_ASSOC);

            $pipelines = array_merge($inboundPipelines, $outboundPipelines);

            $hasPipelineEngineer = $this->checkPipelineEngineer($playerId);
            if ($protection !== null && $pipelines !== []) {
                $pipelineIds = array_map(
                    static fn(array $pipeline): int => (int)($pipeline['id'] ?? 0),
                    $pipelines
                );
                $this->pipelineProtectionCache = $protection->getActiveProtections(
                    $playerId,
                    'pipeline',
                    $pipelineIds,
                    'pipeline_guard'
                );
            }

            foreach ($pipelines as $pipeline) {
                $pipelineId          = (int) $pipeline['id'];
                $wellId              = (int)($pipeline['well_id'] ?? 0);
                $hubId               = (int)($pipeline['hub_id'] ?? 0);
                $leg                 = (string)($pipeline['leg'] ?? 'inbound');
                $conditionBefore     = (float) $pipeline['condition_pct'];
                $transportLossBefore = (float) ($pipeline['transport_loss'] ?? 0.0);
                $currentStatus       = (string)($pipeline['status'] ?? 'active');
                $opexTickCost        = round((float)($pipeline['opex_per_tick'] ?? 0.0), 2);
                $pipelineDeltaHours  = isset($this->completedActiveHours[$pipelineId])
                    ? min($deltaHours, $this->completedActiveHours[$pipelineId])
                    : $deltaHours;

                $degradeRate = (float) ($pipeline['degradation_rate_per_hour'] ?? 0.05)
 * (float) ($hseBonus['degrade_mult'] ?? 1.0);

                if (!$hasPipelineEngineer) {
                    $degradeRate *= 2.0;
                }

 // Active leak accelerates degradation by 20%
                if ($currentStatus === 'leak') {
                    $degradeRate *= 1.20;
                }

                $newCondition     = max(0.0, $conditionBefore - ($degradeRate * $pipelineDeltaHours));
                $newTransportLoss = $transportLossBefore;

                if (!$hasPipelineEngineer && $newCondition < 80.0) {
                    $newTransportLoss = min(10.0, $transportLossBefore + (0.1 * $pipelineDeltaHours));
                }

 // Leaking pipeline loses additional oil through the crack each tick
                if ($currentStatus === 'leak') {
                    $newTransportLoss = min(10.0, $newTransportLoss + (0.4 * $pipelineDeltaHours));
                }

 // Determine new status
                if ($currentStatus === 'leak') {
 // Leak persists until repair task; only breaks completely when condition=0
                    $newStatus = $newCondition <= 0.0 ? 'damaged' : 'leak';
                } else {
                    $newStatus = match (true) {
                        $newCondition <= 0.0 => 'damaged',
                        $newCondition < 40.0 => 'critical',
                        $newCondition < 70.0 => 'degraded',
                        default              => 'active',
                    };

 // Spontaneous leak trigger when condition drops below 60%
                    if ($newStatus !== 'damaged' && $newCondition < 60.0) {
                        $leakChance = 0.0008
                         * $pipelineDeltaHours
 * (float)($pipeline['incident_risk_mult'] ?? 1.0)
 * ((60.0 - $newCondition) / 60.0)
 * ($hasPipelineEngineer ? 1.0 : 2.0);
                        if (mt_rand(1, 1_000_000) <= (int) round($leakChance * 1_000_000)) {
                            $newStatus = 'leak';
                        }
                    }
                }

                $this->db->prepare(
                    "UPDATE well_pipelines
                        SET condition_pct   = ?,
                            transport_loss  = ?,
                            status          = ?,
                            damaged_at      = CASE WHEN ? = 'damaged' THEN NOW() ELSE damaged_at END,
                            leak_started_at = CASE
                                                WHEN ? = 'leak'
                                                THEN COALESCE(leak_started_at, NOW())
                                                ELSE NULL
                                              END
                      WHERE id = ? AND player_id = ?"
                )->execute([
                    // round(,4) + kolumna DECIMAL(8,4): degradacja przy tickach 5-min
                    // (0.0017-0.0042/tick) nie moze zaokraglac sie z powrotem do zera —
                    // przy round(,1)/DECIMAL(5,2) rurociagi nigdy sie nie zuzywaly.
                    // round(,4) + DECIMAL(8,4) column: 5-min tick degradation
                    // (0.0017-0.0042/tick) must not round back to zero — with
                    // round(,1)/DECIMAL(5,2) pipelines never wore out.
                    round($newCondition, 4),
                    round($newTransportLoss, 2),
                    $newStatus,
                    $newStatus,   // for damaged_at CASE
                    $newStatus,   // for leak_started_at CASE
                    $pipelineId,
                    $playerId,
                ]);

                if ($newStatus === 'damaged' && !in_array($pipelineId, $this->unavailablePipelineIds, true)) {
                    $this->unavailablePipelineIds[] = $pipelineId;
                }

                $this->wellPipelineService->recordTickStat(
                    $playerId,
                    $wellId,
                    $pipelineId,
                    $pipelineDeltaHours,
                    $conditionBefore,
                    $newCondition,
                    $transportLossBefore,
                    $newTransportLoss,
                    $opexTickCost,
                    $newStatus
                );

                if ($newStatus !== $currentStatus) {
 // Leak start gets dedicated event and player notification
                    if ($newStatus === 'leak') {
                        $this->wellPipelineService->recordEvent(
                            $playerId,
                            $wellId,
                            $pipelineId,
                            'pipeline_leak',
                            'danger',
                            tPlain('pipeline.event_leak_started', [
                                'id'   => $pipelineId,
                                'loss' => number_format($newTransportLoss, 1, '.', ''),
                            ])
                        );
                        $tsvc?->notify(
                            'pipeline_leak',
                            null,
                            t('tick.notify.pipeline_leak', ['id' => $pipelineId])
                        );
                    } else {
                        $this->wellPipelineService->recordEvent(
                            $playerId,
                            $wellId,
                            $pipelineId,
                            'status_change',
                            $newStatus === 'damaged' ? 'danger' : 'warning',
                            tPlain('pipeline.event_status_change', [
                                'id'        => $pipelineId,
                                'status'    => $newStatus,
                                'condition' => number_format($newCondition, 1, '.', ''),
                            ])
                        );
                    }
                }

                if ($newCondition < 40.0) {
                    $explosionChance = 0.0006
                         * $pipelineDeltaHours
 * (float) ($hseBonus['catastrophe_mult'] ?? 1.0)
 * (float)($pipeline['incident_risk_mult'] ?? 1.0);
                    if (mt_rand(1, 1_000_000) <= (int) round($explosionChance * 1_000_000)) {
                        $oilInTransit = min(
                            $remainingStorageForDisasters,
                            $remainingStorageForDisasters * 0.05
                        );
                        $disaster = $this->wellService->triggerPipelineExplosion(
                            $pipelineId,
                            $playerId,
                            $oilInTransit,
                            $hseBonus
                        );

                        if (!empty($disaster['disaster'])) {
                            $this->disastersTriggered++;
                            $this->oilLostBbl += $oilInTransit;
                            if ($hubId > 0) {
                                $this->oilLostByHubBbl[$hubId] = ($this->oilLostByHubBbl[$hubId] ?? 0.0) + $oilInTransit;
                            }
                            if (!in_array($pipelineId, $this->unavailablePipelineIds, true)) {
                                $this->unavailablePipelineIds[] = $pipelineId;
                            }
                            $remainingStorageForDisasters = max(
                                0.0,
                                $remainingStorageForDisasters - $oilInTransit
                            );
                            $cost = (float) (($disaster['cost'] ?? 0) + ($disaster['env_fine'] ?? 0));
                            $this->cashDelta -= $cost;

                            GameLog::error('tick', 'PIPELINE EXPLOSION', null, [
                                'pipeline_id' => $pipelineId,
                                'player_id' => $playerId,
                                'leg' => $leg,
                                'env_fine' => $disaster['env_fine'] ?? 0,
                            ]);

                            $tsvc?->notify(
                                'disaster_pipeline_explosion',
                                null,
                                t('tick.notify.pipeline_explosion', [
                                    'id' => $pipelineId,
                                    'desc' => $disaster['desc'] ?? '',
                                ])
                            );

                            $this->wellPipelineService->recordEvent(
                                $playerId,
                                $wellId,
                                $pipelineId,
                                'pipeline_explosion',
                                'danger',
                                tPlain('pipeline.event_explosion', [
                                    'id' => $pipelineId,
                                    'cost' => number_format($cost, 2, '.', ''),
                                ])
                            );
                        }

                        // Po eksplozji pomijamy tylko ten rurociag; pozostale rurociagi gracza
                        // musza byc nadal przetworzone (wczesniej bylo 'break' = utrata reszty petli).
                        // After an explosion skip only this pipeline; the player's remaining pipelines
                        // must still be processed (was 'break' = rest of the loop dropped).
                        continue;
                    }
                }

 // Pipeline incident roll (micro / minor / medium) - only when not already damaged
                if (!in_array($newStatus, ['damaged', 'disabled'], true)) {
                    $incidentDamagedPipeline = $this->rollPipelineIncident(
                        $playerId,
                        $wellId,
                        $pipelineId,
                        $newCondition,
                        (float)($pipeline['incident_risk_mult'] ?? 1.0),
                        $hasPipelineEngineer,
                        $pipelineDeltaHours,
                        $hseBonus,
                        $leg,
                        $protection
                    );
                    if ($incidentDamagedPipeline && !in_array($pipelineId, $this->unavailablePipelineIds, true)) {
                        $this->unavailablePipelineIds[] = $pipelineId;
                    }
                }
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'pipeline check FAILED', $e, ['player_id' => $playerId]);
        }
    }

 /**
 * Rolls for a random pipeline incident (micro/minor/medium) and applies effects.
 * Rolls for a random pipeline incident and applies condition drop + transport loss spike.
 * @param array<string,mixed> $hseBonus
 */
    private function rollPipelineIncident(
        int $playerId,
        int $wellId,
        int $pipelineId,
        float $conditionPct,
        float $incidentRiskMult,
        bool $hasPipelineEngineer,
        float $deltaHours,
        array $hseBonus,
        string $leg = 'inbound',
        ?ProtectionService $protection = null
    ): bool {
 // Chance multiplier: higher risk when condition is lower; engineer halves the chance
        $condFactor = max(0.2, (100.0 - $conditionPct) / 100.0);
        $engMult    = $hasPipelineEngineer ? 0.5 : 1.0;
        $hseMult    = (float)($hseBonus['failure_reduction'] ?? 1.0);
        $protectionData = $this->pipelineProtectionData($protection, $playerId, $pipelineId);
        $protMult       = $protectionData['mult'];

 // Incident table: level => short name for event log
        $levels = ['pipe_micro' => 'micro', 'pipe_minor' => 'minor', 'pipe_medium' => 'medium'];

        foreach ($levels as $cfgKey => $levelShort) {
            $cfg   = $this->wellPipelineService->getPipelineIncidentConfig($cfgKey);
            $chance = (float)$cfg['base_chance']
 * $deltaHours
 * $incidentRiskMult
 * $condFactor
 * $engMult
 * $hseMult
 * $protMult;

            if (mt_rand(1, 1_000_000) > (int) round($chance * 1_000_000)) {
                continue; // no incident at this level this tick
            }

 // Apply effects
            $lossAdd  = $cfg['loss_add_min'] >= $cfg['loss_add_max']
                ? $cfg['loss_add_min']
                : $cfg['loss_add_min'] + mt_rand(0, 1000) / 1000.0 * ($cfg['loss_add_max'] - $cfg['loss_add_min']);
            $condDrop = $cfg['cond_drop_min'] >= $cfg['cond_drop_max']
                ? $cfg['cond_drop_min']
                : $cfg['cond_drop_min'] + mt_rand(0, 1000) / 1000.0 * ($cfg['cond_drop_max'] - $cfg['cond_drop_min']);

 // Gdy incydent zbija kondycje do 0, ustawiamy status 'damaged' i damaged_at w tym samym
 // UPDATE — inaczej rurociag transportowalby przy 0% jeszcze caly tick (status liczony byl
 // z kondycji sprzed incydentu). Inne przejscia statusu (degraded/critical/leak) zostawiamy
 // degradacji nastepnego ticka, by nie cofnac np. statusu 'leak'.
 // When the incident drops condition to 0, set status 'damaged' + damaged_at in the same UPDATE —
 // otherwise the pipeline would transport at 0% for a full tick (status was computed from the
 // pre-incident condition). Other transitions (degraded/critical/leak) are left to next tick's
 // degradation so we don't regress e.g. a 'leak' status.
            $this->db->prepare(
                "UPDATE well_pipelines
                    SET transport_loss = LEAST(10.0, transport_loss + ?),
                        condition_pct  = GREATEST(0.0, condition_pct - ?),
                        status     = CASE WHEN GREATEST(0.0, condition_pct - ?) <= 0.0 THEN 'damaged' ELSE status END,
                        damaged_at = CASE WHEN GREATEST(0.0, condition_pct - ?) <= 0.0 THEN COALESCE(damaged_at, NOW()) ELSE damaged_at END
                  WHERE id = ? AND player_id = ?"
            )->execute([
                round($lossAdd, 2),
                round($condDrop, 1),
                round($condDrop, 1),
                round($condDrop, 1),
                $pipelineId,
                $playerId,
            ]);

            $this->wellPipelineService->recordEvent(
                $playerId,
                $wellId,
                $pipelineId,
                'incident',
                match($levelShort) { 'medium' => 'danger', 'minor' => 'warning', default => 'info' },
                tPlain('pipeline.event_incident_' . $levelShort, [
                    'id'       => $pipelineId,
                    'loss_add' => number_format($lossAdd, 1, '.', ''),
                    'cond_drop'=> number_format($condDrop, 1, '.', ''),
                ]),
                $levelShort   // level column - 'micro', 'minor', 'medium'
            );

            GameLog::info('tick', 'pipeline_incident', [
                'pipeline_id' => $pipelineId,
                'well_id'     => $wellId,
                'player_id'   => $playerId,
                'leg'         => $leg,
                'level'       => $levelShort,
                'loss_add'    => round($lossAdd, 2),
                'cond_drop'   => round($condDrop, 1),
            ]);

            if ($protection !== null && $protMult < 1.0 && $protectionData['option_id'] > 0) {
                $protection->logEvent(
                    $playerId, $protectionData['option_id'], 'pipeline', $pipelineId, 'pipeline_guard',
                    'protection_applied_to_incident', round($lossAdd, 2), $levelShort
                );
            }

            return max(0.0, $conditionPct - $condDrop) <= 0.0;
        }

        return false;
    }

    /**
     * Dane ochrony rurociagu: mnoznik ryzyka i ID opcji do audytu.
     * Pipeline protection data: risk multiplier and option ID for audit.
     *
     * @return array{mult: float, option_id: int}
     */
    private function pipelineProtectionData(?ProtectionService $protection, int $playerId, int $pipelineId): array
    {
        if ($protection === null) {
            return ['mult' => 1.0, 'option_id' => 0];
        }
 // H9: getActiveProtection() bez try/catch ubijalby caly tick rurociagu gracza przez zewnetrzny catch.
 // H9: Without try/catch, getActiveProtection() exception would abort all pipeline processing via outer catch.
        $row = $this->pipelineProtectionCache[$pipelineId] ?? null;
        if ($row === null) {
            try {
                $row = $protection->getActiveProtection($playerId, 'pipeline', $pipelineId, 'pipeline_guard');
            } catch (Throwable $e) {
                GameLog::error('tick', 'pipelineProtectionData::getActiveProtection FAILED — brak ochrony / no protection applied', $e, [
                    'pipeline_id' => $pipelineId, 'player_id' => $playerId,
                ]);
                return ['mult' => 1.0, 'option_id' => 0];
            }
        }
        if ($row === null) {
            return ['mult' => 1.0, 'option_id' => 0];
        }
        $effects = $row['effects'] ?? [];
        $eff = $effects['pipeline_incident_risk_mult'] ?? null;
        $mult = 1.0;
        if ($eff !== null && $eff['type'] === 'mult') {
            $mult = max(0.0, min(1.0, (float)$eff['value']));
        }
        return ['mult' => $mult, 'option_id' => (int)($row['protection_option_id'] ?? 0)];
    }

    private function checkPipelineEngineer(int $playerId): bool
    {
        try {
            $peStmt = $this->db->prepare(
                "SELECT id FROM technical_staff
                  WHERE player_id = ? AND specialization = 'pipeline_engineer'
                    AND status IN ('active','busy')
                    AND (fired_at IS NULL OR fired_at > NOW())
                  LIMIT 1"
            );
            $peStmt->execute([$playerId]);
            return (bool) $peStmt->fetch();
        } catch (Throwable $e) {
            GameLog::error('tick', 'pipeline engineer check FAILED', $e, ['player_id' => $playerId]);
            return false;
        }
    }
}
