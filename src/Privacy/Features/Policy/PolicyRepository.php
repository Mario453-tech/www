<?php
/** @phpstan-type PolicyRow array{id: int|string, policy_type: string, version: string, title: string, content: string, is_active: int|string, published_at: string|null, created_at: string, updated_at: string} */
class PolicyRepository
{
    public function __construct(private readonly PDO $db) {}

    /** @return list<PolicyRow> */
    public function getAll(): array
    {
        try {
            return $this->db->query(
                "SELECT * FROM privacy_policy_versions ORDER BY policy_type, created_at DESC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    /** @return PolicyRow|null */
    public function getById(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM privacy_policy_versions WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
