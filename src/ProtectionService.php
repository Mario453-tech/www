<?php

declare(strict_types=1);

require_once __DIR__ . '/Protection/ProtectionSchema.php';
require_once __DIR__ . '/FinancialTransactionService.php';
require_once __DIR__ . '/CompanyCredibilityService.php';

/**
 * ProtectionService - uniwersalny silnik ochrony aktywow ("gniazdko").
 * ProtectionService - universal asset protection engine (the "socket").
 *
 * Opcje ochrony i ich efekty sa definiowane w bazie (panel admina), nie w kodzie.
 * Modul gry pyta o aktywne efekty dla celu i naklada je na swoje ryzyka - zaden
 * modul nie liczy efektow samodzielnie. Platnosc przez FTS (TYPE_PROTECTION).
 * Protection options and their effects live in the database (admin panel), not in
 * code. A game module asks for active effects for a target and applies them to its
 * risks - no module computes effects on its own. Payment goes through FTS.
 *
 * P1: transport drogowy - target_type 'road_transport', target_id = well_id,
 * context 'road_transport_guard'.
 */
class ProtectionService
{
    /** Klucze efektow obslugiwane w P1 / Effect keys supported in P1 */
    public const EFFECT_KEYS_P1 = ['theft_risk_mult', 'raid_risk_mult', 'sabotage_risk_mult'];

    private PDO $db;
    private CompanyCredibilityService $credibility;
    private ?LegalService $legal = null;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        // Schemat i serwis reputacji poza transakcja wolajacego (wzor BriberyService).
        // Schema and reputation service outside the caller's transaction (BriberyService pattern).
        ProtectionSchema::ensure($this->db);
        $this->credibility = new CompanyCredibilityService($this->db);

        if (class_exists('GameLog', false)) {
            GameLog::info('ProtectionService', '__construct');
        }
    }

    /**
     * Opcje ochrony dla celu: aktywne, z kosztem, flaga affordable i powodem blokady.
     * Protection options for a target: active, with cost, affordable flag and lock reason.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableOptions(int $playerId, string $targetType, string $context, float $referenceValue = 0.0): array
    {
        $this->expireOverdue();
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM protection_options
                  WHERE is_active = 1 AND target_type = ? AND context = ?
                  ORDER BY sort_order ASC, id ASC"
            );
            $stmt->execute([$targetType, $context]);
            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            GameLog::error('ProtectionService', 'getAvailableOptions FAILED', $e, ['player_id' => $playerId]);
            return [];
        }
        if ($options === []) {
            return [];
        }

        $score = $this->credibility->getScore($playerId);
        $needsLegal = false;
        foreach ($options as $opt) {
            if ((int)$opt['min_legal_level'] > 0) {
                $needsLegal = true;
                break;
            }
        }
        $legalLevel = $needsLegal ? $this->legalLevel($playerId) : 0;
        $cash = $this->playerCash($playerId);

        $out = [];
        foreach ($options as $opt) {
            $cost = $this->computeCost($opt, $referenceValue);
            $lockedReason = null;
            if ($score < (int)$opt['min_company_credibility']) {
                $lockedReason = 'credibility';
            } elseif ($legalLevel < (int)$opt['min_legal_level']) {
                $lockedReason = 'legal_level';
            }
            $out[] = $opt + [
                'cost'          => $cost,
                'affordable'    => $cash >= $cost,
                'locked_reason' => $lockedReason,
                'effects'       => $this->effectsFor((int)$opt['id']),
            ];
        }
        return $out;
    }

    /**
     * Wycena opcji bez ruchu srodkow (dla UI).
     * Option quote without money movement (for the UI).
     *
     * @return array<string, mixed>
     */
    public function quote(int $playerId, string $optionCode, float $referenceValue): array
    {
        $opt = $this->optionByCode($optionCode);
        if ($opt === null) {
            return ['success' => false, 'outcome' => 'not_found', 'message' => tPlain('protection.err_not_found')];
        }
        $cost = $this->computeCost($opt, $referenceValue);
        return [
            'success'    => true,
            'cost'       => $cost,
            'affordable' => $this->playerCash($playerId) >= $cost,
            'duration_minutes' => (int)$opt['duration_minutes'],
            'effects'    => $this->effectsFor((int)$opt['id']),
        ];
    }

    /**
     * Aktywuje ochrone: walidacja, FTS debit, zapis active + log, powiadomienie.
     * Activates protection: validation, FTS debit, active + log insert, notification.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function activate(int $playerId, string $optionCode, string $targetType, int $targetId, float $referenceValue, array $meta = []): array
    {
        $opt = $this->optionByCode($optionCode);
        if ($opt === null || (string)$opt['target_type'] !== $targetType) {
            return ['success' => false, 'outcome' => 'not_found', 'message' => tPlain('protection.err_not_found')];
        }
        if ((int)$opt['is_active'] !== 1) {
            return ['success' => false, 'outcome' => 'disabled', 'message' => tPlain('protection.err_disabled')];
        }
        if ($this->credibility->getScore($playerId) < (int)$opt['min_company_credibility']) {
            return ['success' => false, 'outcome' => 'requirements_not_met',
                    'message' => tPlain('protection.err_req_credibility', ['min' => (int)$opt['min_company_credibility']])];
        }
        if ((int)$opt['min_legal_level'] > 0 && $this->legalLevel($playerId) < (int)$opt['min_legal_level']) {
            return ['success' => false, 'outcome' => 'requirements_not_met',
                    'message' => tPlain('protection.err_req_legal', ['min' => (int)$opt['min_legal_level']])];
        }

        $this->expireOverdue();
        $context = (string)$opt['context'];
        $existing = $this->activeRow($playerId, $targetType, $targetId, $context);
        if ($existing !== null) {
            return ['success' => false, 'outcome' => 'already_active', 'ends_at' => $existing['ends_at'],
                    'message' => tPlain('protection.err_already_active', ['ends' => (string)$existing['ends_at']])];
        }

        $cost = $this->computeCost($opt, $referenceValue);
        $now = date('Y-m-d H:i:s');
        $endsAt = date('Y-m-d H:i:s', time() + (int)$opt['duration_minutes'] * 60);
        $optionId = (int)$opt['id'];
        $optionName = (string)$opt['name'];

        $fts = new FinancialTransactionService($this->db);
        $this->db->beginTransaction();
        try {
            $charge = $fts->debit(
                $playerId,
                $cost,
                FinancialTransactionService::TYPE_PROTECTION,
                tPlain('protection.tx_label', ['name' => $optionName])
            );
            if (empty($charge['success'])) {
                $this->db->rollBack();
                return ['success' => false, 'outcome' => 'no_funds', 'cost' => $cost,
                        'message' => tPlain('protection.err_no_funds', ['cost' => number_format($cost, 0, '.', ' ')])];
            }

            $this->db->prepare(
                "INSERT INTO active_protections
                    (player_id, protection_option_id, target_type, target_id, context,
                     paid_from, cost, starts_at, ends_at, status, meta_json, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 'cash', ?, ?, ?, 'active', ?, ?, ?)"
            )->execute([
                $playerId, $optionId, $targetType, $targetId, $context,
                $cost, $now, $endsAt,
                $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                $now, $now,
            ]);

            $this->logEvent($playerId, $optionId, $targetType, $targetId, $context,
                'protection_activated', $cost,
                tPlain('protection.log_activated', ['name' => $optionName]));

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            GameLog::error('ProtectionService', 'activate FAILED', $e, ['player_id' => $playerId, 'option' => $optionCode]);
            return ['success' => false, 'outcome' => 'error', 'message' => tPlain('protection.err_generic')];
        }

        $this->notifyActivated($playerId, $optionName, $endsAt, $meta);
        GameLog::info('ProtectionService', 'activate OK', [
            'player_id' => $playerId, 'option' => $optionCode,
            'target' => $targetType . '#' . $targetId, 'cost' => $cost, 'ends_at' => $endsAt,
        ]);
        return [
            'success' => true, 'outcome' => 'success', 'cost' => $cost, 'ends_at' => $endsAt,
            'message' => tPlain('protection.msg_activated', [
                'name' => $optionName, 'cost' => number_format($cost, 0, '.', ' '),
            ]),
        ];
    }

    /**
     * Aktywna ochrona celu wraz z efektami (null = brak).
     * Active protection of a target with its effects (null = none).
     *
     * @return array<string, mixed>|null
     */
    public function getActiveProtection(int $playerId, string $targetType, int $targetId, string $context): ?array
    {
        $this->expireOverdue();
        $row = $this->activeRow($playerId, $targetType, $targetId, $context);
        if ($row === null) {
            return null;
        }
        $row['effects'] = $this->effectsFor((int)$row['protection_option_id']);
        return $row;
    }

    /**
     * Scalona mapa efektow aktywnej ochrony: effect_key => ['type','value'].
     * Mnozniki przycinane do (0,1] - ochrona nigdy nie zeruje ryzyka.
     * Merged effect map of the active protection: effect_key => ['type','value'].
     * Multipliers clamped to (0,1] - protection never zeroes the risk.
     *
     * @return array<string, array{type:string, value:float}>
     */
    public function getActiveEffects(int $playerId, string $targetType, int $targetId, string $context): array
    {
        $row = $this->getActiveProtection($playerId, $targetType, $targetId, $context);
        return $row === null ? [] : $row['effects'];
    }

    /**
     * Naklada efekty na bazowe ryzyka: mult mnozy, delta dodaje, nieznane klucze ignorowane.
     * Applies effects to base risks: mult multiplies, delta adds, unknown keys ignored.
     *
     * @param array<string, float> $baseRisks
     * @param array<string, array{type:string, value:float}> $effects
     * @return array<string, float>
     */
    public function applyEffects(array $baseRisks, array $effects): array
    {
        foreach ($effects as $key => $eff) {
            if (!array_key_exists($key, $baseRisks)) {
                continue;
            }
            $baseRisks[$key] = $eff['type'] === 'delta'
                ? $baseRisks[$key] + $eff['value']
                : $baseRisks[$key] * $eff['value'];
        }
        return $baseRisks;
    }

    /**
     * Publiczny wpis do historii (takze dla modulow: protection_applied_to_incident).
     * Public history entry (also for modules: protection_applied_to_incident).
     *
     * @param array<string, mixed>|null $meta
     */
    public function logEvent(int $playerId, int $optionId, string $targetType, int $targetId, string $context,
                             string $eventKey, float $amount = 0.0, string $message = '', ?array $meta = null): void
    {
        try {
            $this->db->prepare(
                "INSERT INTO protection_logs
                    (player_id, protection_option_id, target_type, target_id, context,
                     event_key, amount, message, meta_json, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $playerId, $optionId, $targetType, $targetId, $context,
                $eventKey, $amount, $message,
                $meta !== null ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            GameLog::error('ProtectionService', 'logEvent FAILED', $e, ['event' => $eventKey]);
        }
    }

    /**
     * Anuluje aktywna ochrone (admin). Bez zwrotu srodkow w P1.
     * Cancels an active protection (admin). No refund in P1.
     *
     * @return array<string, mixed>
     */
    public function cancel(int $activeId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM active_protections WHERE id = ? AND status = 'active'");
            $stmt->execute([$activeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return ['success' => false, 'message' => tPlain('protection.err_not_found')];
            }
            $this->db->prepare(
                "UPDATE active_protections SET status = 'cancelled', updated_at = ? WHERE id = ?"
            )->execute([date('Y-m-d H:i:s'), $activeId]);
            $this->logEvent((int)$row['player_id'], (int)$row['protection_option_id'],
                (string)$row['target_type'], (int)$row['target_id'], (string)$row['context'],
                'protection_cancelled');
            GameLog::info('ProtectionService', 'cancel OK', ['active_id' => $activeId]);
            return ['success' => true];
        } catch (Throwable $e) {
            GameLog::error('ProtectionService', 'cancel FAILED', $e, ['active_id' => $activeId]);
            return ['success' => false, 'message' => tPlain('protection.err_generic')];
        }
    }

    // ------------------------------------------------------------- internals

    /** @return array<string, mixed>|null */
    private function optionByCode(string $code): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM protection_options WHERE code = ?");
            $stmt->execute([$code]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            GameLog::error('ProtectionService', 'optionByCode FAILED', $e, ['code' => $code]);
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    private function activeRow(int $playerId, string $targetType, int $targetId, string $context): ?array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM active_protections
                  WHERE player_id = ? AND target_type = ? AND target_id = ? AND context = ?
                    AND status = 'active' AND ends_at > ?
                  ORDER BY ends_at DESC LIMIT 1"
            );
            $stmt->execute([$playerId, $targetType, $targetId, $context, date('Y-m-d H:i:s')]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            GameLog::error('ProtectionService', 'activeRow FAILED', $e, ['player_id' => $playerId]);
            return null;
        }
    }

    /** @return array<string, array{type:string, value:float}> */
    private function effectsFor(int $optionId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT effect_key, effect_type, effect_value FROM protection_effects WHERE protection_option_id = ?"
            );
            $stmt->execute([$optionId]);
            $out = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $type = (string)$row['effect_type'];
                $value = (float)$row['effect_value'];
                if ($type === 'mult') {
                    $value = max(0.05, min(1.0, $value));
                }
                $out[(string)$row['effect_key']] = ['type' => $type, 'value' => $value];
            }
            return $out;
        } catch (Throwable $e) {
            GameLog::error('ProtectionService', 'effectsFor FAILED', $e, ['option_id' => $optionId]);
            return [];
        }
    }

    /** @param array<string, mixed> $option */
    private function computeCost(array $option, float $referenceValue): float
    {
        $value = (float)$option['cost_value'];
        $cost = match ((string)$option['cost_type']) {
            'percent_reference' => max(0.0, $referenceValue) * $value / 100.0,
            'per_hour'          => $value * ((int)$option['duration_minutes'] / 60.0),
            'per_bbl'           => $value * max(0.0, $referenceValue),
            default             => $value,
        };
        return round(max(0.0, $cost), 2);
    }

    /**
     * Leniwe wygaszanie: przeterminowane aktywne -> expired (bez crona).
     * Lazy expiry: overdue active rows -> expired (no cron needed).
     */
    private function expireOverdue(): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $this->db->prepare(
                "SELECT id, player_id, protection_option_id, target_type, target_id, context
                   FROM active_protections WHERE status = 'active' AND ends_at <= ?"
            );
            $stmt->execute([$now]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($rows === []) {
                return;
            }
            $upd = $this->db->prepare("UPDATE active_protections SET status = 'expired', updated_at = ? WHERE id = ?");
            foreach ($rows as $row) {
                $upd->execute([$now, (int)$row['id']]);
                $this->logEvent((int)$row['player_id'], (int)$row['protection_option_id'],
                    (string)$row['target_type'], (int)$row['target_id'], (string)$row['context'],
                    'protection_expired');
            }
        } catch (Throwable $e) {
            GameLog::error('ProtectionService', 'expireOverdue FAILED', $e);
        }
    }

    private function legalLevel(int $playerId): int
    {
        try {
            if ($this->legal === null) {
                // Leniwa konstrukcja - LegalService robi wlasny ensureSchema, wiec tylko
                // poza transakcja (walidacje wolamy przed beginTransaction).
                // Lazy construction - LegalService runs its own ensureSchema, so only
                // outside a transaction (validations run before beginTransaction).
                require_once __DIR__ . '/LegalService.php';
                $this->legal = new LegalService($this->db);
            }
            return $this->legal->getLegalLevelForPlayer($playerId);
        } catch (Throwable $e) {
            GameLog::error('ProtectionService', 'legalLevel FAILED', $e, ['player_id' => $playerId]);
            return 0;
        }
    }

    private function playerCash(int $playerId): float
    {
        try {
            $stmt = $this->db->prepare("SELECT cash FROM players WHERE id = ?");
            $stmt->execute([$playerId]);
            return (float)($stmt->fetchColumn() ?: 0.0);
        } catch (Throwable $e) {
            GameLog::error('ProtectionService', 'playerCash FAILED', $e, ['player_id' => $playerId]);
            return 0.0;
        }
    }

    /**
     * Powiadomienie dyrektora o aktywacji (guarded - brak tabeli nie przerywa operacji).
     * Director notification about activation (guarded - missing table never breaks the flow).
     *
     * @param array<string, mixed> $meta
     */
    private function notifyActivated(int $playerId, string $optionName, string $endsAt, array $meta): void
    {
        try {
            $this->db->prepare(
                "INSERT INTO director_notifications
                    (player_id, type, priority, title, message, icon, requires_action)
                 VALUES (?, 'info', 'low', ?, ?, 'check', 0)"
            )->execute([
                $playerId,
                tPlain('protection.notif.activated.title'),
                tPlain('protection.notif.activated.message', [
                    'name' => $optionName,
                    'target' => (string)($meta['label'] ?? ''),
                    'ends' => $endsAt,
                ]),
            ]);
        } catch (Throwable $e) {
            GameLog::error('ProtectionService', 'notifyActivated FAILED', $e, ['player_id' => $playerId]);
        }
    }
}
