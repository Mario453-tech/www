<?php
declare(strict_types=1);

require_once __DIR__ . '/MySqlIntegrationTestCase.php';

/**
 * M3 (runda 5): ropa wydrenowana z bufora hubu, ktora nie miesci sie w PELNYM magazynie,
 * WRACA do bufora (czeka na kolejny tick) zamiast byc niszczona jako strata. Tylko realny
 * nadmiar ponad pojemnosc bufora = strata.
 *
 * M3 (round 5): oil drained from the hub buffer that does not fit a FULL storage RETURNS to
 * the buffer (waits for the next tick) instead of being destroyed as a loss. Only a genuine
 * excess over buffer capacity is a loss.
 *
 * Test bilansu barylek (weryfikacja braku podwojnego liczenia):
 *   wejscie(0) + bufor_poczatkowy == bufor_koncowy + dostarczone_do_magazynu + strata
 * Barrel-balance (double-counting check):
 *   input(0) + initial_buffer == final_buffer + delivered_to_storage + loss
 */
final class MySqlHubStorageBlockedRebufferTest extends MySqlIntegrationTestCase
{
    /**
     * Buduje minimalny ctx WellLoopSection z polami, ktorych dotyka WellHubSection::finalize.
     * @param array<string,mixed> $hubRow
     */
    private function makeCtx(int $hubId, int $wellId, array $hubRow, float $storageCap, float $storageUsed): WellLoopSection
    {
        return new class($hubId, $wellId, $hubRow, $storageCap, $storageUsed) extends WellLoopSection {
            public function __construct(int $hubId, int $wellId, array $hubRow, float $cap, float $used)
            {
                $this->hubCache                 = [$hubId => $hubRow];
                $this->hubInputAccum            = [$hubId => 0.0];
                $this->wellHubMap               = [$wellId => $hubId];
                $this->hubOutboundType          = [$hubId => 'nieustawiony'];
                $this->hubOutboundPipelineCache = [$hubId => null];
                $this->storageCapacity          = $cap;
                $this->currentStorage           = $used;
                $this->playerCash               = 1_000_000.0;
                $this->totalCosts               = 0.0;
                $this->finOpex                  = 0.0;
                $this->finHubUsageCost          = 0.0;
                $this->finBbl                   = 0.0;
                $this->deliveredBbl             = 0.0;
                $this->finRevenue               = 0.0;
                $this->storageBlockedBbl        = 0.0;
                $this->finLossBbl               = 0.0;
                $this->finLossValue             = 0.0;
                $this->finHubLossBbl            = 0.0;
                $this->finHubLossValue          = 0.0;
                $this->finHubIncidentLossBbl    = 0.0;
                $this->finHubIncidentLossValue  = 0.0;
                $this->incidentsTriggered       = 0;
            }
        };
    }

    public function testDrainedOilReturnsToBufferWhenStorageFull(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $hubId    = $ids['hubId'];
        $wellId   = $ids['wellId'];

        // Hub z buforem 200 bbl (cap 500), sprawny, aktywny. seedHub: nominal/real_capacity_bph=200.
        $this->seedHub($hubId, 'M3 rebuffer hub', 77, 'A1', 90.0, 'active', 'new', 'standard', 200.0, $playerId);

        $hubRow = $this->db->query("SELECT * FROM logistics_hubs WHERE id = {$hubId}")->fetch();
        $hubRow['region_political_risk'] = 1;
        $initialBuffer = (float)$hubRow['buffer_current_bbl']; // 200

        // Magazyn PELNY: brak miejsca na wydrenowana rope.
        $storageCap = 1000.0;
        $ctx        = $this->makeCtx($hubId, $wellId, $hubRow, $storageCap, $storageCap);

        // hubIncidentSvc=null (brak losowych incydentow), hubSvc=null (brak OPEX) => deterministycznie.
        $section = new WellHubSection(
            $ctx, new \DateTime(), new HubTickService($this->db, new HubService($this->db)),
            null, null, [], ['opex' => 1.0, 'loss' => 1.0], 70.0,
            new OutboundLegService([]), null
        );

        $section->finalize($playerId, 1.0, []);

        $finalBuffer = (float)$this->db->query("SELECT buffer_current_bbl FROM logistics_hubs WHERE id = {$hubId}")->fetchColumn();

        // M3: ropa wrocila do bufora — bufor odtworzony (~200), NIE zero.
        $this->assertGreaterThan(150.0, $finalBuffer,
            'M3: wydrenowana ropa musi wrocic do bufora przy pelnym magazynie, nie zostac skasowana');

        // Brak straty: cala zablokowana ropa zmiescila sie z powrotem w buforze.
        $this->assertLessThan(1.0, $ctx->finHubLossBbl, 'M3: brak straty hubowej — ropa nie ginie');
        $this->assertLessThan(1.0, $ctx->storageBlockedBbl, 'M3: nic nie zablokowane na trwale');

        // Nic nie dostarczono do magazynu (byl pelny).
        $this->assertLessThan(1.0, $ctx->finBbl, 'pelny magazyn: zero dostarczone');

        // BILANS BARYLEK (brak podwojnego liczenia):
        // wejscie(0) + bufor_pocz == bufor_konc + dostarczone(finBbl) + strata(finHubLossBbl)
        $balanceLhs = 0.0 + $initialBuffer;
        $balanceRhs = $finalBuffer + $ctx->finBbl + $ctx->finHubLossBbl;
        $this->assertEqualsWithDelta($balanceLhs, $balanceRhs, 1.0,
            "Bilans barylek: wejscie+bufor_pocz ({$balanceLhs}) == bufor_konc+dostarczone+strata ({$balanceRhs})");
    }

    public function testDeliversToStorageWhenSpaceAvailable(): void
    {
        // Kontrola: gdy magazyn ma miejsce, wydrenowana ropa jednak trafia do magazynu (nie utyka w buforze).
        // Control: with free storage, drained oil is delivered to storage (not stuck in the buffer).
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $hubId    = $ids['hubId'];
        $wellId   = $ids['wellId'];

        $this->seedHub($hubId, 'M3 deliver hub', 77, 'A1', 90.0, 'active', 'new', 'standard', 200.0, $playerId);
        $hubRow = $this->db->query("SELECT * FROM logistics_hubs WHERE id = {$hubId}")->fetch();
        $hubRow['region_political_risk'] = 1;
        $initialBuffer = (float)$hubRow['buffer_current_bbl'];

        // Magazyn PUSTY (duzo miejsca).
        $ctx     = $this->makeCtx($hubId, $wellId, $hubRow, 100000.0, 0.0);
        $section = new WellHubSection(
            $ctx, new \DateTime(), new HubTickService($this->db, new HubService($this->db)),
            null, null, [], ['opex' => 1.0, 'loss' => 1.0], 70.0,
            new OutboundLegService([]), null
        );

        $section->finalize($playerId, 1.0, []);

        $finalBuffer = (float)$this->db->query("SELECT buffer_current_bbl FROM logistics_hubs WHERE id = {$hubId}")->fetchColumn();

        // Ropa dostarczona do magazynu, bufor opustoszaly.
        $this->assertGreaterThan(150.0, $ctx->finBbl, 'wolny magazyn: wydrenowana ropa dostarczona');
        $this->assertLessThan(50.0, $finalBuffer, 'bufor opustoszal po dostawie');

        // Bilans: wejscie(0)+bufor_pocz == bufor_konc + dostarczone + strata.
        $this->assertEqualsWithDelta(
            $initialBuffer,
            $finalBuffer + $ctx->finBbl + $ctx->finHubLossBbl,
            1.0,
            'Bilans barylek zachowany takze przy dostawie do magazynu'
        );
    }

    public function testNewPipelineInputUsesHubBufferWhenStorageIsFull(): void
    {
        $ids      = $this->getTrackedIds();
        $playerId = $this->seedPlayer();
        $hubId    = $ids['hubId'];
        $wellId   = $ids['wellId'];

        $this->seedHub($hubId, 'Full storage input hub', 77, 'A1', 90.0, 'active', 'new', 'standard', 200.0, $playerId);
        $hubRow = $this->db->query("SELECT * FROM logistics_hubs WHERE id = {$hubId}")->fetch();
        $hubRow['region_political_risk'] = 1;

        $initialBuffer = (float)$hubRow['buffer_current_bbl'];
        $inputBbl      = 100.0;
        $storageCap    = 1000.0;

        // The synchronous well path credits input optimistically before hub reconciliation.
        // Synchroniczna sciezka odwiertu kredytuje wejscie optymistycznie przed rozliczeniem huba.
        $ctx = $this->makeCtx($hubId, $wellId, $hubRow, $storageCap, $storageCap + $inputBbl);
        $ctx->hubInputAccum[$hubId] = $inputBbl;
        $ctx->finBbl                = $inputBbl;
        $ctx->deliveredBbl          = $inputBbl;
        $ctx->finRevenue            = $inputBbl * 70.0;

        $section = new WellHubSection(
            $ctx,
            new DateTime('2026-07-15 12:00:00'),
            new HubTickService($this->db, new HubService($this->db)),
            null,
            null,
            [],
            ['opex' => 1.0, 'loss' => 1.0],
            70.0,
            new OutboundLegService([]),
            null
        );
        $section->finalize($playerId, 1.0, []);

        $finalBuffer = (float)$this->db->query("SELECT buffer_current_bbl FROM logistics_hubs WHERE id = {$hubId}")->fetchColumn();
        $storageDelta = $ctx->currentStorage - $storageCap;

        $this->assertLessThanOrEqual($storageCap, $ctx->currentStorage, 'Hub flow must not overfill storage.');
        $this->assertGreaterThan($initialBuffer, $finalBuffer, 'Blocked new input should wait in the hub buffer.');
        $this->assertEqualsWithDelta(0.0, $ctx->finBbl, 0.01, 'No new oil is delivered into a full storage.');
        $this->assertEqualsWithDelta(
            $initialBuffer + $inputBbl,
            $finalBuffer + $storageDelta + $ctx->finHubLossBbl + $ctx->finOutboundLossBbl,
            0.05,
            'Opening buffer plus input must equal closing buffer, storage delta and classified losses.'
        );
    }
}
