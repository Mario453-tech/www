<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';

/**
 * M8 (runda 5): licznik ticks_since_incident (odpornosc + presja incydentow) rosnie
 * proporcjonalnie do realnego czasu (1 tick = 5 min = deltaHours/12), a nie o stale +1
 * na uruchomienie crona. Wczesniej catch-up tick po przerwie crona dawal odwiertowi
 * "darmowa" odpornosc na najwyzej-deltaHours ticku i gubil narost presji.
 *
 * M8 (round 5): the ticks_since_incident counter (incident immunity + pressure) grows
 * proportionally to real elapsed time (1 tick = 5 min = deltaHours/12), not a flat +1 per
 * cron run. Previously a catch-up tick after a cron outage granted the well "free" immunity
 * on the highest-deltaHours tick and lost pressure buildup.
 *
 * transport_incident_mult = 0.0 zeruje szanse incydentu (chance = base * ... * transportMult),
 * wiec zaden incydent nie odpala i nie resetuje licznika — test jest deterministyczny.
 * transport_incident_mult = 0.0 zeroes the incident chance, so nothing fires or resets the
 * counter — the test is deterministic.
 */
final class MySqlIncidentImmunityDeltaHoursTest extends MySqlIntegrationTestCase
{
    /** @return array<string,mixed> */
    private function wellData(int $ticksSince): array
    {
        return [
            'ticks_since_incident'    => $ticksSince,
            'technical_condition'     => 100.0,
            'risk_score'              => 0.0,
            'post_incident_risk_boost'=> 0.0,
            'reservoir_remaining'     => 500000.0,
            'reservoir_max'           => 500000.0,
            'base_production_per_hour'=> 50.0,
            'equipment_tier'          => 'standard',
        ];
    }

    private function seedWellWithTicks(int $wellId, int $playerId, int $ticks): void
    {
        $this->seedWell($playerId, $wellId, 'active', 77, 'A1', 'rurociag', 100.0, 50.0);
        $this->db->prepare("UPDATE wells SET ticks_since_incident = ? WHERE id = ?")
            ->execute([$ticks, $wellId]);
    }

    private function ticksInDb(int $wellId): int
    {
        return (int)$this->db->query("SELECT ticks_since_incident FROM wells WHERE id = {$wellId}")->fetchColumn();
    }

    public function testCatchupTickScalesImmunityCounterByElapsedTime(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $wellId   = $ids['wellId'];
        $this->seedWellWithTicks($wellId, $playerId, 10);

        $svc    = new IncidentService();
        // deltaHours = 4h => round(4 * 12) = 48 pieciominutowych tickow.
        $result = $svc->processTick($wellId, $playerId, 4.0, $this->wellData(10), ['transport_incident_mult' => 0.0], []);

        $this->assertNull($result['incident'], 'transport_incident_mult=0 => brak incydentu (deterministycznie)');
        $this->assertSame(58, $this->ticksInDb($wellId),
            'M8: catch-up 4h musi dodac 48 (10 + 48 = 58), nie +1');
    }

    public function testNormalTickStillIncrementsByOne(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $wellId   = $ids['wellId'];
        $this->seedWellWithTicks($wellId, $playerId, 0);

        $svc = new IncidentService();
        // Normalny tick co 5 min => deltaHours = 5/60 = 0.0833; round(0.0833*12) = round(1.0) = 1.
        $result = $svc->processTick($wellId, $playerId, 5.0 / 60.0, $this->wellData(0), ['transport_incident_mult' => 0.0], []);

        $this->assertNull($result['incident']);
        $this->assertSame(1, $this->ticksInDb($wellId),
            'M8: normalny 5-min tick nadal daje +1 (zachowanie niezmienione)');
    }
}
