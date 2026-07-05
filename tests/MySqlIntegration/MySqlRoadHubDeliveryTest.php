<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';

/**
 * M4 (runda 5, czesc A): ropa dostarczona ciezarowkami dla odwiertu PRZYPISANEGO DO HUBA nie
 * jest capowana magazynem w WellRoadTripSection — idzie pelna do bufora huba (przez
 * deliveredByWell -> hubInputAccum -> finalizeHubTicks), jak produkcja synchroniczna. Wczesniej
 * przy pelnym magazynie cala ich ropa byla kasowana jako "overflow" zanim dotarla do huba.
 * Odwierty BEZ huba nadal sa capowane (nie maja gdzie czekac).
 *
 * M4 (round 5, part A): oil delivered by truck for a HUB-ASSIGNED well is not storage-capped in
 * WellRoadTripSection — it goes fully to the hub buffer, like synchronous production. Previously,
 * on full storage, all of it was destroyed as "overflow" before reaching the hub. Hubless wells
 * are still capped (nowhere to wait).
 *
 * Bilans barylek: dostarczone_przez_kursy == deliveredBbl(do huba/magazynu) + lostBbl.
 * Barrel-balance: trips_delivered == deliveredBbl + lostBbl.
 */
final class MySqlRoadHubDeliveryTest extends MySqlIntegrationTestCase
{
    private function insertCompletedTrip(int $playerId, int $wellId, float $volume): void
    {
        // Kurs z ETA w przeszlosci, incident_risk_mult=0 => zero strat losowych (deterministycznie).
        $this->db->prepare(
            "INSERT INTO well_road_trips
                (player_id, well_id, volume_bbl, truck_type, trips_count, trip_hours, cost,
                 incident_risk_mult, political_risk_level, status, departure_at, eta_at)
             VALUES (?, ?, ?, 'standard', 1, 2, 500.00, 0.0, 1, 'in_transit', NOW() - INTERVAL 3 HOUR, NOW() - INTERVAL 1 SECOND)"
        )->execute([$playerId, $wellId, $volume]);
    }

    public function testHubWellOilNotStorageCappedRoadNonHubIs(): void
    {
        $ids       = $this->getTrackedIds();
        $playerId  = $this->seedPlayer();
        $hubWellId = $ids['wellId'];
        $nonHubId  = $ids['auxWellId'];
        $hubId     = $ids['hubId'];

        $this->seedWell($playerId, $hubWellId, 'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);
        $this->seedWell($playerId, $nonHubId,  'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);

        $this->insertCompletedTrip($playerId, $hubWellId, 50.0);
        $this->insertCompletedTrip($playerId, $nonHubId,  50.0);

        // Magazyn PELNY: bez capa hub-well straciloby cala rope (stary bug).
        $storageCap = 1000.0;
        $section    = new WellRoadTripSection($this->db, new \DateTime());
        $newStorage = $section->process(
            $playerId, $storageCap, $storageCap, [],
            new RoadTransportService($this->db), null, null,
            [$hubWellId => $hubId]   // tylko hubWellId przypisany do huba
        );

        // Odwiert Z HUBEM: pelne 50 bbl trafia do deliveredByWell (bufor huba), NIE stracone.
        $this->assertArrayHasKey($hubWellId, $section->deliveredByWell,
            'M4: ropa hub-well musi trafic do huba mimo pelnego magazynu');
        $this->assertEqualsWithDelta(50.0, $section->deliveredByWell[$hubWellId], 0.001,
            'M4: pelna ilosc hub-well (bez capa magazynu)');

        // Odwiert BEZ huba: capowany pelnym magazynem => 0 skredytowane, cale 50 stracone.
        $this->assertArrayNotHasKey($nonHubId, $section->deliveredByWell,
            'odwiert bez huba przy pelnym magazynie nie kredytuje magazynu');

        // deliveredBbl = tylko hub (50). lostBbl = tylko bez-huba (50).
        $this->assertEqualsWithDelta(50.0, $section->deliveredBbl, 0.001, 'deliveredBbl = hub 50');
        $this->assertEqualsWithDelta(50.0, $section->lostBbl, 0.001, 'lostBbl = non-hub 50');

        // BILANS BARYLEK (brak podwojnego liczenia): 100 dostarczone == 50 (hub) + 50 (strata).
        $this->assertEqualsWithDelta(100.0, $section->deliveredBbl + $section->lostBbl, 0.001,
            'Bilans: trips_delivered(100) == deliveredBbl + lostBbl');

        // Magazyn: +50 (optymistyczny kredyt hub-oil; finalize huba zbuforuje/pogodzi pozniej).
        $this->assertEqualsWithDelta($storageCap + 50.0, $newStorage, 0.001,
            'currentStorage += hub-oil (optymistycznie), non-hub odrzucone');

        // DB: kurs hub-well NIE przeskalowany (delivered_bbl pelne 50), non-hub przeskalowany do 0.
        $hubTrip = (float)$this->db->query(
            "SELECT delivered_bbl FROM well_road_trips WHERE player_id = {$playerId} AND well_id = {$hubWellId}"
        )->fetchColumn();
        $nonHubTrip = (float)$this->db->query(
            "SELECT delivered_bbl FROM well_road_trips WHERE player_id = {$playerId} AND well_id = {$nonHubId}"
        )->fetchColumn();
        $this->assertEqualsWithDelta(50.0, $hubTrip, 0.001, 'kurs hub-well: delivered_bbl pelne (nie capowane w DB)');
        $this->assertLessThan(0.001, $nonHubTrip, 'kurs non-hub: delivered_bbl przeskalowane do 0 (pelny magazyn)');
    }

    public function testHubWellDeliveredToBufferPathAlsoWhenStorageHasRoom(): void
    {
        // Kontrola: przy wolnym magazynie hub-well nadal routuje pelna ilosc do huba, non-hub do magazynu.
        $ids       = $this->getTrackedIds();
        $playerId  = $this->seedPlayer();
        $hubWellId = $ids['wellId'];
        $nonHubId  = $ids['auxWellId'];
        $hubId     = $ids['hubId'];

        $this->seedWell($playerId, $hubWellId, 'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);
        $this->seedWell($playerId, $nonHubId,  'active', 77, 'A1', 'ciezarowki', 100.0, 50.0);
        $this->insertCompletedTrip($playerId, $hubWellId, 50.0);
        $this->insertCompletedTrip($playerId, $nonHubId,  50.0);

        $section    = new WellRoadTripSection($this->db, new \DateTime());
        $newStorage = $section->process(
            $playerId, 0.0, 100000.0, [],
            new RoadTransportService($this->db), null, null,
            [$hubWellId => $hubId]
        );

        // Oba dostarczone (pusty magazyn): hub 50 + non-hub 50.
        $this->assertEqualsWithDelta(50.0, $section->deliveredByWell[$hubWellId] ?? 0.0, 0.001);
        $this->assertEqualsWithDelta(50.0, $section->deliveredByWell[$nonHubId] ?? 0.0, 0.001);
        $this->assertEqualsWithDelta(100.0, $section->deliveredBbl, 0.001);
        $this->assertLessThan(0.001, $section->lostBbl, 'wolny magazyn: brak strat');
        $this->assertEqualsWithDelta(100.0, $newStorage, 0.001, 'oba skredytowane do magazynu (optymistycznie)');
    }
}
