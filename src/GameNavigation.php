<?php
declare(strict_types=1);

final class GameNavigation
{
    private const GROUPS = [
        'operations' => ['map', 'technical', 'logistics'],
        'business' => ['market', 'bank', 'finance', 'contracts'],
        'company' => ['dashboard', 'boardroom', 'hr', 'legal', 'sabotage'],
    ];

    /** @param list<array<string,mixed>> $items */
    public static function special(array $items, string $key, bool $seeded = false): ?array
    {
        require_once __DIR__ . '/PlayerNavIcon.php';
        require_once __DIR__ . '/PlayerNavigationSettings.php';
        foreach ($items as $item) {
            if (trim((string)($item['url_key'] ?? ''), '/') !== $key) continue;
            if (isset($item['active']) && !(int)$item['active']) return null;
            return [
                'id' => (int)($item['id'] ?? 0),
                'label' => PlayerNavigationSettings::label($item),
                'icon' => PlayerNavIcon::resolve((string)($item['icon_key'] ?? ''), $key),
            ];
        }
        return $seeded ? null : ['id' => 0, 'label' => tPlain('nav.' . $key), 'icon' => PlayerNavIcon::resolve('', $key)];
    }

    /** @param list<array<string, mixed>> $items
     *  @param list<array<string,mixed>>|null $configuredGroups
     *  @return array<string, array{label:string, icon:string, active:bool, items:list<array<string,mixed>>}>
     */
    public static function build(array $items, string $currentPath, ?array $configuredGroups = null): array
    {
        require_once __DIR__ . '/PlayerNavIcon.php';
        require_once __DIR__ . '/PlayerNavigationSettings.php';
        $groups = [];
        if ($configuredGroups === null) {
            foreach (self::GROUPS as $id => $_keys) {
                $groups[$id] = ['label' => tPlain('nav.group.' . $id), 'icon' => PlayerNavIcon::resolve('', $id), 'active' => false, 'items' => []];
            }
        } else {
            usort($configuredGroups, static fn(array $a, array $b): int => [(int)$a['sort_order'], (int)($a['id'] ?? 0)] <=> [(int)$b['sort_order'], (int)($b['id'] ?? 0)]);
            foreach ($configuredGroups as $row) {
                if (!(int)$row['active']) continue;
                $id = (string)$row['group_key'];
                $groups[$id] = [
                    'label' => PlayerNavigationSettings::label($row),
                    'icon' => PlayerNavIcon::resolve((string)($row['icon_key'] ?? ''), $id),
                    'active' => false, 'items' => [],
                ];
            }
        }

        foreach ($items as $item) {
            $key = trim((string)($item['url_key'] ?? ''), '/');
            if ($key === '' || in_array($key, ['home', 'chat', 'help', 'logout'], true)) {
                continue;
            }

            $groupId = (string)($item['group_key'] ?? '');
            if ($groupId === '') {
                $groupId = 'company';
                foreach (self::GROUPS as $id => $keys) {
                    if (in_array($key, $keys, true)) {
                        $groupId = $id;
                        break;
                    }
                }
            }
            if (!isset($groups[$groupId])) continue;

            $href = str_starts_with((string)$item['url_key'], '/')
                ? (string)$item['url_key'] : url((string)$item['url_key']);
            $path = parse_url($href, PHP_URL_PATH) ?: '/';
            $path = $path === '/' ? '/' : rtrim($path, '/');
            $current = $currentPath === '/' ? '/' : rtrim($currentPath, '/');
            $active = $current === $path || ($path !== '/' && str_starts_with($current, $path . '/'));
            $label = PlayerNavigationSettings::label($item);

            $groups[$groupId]['items'][] = ['id' => (int)($item['id'] ?? 0), 'href' => $href, 'label' => $label, 'active' => $active,
                'icon' => PlayerNavIcon::resolve((string)($item['icon_key'] ?? ''), $key)];
            $groups[$groupId]['active'] = $groups[$groupId]['active'] || $active;
        }

        return $groups;
    }
}
