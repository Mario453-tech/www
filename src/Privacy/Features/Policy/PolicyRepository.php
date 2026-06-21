<?php
class PolicyRepository
{
    public function __construct(private readonly PDO $db) {}

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
