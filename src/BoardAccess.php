<?php
/**
 * Checks which board departments are unlocked for the player.
 * PL: Sprawdza, ktore dzialy zarzadu sa odblokowane dla gracza.
 *
 * A department is unlocked when the player has an active board member
 * with the required role.
 * PL: Dzial jest odblokowany, gdy gracz ma aktywnego czlonka zarzadu
 * z wymagana rola.
 *
 * Preferred model uses member_type='director', but legacy staff records
 * are still honored after older HR migrations.
 * PL: Preferowany model uzywa member_type='director', ale stare rekordy
 * staff sa nadal honorowane po starszych migracjach HR.
 */
class BoardAccess
{
 /** Roles that require a board member to unlock a department. */
 /** PL: Role wymagajace czlonka zarzadu do odblokowania dzialu. */
    public const PROTECTED_ROLES = ['hr', 'technical', 'finance', 'legal', 'logistics'];

 /** Navigation url_key to board role_code map. */
 /** PL: Mapowanie url_key nawigacji na role_code board_members. */
    public const NAV_ROLE_MAP = [
        'hr' => 'hr',
        'technical' => 'technical',
        'finance' => 'finance',
        'legal' => 'legal',
        'logistics' => 'logistics',
    ];

    private const ROLE_LABELS = [
        'hr' => 'board_access.role_hr',
        'technical' => 'board_access.role_technical',
        'finance' => 'board_access.role_finance',
        'legal' => 'board_access.role_legal',
        'logistics' => 'board_access.role_logistics',
    ];

 /** Per-request cache keyed by player id. */
 /** PL: Cache na czas requestu indeksowany po player id. */
    private static array $cache = [];

 /**
 * Returns a role availability map for a player.
 * PL: Zwraca mape dostepnosci rol dla gracza.
 */
    public static function get(int $playerId): array
    {
        if (isset(self::$cache[$playerId])) {
            return self::$cache[$playerId];
        }

        $result = array_fill_keys(self::PROTECTED_ROLES, false);

        try {
            $db = Database::getInstance()->getConnection();
            try {
                Database::addColumnIfMissing('board_members', 'member_type', "ENUM('director','staff') NOT NULL DEFAULT 'director' AFTER player_id");
            } catch (Throwable $e) {
 // Best-effort guard for legacy schemas.
 // PL: Zabezpieczenie best-effort dla starych schematow.
            }

            $stmt = $db->prepare("
                SELECT br.code, bm.member_type
                FROM board_members bm
                JOIN board_roles br ON br.id = bm.role_id
                WHERE bm.player_id = ?
                  AND bm.status = 'active'
                  AND br.code IN ('hr','technical','finance','legal','logistics')
            ");
            $stmt->execute([$playerId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $code = (string)($row['code'] ?? '');
                if (isset($result[$code])) {
                    $result[$code] = true;
                }
            }
        } catch (Throwable $e) {
            if (class_exists('GameLog', false)) {
                GameLog::error('BoardAccess', 'get FAILED', $e, ['player_id' => $playerId]);
            }

 // Brak tabeli/schematu (nowa instalacja) -> fail-open (nie blokuj setupu).
 // Missing table/schema (fresh install) -> fail-open (do not block initial setup).
            $msg = $e->getMessage();
            $missingSchema = stripos($msg, '42S02') !== false
                          || stripos($msg, "doesn't exist") !== false
                          || stripos($msg, 'no such table') !== false;
            if ($missingSchema) {
                return array_fill_keys(self::PROTECTED_ROLES, true);
            }

 // Prawdziwy blad DB -> fail-closed (nie dawaj dostepu przy bledzie).
 // Real DB error -> fail-closed (do not grant access on error).
            return array_fill_keys(self::PROTECTED_ROLES, false);
        }

        self::$cache[$playerId] = $result;
        return $result;
    }

 /**
 * Checks whether a specific role is currently staffed.
 * PL: Sprawdza, czy konkretna rola jest obecnie obsadzona.
 */
    public static function has(int $playerId, string $role): bool
    {
        return self::get($playerId)[$role] ?? false;
    }

 /**
 * Requires a staffed role and redirects if it is missing.
 * PL: Wymaga obsadzonej roli i przekierowuje, jesli jej brakuje.
 */
    public static function require(int $playerId, string $role): void
    {
        if (self::has($playerId, $role)) {
            return;
        }

        $labelKey = self::ROLE_LABELS[$role] ?? null;
        $label = $labelKey ? t($labelKey) : $role;
        $_SESSION['board_access_denied'] = t('board_access.denied', ['label' => $label]);

        header('Location: /boardroom');
        exit;
    }

 /**
 * Filters navigation items by unlocked board roles.
 * PL: Filtruje elementy nawigacji po odblokowanych rolach zarzadu.
 *
 * @param array $navItems Rows from nav_items table.
 * @param int $playerId Player id.
 * @return array
 */
    public static function filterNav(array $navItems, int $playerId): array
    {
        $access = self::get($playerId);

        return array_filter($navItems, function (array $item) use ($access): bool {
            $key = $item['url_key'] ?? '';
            $key = ltrim($key, '/');

            if (isset(self::NAV_ROLE_MAP[$key])) {
                $role = self::NAV_ROLE_MAP[$key];
                return $access[$role] ?? false;
            }

 // Items without a protected role stay visible.
 // PL: Pozycje bez chronionej roli pozostaja widoczne.
            return true;
        });
    }
}
