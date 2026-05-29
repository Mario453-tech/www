<?php
declare(strict_types=1);

/**
 * PortService — zarzadzanie systemowymi portami morskimi.
 * PortService — management of system-owned maritime ports.
 *
 * Porty naleza do systemu (nie do graczy).
 * Ports belong to the system (not to players).
 *
 * Tabela / Table: ports
 */
class PortService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureSchema();
    }

    // 
    // Schema
    // 

    public function ensureSchema(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS ports (
                id                    INT             NOT NULL AUTO_INCREMENT PRIMARY KEY,
                region_id             INT             NOT NULL,
                name                  VARCHAR(120)    NOT NULL,
                port_type             ENUM('small','medium','large') NOT NULL DEFAULT 'medium',
                throughput_per_tick   DECIMAL(12,2)   NOT NULL DEFAULT 500.00,
                queue_limit           INT             NOT NULL DEFAULT 20,
                handling_cost_per_bbl DECIMAL(8,4)    NOT NULL DEFAULT 0.50,
                base_transit_hours    DECIMAL(6,2)    NOT NULL DEFAULT 3.00,
                overload_risk_pct     DECIMAL(5,2)    NOT NULL DEFAULT 15.00,
                failure_risk_per_tick DECIMAL(8,6)    NOT NULL DEFAULT 0.001000,
                status                ENUM('active','overloaded','damaged','closed') NOT NULL DEFAULT 'active',
                created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_ports_region (region_id),
                KEY idx_ports_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // 
    // Queries
    // 

    /**
     * Znajdz aktywny port dla regionu odwiertu.
     * Find an active port for a well's region.
     * Preferuje port aktywny nad przeciazonym, blizszy regionowi.
     * Prefers active over overloaded, closer to the region.
     *
     * @return array<string, mixed>|null
     */
    public function findForRegion(int $regionId): ?array
    {
        // Szukaj portu w tym samym regionie / Look for port in the same region
        $stmt = $this->db->prepare(
            "SELECT * FROM ports
              WHERE region_id = ?
                AND status IN ('active','overloaded')
              ORDER BY status = 'active' DESC, id ASC
              LIMIT 1"
        );
        $stmt->execute([$regionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;

        // Fallback: dowolny aktywny port / Fallback: any active port
        $row = $this->db->query(
            "SELECT * FROM ports WHERE status = 'active' ORDER BY RAND() LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getById(int $portId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ports WHERE id = ?");
        $stmt->execute([$portId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Zlicz oczekujace dostawy w kolejce portu.
     * Count deliveries waiting in the port queue.
     */
    public function getQueueSize(int $portId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM port_queue WHERE port_id = ? AND status IN ('waiting','processing')"
        );
        $stmt->execute([$portId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Zaktualizuj status portu na podstawie rozmiaru kolejki.
     * Update port status based on queue size.
     */
    public function refreshStatuses(): void
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
                        WHEN COALESCE(q.cnt, 0) >= p.queue_limit * 0.8 AND p.status = 'active'    THEN 'overloaded'
                        WHEN COALESCE(q.cnt, 0) <  p.queue_limit * 0.8 AND p.status = 'overloaded' THEN 'active'
                        ELSE p.status
                   END,
                   p.updated_at = NOW()
                 WHERE p.status IN ('active','overloaded')"
            );
        } catch (Throwable $e) {
            GameLog::error('tick', 'PortService::refreshStatuses FAILED', $e);
        }
    }

    // 
    // Admin seed — tworzy domyslne porty dla kazdego regionu
    // Admin seed — creates default ports for every region
    // 

    /**
     * Tworzy po 1 porcie na region jesli nie istnieja.
     * Creates 1 port per region if none exist.
     *
     * @return int liczba utworzonych portow / number of ports created
     */
    public function seedDefaultPorts(): int
    {
        $created = 0;
        try {
            $regions = $this->db->query(
                "SELECT id, name FROM world_regions ORDER BY id"
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($regions as $region) {
                $regionId = (int)$region['id'];

                // Sprawdz czy port juz istnieje / Check if port already exists
                $stmt = $this->db->prepare(
                    "SELECT id FROM ports WHERE region_id = ? LIMIT 1"
                );
                $stmt->execute([$regionId]);
                if ($stmt->fetch()) continue;

                $name = 'Port ' . $region['name'];
                $this->db->prepare(
                    "INSERT INTO ports
                        (region_id, name, port_type, throughput_per_tick, queue_limit,
                         handling_cost_per_bbl, base_transit_hours, overload_risk_pct,
                         failure_risk_per_tick, status)
                     VALUES (?, ?, 'medium', 600.00, 25, 0.50, 3.00, 15.00, 0.001000, 'active')"
                )->execute([$regionId, $name]);
                $created++;
            }
        } catch (Throwable $e) {
            GameLog::error('admin', 'PortService::seedDefaultPorts FAILED', $e);
        }
        return $created;
    }

    /**
     * Zwraca wszystkie porty z liczba oczekujacych dostaw.
     * Returns all ports with waiting delivery count.
     *
     * @return list<array<string, mixed>>
     */
    public function getAllWithQueueStats(): array
    {
        try {
            return $this->db->query(
                "SELECT p.*,
                        COALESCE(q.waiting, 0)    AS queue_waiting,
                        COALESCE(q.processing, 0) AS queue_processing,
                        r.name                    AS region_name
                   FROM ports p
                   LEFT JOIN world_regions r ON r.id = p.region_id
                   LEFT JOIN (
                       SELECT port_id,
                              SUM(status = 'waiting')    AS waiting,
                              SUM(status = 'processing') AS processing
                         FROM port_queue
                        GROUP BY port_id
                   ) q ON q.port_id = p.id
                  ORDER BY p.region_id, p.id"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            GameLog::error('admin', 'PortService::getAllWithQueueStats FAILED', $e);
            return [];
        }
    }
}
