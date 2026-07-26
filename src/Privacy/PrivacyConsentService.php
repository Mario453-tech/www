<?php
/**
 * Rdzen zarzadzania zgodami.
 * Decyduje czy pokazac baner, odczytuje ostatnia zgode gracza/goscia,
 * zapisuje nowa zgode.
 *
 * @phpstan-type ConsentRow array{id: int|string, player_id: int|string|null, anonymous_token: string, consent_version: string, banner_version: string, accepted_categories_json: string, rejected_categories_json: string, source: string, ip_address: string|null, user_agent: string|null, created_at: string, updated_at: string, withdrawn_at: string|null}
 */
class PrivacyConsentService
{
    public function __construct(
        private readonly PDO                    $db,
        private readonly PrivacySettingsService $settings
    ) {}

    /**
     * Czy baner powinien sie teraz pokazac?
     * Sprawdza: czy modul jest wlaczony, czy zgoda istnieje, czy wersje sie zgadzaja.
     */
    public function shouldShowBanner(?int $playerId, string $anonymousToken): bool
    {
        if (!(bool)$this->settings->get('privacy.banner.enabled', true)) {
            return false;
        }
        $consent = $this->getActiveConsent($playerId, $anonymousToken);
        if ($consent === null) {
            return true;
        }
        $requiredBannerVersion  = (string)$this->settings->get('privacy.banner.version', '1.0');
        $requiredConsentVersion = (string)$this->settings->get('privacy.cookies.policy_version', '1.0');
        $forceReconsent         = (bool)$this->settings->get('privacy.banner.force_reconsent', false);
        $reconsentOnPolicy      = (bool)$this->settings->get('privacy.cookies.reconsent_after_policy_change', true);

        if ($forceReconsent) {
            return true;
        }
        if ($consent['banner_version'] !== $requiredBannerVersion) {
            return true;
        }
        if ($reconsentOnPolicy && $consent['consent_version'] !== $requiredConsentVersion) {
            return true;
        }
        return false;
    }

    /**
     * Ostatnia aktywna (nie wycofana) zgoda gracza lub goscia.
     *
     * @return ConsentRow|null
     */
    public function getActiveConsent(?int $playerId, string $anonymousToken): ?array
    {
        try {
            if ($playerId) {
                $stmt = $this->db->prepare("
                    SELECT * FROM cookie_consents
                    WHERE player_id = ? AND withdrawn_at IS NULL
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute([$playerId]);
            } else {
                if ($anonymousToken === '') return null;
                $stmt = $this->db->prepare("
                    SELECT * FROM cookie_consents
                    WHERE anonymous_token = ? AND player_id IS NULL AND withdrawn_at IS NULL
                    ORDER BY created_at DESC LIMIT 1
                ");
                $stmt->execute([$anonymousToken]);
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Zapisuje zgode uzytkownika.
     * Zwraca id nowo zapisanej zgody.
     *
     * @param list<string> $acceptedCategories
     * @param list<string> $rejectedCategories
     */
    public function saveConsent(
        ?int   $playerId,
        string $anonymousToken,
        array  $acceptedCategories,
        array  $rejectedCategories,
        string $source = 'banner',
        string $ip     = '',
        string $ua     = ''
    ): int {
        $consentVersion = (string)$this->settings->get('privacy.cookies.policy_version', '1.0');
        $bannerVersion  = (string)$this->settings->get('privacy.banner.version', '1.0');

        try {
            $stmt = $this->db->prepare("
                INSERT INTO cookie_consents
                    (player_id, anonymous_token, consent_version, banner_version,
                     accepted_categories_json, rejected_categories_json,
                     source, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $playerId ?: null,
                $anonymousToken,
                $consentVersion,
                $bannerVersion,
                json_encode($acceptedCategories),
                json_encode($rejectedCategories),
                $source,
                $ip ?: null,
                $ua ?: null,
            ]);
            return (int)$this->db->lastInsertId();
        } catch (Throwable $e) {
            GameLog::error('Privacy', 'saveConsent FAILED', $e);
            return 0;
        }
    }

    /**
     * Wycofuje aktywna zgode (np. gdy gracz kliknie "cofnij zgode").
     */
    public function withdrawConsent(?int $playerId, string $anonymousToken): void
    {
        try {
            if ($playerId) {
                $this->db->prepare("
                    UPDATE cookie_consents SET withdrawn_at = NOW()
                    WHERE player_id = ? AND withdrawn_at IS NULL
                ")->execute([$playerId]);
            } elseif ($anonymousToken !== '') {
                $this->db->prepare("
                    UPDATE cookie_consents SET withdrawn_at = NOW()
                    WHERE anonymous_token = ? AND player_id IS NULL AND withdrawn_at IS NULL
                ")->execute([$anonymousToken]);
            }
        } catch (Throwable $e) {
            GameLog::error('Privacy', 'withdrawConsent FAILED', $e);
        }
    }

    /**
     * Sprawdza czy dana kategoria cookies jest zaakceptowana przez uzytkownika.
     */
    public function isCategoryAccepted(?int $playerId, string $anonymousToken, string $category): bool
    {
        if ($category === 'necessary') {
            return true;
        }
        $consent = $this->getActiveConsent($playerId, $anonymousToken);
        if ($consent === null) {
            return false;
        }
        $accepted = json_decode($consent['accepted_categories_json'], true) ?? [];
        return in_array($category, $accepted, true);
    }

    /**
     * Generuje token dla anonimowego goscia i zapisuje w sesji.
     */
    public static function getOrCreateAnonymousToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['privacy_anon_token'])) {
            $_SESSION['privacy_anon_token'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['privacy_anon_token'];
    }
}
