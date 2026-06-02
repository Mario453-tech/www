<?php

declare(strict_types=1);

/**
 * LegalService — Dział prawny P1: zezwolenia na wiercenie per region.
 *
 * Etap 1: warstwa danych + odczyt.
 *  - ensureSchema(): tworzy tabele konfiguracji regionów i wniosków/zezwoleń (MySQL).
 *  - seedRegionConfig(): seeduje konfigurację dla regionów z world_regions
 *    (poziom ryzyka mapowany z political_risk).
 *  - metody odczytu: getRegionConfig, getAllRegionConfigs, getPermitStatus,
 *    hasActivePermit.
 *
 * Zasada nadrzędna: gracz nie może kupić odwiertu w regionie bez aktywnego
 * zezwolenia (granted lub transitional). Bramka jest egzekwowana w WorldMap
 * (etap 2). Gracz nie widzi procentów ryzyka — to parametry admina/ticka.
 */
class LegalService
{
    /** Poziomy ryzyka regionu widziane przez gracza jako prosty opis. */
    public const RISK_LEVELS = ['low', 'medium', 'high', 'critical'];

    /** Statusy wniosku/zezwolenia w P1. */
    public const STATUS_PENDING     = 'pending';      // wniosek w trakcie
    public const STATUS_DELAYED     = 'delayed';      // opóźnienie decyzji (nowy termin)
    public const STATUS_NO_DECISION = 'no_decision';  // brak decyzji
    public const STATUS_GRANTED     = 'granted';      // zezwolenie aktywne
    public const STATUS_REFUSED     = 'refused';      // wniosek odrzucony
    public const STATUS_TRANSITIONAL = 'transitional'; // zezwolenie przejściowe (migracja)

    /** Statusy oznaczające aktywne zezwolenie (odblokowują zakup odwiertów). */
    public const ACTIVE_STATUSES = [self::STATUS_GRANTED, self::STATUS_TRANSITIONAL];

    /** Statusy oznaczające trwającą sprawę (wniosek w toku). */
    public const PENDING_STATUSES = [self::STATUS_PENDING, self::STATUS_DELAYED];

    /** Domyślne parametry konfiguracji per poziom ryzyka (seed; balans w etapie 6). */
    private const RISK_DEFAULTS = [
        'low' => [
            'application_cost' => 100000.00, 'base_review_minutes' => 30,
            'delay_risk_pct' => 5.00,  'delay_min_minutes' => 5,  'delay_max_minutes' => 15,
            'no_decision_risk_pct' => 0.00, 'refusal_risk_pct' => 2.00,
            'refusal_cooldown_minutes' => 60,  'required_capital' => 0.00,
        ],
        'medium' => [
            'application_cost' => 250000.00, 'base_review_minutes' => 60,
            'delay_risk_pct' => 15.00, 'delay_min_minutes' => 10, 'delay_max_minutes' => 30,
            'no_decision_risk_pct' => 3.00, 'refusal_risk_pct' => 8.00,
            'refusal_cooldown_minutes' => 120, 'required_capital' => 0.00,
        ],
        'high' => [
            'application_cost' => 500000.00, 'base_review_minutes' => 90,
            'delay_risk_pct' => 30.00, 'delay_min_minutes' => 15, 'delay_max_minutes' => 45,
            'no_decision_risk_pct' => 10.00, 'refusal_risk_pct' => 15.00,
            'refusal_cooldown_minutes' => 240, 'required_capital' => 5000000.00,
        ],
        'critical' => [
            'application_cost' => 1000000.00, 'base_review_minutes' => 120,
            'delay_risk_pct' => 45.00, 'delay_min_minutes' => 20, 'delay_max_minutes' => 60,
            'no_decision_risk_pct' => 20.00, 'refusal_risk_pct' => 25.00,
            'refusal_cooldown_minutes' => 480, 'required_capital' => 25000000.00,
        ],
    ];

    private PDO $db;

    /** @var array<int,bool> cache zapewnionego schematu per połączenie */
    private static array $schemaEnsured = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->ensureSchema();
    }

    // ----------------------------------------------------------------- Schema

    /**
     * Tworzy tabele działu prawnego (idempotentnie). DDL MySQL — na SQLite
     * (testy) jest no-op, bo testy budują własny schemat.
     */
    public function ensureSchema(): void
    {
        $connId = spl_object_id($this->db);
        if (isset(self::$schemaEnsured[$connId])) {
            return;
        }
        self::$schemaEnsured[$connId] = true;

        if ($this->driver() !== 'mysql') {
            return;
        }

        try {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS legal_region_config (
                    region_id INT UNSIGNED NOT NULL PRIMARY KEY,
                    enabled TINYINT(1) NOT NULL DEFAULT 1,
                    is_offshore TINYINT(1) NOT NULL DEFAULT 0,
                    risk_level ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
                    application_cost DECIMAL(14,2) NOT NULL DEFAULT 100000.00,
                    base_review_minutes INT UNSIGNED NOT NULL DEFAULT 60,
                    delay_risk_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                    delay_min_minutes INT UNSIGNED NOT NULL DEFAULT 10,
                    delay_max_minutes INT UNSIGNED NOT NULL DEFAULT 30,
                    no_decision_risk_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                    refusal_risk_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                    refusal_cooldown_minutes INT UNSIGNED NOT NULL DEFAULT 120,
                    required_capital DECIMAL(20,2) NOT NULL DEFAULT 0.00,
                    required_legal_level INT UNSIGNED NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );

            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS drilling_permit_applications (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    player_id INT UNSIGNED NOT NULL,
                    region_id INT UNSIGNED NOT NULL,
                    status ENUM('pending','delayed','no_decision','granted','refused','transitional')
                        NOT NULL DEFAULT 'pending',
                    cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    submitted_at DATETIME NULL DEFAULT NULL,
                    decision_due_at DATETIME NULL DEFAULT NULL,
                    decided_at DATETIME NULL DEFAULT NULL,
                    refusal_cooldown_until DATETIME NULL DEFAULT NULL,
                    delay_count INT UNSIGNED NOT NULL DEFAULT 0,
                    source VARCHAR(16) NOT NULL DEFAULT 'player',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_player_region (player_id, region_id),
                    KEY idx_status_due (status, decision_due_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'ensureSchema FAILED', $e);
            }
        }
    }

    /**
     * Seeduje konfigurację działu prawnego dla regionów ze świata, których
     * jeszcze nie ma w legal_region_config. Poziom ryzyka mapowany z
     * world_regions.political_risk. Idempotentne (INSERT IGNORE / brak nadpisań).
     *
     * @return int liczba zaseedowanych regionów
     */
    public function seedRegionConfig(): int
    {
        try {
            $regions = $this->db->query(
                "SELECT id, political_risk FROM world_regions"
            )->fetchAll();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'seedRegionConfig: read world_regions FAILED', $e);
            }
            return 0;
        }

        $existing = [];
        foreach ($this->db->query("SELECT region_id FROM legal_region_config")->fetchAll() as $r) {
            $existing[(int)$r['region_id']] = true;
        }

        $insert = $this->db->prepare(
            "INSERT INTO legal_region_config
                (region_id, enabled, is_offshore, risk_level, application_cost,
                 base_review_minutes, delay_risk_pct, delay_min_minutes, delay_max_minutes,
                 no_decision_risk_pct, refusal_risk_pct, refusal_cooldown_minutes,
                 required_capital, required_legal_level)
             VALUES (?, 1, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
        );

        $seeded = 0;
        foreach ($regions as $region) {
            $regionId = (int)$region['id'];
            if (isset($existing[$regionId])) {
                continue;
            }
            $risk = self::riskLevelFromPolitical((int)$region['political_risk']);
            $d    = self::RISK_DEFAULTS[$risk];
            $insert->execute([
                $regionId,
                $risk,
                $d['application_cost'],
                $d['base_review_minutes'],
                $d['delay_risk_pct'],
                $d['delay_min_minutes'],
                $d['delay_max_minutes'],
                $d['no_decision_risk_pct'],
                $d['refusal_risk_pct'],
                $d['refusal_cooldown_minutes'],
                $d['required_capital'],
            ]);
            $seeded++;
        }

        return $seeded;
    }

    // ------------------------------------------------------------------ Reads

    /**
     * Konfiguracja prawna jednego regionu (z nazwą regionu, jeśli dostępna).
     *
     * @return array<string,mixed>|null
     */
    public function getRegionConfig(int $regionId): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT c.*, r.name AS region_name, r.code AS region_code
                   FROM legal_region_config c
                   LEFT JOIN world_regions r ON r.id = c.region_id
                  WHERE c.region_id = ?
                  LIMIT 1"
            );
            $stmt->execute([$regionId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'getRegionConfig FAILED', $e, ['region_id' => $regionId]);
            }
            return null;
        }
    }

    /**
     * Wszystkie konfiguracje regionów (dla panelu admina / listy działu prawnego).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getAllRegionConfigs(): array
    {
        try {
            return $this->db->query(
                "SELECT c.*, r.name AS region_name, r.code AS region_code
                   FROM legal_region_config c
                   LEFT JOIN world_regions r ON r.id = c.region_id
                  ORDER BY c.region_id"
            )->fetchAll();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'getAllRegionConfigs FAILED', $e);
            }
            return [];
        }
    }

    /**
     * Status zezwolenia/wniosku gracza dla regionu.
     *
     * @return array{region_id:int,status:string,has_active:bool,application:?array<string,mixed>}
     */
    public function getPermitStatus(int $playerId, int $regionId): array
    {
        $application = null;
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM drilling_permit_applications
                  WHERE player_id = ? AND region_id = ?
                  LIMIT 1"
            );
            $stmt->execute([$playerId, $regionId]);
            $row = $stmt->fetch();
            $application = $row ?: null;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'getPermitStatus FAILED', $e, [
                    'player_id' => $playerId,
                    'region_id' => $regionId,
                ]);
            }
        }

        $status = $application['status'] ?? 'none';

        return [
            'region_id'   => $regionId,
            'status'      => (string)$status,
            'has_active'  => in_array($status, self::ACTIVE_STATUSES, true),
            'application' => $application,
        ];
    }

    /**
     * Czy gracz ma aktywne zezwolenie (granted lub transitional) na region.
     * To jest twarda bramka zakupu odwiertów.
     */
    public function hasActivePermit(int $playerId, int $regionId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM drilling_permit_applications
                  WHERE player_id = ? AND region_id = ? AND status IN ('granted','transitional')
                  LIMIT 1"
            );
            $stmt->execute([$playerId, $regionId]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'hasActivePermit FAILED', $e, [
                    'player_id' => $playerId,
                    'region_id' => $regionId,
                ]);
            }
            return false;
        }
    }

    // ---------------------------------------------------------------- Helpers

    /** Mapuje world_regions.political_risk (1..4+) na poziom ryzyka regionu. */
    public static function riskLevelFromPolitical(int $politicalRisk): string
    {
        return match (true) {
            $politicalRisk <= 1 => 'low',
            $politicalRisk === 2 => 'medium',
            $politicalRisk === 3 => 'high',
            default => 'critical',
        };
    }

    private function driver(): string
    {
        try {
            return (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (Throwable) {
            return 'mysql';
        }
    }
}
