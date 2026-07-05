<?php
declare(strict_types=1);

/**
 * WellRoadTripSection tick: przetwarzanie ukonczonych kursow drogowych.
 * WellRoadTripSection tick: processing completed road trips.
 *
 * Wywolywana per gracz po WellLoopSection (po jej run()), przed zapisem magazynu.
 * Called per player after WellLoopSection (after its run()), before storage save.
 *
 * Dla kazdego kursu z well_road_trips gdzie eta_at <= NOW():
 * - stosuje incydenty per kurs (RoadTransportService::processCompletedTrips)
 * - kredytuje dostarczona rope do magazynu w pamieci
 *
 * For each trip in well_road_trips where eta_at <= NOW():
 * - applies per-trip incidents (RoadTransportService::processCompletedTrips)
 * - credits delivered oil to in-memory storage
 */
class WellRoadTripSection
{
 // Liczniki eksponowane do PlayersSection / Counters exposed to PlayersSection
    public float $deliveredBbl   = 0.0;
    public float $lostBbl        = 0.0;
    public int   $completedCount = 0;
 /** @var array<int, float> well_id => credited bbl (basis for the second transport leg) */
    public array $deliveredByWell = [];

    /** @phpstan-ignore property.onlyWritten */
    private PDO      $db;
    private DateTime $now;

    public function __construct(PDO $db, DateTime $now)
    {
        $this->db  = $db;
        $this->now = $now;
    }

 /**
 * Przetwarza ukonczone kursy; zwraca zaktualizowany poziom magazynu.
 * Processes completed trips; returns updated storage level.
 *
 * @param array<string, mixed> $hseBonus
 */
    /**
     * @param array<int, int> $wellHubMap well_id => hub_id (odwierty przypisane do huba)
     */
    public function process(
        int                  $playerId,
        float                $currentStorage,
        float                $storageCapacity,
        array                $hseBonus,
        ?RoadTransportService $roadTransportSvc,
        ?ProtectionService   $protectionSvc = null,
        ?SabotageService     $sabotageSvc = null,
        array                $wellHubMap = []
    ): float {
        if ($roadTransportSvc === null) {
            return $currentStorage;
        }

        try {
            // Snapshot IDs already in 'crediting' before this batch so the overflow
            // scale-down UPDATE cannot touch them (orphaned rows from a previous tick).
            $orphanStmt = $this->db->prepare(
                "SELECT id FROM well_road_trips WHERE player_id = ? AND status = 'crediting'"
            );
            $orphanStmt->execute([$playerId]);
            $orphanIds = $orphanStmt->fetchAll(PDO::FETCH_COLUMN);

            $result    = $roadTransportSvc->processCompletedTrips($playerId, $hseBonus, $protectionSvc, $sabotageSvc);
            $delivered = (float)$result['delivered_bbl'];
            $lost      = (float)$result['lost_bbl'];
            $count     = (int)$result['completed_count'];
            $byWell    = (array)($result['delivered_by_well'] ?? []);

            if ($delivered > 0.0) {
                // M4: rozdziel dostarczona rope na odwierty przypisane do huba i bez huba.
                // Odwierty z HUBEM: pelna ilosc idzie do bufora huba (przez deliveredByWell ->
                // queueHubDeliveredInputs -> hubInputAccum -> finalizeHubTicks), BEZ capa magazynu
                // — dokladnie jak produkcja synchroniczna do huba. Wczesniej cap magazynu kasowal
                // ich rope jako "overflow" zanim w ogole dotarla do huba (hub ma wlasny bufor).
                // Odwierty BEZ huba: cap magazynem, nadmiar = strata (bez zmian; nie maja gdzie czekac).
                // M4: split delivered oil into hub-assigned wells and hubless wells.
                // Hub wells: the full amount goes to the hub buffer (via deliveredByWell ->
                // hubInputAccum -> finalizeHubTicks), WITHOUT the storage cap — exactly like
                // synchronous hub production. Previously the storage cap destroyed it as "overflow"
                // before it ever reached the hub (which has its own buffer).
                // Hubless wells: storage cap, excess = loss (unchanged; nowhere to wait).
                $hubDelivered = 0.0; $nonHubDelivered = 0.0; $hubWellIds = [];
                foreach ($byWell as $wid => $bbl) {
                    if (isset($wellHubMap[(int)$wid])) {
                        $hubDelivered += (float)$bbl;
                        $hubWellIds[(int)$wid] = true;
                    } else {
                        $nonHubDelivered += (float)$bbl;
                    }
                }

                $freeSpace      = max(0.0, $storageCapacity - $currentStorage);
                $nonHubCredited = min($nonHubDelivered, $freeSpace);
                $nonHubOverflow = max(0.0, $nonHubDelivered - $nonHubCredited);
                $nonHubRatio    = $nonHubDelivered > 0.0 ? ($nonHubCredited / $nonHubDelivered) : 0.0;

                // Overflow (tylko bez-huba) obslugiwany PRZED kredytem w pamieci — crash-safe:
                // najpierw skaluje DB delivered_bbl (tylko kursow bez huba), potem currentStorage.
                // Overflow (hubless only) handled BEFORE the in-memory credit — crash-safe: scale the
                // DB delivered_bbl first (only for hubless trips), then currentStorage.
                if ($nonHubOverflow > 0.0) {
                    $lost += $nonHubOverflow;
                    GameLog::warn('tick', 'road_trip_storage_overflow', [
                        'player_id'        => $playerId,
                        'overflow_bbl'     => round($nonHubOverflow, 2),
                        'storage_capacity' => round($storageCapacity, 2),
                        'storage_before'   => round($currentStorage, 2),
                    ]);
                    if ($nonHubCredited < $nonHubDelivered) {
                        try {
                            $ownTx = !$this->db->inTransaction();
                            if ($ownTx) $this->db->beginTransaction();
                            // Skaluj delivered_bbl kursow 'crediting' TYLKO bez huba: kursy hubowe
                            // nie sa capowane magazynem, wiec ich delivered_bbl zostaje pelny.
                            // Wyklucz tez osierocone kursy z poprzedniego ticka (orphanIds).
                            // Scale delivered_bbl of 'crediting' trips ONLY for hubless wells: hub
                            // trips are not storage-capped, so their delivered_bbl stays full.
                            // Also exclude orphaned trips from a previous tick (orphanIds).
                            $sql    = "UPDATE well_road_trips SET delivered_bbl = delivered_bbl * ? WHERE player_id = ? AND status = 'crediting'";
                            $params = [round($nonHubRatio, 8), $playerId];
                            if ($hubWellIds) {
                                $hph  = implode(',', array_fill(0, count($hubWellIds), '?'));
                                $sql .= " AND well_id NOT IN ($hph)";
                                foreach (array_keys($hubWellIds) as $hwid) { $params[] = $hwid; }
                            }
                            if ($orphanIds) {
                                $oph  = implode(',', array_fill(0, count($orphanIds), '?'));
                                $sql .= " AND id NOT IN ($oph)";
                                foreach ($orphanIds as $oid) { $params[] = $oid; }
                            }
                            $this->db->prepare($sql)->execute($params);
                            if ($ownTx) $this->db->commit();
                        } catch (Throwable $e) {
                            if (isset($ownTx) && $ownTx && $this->db->inTransaction()) {
                                try { $this->db->rollBack(); } catch (Throwable $re) {}
                            }
                            GameLog::error('tick', 'road_trip_overflow_delivered_update FAILED', $e, ['player_id' => $playerId]);
                        }
                    }
                }

                // Kredyt do magazynu: bez-huba (po capie) + hub (pelny, optymistycznie — finalize
                // huba zbuforuje/pogodzi jak przy produkcji synchronicznej).
                // Storage credit: hubless (post-cap) + hub (full, optimistic — hub finalize buffers/
                // reconciles it like synchronous production).
                $creditedToStorage = $nonHubCredited + $hubDelivered;
                if ($creditedToStorage > 0.0) {
                    $currentStorage += $creditedToStorage;
                }

                // Rozklad per-odwiert: huby pelna ilosc, bez-huba przeskalowane wolnym miejscem.
                // Per-well breakdown: hub wells full amount, hubless wells scaled by free storage.
                foreach ($byWell as $wid => $bbl) {
                    $wid    = (int)$wid;
                    $scaled = isset($hubWellIds[$wid])
                        ? round((float)$bbl, 4)
                        : round((float)$bbl * $nonHubRatio, 4);
                    if ($scaled > 0.0) {
                        $this->deliveredByWell[$wid] = ($this->deliveredByWell[$wid] ?? 0.0) + $scaled;
                    }
                }
                $this->deliveredBbl += $creditedToStorage;
            }

            $this->lostBbl        += $lost;
            $this->completedCount += $count;

            if ($count > 0) {
                GameLog::info('tick', 'road_trips_completed', [
                    'player_id'       => $playerId,
                    'completed_count' => $count,
                    'delivered_bbl'   => round($delivered, 2),
                    'lost_bbl'        => round($lost, 2),
                ]);
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'WellRoadTripSection::process FAILED', $e, ['player_id' => $playerId]);
        }

        return $currentStorage;
    }
}
