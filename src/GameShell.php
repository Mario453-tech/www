<?php

class GameShell
{
 /** @return array<int, array<string, mixed>> */
    public static function statusItems(int $playerId): array
    {
        $playerData = ['cash' => 0, 'status' => 'active', 'capacity' => 0, 'used' => 0, 'created_at' => null];
        $marketData = ['current_price' => 0];
        $wellCounts = ['active' => 0, 'total' => 0, 'damaged' => 0];

        // Zapewnij kolumny portfela PRZED Player::getData() (SELECT p.* musi widziec bank_balance).
        // Ensure wallet columns BEFORE Player::getData() (SELECT p.* must see bank_balance).
        try {
            new WalletService();
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('GameShell', 'WalletService ensureSchema failed', $e);
            }
        }

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

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT
                    SUM(CASE WHEN status = 'active' AND technical_condition > 1 THEN 1 ELSE 0 END) AS active_count,
                    SUM(CASE WHEN status NOT IN ('sold', 'seized') THEN 1 ELSE 0 END) AS total_count,
                    SUM(CASE
                        WHEN status NOT IN ('sold', 'seized')
                         AND (status IN ('broken', 'blowout', 'contaminated') OR technical_condition <= 1)
                        THEN 1 ELSE 0
                    END) AS damaged_count
                FROM wells
                WHERE player_id = ?
            ");
            $stmt->execute([$playerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $wellCounts = [
                'active' => (int)($row['active_count'] ?? 0),
                'total' => (int)($row['total_count'] ?? 0),
                'damaged' => (int)($row['damaged_count'] ?? 0),
            ];
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('GameShell', 'company well status load failed', $e, ['player_id' => $playerId]);
            }
        }

        $used        = (float)($playerData['used'] ?? 0);
        $capacity    = (float)($playerData['capacity'] ?? 0);
        $storagePct  = $capacity > 0 ? round(($used / $capacity) * 100, 0) : 0;
        $companyDays = self::companyAgeDays($playerData['created_at'] ?? null);
        $companyStatus = self::companyStatusPresentation(
            (string)($playerData['status'] ?? 'active'),
            $wellCounts['active'],
            $wellCounts['total'],
            $wellCounts['damaged']
        );
        $bankBalance = (float)($playerData['bank_balance'] ?? 0.0);

        return [
            [
                'label'           => t('index.cash'),
                'value'           => number_format((float)$playerData['cash'], 2, ',', ' '),
                'sub'             => 'PLN',
                'class'           => 'money',
                'icon_html'       => self::statusIconHtml('cash'),
                'icon_color'      => '#c8860a',
                'data_wallet_key' => 'cash',
                'data_wallet_fmt' => 'dec',
            ],
            [
                'label'           => t('index.bank_balance'),
                'value'           => number_format($bankBalance, 2, ',', ' '),
                'sub'             => 'PLN',
                'class'           => 'money',
                'icon_html'       => self::statusIconHtml('bank'),
                'icon_color'      => '#5b8dd9',
                'data_wallet_key' => 'bank',
                'data_wallet_fmt' => 'dec',
            ],
            [
                'label'      => t('index.storage'),
                'value'      => number_format($used, 0, ',', ' ') . ' / ' . number_format($capacity, 0, ',', ' '),
                'sub'        => t('game_shell.storage_sub', ['pct' => $storagePct]),
                'pct'        => $storagePct,
                'class'      => 'storage',
                'icon_html'  => self::statusIconHtml('storage'),
                'icon_color' => '#2a9d6e',
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
                'value' => $companyStatus['label'],
                'sub' => t('game_shell.company_age_sub', ['days' => $companyDays]),
                'class' => $companyStatus['code'] === 'well_failure' ? 'cv-bad' : '',
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
            $rows = $db->query("SELECT label, lang_key, url_key, css_class FROM nav_items WHERE active = 1 AND location = 'actions' ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

            if (class_exists('BoardAccess', false)) {
                $rows = BoardAccess::filterNav($rows ?: [], $playerId);
            }

            if (class_exists('BoardAccess', false) && BoardAccess::has($playerId, 'legal')) {
                $hasSabotage = false;
                foreach ($rows ?: [] as $row) {
                    if ((string)($row['url_key'] ?? '') === 'sabotage') {
                        $hasSabotage = true;
                        break;
                    }
                }
                if (!$hasSabotage) {
                    $rows[] = [
                        'label'     => tPlain('sabotage.action_label'),
                        'url_key'   => 'sabotage',
                        'lang_key'  => '',
                        'css_class' => 'btn-secondary',
                    ];
                }
            }

            $rows = array_values(array_filter($rows ?: [], static function (array $row): bool {
                return (string)($row['url_key'] ?? '') !== 'upgrade-well';
            }));

            return array_map(static function (array $row): array {
                $key = (string)($row['url_key'] ?? '#');
                $href = str_starts_with($key, '/') ? $key : url($key);
                $langKey = (string)($row['lang_key'] ?? '');
                $labelText = ($langKey !== '') ? tPlain($langKey) : (string)($row['label'] ?? '');

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
                    'legal' => self::actionIconHtml('legal'),
                    'contracts' => self::actionIconHtml('finance'),
                    'sabotage' => self::actionIconHtml('sabotage'),
                ];

                $icon = $iconMap[$key] ?? '';
                if ($icon === '') {
                    $labelLower = function_exists('mb_strtolower') ? mb_strtolower($labelText, 'UTF-8') : strtolower($labelText);
                    if (str_contains($labelLower, 'kup odwiert')) {
                        $icon = self::actionIconHtml('buy');
                    } elseif (str_contains($labelLower, 'prawny') || str_contains($labelLower, 'zezwolen')) {
                        $icon = self::actionIconHtml('legal');
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
                    } elseif (str_contains($labelLower, 'sabot')) {
                        $icon = self::actionIconHtml('sabotage');
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

    public static function companyStatusCode(string $playerStatus, int $activeWells, int $totalWells, int $damagedWells): string
    {
        $status = strtolower($playerStatus);
        if (in_array($status, ['bankrupt', 'under_bailiff', 'financial_risk', 'recovery', 'paused'], true)) {
            return $status;
        }
        if ($damagedWells > 0) {
            return 'well_failure';
        }
        if ($totalWells > 0 && $activeWells === 0) {
            return 'idle';
        }

        return $status;
    }

    /** @return array{code:string,label:string,modifier:string} */
    public static function companyStatusPresentation(string $playerStatus, int $activeWells, int $totalWells, int $damagedWells): array
    {
        $code = self::companyStatusCode($playerStatus, $activeWells, $totalWells, $damagedWells);
        $label = match ($code) {
            'active' => t('header.company_active'),
            'well_failure' => t('header.company_well_failure', ['count' => $damagedWells]),
            'idle' => t('header.company_idle'),
            'bankrupt' => t('header.company_bankrupt'),
            'under_bailiff' => t('header.company_under_bailiff'),
            'financial_risk' => t('header.company_financial_risk'),
            'recovery' => t('header.company_recovery'),
            'paused' => t('header.company_paused'),
            default => ucfirst($code),
        };
        $modifier = match ($code) {
            'well_failure' => 'failure',
            'under_bailiff' => 'bailiff',
            'financial_risk' => 'risk',
            default => $code,
        };

        return ['code' => $code, 'label' => $label, 'modifier' => $modifier];
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
        // Ikony statusu ladowane z plikow SVG zamiast hardcode. / Status icons loaded from SVG files instead of hardcode.
        static $cache = [];
        $allowed = ['cash', 'bank', 'storage', 'oil_price', 'company', 'wells'];
        $iconKey = in_array($key, $allowed, true) ? $key : 'default';
        if (!isset($cache[$iconKey])) {
            $path = __DIR__ . '/../assets/img/icons/status/' . $iconKey . '.svg';
            $cache[$iconKey] = file_exists($path) ? rtrim((string) file_get_contents($path)) : '';
        }
        return $cache[$iconKey];
    }

    public static function actionIconHtml(string $key): string
    {
        // Ikony akcji ladowane z plikow SVG zamiast hardcode. / Action icons loaded from SVG files instead of hardcode.
        static $cache = [];
        $allowed = ['market', 'bank', 'team', 'dashboard', 'map', 'buy', 'technical', 'finance', 'logistics', 'help', 'legal', 'sabotage'];
        $iconKey = in_array($key, $allowed, true) ? $key : 'default';
        if (!isset($cache[$iconKey])) {
            $path = __DIR__ . '/../assets/img/icons/nav/' . $iconKey . '.svg';
            $cache[$iconKey] = file_exists($path) ? rtrim((string) file_get_contents($path)) : '';
        }
        return $cache[$iconKey];
    }
}
