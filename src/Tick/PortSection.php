<?php
declare(strict_types=1);

/**
 * PortSection tick: obsluga kolejki portowej i kredytowanie magazynu.
 * PortSection tick: port queue processing and storage crediting.
 *
 * Odpowiada za: / Responsible for:
 * - przetwarzanie dostaw 'waiting' w kolejce portowej / processing 'waiting' deliveries in port queue
 * - obliczanie oplat portowych per bbl / calculating port fees per bbl
 * - zwrot liczby bbl do dodania do magazynu gracza / returning bbl count to add to player storage
 * - aktualizacje statusow portow (overloaded/active) / updating port statuses
 *
 * NIE zapisuje bezposrednio do storage zwraca deliveredBbl,
 * PlayersSection dodaje je do currentStorage przed finalnym zapisem.
 * Does NOT write directly to storage returns deliveredBbl,
 * PlayersSection adds them to currentStorage before the final save.
 *
 * Wywoywana per gracz w PlayersSection po MarineDeliverySection.
 * Called per player in PlayersSection after MarineDeliverySection.
 */
class PortSection
{
 // Wyniki per gracz / Per-player results
    public float $deliveredBbl      = 0.0;  // bbl dodane do magazynu / bbl added to storage
    public float $handlingCost      = 0.0;  // oplaty portowe do odliczenia / port fees to deduct
    public int   $processedCount    = 0;    // liczba przetworzonych dostaw / processed delivery count
 /** @var array<int, float> well_id => credited bbl (basis for the second transport leg) */
    public array $deliveredByWell   = [];

    private PDO      $db;
    private DateTime $now;

    public function __construct(PDO $db, DateTime $now)
    {
        $this->db  = $db;
        $this->now = $now;
    }

 /**
 * Przetwarza kolejke portowa gracza.
 * Processes the player's port queue.
 *
 * Zwraca nowy poziom magazynu po doliczeniu dostaw morskich.
 * Returns the new storage level after crediting marine deliveries.
 */
    public function process(
        int   $playerId,
        float $currentStorage,
        float $storageCapacity,
        float $oilPrice
    ): float {
        try {
 // Pobierz do 10 oczekujacych dostaw per tick / Fetch up to 10 waiting deliveries per tick
            $stmt = $this->db->prepare(
                "SELECT pq.*,
                        p.throughput_per_tick,
                        p.handling_cost_per_bbl,
                        p.status AS port_status,
                        md.well_id AS well_id
                   FROM port_queue pq
                   JOIN ports p ON p.id = pq.port_id
                   LEFT JOIN marine_deliveries md ON md.id = pq.delivery_id
                  WHERE pq.player_id = ?
                    AND pq.status = 'waiting'
                    AND p.status != 'closed'
                  ORDER BY pq.queued_at ASC
                  LIMIT 10"
            );
            $stmt->execute([$playerId]);
            $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // BUG1 FIX: track bbl processed this tick and enforce throughput_per_tick limit.
            // All entries in $entries share the same port (joined via port_id), so we read
            // throughput_per_tick from the first row; the SELECT already limits to one port
            // per player via ORDER BY queued_at.
            $processedThisTick = 0.0;
            $throughputLimit   = isset($entries[0])
                ? (float)($entries[0]['throughput_per_tick'] ?? PHP_FLOAT_MAX)
                : PHP_FLOAT_MAX;

            foreach ($entries as $entry) {
                $freeSpace = $storageCapacity - $currentStorage;
                if ($freeSpace <= 0.0) {
 // Magazyn pelny zostawiamy w kolejce na nastepny tick
 // Storage full leave in queue for next tick
                    break;
                }
                // BUG1 FIX: stop processing when port throughput for this tick is exhausted
                if ($processedThisTick >= $throughputLimit) {
                    break;
                }
                $storageBefore  = $currentStorage;
                $currentStorage = $this->processEntry(
                    $entry, $playerId, $currentStorage, $freeSpace, $throughputLimit, $processedThisTick
                );
                $processedThisTick += max(0.0, $currentStorage - $storageBefore);
            }

 // Odswiez statusy portow / Refresh port statuses
            $this->refreshPortStatuses();

        } catch (Throwable $e) {
            GameLog::error('tick', 'PortSection::process FAILED', $e, ['player_id' => $playerId]);
        }

        return $currentStorage;
    }

 // 
 // Per-entry logic
 // 

 /**
 * @param array<string, mixed> $entry
 */
    private function processEntry(
        array $entry,
        int   $playerId,
        float $currentStorage,
        float $freeSpace,
        float $throughputLimit   = PHP_FLOAT_MAX,
        float $processedThisTick = 0.0
    ): float {
        $entryId      = (int)$entry['id'];
        $deliveryId   = (int)$entry['delivery_id'];
        $portId       = (int)$entry['port_id'];
        $volumeBbl    = (float)$entry['volume_bbl'];
        $costPerBbl   = (float)($entry['handling_cost_per_bbl'] ?? 0.50);
        $nowStr       = $this->now->format('Y-m-d H:i:s');

 // Kredytuj tyle ile miesci sie w magazynie / Credit only what fits in storage
 // BUG1 FIX: also cap by remaining throughput available this tick
        $remainingThroughput = max(0.0, $throughputLimit - $processedThisTick);
        $actual       = min($volumeBbl, $freeSpace, $remainingThroughput);
        $handlingCost = round($actual * $costPerBbl, 2);
        $remainder    = round($volumeBbl - $actual, 4);

        if ($remainder > 0.001) {
 // Magazyn nie pomiescil calej dostawy: kredytuj to co weszlo i zostaw reszte w kolejce
 // (status 'waiting') na nastepny tick - spojnie z przerwaniem petli przy pelnym magazynie,
 // zamiast cicho gubic nadmiar.
 // Storage could not fit the whole delivery: credit what fits and keep the remainder queued
 // ('waiting') for the next tick - consistent with the loop break on full storage, instead
 // of silently dropping the overflow.
            $this->db->prepare(
                "UPDATE port_queue SET volume_bbl = ? WHERE id = ?"
            )->execute([$remainder, $entryId]);
 // BUG2 FIX: update marine_deliveries status so partial deliveries are not permanently stuck
 // in 'waiting_for_port'. Use 'waiting_for_port' to keep the delivery associated with the
 // port queue entry that still has volume remaining.
            $this->db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'waiting_for_port', handling_cost = COALESCE(handling_cost, 0) + ?
                  WHERE id = ? AND status NOT IN ('delivered','lost')"
            )->execute([$handlingCost, $deliveryId]);
            GameLog::info('tick', 'port_delivery_partial', [
                'delivery_id' => $deliveryId,
                'player_id'   => $playerId,
                'credited'    => round($actual, 4),
                'remainder'   => $remainder,
            ]);
        } else {
 // M2: Oznacz atomowo port_queue + marine_deliveries — awaria miedzy nimi daje split-brain
 // (done w kolejce ale 'waiting_for_port' w deliveries). Transakcja zapobiega sierocie.
 // M2: Mark port_queue + marine_deliveries atomically — crash between them creates split-brain
 // (done in queue but 'waiting_for_port' in deliveries). Transaction prevents orphan state.
            try {
                $this->db->beginTransaction();
                $this->db->prepare(
                    "UPDATE port_queue
                        SET status = 'done', processed_at = ?
                      WHERE id = ?"
                )->execute([$nowStr, $entryId]);
                $this->db->prepare(
                    "UPDATE marine_deliveries
                        SET status = 'delivered', delivered_at = ?, handling_cost = ?
                      WHERE id = ?"
                )->execute([$nowStr, $handlingCost, $deliveryId]);
                $this->db->commit();
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    try { $this->db->rollBack(); } catch (Throwable $re) {}
                }
                GameLog::error('tick', 'port_delivery_done FAILED — rolled back', $e, [
                    'entry_id' => $entryId, 'delivery_id' => $deliveryId,
                ]);
                return $currentStorage;
            }
            $this->processedCount++;
        }

 // Akumuluj wyniki (PlayersSection doda do storage i cash) / Accumulate results
        $currentStorage      += $actual;
        $this->deliveredBbl  += $actual;
        $this->handlingCost  += $handlingCost;

        $wellId = (int)($entry['well_id'] ?? 0);
        if ($wellId > 0 && $actual > 0.0) {
            $this->deliveredByWell[$wellId] = ($this->deliveredByWell[$wellId] ?? 0.0) + $actual;
        }

        GameLog::info('tick', 'port_delivery_processed', [
            'delivery_id'   => $deliveryId,
            'port_id'       => $portId,
            'player_id'     => $playerId,
            'vol_bbl'       => round($actual, 3),
            'handling_cost' => $handlingCost,
        ]);

        return $currentStorage;
    }

 /**
 * Odswiez statusy portow na podstawie rozmiaru kolejki.
 * Refresh port statuses based on queue size.
 */
    private function refreshPortStatuses(): void
    {
        try {
            $this->db->exec(
                "UPDATE ports p
                    LEFT JOIN (
                        SELECT port_id, COUNT(*) AS cnt
                          FROM port_queue
                         WHERE status IN ('waiting','processing')
                         GROUP BY port_id
                    ) q ON q.port_id = p.id
                   SET p.status = CASE
                        WHEN COALESCE(q.cnt, 0) >= p.queue_limit * 0.8
                             AND p.status = 'active'     THEN 'overloaded'
                        WHEN COALESCE(q.cnt, 0) < p.queue_limit * 0.8
                             AND p.status = 'overloaded' THEN 'active'
                        ELSE p.status
                   END,
                   p.updated_at = NOW()
                 WHERE p.status IN ('active','overloaded')"
            );
        } catch (Throwable $e) {
            GameLog::error('tick', 'PortSection::refreshPortStatuses FAILED', $e);
        }
    }
}
