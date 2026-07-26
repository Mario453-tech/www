<?php
/**
 * Dostep do bazy danych dla definicji cookies.
 *
 * @phpstan-type CookieFilter array{category?: string, is_active?: int|string}
 * @phpstan-type CookieData array{name: string, category: string, provider: string, purpose: string, duration: string, type: string, is_required: int, is_active: int, cookie_key: string, notes: string|null}
 * @phpstan-type CookieRow array{id: int|string, name: string, category: string, provider: string, purpose: string, duration: string, type: string, is_required: int|string, is_active: int|string, cookie_key: string, notes: string|null, created_at: string, updated_at: string}
 */
class CookieRepository
{
    public function __construct(private readonly PDO $db) {}

    /**
     * @param CookieFilter $filters
     * @return list<CookieRow>
     */
    public function getAll(array $filters = []): array
    {
        $where  = [];
        $params = [];
        if (isset($filters['category']) && $filters['category'] !== '') {
            $where[]  = 'category = ?';
            $params[] = $filters['category'];
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[]  = 'is_active = ?';
            $params[] = (int)$filters['is_active'];
        }
        $sql = "SELECT * FROM cookie_definitions"
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY FIELD(category,'necessary','preferences','analytics','marketing'), name";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    /** @return CookieRow|null */
    public function getById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM cookie_definitions WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param CookieData $data */
    public function create(array $data): int
    {
        try {
            $this->db->prepare("
                INSERT INTO cookie_definitions
                    (name, category, provider, purpose, duration, type, is_required, is_active, cookie_key, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $data['name'],
                $data['category'],
                $data['provider']    ?? '',
                $data['purpose']     ?? '',
                $data['duration']    ?? '',
                $data['type']        ?? 'cookie',
                (int)($data['is_required'] ?? 0),
                (int)($data['is_active']   ?? 1),
                $data['cookie_key']  ?? '',
                $data['notes']       ?? null,
            ]);
            return (int)$this->db->lastInsertId();
        } catch (Throwable $e) {
            GameLog::error('Privacy', 'CookieRepository::create FAILED', $e);
            return 0;
        }
    }

    /** @param CookieData $data */
    public function update(int $id, array $data): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE cookie_definitions
                SET name = ?, category = ?, provider = ?, purpose = ?, duration = ?,
                    type = ?, is_required = ?, is_active = ?, cookie_key = ?, notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['category'],
                $data['provider']    ?? '',
                $data['purpose']     ?? '',
                $data['duration']    ?? '',
                $data['type']        ?? 'cookie',
                (int)($data['is_required'] ?? 0),
                (int)($data['is_active']   ?? 1),
                $data['cookie_key']  ?? '',
                $data['notes']       ?? null,
                $id,
            ]);
            return $stmt->rowCount() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function setActive(int $id, bool $active): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE cookie_definitions SET is_active = ? WHERE id = ?");
            $stmt->execute([(int)$active, $id]);
            return $stmt->rowCount() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM cookie_definitions WHERE id = ? AND is_required = 0");
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
