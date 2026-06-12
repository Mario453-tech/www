<?php
declare(strict_types=1);

/**
 * RoadTransportService transport drogowy jako system kursw.
 * RoadTransportService road transport as a trip system.
 *
 * Kady kurs jest osobn jednostk ryzyka.
 * Each trip is an independent unit of risk.
 * Kradzie i napad = cay kurs stracony (nie procent wolumenu).
 * Theft and raid = the entire trip is lost (not a percentage of volume).
 * Strata nie jest obcinana procentowo kady kurs wypada niezalenie.
 * Loss is not clipped as a percentage each trip fails independently.
 *
 * Tabele / Tables:
 * well_road_configs konfiguracja per odwiert (typ ciarwki, pojemno kursu, koszt)
 * config per well (truck type, trip capacity, cost)
 * well_road_incident_logs log incydentw kursw per tick / trip incident log per tick
 */
class RoadTransportService
{
    private PDO $db;

 /** Bazowe prawdopodobiestwo incydentu na kurs na godzin / Base incident probability per trip per hour */
    private const BASE_INCIDENT_CHANCE_PER_HOUR = 0.015;

 /** Wagi typw incydentw przy losowaniu / Incident type weights for random selection */
    private const INCIDENT_WEIGHTS = [
        'theft'       => 3,
        'raid'        => 2,
        'accident'    => 4,
        'sabotage'    => 1,
        'route_block' => 2,
    ];

 /**
 * Mapowanie kluczy efektow ochrony (ProtectionService) na typy incydentow.
 * Typy accident i route_block celowo bez ochrony (awaria/korki).
 * Maps protection effect keys (ProtectionService) to incident types.
 * accident and route_block intentionally unprotected (breakdown/road blocks).
 */
    private const PROTECTION_EFFECT_TO_TYPE = [
        'theft_risk_mult'    => 'theft',
        'raid_risk_mult'     => 'raid',
        'sabotage_risk_mult' => 'sabotage',
    ];

 /** Parametry domylne typw ciarwek / Default parameters for truck types */
    private const TRUCK_DEFAULTS = [
        'standard' => ['trip_capacity_bbl' => 25.0, 'cost_per_trip' =>  500.0, 'incident_risk_mult' => 1.000, 'trip_hours' => 2],
        'heavy'    => ['trip_capacity_bbl' => 50.0, 'cost_per_trip' =>  900.0, 'incident_risk_mult' => 0.800, 'trip_hours' => 3],
        'armored'  => ['trip_capacity_bbl' => 20.0, 'cost_per_trip' => 1500.0, 'incident_risk_mult' => 0.300, 'trip_hours' => 2],
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->ensureSchema();
    }

 // 
 // Schemat
 // 

    /** @var array<int,bool> strażnik per połączenie (raz na proces, ale ponownie dla nowego PDO w testach) */
    private static array $schemaEnsured = [];

    private function ensureSchema(): void
    {
        $schemaConnId = spl_object_id($this->db);
        if (isset(self::$schemaEnsured[$schemaConnId])) {
            return;
        }
        self::$schemaEnsured[$schemaConnId] = true;

        $isSqlite = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        if ($isSqlite) {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS well_road_configs (
                    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
                    player_id          INTEGER NOT NULL,
                    well_id            INTEGER NOT NULL UNIQUE,
                    truck_type         TEXT    NOT NULL DEFAULT 'standard',
                    trip_capacity_bbl  REAL    NOT NULL DEFAULT 25.0,
                    cost_per_trip      REAL    NOT NULL DEFAULT 500.0,
                    incident_risk_mult REAL    NOT NULL DEFAULT 1.0,
                    created_at         TEXT,
                    updated_at         TEXT
                )"
            );
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS well_road_incident_logs (
                    id            INTEGER PRIMARY KEY AUTOINCREMENT,
                    well_id       INTEGER NOT NULL,
                    player_id     INTEGER NOT NULL,
                    incident_type TEXT    NOT NULL,
                    trips_total   INTEGER NOT NULL DEFAULT 0,
                    trips_lost    INTEGER NOT NULL DEFAULT 0,
                    vol_lost_bbl  REAL    NOT NULL DEFAULT 0.0,
                    created_at    TEXT
                )"
            );
        } else {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS well_road_configs (
                    id                  INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    player_id           INT          NOT NULL,
                    well_id             INT          NOT NULL,
                    truck_type          ENUM('standard','heavy','armored') NOT NULL DEFAULT 'standard',
                    trip_capacity_bbl   DECIMAL(10,2) NOT NULL DEFAULT 25.00,
                    cost_per_trip       DECIMAL(10,2) NOT NULL DEFAULT 500.00,
                    incident_risk_mult  DECIMAL(6,3)  NOT NULL DEFAULT 1.000,
                    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_road_cfg_well (well_id),
                    KEY idx_road_cfg_player (player_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS well_road_incident_logs (
                    id            INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    well_id       INT          NOT NULL,
                    player_id     INT          NOT NULL,
                    incident_type ENUM('theft','raid','accident','sabotage','route_block') NOT NULL,
                    trips_total   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    trips_lost    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                    vol_lost_bbl  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_road_inc_well    (well_id),
                    KEY idx_road_inc_player  (player_id),
                    KEY idx_road_inc_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
 // Tabela kursow drogowych w czasie (P1.2) / Time-based road trips table (P1.2)
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS well_road_trips (
                    id                   INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    player_id            INT           NOT NULL,
                    well_id              INT           NOT NULL,
                    volume_bbl           DECIMAL(12,4) NOT NULL,
                    delivered_bbl        DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
                    truck_type           ENUM('standard','heavy','armored') NOT NULL DEFAULT 'standard',
                    trips_count          SMALLINT UNSIGNED NOT NULL DEFAULT 1,
                    trip_hours           TINYINT UNSIGNED NOT NULL DEFAULT 2,
                    cost                 DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    incident_risk_mult   DECIMAL(6,3)  NOT NULL DEFAULT 1.000,
                    political_risk_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
                    status               ENUM('in_transit','delivered','lost') NOT NULL DEFAULT 'in_transit',
                    departure_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    eta_at               DATETIME NOT NULL,
                    arrived_at           DATETIME NULL DEFAULT NULL,
                    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_road_trips_player (player_id),
                    KEY idx_road_trips_well   (well_id),
                    KEY idx_road_trips_eta    (eta_at),
                    KEY idx_road_trips_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
 // Migracja brakujacych kolumn (istniejace tabele z wczesniejszego schematu)
 // Column migration (existing tables from earlier schema version)
            $this->ensureRoadTripColumn('delivered_bbl',        'DECIMAL(12,4) NOT NULL DEFAULT 0.0000 AFTER volume_bbl');
            $this->ensureRoadTripColumn('trips_count',          'SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER truck_type');
            $this->ensureRoadTripColumn('trip_hours',           'TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER trips_count');
            $this->ensureRoadTripColumn('incident_risk_mult',   'DECIMAL(6,3) NOT NULL DEFAULT 1.000 AFTER cost');
            $this->ensureRoadTripColumn('political_risk_level', 'TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER incident_risk_mult');
        }
    }

 // Configuration management

 /**
 * Tworzy domyln konfiguracj dla odwiertw ciezarowki, ktre jej jeszcze nie maj.
 * Creates default configuration for truck-type wells that do not have one yet.
 *
 * @param list<array<string, mixed>> $wells
 */
    public function ensureConfigsForPlayerWells(int $playerId, array $wells): void
    {
        if ($wells === []) {
            return;
        }

        $isSqlite = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';

        if ($isSqlite) {
            $stmt = $this->db->prepare(
                "INSERT OR IGNORE INTO well_road_configs
                    (player_id, well_id, truck_type, trip_capacity_bbl, cost_per_trip, incident_risk_mult)
                 VALUES (?, ?, 'standard', 25.0, 500.0, 1.0)"
            );
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO well_road_configs
                    (player_id, well_id, truck_type, trip_capacity_bbl, cost_per_trip, incident_risk_mult)
                 SELECT ?, ?, 'standard', 25.00, 500.00, 1.000
                 FROM DUAL
                 WHERE NOT EXISTS (SELECT 1 FROM well_road_configs WHERE well_id = ?)"
            );
        }

        foreach ($wells as $well) {
            if ((string)($well['transport_type'] ?? '') !== 'ciezarowki') {
                continue;
            }
            $wellId = (int)($well['id'] ?? 0);
            if ($wellId <= 0) {
                continue;
            }
            $isSqlite ? $stmt->execute([$playerId, $wellId]) : $stmt->execute([$playerId, $wellId, $wellId]);
        }
    }

 /**
 * @param list<int> $wellIds
 * @return array<int, array<string, mixed>> indexed by well_id
 */
    public function getConfigsByWellIds(int $playerId, array $wellIds): array
    {
        $wellIds = array_values(array_unique(array_map('intval', $wellIds)));
        if ($wellIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($wellIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM well_road_configs
              WHERE player_id = ? AND well_id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$playerId], $wellIds));

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[(int)$row['well_id']] = $row;
        }
        return $rows;
    }

 // Time-based trip dispatch and completion (MySQL only)

 /**
 * MySQL only. Tworzy rekord kursu w well_road_trips; olej jest w tranzycie.
 * MySQL only. Creates a trip record in well_road_trips; oil is in transit.
 *
 * @param array<string, mixed>|null $config wiersz well_road_configs lub null / well_road_configs row or null
 * @return array{trips_count:int, volume_bbl:float, cost:float, truck_type:string, eta_at:string}
 */
    public function dispatchTrips(
        int    $playerId,
        int    $wellId,
        float  $volumeBbl,
        ?array $config,
        int    $politicalRiskLevel = 1
    ): array {
        if ($volumeBbl <= 0.0) {
            return ['trips_count' => 0, 'volume_bbl' => 0.0, 'cost' => 0.0, 'truck_type' => 'standard', 'eta_at' => ''];
        }

        $truckType = (string)($config['truck_type'] ?? 'standard');
        if (!isset(self::TRUCK_DEFAULTS[$truckType])) {
            $truckType = 'standard';
        }
        $defaults    = self::TRUCK_DEFAULTS[$truckType];
        $tripCap     = max(0.01, (float)($config['trip_capacity_bbl'] ?? $defaults['trip_capacity_bbl']));
        $costPerTrip = (float)($config['cost_per_trip']      ?? $defaults['cost_per_trip']);
        $riskMult    = (float)($config['incident_risk_mult'] ?? $defaults['incident_risk_mult']);
        $tripHours   = (int)$defaults['trip_hours'];

        $tripsCount = max(1, (int)ceil($volumeBbl / $tripCap));
        $cost       = round($tripsCount * $costPerTrip, 2);

 // Uzyj NOW() MySQL zamiast PHP DateTime zeby uniknac roznic stref czasowych.
 // Use MySQL NOW() instead of PHP DateTime to avoid timezone mismatches.
        $etaStmt = $this->db->prepare("SELECT DATE_ADD(NOW(), INTERVAL ? HOUR) AS eta_at");
        $etaStmt->execute([$tripHours]);
        $etaAt = (string)$etaStmt->fetchColumn();

        $this->db->prepare(
            "INSERT INTO well_road_trips
                (player_id, well_id, volume_bbl, truck_type, trips_count, trip_hours,
                 cost, incident_risk_mult, political_risk_level, status, eta_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'in_transit', ?)"
        )->execute([
            $playerId, $wellId, round($volumeBbl, 4), $truckType, $tripsCount, $tripHours,
            $cost, $riskMult, $politicalRiskLevel, $etaAt,
        ]);

        return [
            'trips_count' => $tripsCount,
            'volume_bbl'  => $volumeBbl,
            'cost'        => $cost,
            'truck_type'  => $truckType,
            'eta_at'      => $etaAt,
        ];
    }

 /**
 * Przetwarza ukonczone kursy (eta_at <= NOW()). Stosuje incydenty, aktualizuje rekordy.
 * Processes completed trips (eta_at <= NOW()). Applies incidents, updates records.
 *
 * Ochrona (ProtectionService) dziala per odwiert: mnozniki theft/raid/sabotage
 * nakladane na wagi incydentow przy rozliczaniu kursu.
 * Protection (ProtectionService) works per well: theft/raid/sabotage multipliers
 * applied to incident weights when the trip is resolved.
 *
 * @param array<string, mixed> $hseBonus
 * @return array{delivered_bbl:float, lost_bbl:float, completed_count:int, delivered_by_well:array<int,float>}
 */
    public function processCompletedTrips(int $playerId, array $hseBonus, ?ProtectionService $protection = null): array
    {
        $totalDelivered = 0.0;
        $totalLost      = 0.0;
        $completed      = 0;
 /** @var array<int, float> well_id => delivered bbl (basis for the second transport leg) */
        $deliveredByWell = [];

        try {
            $stmt = $this->db->prepare(
                "SELECT id, well_id, volume_bbl, trips_count, trip_hours,
                        incident_risk_mult, political_risk_level
                   FROM well_road_trips
                  WHERE player_id = ? AND status = 'in_transit' AND eta_at <= NOW()
                  ORDER BY eta_at ASC"
            );
            $stmt->execute([$playerId]);
            $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('RoadTransportService', 'processCompletedTrips fetch FAILED', $e, ['player_id' => $playerId]);
            }
            return ['delivered_bbl' => 0.0, 'lost_bbl' => 0.0, 'completed_count' => 0, 'delivered_by_well' => []];
        }

 /** @var array<int, array<string,mixed>|null> well_id => aktywna ochrona / active protection */
        $protByWell = [];

        foreach ($trips as $trip) {
            $wid = (int)$trip['well_id'];

            if ($protection !== null && !array_key_exists($wid, $protByWell)) {
                $protByWell[$wid] = $protection->getActiveProtection(
                    $playerId, 'road_transport', $wid, 'road_transport_guard'
                );
            }
            $activeProt = $protByWell[$wid] ?? null;
            $protMults = [];
            if ($activeProt !== null) {
                foreach (self::PROTECTION_EFFECT_TO_TYPE as $effectKey => $incidentType) {
                    $eff = $activeProt['effects'][$effectKey] ?? null;
                    if ($eff !== null && $eff['type'] === 'mult') {
                        $protMults[$incidentType] = (float)$eff['value'];
                    }
                }
            }

            [$delivered, $lost, $incidents] = $this->applyTripIncidents(
                (float)$trip['volume_bbl'],
                (int)$trip['trips_count'],
                (float)$trip['incident_risk_mult'],
                (int)$trip['political_risk_level'],
                (int)$trip['trip_hours'],
                $hseBonus,
                $protMults
            );

            if ($protection !== null && $activeProt !== null && $incidents !== []) {
                $types = array_values(array_unique(array_column($incidents, 'type')));
                $protection->logEvent(
                    $playerId, (int)$activeProt['protection_option_id'],
                    'road_transport', $wid, 'road_transport_guard',
                    'protection_applied_to_incident', round($lost, 4),
                    implode(',', $types),
                    ['summary' => $this->summarizeProtectionIncidents($incidents)]
                );
            }

            try {
                $this->db->prepare(
                    "UPDATE well_road_trips
                        SET status = 'delivered', delivered_bbl = ?, arrived_at = NOW()
                      WHERE id = ?"
                )->execute([round($delivered, 4), (int)$trip['id']]);

                if ($incidents !== [] && $lost > 0.0) {
                    $this->logIncidents(
                        (int)$trip['well_id'], $playerId,
                        (int)$trip['trips_count'], $lost, $incidents
                    );
                }
            } catch (Throwable $e) {
                if (class_exists('GameLog', false)) {
                    GameLog::error('RoadTransportService', 'processCompletedTrips update FAILED', $e, ['trip_id' => $trip['id']]);
                }
            }

            $totalDelivered += $delivered;
            $totalLost      += $lost;
            $completed++;

            $deliveredByWell[$wid] = ($deliveredByWell[$wid] ?? 0.0) + $delivered;
        }

        return [
            'delivered_bbl'   => round($totalDelivered, 4),
            'lost_bbl'        => round($totalLost, 4),
            'completed_count' => $completed,
            'delivered_by_well' => $deliveredByWell,
        ];
    }

 /**
 * Zwraca aktywne kursy gracza (status=in_transit) z danymi odwiertu i pozostalym czasem.
 * Returns active trips for a player (status=in_transit) with well data and time remaining.
 *
 * @return list<array<string, mixed>>
 */
    public function getActiveTripsForPlayer(int $playerId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT rt.id, rt.well_id, rt.volume_bbl, rt.truck_type, rt.trips_count,
                        rt.cost, rt.status, rt.departure_at, rt.eta_at,
                        GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), rt.eta_at)) AS seconds_remaining,
                        COALESCE(NULLIF(w.name,''), w.location_name, CONCAT('Odwiert #', rt.well_id)) AS well_name
                   FROM well_road_trips rt
                   LEFT JOIN wells w ON w.id = rt.well_id
                  WHERE rt.player_id = ? AND rt.status = 'in_transit'
                  ORDER BY rt.eta_at ASC"
            );
            $stmt->execute([$playerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('RoadTransportService', 'getActiveTripsForPlayer FAILED', $e, ['player_id' => $playerId]);
            }
            return [];
        }
    }

 // Tick trip logic (stateless model - used for SQLite / legacy)

 /**
 * Przetwarza transport kursowy dla jednego odwiertu w ticku.
 * Processes road transport for one well in a tick.
 *
 * Kady kurs jest niezalen jednostk ryzyka. Kradzie/napad = cay kurs stracony.
 * Each trip is an independent unit of risk. Theft/raid = entire trip lost.
 * Opata za kurs liczy si od wszystkich kursw wysanych, nie tylko dostarczonych.
 * Trip fee is charged for all dispatched trips, not just delivered ones.
 *
 * @param array<string, mixed>|null $config wiersz z well_road_configs lub null (-> defaults)
 * @param array<string, mixed> $hseBonus
 * @return array{
 * trips_total: int,
 * trips_delivered: int,
 * trips_lost: int,
 * delivered_bbl: float,
 * lost_bbl: float,
 * cost: float,
 * incidents: list<array<string, mixed>>
 * }
 */
    public function processTick(
        int    $playerId,
        int    $wellId,
        float  $inputBbl,
        float  $deltaHours,
        ?array $config,
        array  $hseBonus,
        int    $politicalRiskLevel = 1
    ): array {
        if ($inputBbl <= 0.0 || $deltaHours <= 0.0) {
            return $this->emptyResult();
        }

        $tripCap     = max(0.01, (float)(($config['trip_capacity_bbl'] ?? null) ?? 25.0));
        $costPerTrip = (float)(($config['cost_per_trip']        ?? null) ?? 500.0);
        $riskMult    = (float)(($config['incident_risk_mult']   ?? null) ?? 1.000);

        $tripsTotal  = max(1, (int)ceil($inputBbl / $tripCap));
        $volPerTrip  = $inputBbl / $tripsTotal;

 // Szansa incydentu per kurs (skalowana przez czas, ryzyko polityczne i BHP)
        $politicalScale = match (true) {
            $politicalRiskLevel >= 4 => 2.0,
            $politicalRiskLevel >= 3 => 1.5,
            $politicalRiskLevel >= 2 => 1.2,
            default                  => 1.0,
        };
        $hseScale       = (float)($hseBonus['failure_reduction'] ?? 1.0);
        $incidentChance = min(
            0.95,
            self::BASE_INCIDENT_CHANCE_PER_HOUR * $riskMult * $politicalScale * $hseScale * $deltaHours
        );

        $deliveredBbl = 0.0;
        $lostBbl      = 0.0;
        $tripsLost    = 0;
        $incidents    = [];

        $totalWeight = (int)array_sum(self::INCIDENT_WEIGHTS);
        $threshold   = (int)($incidentChance * 1_000_000);

        for ($i = 0; $i < $tripsTotal; $i++) {
            if (mt_rand(1, 1_000_000) > $threshold) {
 // Kurs dostarczony bez incydentu
                $deliveredBbl += $volPerTrip;
                continue;
            }

 // Losowanie typu incydentu
            $type  = $this->rollIncidentType($totalWeight);
            $loss  = $this->computeTripLoss($type, $volPerTrip);
            $loss  = min($volPerTrip, round($loss, 4));

            $deliveredBbl += max(0.0, $volPerTrip - $loss);
            $lostBbl      += $loss;
            $tripsLost++;
            $incidents[] = [
                'type'     => $type,
                'trip_idx' => $i,
                'lost_bbl' => $loss,
            ];
        }

        $deliveredBbl = round($deliveredBbl, 4);
        $lostBbl      = round($lostBbl, 4);
        $cost         = round($tripsTotal * $costPerTrip, 2);

        if ($incidents !== []) {
            $this->logIncidents($wellId, $playerId, $tripsTotal, $lostBbl, $incidents);
        }

        return [
            'trips_total'     => $tripsTotal,
            'trips_delivered' => $tripsTotal - $tripsLost,
            'trips_lost'      => $tripsLost,
            'delivered_bbl'   => $deliveredBbl,
            'lost_bbl'        => $lostBbl,
            'cost'            => $cost,
            'incidents'       => $incidents,
        ];
    }

 //
 // Helpery prywatne
 //

 /**
 * Dodaje kolumne do well_road_trips jesli nie istnieje (migracja).
 * Adds column to well_road_trips if it does not exist (migration).
 */
    private function ensureRoadTripColumn(string $column, string $definition): void
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'well_road_trips'
                    AND COLUMN_NAME  = ?"
            );
            $stmt->execute([$column]);
            if ((int)$stmt->fetchColumn() > 0) {
                return;
            }
            $this->db->exec("ALTER TABLE well_road_trips ADD COLUMN {$column} {$definition}");
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('RoadTransportService', 'ensureRoadTripColumn FAILED', $e, ['column' => $column]);
            }
        }
    }

 /**
 * Stosuje incydenty per-kurs dla paczki kursow. Zwraca [delivered, lost, incidents].
 * Applies per-trip incidents for a batch of trips. Returns [delivered, lost, incidents].
 *
 * Mnozniki ochrony dzialaja na wagach typow: w'_i = w_i * mult_i, a laczna szansa
 * jest skalowana przez sum(w')/sum(w) - typy niechronione (accident, route_block)
 * zachowuja dokladnie swoje bazowe prawdopodobienstwa.
 * Protection multipliers act on type weights: w'_i = w_i * mult_i, and the total
 * chance is scaled by sum(w')/sum(w) - unprotected types (accident, route_block)
 * keep exactly their base probabilities.
 *
 * @param array<string, mixed> $hseBonus
 * @param array<string, float> $protMults typ incydentu => mnoznik / incident type => multiplier
 * @return array{float, float, list<array<string, mixed>>}
 */
    private function applyTripIncidents(
        float $totalVolume,
        int   $tripsCount,
        float $riskMult,
        int   $politicalRiskLevel,
        int   $tripHours,
        array $hseBonus,
        array $protMults = []
    ): array {
        if ($totalVolume <= 0.0 || $tripsCount <= 0) {
            return [0.0, 0.0, []];
        }

        $volPerTrip     = $totalVolume / $tripsCount;
        $politicalScale = match (true) {
            $politicalRiskLevel >= 4 => 2.0,
            $politicalRiskLevel >= 3 => 1.5,
            $politicalRiskLevel >= 2 => 1.2,
            default                  => 1.0,
        };
        $hseScale       = (float)($hseBonus['failure_reduction'] ?? 1.0);
        $incidentChance = min(
            0.95,
            self::BASE_INCIDENT_CHANCE_PER_HOUR * $riskMult * $politicalScale * $hseScale * $tripHours
        );

        $weights = array_map('floatval', self::INCIDENT_WEIGHTS);
        if ($protMults !== []) {
            foreach ($protMults as $type => $mult) {
                if (isset($weights[$type])) {
                    $weights[$type] *= max(0.0, min(1.0, $mult));
                }
            }
            $baseWeight = (float)array_sum(self::INCIDENT_WEIGHTS);
            $incidentChance *= array_sum($weights) / $baseWeight;
        }

        $deliveredBbl = 0.0;
        $lostBbl      = 0.0;
        $incidents    = [];
        $threshold    = (int)($incidentChance * 1_000_000);

        for ($i = 0; $i < $tripsCount; $i++) {
            if (mt_rand(1, 1_000_000) > $threshold) {
                $deliveredBbl += $volPerTrip;
                continue;
            }
            $type  = $this->rollIncidentTypeWeighted($weights);
            $loss  = min($volPerTrip, round($this->computeTripLoss($type, $volPerTrip), 4));
            $deliveredBbl += max(0.0, $volPerTrip - $loss);
            $lostBbl      += $loss;
            $incidents[]   = ['type' => $type, 'trip_idx' => $i, 'lost_bbl' => $loss];
        }

        return [round($deliveredBbl, 4), round($lostBbl, 4), $incidents];
    }

 /**
 * Losuje typ incydentu z wag zmiennoprzecinkowych (po nalozeniu ochrony).
 * Rolls the incident type from float weights (after protection is applied).
 *
 * @param array<string, float> $weights
 */
    private function rollIncidentTypeWeighted(array $weights): string
    {
        $total = array_sum($weights);
        if ($total <= 0.0) {
            return 'accident';
        }
        $roll  = mt_rand(1, 1_000_000) / 1_000_000 * $total;
        $cumul = 0.0;
        foreach ($weights as $type => $weight) {
            $cumul += $weight;
            if ($roll <= $cumul) {
                return $type;
            }
        }
        return 'accident';
    }

    /**
     * Agreguje incydenty do lekkiego meta_json, zamiast zapisywac kazdy kurs.
     * Aggregates incidents into a lightweight meta_json instead of storing every trip.
     *
     * @param list<array<string, mixed>> $incidents
     * @return array<string, mixed>
     */
    private function summarizeProtectionIncidents(array $incidents): array
    {
        $byType = [];
        $lostTotal = 0.0;
        foreach ($incidents as $incident) {
            $type = (string)($incident['type'] ?? 'unknown');
            if (!isset($byType[$type])) {
                $byType[$type] = ['count' => 0, 'lost_bbl' => 0.0];
            }
            $loss = (float)($incident['lost_bbl'] ?? 0.0);
            $byType[$type]['count']++;
            $byType[$type]['lost_bbl'] += $loss;
            $lostTotal += $loss;
        }

        foreach ($byType as $type => $row) {
            $byType[$type]['lost_bbl'] = round((float)$row['lost_bbl'], 4);
        }

        return [
            'total_incidents' => count($incidents),
            'lost_bbl' => round($lostTotal, 4),
            'types' => $byType,
        ];
    }

    private function rollIncidentType(int $totalWeight): string
    {
        $roll  = mt_rand(1, $totalWeight);
        $cumul = 0;
        foreach (self::INCIDENT_WEIGHTS as $type => $weight) {
            $cumul += $weight;
            if ($roll <= $cumul) {
                return $type;
            }
        }
        return 'accident';
    }

    private function computeTripLoss(string $type, float $volPerTrip): float
    {
        return match ($type) {
            'theft', 'raid', 'sabotage', 'route_block' => $volPerTrip,
            'accident'                                  => $volPerTrip * (0.5 + mt_rand(0, 500) / 1000.0),
        };
    }

 /**
 * @param list<array<string, mixed>> $incidents
 */
    private function logIncidents(
        int   $wellId,
        int   $playerId,
        int   $tripsTotal,
        float $totalLostBbl,
        array $incidents
    ): void {
        try {
 // Grupuj per typ jeden wiersz logu per typ incydentu per tick
            $byType = [];
            foreach ($incidents as $inc) {
                $t = $inc['type'];
                if (!isset($byType[$t])) {
                    $byType[$t] = ['trips_lost' => 0, 'vol_lost_bbl' => 0.0];
                }
                $byType[$t]['trips_lost']++;
                $byType[$t]['vol_lost_bbl'] += $inc['lost_bbl'];
            }

            $stmt = $this->db->prepare(
                "INSERT INTO well_road_incident_logs
                    (well_id, player_id, incident_type, trips_total, trips_lost, vol_lost_bbl)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            foreach ($byType as $type => $data) {
                $stmt->execute([
                    $wellId,
                    $playerId,
                    $type,
                    $tripsTotal,
                    $data['trips_lost'],
                    round($data['vol_lost_bbl'], 4),
                ]);
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('RoadTransportService', 'logIncidents FAILED', $e, [
                    'well_id'   => $wellId,
                    'player_id' => $playerId,
                ]);
            }
        }
    }

 /**
 * @return array{trips_total:int, trips_delivered:int, trips_lost:int, delivered_bbl:float, lost_bbl:float, cost:float, incidents:list<array<string,mixed>>}
 */
    private function emptyResult(): array
    {
        return [
            'trips_total'     => 0,
            'trips_delivered' => 0,
            'trips_lost'      => 0,
            'delivered_bbl'   => 0.0,
            'lost_bbl'        => 0.0,
            'cost'            => 0.0,
            'incidents'       => [],
        ];
    }
}
