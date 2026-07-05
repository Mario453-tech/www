<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';

/**
 * M7 (runda 5, opcja 1): incydenty auto_repair (micro/minor) NIE trzymaja spadku produkcji
 * przez okno `hours` — dzialaja tylko w ticku wystapienia (freshDrop w WellRiskHandler).
 * Tylko medium/major (auto_repair=0, platne, reczna naprawa) daja trwajacy spadek liczony
 * przez getOngoingProdDrop / getOngoingProdDropForPlayer.
 *
 * M7 (round 5, option 1): auto_repair incidents (micro/minor) do NOT sustain the production
 * drop across the `hours` window — they apply only in their firing tick (freshDrop in
 * WellRiskHandler). Only medium/major (auto_repair=0, paid, manual repair) yield an ongoing
 * drop returned by getOngoingProdDrop / getOngoingProdDropForPlayer.
 */
final class MySqlIncidentOngoingDropAutoRepairTest extends MySqlIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupIncidents();
    }

    protected function tearDown(): void
    {
        $this->cleanupIncidents();
        parent::tearDown();
    }

    private function cleanupIncidents(): void
    {
        try {
            $this->db->prepare("DELETE FROM well_incidents WHERE player_id = ?")
                ->execute([$this->getTrackedIds()['playerId']]);
        } catch (\Throwable $e) {}
    }

    private function insertIncident(int $wellId, int $playerId, string $level, int $prodDrop, int $autoRepair): void
    {
        $this->db->prepare(
            "INSERT INTO well_incidents
                (well_id, player_id, level, cause_type, prod_drop, hours, auto_repair, message, created_at)
             VALUES (?, ?, ?, 'system', ?, 6, ?, 'test', NOW())"
        )->execute([$wellId, $playerId, $level, $prodDrop, $autoRepair]);
    }

    public function testAutoRepairIncidentsDoNotSustainOngoingDrop(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $wellId   = $ids['wellId'];
        $this->seedWell($playerId, $wellId, 'active', 77, 'A1', 'rurociag', 100.0, 50.0);

        // Aktywne micro (10%) i minor (30%) — auto_repair=1, w oknie hours, niesprzatane.
        $this->insertIncident($wellId, $playerId, 'micro', 10, 1);
        $this->insertIncident($wellId, $playerId, 'minor', 30, 1);

        $svc = new IncidentService();

        $this->assertSame(0.0, $svc->getOngoingProdDrop($wellId, $playerId),
            'M7: micro/minor (auto_repair) nie moga dawac trwajacego spadku');
        $this->assertArrayNotHasKey($wellId, $svc->getOngoingProdDropForPlayer($playerId),
            'M7: odwiert tylko z auto_repair nie pojawia sie w mapie trwajacych spadkow');
    }

    public function testNonAutoRepairIncidentStillSustainsOngoingDrop(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $wellId   = $ids['wellId'];
        $this->seedWell($playerId, $wellId, 'active', 77, 'A1', 'rurociag', 100.0, 50.0);

        // micro (auto_repair=1, ignorowane) + medium (auto_repair=0, 50% — liczone).
        $this->insertIncident($wellId, $playerId, 'micro', 10, 1);
        $this->insertIncident($wellId, $playerId, 'medium', 50, 0);

        $svc = new IncidentService();

        $this->assertEqualsWithDelta(50.0, $svc->getOngoingProdDrop($wellId, $playerId), 0.001,
            'medium (auto_repair=0) trzyma spadek 50%');
        $map = $svc->getOngoingProdDropForPlayer($playerId);
        $this->assertArrayHasKey($wellId, $map);
        $this->assertEqualsWithDelta(50.0, $map[$wellId], 0.001,
            'mapa zwraca 50% (medium), a nie MAX z micro/medium bez filtra');
    }
}
