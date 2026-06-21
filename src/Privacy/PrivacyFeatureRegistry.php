<?php
require_once __DIR__ . '/PrivacyFeatureInterface.php';
require_once __DIR__ . '/AbstractPrivacyFeature.php';
require_once __DIR__ . '/PrivacySettingsService.php';
require_once __DIR__ . '/PrivacyAuditLogger.php';
require_once __DIR__ . '/PrivacyPolicyService.php';
require_once __DIR__ . '/Features/Cookies/CookiesFeature.php';
require_once __DIR__ . '/Features/Consents/ConsentsFeature.php';
require_once __DIR__ . '/Features/Policy/PolicyFeature.php';
require_once __DIR__ . '/Features/Banner/BannerSettingsFeature.php';

/**
 * Rejestr podmodułów prywatności.
 *
 * Żeby dodać nowy podmoduł w przyszłości:
 * 1. Stwórz nowy folder src/Privacy/Features/NazwaModulu/
 * 2. Stwórz klasę NazwaModuluFeature implementującą PrivacyFeatureInterface
 * 3. Dodaj jedną linię w metodzie build() poniżej
 * — i to wszystko. Zakładka w panelu admina pojawi się automatycznie.
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

    /** Zwraca wszystkie zarejestrowane podmoduły (włączone i wyłączone). */
    public function all(): array
    {
        return $this->features;
    }

    /** Zwraca tylko aktywne podmoduły — te które pokazujemy w panelu. */
    public function getEnabled(): array
    {
        return array_filter($this->features, fn($f) => $f->isEnabled());
    }

    /**
     * Fabryka — buduje rejestr ze wszystkimi podmodułami.
     * To jedyne miejsce gdzie są wymienione wszystkie podmoduły.
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

        // Tutaj dodaj kolejne podmoduły w przyszłości, np.:
        // $registry->register(new PrivacyRequestsFeature($db, $settings, $audit));
        // $registry->register(new RetentionFeature($db, $settings, $audit));

        return $registry;
    }
}
