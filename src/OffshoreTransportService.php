<?php
declare(strict_types=1);

/**
 * OffshoreTransportService morski transport ropy jako system rejsw tankowcw.
 * OffshoreTransportService offshore oil transport as a tanker voyage system.
 *
 * Kady rejs jest osobn jednostk ryzyka.
 * Each voyage is an independent unit of risk.
 * Piractwo i awaria = cay rejs stracony.
 * Piracy and breakdown = the entire voyage is lost.
 * Sztorm i opnienie = strata czciowa.
 * Storm and delay = partial loss.
 *
 * Tabele / Tables:
 * well_offshore_configs konfiguracja per odwiert (typ tankowca, pojemno rejsu, koszt)
 * config per well (tanker type, voyage capacity, cost)
 * well_offshore_incident_logs log incydentw per tick / incident log per tick
 */
class OffshoreTransportService
{
    private PDO $db;

 /** Bazowe prawdopodobiestwo incydentu na rejs na godzin / Base incident probability per voyage per hour */
    private const BASE_INCIDENT_CHANCE_PER_HOUR = 0.012;

 /** Wagi typw incydentw przy losowaniu / Incident type weights for random selection */
    private const INCIDENT_WEIGHTS = [
        'storm'     => 4,
        'breakdown' => 2,
        'delay'     => 5,
        'piracy'    => 1,
    ];

 /** Parametry domylne typw tankowcw / Default parameters for tanker types */
    private const TANKER_DEFAULTS = [
        'small'  => ['shipment_capacity_bbl' =>  30.0, 'cost_per_shipment' =>  800.0, 'incident_risk_mult' => 1.000],
        'medium' => ['shipment_capacity_bbl' =>  75.0, 'cost_per_shipment' => 1800.0, 'incident_risk_mult' => 0.850],
        'large'  => ['shipment_capacity_bbl' => 150.0, 'cost_per_shipment' => 3200.0, 'incident_risk_mult' => 0.650],
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
                "CREATE TABLE IF NOT EXISTS well_offshore_configs (
                    id                     INTEGER PRIMARY KEY AUTOINCREMENT,
                    player_id              INTEGER NOT NULL,
                    well_id                INTEGER NOT NULL UNIQUE,
                    tanker_type            TEXT    NOT NULL DEFAULT 'small',
                    shipment_capacity_bbl  REAL    NOT NULL DEFAULT 30.0,
                    cost_per_shipment      REAL    NOT NULL DEFAULT 800.0,
                    incident_risk_mult     REAL    NOT NULL DEFAULT 1.0,
                    created_at             TEXT,
                    updated_at             TEXT
                )"
            );
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS well_offshore_incident_logs (
                    id             INTEGER PRIMARY KEY AUTOINCREMENT,
                    well_id        INTEGER NOT NULL,
                    player_id      INTEGER NOT NULL,
                    incident_type  TEXT    NOT NULL,
                    shipments_total  INTEGER NOT NULL DEFAULT 0,
                    shipments_lost   INTEGER NOT NULL DEFAULT 0,
                    vol_lost_bbl     REAL    NOT NULL DEFAULT 0.0,
                    created_at     TEXT
                )"
            );
        } else {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS well_offshore_configs (
                    id                     INT           NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    player_id              INT           NOT NULL,
                    well_id                INT           NOT NULL,
                    tanker_type            ENUM('small','medium','large') NOT NULL DEFAULT 'small',
                    shipment_capacity_bbl  DECIMAL(10,2) NOT NULL DEFAULT 30.00,
                    cost_per_shipment      DECIMAL(10,2) NOT NULL DEFAULT 800.00,
                    incident_risk_mult     DECIMAL(6,3)  NOT NULL DEFAULT 1.000,
                    created_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_offshore_cfg_well  (well_id),
                    KEY        idx_offshore_cfg_player (player_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS well_offshore_incident_logs (
                    id              INT                                               NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    well_id         INT                                               NOT NULL,
                    player_id       INT                                               NOT NULL,
                    incident_type   ENUM('storm','breakdown','delay','piracy')        NOT NULL,
                    shipments_total SMALLINT UNSIGNED                                 NOT NULL DEFAULT 0,
                    shipments_lost  SMALLINT UNSIGNED                                 NOT NULL DEFAULT 0,
                    vol_lost_bbl    DECIMAL(12,4)                                     NOT NULL DEFAULT 0.0000,
                    created_at      DATETIME                                          NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_offshore_inc_well    (well_id),
                    KEY idx_offshore_inc_player  (player_id),
                    KEY idx_offshore_inc_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

 // Configuration management

 /**
 * Tworzy domyln konfiguracj dla odwiertw offshore, ktre jej jeszcze nie maj.
 * Creates default configuration for offshore wells that do not have one yet.
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
                "INSERT OR IGNORE INTO well_offshore_configs
                    (player_id, well_id, tanker_type, shipment_capacity_bbl, cost_per_shipment, incident_risk_mult)
                 VALUES (?, ?, 'small', 30.0, 800.0, 1.0)"
            );
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO well_offshore_configs
                    (player_id, well_id, tanker_type, shipment_capacity_bbl, cost_per_shipment, incident_risk_mult)
                 SELECT ?, ?, 'small', 30.00, 800.00, 1.000
                 FROM DUAL
                 WHERE NOT EXISTS (SELECT 1 FROM well_offshore_configs WHERE well_id = ?)"
            );
        }

        foreach ($wells as $well) {
            if ((string)($well['transport_type'] ?? '') !== 'tankowiec') {
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
            "SELECT * FROM well_offshore_configs
              WHERE player_id = ? AND well_id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$playerId], $wellIds));

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[(int)$row['well_id']] = $row;
        }
        return $rows;
    }

 // Tick voyage logic

 /**
 * Przetwarza transport morski dla jednego odwiertu w ticku.
 * Processes maritime transport for one well in a tick.
 *
 * Kady rejs jest niezalen jednostk ryzyka.
 * Each voyage is an independent unit of risk.
 * Piractwo i awaria = cay rejs stracony. Sztorm i opnienie = strata czciowa.
 * Piracy and breakdown = entire voyage lost. Storm and delay = partial loss.
 * Opata za rejs naliczana od wszystkich wysanych rejsw, nie tylko dostarczonych.
 * Voyage fee charged for all dispatched voyages, not just delivered ones.
 *
 * @param array<string, mixed>|null $config wiersz z well_offshore_configs lub null (-> defaults)
 * @param array<string, mixed> $hseBonus
 * @return array{
 * shipments_total: int,
 * shipments_delivered: int,
 * shipments_lost: int,
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

        $shipmentCap     = max(0.01, (float)(($config['shipment_capacity_bbl'] ?? null) ?? 30.0));
        $costPerShipment = (float)(($config['cost_per_shipment']        ?? null) ?? 800.0);
        $riskMult        = (float)(($config['incident_risk_mult']       ?? null) ?? 1.000);

        $shipmentsTotal = max(1, (int)ceil($inputBbl / $shipmentCap));
        $volPerShipment = $inputBbl / $shipmentsTotal;

 // Szansa incydentu per rejs (skalowana przez czas, ryzyko polityczne i BHP)
        $politicalScale = match (true) {
            $politicalRiskLevel >= 4 => 2.5,
            $politicalRiskLevel >= 3 => 1.8,
            $politicalRiskLevel >= 2 => 1.3,
            default                  => 1.0,
        };
        $hseScale       = (float)($hseBonus['failure_reduction'] ?? 1.0);
        $incidentChance = min(
            0.95,
            self::BASE_INCIDENT_CHANCE_PER_HOUR * $riskMult * $politicalScale * $hseScale * $deltaHours
        );

        $deliveredBbl    = 0.0;
        $lostBbl         = 0.0;
        $shipmentsLost   = 0;
        $incidents       = [];

        $totalWeight = (int)array_sum(self::INCIDENT_WEIGHTS);
        $threshold   = (int)($incidentChance * 1_000_000);

        for ($i = 0; $i < $shipmentsTotal; $i++) {
            if (mt_rand(1, 1_000_000) > $threshold) {
 // Rejs dostarczony bez incydentu
                $deliveredBbl += $volPerShipment;
                continue;
            }

 // Losowanie typu incydentu
            $type = $this->rollIncidentType($totalWeight);
            $loss = $this->computeShipmentLoss($type, $volPerShipment);
            $loss = min($volPerShipment, round($loss, 4));

            $deliveredBbl += max(0.0, $volPerShipment - $loss);
            $lostBbl      += $loss;

            if ($loss >= $volPerShipment * 0.99) {
                $shipmentsLost++;
            }

            $incidents[] = [
                'type'          => $type,
                'shipment_idx'  => $i,
                'lost_bbl'      => $loss,
            ];
        }

        $deliveredBbl = round($deliveredBbl, 4);
        $lostBbl      = round($lostBbl, 4);
        $cost         = round($shipmentsTotal * $costPerShipment, 2);

        if ($incidents !== []) {
            $this->logIncidents($wellId, $playerId, $shipmentsTotal, $lostBbl, $incidents);
        }

        return [
            'shipments_total'     => $shipmentsTotal,
            'shipments_delivered' => $shipmentsTotal - $shipmentsLost,
            'shipments_lost'      => $shipmentsLost,
            'delivered_bbl'       => $deliveredBbl,
            'lost_bbl'            => $lostBbl,
            'cost'                => $cost,
            'incidents'           => $incidents,
        ];
    }

 // 
 // Helpery prywatne
 // 

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
        return 'storm';
    }

    private function computeShipmentLoss(string $type, float $volPerShipment): float
    {
        return match ($type) {
            'piracy', 'breakdown'   => $volPerShipment,
            'storm'                 => $volPerShipment * (0.20 + mt_rand(0, 400) / 1000.0),
            'delay'                 => $volPerShipment * (0.10 + mt_rand(0, 200) / 1000.0),
        };
    }

 /**
 * @param list<array<string, mixed>> $incidents
 */
    private function logIncidents(
        int   $wellId,
        int   $playerId,
        int   $shipmentsTotal,
        float $totalLostBbl,
        array $incidents
    ): void {
        try {
 // Grupuj per typ jeden wiersz logu per typ incydentu per tick
            $byType = [];
            foreach ($incidents as $inc) {
                $t = $inc['type'];
                if (!isset($byType[$t])) {
                    $byType[$t] = ['shipments_lost' => 0, 'vol_lost_bbl' => 0.0];
                }
 // Liczymy rejs jako stracony gdy strata >= 99% pojemnoci / count voyage as lost when loss >= 99% capacity
                if ($inc['lost_bbl'] >= ($inc['lost_bbl'] + 0.001)) {
 // partial count volume only
                }
                $byType[$t]['shipments_lost']++;
                $byType[$t]['vol_lost_bbl'] += $inc['lost_bbl'];
            }

            $stmt = $this->db->prepare(
                "INSERT INTO well_offshore_incident_logs
                    (well_id, player_id, incident_type, shipments_total, shipments_lost, vol_lost_bbl)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );

            foreach ($byType as $type => $data) {
                $stmt->execute([
                    $wellId,
                    $playerId,
                    $type,
                    $shipmentsTotal,
                    $data['shipments_lost'],
                    round($data['vol_lost_bbl'], 4),
                ]);
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('OffshoreTransportService', 'logIncidents FAILED', $e, [
                    'well_id'   => $wellId,
                    'player_id' => $playerId,
                ]);
            }
        }
    }

 /**
 * @return array{shipments_total:int, shipments_delivered:int, shipments_lost:int, delivered_bbl:float, lost_bbl:float, cost:float, incidents:list<array<string,mixed>>}
 */
    private function emptyResult(): array
    {
        return [
            'shipments_total'     => 0,
            'shipments_delivered' => 0,
            'shipments_lost'      => 0,
            'delivered_bbl'       => 0.0,
            'lost_bbl'            => 0.0,
            'cost'                => 0.0,
            'incidents'           => [],
        ];
    }
}
