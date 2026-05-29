<?php
declare(strict_types=1);

/**
 * WellRoadTripSection  tick: przetwarzanie ukonczonych kursow drogowych.
 * WellRoadTripSection  tick: processing completed road trips.
 *
 * Wywolywana per gracz po WellLoopSection (po jej run()), przed zapisem magazynu.
 * Called per player after WellLoopSection (after its run()), before storage save.
 *
 * Dla kazdego kursu z well_road_trips gdzie eta_at <= NOW():
 *   - stosuje incydenty per kurs (RoadTransportService::processCompletedTrips)
 *   - kredytuje dostarczona rope do magazynu w pamieci
 *
 * For each trip in well_road_trips where eta_at <= NOW():
 *   - applies per-trip incidents (RoadTransportService::processCompletedTrips)
 *   - credits delivered oil to in-memory storage
 */
class WellRoadTripSection
{
    // Liczniki eksponowane do PlayersSection / Counters exposed to PlayersSection
    public float $deliveredBbl   = 0.0;
    public float $lostBbl        = 0.0;
    public int   $completedCount = 0;

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
     * @param  array<string, mixed>       $hseBonus
     */
    public function process(
        int                  $playerId,
        float                $currentStorage,
        float                $storageCapacity,
        array                $hseBonus,
        ?RoadTransportService $roadTransportSvc
    ): float {
        if ($roadTransportSvc === null) {
            return $currentStorage;
        }

        try {
            $result    = $roadTransportSvc->processCompletedTrips($playerId, $hseBonus);
            $delivered = (float)$result['delivered_bbl'];
            $lost      = (float)$result['lost_bbl'];
            $count     = (int)$result['completed_count'];

            if ($delivered > 0.0) {
                $freeSpace = max(0.0, $storageCapacity - $currentStorage);
                $credited  = min($delivered, $freeSpace);
                $overflow  = max(0.0, $delivered - $credited);
                if ($credited > 0.0) {
                    $currentStorage += $credited;
                }
                if ($overflow > 0.0) {
                    $lost += $overflow;
                    GameLog::warn('tick', 'road_trip_storage_overflow', [
                        'player_id' => $playerId,
                        'overflow_bbl' => round($overflow, 2),
                        'storage_capacity' => round($storageCapacity, 2),
                        'storage_before' => round($currentStorage - $credited, 2),
                    ]);
                }
                $this->deliveredBbl += $credited;
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
