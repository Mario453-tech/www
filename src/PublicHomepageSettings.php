<?php
declare(strict_types=1);

final class PublicHomepageSettings
{
    public const KEY = 'public_homepage_enabled';

    /**
     * Keep the landing page available when configuration cannot be read.
     * Zachowaj dostepnosc strony startowej, gdy konfiguracji nie mozna odczytac.
     */
    public static function isEnabled(PDO $db): bool
    {
        try {
            $stmt = $db->prepare('SELECT `value` FROM site_config WHERE `key` = ? LIMIT 1');
            $stmt->execute([self::KEY]);
            $value = $stmt->fetchColumn();

            if ($value === false) {
                return true;
            }

            return !in_array(strtolower(trim((string) $value)), ['0', 'false', 'off', 'no'], true);
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::warn('PublicHomepageSettings', 'Public homepage setting unavailable; using enabled default', [
                    'error' => $e->getMessage(),
                ]);
            }

            return true;
        }
    }
}
