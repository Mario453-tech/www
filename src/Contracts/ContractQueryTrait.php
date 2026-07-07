<?php
declare(strict_types=1);

/**
 * Query and helper methods for ContractService.
 * Metody zapytan i helpery dla ContractService.
 */
trait ContractQueryTrait
{
    /** @return list<array<string,mixed>> */
    public function listActiveContracts(int $playerId): array
    {
        return $this->fetchList(
            "SELECT * FROM player_contracts WHERE player_id = ? AND status = 'active' ORDER BY next_delivery_at ASC, id ASC",
            [$playerId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listDeliveries(int $playerId, int $limit = 50): array
    {
        return $this->fetchList(
            "SELECT * FROM contract_deliveries WHERE player_id = ? ORDER BY created_at DESC, id DESC LIMIT " . $this->limit($limit),
            [$playerId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listLogs(int $playerId, int $limit = 50): array
    {
        return $this->fetchList(
            "SELECT * FROM contract_logs WHERE player_id = ? ORDER BY created_at DESC, id DESC LIMIT " . $this->limit($limit),
            [$playerId]
        );
    }

    private function ensureConfig(): void
    {
        try {
            if ($this->driver() === 'sqlite') {
                $this->db->prepare(
                    "INSERT OR IGNORE INTO well_config (`key`, `value`, label, category) VALUES (?, '0', ?, ?)"
                )->execute([self::CFG_MODULE_ENABLED, self::CFG_LABEL_MODULE_ENABLED, self::CFG_CATEGORY]);
                return;
            }
            $this->db->prepare(
                "INSERT INTO well_config (`key`, `value`, label, category)
                 SELECT ?, '0', ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM well_config WHERE `key` = ?)"
            )->execute([self::CFG_MODULE_ENABLED, self::CFG_LABEL_MODULE_ENABLED, self::CFG_CATEGORY, self::CFG_MODULE_ENABLED]);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractService', 'ensureConfig FAILED', $e);
            }
        }
    }

    /** @return array<string,mixed>|null */
    private function optionById(int $optionId): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM contract_options WHERE id = ? LIMIT 1");
            $stmt->execute([$optionId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** @return array<string,array{type:string,value:float,text:?string}> */
    private function termsForOption(int $optionId): array
    {
        return $this->termsForMany([$optionId])[$optionId] ?? [];
    }

    /**
     * @param list<int> $optionIds
     * @return array<int,array<string,array{type:string,value:float,text:?string}>>
     */
    private function termsForMany(array $optionIds): array
    {
        $optionIds = array_values(array_unique(array_filter(array_map('intval', $optionIds), static fn(int $id): bool => $id > 0)));
        if ($optionIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
        $stmt = $this->db->prepare("SELECT * FROM contract_terms WHERE contract_option_id IN ({$placeholders}) ORDER BY contract_option_id, term_key");
        $stmt->execute($optionIds);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['contract_option_id']][(string)$row['term_key']] = [
                'type' => (string)$row['term_type'],
                'value' => (float)($row['term_value'] ?? 0.0),
                'text' => $row['term_text'] === null ? null : (string)$row['term_text'],
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $option
     * @param array<string,array{type:string,value:float,text:?string}> $terms
     */
    private function lockedReason(array $option, int $score, int $legalLevel, array $terms = [], ?int $contractReputation = null): ?string
    {
        if ($credibilityScore < (int)$option['min_credibility']) {
            return 'credibility';
        }
        if ($legalLevel < (int)$option['requires_legal_level']) {
            return 'legal_level';
        }
        $minContractReputation = (int)round((float)($terms['min_contract_reputation']['value'] ?? 0.0));
        if ($minContractReputation > 0 && ($contractReputation ?? 50) < $minContractReputation) {
            return 'contract_reputation';
        }
        return null;
    }

    /** @param array<string,array{type:string,value:float,text:?string}> $terms */
    private function validateRequiredTerms(array $terms): ?string
    {
        foreach (['total_bbl', 'delivery_bbl', 'delivery_interval_minutes', 'duration_minutes'] as $key) {
            if (!isset($terms[$key]) || $terms[$key]['value'] <= 0) {
                return 'missing_term_' . $key;
            }
        }
        if ($terms['delivery_bbl']['value'] > $terms['total_bbl']['value']) {
            return 'delivery_gt_total';
        }
        return null;
    }

    private function activeContractCount(int $playerId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM player_contracts WHERE player_id = ? AND status = 'active'");
        $stmt->execute([$playerId]);
        return (int)$stmt->fetchColumn();
    }

    private function lockPlayerRow(int $playerId): bool
    {
        $forUpdate = $this->driver() === 'sqlite' ? '' : ' FOR UPDATE';
        $stmt = $this->db->prepare("SELECT id FROM players WHERE id = ? LIMIT 1{$forUpdate}");
        $stmt->execute([$playerId]);
        return $stmt->fetchColumn() !== false;
    }

    private function playerExists(int $playerId): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT 1 FROM players WHERE id = ? LIMIT 1");
            $stmt->execute([$playerId]);
            return $stmt->fetchColumn() !== false;
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractService', 'playerExists FAILED', $e, ['player_id' => $playerId]);
            }
            return false;
        }
    }

    /** @return array<string,mixed>|null */
    private function contractForUpdate(int $playerId, int $contractId): ?array
    {
        $forUpdate = $this->driver() === 'sqlite' ? '' : ' FOR UPDATE';
        $stmt = $this->db->prepare("SELECT * FROM player_contracts WHERE id = ? AND player_id = ? LIMIT 1{$forUpdate}");
        $stmt->execute([$contractId, $playerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $option */
    private function isExpired(array $option): bool
    {
        $expires = $option['expires_at'] ?? null;
        return $expires !== null && $expires !== '' && strtotime((string)$expires) <= time();
    }

    private function legalLevel(int $playerId): int
    {
        try {
            $this->legal ??= new LegalService($this->db);
            return $this->legal->getLegalLevelForPlayer($playerId);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractService', 'legalLevel FAILED', $e, ['player_id' => $playerId]);
            }
            return 0;
        }
    }

    /** @param array<string,mixed> $meta */
    private function logEvent(int $playerId, ?int $contractId, string $targetType, ?int $targetId, string $context, string $eventKey, string $message, array $meta = []): void
    {
        $this->db->prepare(
            "INSERT INTO contract_logs
                (player_contract_id, player_id, target_type, target_id, context, event_key, message, meta_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $contractId,
            $playerId,
            $targetType,
            $targetId,
            $context,
            $eventKey,
            $message,
            $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $this->nowString(),
        ]);
    }

    /** @param list<mixed> $params @return list<array<string,mixed>> */
    private function fetchList(string $sql, array $params): array
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('ContractService', 'fetchList FAILED', $e);
            }
            return [];
        }
    }

    /** @return array{success:bool,status:string,message_key:string} */
    private function result(bool $success, string $status): array
    {
        return ['success' => $success, 'status' => $status, 'message_key' => 'contracts.' . $status];
    }

    private function driver(): string
    {
        return (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function nowString(): string
    {
        // SQLite: PHP clock is fine (same process, no timezone gap).
        // MySQL: fetch NOW() from the server so writes and WHERE NOW() comparisons share the same clock (rule #14).
        if ($this->driver() === 'sqlite') {
            return date('Y-m-d H:i:s');
        }
        try {
            $row = $this->db->query("SELECT NOW() AS n")->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? (string)$row['n'] : date('Y-m-d H:i:s');
        } catch (Throwable) {
            return date('Y-m-d H:i:s');
        }
    }

    private function datePlusMinutes(string $date, int $minutes): string
    {
        return date('Y-m-d H:i:s', strtotime($date) + max(1, $minutes) * 60);
    }

    private function limit(int $limit): int
    {
        return max(1, min(200, $limit));
    }

    /**
     * Oblicza cene za bbl na podstawie trybu cenowego kontraktu.
     * Calculates price per bbl based on contract price mode.
     *
     * @param array<string,array{type:string,value:float,text:?string}> $terms
     */
    private function calculatePrice(array $terms, float $marketPrice): float
    {
        $mode       = (string)($terms['price_mode']['text'] ?? 'market_plus_bonus');
        $multiplier = (float)($terms['price_multiplier']['value'] ?? 1.0);
        $bonusPct   = (float)($terms['bonus_pct']['value'] ?? 0.0);
        $fixedPrice = (float)($terms['fixed_price']['value'] ?? 0.0);

        return match($mode) {
            'fixed'              => max(0.0, $fixedPrice),
            'market_multiplier'  => round($marketPrice * max(0.0, $multiplier), 2),
            default              => round($marketPrice * (1.0 + $bonusPct / 100.0), 2),
        };
    }
}
