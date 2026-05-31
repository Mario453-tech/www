<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

abstract class MySqlIntegrationTestCase extends TestCase
{
    protected PDO $db;
    protected int $seed;

    protected function setUp(): void
    {
        parent::setUp();

        $cfg = require dirname(__DIR__, 2) . '/config/database.php';
        $dsn = 'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=' . $cfg['charset'];

        $this->db = new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->seed = random_int(900000000, 900999900);
        $this->cleanupTrackedIds();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];

        GameLog::setEnabled(false);

        require_once dirname(__DIR__, 2) . '/src/WellPipelineService.php';
        new WellPipelineService($this->db);
    }

    protected function tearDown(): void
    {
        $this->cleanupTrackedIds();

        parent::tearDown();
    }

    protected function cleanupTrackedIds(): void
    {
        $ids = $this->getTrackedIds();

        $this->deleteByIds('finance_logs', 'player_id', [$ids['playerId']]);
        $this->deleteByIds('technical_notifications', 'player_id', [$ids['playerId']]);
        $this->deleteByIds('technical_task_queue', 'player_id', [$ids['playerId']]);
        $this->deleteByIds('technical_tasks', 'player_id', [$ids['playerId']]);
        $this->deleteByIds('logistics_hub_events', 'player_id', [$ids['playerId']]);
        $this->deleteByIds('logistics_hub_tick_stats', 'hub_id', [$ids['hubId'], $ids['auxHubId']]);
        $this->deleteByIds('logistics_hub_assignments', 'well_id', [$ids['wellId'], $ids['auxWellId']]);
        $this->deleteByIds('well_offshore_incident_logs', 'well_id', [$ids['wellId'], $ids['auxWellId']]);
        $this->deleteByIds('well_offshore_configs', 'well_id', [$ids['wellId'], $ids['auxWellId']]);
        $this->deleteByIds('well_road_incident_logs', 'well_id', [$ids['wellId'], $ids['auxWellId']]);
        $this->deleteByIds('well_road_trips', 'well_id', [$ids['wellId'], $ids['auxWellId']]);
        $this->deleteByIds('well_road_configs', 'well_id', [$ids['wellId'], $ids['auxWellId']]);
        $this->deleteByIds('well_pipelines', 'well_id', [$ids['wellId'], $ids['auxWellId']]);
        $this->deleteByIds('pipelines', 'player_id', [$ids['playerId']]);
        $this->deleteByIds('logistics_hubs', 'id', [$ids['hubId'], $ids['auxHubId']]);
        $this->deleteByIds('wells', 'id', [$ids['wellId'], $ids['auxWellId']]);
        $this->deleteByIds('technical_staff', 'player_id', [$ids['playerId']]);
        $this->deleteByIds('board_members', 'id', [$ids['managerId']]);
        $this->deleteByIds('board_roles', 'id', [$ids['roleId']]);
        $this->deleteByIds('players', 'id', [$ids['playerId']]);
    }

 /**
 * @return array{playerId:int,wellId:int,auxWellId:int,hubId:int,auxHubId:int,staffId:int,managerId:int,roleId:int}
 */
    protected function getTrackedIds(): array
    {
        return [
            'playerId' => $this->seed,
            'wellId' => $this->seed + 1,
            'auxWellId' => $this->seed + 2,
            'hubId' => $this->seed + 3,
            'auxHubId' => $this->seed + 4,
            'staffId' => $this->seed + 5,
            'managerId' => $this->seed + 6,
            'roleId' => $this->seed + 7,
        ];
    }

    protected function seedPlayer(): int
    {
        $id = $this->getTrackedIds()['playerId'];
        $username = 'phpunit_mysql_' . $id;
        $email = $username . '@example.test';

        $stmt = $this->db->prepare(
            'INSERT INTO players (id, username, email, password_hash, cash, status, created_at, last_tick_at, safety_procedures_level, procedure_integrity)
             VALUES (?, ?, ?, ?, 50000000.00, \'active\', NOW(), NOW(), 0, 100)'
        );
        $stmt->execute([$id, $username, $email, password_hash('secret', PASSWORD_BCRYPT)]);

        return $id;
    }

    protected function seedWell(
        int $playerId,
        int $wellId,
        string $status = 'active',
        int $regionId = 77,
        string $zoneKey = 'A1',
        string $transportType = 'rurociag',
        float $capacityPct = 120.0,
        float $baseProductionPerHour = 37.5
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO wells (id, player_id, status, created_at, region_id, zone_key, location_name, name, transport_type, transport_capacity_pct, base_production_per_hour)
             VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $wellId,
            $playerId,
            $status,
            $regionId,
            $zoneKey,
            'PHPUnit Pole ' . $wellId,
            'PHPUnit Well ' . $wellId,
            $transportType,
            $capacityPct,
            $baseProductionPerHour,
        ]);
    }

    protected function seedHub(
        int $hubId,
        string $name,
        int $regionId = 77,
        string $zoneKey = 'A1',
        float $condition = 90.0,
        string $status = 'active',
        string $acquisitionType = 'new',
        string $workMode = 'standard',
        float $bufferCurrent = 0.0,
        ?int $playerId = null
    ): void {
        $ownerId = $playerId ?? $this->getTrackedIds()['playerId'];
        $stmt = $this->db->prepare(
            'INSERT INTO logistics_hubs (id, player_id, region_id, zone_key, name, hub_type, acquisition_type, status, work_mode, slot_limit, condition_pct, initial_condition_pct, nominal_capacity_bph, real_capacity_bph, buffer_capacity_bbl, buffer_current_bbl, opex_per_tick, lease_fee_per_tick, build_cost, repair_cost_estimate)
             VALUES (?, ?, ?, ?, ?, \'medium\', ?, ?, ?, 4, ?, ?, 200.00, 200.00, 500.00, ?, 100.00, 0.00, 100000.00, 200000.00)'
        );
        $stmt->execute([
            $hubId,
            $ownerId,
            $regionId,
            $zoneKey,
            $name,
            $acquisitionType,
            $status,
            $workMode,
            $condition,
            $condition,
            $bufferCurrent,
        ]);
    }

    protected function seedAssignment(int $hubId, int $wellId, string $status = 'active'): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO logistics_hub_assignments (hub_id, well_id, status, assigned_at, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW(), NOW())'
        );
        $stmt->execute([$hubId, $wellId, $status]);
    }

    protected function seedTechnicalWorker(int $playerId, int $skill = 6): int
    {
        $ids = $this->getTrackedIds();
        $this->ensureTechnicalManagerExists();
        $this->insertTechnicalStaff(
            $ids['staffId'],
            $playerId,
            'maintenance_engineer',
            'In�ynier Utrzymania Ruchu',
            $skill,
            9000
        );

        return $ids['staffId'];
    }

    protected function seedTechnicalStaff(
        int $playerId,
        int $staffId,
        string $specCode,
        string $specName,
        int $skill = 6,
        int $salary = 9000
    ): int {
        $this->ensureTechnicalManagerExists();
        $this->insertTechnicalStaff($staffId, $playerId, $specCode, $specName, $skill, $salary);
        return $staffId;
    }

    protected function setPlayerProcedures(int $playerId, int $level, int $integrity): void
    {
        $stmt = $this->db->prepare(
            'UPDATE players
                SET safety_procedures_level = ?,
                    procedure_integrity = ?,
                    procedures_last_decay_at = NOW()
              WHERE id = ?'
        );
        $stmt->execute([$level, $integrity, $playerId]);
    }

    protected function seedOffshoreConfig(
        int    $playerId,
        int    $wellId,
        string $tankerType          = 'small',
        float  $shipmentCapacityBbl = 30.0,
        float  $costPerShipment     = 800.0,
        float  $incidentRisk        = 1.0
    ): void {
        $this->db->prepare(
            'INSERT INTO well_offshore_configs
                (player_id, well_id, tanker_type, shipment_capacity_bbl, cost_per_shipment, incident_risk_mult)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                tanker_type = VALUES(tanker_type),
                shipment_capacity_bbl = VALUES(shipment_capacity_bbl),
                cost_per_shipment = VALUES(cost_per_shipment),
                incident_risk_mult = VALUES(incident_risk_mult)'
        )->execute([$playerId, $wellId, $tankerType, $shipmentCapacityBbl, $costPerShipment, $incidentRisk]);
    }

    protected function seedRoadConfig(
        int    $playerId,
        int    $wellId,
        string $truckType       = 'standard',
        float  $tripCapacityBbl = 25.0,
        float  $costPerTrip     = 500.0,
        float  $incidentRisk    = 1.0
    ): void {
        $this->db->prepare(
            'INSERT INTO well_road_configs
                (player_id, well_id, truck_type, trip_capacity_bbl, cost_per_trip, incident_risk_mult)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                truck_type = VALUES(truck_type),
                trip_capacity_bbl = VALUES(trip_capacity_bbl),
                cost_per_trip = VALUES(cost_per_trip),
                incident_risk_mult = VALUES(incident_risk_mult)'
        )->execute([$playerId, $wellId, $truckType, $tripCapacityBbl, $costPerTrip, $incidentRisk]);
    }

    protected function seedLegacyPipeline(int $playerId, string $name = 'Legacy Pipeline', float $transportLoss = 2.5): int
    {
        $pipelineId = $this->seed + 30;
        $stmt = $this->db->prepare(
            'INSERT INTO pipelines (id, player_id, name, capacity_bbl_h, condition_pct, status, last_inspected_at, transport_loss, built_at)
             VALUES (?, ?, ?, 120, 100, \'active\', NOW(), ?, NOW())'
        );
        $stmt->execute([$pipelineId, $playerId, $name, $transportLoss]);
        return $pipelineId;
    }

    private function deleteByIds(string $table, string $column, array $ids): void
    {
        $ids = array_values(array_unique(array_filter($ids, static fn($id): bool => is_int($id) || ctype_digit((string)$id))));
        if ($ids === []) {
            return;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE `{$column}` IN ({$placeholders})");
            $stmt->execute($ids);
        } catch (PDOException $e) {
 // Tabela moe nie istnie jeszcze (nowe moduy) pomijamy cicho.
            if (!str_contains($e->getMessage(), '1146') && !str_contains($e->getMessage(), '42S02')) {
                throw $e;
            }
        }
    }

    private function ensureTechnicalManagerExists(): void
    {
        $ids = $this->getTrackedIds();
        $roleStmt = $this->db->prepare("SELECT id FROM board_roles WHERE code = 'technical' LIMIT 1");
        $roleStmt->execute();
        $roleId = $roleStmt->fetchColumn();
        if ($roleId === false) {
            $this->db->prepare(
                "INSERT INTO board_roles (id, code, name, created_at) VALUES (?, 'technical', 'Technical', NOW())"
            )->execute([$ids['roleId']]);
            $roleId = $ids['roleId'];
        }

        $existsStmt = $this->db->prepare('SELECT id FROM board_members WHERE id = ? LIMIT 1');
        $existsStmt->execute([$ids['managerId']]);
        if ($existsStmt->fetchColumn() === false) {
            $this->db->prepare(
                'INSERT INTO board_members
                    (id, role_id, status, first_name, last_name, birth_date, nationality,
                     experience_years, skill_organization, skill_negotiation, skill_analysis,
                     skill_stress, skill_ethics, trait_loyalty, trait_corruption_risk, trait_ambition, salary)
                 VALUES (?, ?, \'active\', \'Jan\', \'MySql\', \'1980-01-01\', \'PL\',
                     10, 5, 5, 5, 5, 5, 5, 5, 5, 10000.00)'
            )->execute([$ids['managerId'], $roleId]);
        }
    }

    private function insertTechnicalStaff(
        int $staffId,
        int $playerId,
        string $specCode,
        string $specName,
        int $skill,
        int $salary
    ): void {
        $managerId = $this->getTrackedIds()['managerId'];

        $this->db->prepare(
            'INSERT INTO technical_staff (id, player_id, manager_id, first_name, last_name, spec_code, specialization, spec_name, skill_level, salary, status)
             VALUES (?, ?, ?, \'Jan\', \'MySql\', ?, ?, ?, ?, ?, \'active\')'
        )->execute([$staffId, $playerId, $managerId, $specCode, $specCode, $specName, $skill, $salary]);
    }
}
