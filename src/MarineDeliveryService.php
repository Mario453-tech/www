<?php
declare(strict_types=1);

/**
 * MarineDeliveryService tworzenie i zarzadzanie dostawami morskimi.
 * MarineDeliveryService creation and management of marine deliveries.
 *
 * Dostawa morska to osobny obiekt: ropa jest w drodze i trafia do magazynu
 * dopiero po przetworzeniu przez port (przez PortSection w ticku).
 *
 * A marine delivery is a standalone object: oil is in transit and credited
 * to storage only after the port processes it (via PortSection in the tick).
 *
 * Tabele / Tables: marine_deliveries, port_queue
 */
class MarineDeliveryService
{
    private PDO         $db;
    private PortService $portService;

 /** Bazowy czas tranzytu w godzinach (konfigurowalny) / Base transit time in hours (configurable) */
    private const BASE_TRANSIT_HOURS = 3.0;

 /** Wariancja czasu tranzytu (losowe +/- X godzin) / Transit time variance (random +/- X hours) */
    private const TRANSIT_VARIANCE_HOURS = 1.0;

    public function __construct(PDO $db)
    {
        $this->db          = $db;
        $this->portService = new PortService($db);
        $this->ensureSchema();
    }

 /**
 * Czy region odwiertu ma aktywny port? (bramka produkcji morskiej).
 * Does the well's region have an active port? (marine production gate).
 *
 * Odwiert tankowcowy wysyla rope dopiero gdy istnieje port w jego regionie.
 * A tanker well ships oil only once a port exists in its region.
 */
    public function regionHasPort(int $regionId): bool
    {
        return $this->portService->hasActivePortForRegion($regionId);
    }

 // 
 // Schema
 // 

    /** @var array<int,bool> strażnik per połączenie (raz na proces, ale ponownie dla nowego PDO w testach) */
    private static array $schemaEnsured = [];

    public function ensureSchema(): void
    {
        $schemaConnId = spl_object_id($this->db);
        if (isset(self::$schemaEnsured[$schemaConnId])) {
            return;
        }
        self::$schemaEnsured[$schemaConnId] = true;

        // Dodaj kolumne bufora do wells jesli nie istnieje (potrzebna rowniez w CLI / cron).
        // Add buffer column to wells if missing (needed in CLI / cron context too).
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            try {
                Database::addColumnIfMissing(
                    'wells',
                    'marine_buffer_bbl',
                    "DECIMAL(12,4) NOT NULL DEFAULT 0.0000 COMMENT 'Bufor ropy tankowca / Tanker oil buffer'"
                );
            } catch (Throwable $e) {
                GameLog::error('MarineDeliveryService', 'ensureSchema marine_buffer_bbl FAILED', $e);
            }
        }

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS marine_deliveries (
                id            BIGINT        NOT NULL AUTO_INCREMENT PRIMARY KEY,
                player_id     INT           NOT NULL,
                well_id       INT           NOT NULL,
                port_id       INT           NULL DEFAULT NULL,
                volume_bbl    DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                status        ENUM('departing','in_transit','waiting_for_port','processing','delivered','delayed','lost')
                                            NOT NULL DEFAULT 'departing',
                departure_at  DATETIME      NOT NULL,
                eta_at        DATETIME      NOT NULL,
                arrived_at    DATETIME      NULL DEFAULT NULL,
                delivered_at  DATETIME      NULL DEFAULT NULL,
                delay_ticks   SMALLINT      NOT NULL DEFAULT 0,
                incident_type VARCHAR(64)   NULL DEFAULT NULL,
                handling_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_marine_player (player_id),
                KEY idx_marine_well   (well_id),
                KEY idx_marine_port   (port_id),
                KEY idx_marine_status (status),
                KEY idx_marine_eta    (eta_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS port_queue (
                id                    BIGINT   NOT NULL AUTO_INCREMENT PRIMARY KEY,
                port_id               INT      NOT NULL,
                delivery_id           BIGINT   NOT NULL,
                player_id             INT      NOT NULL,
                volume_bbl            DECIMAL(12,4) NOT NULL,
                queued_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                processing_started_at DATETIME NULL DEFAULT NULL,
                processed_at          DATETIME NULL DEFAULT NULL,
                status                ENUM('waiting','processing','done','abandoned') NOT NULL DEFAULT 'waiting',
                UNIQUE KEY uq_port_queue_delivery (delivery_id),
                KEY idx_port_queue_port   (port_id),
                KEY idx_port_queue_player (player_id),
                KEY idx_port_queue_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

 // 
 // Create
 // 

 /**
 * Tworzy nowa dostawe morska (ropa wyruszyla z odwiertu).
 * Creates a new marine delivery (oil departing from well).
 *
 * @param array<string, mixed> $well pelny rekord odwiertu / full well record
 * @param array<string, mixed> $hseBonus aktywne bonusy BHP / active HSE bonuses
 * @return int ID nowej dostawy / new delivery ID
 */
    public function createDelivery(
        int   $playerId,
        int   $wellId,
        float $volumeBbl,
        float $deltaHours,
        array $well,
        array $hseBonus
    ): int {
        $now      = new DateTime();
        $nowStr   = $now->format('Y-m-d H:i:s');
        $regionId = (int)($well['region_id'] ?? 0);

 // Czas tranzytu: baza +/- wariancja / Transit time: base +/- variance
        $transitHours = self::BASE_TRANSIT_HOURS
            + (mt_rand(-100, 100) / 100.0) * self::TRANSIT_VARIANCE_HOURS;
        $transitHours = max(1.0, $transitHours);

        $eta = clone $now;
        $eta->modify('+' . (int)round($transitHours * 3600) . ' seconds');
        $etaStr = $eta->format('Y-m-d H:i:s');

 // Znajdz port docelowy dla regionu / Find destination port for region
        $port   = $this->portService->findForRegion($regionId);
        $portId = $port ? (int)$port['id'] : null;

        $stmt = $this->db->prepare(
            "INSERT INTO marine_deliveries
                (player_id, well_id, port_id, volume_bbl, status,
                 departure_at, eta_at, created_at)
             VALUES (?, ?, ?, ?, 'departing', ?, ?, ?)"
        );
        $stmt->execute([$playerId, $wellId, $portId, round($volumeBbl, 4), $nowStr, $etaStr, $nowStr]);

        $deliveryId = (int)$this->db->lastInsertId();

        GameLog::info('tick', 'marine_delivery_created', [
            'delivery_id' => $deliveryId,
            'player_id'   => $playerId,
            'well_id'     => $wellId,
            'port_id'     => $portId,
            'vol_bbl'     => round($volumeBbl, 3),
            'eta_hours'   => round($transitHours, 2),
        ]);

        return $deliveryId;
    }

 // 
 // Queries for player panel
 // 

 /**
 * Zwraca aktywne dostawy gracza (w drodze + w kolejce portowej).
 * Returns active deliveries for a player (in transit + in port queue).
 *
 * @return list<array<string, mixed>>
 */
    public function getActiveForPlayer(int $playerId, int $limit = 50): array
    {
        try {
            $limit = max(1, min(500, $limit));
            $stmt = $this->db->prepare(
                "SELECT md.*, p.name AS port_name, w.name AS well_name
                   FROM marine_deliveries md
                   LEFT JOIN ports p ON p.id = md.port_id
                   LEFT JOIN wells w ON w.id = md.well_id
                  WHERE md.player_id = ?
                    AND (
                           md.status IN ('departing','in_transit','waiting_for_port','processing')
                        OR (md.status = 'delayed' AND md.departure_at >= NOW() - INTERVAL 2 DAY)
                    )
                  ORDER BY CASE md.status
                               WHEN 'departing' THEN 1
                               WHEN 'in_transit' THEN 2
                               WHEN 'delayed' THEN 3
                               WHEN 'waiting_for_port' THEN 4
                               WHEN 'processing' THEN 5
                               ELSE 9
                           END,
                           md.eta_at ASC,
                           md.id ASC
                  LIMIT {$limit}"
            );
            $stmt->execute([$playerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            GameLog::error('marine', 'getActiveForPlayer FAILED', $e, ['player_id' => $playerId]);
            return [];
        }
    }

 /**
 * Historia dostaw gracza (ostatnie 20 dostarczonych/utraconych).
 * Player delivery history (last 20 delivered/lost).
 *
 * @return list<array<string, mixed>>
 */
    public function getHistoryForPlayer(int $playerId, int $limit = 20): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT md.*,
                        p.name AS port_name,
                        COALESCE(w.name, w.location_name, CONCAT('Odwiert #', md.well_id)) AS well_name
                   FROM marine_deliveries md
                   LEFT JOIN ports p ON p.id = md.port_id
                   LEFT JOIN wells w ON w.id = md.well_id
                  WHERE md.player_id = ?
                    AND md.status IN ('delivered','lost')
                  ORDER BY COALESCE(md.delivered_at, md.arrived_at, md.eta_at, md.created_at) DESC
                  LIMIT ?"
            );
 // LIMIT musi byc zbindowany jako INT PDO z execute([]) binduje string (cudzyslowy) co MySQL odrzuca
            $stmt->bindValue(1, $playerId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit,    PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            GameLog::error('marine', 'getHistoryForPlayer FAILED', $e, ['player_id' => $playerId]);
            return [];
        }
    }

    /**
     * Zwraca bufory tankowcow przy odwiertach gracza.
     * Returns tanker buffers accumulated at the player's wells.
     *
     * @return list<array<string, mixed>>
     */
    public function getBufferedForPlayer(int $playerId, float $minLoadBbl): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT w.id AS well_id,
                        COALESCE(w.name, w.location_name, CONCAT('Odwiert #', w.id)) AS well_name,
                        w.status,
                        w.marine_buffer_bbl,
                        ? AS min_load_bbl
                   FROM wells w
                  WHERE w.player_id = ?
                    AND w.transport_type = 'tankowiec'
                    AND w.status NOT IN ('seized','blowout','sold')
                    AND COALESCE(w.marine_buffer_bbl, 0) > 0
                  ORDER BY w.marine_buffer_bbl DESC, w.id ASC"
            );
            $stmt->bindValue(1, $minLoadBbl, PDO::PARAM_STR);
            $stmt->bindValue(2, $playerId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            GameLog::error('marine', 'getBufferedForPlayer FAILED', $e, ['player_id' => $playerId]);
            return [];
        }
    }

 /**
 * Ile bbl jest aktualnie w drodze lub w buforze przy odwiercie dla gracza.
 * How many bbl are currently in transit OR buffered at the well for a player.
 *
 * Bufor (marine_buffer_bbl) zawiera rope zgromadzona przy odwiercie ktora jeszcze nie
 * wyruszyla — jest dla gracza niewidoczna dopoki nie zostanie wliczona do sumy.
 * Buffer (marine_buffer_bbl) holds oil accumulated at the well that hasn't departed yet —
 * invisible to the player unless included in the total.
 */
    public function getInTransitBbl(int $playerId): float
    {
        $total = 0.0;
        try {
 // Dostawy aktualnie w tranzycie / Deliveries currently in transit
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(volume_bbl), 0)
                   FROM marine_deliveries
                  WHERE player_id = ?
                    AND (
                           status IN ('departing','in_transit','waiting_for_port','processing')
                        OR (status = 'delayed' AND departure_at >= NOW() - INTERVAL 2 DAY)
                    )"
            );
            $stmt->execute([$playerId]);
            $total += (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
 // Bufor przy odwiertach tankowcowych (jeszcze nie wyruszyl) / Tanker well buffers (not yet departed)
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(marine_buffer_bbl), 0)
                   FROM wells
                  WHERE player_id = ?
                    AND transport_type = 'tankowiec'
                    AND status NOT IN ('seized','blowout','sold')"
            );
            $stmt->execute([$playerId]);
            $total += (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
 // Kolumna moze nie istniec na starym deploymencie / Column may not exist on old deployment
        }
        return $total;
    }

 /**
 * Awaryjne dane panelu logistyki, bez konstruktora serwisu.
 * Fallback logistics panel data, without constructing the service.
 *
 * @return array{
 *   deliveries: list<array<string, mixed>>,
 *   buffers: list<array<string, mixed>>,
 *   history: list<array<string, mixed>>,
 *   in_transit_bbl: float
 * }
 */
    public static function loadPanelFallback(PDO $db, int $playerId, float $minLoadBbl, int $activeLimit = 50, int $historyLimit = 10): array
    {
        $activeLimit = max(1, min(500, $activeLimit));
        $historyLimit = max(1, min(500, $historyLimit));
        $data = [
            'deliveries'     => [],
            'buffers'        => [],
            'history'        => [],
            'in_transit_bbl' => 0.0,
        ];

        try {
            $stmt = $db->prepare(
                "SELECT md.*,
                        p.name AS port_name,
                        COALESCE(w.name, w.location_name, CONCAT('Odwiert #', md.well_id)) AS well_name
                   FROM marine_deliveries md
                   LEFT JOIN ports p ON p.id = md.port_id
                   LEFT JOIN wells w ON w.id = md.well_id
                  WHERE md.player_id = ?
                    AND (
                           md.status IN ('departing','in_transit','waiting_for_port','processing')
                        OR (md.status = 'delayed' AND md.departure_at >= NOW() - INTERVAL 2 DAY)
                    )
                  ORDER BY CASE md.status
                               WHEN 'departing' THEN 1
                               WHEN 'in_transit' THEN 2
                               WHEN 'delayed' THEN 3
                               WHEN 'waiting_for_port' THEN 4
                               WHEN 'processing' THEN 5
                               ELSE 9
                           END,
                           md.eta_at ASC,
                           md.id ASC
                  LIMIT {$activeLimit}"
            );
            $stmt->execute([$playerId]);
            $data['deliveries'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            GameLog::error('marine', 'loadPanelFallback active deliveries FAILED', $e, ['player_id' => $playerId]);
        }

        try {
            $stmt = $db->prepare(
                "SELECT w.id AS well_id,
                        COALESCE(w.name, w.location_name, CONCAT('Odwiert #', w.id)) AS well_name,
                        w.status,
                        w.marine_buffer_bbl,
                        ? AS min_load_bbl
                   FROM wells w
                  WHERE w.player_id = ?
                    AND w.transport_type = 'tankowiec'
                    AND w.status NOT IN ('seized','blowout','sold')
                    AND COALESCE(w.marine_buffer_bbl, 0) > 0
                  ORDER BY w.marine_buffer_bbl DESC, w.id ASC"
            );
            $stmt->bindValue(1, $minLoadBbl, PDO::PARAM_STR);
            $stmt->bindValue(2, $playerId, PDO::PARAM_INT);
            $stmt->execute();
            $data['buffers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            GameLog::error('marine', 'loadPanelFallback buffers FAILED', $e, ['player_id' => $playerId]);
        }

        try {
            $stmt = $db->prepare(
                "SELECT md.*,
                        p.name AS port_name,
                        COALESCE(w.name, w.location_name, CONCAT('Odwiert #', md.well_id)) AS well_name
                   FROM marine_deliveries md
                   LEFT JOIN ports p ON p.id = md.port_id
                   LEFT JOIN wells w ON w.id = md.well_id
                  WHERE md.player_id = ?
                    AND md.status IN ('delivered','lost')
                  ORDER BY COALESCE(md.delivered_at, md.arrived_at, md.eta_at, md.created_at) DESC
                  LIMIT {$historyLimit}"
            );
            $stmt->bindValue(1, $playerId, PDO::PARAM_INT);
            $stmt->execute();
            $data['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            GameLog::error('marine', 'loadPanelFallback history FAILED', $e, ['player_id' => $playerId]);
        }

        try {
            $stmt = $db->prepare(
                "SELECT
                    (SELECT COALESCE(SUM(volume_bbl), 0)
                       FROM marine_deliveries
                      WHERE player_id = ?
                        AND (
                               status IN ('departing','in_transit','waiting_for_port','processing')
                            OR (status = 'delayed' AND departure_at >= NOW() - INTERVAL 2 DAY)
                        ))
                    +
                    (SELECT COALESCE(SUM(marine_buffer_bbl), 0)
                       FROM wells
                      WHERE player_id = ?
                        AND transport_type = 'tankowiec'
                        AND status NOT IN ('seized','blowout','sold')) AS total_bbl"
            );
            $stmt->execute([$playerId, $playerId]);
            $data['in_transit_bbl'] = (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            GameLog::error('marine', 'loadPanelFallback totals FAILED', $e, ['player_id' => $playerId]);
        }

        return $data;
    }
}
