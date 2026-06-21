<?php
/**
 * Klasa bazowa dla podmodulow prywatnosci.
 * Dostarcza wspolne zaleznosci (PDO, Settings, AuditLogger) i domyslna implementacje isEnabled().
 * Nie jest obowiazkowa - feature moze implementowac interfejs bezposrednio.
 */
abstract class AbstractPrivacyFeature implements PrivacyFeatureInterface
{
    public function __construct(
        protected readonly PDO                    $db,
        protected readonly PrivacySettingsService $settings,
        protected readonly PrivacyAuditLogger     $audit
    ) {}

    public function isEnabled(): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT is_enabled FROM privacy_features WHERE feature_key = ? LIMIT 1"
            );
            $stmt->execute([$this->getKey()]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            // Jesli wiersz istnieje - sluchamy bazy; jesli brak wiersza lub tabeli - wlaczone domyslnie
            return $row ? (bool)$row['is_enabled'] : true;
        } catch (Throwable) {
            // Tabela nie istnieje (migracja nie uruchomiona) - pokazuj wszystkie podmoduly
            return true;
        }
    }

    public function getTabId(): string
    {
        return 'tab_' . $this->getKey();
    }
}
