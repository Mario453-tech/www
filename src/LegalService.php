<?php

declare(strict_types=1);

require_once __DIR__ . '/CompanyCredibilityService.php';
require_once __DIR__ . '/PlayerPaymentService.php';
require_once __DIR__ . '/Legal/HubPermitTrait.php';

/**
 * LegalService — Dział prawny P1+P2a: zezwolenia na wiercenie i huby per region.
 *
 * Etap 1: warstwa danych + odczyt.
 *  - ensureSchema(): tworzy tabele konfiguracji regionów i wniosków/zezwoleń (MySQL).
 *  - seedRegionConfig(): seeduje konfigurację dla regionów z world_regions
 *    (poziom ryzyka mapowany z political_risk).
 *  - metody odczytu: getRegionConfig, getAllRegionConfigs, getPermitStatus,
 *    hasActivePermit.
 *
 * P2a: zezwolenia na huby logistyczne (hub_permit_applications) — LegalHubPermitTrait.
 *
 * Zasada nadrzędna: gracz nie może kupić odwiertu w regionie bez aktywnego
 * zezwolenia (granted lub transitional). Bramka jest egzekwowana w WorldMap
 * (etap 2). Gracz nie widzi procentów ryzyka — to parametry admina/ticka.
 */
class LegalService
{
    use LegalHubPermitTrait;

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

    /** Minimalna wiarygodnosc dla wnioskow w regionach high/critical. */
    public const HIGH_RISK_CREDIBILITY_MIN = 40;

    private const CREDIBILITY_GATED_RISK_LEVELS = ['high' => true, 'critical' => true];

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

    /** @var array<int,bool> Guard auto-seedu per połączenie (raz na żądanie). */
    private static array $autoSeeded = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->ensureSchema();
        $this->autoSeedIfEmpty();
    }

    public static function requiresCompanyCredibility(string $riskLevel): bool
    {
        return isset(self::CREDIBILITY_GATED_RISK_LEVELS[$riskLevel]);
    }

    /**
     * Auto-seed: jeśli legal_region_config jest pusta (pierwsze uruchomienie po
     * wdrożeniu), wypełnia ją na podstawie world_regions, aby gracze nie utknęli
     * (bez configu nie da się złożyć wniosku, a bramka zakupu blokuje fail-closed).
     * Tylko MySQL (produkcja) — na SQLite testy budują własny schemat. Raz na
     * połączenie w ramach żądania.
     */
    private function autoSeedIfEmpty(): void
    {
        if ($this->driver() !== 'mysql') {
            return;
        }
        $connId = spl_object_id($this->db);
        if (isset(self::$autoSeeded[$connId])) {
            return;
        }
        self::$autoSeeded[$connId] = true;

        try {
            $hasConfig = $this->db->query("SELECT 1 FROM legal_region_config LIMIT 1")->fetchColumn();
            if ($hasConfig === false) {
                $n = $this->seedRegionConfig();
                if ($n > 0 && class_exists('GameLog', false)) {
                    GameLog::info('LegalService', "Auto-seed: skonfigurowano {$n} regionów (pierwsze uruchomienie).");
                }
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'autoSeedIfEmpty FAILED', $e);
            }
        }
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

            // P2a: kolumny i tabela zezwolen na huby / P2a: hub permit columns and table
            $this->ensureHubPermitSchema();
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

    /**
     * Wylicza poziom dzialu prawnego gracza z aktywnego dyrektora prawnego.
     * Calculates the player's legal department level from the active legal director.
     */
    public function getLegalLevelForPlayer(int $playerId): int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT bm.skill_organization, bm.skill_analysis, bm.skill_ethics
                   FROM board_members bm
                   JOIN board_roles br ON br.id = bm.role_id
                  WHERE bm.player_id = ?
                    AND bm.status = 'active'
                    AND br.code = 'legal'
                  ORDER BY (
                      COALESCE(bm.skill_organization, 0)
                    + COALESCE(bm.skill_analysis, 0)
                    + COALESCE(bm.skill_ethics, 0)
                  ) DESC
                  LIMIT 1"
            );
            $stmt->execute([$playerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return 0;
            }

            $score = (
                (int)($row['skill_organization'] ?? 0)
                + (int)($row['skill_analysis'] ?? 0)
                + (int)($row['skill_ethics'] ?? 0)
            ) / 3;

            return max(0, min(10, (int)round($score)));
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'getLegalLevelForPlayer FAILED', $e, ['player_id' => $playerId]);
            }
            return 0;
        }
    }

    // ------------------------------------------------------------- Mutations

    /**
     * Składa wniosek o zezwolenie na wiercenie w regionie (etap 3).
     *
     * Walidacja kolejno: region skonfigurowany i włączony, brak aktywnego
     * zezwolenia, brak wniosku w toku, cooldown po odmowie, wymagany kapitał
     * (region wysokiego ryzyka), środki na opłatę. Po przejściu walidacji
     * pobiera opłatę z players.cash i tworzy wniosek 'pending' z terminem
     * decyzji = teraz + base_review_minutes (rozpatruje tick — etap 4).
     *
     * Cała operacja w transakcji. Zwraca ['success'=>bool, 'code'=>string, ...].
     *
     * @return array<string,mixed>
     */
    public function submitApplication(int $playerId, int $regionId, ?DateTimeInterface $now = null): array
    {
        $now = $now ? DateTime::createFromInterface($now) : new DateTime();
        $nowStr = $now->format('Y-m-d H:i:s');

        $config = $this->getRegionConfig($regionId);
        if ($config === null) {
            return ['success' => false, 'code' => 'unknown_region', 'message' => tPlain('legal.err.unknown_region')];
        }
        if ((int)$config['enabled'] !== 1) {
            return ['success' => false, 'code' => 'region_disabled', 'message' => tPlain('legal.err.region_disabled')];
        }

        $existing = $this->getPermitStatus($playerId, $regionId)['application'];
        $existingStatus = $existing['status'] ?? 'none';

        if (in_array($existingStatus, self::ACTIVE_STATUSES, true)) {
            return ['success' => false, 'code' => 'already_active', 'message' => tPlain('legal.err.already_active')];
        }
        if (in_array($existingStatus, self::PENDING_STATUSES, true) || $existingStatus === self::STATUS_NO_DECISION) {
            return ['success' => false, 'code' => 'in_progress', 'message' => tPlain('legal.err.in_progress')];
        }
        if ($existingStatus === self::STATUS_REFUSED && !empty($existing['refusal_cooldown_until'])) {
            $cooldownUntil = new DateTime((string)$existing['refusal_cooldown_until']);
            if ($cooldownUntil > $now) {
                $remainingMin = (int)ceil(($cooldownUntil->getTimestamp() - $now->getTimestamp()) / 60);
                return [
                    'success' => false,
                    'code'    => 'cooldown',
                    'message' => tPlain('legal.err.cooldown', ['time' => self::minutesToHuman($remainingMin)]),
                ];
            }
        }

        $requiredCapital = (float)$config['required_capital'];
        $applicationCost = (float)$config['application_cost'];
        $requiredLegalLevel = (int)($config['required_legal_level'] ?? 0);
        $riskLevel = (string)($config['risk_level'] ?? 'low');

        // Wiarygodnosc firmy blokuje wnioski w regionach high/critical.
        // Company credibility blocks applications in high/critical-risk regions.
        if (self::requiresCompanyCredibility($riskLevel)) {
            $credibilityScore = (new CompanyCredibilityService($this->db))->getScore($playerId);
            if ($credibilityScore < self::HIGH_RISK_CREDIBILITY_MIN) {
                return [
                    'success'                      => false,
                    'code'                         => 'credibility_locked',
                    'message'                      => tPlain('legal.err.credibility_locked', [
                        'min'     => self::HIGH_RISK_CREDIBILITY_MIN,
                        'current' => $credibilityScore,
                    ]),
                    'required_company_credibility' => self::HIGH_RISK_CREDIBILITY_MIN,
                    'company_credibility'          => $credibilityScore,
                ];
            }
        }

        $paymentService = new PlayerPaymentService($this->db);

        $this->db->beginTransaction();
        try {
            $cashStmt = $this->db->prepare("SELECT cash FROM players WHERE id = ? LIMIT 1");
            $cashStmt->execute([$playerId]);
            $cashRow = $cashStmt->fetch();
            if (!$cashRow) {
                $this->db->rollBack();
                return ['success' => false, 'code' => 'unknown_player', 'message' => tPlain('legal.err.unknown_player')];
            }
            $cash = (float)$cashRow['cash'];

            // Wymagany poziom dzialu prawnego dla trudniejszych regionow.
            // Required legal department level for more demanding regions.
            if ($requiredLegalLevel > 0) {
                $legalLevel = $this->getLegalLevelForPlayer($playerId);
                if ($legalLevel < $requiredLegalLevel) {
                    $this->db->rollBack();
                    return [
                        'success'              => false,
                        'code'                 => 'legal_level_locked',
                        'message'              => tPlain('legal.err.legal_level_locked', [
                            'level'   => $requiredLegalLevel,
                            'current' => $legalLevel,
                        ]),
                        'required_legal_level' => $requiredLegalLevel,
                        'legal_level'          => $legalLevel,
                    ];
                }
            }

            // Region wysokiego ryzyka: firma nie spełnia wymogu kapitałowego.
            if ($requiredCapital > 0 && $cash < $requiredCapital) {
                $this->db->rollBack();
                return [
                    'success'          => false,
                    'code'             => 'region_locked',
                    'message'          => tPlain('legal.err.region_locked'),
                    'required_capital' => $requiredCapital,
                ];
            }

            // Środki na opłatę za wniosek.
            if ($cash < $applicationCost) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'code'    => 'insufficient_funds',
                    'message' => tPlain('legal.err.insufficient_funds', [
                        'cost' => number_format($applicationCost, 0, '.', ' '),
                    ]),
                    'cost'    => $applicationCost,
                ];
            }

            // Pobranie oplaty / Deduct legal application fee.
            $payment = $paymentService->charge(
                $playerId,
                $applicationCost,
                FinancialTransactionService::TYPE_LEGAL_FEE,
                tPlain('bank.tx_legal_drilling_permit', ['id' => $regionId]),
                'legal_region',
                $regionId
            );
            if (!$payment['success']) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'code'    => 'insufficient_funds',
                    'message' => tPlain('legal.err.insufficient_funds', [
                        'cost' => number_format($applicationCost, 0, '.', ' '),
                    ]),
                    'cost'    => $applicationCost,
                ];
            }

            // Termin decyzji = teraz + bazowy czas rozpatrzenia.
            $dueStr = (clone $now)
                ->modify('+' . (int)$config['base_review_minutes'] . ' minutes')
                ->format('Y-m-d H:i:s');

            // Jeden wiersz na parę (gracz, region) — wstaw lub zaktualizuj po odmowie.
            if ($existing) {
                $this->db->prepare(
                    "UPDATE drilling_permit_applications
                        SET status = 'pending', cost = ?, submitted_at = ?, decision_due_at = ?,
                            decided_at = NULL, refusal_cooldown_until = NULL, delay_count = 0,
                            source = 'player', updated_at = ?
                      WHERE player_id = ? AND region_id = ?"
                )->execute([$applicationCost, $nowStr, $dueStr, $nowStr, $playerId, $regionId]);
            } else {
                $this->db->prepare(
                    "INSERT INTO drilling_permit_applications
                        (player_id, region_id, status, cost, submitted_at, decision_due_at, source, created_at, updated_at)
                     VALUES (?, ?, 'pending', ?, ?, ?, 'player', ?, ?)"
                )->execute([$playerId, $regionId, $applicationCost, $nowStr, $dueStr, $nowStr, $nowStr]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'submitApplication FAILED', $e, [
                    'player_id' => $playerId,
                    'region_id' => $regionId,
                ]);
            }
            return ['success' => false, 'code' => 'error', 'message' => tPlain('legal.err.generic')];
        }

        // Brief §13: powiadomienie dyrektora o złożeniu wniosku (po commit, poza
        // transakcją — nigdy nie cofa pobranej opłaty ani utworzonego wniosku).
        // Brief §13: director notification on submission (after commit, outside
        // the transaction — never rolls back the fee or the created application).
        $regionName = (string)($config['region_name'] ?? ('#' . $regionId));
        $this->notifyDirector($playerId, 'submitted', [
            'region' => $regionName,
            'time'   => self::minutesToHuman((int)$config['base_review_minutes']),
        ], '', 'low');

        return [
            'success'         => true,
            'code'            => 'submitted',
            'message'         => tPlain('legal.msg.application_submitted', [
                'time' => self::minutesToHuman((int)$config['base_review_minutes']),
            ]),
            'cost'            => $applicationCost,
            'review_minutes'  => (int)$config['base_review_minutes'],
        ];
    }

    // -------------------------------------------------------- Notifications

    /** @var array<int,string> Cache nazw regionów w obrębie obiektu serwisu. */
    private array $regionNameCache = [];

    /**
     * Wstawia powiadomienie dyrektora działu prawnego (brief §13). Spójne z
     * LegalSection: type='legal', action_url='legal.php'. Całość w try/catch —
     * brak tabeli/błąd nigdy nie przerywa operacji nadrzędnej (np. składania
     * wniosku czy migracji).
     *
     * Inserts a legal director notification (brief §13). Consistent with
     * LegalSection. Fully guarded — a missing table/error never breaks the
     * parent operation (e.g. submitting an application or migration).
     *
     * @param array<string,string> $params parametry do interpolacji wiadomości
     */
    private function notifyDirector(
        int $playerId,
        string $key,
        array $params,
        string $icon,
        string $priority = 'low'
    ): void {
        try {
            $title   = tPlain("legal.notif.{$key}.title");
            $message = tPlain("legal.notif.{$key}.message", $params);
            $expires = (new DateTime())->modify('+72 hours')->format('Y-m-d H:i:s');

            $this->db->prepare(
                "INSERT INTO director_notifications
                    (player_id, type, priority, title, message, icon,
                     requires_action, action_url, action_label, expires_at)
                 VALUES (?, 'legal', ?, ?, ?, ?, 0, 'legal.php', 'Dział prawny', ?)"
            )->execute([$playerId, $priority, $title, $message, $icon, $expires]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'notifyDirector FAILED', $e, [
                    'player_id' => $playerId,
                    'key'       => $key,
                ]);
            }
        }
    }

    /**
     * Nazwa regionu z cache (fallback "#id"). Zapytanie w try/catch — brak
     * tabeli world_regions nie przerywa operacji nadrzędnej.
     * Region name with cache (fallback "#id"). Guarded query.
     */
    private function resolveRegionName(int $regionId): string
    {
        if (isset($this->regionNameCache[$regionId])) {
            return $this->regionNameCache[$regionId];
        }
        $name = '#' . $regionId;
        try {
            $stmt = $this->db->prepare("SELECT name FROM world_regions WHERE id = ? LIMIT 1");
            $stmt->execute([$regionId]);
            $row = $stmt->fetch();
            if ($row && !empty($row['name'])) {
                $name = (string)$row['name'];
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'resolveRegionName FAILED', $e, ['region_id' => $regionId]);
            }
        }
        return $this->regionNameCache[$regionId] = $name;
    }

    // ---------------------------------------------------------------- Helpers

    /**
     * Zamienia minuty na ludzki opis czasu dla UI gracza (bez ticków).
     * Do ~90 min pokazujemy minuty, powyżej — zaokrąglone godziny.
     */
    public static function minutesToHuman(int $minutes): string
    {
        $minutes = max(0, $minutes);
        if ($minutes < 90) {
            return $minutes . ' min';
        }
        return (int)round($minutes / 60) . ' h';
    }

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

    // ------------------------------------------------------------- Migration

    /**
     * Migracja P1 — zezwolenia przejściowe dla istniejących graczy.
     *
     * Dla każdej pary (gracz, region) w której gracz ma odwiert NIE będący
     * seized/blowout/sold, region jest w legal_region_config i gracz NIE ma
     * jeszcze żadnego wpisu w drilling_permit_applications, wstawia wiersz
     * ze statusem 'transitional'. Idempotentne (INSERT IGNORE / pomija
     * istniejące).
     *
     * Zwraca liczbę nowo wstawionych wpisów.
     *
     * @param ?DateTimeInterface $now Czas migracji (testowalność)
     */
    public function migrateTransitionalPermits(?DateTimeInterface $now = null): int
    {
        $nowStr = ($now ? DateTime::createFromInterface($now) : new DateTime())
            ->format('Y-m-d H:i:s');

        try {
            // Pary (player_id, region_id) które mają odwierty ale jeszcze
            // nie mają żadnego wpisu o zezwoleniu.
            $stmt = $this->db->query(
                "SELECT DISTINCT w.player_id, w.region_id
                   FROM wells w
                   JOIN legal_region_config c ON c.region_id = w.region_id
                  WHERE w.region_id IS NOT NULL
                    AND w.status NOT IN ('seized','blowout','sold')
                    AND NOT EXISTS (
                        SELECT 1 FROM drilling_permit_applications a
                         WHERE a.player_id = w.player_id AND a.region_id = w.region_id
                    )"
            );
            $pairs = $stmt->fetchAll();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'migrateTransitionalPermits fetch FAILED', $e);
            }
            return 0;
        }

        if (empty($pairs)) {
            return 0;
        }

        $isMySQL = ($this->driver() === 'mysql');
        $insert  = $this->db->prepare(
            $isMySQL
                ? "INSERT IGNORE INTO drilling_permit_applications
                       (player_id, region_id, status, cost, submitted_at, decided_at, source, created_at, updated_at)
                   VALUES (?, ?, 'transitional', 0, ?, ?, 'migration', ?, ?)"
                : "INSERT OR IGNORE INTO drilling_permit_applications
                       (player_id, region_id, status, cost, submitted_at, decided_at, source, created_at, updated_at)
                   VALUES (?, ?, 'transitional', 0, ?, ?, 'migration', ?, ?)"
        );

        $migrated = 0;
        foreach ($pairs as $pair) {
            try {
                $insert->execute([
                    (int)$pair['player_id'],
                    (int)$pair['region_id'],
                    $nowStr, // submitted_at
                    $nowStr, // decided_at
                    $nowStr, // created_at
                    $nowStr, // updated_at
                ]);
                if ($insert->rowCount() > 0) {
                    $migrated++;
                    // Brief §13 / §12.2: powiadomienie o nadaniu zezwolenia przejściowego.
                    // Brief §13 / §12.2: notification about the transitional permit grant.
                    $this->notifyDirector((int)$pair['player_id'], 'transitional', [
                        'region' => $this->resolveRegionName((int)$pair['region_id']),
                    ], '', 'low');
                }
            } catch (Throwable $e) {
                if (class_exists('GameLog', false)) {
                    GameLog::error('LegalService', 'migrateTransitionalPermits insert FAILED', $e, [
                        'player_id' => $pair['player_id'],
                        'region_id' => $pair['region_id'],
                    ]);
                }
            }
        }

        if ($migrated > 0 && class_exists('GameLog', false)) {
            GameLog::info('LegalService', "migrateTransitionalPermits: {$migrated} wpisów przejściowych", [
                'total_pairs' => count($pairs),
                'migrated'    => $migrated,
            ]);
        }

        return $migrated;
    }

    // ------------------------------------------------------- Map permit data

    /**
     * Pobiera status zezwoleń dla wielu regionów jednocześnie (batch) na potrzeby
     * mapy — brief §5. Zastępuje N wywołań hasActivePermit() dwoma zapytaniami.
     *
     * Zwracany status (pole 'status') przyjmuje wartości:
     *   'active'      — granted lub transitional (mapa pokazuje odwierty normalnie)
     *   'pending'     — wniosek czeka na decyzję
     *   'delayed'     — decyzja opóźniona (nowy termin)
     *   'no_decision' — urząd nie wydał decyzji
     *   'refused'     — wniosek odrzucony, cooldown jeszcze trwa
     *   'locked'      — gracz nie spełnia wymogu kapitałowego regionu
     *   'none'        — brak wniosku (lub cooldown minął)
     *
     * Returns permit status for multiple regions at once (batch) for map use —
     * brief §5. Replaces N hasActivePermit() calls with two queries.
     *
     * @param int   $playerId
     * @param int[] $regionIds
     * @param float $playerCash  Player cash (for capital lock check).
     * @param ?DateTimeInterface $now
     * @return array<int,array{status:string,minutes_left:int|null,cooldown_minutes:int|null,required_capital:float|null}>
     */
    public function getMapPermitData(
        int $playerId,
        array $regionIds,
        float $playerCash,
        ?DateTimeInterface $now = null
    ): array {
        if (empty($regionIds)) {
            return [];
        }

        $now = $now ? DateTime::createFromInterface($now) : new DateTime();

        // Inicjuj wszystkie regiony jako 'none'.
        // Initialise all regions as 'none'.
        $result = [];
        foreach ($regionIds as $rid) {
            $result[(int)$rid] = [
                'status'            => 'none',
                'minutes_left'      => null,
                'cooldown_minutes'  => null,
                'required_capital'  => null,
                'required_legal_level' => null,
                'legal_level'       => null,
                'required_company_credibility' => null,
                'company_credibility' => null,
            ];
        }

        // --- Zapytanie 1: aplikacje gracza dla tych regionów.
        // --- Query 1: player applications for these regions.
        $apps = [];
        try {
            $ph   = implode(',', array_fill(0, count($regionIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT region_id, status, decision_due_at, refusal_cooldown_until
                   FROM drilling_permit_applications
                  WHERE player_id = ? AND region_id IN ({$ph})"
            );
            $stmt->execute(array_merge([$playerId], array_map('intval', $regionIds)));
            $apps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'getMapPermitData: apps query FAILED', $e, ['player_id' => $playerId]);
            }
        }

        // --- Zapytanie 2: wymagany kapitał per region (blokada §7.2).
        // --- Query 2: required capital per region (lock §7.2).
        $capitals = [];
        $legalLevelsRequired = [];
        $credibilityRequired = [];
        try {
            $ph   = implode(',', array_fill(0, count($regionIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT region_id, required_capital, required_legal_level, risk_level
                   FROM legal_region_config
                  WHERE region_id IN ({$ph})"
            );
            $stmt->execute(array_map('intval', $regionIds));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $capitals[(int)$row['region_id']] = (float)$row['required_capital'];
                $legalLevelsRequired[(int)$row['region_id']] = (int)($row['required_legal_level'] ?? 0);
                $credibilityRequired[(int)$row['region_id']] = self::requiresCompanyCredibility((string)($row['risk_level'] ?? 'low'))
                    ? self::HIGH_RISK_CREDIBILITY_MIN
                    : 0;
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'getMapPermitData: capitals query FAILED', $e, ['player_id' => $playerId]);
            }
        }

        $playerLegalLevel = 0;
        foreach ($legalLevelsRequired as $requiredLevel) {
            if ($requiredLevel > 0) {
                $playerLegalLevel = $this->getLegalLevelForPlayer($playerId);
                break;
            }
        }

        $playerCredibility = CompanyCredibilityService::DEFAULT_SCORE;
        foreach ($credibilityRequired as $requiredCredibility) {
            if ($requiredCredibility > 0) {
                $playerCredibility = (new CompanyCredibilityService($this->db))->getScore($playerId);
                break;
            }
        }

        // --- Oblicz status mapy per region na podstawie aplikacji.
        // --- Compute map status per region from applications.
        foreach ($apps as $app) {
            $rid    = (int)$app['region_id'];
            $status = (string)$app['status'];

            if (in_array($status, self::ACTIVE_STATUSES, true)) {
                $result[$rid] = array_merge($result[$rid], [
                    'status' => 'active', 'minutes_left' => null, 'cooldown_minutes' => null, 'required_capital' => null,
                ]);
                continue;
            }

            if (in_array($status, ['pending', 'delayed'], true)) {
                $minutesLeft = null;
                if (!empty($app['decision_due_at'])) {
                    $due = new DateTime((string)$app['decision_due_at']);
                    $minutesLeft = max(0, (int)ceil(($due->getTimestamp() - $now->getTimestamp()) / 60));
                }
                $result[$rid] = array_merge($result[$rid], [
                    'status' => $status, 'minutes_left' => $minutesLeft, 'cooldown_minutes' => null, 'required_capital' => null,
                ]);
                continue;
            }

            if ($status === self::STATUS_NO_DECISION) {
                $result[$rid] = array_merge($result[$rid], [
                    'status' => 'no_decision', 'minutes_left' => null, 'cooldown_minutes' => null, 'required_capital' => null,
                ]);
                continue;
            }

            if ($status === self::STATUS_REFUSED && !empty($app['refusal_cooldown_until'])) {
                $cooldownUntil = new DateTime((string)$app['refusal_cooldown_until']);
                $remaining     = (int)ceil(($cooldownUntil->getTimestamp() - $now->getTimestamp()) / 60);
                if ($remaining > 0) {
                    $result[$rid] = array_merge($result[$rid], [
                        'status' => 'refused', 'minutes_left' => null, 'cooldown_minutes' => $remaining, 'required_capital' => null,
                    ]);
                    continue;
                }
            }
            // STATUS_REFUSED po cooldownie lub STATUS_NONE — sprawdź blokadę.
            // Refused past cooldown or no application — fall through to lock check.
        }

        // --- Dla regionów bez aktywnego wpisu: sprawdź blokadę kapitałową §7.2.
        // --- For regions with no active entry: check capital lock §7.2.
        foreach ($result as $rid => &$entry) {
            if ($entry['status'] === 'none') {
                $reqLevel = $legalLevelsRequired[$rid] ?? 0;
                if ($reqLevel > 0 && $playerLegalLevel < $reqLevel) {
                    $entry['status'] = 'legal_locked';
                    $entry['required_legal_level'] = $reqLevel;
                    $entry['legal_level'] = $playerLegalLevel;
                    continue;
                }

                $reqCredibility = $credibilityRequired[$rid] ?? 0;
                if ($reqCredibility > 0 && $playerCredibility < $reqCredibility) {
                    $entry['status'] = 'credibility_locked';
                    $entry['required_company_credibility'] = $reqCredibility;
                    $entry['company_credibility'] = $playerCredibility;
                    continue;
                }

                $req = $capitals[$rid] ?? 0.0;
                if ($req > 0.0 && $playerCash < $req) {
                    $entry['status']           = 'locked';
                    $entry['required_capital'] = $req;
                }
            }
        }
        unset($entry);

        return $result;
    }
}
