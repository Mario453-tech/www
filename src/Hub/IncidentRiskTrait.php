<?php

/**
 * HubIncidentRiskTrait incident type definitions and risk multiplier calculation.
 * Used by HubIncidentService.
 */
trait HubIncidentRiskTrait
{
 /** @var array<string, float> Szansa per godzine ticka mnoona przez deltaHours i riskMult. / Chance per tick hour multiplied by deltaHours and riskMult. */
    private const BASE_CHANCE_PER_HOUR = [
        'transfer_failure'  => 0.008,
        'equipment_damage'  => 0.004,
        'local_leak'        => 0.006,
        'loading_error'     => 0.012,
        'storage_jam'       => 0.010,
        'critical_overload' => 0.025,
    ];

 /** @var array<string, array<string, mixed>> Definicje typow incydentow z efektami. / Incident type definitions with effects. */
    private const INCIDENTS = [
        'transfer_failure'  => ['severity' => 'medium',   'condition_dmg' => [1, 3],   'extra_loss_pct' => [5,  20]],
        'equipment_damage'  => ['severity' => 'high',     'condition_dmg' => [3, 8],   'extra_loss_pct' => [10, 25]],
        'local_leak'        => ['severity' => 'medium',   'condition_dmg' => [2, 5],   'extra_loss_pct' => [10, 35]],
        'loading_error'     => ['severity' => 'low',      'condition_dmg' => [0, 1],   'extra_loss_pct' => [3,  12]],
        'storage_jam'       => ['severity' => 'low',      'condition_dmg' => [0, 2],   'extra_loss_pct' => [5,  15]],
        'critical_overload' => ['severity' => 'critical', 'condition_dmg' => [5, 15],  'extra_loss_pct' => [20, 60]],
    ];

 /** @var array<string, int> Liczba wariantow komunikatu per typ (lang/pl.php klucze). / Number of message variants per type (lang/pl.php keys). */ 
    private const MSG_COUNT = [
        'transfer_failure'  => 4,
        'equipment_damage'  => 4,
        'local_leak'        => 4,
        'loading_error'     => 4,
        'storage_jam'       => 4,
        'critical_overload' => 4,
    ];

 /**
 * Oblicza mnoznik ryzyka na podstawie kondycji, obciazenia i trybu pracy.
 * Calculates risk multiplier based on condition, load, and work mode.
 *
 * @param array<string, mixed> $hub
 * @param array<string, mixed> $tickResult
 */
    private function calcRiskMultiplier(array $hub, array $tickResult, array $hseBonus = []): float
    {
        $mult = 1.0;
        $cond = (float)($hub['condition_pct'] ?? 100.0);
        $load = (float)($tickResult['load_pct'] ?? 0.0);
        $mode = (string)($hub['work_mode'] ?? 'standard');
        $acq  = $this->hubSvc->getAcquisitionDefaults((string)($hub['acquisition_type'] ?? 'new'));

        $mult *= match(true) {
            $cond <= 20.0 => 6.0,
            $cond < 30.0  => 3.0,
            $cond < 50.0  => 1.8,
            $cond < 70.0  => 1.3,
            default       => 1.0,
        };

        $mult *= match(true) {
            $load > 120.0 => 3.0,
            $load > 100.0 => 2.0,
            $load > 80.0  => 1.4,
            default       => 1.0,
        };

        $mult *= match($mode) {
            'max' => 1.5,
            'eco' => 0.6,
            default => 1.0,
        };

        $mult *= (float)($acq['risk_mult'] ?? 1.0);

        if (($hseBonus['active_hse'] ?? 0) > 0) {
            $mult *= max(0.20, (float)($hseBonus['failure_reduction'] ?? 1.0));
            if ($load > 100.0 || $cond < 30.0) {
                $mult *= max(0.15, (float)($hseBonus['catastrophe_mult'] ?? 1.0));
            }
            $procFactor = (float)($hseBonus['proc_factor'] ?? 0.0);
            if ($procFactor > 0.0) {
                $mult *= max(0.70, 1.0 - (0.12 * $procFactor));
            }
        }

        return $mult;
    }
}
