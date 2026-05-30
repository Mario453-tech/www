<?php

class GameShell
{
 /** @return array<int, array<string, mixed>> */
    public static function statusItems(int $playerId): array
    {
        $playerData = ['cash' => 0, 'status' => 'active', 'capacity' => 0, 'used' => 0, 'created_at' => null];
        $marketData = ['current_price' => 0];

        try {
            $player = new Player($playerId);
            $data = $player->getData();
            if (is_array($data)) {
                $playerData = array_merge($playerData, $data);
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('GameShell', 'player status load failed', $e, ['player_id' => $playerId]);
            }
        }

        try {
            $market = new Market();
            $data = $market->getState();
            if (is_array($data)) {
                $marketData = array_merge($marketData, $data);
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('GameShell', 'market status load failed', $e);
            }
        }

        $used = (float)($playerData['used'] ?? 0);
        $capacity = (float)($playerData['capacity'] ?? 0);
        $storagePct = $capacity > 0 ? round(($used / $capacity) * 100, 0) : 0;
        $companyDays = self::companyAgeDays($playerData['created_at'] ?? null);
        $statusLabel = self::statusLabel((string)($playerData['status'] ?? 'active'));

        return [
            [
                'label' => t('index.cash'),
                'value' => number_format((float)$playerData['cash'], 0, ',', ' '),
                'sub' => '$ USD',
                'class' => 'money',
                'icon_html' => self::statusIconHtml('cash'),
                'icon_color' => '#c8860a',
            ],
            [
                'label' => t('index.storage'),
                'value' => number_format($used, 0, ',', ' ') . ' / ' . number_format($capacity, 0, ',', ' '),
                'sub' => t('game_shell.storage_sub', ['pct' => $storagePct]),
                'pct' => $storagePct,
                'class' => 'storage',
                'icon_html' => self::statusIconHtml('storage'),
                'icon_color' => '#5b8dd9',
            ],
            [
                'label' => t('index.oil_price'),
                'value' => number_format((float)$marketData['current_price'], 2, ',', ' ') . ' $/bbl',
                'class' => 'money',
                'icon_html' => self::statusIconHtml('oil_price'),
                'icon_color' => '#e0b020',
            ],
            [
                'label' => t('game_shell.company_status_label'),
                'value' => $statusLabel,
                'sub' => t('game_shell.company_age_sub', ['days' => $companyDays]),
                'class' => '',
                'icon_html' => self::statusIconHtml('company'),
                'icon_color' => '#20b2aa',
                'pulse' => true,
            ],
        ];
    }

 /** @return array<int, array<string, mixed>> */
    public static function actionItems(int $playerId): array
    {
        return self::actionItemsFromConfig($playerId);
    }

 /** @return array<int, array<string, string>> */
    private static function actionItemsFromConfig(int $playerId): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $rows = $db->query("SELECT label, url_key, icon, css_class FROM nav_items WHERE active = 1 AND location = 'actions' ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

            if (class_exists('BoardAccess', false)) {
                $rows = BoardAccess::filterNav($rows ?: [], $playerId);
            }

            $rows = array_values(array_filter($rows ?: [], static function (array $row): bool {
                return (string)($row['url_key'] ?? '') !== 'upgrade-well';
            }));

            return array_map(static function (array $row): array {
                $key = (string)($row['url_key'] ?? '#');
                $href = str_starts_with($key, '/') ? $key : url($key);
                $labelText = (string)($row['label'] ?? '');

                $iconMap = [
                    'market' => self::actionIconHtml('market'),
                    'bank' => self::actionIconHtml('bank'),
                    'hr' => self::actionIconHtml('team'),
                    'boardroom' => self::actionIconHtml('team'),
                    'dashboard' => self::actionIconHtml('dashboard'),
                    'map' => self::actionIconHtml('map'),
                    'buy-well' => self::actionIconHtml('buy'),
                    'technical' => self::actionIconHtml('technical'),
                    'finance' => self::actionIconHtml('finance'),
                    'logistics' => self::actionIconHtml('logistics'),
                    'help' => self::actionIconHtml('help'),
                ];

                $icon = $iconMap[$key] ?? '';
                if ($icon === '') {
                    $labelLower = function_exists('mb_strtolower') ? mb_strtolower($labelText, 'UTF-8') : strtolower($labelText);
                    if (str_contains($labelLower, 'kup odwiert')) {
                        $icon = self::actionIconHtml('buy');
                    } elseif (str_contains($labelLower, 'rynek')) {
                        $icon = self::actionIconHtml('market');
                    } elseif (str_contains($labelLower, 'bank')) {
                        $icon = self::actionIconHtml('bank');
                    } elseif (str_contains($labelLower, 'zarzad') || str_contains($labelLower, 'kadry') || str_contains($labelLower, 'hr')) {
                        $icon = self::actionIconHtml('team');
                    } elseif (str_contains($labelLower, 'techn')) {
                        $icon = self::actionIconHtml('technical');
                    } elseif (str_contains($labelLower, 'logist')) {
                        $icon = self::actionIconHtml('logistics');
                    } elseif (str_contains($labelLower, 'finans')) {
                        $icon = self::actionIconHtml('finance');
                    }
                }

                return [
                    'type' => 'link',
                    'url' => $href,
                    'label' => $labelText,
                    'icon_html' => $icon,
                    'class' => (string)($row['css_class'] ?: 'btn-secondary'),
                ];
            }, $rows ?: []);
        } catch (Throwable $e) {
            return [];
        }
    }

    private static function companyAgeDays(mixed $createdAt): int
    {
        if (!$createdAt) {
            return 0;
        }
        $ts = strtotime((string)$createdAt);
        if (!$ts) {
            return 0;
        }
        return max(0, (int)floor((time() - $ts) / 86400));
    }

    private static function statusLabel(string $status): string
    {
        return match (strtolower($status)) {
            'active' => t('game_shell.company_status_active'),
            'bankrupt' => t('game_shell.company_status_bankrupt'),
            'recovery' => t('game_shell.company_status_recovery'),
            'paused' => t('game_shell.company_status_paused'),
            default => ucfirst($status),
        };
    }

 /** @return array{statusItems:array<int,array<string,mixed>>,actions:array<int,array<string,mixed>>} */
    public static function data(int $playerId): array
    {
        return [
            'statusItems' => self::statusItems($playerId),
            'actions' => self::actionItems($playerId),
        ];
    }

    public static function statusIconHtml(string $key): string
    {
        return match ($key) {
            'cash' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="currentColor" opacity=".18"></circle><path d="M12 6.75c-1.86 0-3.25 1.02-3.25 2.5 0 1.58 1.33 2.17 3.08 2.58 1.46.34 2.17.59 2.17 1.32 0 .67-.67 1.1-1.75 1.1-1.12 0-2.2-.39-3.06-1.06l-.94 1.41c.88.71 1.95 1.16 3.13 1.31V18h1.5v-2.05c1.95-.18 3.22-1.23 3.22-2.82 0-1.7-1.36-2.28-3.2-2.7-1.33-.31-2.05-.52-2.05-1.23 0-.62.57-1.01 1.57-1.01.92 0 1.77.27 2.49.79l.88-1.44c-.74-.56-1.67-.92-2.64-1.03V5.5h-1.5v1.25Z" fill="currentColor"></path></svg>',
            'storage' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="2.5" width="10" height="19" rx="2" fill="currentColor" opacity=".18"></rect><path d="M9 4.5h6v2H9zM8.5 9h7v3h-7zM8.5 13.5h7v3h-7zM9 18h6v1.5H9z" fill="currentColor"></path></svg>',
            'oil_price' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2.8 2.7 4.73 5.32.9-3.72 3.86.78 5.31L12 15.55 6.92 17.6l.78-5.31L3.98 8.43l5.32-.9L12 2.8Z" fill="currentColor" opacity=".18"></path><path d="M12 4.8 13.8 8l3.6.61-2.53 2.62.53 3.59L12 13.45 8.6 14.82l.53-3.59L6.6 8.61 10.2 8 12 4.8Zm-.75 3.7v2.45H9.5v1.5h1.75v2.05h1.5v-2.05h1.62c1.21 0 2.13-.79 2.13-1.96 0-1.16-.92-1.99-2.13-1.99h-3.12Zm1.5 1.5h1.47c.37 0 .63.23.63.52 0 .3-.26.48-.63.48h-1.47V10Z" fill="currentColor"></path></svg>',
            'company' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20.5h16v1H4zM6 18V8.5l6-3 6 3V18h-2v-2h-2v2h-4v-2H8v2H6Zm2-1.5h1.5V15H8v1.5Zm0-3h1.5V12H8v1.5Zm0-3H9.5V9H8v1.5Zm3.25 6H12.75V15h-1.5v1.5Zm0-3H12.75V12h-1.5v1.5Zm0-3H12.75V9h-1.5v1.5Zm3.25 6H16V15h-1.5v1.5Zm0-3H16V12h-1.5v1.5Zm0-3H16V9h-1.5v1.5Z" fill="currentColor"></path></svg>',
            'wells' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19h14v1.5H5zM8 17V9.5l4-3 4 3V17h-1.75v-2.25h-4.5V17H8Zm1.75-6h4.5V10l-2.25-1.69L9.75 10v1Zm1 2.5h2.5V12h-2.5v1.5Z" fill="currentColor"></path></svg>',
            default => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="currentColor" opacity=".18"></circle><circle cx="12" cy="12" r="3.5" fill="currentColor"></circle></svg>',
        };
    }

    public static function actionIconHtml(string $key): string
    {
        return match ($key) {
            'market' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8h12l-1 10H7L6 8Zm2.5 2v2H11v-2H8.5Zm4.5 0v2H15.5v-2H13Zm-7-5h12v1.5H6z" fill="currentColor"></path></svg>',
            'bank' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9.5 12 4l9 5.5V11H3V9.5Zm2 3h2v5H5v-5Zm4 0h2v5H9v-5Zm4 0h2v5h-2v-5Zm4 0h2v5h-2v-5ZM3 19h18v1.5H3z" fill="currentColor"></path></svg>',
            'team' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 11a3 3 0 1 1 0-6 3 3 0 0 1 0 6Zm8 0a3 3 0 1 1 0-6 3 3 0 0 1 0 6ZM4 19c0-2.21 1.79-4 4-4s4 1.79 4 4H4Zm8 0c0-1.84 1.51-3.33 3.38-3.33S18.75 17.16 18.75 19H12Z" fill="currentColor"></path></svg>',
            'dashboard' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19h14v1.5H5zM6.5 16h2V9h-2v7Zm4.5 0h2V5h-2v11Zm4.5 0h2v-4h-2v4Z" fill="currentColor"></path></svg>',
            'map' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5.5 15 3l5 1.5v14L15 17l-6 2.5L4 18V4l5 1.5Zm1.5 1.04v10.92l3-1.25V5.29l-3 1.25Zm-5 .02v10.28l3 1.02V7.58l-3-1.02Zm13-.02-3-.9v10.57l3 .9V6.54Z" fill="currentColor"></path></svg>',
            'buy' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h13l-1 9H8L6 7Zm2.5 2h7v1.5h-7V9Zm-1-4H9l.8 1.5H7.5L5 16.5H4V18h2l1.2-10H20V6.5H9.9L9.1 5H7.5Z" fill="currentColor"></path></svg>',
            'technical' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.9 5.5 3.6 3.6-2 2-3.6-3.6 2-2Zm-8.2 8.2 4.92-4.92 3.52 3.52L10.2 17.24H6.7V13.7Zm-.7 5.04h12V20H6z" fill="currentColor"></path></svg>',
            'finance' => self::statusIconHtml('cash'),
            'logistics' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h10v8H4V7Zm10 2h3l3 3v3h-2.25a2.25 2.25 0 1 1-4.5 0H12V9Zm-7.75 7.25A2.25 2.25 0 1 1 8.5 14a2.25 2.25 0 0 1-2.25 2.25Zm9 0A2.25 2.25 0 1 1 17.5 14a2.25 2.25 0 0 1-2.25 2.25Z" fill="currentColor"></path></svg>',
            'help' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 18.5a1.1 1.1 0 1 0 0 2.2 1.1 1.1 0 0 0 0-2.2Zm.02-14c-3.02 0-5.02 1.75-5.02 4.31h1.8c0-1.53 1.29-2.57 3.2-2.57 1.76 0 3 .92 3 2.31 0 1.03-.58 1.69-1.99 2.48-1.76.98-2.36 1.88-2.36 3.68v.44h1.8v-.36c0-1.16.43-1.73 1.77-2.49 1.67-.94 2.58-2 2.58-3.82 0-2.41-2-3.98-4.78-3.98Z" fill="currentColor"></path></svg>',
            default => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="currentColor"></circle></svg>',
        };
    }
}
