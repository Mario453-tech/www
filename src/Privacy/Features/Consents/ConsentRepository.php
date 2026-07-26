<?php
/**
 * Dostep do bazy danych dla zgod uzytkownikow.
 *
 * @phpstan-type ConsentFilter array{date_from?: string, date_to?: string, consent_version?: string, source?: string, player_id?: int}
 * @phpstan-type ConsentRow array{id: int|string, player_id: int|string|null, anonymous_token: string, consent_version: string, banner_version: string, accepted_categories_json: string, rejected_categories_json: string, source: string, ip_address: string|null, user_agent: string|null, created_at: string, updated_at: string, withdrawn_at: string|null, username: string|null}
 */
class ConsentRepository
{
    public function __construct(private readonly PDO $db) {}

    /**
     * @param ConsentFilter $filters
     * @return array{rows: list<ConsentRow>, total: int, pages: int}
     */
    public function getList(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[]  = 'c.created_at >= ?';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[]  = 'c.created_at <= ?';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['consent_version'])) {
            $where[]  = 'c.consent_version = ?';
            $params[] = $filters['consent_version'];
        }
        if (!empty($filters['source'])) {
            $where[]  = 'c.source = ?';
            $params[] = $filters['source'];
        }
        if (!empty($filters['player_id'])) {
            $where[]  = 'c.player_id = ?';
            $params[] = (int)$filters['player_id'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset      = ($page - 1) * $perPage;

        try {
            $totalStmt = $this->db->prepare(
                "SELECT COUNT(*) FROM cookie_consents c $whereClause"
            );
            $totalStmt->execute($params);
            $total = (int)$totalStmt->fetchColumn();

            $stmt = $this->db->prepare("
                SELECT c.*, p.username
                FROM cookie_consents c
                LEFT JOIN players p ON p.id = c.player_id
                $whereClause
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([...$params, $perPage, $offset]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return ['rows' => $rows, 'total' => $total, 'pages' => (int)ceil($total / $perPage)];
        } catch (Throwable) {
            return ['rows' => [], 'total' => 0, 'pages' => 0];
        }
    }

    /** @return ConsentRow|null */
    public function getById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, p.username
                FROM cookie_consents c
                LEFT JOIN players p ON p.id = c.player_id
                WHERE c.id = ? LIMIT 1
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<string> */
    public function getVersions(): array
    {
        try {
            return $this->db->query(
                "SELECT DISTINCT consent_version FROM cookie_consents ORDER BY consent_version DESC"
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            return [];
        }
    }
}
