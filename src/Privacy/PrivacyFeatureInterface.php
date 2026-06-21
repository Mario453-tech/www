<?php
/**
 * Kontrakt dla każdego podmodułu prywatności.
 * Każdy feature (Cookies, Zgody, Polityki, Ustawienia banera) musi go implementować.
 * Żeby dodać nowy feature w przyszłości — wystarczy stworzyć klasę implementującą ten interfejs
 * i zarejestrować ją w PrivacyFeatureRegistry::build().
 */
interface PrivacyFeatureInterface
{
    public function getKey(): string;

    public function getLabel(): string;

    public function getIcon(): string;

    public function getTabId(): string;

    public function isEnabled(): bool;

    /**
     * Obsługuje POST request dla tej zakładki (akcje formularzy).
     * Zwraca ['success' => bool, 'message' => string] lub null gdy brak akcji.
     */
    public function handlePost(array $post, int $adminId, string $ip, string $ua): ?array;

    /**
     * Przygotowuje dane dla widoku zakładki.
     */
    public function getViewData(array $get): array;

    /**
     * Ścieżka do pliku widoku zakładki (relative to project root).
     */
    public function getViewPath(): string;
}
