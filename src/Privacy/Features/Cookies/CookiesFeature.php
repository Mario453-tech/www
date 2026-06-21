<?php
require_once __DIR__ . '/CookieRepository.php';
require_once __DIR__ . '/CookieService.php';

class CookiesFeature extends AbstractPrivacyFeature
{
    private CookieRepository $repo;
    private CookieService    $service;

    public function __construct(PDO $db, PrivacySettingsService $settings, PrivacyAuditLogger $audit)
    {
        parent::__construct($db, $settings, $audit);
        $this->repo    = new CookieRepository($db);
        $this->service = new CookieService($this->repo, $audit);
    }

    public function getKey(): string   { return 'cookies'; }
    public function getLabel(): string { return t('privacy.feature.cookies_label'); }
    public function getIcon(): string  { return '🍪'; }

    public function handlePost(array $post, int $adminId, string $ip, string $ua): ?array
    {
        $action = (string)($post['action'] ?? '');
        return match ($action) {
            'cookie_create'   => $this->service->create($post, $adminId, $ip, $ua),
            'cookie_update'   => $this->service->update((int)($post['id'] ?? 0), $post, $adminId, $ip, $ua),
            'cookie_activate' => $this->service->toggleActive((int)($post['id'] ?? 0), true,  $adminId, $ip, $ua),
            'cookie_deactivate' => $this->service->toggleActive((int)($post['id'] ?? 0), false, $adminId, $ip, $ua),
            'cookie_delete'   => $this->service->delete((int)($post['id'] ?? 0), $adminId, $ip, $ua),
            default           => null,
        };
    }

    public function getViewData(array $get): array
    {
        $filters = [
            'category'  => (string)($get['filter_category'] ?? ''),
            'is_active' => $get['filter_active'] ?? '',
        ];
        $editId  = (int)($get['edit'] ?? 0);
        $editRow = $editId ? $this->repo->getById($editId) : null;

        return [
            'cookies'          => $this->repo->getAll($filters),
            'filters'          => $filters,
            'edit_row'         => $editRow,
            'valid_categories' => ['necessary', 'preferences', 'analytics', 'marketing'],
            'valid_types'      => ['cookie', 'local_storage', 'session_storage', 'indexeddb'],
        ];
    }

    public function getViewPath(): string
    {
        return __DIR__ . '/../../../../templates/views/admin/privacy/tab_cookies.php';
    }
}
