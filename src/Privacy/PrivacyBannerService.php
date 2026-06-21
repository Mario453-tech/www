<?php
/**
 * Dostarcza dane potrzebne do wyrenderowania banera cookies.
 */
class PrivacyBannerService
{
    public function __construct(
        private readonly PDO                    $db,
        private readonly PrivacySettingsService $settings
    ) {}

    /**
     * Zwraca wszystkie dane potrzebne widokowi banera.
     */
    public function getBannerData(): array
    {
        return [
            'heading'             => (string)$this->settings->get('privacy.banner.heading',          'Twoja prywatność ma znaczenie'),
            'description'         => (string)$this->settings->get('privacy.banner.description',      ''),
            'btn_accept_all'      => (string)$this->settings->get('privacy.banner.btn_accept_all',   'Akceptuję wszystkie'),
            'btn_necessary_only'  => (string)$this->settings->get('privacy.banner.btn_necessary_only','Tylko niezbędne'),
            'btn_settings'        => (string)$this->settings->get('privacy.banner.btn_settings',     'Ustawienia'),
            'show_decline_button' => (bool)  $this->settings->get('privacy.banner.show_decline_button', true),
            'policy_url'          => (string)$this->settings->get('privacy.banner.policy_url',       '/cookies-policy.php'),
            'privacy_url'         => (string)$this->settings->get('privacy.banner.privacy_url',      '/privacy-policy.php'),
            'categories'          => $this->getActiveCategories(),
        ];
    }

    /**
     * Zwraca kategorie cookies z opisami, pogrupowane.
     * Kategoria 'necessary' zawsze na pierwszym miejscu.
     */
    public function getActiveCategories(): array
    {
        $labels = [
            'necessary'   => t('privacy.category.necessary'),
            'preferences' => t('privacy.category.preferences'),
            'analytics'   => t('privacy.category.analytics'),
            'marketing'   => t('privacy.category.marketing'),
        ];
        $descriptions = [
            'necessary'   => t('privacy.category.necessary_desc'),
            'preferences' => t('privacy.category.preferences_desc'),
            'analytics'   => t('privacy.category.analytics_desc'),
            'marketing'   => t('privacy.category.marketing_desc'),
        ];
        $required = ['necessary'];

        $order = ['necessary', 'preferences', 'analytics', 'marketing'];
        $categories = [];
        try {
            $rows = $this->db->query("
                SELECT DISTINCT category FROM cookie_definitions
                WHERE is_active = 1 ORDER BY FIELD(category,'necessary','preferences','analytics','marketing')
            ")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable) {
            $rows = [];
        }
        $present = array_flip($rows);
        foreach ($order as $cat) {
            if (!isset($present[$cat])) continue;
            $categories[] = [
                'key'         => $cat,
                'label'       => $labels[$cat]       ?? $cat,
                'description' => $descriptions[$cat] ?? '',
                'required'    => in_array($cat, $required, true),
            ];
        }
        return $categories;
    }
}
