<?php
require_once __DIR__ . '/BannerSettingsService.php';

class BannerSettingsFeature extends AbstractPrivacyFeature
{
    private BannerSettingsService $bannerSvc;

    public function __construct(PDO $db, PrivacySettingsService $settings, PrivacyAuditLogger $audit)
    {
        parent::__construct($db, $settings, $audit);
        $this->bannerSvc = new BannerSettingsService($settings);
    }

    public function getKey(): string   { return 'banner_settings'; }
    public function getLabel(): string { return t('privacy.feature.banner_label'); }
    public function getIcon(): string  { return ''; }

    /**
     * @param array<string, mixed> $post
     * @return array{success: bool, message: string}|null
     */
    public function handlePost(array $post, int $adminId, string $ip, string $ua): ?array
    {
        if ((string)($post['action'] ?? '') !== 'save_banner_settings') {
            return null;
        }
        $old = $this->bannerSvc->getAll();
        $this->bannerSvc->saveFromPost($post);
        $new = $this->bannerSvc->getAll();
        $this->audit->log($adminId, 'banner_settings_save', 'privacy_settings', null, $old, $new, $ip, $ua);
        return ['success' => true, 'message' => t('privacy.banner_settings.msg_saved')];
    }

    /**
     * @param array<string, mixed> $get
     * @return array{all_settings: array<string, mixed>}
     */
    public function getViewData(array $get): array
    {
        return ['all_settings' => $this->bannerSvc->getAll()];
    }

    public function getViewPath(): string
    {
        return __DIR__ . '/../../../../templates/views/admin/privacy/tab_settings.php';
    }
}
