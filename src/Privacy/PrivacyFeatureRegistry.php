<?php
require_once __DIR__ . '/PrivacyFeatureInterface.php';
require_once __DIR__ . '/AbstractPrivacyFeature.php';
require_once __DIR__ . '/PrivacySettingsService.php';
require_once __DIR__ . '/PrivacyAuditLogger.php';
require_once __DIR__ . '/PrivacyPolicyService.php';
require_once __DIR__ . '/PrivacyConsentService.php';
require_once __DIR__ . '/PrivacyBannerService.php';
require_once __DIR__ . '/Features/Cookies/CookiesFeature.php';
require_once __DIR__ . '/Features/Consents/ConsentsFeature.php';
require_once __DIR__ . '/Features/Policy/PolicyFeature.php';
require_once __DIR__ . '/Features/Banner/BannerSettingsFeature.php';

/**
 * Rejestr podmodulow prywatnosci.
 *
 * Zeby dodac nowy podmodul w przyszlosci:
 * 1. Stworz nowy folder src/Privacy/Features/NazwaModulu/
 * 2. Stworz klase NazwaModuluFeature implementujaca PrivacyFeatureInterface
 * 3. Dodaj jedna linie w metodzie build() ponizej
 * - i to wszystko. Zakladka w panelu admina pojawi sie automatycznie.
 */
class PrivacyFeatureRegistry
{
    /** @var PrivacyFeatureInterface[] */
    private array $features = [];

    public function register(PrivacyFeatureInterface $feature): void
    {
        $this->features[$feature->getKey()] = $feature;
    }

    public function get(string $key): ?PrivacyFeatureInterface
    {
        return $this->features[$key] ?? null;
    }

    /** Zwraca wszystkie zarejestrowane podmoduly (wlaczone i wylaczone). */
    public function all(): array
    {
        return $this->features;
    }

    /** Zwraca tylko aktywne podmoduly - te ktore pokazujemy w panelu. */
    public function getEnabled(): array
    {
        return array_filter($this->features, fn($f) => $f->isEnabled());
    }

    /**
     * Fabryka - buduje rejestr ze wszystkimi podmodulami.
     * To jedyne miejsce gdzie sa wymienione wszystkie podmoduly.
     */
    public static function build(PDO $db): self
    {
        $settings = new PrivacySettingsService($db);
        $audit    = new PrivacyAuditLogger($db);

        $registry = new self();
        $registry->register(new CookiesFeature($db, $settings, $audit));
        $registry->register(new ConsentsFeature($db, $settings, $audit));
        $registry->register(new PolicyFeature($db, $settings, $audit));
        $registry->register(new BannerSettingsFeature($db, $settings, $audit));

        // Tutaj dodaj kolejne podmoduly w przyszlosci, np.:
        // $registry->register(new PrivacyRequestsFeature($db, $settings, $audit));
        // $registry->register(new RetentionFeature($db, $settings, $audit));

        return $registry;
    }
}
