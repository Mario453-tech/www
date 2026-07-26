<?php
/**
 * Zarzadzanie wersjami polityk prywatnosci i cookies.
 *
 * @phpstan-type PolicyRow array{id: int|string, policy_type: string, version: string, title: string, content: string, is_active: int|string, published_at: string|null, created_at: string, updated_at: string}
 */
class PrivacyPolicyService
{
    public function __construct(private readonly PDO $db) {}

    /**
     * Aktywna wersja polityki danego typu ('cookies' lub 'privacy').
     *
     * @return PolicyRow|null
     */
    public function getActive(string $policyType): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM privacy_policy_versions
                WHERE policy_type = ? AND is_active = 1
                ORDER BY published_at DESC LIMIT 1
            ");
            $stmt->execute([$policyType]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Lista wszystkich wersji danego typu, najnowsze najpierw.
     *
     * @return list<PolicyRow>
     */
    public function getAll(string $policyType): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM privacy_policy_versions
                WHERE policy_type = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$policyType]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Aktywuje wybrana wersje (dezaktywuje pozostale tego samego typu).
     */
    public function activate(int $id): bool
    {
        try {
            $row = $this->db->prepare("SELECT policy_type FROM privacy_policy_versions WHERE id = ? LIMIT 1");
            $row->execute([$id]);
            $policy = $row->fetch(PDO::FETCH_ASSOC);
            if (!$policy) return false;

            $this->db->beginTransaction();
            $this->db->prepare("
                UPDATE privacy_policy_versions SET is_active = 0 WHERE policy_type = ?
            ")->execute([$policy['policy_type']]);
            $this->db->prepare("
                UPDATE privacy_policy_versions SET is_active = 1, published_at = NOW() WHERE id = ?
            ")->execute([$id]);
            $this->db->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            GameLog::error('Privacy', 'PolicyService::activate FAILED', $e);
            return false;
        }
    }

    /**
     * Tworzy nowa wersje polityki (nieaktywna).
     */
    public function create(string $policyType, string $version, string $title, string $content): int
    {
        try {
            $this->db->prepare("
                INSERT INTO privacy_policy_versions (policy_type, version, title, content, is_active)
                VALUES (?, ?, ?, ?, 0)
            ")->execute([$policyType, $version, $title, $content]);
            return (int)$this->db->lastInsertId();
        } catch (Throwable $e) {
            GameLog::error('Privacy', 'PolicyService::create FAILED', $e);
            return 0;
        }
    }

    /**
     * Aktualizuje tresc istniejacei wersji (tylko gdy nieaktywna).
     */
    public function update(int $id, string $title, string $content): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE privacy_policy_versions SET title = ?, content = ?
                WHERE id = ? AND is_active = 0
            ");
            $stmt->execute([$title, $content, $id]);
            return $stmt->rowCount() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}
