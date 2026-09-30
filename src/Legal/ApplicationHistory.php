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
    }

    /** @param array<string,mixed> $application */
    public function archive(int $playerId, int $regionId, string $kind, array $application): void
    {
        if (!$this->db->inTransaction()) throw new LogicException('Application archive requires a transaction');
        if ((int)$application['player_id'] !== $playerId || (int)$application['region_id'] !== $regionId) {
            throw new LogicException('Application archive owner mismatch');
        }
        $this->db->prepare('INSERT INTO legal_application_history
            (player_id, region_id, permit_kind, status, cost, submitted_at, decision_due_at, decided_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([
                $playerId, $regionId, $kind, $application['status'], $application['cost'] ?? 0,
                $application['submitted_at'] ?? null, $application['decision_due_at'] ?? null,
                $application['decided_at'] ?? null,
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
        $query = $this->db->prepare('SELECT region_id, permit_kind, status, cost, submitted_at, decision_due_at, decided_at
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
        foreach ($fees as $fee) {
            $regionId = (int)$fee['reference_id'];
            $kind = $fee['reference_type'] === 'legal_region' ? 'drilling' : 'local';
            $submitted = $current[$regionId][$kind]['submitted_at'] ?? null;
            $feeTime = strtotime($fee['created_at']);
            if ($submitted && $feeTime >= strtotime($submitted) - 5) continue;
            foreach ($result[$regionId][$kind] ?? [] as $row) {
                if ($row['source'] === 'archive' && $row['submitted_at'] && abs(strtotime($row['submitted_at']) - $feeTime) <= 5) continue 2;
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
