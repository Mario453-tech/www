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
 * Usuwa dwie kategorie smieci / Removes two categories of junk:
 *  1. dostawy zakonczone (delivered/lost) starsze niz 7 dni — balast historii,
 *     finished deliveries (delivered/lost) older than 7 days — history bloat;
 *  2. utkniete rejsy (departing/in_transit/delayed) ktore wyruszyly ponad 2 dni
 *     temu i nigdy sie nie rozwiazaly (brak portu w regionie) — te stany nie maja
 *     wpisu w port_queue, wiec usuniecie jest bezpieczne (brak osieroconych rekordow).
 *     stuck voyages that departed over 2 days ago and never resolved (no port for
 *     the region); these states have no port_queue row, so deletion is safe.
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
            $stmt = $db->prepare(
                "DELETE FROM marine_deliveries
                  WHERE status IN ('departing','in_transit','delayed')
                    AND departure_at < NOW() - INTERVAL 2 DAY"
            );
            $stmt->execute();
            $stuck = $stmt->rowCount();

 // 3) Dostawy 'delayed' bez przypisanego portu (port_id IS NULL) starsze niz 2 godziny.
 //    Sa definitywnie nieodwracalne: przy tworzeniu nie bylo portu, przy przybyciu
 //    tez nie bylo (findPort zwrocil null) — nigdy nie dotrza do magazynu.
 //    Delayed deliveries with no port assigned (port_id IS NULL) older than 2 hours.
 //    These are definitively undeliverable: no port at creation, no port at arrival
 //    (findPort returned null) — they will never reach storage.
            $stmt = $db->prepare(
                "DELETE FROM marine_deliveries
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
        if ($status === 'departing') {
            $this->db->prepare(
                "UPDATE marine_deliveries SET status = 'in_transit' WHERE id = ?"
            )->execute([$id]);
            $status = 'in_transit';
        }

 // Zdarzenie losowe w tranzycie / Random transit event
 // Szansa bazowa 4% * deltaHours, zmniejszana przez HSE
 // Base 4% chance * deltaHours, reduced by HSE bonus
        $incidentChance = 0.04 * $deltaHours * (float)($hseBonus['catastrophe_mult'] ?? 1.0);
        if (mt_rand(1, 100000) <= (int)($incidentChance * 100000)) {
            $this->applyIncident($id, $volumeBbl, $delivery['player_id']);
            return;
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
                  WHERE id = ?"
            )->execute([$id]);
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
            $this->db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'lost', incident_type = 'piracy', delivered_at = NOW()
                  WHERE id = ?"
            )->execute([$deliveryId]);
            $this->lostBbl += $volumeBbl;
            $this->lostDeliveries++;
            GameLog::warn('tick', 'marine_delivery_lost_piracy', [
                'delivery_id' => $deliveryId, 'player_id' => $playerId, 'vol_bbl' => $volumeBbl,
            ]);

        } elseif ($roll <= 15) {
 // Katastrofa caly ladunek utracony / Catastrophe entire cargo lost
            $this->db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'lost', incident_type = 'catastrophe', delivered_at = NOW()
                  WHERE id = ?"
            )->execute([$deliveryId]);
            $this->lostBbl += $volumeBbl;
            $this->lostDeliveries++;
            GameLog::warn('tick', 'marine_delivery_lost_catastrophe', [
                'delivery_id' => $deliveryId, 'player_id' => $playerId, 'vol_bbl' => $volumeBbl,
            ]);

        } elseif ($roll <= 40) {
 // Sztorm opoznienie o 2 godziny / Storm 2 hour delay
            $this->db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'delayed', incident_type = 'storm',
                        delay_ticks = delay_ticks + 1,
                        eta_at = DATE_ADD(eta_at, INTERVAL 2 HOUR)
                  WHERE id = ?"
            )->execute([$deliveryId]);
            $this->delayedDeliveries++;
            GameLog::info('tick', 'marine_delivery_delayed_storm', [
                'delivery_id' => $deliveryId, 'player_id' => $playerId,
            ]);

        } else {
 // Awaria silnika opoznienie o 1 godzine / Engine breakdown 1 hour delay
            $this->db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'delayed', incident_type = 'breakdown',
                        delay_ticks = delay_ticks + 1,
                        eta_at = DATE_ADD(eta_at, INTERVAL 1 HOUR)
                  WHERE id = ?"
            )->execute([$deliveryId]);
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
            if ($row) return (int)$row['id'];
        }

 // Fallback: dowolny aktywny port / Fallback: any active port
        $row = $this->db->query(
            "SELECT id FROM ports WHERE status = 'active' ORDER BY RAND() LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['id'] : null;
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
 // Sprawdz pojemnosc kolejki / Check queue capacity
        $limitStmt = $this->db->prepare("SELECT queue_limit FROM ports WHERE id = ?");
        $limitStmt->execute([$portId]);
        $portRow    = $limitStmt->fetch(PDO::FETCH_ASSOC);
        $queueLimit = $portRow ? (int)$portRow['queue_limit'] : 20;

        $sizeStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM port_queue WHERE port_id = ? AND status IN ('waiting','processing')"
        );
        $sizeStmt->execute([$portId]);
        $queueSize = (int)$sizeStmt->fetchColumn();

        if ($queueSize >= $queueLimit) {
 // Kolejka pelna opoznienie o 1 godzine / Queue full 1 hour delay
            $this->db->prepare(
                "UPDATE marine_deliveries
                    SET status = 'waiting_for_port',
                        port_id = ?,
                        arrived_at = ?,
                        delay_ticks = delay_ticks + 1,
                        eta_at = DATE_ADD(eta_at, INTERVAL 1 HOUR)
                  WHERE id = ?"
            )->execute([$portId, $nowStr, $deliveryId]);
            $this->delayedDeliveries++;
            GameLog::info('tick', 'marine_delivery_port_queue_full', [
                'delivery_id' => $deliveryId, 'port_id' => $portId, 'queue_size' => $queueSize,
            ]);
            return;
        }

 // Dodaj do kolejki portowej / Add to port queue
        $this->db->prepare(
            "UPDATE marine_deliveries
                SET status = 'waiting_for_port', port_id = ?, arrived_at = ?
              WHERE id = ?"
        )->execute([$portId, $nowStr, $deliveryId]);

        $this->db->prepare(
            "INSERT INTO port_queue (port_id, delivery_id, player_id, volume_bbl, queued_at, status)
             VALUES (?, ?, ?, ?, ?, 'waiting')
             ON DUPLICATE KEY UPDATE status = 'waiting', queued_at = VALUES(queued_at)"
        )->execute([$portId, $deliveryId, $playerId, round($volumeBbl, 4), $nowStr]);

        $this->queuedDeliveries++;

        GameLog::info('tick', 'marine_delivery_queued_at_port', [
            'delivery_id' => $deliveryId, 'port_id' => $portId,
            'player_id'   => $playerId,   'vol_bbl' => round($volumeBbl, 3),
        ]);
    }
}
