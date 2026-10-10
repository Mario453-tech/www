<?php
declare(strict_types=1);

final class GameNavigation
{
    private const GROUPS = [
        'operations' => ['map', 'technical', 'logistics'],
        'business' => ['market', 'bank', 'finance', 'contracts'],
        'company' => ['dashboard', 'boardroom', 'hr', 'legal', 'sabotage'],
    ];

    private const ICONS = [
        'map' => 'mapa', 'technical' => 'technika', 'logistics' => 'logistyka',
        'market' => 'rynek', 'bank' => 'bank', 'finance' => 'finanse', 'contracts' => 'kontrakty',
        'dashboard' => 'dyrektor', 'boardroom' => 'dyrektor', 'hr' => 'kadry', 'legal' => 'dzial-prawny',
    ];

    /** @param list<array<string, mixed>> $items
     *  @return array<string, array{label:string, active:bool, items:list<array{href:string,label:string,active:bool,icon:string}>}>
     */
    public static function build(array $items, string $currentPath): array
    {
        $groups = [];
        foreach (self::GROUPS as $id => $_keys) {
            $groups[$id] = ['label' => tPlain('nav.group.' . $id), 'active' => false, 'items' => []];
        }

        foreach ($items as $item) {
            $key = trim((string)($item['url_key'] ?? ''), '/');
            if ($key === '' || in_array($key, ['home', 'chat', 'help', 'logout'], true)) {
                continue;
            }

            $groupId = 'company';
            foreach (self::GROUPS as $id => $keys) {
                if (in_array($key, $keys, true)) {
                    $groupId = $id;
                    break;
                }
            }

            $href = str_starts_with((string)$item['url_key'], '/')
                ? (string)$item['url_key'] : url((string)$item['url_key']);
            $path = parse_url($href, PHP_URL_PATH) ?: '/';
            $path = $path === '/' ? '/' : rtrim($path, '/');
            $current = $currentPath === '/' ? '/' : rtrim($currentPath, '/');
            $active = $current === $path || ($path !== '/' && str_starts_with($current, $path . '/'));
            $langKey = (string)($item['lang_key'] ?? '');
            $label = $langKey !== '' ? tPlain($langKey) : (string)($item['label'] ?? $key);

            $groups[$groupId]['items'][] = ['href' => $href, 'label' => $label, 'active' => $active,
                'icon' => self::ICONS[$key] ?? 'dashboard'];
            $groups[$groupId]['active'] = $groups[$groupId]['active'] || $active;
        }

        return $groups;
    }
}
