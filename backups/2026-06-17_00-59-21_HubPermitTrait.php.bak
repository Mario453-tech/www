<?php

declare(strict_types=1);

/**
 * Legal/HubPermitTrait.php
 * P2a — zezwolenia na budowe hubow logistycznych per region.
 * P2a — hub construction permits per region.
 *
 * Mixin do LegalService. Wymaga: $this->db, $this->driver(), minutesToHuman().
 * Mixin for LegalService. Requires: $this->db, $this->driver(), minutesToHuman().
 */
trait LegalHubPermitTrait
{
    /** Koszt domyslny wniosku o zezwolenie na hub per poziom ryzyka. */
    /** Default hub permit application cost per risk level. */
    private const HUB_PERMIT_COST_DEFAULTS = [
        'low'      => 200000.00,
        'medium'   => 500000.00,
        'high'     => 1000000.00,
        'critical' => 2000000.00,
    ];

    /** Domyslny czas rozpatrzenia wniosku o hub (minuty). */
    /** Default hub permit review time (minutes). */
    private const HUB_REVIEW_MINUTES_DEFAULTS = [
        'low'      => 60,
        'medium'   => 120,
        'high'     => 180,
        'critical' => 240,
    ];

    // ---------------------------------------------------------------- Schema

    /**
     * Tworzy tabele i kolumny dla zezwolen na huby (idempotentnie, MySQL/MariaDB).
     * Creates hub permit tables and columns (idempotent, MySQL/MariaDB).
     * Wywolywane z ensureSchema() przez LegalService.
     * Called from LegalService::ensureSchema().
     */
    private function ensureHubPermitSchema(): void
    {
        if ($this->driver() !== 'mysql') {
            return;
        }
        try {
            // Bezpieczne dodanie kolumn zgodne z helperem projektu / Safe column bootstrap using project helper
            Database::addColumnIfMissing(
                'legal_region_config',
                'hub_permit_enabled',
                'TINYINT(1) NOT NULL DEFAULT 0'
            );
            Database::addColumnIfMissing(
                'legal_region_config',
                'hub_permit_cost',
                'DECIMAL(14,2) NOT NULL DEFAULT 500000.00'
            );
            Database::addColumnIfMissing(
                'legal_region_config',
                'hub_review_minutes',
                'INT UNSIGNED NOT NULL DEFAULT 120'
            );

            // Tabela wnioskow o zezwolenia na huby / Hub permit applications table
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS hub_permit_applications (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    player_id INT UNSIGNED NOT NULL,
                    region_id INT UNSIGNED NOT NULL,
                    status ENUM('pending','delayed','no_decision','granted','refused')
                        NOT NULL DEFAULT 'pending',
                    cost DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                    submitted_at DATETIME NULL DEFAULT NULL,
                    decision_due_at DATETIME NULL DEFAULT NULL,
                    decided_at DATETIME NULL DEFAULT NULL,
                    refusal_cooldown_until DATETIME NULL DEFAULT NULL,
                    delay_count INT UNSIGNED NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_player_region (player_id, region_id),
                    KEY idx_status_due (status, decision_due_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
            );
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'ensureHubPermitSchema FAILED', $e);
            }
        }
    }

    // ------------------------------------------------------------- Read gate

    /**
     * Czy gracz ma aktywne zezwolenie na hub w tym regionie?
     * Does the player have an active hub permit in this region?
     * Bramka uzytkowa dla HubAcquisitionService.
     * Utility gate for HubAcquisitionService.
     */
    public function hasActiveHubPermit(int $playerId, int $regionId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM hub_permit_applications
                  WHERE player_id = ? AND region_id = ? AND status = 'granted'
                  LIMIT 1"
            );
            $stmt->execute([$playerId, $regionId]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'hasActiveHubPermit FAILED', $e, [
                    'player_id' => $playerId,
                    'region_id' => $regionId,
                ]);
            }
            return false;
        }
    }

    /**
     * Czy region wymaga zezwolenia na hub?
     * Is a hub permit required in this region?
     */
    public function isHubPermitRequired(int $regionId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT hub_permit_enabled FROM legal_region_config WHERE region_id = ? LIMIT 1"
            );
            $stmt->execute([$regionId]);
            $row = $stmt->fetch();
            return $row && (int)$row['hub_permit_enabled'] === 1;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'isHubPermitRequired FAILED', $e, ['region_id' => $regionId]);
            }
            return false;
        }
    }

    /**
     * Status wniosku gracza o zezwolenie na hub w regionie.
     * Player hub permit application status in region.
     *
     * @return array{status: string, has_active: bool, application: array<string,mixed>|null}
     */
    public function getHubPermitStatus(int $playerId, int $regionId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM hub_permit_applications WHERE player_id = ? AND region_id = ? LIMIT 1"
            );
            $stmt->execute([$playerId, $regionId]);
            $app = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'getHubPermitStatus FAILED', $e, [
                    'player_id' => $playerId, 'region_id' => $regionId,
                ]);
            }
            return ['status' => 'none', 'has_active' => false, 'application' => null];
        }

        $status    = $app ? (string)$app['status'] : 'none';
        $hasActive = $status === 'granted';

        return ['status' => $status, 'has_active' => $hasActive, 'application' => $app];
    }

    /**
     * Statusy zezwolen na huby dla wielu regionow (dla widoku gracza).
     * Hub permit statuses for multiple regions (for player view).
     *
     * @param  int[]  $regionIds
     * @return array<int, array{status: string, has_active: bool, application: array<string,mixed>|null}>
     */
    public function getHubPermitBatch(int $playerId, array $regionIds): array
    {
        if (empty($regionIds)) {
            return [];
        }

        $result = [];
        foreach ($regionIds as $rid) {
            $result[(int)$rid] = ['status' => 'none', 'has_active' => false, 'application' => null];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($regionIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT * FROM hub_permit_applications
                  WHERE player_id = ? AND region_id IN ({$placeholders})"
            );
            $stmt->execute(array_merge([$playerId], $regionIds));
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'getHubPermitBatch FAILED', $e, ['player_id' => $playerId]);
            }
            return $result;
        }

        foreach ($rows as $app) {
            $rid    = (int)$app['region_id'];
            $status = (string)$app['status'];
            $result[$rid] = [
                'status'     => $status,
                'has_active' => $status === 'granted',
                'application' => $app,
            ];
        }

        return $result;
    }

    // --------------------------------------------------------------- Submit

    /**
     * Zlozenie wniosku o zezwolenie na hub logistyczny.
     * Submit an application for a hub construction permit.
     *
     * @return array<string,mixed>
     */
    public function submitHubApplication(int $playerId, int $regionId, ?DateTimeInterface $now = null): array
    {
        $now    = $now ? DateTime::createFromInterface($now) : new DateTime();
        $nowStr = $now->format('Y-m-d H:i:s');

        // Pobierz config regionu / Get region config
        $cfgStmt = $this->db->prepare(
            "SELECT lrc.*, wr.name AS region_name
               FROM legal_region_config lrc
               LEFT JOIN world_regions wr ON wr.id = lrc.region_id
              WHERE lrc.region_id = ? LIMIT 1"
        );
        $cfgStmt->execute([$regionId]);
        $config = $cfgStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($config === null) {
            return ['success' => false, 'code' => 'unknown_region', 'message' => tPlain('legal.hub.err.unknown_region')];
        }
        if (!(int)$config['enabled']) {
            return ['success' => false, 'code' => 'region_disabled', 'message' => tPlain('legal.hub.err.region_disabled')];
        }
        if (!(int)$config['hub_permit_enabled']) {
            return ['success' => false, 'code' => 'hub_not_required', 'message' => tPlain('legal.hub.err.hub_not_required')];
        }

        $existing = $this->getHubPermitStatus($playerId, $regionId);
        $existingStatus = $existing['status'];

        if ($existingStatus === 'granted') {
            return ['success' => false, 'code' => 'already_active', 'message' => tPlain('legal.hub.err.already_active')];
        }
        if (in_array($existingStatus, ['pending', 'delayed', 'no_decision'], true)) {
            return ['success' => false, 'code' => 'in_progress', 'message' => tPlain('legal.hub.err.in_progress')];
        }
        if ($existingStatus === 'refused' && !empty($existing['application']['refusal_cooldown_until'])) {
            $cooldownUntil = new DateTime((string)$existing['application']['refusal_cooldown_until']);
            if ($cooldownUntil > $now) {
                $remainMin = (int)ceil(($cooldownUntil->getTimestamp() - $now->getTimestamp()) / 60);
                return [
                    'success' => false,
                    'code'    => 'cooldown',
                    'message' => tPlain('legal.hub.err.cooldown', ['time' => self::minutesToHuman($remainMin)]),
                ];
            }
        }

        $applicationCost = (float)$config['hub_permit_cost'];
        $reviewMinutes   = (int)$config['hub_review_minutes'];

        $paymentService = new PlayerPaymentService($this->db);

        $this->db->beginTransaction();
        try {
            $cashStmt = $this->db->prepare("SELECT cash FROM players WHERE id = ? LIMIT 1");
            $cashStmt->execute([$playerId]);
            $cashRow = $cashStmt->fetch();
            if (!$cashRow) {
                $this->db->rollBack();
                return ['success' => false, 'code' => 'unknown_player', 'message' => tPlain('legal.hub.err.unknown_player')];
            }
            $cash = (float)$cashRow['cash'];

            if ($cash < $applicationCost) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'code'    => 'insufficient_funds',
                    'message' => tPlain('legal.hub.err.insufficient_funds', [
                        'cost' => number_format($applicationCost, 0, '.', ' '),
                    ]),
                    'cost' => $applicationCost,
                ];
            }

            // Pobierz oplate / Deduct fee.
            $payment = $paymentService->charge(
                $playerId,
                $applicationCost,
                FinancialTransactionService::TYPE_LEGAL_FEE,
                tPlain('bank.tx_legal_hub_permit', ['id' => $regionId]),
                'legal_hub_region',
                $regionId
            );
            if (!$payment['success']) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'code'    => 'insufficient_funds',
                    'message' => tPlain('legal.hub.err.insufficient_funds', [
                        'cost' => number_format($applicationCost, 0, '.', ' '),
                    ]),
                    'cost' => $applicationCost,
                ];
            }

            $dueStr = (clone $now)->modify("+{$reviewMinutes} minutes")->format('Y-m-d H:i:s');

            // Jeden wiersz per (gracz, region) — wstaw lub zaktualizuj / One row per (player, region) — upsert
            if ($existing['application']) {
                $this->db->prepare(
                    "UPDATE hub_permit_applications
                        SET status = 'pending', cost = ?, submitted_at = ?, decision_due_at = ?,
                            decided_at = NULL, refusal_cooldown_until = NULL, delay_count = 0,
                            updated_at = ?
                      WHERE player_id = ? AND region_id = ?"
                )->execute([$applicationCost, $nowStr, $dueStr, $nowStr, $playerId, $regionId]);
            } else {
                $this->db->prepare(
                    "INSERT INTO hub_permit_applications
                        (player_id, region_id, status, cost, submitted_at, decision_due_at, created_at, updated_at)
                     VALUES (?, ?, 'pending', ?, ?, ?, ?, ?)"
                )->execute([$playerId, $regionId, $applicationCost, $nowStr, $dueStr, $nowStr, $nowStr]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'submitHubApplication FAILED', $e, [
                    'player_id' => $playerId, 'region_id' => $regionId,
                ]);
            }
            return ['success' => false, 'code' => 'error', 'message' => tPlain('legal.hub.err.generic')];
        }

        // Powiadomienie po transakcji — nigdy nie cofa oplaty.
        // Director notification after commit — never rolls back the fee.
        $this->notifyDirectorHub($playerId, 'submitted', [
            'region' => (string)($config['region_name'] ?? ('#' . $regionId)),
            'time'   => self::minutesToHuman($reviewMinutes),
        ], '📝', 'low');

        if (class_exists('GameLog', false)) {
            GameLog::info('LegalService', 'Hub permit application submitted', [
                'player_id' => $playerId, 'region_id' => $regionId, 'cost' => $applicationCost,
            ]);
        }

        return [
            'success'        => true,
            'code'           => 'submitted',
            'message'        => tPlain('legal.hub.msg.submitted', [
                'time' => self::minutesToHuman($reviewMinutes),
            ]),
            'cost'           => $applicationCost,
            'review_minutes' => $reviewMinutes,
        ];
    }

    // --------------------------------------------------------------- Admin

    /**
     * Wszystkie wnioski o zezwolenia na huby (dla panelu admina).
     * All hub permit applications (for admin panel).
     *
     * @return list<array<string,mixed>>
     */
    public function getAllHubApplications(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT a.*, p.company_name, p.username,
                        r.name AS region_name
                   FROM hub_permit_applications a
                   LEFT JOIN players p ON p.id = a.player_id
                   LEFT JOIN world_regions r ON r.id = a.region_id
                  ORDER BY a.submitted_at DESC
                  LIMIT 500"
            );
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'getAllHubApplications FAILED', $e);
            }
            return [];
        }
    }

    /**
     * Seeduje domyslne wartosci hub_permit_cost i hub_review_minutes w legal_region_config.
     * Seeds default hub_permit_cost and hub_review_minutes in legal_region_config.
     * Idempotentne — nadpisuje tylko wartosci DOMYSLNE (500000 i 120).
     * Idempotent — only overwrites DEFAULT values (500000 and 120).
     */
    public function seedHubPermitDefaults(): int
    {
        $updated = 0;
        try {
            $rows = $this->db->query(
                "SELECT region_id, risk_level FROM legal_region_config"
            )->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare(
                "UPDATE legal_region_config
                    SET hub_permit_cost = ?, hub_review_minutes = ?
                  WHERE region_id = ?
                    AND hub_permit_cost = 500000.00
                    AND hub_review_minutes = 120"
            );

            foreach ($rows as $row) {
                $level = (string)$row['risk_level'];
                $cost  = self::HUB_PERMIT_COST_DEFAULTS[$level]    ?? 500000.00;
                $mins  = self::HUB_REVIEW_MINUTES_DEFAULTS[$level]  ?? 120;
                $stmt->execute([$cost, $mins, (int)$row['region_id']]);
                $updated += $stmt->rowCount();
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'seedHubPermitDefaults FAILED', $e);
            }
        }
        return $updated;
    }

    // -------------------------------------------------- Notification helper

    /**
     * Wstawia powiadomienie dyrektora dla gracza (typ 'legal_hub').
     * Inserts a director notification for the player (type 'legal_hub').
     * W pelni owiniete try/catch — nigdy nie przerywa nadrzednej operacji.
     * Fully try/catch guarded — never breaks the parent operation.
     *
     * @param array<string,string> $params
     */
    private function notifyDirectorHub(
        int    $playerId,
        string $key,
        array  $params,
        string $icon,
        string $priority = 'low'
    ): void {
        try {
            $title   = tPlain("legal.hub.notif.{$key}.title");
            $message = tPlain("legal.hub.notif.{$key}.message", $params);
            $expires = (new DateTime())->modify('+72 hours')->format('Y-m-d H:i:s');

            // Uwaga: director_notifications.type to ENUM bez 'legal_hub' — uzywamy 'legal'.
            // Note: director_notifications.type ENUM lacks 'legal_hub' — use 'legal'.
            $this->db->prepare(
                "INSERT INTO director_notifications
                    (player_id, type, priority, title, message, icon,
                     requires_action, action_url, action_label, expires_at)
                 VALUES (?, 'legal', ?, ?, ?, ?, 0, 'legal.php', 'Dział prawny — huby', ?)"
            )->execute([$playerId, $priority, $title, $message, $icon, $expires]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('LegalService', 'notifyDirectorHub FAILED', $e, [
                    'player_id' => $playerId, 'key' => $key,
                ]);
            }
        }
    }
}
