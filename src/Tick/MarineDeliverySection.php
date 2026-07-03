<?php
declare(strict_types=1);

/**
 * MarineDeliverySection tick: aktualizacja statusow dostaw morskich.
 * MarineDeliverySection tick: marine delivery status updates.
 *
 * Odpowiada za: / Responsible for:
 * - departing in_transit (pierwsze przetworzenie) / first processing
 * - in_transit waiting_for_port (po uplywie ETA) / after ETA passes
 * - zdarzenia losowe: sztorm, piraci, awaria / random events: storm, pirates, breakdown
 * - opoznione dostawy (delayed) ponowna proba / delayed retry
 * - przekazanie do kolejki portowej / forwarding to port queue
 *
 * Wywoywana per gracz w PlayersSection przed PortSection.
 * Called per player in PlayersSection before PortSection.
 */
class MarineDeliverySection
{
 // Liczniki (eksponowane do statystyk) / Counters (exposed for stats)
    public float $lostBbl           = 0.0;
    public int   $lostDeliveries    = 0;
    public int   $delayedDeliveries = 0;
    public int   $queuedDeliveries  = 0;

 // M1: Keszuj porty per region_id — unika powielonych SELECT za kazdy rejs w tikuej.
 // M1: Cache ports per region_id — avoids repeated SELECT per delivery in one tick.
 /** @var array<int, int|null> region_id => port_id or null */
    private array $portCache = [];

    private PDO      $db;
    private DateTime $now;

    public function __construct(PDO $db, DateTime $now)
    {
        $this->db  = $db;
        $this->now = $now;
    }

 /**
 * Globalne czyszczenie zalegajacych dostaw morskich (raz na tick, nie per gracz).
 * Global cleanup of stale marine deliveries (once per tick, not per player).
 *
 * Dwie kategorie / Two categories:
 *  1. dostawy zakonczone (delivered/lost) starsze niz 7 dni — DELETE (balast historii),
 *     finished deliveries (delivered/lost) older than 7 days — DELETE (history bloat);
 *  2. utkniete rejsy (departing/in_transit/delayed/waiting_for_port) — oznaczane
 *     status='lost' (widoczna strata w historii gracza, sprzatana krokiem 1 po 7 dniach),
 *     NIE kasowane bez sladu; wpisy port_queue sa zwalniane.
 *     stuck voyages (departing/in_transit/delayed/waiting_for_port) — marked
 *     status='lost' (visible loss in player history, pruned by step 1 after 7 days),
 *     NOT deleted without a trace; port_queue rows are released.
 *
 * Prog 2 dni jest spojny z filtrem dropdownu w admin/incidents.php (departure_at).
 * The 2-day window matches the admin/incidents.php dropdown filter (departure_at).
 *
 * @return array{terminal:int,stuck:int} liczba usunietych rekordow / deleted row counts
 */
    public static function purgeStale(PDO $db): array
    {
        $terminal = 0;
        $stuck    = 0;
        try {
 // 1) Zakonczone dostawy starsze niz 7 dni / Finished deliveries older than 7 days
            $stmt = $db->prepare(
                "DELETE FROM marine_deliveries
                  WHERE status IN ('delivered','lost')
                    AND COALESCE(delivered_at, arrived_at, eta_at, created_at) < NOW() - INTERVAL 7 DAY"
            );
            $stmt->execute();
            $terminal = $stmt->rowCount();

 // 2) Utkniete rejsy morskie starsze niz 2 dni / Stuck sea voyages older than 2 days
 // 'delayed' z port_id (port zapelnionyretry) jest wylaczone — to legalny rejs czekajacy na
 // miejsce w kolejce; step 3 juz obsluguje 'delayed' bez port_id (port_id IS NULL).
 // 'delayed' with port_id (port-full retry) is excluded — it is a legitimate voyage waiting
 // for queue space; step 3 already handles 'delayed' with no port assigned (port_id IS NULL).
 // Oznaczamy jako 'lost' zamiast DELETE: gracz widzi strate w historii (zaden zapis nie znika
 // bez sladu — wczesniej ladunek offline'owego gracza po prostu wyparowywal); wiersz 'lost'
 // sprzata krok 1 po 7 dniach.
 // Marked 'lost' instead of DELETE: the player sees the loss in history (nothing vanishes
 // without a trace — previously an offline player's cargo simply evaporated); step 1 prunes
 // the 'lost' row after 7 days.
            $stmt = $db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'lost', incident_type = 'stale_purge', delivered_at = NOW()
                  WHERE status IN ('departing','in_transit')
                    AND departure_at < NOW() - INTERVAL 2 DAY"
            );
            $stmt->execute();
            $stuck = $stmt->rowCount();

 // 2b) Dostawa 'delayed' z portem (port pelny, retry) utknela na zawsze:
 //     port zamkniety, kolejka nigdy nie drenuje. Bezpieczne usunac po 7 dniach.
 //     Delayed delivery with an assigned port (port-full retry) stuck permanently:
 //     port went closed, the queue never drains. Safe to purge after 7 days.
            $stmt = $db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'lost', incident_type = 'stale_purge', delivered_at = NOW()
                  WHERE status = 'delayed'
                    AND port_id IS NOT NULL
                    AND departure_at < NOW() - INTERVAL 7 DAY"
            );
            $stmt->execute();
            $stuck += $stmt->rowCount();

 // 2c) Dostawy 'waiting_for_port' starsze niz 7 dni (np. port przeszedl w 'closed' —
 //     kolejka nigdy nie drenuje): oznacz 'lost' i zwolnij sloty port_queue, inaczej
 //     martwe wpisy wiecznie zajmuja queue_limit i blokuja nowe dostawy do portu.
 // 2c) 'waiting_for_port' deliveries older than 7 days (e.g. the port went 'closed' —
 //     the queue never drains): mark 'lost' and free the port_queue slots, otherwise
 //     dead entries consume queue_limit forever and block new deliveries to the port.
            $stmt = $db->prepare(
                "DELETE FROM port_queue
                  WHERE delivery_id IN (
                        SELECT id FROM marine_deliveries
                         WHERE status = 'waiting_for_port'
                           AND departure_at < NOW() - INTERVAL 7 DAY
                  )"
            );
            $stmt->execute();
            $stmt = $db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'lost', incident_type = 'stale_purge', delivered_at = NOW()
                  WHERE status = 'waiting_for_port'
                    AND departure_at < NOW() - INTERVAL 7 DAY"
            );
            $stmt->execute();
            $stuck += $stmt->rowCount();

 // 3) Dostawy 'delayed' bez przypisanego portu (port_id IS NULL) starsze niz 2 godziny.
 //    Sa definitywnie nieodwracalne: przy tworzeniu nie bylo portu, przy przybyciu
 //    tez nie bylo (findPort zwrocil null) — nigdy nie dotrza do magazynu.
 //    Delayed deliveries with no port assigned (port_id IS NULL) older than 2 hours.
 //    These are definitively undeliverable: no port at creation, no port at arrival
 //    (findPort returned null) — they will never reach storage.
            $stmt = $db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'lost', incident_type = 'no_port', delivered_at = NOW()
                  WHERE status = 'delayed'
                    AND port_id IS NULL
                    AND departure_at < NOW() - INTERVAL 2 HOUR"
            );
            $stmt->execute();
            $stuck += $stmt->rowCount();

            if ($terminal > 0 || $stuck > 0) {
                GameLog::info('tick', 'marine_deliveries_purged', [
                    'terminal' => $terminal, 'stuck' => $stuck,
                ]);
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'MarineDeliverySection::purgeStale FAILED', $e);
        }

        return ['terminal' => $terminal, 'stuck' => $stuck];
    }

 /**
 * Przetwarza wszystkie aktywne dostawy morskie gracza.
 * Processes all active marine deliveries for a player.
 *
 * @param array<string, mixed> $hseBonus
 */
    public function process(int $playerId, array $hseBonus, float $deltaHours): void
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT md.*, COALESCE(w.region_id, 0) AS region_id
                   FROM marine_deliveries md
                   LEFT JOIN wells w ON w.id = md.well_id
                  WHERE md.player_id = ?
                    AND md.status IN ('departing','in_transit','delayed')
                  ORDER BY md.eta_at ASC"
            );
            $stmt->execute([$playerId]);
            $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($deliveries as $delivery) {
                $this->processOne($delivery, $hseBonus, $deltaHours);
            }
        } catch (Throwable $e) {
            GameLog::error('tick', 'MarineDeliverySection::process FAILED', $e, ['player_id' => $playerId]);
        }
    }

 // 
 // Per-delivery logic
 // 

 /**
 * @param array<string, mixed> $delivery
 * @param array<string, mixed> $hseBonus
 */
    private function processOne(array $delivery, array $hseBonus, float $deltaHours): void
    {
        $id        = (int)$delivery['id'];
        $status    = (string)$delivery['status'];
        $volumeBbl = (float)$delivery['volume_bbl'];
        $portId    = $delivery['port_id'] !== null ? (int)$delivery['port_id'] : null;
        $regionId  = (int)$delivery['region_id'];
        $nowStr    = $this->now->format('Y-m-d H:i:s');

 // departing in_transit (pierwsze przetworzenie / first processing)
 // Rejs ktory wlasnie wyruszyl nie moze dostac incydentu w tym samym tiku (zero czasu w drodze) -
 // przejscie na in_transit i koniec; rolka incydentu nastapi w kolejnych tikach.
 // A voyage that just departed must not roll an incident the same tick (zero travel time) -
 // transition to in_transit and stop; the incident roll happens on later ticks.
        if ($status === 'departing') {
            $this->db->prepare(
                "UPDATE marine_deliveries SET status = 'in_transit' WHERE id = ? AND player_id = ?"
            )->execute([$id, (int)$delivery['player_id']]);
            return;
        }

 // Zdarzenie losowe tylko dla rejsow aktywnie plynacych (in_transit), nie opoznionych.
 // Random event only for actively sailing voyages (in_transit), not delayed ones.
 // Delayed deliveries already suffered an incident; re-rolling each tick is a bug.
        if ($status === 'in_transit') {
 // Clamp jak w OffshoreTransportService: bez niego deltaHours~24 (nadrabianie po przerwie
 // crona) dawalo ~96% szansy incydentu na rejs w jednym ticku (15% z nich = totalna strata).
 // Clamp as in OffshoreTransportService: without it deltaHours~24 (catch-up after a cron
 // outage) meant ~96% incident chance per voyage in one tick (15% of those = total loss).
            $incidentChance = min(0.95, 0.04 * $deltaHours * (float)($hseBonus['failure_reduction'] ?? 1.0));
            if (mt_rand(1, 100000) <= (int)($incidentChance * 100000)) {
                $this->applyIncident($id, $volumeBbl, $delivery['player_id']);
                return;
            }
        }

 // Sprawdz czy ETA minela / Check if ETA has passed
        $eta = new DateTime($delivery['eta_at']);
        if ($this->now < $eta) {
            return; // Jeszcze w drodze / Still in transit
        }

 // Szukaj portu jesli nie przypisany / Find port if not assigned
        if ($portId === null) {
            $portId = $this->findPort($regionId);
        }

        if ($portId === null) {
 // Brak portu opoznij o 1 godzine / No port delay by 1 hour
            $this->db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'delayed',
                        delay_ticks = delay_ticks + 1,
                        eta_at = DATE_ADD(eta_at, INTERVAL 1 HOUR)
                  WHERE id = ? AND player_id = ?"
            )->execute([$id, (int)$delivery['player_id']]);
            $this->delayedDeliveries++;
            GameLog::info('tick', 'marine_delivery_delayed_no_port', [
                'delivery_id' => $id, 'player_id' => $delivery['player_id'], 'region_id' => $regionId,
            ]);
            return;
        }

        $this->forwardToPort($id, $portId, $volumeBbl, (int)$delivery['player_id'], $nowStr);
    }

 /**
 * Zastosuj losowe zdarzenie (utrata lub opoznienie).
 * Apply a random event (loss or delay).
 */
    private function applyIncident(int $deliveryId, float $volumeBbl, mixed $playerId): void
    {
        $roll = mt_rand(1, 100);

        if ($roll <= 5) {
 // Piraci caly ladunek utracony / Pirates entire cargo lost
            try {
                $this->db->beginTransaction();
                // Filtruj po player_id — izolacja gracza przy UPDATE marine_deliveries (Rule 1).
                // Filter by player_id — player isolation on UPDATE marine_deliveries (Rule 1).
                $this->db->prepare(
                    "UPDATE marine_deliveries
                        SET status = 'lost', incident_type = 'piracy', delivered_at = NOW()
                      WHERE id = ? AND player_id = ?"
                )->execute([$deliveryId, $playerId]);
 // H3: Usun z port_queue na wypadek sieroty (dostawa trafila do kolejki przed utrata)
 // H3: Remove from port_queue in case of orphan (delivery was queued before being lost)
                $this->db->prepare("DELETE FROM port_queue WHERE delivery_id = ? AND player_id = ?")->execute([$deliveryId, (int)$playerId]);
                $this->db->commit();
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    try { $this->db->rollBack(); } catch (Throwable $re) {}
                }
                GameLog::error('tick', 'marine_delivery_lost_piracy FAILED — rolled back', $e, ['delivery_id' => $deliveryId]);
                return;
            }
            $this->lostBbl += $volumeBbl;
            $this->lostDeliveries++;
            GameLog::warn('tick', 'marine_delivery_lost_piracy', [
                'delivery_id' => $deliveryId, 'player_id' => $playerId, 'vol_bbl' => $volumeBbl,
            ]);

        } elseif ($roll <= 15) {
 // Katastrofa caly ladunek utracony / Catastrophe entire cargo lost
            try {
                $this->db->beginTransaction();
                // Filtruj po player_id — izolacja gracza przy UPDATE marine_deliveries (Rule 1).
                // Filter by player_id — player isolation on UPDATE marine_deliveries (Rule 1).
                $this->db->prepare(
                    "UPDATE marine_deliveries
                        SET status = 'lost', incident_type = 'catastrophe', delivered_at = NOW()
                      WHERE id = ? AND player_id = ?"
                )->execute([$deliveryId, $playerId]);
 // H3: Usun z port_queue na wypadek sieroty / H3: Remove from port_queue in case of orphan
                $this->db->prepare("DELETE FROM port_queue WHERE delivery_id = ? AND player_id = ?")->execute([$deliveryId, (int)$playerId]);
                $this->db->commit();
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) {
                    try { $this->db->rollBack(); } catch (Throwable $re) {}
                }
                GameLog::error('tick', 'marine_delivery_lost_catastrophe FAILED — rolled back', $e, ['delivery_id' => $deliveryId]);
                return;
            }
            $this->lostBbl += $volumeBbl;
            $this->lostDeliveries++;
            GameLog::warn('tick', 'marine_delivery_lost_catastrophe', [
                'delivery_id' => $deliveryId, 'player_id' => $playerId, 'vol_bbl' => $volumeBbl,
            ]);

        } elseif ($roll <= 40) {
 // Sztorm opoznienie o 2 godziny / Storm 2 hour delay
            // Filtruj po player_id — izolacja gracza przy UPDATE marine_deliveries (Rule 1).
            // Filter by player_id — player isolation on UPDATE marine_deliveries (Rule 1).
            $this->db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'delayed', incident_type = 'storm',
                        delay_ticks = delay_ticks + 1,
                        eta_at = DATE_ADD(eta_at, INTERVAL 2 HOUR)
                  WHERE id = ? AND player_id = ?"
            )->execute([$deliveryId, $playerId]);
            $this->delayedDeliveries++;
            GameLog::info('tick', 'marine_delivery_delayed_storm', [
                'delivery_id' => $deliveryId, 'player_id' => $playerId,
            ]);

        } else {
 // Awaria silnika opoznienie o 1 godzine / Engine breakdown 1 hour delay
            // Filtruj po player_id — izolacja gracza przy UPDATE marine_deliveries (Rule 1).
            // Filter by player_id — player isolation on UPDATE marine_deliveries (Rule 1).
            $this->db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'delayed', incident_type = 'breakdown',
                        delay_ticks = delay_ticks + 1,
                        eta_at = DATE_ADD(eta_at, INTERVAL 1 HOUR)
                  WHERE id = ? AND player_id = ?"
            )->execute([$deliveryId, $playerId]);
            $this->delayedDeliveries++;
            GameLog::info('tick', 'marine_delivery_delayed_breakdown', [
                'delivery_id' => $deliveryId, 'player_id' => $playerId,
            ]);
        }
    }

 /**
 * Znajdz port dla regionu.
 * Find a port for the region.
 */
    private function findPort(int $regionId): ?int
    {
 // M1: Zwroc z keszu jesli region juz sprawdzony w tym tiku.
 // M1: Return from cache if this region was already looked up this tick.
        if (array_key_exists($regionId, $this->portCache)) {
            return $this->portCache[$regionId];
        }

        $portId = null;

        if ($regionId > 0) {
            $stmt = $this->db->prepare(
                "SELECT id FROM ports
                  WHERE region_id = ?
                    AND status IN ('active','overloaded')
                  ORDER BY status = 'active' DESC
                  LIMIT 1"
            );
            $stmt->execute([$regionId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $portId = (int)$row['id'];
                $this->portCache[$regionId] = $portId;
                return $portId;
            }
        }

 // Fallback: dowolny aktywny port / Fallback: any active port
        $row = $this->db->query(
            "SELECT id FROM ports WHERE status = 'active' ORDER BY RAND() LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);

        $portId = $row ? (int)$row['id'] : null;
        $this->portCache[$regionId] = $portId;
        return $portId;
    }

 /**
 * Przekaz dostawe do kolejki portowej.
 * Forward delivery to the port queue.
 */
    private function forwardToPort(
        int    $deliveryId,
        int    $portId,
        float  $volumeBbl,
        int    $playerId,
        string $nowStr
    ): void {
 // M1: Polacz dwa oddzielne SELECTy (queue_limit + queue count) w jedno zapytanie.
 // M1: Merge two separate SELECTs (queue_limit + queue count) into a single query.
        $capStmt = $this->db->prepare(
            "SELECT p.queue_limit,
                    (SELECT COUNT(*) FROM port_queue pq
                      WHERE pq.port_id = p.id
                        AND pq.status IN ('waiting','processing')) AS queue_size
               FROM ports p WHERE p.id = ?"
        );
        $capStmt->execute([$portId]);
        $capRow     = $capStmt->fetch(PDO::FETCH_ASSOC);
        $queueLimit = $capRow ? (int)$capRow['queue_limit'] : 20;
        $queueSize  = $capRow ? (int)$capRow['queue_size']  : 0;

        if ($queueSize >= $queueLimit) {
 // Kolejka pelna opoznienie o 1 godzine. Status 'delayed' (nie 'waiting_for_port'),
 // bo bez wpisu w port_queue 'waiting_for_port' nie jest przez nic ponownie pobierane -
 // dostawa utknelaby na zawsze. 'delayed' jest ponownie pobierane przez process() co tick.
 // Queue full 1 hour delay. Status 'delayed' (not 'waiting_for_port'): without a port_queue
 // row, 'waiting_for_port' is never re-fetched and the delivery would be stuck forever.
 // 'delayed' is re-fetched by process() every tick so forwarding retries.
            $this->db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'delayed',
                        port_id = ?,
                        arrived_at = ?,
                        delay_ticks = delay_ticks + 1,
                        eta_at = DATE_ADD(eta_at, INTERVAL 1 HOUR)
                  WHERE id = ? AND player_id = ?"
            )->execute([$portId, $nowStr, $deliveryId, $playerId]);
            $this->delayedDeliveries++;
            GameLog::info('tick', 'marine_delivery_port_queue_full', [
                'delivery_id' => $deliveryId, 'port_id' => $portId, 'queue_size' => $queueSize,
            ]);
            return;
        }

 // H2: Dodaj do kolejki portowej atomowo — obie operacje musza sie udac lub zadna.
 // Awaria miedzy UPDATE a INSERT zostawialaby dostawa w 'waiting_for_port' bez wpisu
 // w port_queue, ktory PortSection nigdy by nie pobrala (utknielaby na zawsze).
 // H2: Add to port queue atomically — both operations must succeed or neither.
 // A crash between UPDATE and INSERT would leave the delivery in 'waiting_for_port'
 // with no port_queue entry, never picked up by PortSection (stuck forever).
        try {
            $this->db->beginTransaction();
            $this->db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'waiting_for_port', port_id = ?, arrived_at = ?
                  WHERE id = ? AND player_id = ?"
            )->execute([$portId, $nowStr, $deliveryId, $playerId]);
            $this->db->prepare(
                "INSERT INTO port_queue (port_id, delivery_id, player_id, volume_bbl, queued_at, status)
                 VALUES (?, ?, ?, ?, ?, 'waiting')
                 ON DUPLICATE KEY UPDATE
                     status     = IF(status = 'done', 'done', 'waiting'),
                     volume_bbl = IF(status = 'done', volume_bbl, VALUES(volume_bbl)),
                     queued_at  = IF(status = 'done', queued_at,  VALUES(queued_at))"
            )->execute([$portId, $deliveryId, $playerId, round($volumeBbl, 4), $nowStr]);
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                try { $this->db->rollBack(); } catch (Throwable $re) {}
            }
            GameLog::error('tick', 'marine_delivery_forwardToPort FAILED — rolled back', $e, [
                'delivery_id' => $deliveryId, 'port_id' => $portId,
            ]);
            return;
        }

        $this->queuedDeliveries++;

        GameLog::info('tick', 'marine_delivery_queued_at_port', [
            'delivery_id' => $deliveryId, 'port_id' => $portId,
            'player_id'   => $playerId,   'vol_bbl' => round($volumeBbl, 3),
        ]);
    }
}
