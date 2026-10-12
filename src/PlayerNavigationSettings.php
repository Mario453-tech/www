<?php
declare(strict_types=1);

/** Shared schema and default groups for the player navigation editor. */
final class PlayerNavigationSettings
{
    /** Return only public pages backed by an actual PHP target and a clean rewrite. */
    /** Zwracaj tylko publiczne strony z rzeczywistym plikiem PHP i trasa. */
    public static function destinations(): array
    {
        $root = dirname(__DIR__);
        $rules = file_get_contents($root . '/.htaccess');
        $result = [];
        if ($rules === false) return $result;
        preg_match_all('~^\s*RewriteRule \^([a-z][a-z0-9-]*)\$\s+/public/([a-z][a-z0-9_]*)\.php\s+\[L,PT\]~m', $rules, $matches, PREG_SET_ORDER);
        $gamePages = ['map', 'market', 'market-offers', 'bank', 'sabotage', 'finance', 'legal', 'contracts',
            'technical', 'logistics', 'hr', 'boardroom', 'dashboard', 'profile'];
        foreach ($matches as $match) {
            if (!in_array($match[1], $gamePages, true) || !is_file($root . '/public/' . $match[2] . '.php')) continue;
            $result[] = $match[1];
        }
        return array_values(array_unique($result));
    }

    public static function validDestination(string $route): bool
    {
        $key = ltrim($route, '/');
        return in_array($key, self::destinations(), true) && ($route === $key || $route === '/' . $key);
    }

    public static function shouldSeedLocation(PDO $db, string $location, bool $seeded): bool
    {
        if ($seeded || !in_array($location, ['header', 'footer', 'actions'], true)) return false;
        $query = $db->prepare('SELECT COUNT(*) FROM nav_items WHERE location=?');
        $query->execute([$location]);
        return (int)$query->fetchColumn() === 0;
    }

    public static function validNewLabels(string $pl, string $en, string $de): bool
    {
        foreach ([$pl, $en, $de] as $label) {
            if (trim($label) === '' || mb_strlen($label) > 64) return false;
        }
        return true;
    }

    public static function validEditedLabels(string $previousPl, string $pl, string $en, string $de): bool
    {
        if (trim($pl) === '' || mb_strlen($pl) > 64 || mb_strlen($en) > 64 || mb_strlen($de) > 64) return false;
        return $previousPl === $pl || (trim($en) !== '' && trim($de) !== '');
    }

    public static function editedLanguageKey(string $oldLabel, string $oldKey, string $newLabel, string $newKey): string
    {
        // An unchanged translation key otherwise hides an edited literal label.
        // Niezmieniony klucz tlumaczenia ukrywalby edytowana nazwe.
        return $oldKey !== '' && $oldKey === $newKey && $oldLabel !== $newLabel ? '' : $newKey;
    }

    public static function label(array $row): string
    {
        $locale = getLocale();
        if ($locale === 'en' && ($row['label_en'] ?? '') !== '') return (string)$row['label_en'];
        if ($locale === 'de' && ($row['label_de'] ?? '') !== '') return (string)$row['label_de'];
        $translated = (string)($row['lang_key'] ?? '');
        if ($translated !== '') {
            $value = tPlain($translated);
            if ($value !== $translated) return $value;
        }
        return (string)($row['label'] ?? '');
    }

    public static function ensureSchema(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS player_nav_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_key VARCHAR(64) NOT NULL UNIQUE,
            label VARCHAR(64) NOT NULL,
            label_en VARCHAR(64) NOT NULL DEFAULT '',
            label_de VARCHAR(64) NOT NULL DEFAULT '',
            lang_key VARCHAR(64) NOT NULL DEFAULT '',
            icon_key VARCHAR(64) NOT NULL DEFAULT '',
            sort_order SMALLINT NOT NULL DEFAULT 0,
            active TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        Database::addColumnIfMissing('nav_items', 'icon_key', "VARCHAR(64) NOT NULL DEFAULT '' AFTER `lang_key`");
        Database::addColumnIfMissing('nav_items', 'group_key', "VARCHAR(64) NOT NULL DEFAULT '' AFTER `icon_key`");
        Database::addColumnIfMissing('nav_items', 'label_en', "VARCHAR(64) NOT NULL DEFAULT '' AFTER `label`");
        Database::addColumnIfMissing('nav_items', 'label_de', "VARCHAR(64) NOT NULL DEFAULT '' AFTER `label_en`");
        Database::addColumnIfMissing('player_nav_groups', 'label_en', "VARCHAR(64) NOT NULL DEFAULT '' AFTER `label`");
        Database::addColumnIfMissing('player_nav_groups', 'label_de', "VARCHAR(64) NOT NULL DEFAULT '' AFTER `label_en`");
        // Seed initial groups once; never restore an administrator's deleted group.
        // Zasiej grupy tylko raz; nie przywracaj grupy usunietej przez administratora.
        $db->exec("CREATE TABLE IF NOT EXISTS player_nav_settings (
            setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
            setting_value VARCHAR(64) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $seeded = $db->query("SELECT setting_value FROM player_nav_settings WHERE setting_key='groups_seeded'")->fetchColumn();
        if ($seeded === '1') return;
        $defaults = [
            ['operations', 'Operacje', 'nav.group.operations', 'mapa', 10],
            ['business', 'Biznes', 'nav.group.business', 'finanse', 20],
            ['company', 'Firma', 'nav.group.company', 'dyrektor', 30],
        ];
        $insert = $db->prepare('INSERT IGNORE INTO player_nav_groups (group_key,label,lang_key,icon_key,sort_order) VALUES (?,?,?,?,?)');
        foreach ($defaults as $group) $insert->execute($group);
        $db->exec("INSERT INTO player_nav_settings (setting_key,setting_value) VALUES ('groups_seeded','1') ON DUPLICATE KEY UPDATE setting_value='1'");
    }

    /** @return list<array<string,mixed>> */
    public static function groups(PDO $db): array
    {
        return $db->query('SELECT * FROM player_nav_groups ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function defaultGroup(string $urlKey): string
    {
        $key = trim($urlKey, '/');
        if (in_array($key, ['map', 'technical', 'logistics'], true)) return 'operations';
        if (in_array($key, ['market', 'bank', 'finance', 'contracts'], true)) return 'business';
        return 'company';
    }

    public static function groupExists(PDO $db, string $key): bool
    {
        $stmt = $db->prepare('SELECT 1 FROM player_nav_groups WHERE group_key=? LIMIT 1');
        $stmt->execute([$key]);
        return (bool)$stmt->fetchColumn();
    }

    public static function ensureSpecialItems(PDO $db): void
    {
        $seeded = $db->query("SELECT setting_value FROM player_nav_settings WHERE setting_key='special_seeded'")->fetchColumn();
        if ($seeded === '1') return;
        // Do not overwrite labels, visibility or icons of existing navigation items.
        // Nie nadpisuj etykiet, widocznosci ani ikon istniejacych pozycji.
        $exists = $db->prepare("SELECT 1 FROM nav_items WHERE location='header' AND url_key=? LIMIT 1");
        $insert = $db->prepare("INSERT INTO nav_items (label,url_key,lang_key,sort_order,active,css_class,location) VALUES (?,?,?,?,1,'','header')");
        foreach ([['home', 'Start', 'nav.home', 10], ['help', 'Pomoc', 'nav.help', 90], ['chat', 'Czat', 'nav.chat', 91]] as $row) {
            $exists->execute([$row[0]]);
            if (!$exists->fetchColumn()) $insert->execute([$row[1], $row[0], $row[2], $row[3]]);
        }
        $db->exec("INSERT INTO player_nav_settings (setting_key,setting_value) VALUES ('special_seeded','1') ON DUPLICATE KEY UPDATE setting_value='1'");
    }
}
