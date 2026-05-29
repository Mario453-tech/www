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

    private PDO $db;
    private DateTime $now;
    private WellService $wellService;
    private WellPipelineService $wellPipelineService;

    public function __construct(PDO $db, DateTime $now, WellService $wellService)
    {
        $this->db = $db;
        $this->now = $now;
        $this->wellService = $wellService;
        $this->wellPipelineService = new WellPipelineService($db);
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
        ?object $tsvc
    ): void {
        try {
 // Complete pipeline builds that have finished / Finalizuj rurociagi ktore skonczyly budowe
            $completed = $this->wellPipelineService->completeBuildingPipelines($playerId);
            foreach ($completed as $done) {
                GameLog::info('tick', 'Pipeline build complete', [
                    'pipeline_id' => (int)$done['id'],
                    'player_id'   => $playerId,
                    'type'        => $done['pipeline_type'] ?? 'unknown',
                ]);
                $tsvc?->notify(
                    'pipeline_build_complete',
                    null,
                    t('tick.notify.pipeline_build_complete', ['id' => (int)$done['id']])
                );
            }

 // ETAP 11: degrade and roll incidents for BOTH transport legs independently.
 // Each leg is its own well_pipelines row, so inbound and outbound roll separately.
 // inbound -> wells.transport_type = 'rurociag' (well -> hub)
 // outbound -> logistics_hubs.outbound_transport_type = 'rurociag' (hub -> storage)
 // keyed by well_id=0, hub_id (one pipeline per hub, ETAP 11)

 // Inbound pipelines: keyed by well_id > 0, joined to wells and hub assignment.
            $inboundStmt = $this->db->prepare(
                "SELECT wp.*
                   FROM well_pipelines wp
                   JOIN wells w ON w.id = wp.well_id
                   JOIN logistics_hub_assignments a ON a.well_id = wp.well_id AND a.status = 'active'
                   JOIN logistics_hubs h ON h.id = wp.hub_id
                  WHERE wp.player_id = ?
                    AND wp.leg = 'inbound'
                    AND wp.well_id > 0
                    AND a.hub_id = wp.hub_id
                    AND h.status NOT IN ('disabled', 'building')
                    AND w.transport_type = 'rurociag'
                    AND wp.status IN ('active', 'degraded', 'critical', 'leak')
                  ORDER BY wp.condition_pct ASC"
            );
            $inboundStmt->execute([$playerId]);
            $inboundPipelines = $inboundStmt->fetchAll(PDO::FETCH_ASSOC);

 // Outbound pipelines (ETAP 11): keyed by well_id=0, hub_id; joined to logistics_hubs.
            $outboundStmt = $this->db->prepare(
                "SELECT wp.*
                   FROM well_pipelines wp
                   JOIN logistics_hubs h ON h.id = wp.hub_id
                  WHERE wp.player_id = ?
                    AND wp.leg = 'outbound'
                    AND wp.well_id = 0
                    AND h.status NOT IN ('disabled', 'building')
                    AND h.outbound_transport_type = 'rurociag'
                    AND wp.status IN ('active', 'degraded', 'critical', 'leak')
                  ORDER BY wp.condition_pct ASC"
            );
            $outboundStmt->execute([$playerId]);
            $outboundPipelines = $outboundStmt->fetchAll(PDO::FETCH_ASSOC);

            $pipelines = array_merge($inboundPipelines, $outboundPipelines);

            $hasPipelineEngineer = $this->checkPipelineEngineer($playerId);

            foreach ($pipelines as $pipeline) {
                $pipelineId          = (int) $pipeline['id'];
                $wellId              = (int)($pipeline['well_id'] ?? 0);
                $leg                 = (string)($pipeline['leg'] ?? 'inbound');
                $conditionBefore     = (float) $pipeline['condition_pct'];
                $transportLossBefore = (float) ($pipeline['transport_loss'] ?? 0.0);
                $currentStatus       = (string)($pipeline['status'] ?? 'active');
                $opexTickCost        = round((float)($pipeline['opex_per_tick'] ?? 0.0), 2);

                $degradeRate = (float) ($pipeline['degradation_rate_per_hour'] ?? 0.05)
 * (float) ($hseBonus['degrade_mult'] ?? 1.0);

                if (!$hasPipelineEngineer) {
                    $degradeRate *= 2.0;
                }

 // Active leak accelerates degradation by 20%
                if ($currentStatus === 'leak') {
                    $degradeRate *= 1.20;
                }

                $newCondition     = max(0.0, $conditionBefore - ($degradeRate * $deltaHours));
                $newTransportLoss = $transportLossBefore;

                if (!$hasPipelineEngineer && $newCondition < 80.0) {
                    $newTransportLoss = min(10.0, $transportLossBefore + (0.1 * $deltaHours));
                }

 // Leaking pipeline loses additional oil through the crack each tick
                if ($currentStatus === 'leak') {
                    $newTransportLoss = min(10.0, $newTransportLoss + (0.4 * $deltaHours));
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
 * $deltaHours
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
                      WHERE id = ?"
                )->execute([
                    round($newCondition, 1),
                    round($newTransportLoss, 2),
                    $newStatus,
                    $newStatus,   // for damaged_at CASE
                    $newStatus,   // for leak_started_at CASE
                    $pipelineId,
                ]);

                $this->wellPipelineService->recordTickStat(
                    $playerId,
                    $wellId,
                    $pipelineId,
                    $deltaHours,
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
 * $deltaHours
 * (float) ($hseBonus['catastrophe_mult'] ?? 1.0)
 * (float)($pipeline['incident_risk_mult'] ?? 1.0);
                    if (mt_rand(1, 1000000) <= (int) ($explosionChance * 1000000)) {
                        $oilInTransit = $currentStorage * 0.05;
                        $disaster = $this->wellService->triggerPipelineExplosion(
                            $pipelineId,
                            $playerId,
                            $oilInTransit,
                            $hseBonus
                        );

                        if (!empty($disaster['disaster'])) {
                            $this->disastersTriggered++;
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

                        break;
                    }
                }

 // Pipeline incident roll (micro / minor / medium) - only when not already damaged
                if (!in_array($newStatus, ['damaged', 'disabled'], true)) {
                    $this->rollPipelineIncident(
                        $playerId,
                        $wellId,
                        $pipelineId,
                        $newCondition,
                        (float)($pipeline['incident_risk_mult'] ?? 1.0),
                        $hasPipelineEngineer,
                        $deltaHours,
                        $hseBonus,
                        $leg
                    );
                }
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'pipeline check FAILED', $e, ['player_id' => $playerId]);
        }
    }

 /**
 * Rolls for a random pipeline incident (micro/minor/medium) and applies effects.
 * Rolls for a random pipeline incident and applies condition drop + transport loss spike.
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
        string $leg = 'inbound'
    ): void {
 // Chance multiplier: higher risk when condition is lower; engineer halves the chance
        $condFactor = max(0.2, (100.0 - $conditionPct) / 100.0);
        $engMult    = $hasPipelineEngineer ? 0.5 : 1.0;
        $hseMult    = (float)($hseBonus['failure_reduction'] ?? 1.0);

 // Incident table: level => short name for event log
        $levels = ['pipe_micro' => 'micro', 'pipe_minor' => 'minor', 'pipe_medium' => 'medium'];

        foreach ($levels as $cfgKey => $levelShort) {
            $cfg   = $this->wellPipelineService->getPipelineIncidentConfig($cfgKey);
            $chance = (float)$cfg['base_chance']
 * $deltaHours
 * $incidentRiskMult
 * $condFactor
 * $engMult
 * $hseMult;

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

            $this->db->prepare(
                "UPDATE well_pipelines
                    SET transport_loss = LEAST(10.0, transport_loss + ?),
                        condition_pct  = GREATEST(0.0, condition_pct - ?)
                  WHERE id = ?"
            )->execute([round($lossAdd, 2), round($condDrop, 1), $pipelineId]);

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
                $levelShort   // level column — 'micro', 'minor', 'medium'
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

            break; // Only one incident level per tick per pipeline (per leg)
        }
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
