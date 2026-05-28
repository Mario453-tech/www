<?php
/**
 * DisasterMessages - Losowe komunikaty katastrof przemyslowych | Random Industrial Disaster Messages
 *
 * Komunikaty przechowywane w tabeli: disaster_message_templates | Messages stored in table: disaster_message_templates
 *   disaster_type  ENUM blowout|pipeline_explosion|reservoir_contamination|surface_spill | disaster_type ENUM blowout|pipeline_explosion|reservoir_contamination|surface_spill
 *   hse_active     0 = bez BHP, 1 = z BHP | hse_active 0 = without HSE, 1 = with HSE
 *   message        Tresc z placeholderami {well},{pipe},{loss},{drop},{pct},{fine},{oil},{area},{time},{hours},{vol} | Message with placeholders {well},{pipe},{loss},{drop},{pct},{fine},{oil},{area},{time},{hours},{vol}
 *   is_active      1 = aktywny (losowany), 0 = wylaczony | is_active 1 = active (randomized), 0 = disabled
 *
 * API bez zmian - WellService i tick.php nie wymagaja modyfikacji | API unchanged - WellService and tick.php do not require modification
 *
 * Uzycie | Usage:
 *   $msg  = DisasterMessages::get('blowout', false, ['well' => 5]); | $msg  = DisasterMessages::get('blowout', false, ['well' => 5]);
 *   $full = DisasterMessages::getFull('blowout', true, ['well' => 5]); | $full = DisasterMessages::getFull('blowout', true, ['well' => 5]);
 */
class DisasterMessages
{
    /** @var array<string, list<string>> Cache per request zeby nie odpytywac DB wielokrotnie w jednym ticku */
    private static array $cache = [];

    /** @var array<string, mixed> Fallback gdy tabela pusta lub niedostepna (np. przed migracja) */
    private static array $fallback = [
        'without_hse' => null,
        'with_hse'    => null,
    ];

    /** Metadane (tytuly, ikony, priorytety) — nie zmieniaja sie, nie musza byc w DB */
    /** @return array<string, array<int, array<string, string>>> */
    private static function meta(): array
    {
        return [
            'blowout' => [
                0 => ['title' => t('disaster.blowout_0'),   'icon' => '', 'priority' => 'critical'],
                1 => ['title' => t('disaster.blowout_1'),   'icon' => '', 'priority' => 'high'],
            ],
            'pipeline_explosion' => [
                0 => ['title' => t('disaster.pipeline_0'),  'icon' => '', 'priority' => 'critical'],
                1 => ['title' => t('disaster.pipeline_1'),  'icon' => '', 'priority' => 'high'],
            ],
            'reservoir_contamination' => [
                0 => ['title' => t('disaster.reservoir_0'), 'icon' => '', 'priority' => 'critical'],
                1 => ['title' => t('disaster.reservoir_1'), 'icon' => '', 'priority' => 'high'],
            ],
            'surface_spill' => [
                0 => ['title' => t('disaster.spill_0'),     'icon' => '', 'priority' => 'high'],
                1 => ['title' => t('disaster.spill_1'),     'icon' => '', 'priority' => 'medium'],
            ],
        ];
    }

    // 
    // PUBLICZNE API — identyczne jak wczesniej
    // 

    /**
     * Losowy komunikat dla danego typu katastrofy.
     *
     * @param string $type       blowout|pipeline_explosion|reservoir_contamination|surface_spill
     * @param bool   $hseActive  Czy BHP aktywne w momencie zdarzenia
     * @param array<string, mixed> $context  Zmienne do wstawienia: well, pipe, loss, drop, pct, fine, oil, area, time, hours, vol
     */
    public static function get(string $type, bool $hseActive, array $context = []): string
    {
        $pool = self::loadPool($type, $hseActive);

        if (empty($pool)) {
            $key = $hseActive ? 'disaster.fallback_with_hse' : 'disaster.fallback_without_hse';
            GameLog::warn('DisasterMessages', 'get: using fallback (empty pool)', [
                'type'       => $type,
                'hse_active' => $hseActive,
            ]);
            return self::interpolate(t($key), $context);
        }

        $msg = $pool[array_rand($pool)];
        GameLog::info('DisasterMessages', 'get', [
            'type'       => $type,
            'hse_active' => $hseActive,
            'pool_size'  => count($pool),
            'well'       => $context['well'] ?? null,
        ]);
        return self::interpolate($msg, $context);
    }

    /**
     * Pelna struktura do powiadomienia dyrektora.
     *
     * @return array ['message' => string, 'title' => string, 'icon' => string, 'priority' => string]
     */
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public static function getFull(string $type, bool $hseActive, array $context = []): array
    {
        $hseInt = $hseActive ? 1 : 0;
        $meta   = self::meta()[$type][$hseInt]
               ?? ['title' => t('disaster.meta_default_title'), 'icon' => '', 'priority' => 'high'];

        $result = array_merge($meta, [
            'message' => self::get($type, $hseActive, $context),
        ]);

        GameLog::info('DisasterMessages', 'getFull', [
            'type'     => $type,
            'hse'      => $hseActive,
            'priority' => $meta['priority'],
            'well'     => $context['well'] ?? null,
            
        ]);

        return $result;
    }

    /**
     * Wszystkie aktywne komunikaty dla danego typu — do podgladu w panelu GM.
     */
    /** @return list<string> */
    public static function getAll(string $type, bool $hseActive): array
    {
        return self::loadPool($type, $hseActive);
    }

    /**
     * Wyczysc cache w pamieci (np. po edycji komunikatow przez GM).        
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        GameLog::info('DisasterMessages', 'Cache cleared');
    }

    //  prywatne 

    /**
     * laduje komunikaty z DB — cache per type+hse na czas zycia requestu.
     */
    /** @return list<string> */
    private static function loadPool(string $type, bool $hseActive): array
    {
        $cacheKey = $type . '_' . ($hseActive ? '1' : '0');

        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        try {
            $db   = Database::getInstance()->getConnection();
            $stmt = $db->prepare("
                SELECT message
                FROM disaster_message_templates
                WHERE disaster_type = ?
                  AND hse_active    = ?
                  AND is_active     = 1
                ORDER BY id
            ");
            $stmt->execute([$type, $hseActive ? 1 : 0]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

            self::$cache[$cacheKey] = $rows;

            GameLog::dbResult(
                'DisasterMessages',
                "loadPool {$type} hse=" . ($hseActive ? '1' : '0'),
                count($rows)
            );

        } catch (Throwable $e) {
            GameLog::error('DisasterMessages', 'loadPool FAILED — using fallback', $e, [
                'type'       => $type,
                'hse_active' => $hseActive,
            ]);
            self::$cache[$cacheKey] = [];
        }

        return self::$cache[$cacheKey];
    }

    /**
     * Podstawia zmienne {well}, {fine} itp. w tresci komunikatu.
     */
    /** @param array<string, mixed> $ctx */
    private static function interpolate(string $msg, array $ctx): string
    {
        $defaults = [
            'well'  => '?',
            'pipe'  => '?',
            'loss'  => number_format(mt_rand(50000, 300000)),
            'drop'  => mt_rand(15, 35),
            'pct'   => mt_rand(10, 40),
            'fine'  => number_format(mt_rand(200000, 20000000)),
            'oil'   => number_format(mt_rand(500, 5000)),
            'area'  => mt_rand(500, 5000),
            'time'  => date('H:i'),
            'hours' => mt_rand(2, 12),
            'vol'   => number_format(mt_rand(1000, 5000)),
        ];

        foreach (array_merge($defaults, $ctx) as $k => $v) {
            $msg = str_replace('{' . $k . '}', (string)$v, $msg);
        }

        return $msg;
    }
}
