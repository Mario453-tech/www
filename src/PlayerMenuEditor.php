<?php
declare(strict_types=1);

/** Atomic, versioned player-menu edits. / Atomowe, wersjonowane zmiany menu gracza. */
final class PlayerMenuEditor
{
    public static function version(array $groups, array $items): string
    {
        return hash('sha256', json_encode([$groups, $items], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public static function externalUrl(string $value): string
    {
        $value = trim($value);
        if (preg_match('/[\x00-\x20\x7f]/', $value) || str_contains($value, '\\') || strlen($value) > 64 || !filter_var($value, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('destination');
        }
        $parts = parse_url($value);
        if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['https', 'http'], true)
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || isset($parts['port']) || !str_contains($parts['host'], '.')
            || preg_match('/(^|\.)(localhost|local|test|invalid|internal)$/i', $parts['host'])
            || filter_var($parts['host'], FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('destination');
        }
        return $value;
    }

    public static function href(string $route): string
    {
        require_once __DIR__ . '/PlayerNavigationSettings.php';
        if (preg_match('~^https?://~i', $route)) return self::externalUrl($route);
        if (preg_match('~^/?[a-z][a-z0-9-]{0,63}$~D', $route)) {
            return '/' . ltrim($route, '/');
        }
        throw new InvalidArgumentException('destination');
    }

    public static function rows(PDO $db): array
    {
        return [
            $db->query('SELECT id,group_key,label,label_en,label_de,lang_key,icon_key,sort_order,active FROM player_nav_groups ORDER BY sort_order,id')->fetchAll(PDO::FETCH_ASSOC),
            $db->query("SELECT id,label,label_en,label_de,lang_key,icon_key,url_key,group_key,sort_order,active,css_class,location,open_new_tab FROM nav_items WHERE location='header' ORDER BY sort_order,id")->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public static function save(PDO $db, array $input): array
    {
        require_once __DIR__ . '/PlayerNavigationSettings.php';
        require_once __DIR__ . '/PlayerNavIcon.php';
        $db->beginTransaction();
        $uploadedFiles = [];
        try {
            // Serialize all writers before comparing the browser's snapshot.
            // Serializuj zapisy przed porownaniem wersji z przegladarki.
            if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                $db->query("SELECT setting_value FROM player_nav_settings WHERE setting_key='groups_seeded' FOR UPDATE")->fetchColumn();
            }
            [$beforeGroups, $beforeItems] = self::rows($db);
            if (!is_string($input['version'] ?? null) || !hash_equals(self::version($beforeGroups, $beforeItems), $input['version'])) {
                throw new RuntimeException('conflict');
            }
            $groups = $input['groups'] ?? null;
            $items = $input['items'] ?? null;
            $deletedGroups = $input['deleted_groups'] ?? [];
            $deletedItems = $input['deleted_items'] ?? [];
            if (!is_array($groups) || !is_array($items) || !is_array($deletedGroups) || !is_array($deletedItems)
                || !is_array($input['new_groups'] ?? []) || !is_array($input['new_items'] ?? [])) {
                throw new InvalidArgumentException('structure');
            }
            $oldGroups = array_column($beforeGroups, null, 'group_key');
            $oldItems = array_column($beforeItems, null, 'id');
            $seenGroups = $seenItems = [];
            foreach ($deletedGroups as $key) {
                if (!is_string($key) || !isset($oldGroups[$key]) || isset($seenGroups[$key])) throw new InvalidArgumentException('group');
                $seenGroups[$key] = true;
            }
            foreach ($deletedItems as $id) {
                if (!filter_var($id, FILTER_VALIDATE_INT) || !isset($oldItems[(int)$id]) || isset($seenItems[(int)$id])) throw new InvalidArgumentException('item');
                $seenItems[(int)$id] = true;
            }
            if (count($groups) + count($deletedGroups) !== count($beforeGroups) || count($items) + count($deletedItems) !== count($beforeItems)) {
                throw new InvalidArgumentException('structure');
            }
            foreach ($beforeItems as $oldItem) {
                if (in_array($oldItem['group_key'], $deletedGroups, true) && !in_array((int)$oldItem['id'], array_map('intval', $deletedItems), true)) {
                    // Reassignment is allowed only when the retained item moves to another group.
                    // Przeniesienie dziecka jest dozwolone tylko do innej grupy.
                    $candidate = array_values(array_filter($items, static fn(array $row): bool => (int)($row['id'] ?? 0) === (int)$oldItem['id']));
                    if (!$candidate || $candidate[0]['group_key'] === $oldItem['group_key']) throw new InvalidArgumentException('group');
                }
            }
            foreach ($deletedItems as $id) {
                if (in_array(trim((string)$oldItems[(int)$id]['url_key'], '/'), ['home', 'help', 'chat', 'logout'], true)) throw new InvalidArgumentException('item');
                $db->prepare("DELETE FROM nav_items WHERE id=? AND location='header'")->execute([(int)$id]);
            }
            $groupUpdate = $db->prepare('UPDATE player_nav_groups SET label=?,label_en=?,label_de=?,lang_key=?,icon_key=?,sort_order=?,active=? WHERE group_key=?');
            foreach ($groups as $group) {
                if (!is_array($group) || !is_string($group['group_key'] ?? null)) throw new InvalidArgumentException('group');
                $key = $group['group_key'];
                if (!isset($oldGroups[$key]) || isset($seenGroups[$key])) throw new InvalidArgumentException('group');
                $seenGroups[$key] = true;
                $old = $oldGroups[$key];
                $label = self::text($group, 'label');
                $en = self::text($group, 'label_en');
                $de = self::text($group, 'label_de');
                $language = self::text($group, 'lang_key');
                if (!PlayerNavigationSettings::validEditedLabels($old['label'], $label, $en, $de) || !self::language($language)) throw new InvalidArgumentException('label');
                $icon = self::icon($group, $uploadedFiles);
                $order = self::order($group);
                $active = self::active($group);
                $language = PlayerNavigationSettings::editedLanguageKey($old['label'], $old['lang_key'], $label, $language);
                $groupUpdate->execute([$label, $en, $de, $language, $icon, $order, $active, $key]);
            }
            $newGroupKeys = [];
            $groupInsert = $db->prepare('INSERT INTO player_nav_groups (group_key,label,label_en,label_de,lang_key,icon_key,sort_order,active) VALUES (?,?,?,?,?,?,?,?)');
            foreach ($input['new_groups'] ?? [] as $group) {
                if (!is_array($group) || !is_string($group['group_key'] ?? null) || !preg_match('/^draft-[a-z0-9-]{1,40}$/D', $group['group_key'])
                    || isset($newGroupKeys[$group['group_key']])) throw new InvalidArgumentException('group');
                $label = self::text($group, 'label');
                $en = self::text($group, 'label_en');
                $de = self::text($group, 'label_de');
                $language = self::text($group, 'lang_key');
                if (!PlayerNavigationSettings::validNewLabels($label, $en, $de) || !self::language($language)) throw new InvalidArgumentException('label');
                $key = 'group-' . bin2hex(random_bytes(8));
                $newGroupKeys[$group['group_key']] = $key;
                $seenGroups[$key] = true;
                $groupInsert->execute([$key, $label, $en, $de, $language, self::icon($group, $uploadedFiles), self::order($group), self::active($group)]);
            }
            $itemUpdate = $db->prepare('UPDATE nav_items SET label=?,label_en=?,label_de=?,lang_key=?,icon_key=?,url_key=?,group_key=?,sort_order=?,active=?,css_class=?,open_new_tab=? WHERE id=? AND location=\'header\'');
            foreach ($items as $item) {
                if (!is_array($item) || !filter_var($item['id'] ?? null, FILTER_VALIDATE_INT)) throw new InvalidArgumentException('item');
                $id = (int)$item['id'];
                if (!isset($oldItems[$id]) || isset($seenItems[$id])) throw new InvalidArgumentException('item');
                $seenItems[$id] = true;
                $old = $oldItems[$id];
                $label = self::text($item, 'label');
                $en = self::text($item, 'label_en');
                $de = self::text($item, 'label_de');
                if (!PlayerNavigationSettings::validEditedLabels($old['label'], $label, $en, $de)) throw new InvalidArgumentException('label');
                $language = self::text($item, 'lang_key');
                if (!self::language($language)) throw new InvalidArgumentException('label');
                $icon = self::icon($item, $uploadedFiles);
                $route = self::text($item, 'url_key');
                $special = in_array(trim((string)$old['url_key'], '/'), ['home', 'help', 'chat', 'logout'], true);
                if ($special) {
                    if ($route !== $old['url_key']) throw new InvalidArgumentException('destination');
                    $group = '';
                } else {
                    if (!PlayerNavigationSettings::validDestination($route) && $route !== $old['url_key'] && !preg_match('~^https?://~i', $route)) throw new InvalidArgumentException('destination');
                    if (preg_match('~^https?://~i', $route)) $route = self::externalUrl($route);
                    if (in_array(trim($route, '/'), ['home', 'help', 'chat', 'logout'], true)) throw new InvalidArgumentException('destination');
                    $group = $newGroupKeys[self::text($item, 'group_key')] ?? self::text($item, 'group_key');
                    if ($group === '' && (string)$old['group_key'] === '') $group = PlayerNavigationSettings::defaultGroup((string)$old['url_key']);
                    if (!isset($seenGroups[$group]) || in_array($group, $deletedGroups, true)) throw new InvalidArgumentException('group');
                }
                $css = self::text($item, 'css_class');
                if ($css !== '' && !in_array($css, ['btn-success', 'btn-info', 'btn-primary', 'btn-secondary', 'btn-danger'], true)) throw new InvalidArgumentException('class');
                $order = self::order($item);
                $active = self::active($item);
                $language = PlayerNavigationSettings::editedLanguageKey($old['label'], $old['lang_key'], $label, $language);
                $newTab = self::newTab($item, $route);
                $itemUpdate->execute([$label, $en, $de, $language, $icon, $route, $group, $order, $active, $css, $newTab, $id]);
            }
            $itemInsert = $db->prepare("INSERT INTO nav_items (label,label_en,label_de,lang_key,icon_key,url_key,group_key,sort_order,active,css_class,open_new_tab,location) VALUES (?,?,?,?,?,?,?,?,?,?,?,'header')");
            foreach ($input['new_items'] ?? [] as $item) {
                if (!is_array($item)) throw new InvalidArgumentException('item');
                $label = self::text($item, 'label');
                $en = self::text($item, 'label_en');
                $de = self::text($item, 'label_de');
                if (!PlayerNavigationSettings::validNewLabels($label, $en, $de)) throw new InvalidArgumentException('label');
                $language = self::text($item, 'lang_key');
                if (!self::language($language)) throw new InvalidArgumentException('label');
                $route = self::text($item, 'url_key');
                if (!PlayerNavigationSettings::validDestination($route) && !preg_match('~^https?://~i', $route)) throw new InvalidArgumentException('destination');
                if (preg_match('~^https?://~i', $route)) $route = self::externalUrl($route);
                $group = $newGroupKeys[self::text($item, 'group_key')] ?? self::text($item, 'group_key');
                if (!isset($seenGroups[$group]) || in_array($group, $deletedGroups, true)) throw new InvalidArgumentException('group');
                $css = self::text($item, 'css_class');
                if ($css !== '' && !in_array($css, ['btn-success', 'btn-info', 'btn-primary', 'btn-secondary', 'btn-danger'], true)) throw new InvalidArgumentException('class');
                $itemInsert->execute([$label, $en, $de, $language, self::icon($item, $uploadedFiles), $route, $group, self::order($item), self::active($item), $css, self::newTab($item, $route)]);
            }
            foreach ($deletedGroups as $key) {
                $db->prepare('DELETE FROM player_nav_groups WHERE group_key=?')->execute([$key]);
            }
            $routes = [];
            foreach (array_merge($items, $input['new_items'] ?? []) as $item) {
                $route = trim((string)$item['url_key'], '/');
                if (isset($routes[$route])) throw new InvalidArgumentException('duplicate');
                $routes[$route] = true;
            }
            [$afterGroups, $afterItems] = self::rows($db);
            $version = self::version($afterGroups, $afterItems);
            $db->commit();
            return ['version' => $version, 'groups' => $afterGroups, 'items' => $afterItems];
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            foreach ($uploadedFiles as $name) @unlink(PlayerNavIcon::DIRECTORY . '/' . $name . '.png');
            throw $e;
        }
    }

    private static function text(array $row, string $key): string
    {
        if (!is_string($row[$key] ?? null)) throw new InvalidArgumentException('data');
        $value = trim($row[$key]);
        if (mb_strlen($value) > 64) throw new InvalidArgumentException('data');
        return $value;
    }

    private static function icon(array $row, array &$uploadedFiles): string
    {
        if (isset($row['icon_upload']) && $row['icon_upload'] !== '') {
            $upload = $row['icon_upload'];
            if (!is_string($upload) || strlen($upload) > 1500000
                || !preg_match('~^data:image/(png|jpeg|webp);base64,([a-zA-Z0-9+/=]+)$~D', $upload, $matches)) {
                throw new InvalidArgumentException('image');
            }
            $bytes = base64_decode($matches[2], true);
            if ($bytes === false || strlen($bytes) > 1048576) throw new InvalidArgumentException('image');
            $temp = tempnam(sys_get_temp_dir(), 'menu-icon-');
            if ($temp === false) throw new RuntimeException('storage');
            try {
                file_put_contents($temp, $bytes);
                $mime = 'image/' . $matches[1];
                $name = PlayerNavIcon::storeImage($temp, $mime);
                $uploadedFiles[] = $name;
                return $name;
            } finally {
                @unlink($temp);
            }
        }
        $icon = self::text($row, 'icon_key');
        if ($icon !== '' && !PlayerNavIcon::valid($icon)) throw new InvalidArgumentException('icon');
        return $icon;
    }

    private static function language(string $key): bool
    {
        return $key === '' || (bool)preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $key);
    }

    private static function order(array $row): int
    {
        $order = filter_var($row['sort_order'] ?? null, FILTER_VALIDATE_INT);
        if ($order === false || $order < 0 || $order > 32000) throw new InvalidArgumentException('order');
        return $order;
    }

    private static function active(array $row): int
    {
        if (!in_array($row['active'] ?? null, [0, 1, '0', '1'], true)) throw new InvalidArgumentException('active');
        return (int)$row['active'];
    }

    private static function newTab(array $row, string $route): int
    {
        $value = $row['open_new_tab'] ?? 0;
        if (!in_array($value, [0, 1, '0', '1'], true)) throw new InvalidArgumentException('new_tab');
        return preg_match('~^https?://~i', $route) ? (int)$value : 0;
    }
}
