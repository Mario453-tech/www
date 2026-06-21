<?php
/**
 * Logika biznesowa zarzadzania definicjami cookies.
 */
class CookieService
{
    private static array $validCategories = ['necessary', 'preferences', 'analytics', 'marketing'];
    private static array $validTypes      = ['cookie', 'local_storage', 'session_storage', 'indexeddb'];

    public function __construct(
        private readonly CookieRepository    $repo,
        private readonly PrivacyAuditLogger  $audit
    ) {}

    public function create(array $input, int $adminId, string $ip, string $ua): array
    {
        $data = $this->validate($input);
        if (isset($data['error'])) return ['success' => false, 'message' => $data['error']];

        $id = $this->repo->create($data);
        $this->audit->log($adminId, 'cookie_create', 'cookie_definition', $id, null, $data, $ip, $ua);
        return ['success' => true, 'message' => t('privacy.cookies.msg_created'), 'id' => $id];
    }

    public function update(int $id, array $input, int $adminId, string $ip, string $ua): array
    {
        $old = $this->repo->getById($id);
        if (!$old) return ['success' => false, 'message' => t('privacy.cookies.msg_not_found')];

        $data = $this->validate($input);
        if (isset($data['error'])) return ['success' => false, 'message' => $data['error']];

        $this->repo->update($id, $data);
        $this->audit->log($adminId, 'cookie_update', 'cookie_definition', $id, $old, $data, $ip, $ua);
        return ['success' => true, 'message' => t('privacy.cookies.msg_updated')];
    }

    public function toggleActive(int $id, bool $active, int $adminId, string $ip, string $ua): array
    {
        $old = $this->repo->getById($id);
        if (!$old) return ['success' => false, 'message' => t('privacy.cookies.msg_not_found')];

        $this->repo->setActive($id, $active);
        $action = $active ? 'cookie_activate' : 'cookie_deactivate';
        $this->audit->log($adminId, $action, 'cookie_definition', $id, ['is_active' => (int)!$active], ['is_active' => (int)$active], $ip, $ua);
        return ['success' => true, 'message' => $active ? t('privacy.cookies.msg_activated') : t('privacy.cookies.msg_deactivated')];
    }

    public function delete(int $id, int $adminId, string $ip, string $ua): array
    {
        $old = $this->repo->getById($id);
        if (!$old) return ['success' => false, 'message' => t('privacy.cookies.msg_not_found')];
        if ($old['is_required']) return ['success' => false, 'message' => t('privacy.cookies.msg_required_nodelete')];

        $this->repo->delete($id);
        $this->audit->log($adminId, 'cookie_delete', 'cookie_definition', $id, $old, null, $ip, $ua);
        return ['success' => true, 'message' => t('privacy.cookies.msg_deleted')];
    }

    private function validate(array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') return ['error' => t('privacy.cookies.err_name_required')];
        if (mb_strlen($name) > 200) return ['error' => t('privacy.cookies.err_name_too_long')];

        $category = (string)($input['category'] ?? '');
        if (!in_array($category, self::$validCategories, true)) {
            return ['error' => t('privacy.cookies.err_invalid_category')];
        }

        $type = (string)($input['type'] ?? 'cookie');
        if (!in_array($type, self::$validTypes, true)) {
            $type = 'cookie';
        }

        return [
            'name'        => $name,
            'category'    => $category,
            'provider'    => mb_substr(trim((string)($input['provider']   ?? '')), 0, 200),
            'purpose'     => mb_substr(trim((string)($input['purpose']    ?? '')), 0, 2000),
            'duration'    => mb_substr(trim((string)($input['duration']   ?? '')), 0, 100),
            'type'        => $type,
            'is_required' => isset($input['is_required']) ? 1 : 0,
            'is_active'   => isset($input['is_active'])   ? 1 : 0,
            'cookie_key'  => mb_substr(trim((string)($input['cookie_key'] ?? '')), 0, 200),
            'notes'       => mb_substr(trim((string)($input['notes']      ?? '')), 0, 5000) ?: null,
        ];
    }
}
