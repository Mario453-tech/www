<?php
/**
 * Klasa bazowa dla podmodułów prywatności.
 * Dostarcza wspólne zależności (PDO, Settings, AuditLogger) i domyślną implementację isEnabled().
 * Nie jest obowiązkowa — feature może implementować interfejs bezpośrednio.
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
            // Jeśli wiersz istnieje — słuchamy bazy; jeśli nie ma wiersza ani tabeli — włączone domyślnie
            return $row ? (bool)$row['is_enabled'] : true;
        } catch (Throwable) {
            // Tabela nie istnieje (migracja nie uruchomiona) — pokazuj wszystkie podmoduły
            return true;
        }
    }

    public function getTabId(): string
    {
        return 'tab_' . $this->getKey();
    }
}
