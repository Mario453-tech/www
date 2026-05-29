<?php

/**
 * ModifiersTrait - pobieranie aktywnych zdarzen i obliczanie modyfikatorow produkcji/podatku
 * Modifiers trait — fetching active events and calculating production/tax modifiers.
 */
trait RegionalModifiersTrait
{
    /**
     *  Pobierz aktywne zdarzenia dla gracza - uzywane w tick do modyfikacji produkcji/podatku
     * Fetch active events for a player — used in tick to modify production/tax.
     */
    /** @return list<array<string, mixed>> */
    public function getActiveEvents(int $playerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT re.*, wr.code AS region_code
                FROM regional_events re
                JOIN world_regions wr ON wr.id = re.region_id
                WHERE re.player_id = ? AND re.resolved = 0 AND re.expires_at > NOW()
                ORDER BY re.severity DESC
            ");
            $stmt->execute([$playerId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            GameLog::error('RegionalEventService', 'getActiveEvents FAILED', $e, ['player_id' => $playerId]);
            return [];
        }
    }

    /**
     * Oblicz efektywne modyfikatory z aktywnych zdarzen dla konkretnego odwiertu.
     * Calculate effective modifiers from active events for a specific well.
     * Zwraca: ['prod_mult' => float, 'tax_extra' => float]
     * Returns: ['prod_mult' => float, 'tax_extra' => float]
     */
    /**
     * @param list<array<string, mixed>> $activeEvents
     * @return array<string, mixed>
     */
    public function getWellModifiers(int $playerId, string $regionCode, array $activeEvents): array
    {
        $prodMult = 1.0;
        $taxExtra = 0.0;

        foreach ($activeEvents as $ev) {
            if ($ev['region_code'] !== $regionCode) continue;

            if ($ev['event_type'] === 'production_stop' || $ev['event_type'] === 'pipeline_block') {
                $prodMult *= (1.0 - $this->getStopPct($ev));
            }

            if ($ev['event_type'] === 'production_bonus') {
                $prodMult *= (1.0 + $this->getBonusPct($ev));
            }

            if ($ev['event_type'] === 'conflict_tax') {
                $taxExtra += $this->getTaxExtra($ev);
            }
        }

        return [
            'prod_mult' => max(0.0, $prodMult),
            'tax_extra' => $taxExtra,
        ];
    }

    private function getStopPct(array $event): float
    {
        return match((int)$event['severity']) {
            3 => 0.50, 2 => 0.35, default => 0.20
        };
    }

    private function getBonusPct(array $event): float
    {
        // Bonus produkcji: severity 1->+15%, 2->+25% / Production bonus: severity 1->+15%, 2->+25%
        return match((int)$event['severity']) {
            2 => 0.25, default => 0.15
        };
    }

    private function getTaxExtra(array $event): float
    {
        // Severity  tax_extra: 1=2%, 2=5%, 3=8%
        return match((int)$event['severity']) {
            3 => 0.08, 2 => 0.05, default => 0.02
        };
    }
}
