<?php

/**
 * OutboundLegService - economics of the SECOND transport leg (hub -> storage).
 * OutboundLegService - ekonomia drugiego odcinka transportu (hub -> magazyn).
 *
 * Pure computation: no DB writes, no global state. Callers apply the returned
 * deltas (loss/cost) to their own storage/cash/finance accumulators. This lets
 * every inbound path (synchronous pipeline, road trips, marine deliveries) run
 * the same leg-2 logic consistently.
 *
 * Per-well choice lives in wells.hub_outbound_transport_type:
 * 'nieustawiony' -> direct delivery (no extra loss/cost)
 * 'rurociag' -> outbound pipeline (transport_loss % + OPEX)
 * 'ciezarowki' -> road haul (per-tick cost + independent incident loss)
 *
 * Road incidents are rolled here independently from the inbound leg, so the two
 * legs never share or duplicate a single incident.
 */
class OutboundLegService
{
    private const ROAD_BASE_INCIDENT_CHANCE = 0.015; // per "shipment", before scaling

 /** @var array<string, array<string, float>> */
    private array $transportConfig;

 /** @param array<string, array<string, float>> $transportConfig */
    public function __construct(array $transportConfig)
    {
        $this->transportConfig = $transportConfig;
    }

 /**
 * Computes the second-leg effect for $bbl barrels leaving the hub.
 *
 * @param array<string, mixed>|null $outboundPipeline operational outbound pipeline row, or null
 * @param array<string, float> $mults ['loss_mult','global_loss','opex','transport_cost_mult']
 * @param array<string, mixed> $hseBonus
 * @return array{loss_bbl: float, loss_value: float, cost: float, kind: string}
 */
    public function compute(
        string $outboundType,
        ?array $outboundPipeline,
        float  $bbl,
        float  $oilPrice,
        array  $mults,
        float  $deltaHours = 1.0,
        array  $hseBonus = [],
        int    $politicalRisk = 1
    ): array {
        $none = ['loss_bbl' => 0.0, 'loss_value' => 0.0, 'cost' => 0.0, 'kind' => 'direct', 'capped_bbl' => $bbl, 'excess_bbl' => 0.0];
        if ($bbl <= 0.001) {
            return $none;
        }

        if ($outboundType === 'rurociag') {
            if ($outboundPipeline === null || empty($outboundPipeline['_is_operational'])) {
                return $none; // configured but no operational pipeline yet -> direct
            }
            return $this->computePipeline($outboundPipeline, $bbl, $oilPrice, $mults, $deltaHours);
        }

        if ($outboundType === 'ciezarowki') {
            return $this->computeRoad($bbl, $oilPrice, $mults, $deltaHours, $hseBonus, $politicalRisk);
        }

        return $none; // 'nieustawiony' / unknown -> direct delivery
    }

 /**
 * @param array<string, mixed> $pipe
 * @param array<string, float> $mults
 * @return array{loss_bbl: float, loss_value: float, cost: float, kind: string}
 */
    private function computePipeline(array $pipe, float $bbl, float $oilPrice, array $mults, float $deltaHours): array
    {
        // Przepustowosc jest w bbl/h, a $bbl to wolumen z calego ticka — limit skalujemy przez
        // deltaHours. Nadmiar ponad limit NIE jest dostarczany za darmo: caller cofa go do bufora
        // hubu, wiec strata % i OPEX/bbl licza sie tylko od realnie przetransportowanej ilosci.
        // Capacity is bbl/h and $bbl is the whole tick's volume — scale the cap by deltaHours.
        // Over-capacity excess is NOT delivered free: the caller returns it to the hub buffer, so
        // transport_loss % and per-bbl OPEX apply only to the volume actually transported.
        $requested = $bbl;
        $maxBbl    = (float)($pipe['real_capacity_bph'] ?? PHP_FLOAT_MAX) * max(0.0, $deltaHours);
        $excess    = 0.0;
 // Cap bez warunku maxBbl > 0: real_capacity_bph = 0 (default schematu dla legacy
 // wierszy) znaczy zero przepustowosci, nie nieskonczona.
 // Cap without the maxBbl > 0 guard: real_capacity_bph = 0 (schema default on legacy
 // rows) means zero throughput, not unlimited.
        if ($requested > $maxBbl) {
            $excess = round($requested - $maxBbl, 4);
            $bbl    = $maxBbl;
        }

        $lossPct = (float)($pipe['transport_loss'] ?? 0.0);
        $lossBbl = 0.0;
        if ($lossPct > 0.0) {
            $lossBbl = min($bbl, round(
                $bbl * ($lossPct / 100.0)
 * (float)($mults['global_loss'] ?? 1.0)
 * (float)($mults['loss_mult'] ?? 1.0),
                4
            ));
        }

        $costMult = (float)($mults['opex'] ?? 1.0) * (float)($mults['transport_cost_mult'] ?? 1.0);
        // opex_per_tick skalowany deltaHours (PLN na godzine, podloga 1 ticka) — spojnie
        // z oplatami hubu i rurociagu leg-1; czesc per-bbl zalezy od wolumenu, nie czasu.
        // opex_per_tick scaled by deltaHours (PLN per hour, floored at one tick) — consistent
        // with hub and leg-1 pipeline fees; the per-bbl part depends on volume, not time.
        $cost     = round(
            (float)($pipe['opex_per_tick'] ?? 0.0) * $costMult * max(1.0, $deltaHours)
            + $bbl * (float)($pipe['opex_per_bbl'] ?? 0.0) * $costMult,
            2
        );

        return [
            'loss_bbl'   => $lossBbl,
            'loss_value' => round($lossBbl * $oilPrice, 2),
            'cost'       => $cost,
            'kind'       => 'pipeline',
            'capped_bbl' => $bbl,
            'excess_bbl' => $excess,
        ];
    }

 /**
 * Self-contained per-tick road model for the second leg (no DB writes).
 * Cost from transport_config 'ciezarowki'; loss from an independent incident roll.
 *
 * @param array<string, float> $mults
 * @param array<string, mixed> $hseBonus
 * @return array{loss_bbl: float, loss_value: float, cost: float, kind: string}
 */
    private function computeRoad(
        float $bbl,
        float $oilPrice,
        array $mults,
        float $deltaHours,
        array $hseBonus,
        int   $politicalRisk
    ): array {
        $cfg          = $this->transportConfig['ciezarowki'] ?? [];
        $costPerBbl   = (float)($cfg['cost_per_bbl'] ?? 2.5);
        $incidentMult = (float)($cfg['incident'] ?? 1.3);

 // Globalny mnoznik balansu opex jak w computePipeline i wszystkich kosztach leg-1 —
 // leg-2 drogowy byl jedynym kosztem transportu pomijajacym go.
 // Global opex balance multiplier as in computePipeline and every leg-1 cost —
 // road leg-2 was the only transport cost skipping it.
        $cost = round($bbl * $costPerBbl * (float)($mults['opex'] ?? 1.0) * (float)($mults['transport_cost_mult'] ?? 1.0), 2);

        $politicalScale = match (true) {
            $politicalRisk >= 4 => 2.0,
            $politicalRisk >= 3 => 1.5,
            $politicalRisk >= 2 => 1.2,
            default             => 1.0,
        };
        $hseScale = (float)($hseBonus['failure_reduction'] ?? 1.0);
        $chance   = min(0.60, self::ROAD_BASE_INCIDENT_CHANCE * $incidentMult * $politicalScale * $hseScale * $deltaHours);

        $lossBbl = 0.0;
        if (mt_rand(1, 1_000_000) <= (int)round($chance * 1_000_000)) {
 // Incident hits part of the haul: 30%-80% of half the load at risk.
            $frac    = 0.30 + (mt_rand(0, 500) / 1000.0);
            $lossBbl = $bbl * 0.5 * $frac;
        }
        $lossBbl = min($bbl, round(
            $lossBbl * (float)($mults['loss_mult'] ?? 1.0) * (float)($mults['global_loss'] ?? 1.0),
            4
        ));

        return [
            'loss_bbl'   => $lossBbl,
            'loss_value' => round($lossBbl * $oilPrice, 2),
            'cost'       => $cost,
            'kind'       => 'road',
            'capped_bbl' => $bbl,
            'excess_bbl' => 0.0,
        ];
    }
}
