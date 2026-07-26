<?php
class BannerSettingsService
{
    /** @var list<string> */
    private static array $boolKeys = [
        'privacy.banner.enabled',
        'privacy.banner.force_reconsent',
        'privacy.banner.show_decline_button',
        'privacy.cookies.reconsent_after_policy_change',
    ];

    public function __construct(private readonly PrivacySettingsService $settings) {}

    /** @return array<string, mixed> */
    public function getAll(): array
    {
        return $this->settings->all();
    }

    /** @param array<string, mixed> $post */
    public function saveFromPost(array $post): void
    {
        $stringKeys = [
            'privacy.banner.version',
            'privacy.banner.heading',
            'privacy.banner.description',
            'privacy.banner.btn_accept_all',
            'privacy.banner.btn_necessary_only',
            'privacy.banner.btn_settings',
            'privacy.banner.policy_url',
            'privacy.banner.privacy_url',
            'privacy.cookies.policy_version',
        ];

        foreach ($stringKeys as $key) {
            $field = str_replace('.', '_', $key);
            if (array_key_exists($field, $post)) {
                $this->settings->set($key, trim((string)$post[$field]), 'string');
            }
        }
        foreach (self::$boolKeys as $key) {
            $field = str_replace('.', '_', $key);
            $this->settings->set($key, isset($post[$field]) ? '1' : '0', 'boolean');
        }
    }
}
