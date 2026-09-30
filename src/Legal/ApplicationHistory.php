<?php
declare(strict_types=1);

/**
 * Preserve completed attempts before the current application is replaced.
 * Zachowaj poprzednie proby przed podmiana biezacego wniosku.
 */
final class LegalApplicationHistory
{
    public function __construct(private PDO $db) {}

    public function ensureSchema(): void
    {
        try {
            $this->db->query('SELECT id FROM legal_application_history LIMIT 0');
            $this->ensureFeeReferences();
            return;
        } catch (PDOException $exception) {
            if ($this->db->inTransaction()) throw $exception;
        }
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $this->db->exec("CREATE TABLE IF NOT EXISTS legal_application_history (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                player_id INT UNSIGNED NOT NULL,
                region_id INT UNSIGNED NOT NULL,
                permit_kind VARCHAR(16) NOT NULL,
                status VARCHAR(24) NOT NULL,
                cost DECIMAL(14,2) NOT NULL DEFAULT 0,
                submitted_at DATETIME NULL,
                decision_due_at DATETIME NULL,
                decided_at DATETIME NULL,
                archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_legal_history_player_region (player_id, region_id, permit_kind, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        } else {
            $this->db->exec("CREATE TABLE IF NOT EXISTS legal_application_history (
                id INTEGER PRIMARY KEY AUTOINCREMENT, player_id INTEGER NOT NULL,
                region_id INTEGER NOT NULL, permit_kind TEXT NOT NULL, status TEXT NOT NULL,
                cost NUMERIC NOT NULL DEFAULT 0, submitted_at TEXT NULL, decision_due_at TEXT NULL,
                decided_at TEXT NULL, archived_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)");
        }
        $this->ensureFeeReferences();
    }

    /**
     * Add nullable audit references without changing old application records.
     * Dodaj opcjonalne referencje audytu bez zmiany starych wnioskow.
     */
    private function ensureFeeReferences(): void
    {
        $mysql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        foreach (['legal_application_history', 'drilling_permit_applications', 'hub_permit_applications'] as $table) {
            try {
                $this->db->query("SELECT player_id FROM {$table} LIMIT 0");
            } catch (PDOException $exception) {
                if (!$mysql && str_contains($exception->getMessage(), 'no such table')) continue;
                throw $exception;
            }
            try {
                $this->db->query("SELECT fee_transaction_id FROM {$table} LIMIT 0");
            } catch (PDOException $exception) {
                if ($this->db->inTransaction()) throw $exception;
                $type = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
                try {
                    $this->db->exec("ALTER TABLE {$table} ADD COLUMN fee_transaction_id {$type} NULL");
                } catch (PDOException $race) {
                    $this->db->query("SELECT fee_transaction_id FROM {$table} LIMIT 0");
                }
            }
        }
    }

    /**
     * Application outcome differs from the retained transitional access permit.
     * Wynik wniosku jest niezalezny od zachowanego dostepu przejsciowego.
     * @param array<string,mixed> $application
     */
    public static function applicationStatus(array $application): string
    {
        if (($application['status'] ?? '') === 'transitional'
            && empty($application['upgrade_pending'])
            && !empty($application['upgrade_decision_due_at'])
            && !empty($application['decided_at'])) {
            return 'no_decision';
        }
        return (string)($application['status'] ?? 'none');
    }

    /** @param array<string,mixed> $application */
    public function archive(int $playerId, int $regionId, string $kind, array $application): void
    {
        if (!$this->db->inTransaction()) throw new LogicException('Application archive requires a transaction');
        if ((int)$application['player_id'] !== $playerId || (int)$application['region_id'] !== $regionId) {
            throw new LogicException('Application archive owner mismatch');
        }
        $this->db->prepare('INSERT INTO legal_application_history
            (player_id, region_id, permit_kind, status, cost, submitted_at, decision_due_at, decided_at, fee_transaction_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                $playerId, $regionId, $kind, self::applicationStatus($application), $application['cost'] ?? 0,
                $application['submitted_at'] ?? null, $application['upgrade_decision_due_at'] ?? $application['decision_due_at'] ?? null,
                $application['decided_at'] ?? null, $application['fee_transaction_id'] ?? null,
            ]);
    }

    /**
     * Read only the authenticated player's archive and verified earlier fee records.
     * Czytaj archiwum gracza i potwierdzone oplaty wczesniejszych wnioskow.
     * Fee records do not reconstruct missing decisions.
     * Wpisy oplat nie odtwarzaja niezapisanych decyzji.
     * @param array<int,array<string,array<string,mixed>|null>> $current
     * @return array<int,array<string,list<array<string,mixed>>>>
     */
    public function forPlayer(int $playerId, array $current): array
    {
        $query = $this->db->prepare('SELECT region_id, permit_kind, status, cost, submitted_at, decision_due_at, decided_at, fee_transaction_id
            FROM legal_application_history WHERE player_id = ? ORDER BY id DESC');
        $query->execute([$playerId]);
        $result = [];
        foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(int)$row['region_id']][$row['permit_kind']][] = $row + ['source' => 'archive'];
        }
        try {
            $fees = (new FinancialTransactionService($this->db))->legalFeesForPlayer($playerId);
        } catch (PDOException $exception) {
            if (class_exists('GameLog', false)) GameLog::error('LegalApplicationHistory', 'Legacy fee history unavailable', $exception, ['player_id' => $playerId]);
            $fees = [];
        }
        $linkedFees = [];
        $attempts = $result;
        foreach ($current as $regionId => $kinds) foreach ($kinds as $kind => $application) {
            if ($application) $attempts[$regionId][$kind][] = $application;
        }
        $legacy = [];
        foreach ($attempts as $regionId => $kinds) foreach ($kinds as $kind => $rows) {
            usort($rows, static fn(array $a, array $b): int => strcmp($b['submitted_at'] ?? '', $a['submitted_at'] ?? ''));
            $nextSubmitted = null;
            foreach ($rows as $row) {
                if (!empty($row['fee_transaction_id'])) $linkedFees[(int)$row['fee_transaction_id']] = true;
                $submitted = $row['submitted_at'] ?? null;
                if (!$submitted) continue;
                if (empty($row['fee_transaction_id'])) {
                    $legacy[$regionId][$kind][] = ['from'=>$submitted, 'to'=>$nextSubmitted, 'cost'=>$row['cost'] ?? null, 'used'=>false];
                }
                $nextSubmitted = $submitted;
            }
        }
        foreach ($fees as $fee) {
            if (isset($linkedFees[(int)$fee['id']])) continue;
            $regionId = (int)$fee['reference_id'];
            $kind = $fee['reference_type'] === 'legal_region' ? 'drilling' : 'local';
            // Legacy records have no audit ID: match at most one fee inside the attempt's lifetime.
            // Stare rekordy nie maja ID audytu: dopasuj najwyzej jedna oplate w czasie trwania proby.
            foreach ($legacy[$regionId][$kind] ?? [] as $index => $attempt) {
                if (!$attempt['used'] && $fee['created_at'] >= $attempt['from']
                    && ($attempt['to'] === null || $fee['created_at'] < $attempt['to'])
                    && ($attempt['cost'] === null || (float)$attempt['cost'] === 0.0 || round((float)$attempt['cost'], 2) === round((float)$fee['amount'], 2))) {
                    $legacy[$regionId][$kind][$index]['used'] = true;
                    continue 2;
                }
            }
            $result[$regionId][$kind][] = ['source' => 'fee', 'status' => 'submitted',
                'cost' => $fee['amount'], 'submitted_at' => $fee['created_at'],
                'decision_due_at' => null, 'decided_at' => null];
        }
        foreach ($result as &$kinds) foreach ($kinds as &$rows) {
            usort($rows, static fn(array $a, array $b): int => strcmp($b['submitted_at'] ?? '', $a['submitted_at'] ?? ''));
        }
        unset($kinds, $rows);
        return $result;
    }
}
