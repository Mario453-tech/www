<?php
declare(strict_types=1);

/**
 * Visit Tracking and Analytics Service.
 * Serwis sledzenia wizyt i analityki ruchu.
 */
final class VisitTrackerService
{
    private static bool $bootstrapped = false;



    /**
     * Ensure database tables exist.
     * Upewnij sie, ze tabele bazy danych istnieja.
     */
    public static function ensureTablesExist(PDO $db): void
    {
        if (self::$bootstrapped) {
            return;
        }

        try {
            // Fast check if main table exists to bypass DDL on every request.
            // Szybkie sprawdzenie czy glowna tabela juz istnieje, aby pominac DDL.
            $check = $db->query("SHOW TABLES LIKE 'site_visits_daily'");
            if ($check && $check->fetchColumn() !== false) {
                self::$bootstrapped = true;
                return;
            }
        } catch (Throwable) {
            // Proceed to CREATE TABLE statements on failure.
            // Przejdz do instrukcji CREATE TABLE w razie bledu.
        }

        $statements = [
            "CREATE TABLE IF NOT EXISTS `site_visits_daily` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `visit_date` DATE NOT NULL UNIQUE,
                `page_views` INT UNSIGNED NOT NULL DEFAULT 0,
                `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
                `player_views` INT UNSIGNED NOT NULL DEFAULT 0,
                `guest_views` INT UNSIGNED NOT NULL DEFAULT 0,
                `bot_views` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                INDEX `idx_visit_date` (`visit_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `site_visit_country_stats` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `visit_date` DATE NOT NULL,
                `country_code` VARCHAR(5) NOT NULL,
                `page_views` INT UNSIGNED NOT NULL DEFAULT 0,
                `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
                UNIQUE KEY `uniq_date_country` (`visit_date`, `country_code`),
                INDEX `idx_country` (`country_code`),
                INDEX `idx_date` (`visit_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `site_visit_page_stats` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `visit_date` DATE NOT NULL,
                `page_path` VARCHAR(120) NOT NULL,
                `page_views` INT UNSIGNED NOT NULL DEFAULT 0,
                `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
                UNIQUE KEY `uniq_date_page` (`visit_date`, `page_path`),
                INDEX `idx_page` (`page_path`),
                INDEX `idx_date` (`visit_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `site_visit_device_stats` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `visit_date` DATE NOT NULL,
                `device_type` ENUM('desktop', 'mobile', 'tablet', 'bot') NOT NULL,
                `page_views` INT UNSIGNED NOT NULL DEFAULT 0,
                `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
                UNIQUE KEY `uniq_date_device` (`visit_date`, `device_type`),
                INDEX `idx_device` (`device_type`),
                INDEX `idx_date` (`visit_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS `site_visit_events` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `visit_date` DATE NOT NULL,
                `visitor_hash` VARCHAR(32) NOT NULL,
                `player_id` INT UNSIGNED NULL,
                `country_code` VARCHAR(5) NOT NULL DEFAULT 'XX',
                `device_type` ENUM('desktop', 'mobile', 'tablet', 'bot') NOT NULL DEFAULT 'desktop',
                `page_path` VARCHAR(120) NOT NULL,
                `referrer_host` VARCHAR(120) NOT NULL DEFAULT '',
                `created_at` DATETIME NOT NULL,
                INDEX `idx_date_hash` (`visit_date`, `visitor_hash`),
                INDEX `idx_date_country` (`visit_date`, `country_code`),
                INDEX `idx_date_player` (`visit_date`, `player_id`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];

        try {
            foreach ($statements as $sql) {
                $db->exec($sql);
            }
            self::$bootstrapped = true;
        } catch (Throwable $e) {
            // Silently record error or ignore if permissions are restricted.
            // Po cichu zarejestruj blad lub zignoruj przy braku uprawnien.
            error_log('[VisitTrackerService] Bootstrap table creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Record a visit from global environment.
     * Zarejestruj wizyte ze srodowiska globalnego.
     */
    public static function recordFromGlobals(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET') {
            return;
        }

        // Skip AJAX and API calls.
        // Pomin zapytania AJAX oraz API.
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        // Ignore admin, cron and api endpoints.
        // Ignoruj panel admina, cron i endpointy API.
        if (str_starts_with($path, '/admin/') || str_starts_with($path, '/api/') || str_starts_with($path, '/cron/')) {
            return;
        }

        // Ignore static assets.
        // Ignoruj pliki statyczne.
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|webp|woff|woff2|ttf|map|json|xml|txt)$/i', $path)) {
            return;
        }

        $playerId = null;
        if (class_exists('Auth', false) && method_exists('Auth', 'isLoggedIn') && Auth::isLoggedIn()) {
            $playerId = Auth::getUserId();
        }

        try {
            if (!class_exists('Database', false)) {
                return;
            }
            $db = Database::getInstance()->getConnection();
            self::recordVisit($db, $_SERVER, $playerId);
        } catch (Throwable $e) {
            // Failsafe - visit tracker must never crash normal gameplay.
            // Zabezpieczenie - licznik odwiedzin nigdy nie moze popsuc rozgrywki.
            error_log('[VisitTrackerService] Error recording visit: ' . $e->getMessage());
        }
    }

    /**
     * Record visit details into the database.
     * Zapisz szczegoly wizyty w bazie danych.
     *
     * @param array<string, mixed> $server
     */
    public static function recordVisit(PDO $db, array $server, ?int $playerId = null): bool
    {
        self::ensureTablesExist($db);

        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $ip = (string)($server['REMOTE_ADDR'] ?? '127.0.0.1');
        $userAgent = (string)($server['HTTP_USER_AGENT'] ?? '');
        $referrer = (string)($server['HTTP_REFERER'] ?? '');
        $uri = (string)($server['REQUEST_URI'] ?? '/');
        $pagePath = self::normalizePath($uri);

        $countryCode = self::detectCountryCode($server, $ip);
        $deviceType = self::detectDeviceType($userAgent);
        $referrerHost = self::extractReferrerHost($referrer, (string)($server['HTTP_HOST'] ?? ''));

        // Anonymized daily hash for unique visitor counting (GDPR compliant).
        // Anonimowy dzienny hash unikalnego goscia (zgodny z RODO).
        $salt = 'oilempire_visit_' . $today;
        $visitorHash = md5($ip . '|' . $userAgent . '|' . $salt);

        $isPlayer = ($playerId !== null && $playerId > 0);
        $isBot = ($deviceType === 'bot');

        // Check if visitor has already been recorded today (and which pages).
        // Sprawdz czy odwiedzajacy byl juz dzisiaj odnotowany (i jakie podstrony).
        $checkStmt = $db->prepare("SELECT page_path FROM site_visit_events WHERE visit_date = ? AND visitor_hash = ?");
        $checkStmt->execute([$today, $visitorHash]);
        $existingPages = $checkStmt->fetchAll(PDO::FETCH_COLUMN);
        $isNewUnique = empty($existingPages);
        $isNewPageUnique = !in_array($pagePath, $existingPages, true);

        // 1. Insert detailed event (retains retention window).
        // 1. Zapisz szczegolowe zdarzenie (z oknem retencji).
        $eventStmt = $db->prepare("
            INSERT INTO site_visit_events
                (visit_date, visitor_hash, player_id, country_code, device_type, page_path, referrer_host, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $eventStmt->execute([
            $today,
            $visitorHash,
            $playerId,
            $countryCode,
            $deviceType,
            $pagePath,
            $referrerHost,
            $now,
        ]);

        $uvInc = $isNewUnique ? 1 : 0;
        $playerInc = $isPlayer ? 1 : 0;
        $guestInc = (!$isPlayer && !$isBot) ? 1 : 0;
        $botInc = $isBot ? 1 : 0;

        // 2. Aggregate into site_visits_daily.
        // 2. Agreguj do site_visits_daily.
        $dailyStmt = $db->prepare("
            INSERT INTO site_visits_daily
                (visit_date, page_views, unique_visitors, player_views, guest_views, bot_views, created_at, updated_at)
            VALUES (?, 1, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                page_views = page_views + 1,
                unique_visitors = unique_visitors + VALUES(unique_visitors),
                player_views = player_views + VALUES(player_views),
                guest_views = guest_views + VALUES(guest_views),
                bot_views = bot_views + VALUES(bot_views),
                updated_at = VALUES(updated_at)
        ");
        $dailyStmt->execute([$today, $uvInc, $playerInc, $guestInc, $botInc, $now, $now]);

        // 3. Aggregate into site_visit_country_stats.
        // 3. Agreguj do site_visit_country_stats.
        $countryStmt = $db->prepare("
            INSERT INTO site_visit_country_stats
                (visit_date, country_code, page_views, unique_visitors)
            VALUES (?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE
                page_views = page_views + 1,
                unique_visitors = unique_visitors + VALUES(unique_visitors)
        ");
        $countryStmt->execute([$today, $countryCode, $uvInc]);

        // 4. Aggregate into site_visit_page_stats.
        // 4. Agreguj do site_visit_page_stats.
        $pageStmt = $db->prepare("
            INSERT INTO site_visit_page_stats
                (visit_date, page_path, page_views, unique_visitors)
            VALUES (?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE
                page_views = page_views + 1,
                unique_visitors = unique_visitors + VALUES(unique_visitors)
        ");
        $pageStmt->execute([$today, $pagePath, $isNewPageUnique ? 1 : 0]);

        // 5. Aggregate into site_visit_device_stats.
        // 5. Agreguj do site_visit_device_stats.
        $devStmt = $db->prepare("
            INSERT INTO site_visit_device_stats
                (visit_date, device_type, page_views, unique_visitors)
            VALUES (?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE
                page_views = page_views + 1,
                unique_visitors = unique_visitors + VALUES(unique_visitors)
        ");
        $devStmt->execute([$today, $deviceType, $uvInc]);

        return true;
    }

    /**
     * Detect country code from HTTP headers and IP.
     * Wykryj kod panstwa z naglowkow HTTP i adresu IP.
     *
     * @param array<string, mixed> $server
     */
    public static function detectCountryCode(array $server, string $ip): string
    {
        // 1. Cloudflare header (highest accuracy, 0ms latency).
        // 1. Naglowek Cloudflare (najwyzsza dokladnosc, zerowy narzut).
        if (!empty($server['HTTP_CF_IPCOUNTRY'])) {
            $code = strtoupper(trim((string)$server['HTTP_CF_IPCOUNTRY']));
            if (strlen($code) === 2 && ctype_alpha($code) && $code !== 'XX' && $code !== 'T1') {
                return $code;
            }
        }

        // 2. Server GeoIP module headers (mod_geoip / cPanel).
        // 2. Naglowki modulu GeoIP serwera.
        foreach (['GEOIP_COUNTRY_CODE', 'HTTP_X_COUNTRY_CODE', 'HTTP_GEOIP_COUNTRY_CODE'] as $h) {
            if (!empty($server[$h])) {
                $code = strtoupper(trim((string)$server[$h]));
                if (strlen($code) === 2 && ctype_alpha($code)) {
                    return $code;
                }
            }
        }

        // 3. Localhost and private subnet identification.
        // 3. Identyfikacja localhosta oraz podsieci prywatnych.
        if (
            $ip === '127.0.0.1' ||
            $ip === '::1' ||
            str_starts_with($ip, '192.168.') ||
            str_starts_with($ip, '10.') ||
            str_starts_with($ip, '172.16.')
        ) {
            // In local development default to PL.
            // W srodowisku deweloperskim domyslnie Polska.
            return 'PL';
        }

        // 4. Accept-Language header fallback (fast, no external requests).
        // 4. Fallback na naglowek jezyka przegladarki (szybki, bez zapytan sieciowych).
        if (!empty($server['HTTP_ACCEPT_LANGUAGE'])) {
            $langHeader = strtolower((string)$server['HTTP_ACCEPT_LANGUAGE']);
            if (preg_match('/[a-z]{2}-([a-z]{2})/i', $langHeader, $m)) {
                $candidate = strtoupper($m[1]);
                if (strlen($candidate) === 2 && ctype_alpha($candidate)) {
                    return $candidate;
                }
            }

            // Simple primary language fallback.
            // Prosty fallback na glowny jezyk.
            if (str_starts_with($langHeader, 'pl')) return 'PL';
            if (str_starts_with($langHeader, 'de')) return 'DE';
            if (str_starts_with($langHeader, 'en')) return 'GB';
            if (str_starts_with($langHeader, 'uk')) return 'UA';
            if (str_starts_with($langHeader, 'fr')) return 'FR';
            if (str_starts_with($langHeader, 'es')) return 'ES';
            if (str_starts_with($langHeader, 'it')) return 'IT';
            if (str_starts_with($langHeader, 'cs')) return 'CZ';
            if (str_starts_with($langHeader, 'sk')) return 'SK';
        }

        return 'XX';
    }

    /**
     * Detect device type from User-Agent string.
     * Wykryj typ urzadzenia z ciagu User-Agent.
     */
    public static function detectDeviceType(string $userAgent): string
    {
        $ua = strtolower($userAgent);

        // Bots and crawlers.
        // Boty i crawlery.
        if (preg_match('/(googlebot|bingbot|yandexbot|duckduckbot|slurp|baiduspider|twitterbot|facebookexternalhit|rogerbot|linkedinbot|embedly|quora|showyoubot|outbrain|pinterest|ia_archiver|mj12bot|ahrefsbot|semrushbot|dotbot)/i', $ua)) {
            return 'bot';
        }

        // Tablets.
        // Tablety.
        if (preg_match('/(ipad|tablet|playbook|silk)|(android(?!.*mobi))/i', $ua)) {
            return 'tablet';
        }

        // Mobile phones.
        // Telefony mobilne.
        if (preg_match('/(mobile|iphone|ipod|blackberry|opera mini|iemobile|windows phone|android.*mobile)/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Clean and normalize page path.
     * Oczysc i znormalizuj sciezke podstrony.
     */
    public static function normalizePath(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = trim($path);
        if ($path === '' || $path === '/index.php') {
            return '/';
        }
        return substr($path, 0, 120);
    }

    /**
     * Extract hostname from referrer.
     * Wyodrebnij nazwe hosta z referera.
     */
    public static function extractReferrerHost(string $referrer, string $currentHost): string
    {
        if (empty($referrer)) {
            return 'direct';
        }

        $refHost = parse_url($referrer, PHP_URL_HOST);
        if (!$refHost) {
            return 'direct';
        }

        $refHost = strtolower($refHost);
        $currHost = strtolower(explode(':', $currentHost)[0]);

        if ($refHost === $currHost || str_ends_with($refHost, '.' . $currHost)) {
            return 'internal';
        }

        return substr($refHost, 0, 120);
    }

    /**
     * Get translated country name by code.
     * Pobierz przetlumaczona nazwe panstwa na podstawie kodu.
     */
    public static function getCountryName(string $code): string
    {
        $code = strtoupper(trim($code));
        $langKey = 'admin.visits.country_' . strtolower($code);

        if (function_exists('t')) {
            $translated = t($langKey);
            if ($translated !== $langKey) {
                return $translated;
            }
        }

        return $code;
    }

    /**
     * Query all analytics data for dashboard view.
     * Pobierz wszystkie dane analityczne dla widoku panelu.
     *
     * @return array<string, mixed>
     */
    public static function getDashboardData(PDO $db, string $period = '7d', ?string $countryFilter = null): array
    {
        self::ensureTablesExist($db);

        $today = date('Y-m-d');
        $startDate = match ($period) {
            'today' => $today,
            '30d'   => date('Y-m-d', strtotime('-29 days')),
            '90d'   => date('Y-m-d', strtotime('-89 days')),
            'all'   => '2020-01-01',
            default => date('Y-m-d', strtotime('-6 days')), // '7d'
        };

        // 1. KPI Totals.
        // 1. Sumaryczne wskazniki KPI.
        $summary = [
            'total_pv' => 0,
            'total_uv' => 0,
            'player_views' => 0,
            'guest_views' => 0,
            'bot_views' => 0,
            'top_country' => '-',
            'top_country_uv' => 0,
            'avg_pv_per_uv' => 0.0,
        ];

        if ($countryFilter) {
            $stmt = $db->prepare("
                SELECT
                    COALESCE(SUM(page_views), 0) AS total_pv,
                    COALESCE(SUM(unique_visitors), 0) AS total_uv
                FROM site_visit_country_stats
                WHERE visit_date BETWEEN ? AND ? AND country_code = ?
            ");
            $stmt->execute([$startDate, $today, $countryFilter]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $summary['total_pv'] = (int)$row['total_pv'];
                $summary['total_uv'] = (int)$row['total_uv'];
            }

            $subStmt = $db->prepare("
                SELECT
                    COUNT(CASE WHEN player_id IS NOT NULL AND player_id > 0 THEN 1 END) AS player_views,
                    COUNT(CASE WHEN (player_id IS NULL OR player_id = 0) AND device_type != 'bot' THEN 1 END) AS guest_views,
                    COUNT(CASE WHEN device_type = 'bot' THEN 1 END) AS bot_views
                FROM site_visit_events
                WHERE visit_date BETWEEN ? AND ? AND country_code = ?
            ");
            $subStmt->execute([$startDate, $today, $countryFilter]);
            $subRow = $subStmt->fetch(PDO::FETCH_ASSOC);
            if ($subRow) {
                $summary['player_views'] = (int)$subRow['player_views'];
                $summary['guest_views'] = (int)$subRow['guest_views'];
                $summary['bot_views'] = (int)$subRow['bot_views'];
            }
        } else {
            $stmt = $db->prepare("
                SELECT
                    COALESCE(SUM(page_views), 0) AS total_pv,
                    COALESCE(SUM(unique_visitors), 0) AS total_uv,
                    COALESCE(SUM(player_views), 0) AS player_views,
                    COALESCE(SUM(guest_views), 0) AS guest_views,
                    COALESCE(SUM(bot_views), 0) AS bot_views
                FROM site_visits_daily
                WHERE visit_date BETWEEN ? AND ?
            ");
            $stmt->execute([$startDate, $today]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $summary['total_pv'] = (int)$row['total_pv'];
                $summary['total_uv'] = (int)$row['total_uv'];
                $summary['player_views'] = (int)$row['player_views'];
                $summary['guest_views'] = (int)$row['guest_views'];
                $summary['bot_views'] = (int)$row['bot_views'];
            }
        }

        if ($summary['total_uv'] > 0) {
            $summary['avg_pv_per_uv'] = round($summary['total_pv'] / $summary['total_uv'], 2);
        }

        // 2. Breakdown by Country.
        // 2. Podzial na panstwa.
        $countryStmt = $db->prepare("
            SELECT
                country_code,
                SUM(page_views) AS page_views,
                SUM(unique_visitors) AS unique_visitors
            FROM site_visit_country_stats
            WHERE visit_date BETWEEN ? AND ?
            GROUP BY country_code
            ORDER BY unique_visitors DESC, page_views DESC
            LIMIT 30
        ");
        $countryStmt->execute([$startDate, $today]);
        $countries = $countryStmt->fetchAll(PDO::FETCH_ASSOC);

        $countryList = [];
        $totalCountryUv = 0;
        foreach ($countries as $c) {
            $totalCountryUv += (int)$c['unique_visitors'];
        }

        foreach ($countries as $c) {
            $code = (string)$c['country_code'];
            $uv = (int)$c['unique_visitors'];
            $pv = (int)$c['page_views'];
            $pct = $totalCountryUv > 0 ? round(($uv / $totalCountryUv) * 100, 1) : 0.0;

            $countryList[] = [
                'code' => $code,
                'name' => self::getCountryName($code),
                'unique_visitors' => $uv,
                'page_views' => $pv,
                'percent' => $pct,
            ];
        }

        if (!empty($countryList[0])) {
            $summary['top_country'] = $countryList[0]['name'] . ' [' . $countryList[0]['code'] . ']';
            $summary['top_country_uv'] = $countryList[0]['unique_visitors'];
        }

        // 3. Timeline / Trend chart data.
        // 3. Os czasu / dane do wykresu trendu.
        if ($countryFilter) {
            $timelineStmt = $db->prepare("
                SELECT
                    visit_date,
                    page_views,
                    unique_visitors,
                    0 AS player_views,
                    0 AS guest_views
                FROM site_visit_country_stats
                WHERE visit_date BETWEEN ? AND ? AND country_code = ?
                ORDER BY visit_date ASC
            ");
            $timelineStmt->execute([$startDate, $today, $countryFilter]);
        } else {
            $timelineStmt = $db->prepare("
                SELECT
                    visit_date,
                    page_views,
                    unique_visitors,
                    player_views,
                    guest_views
                FROM site_visits_daily
                WHERE visit_date BETWEEN ? AND ?
                ORDER BY visit_date ASC
            ");
            $timelineStmt->execute([$startDate, $today]);
        }
        $timelineRows = $timelineStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fill gap dates so chart has full continuous progression.
        // Uzupelnij brakujace dni, aby wykres mial ciaglosc.
        $timeline = [];
        $curr = strtotime($startDate);
        $end = strtotime($today);
        $timelineMap = [];
        foreach ($timelineRows as $tr) {
            $timelineMap[$tr['visit_date']] = $tr;
        }

        while ($curr <= $end) {
            $dStr = date('Y-m-d', $curr);
            if (isset($timelineMap[$dStr])) {
                $timeline[] = [
                    'date' => $dStr,
                    'label' => date('d.m', $curr),
                    'pv' => (int)$timelineMap[$dStr]['page_views'],
                    'uv' => (int)$timelineMap[$dStr]['unique_visitors'],
                    'player_views' => (int)$timelineMap[$dStr]['player_views'],
                    'guest_views' => (int)$timelineMap[$dStr]['guest_views'],
                ];
            } else {
                $timeline[] = [
                    'date' => $dStr,
                    'label' => date('d.m', $curr),
                    'pv' => 0,
                    'uv' => 0,
                    'player_views' => 0,
                    'guest_views' => 0,
                ];
            }
            $curr = strtotime('+1 day', $curr);
        }

        // 4. Breakdown by Pages.
        // 4. Podzial na podstrony.
        if ($countryFilter) {
            $pagesStmt = $db->prepare("
                SELECT
                    page_path,
                    COUNT(*) AS page_views,
                    COUNT(DISTINCT visitor_hash) AS unique_visitors
                FROM site_visit_events
                WHERE visit_date BETWEEN ? AND ? AND country_code = ?
                GROUP BY page_path
                ORDER BY page_views DESC
                LIMIT 15
            ");
            $pagesStmt->execute([$startDate, $today, $countryFilter]);
        } else {
            $pagesStmt = $db->prepare("
                SELECT
                    page_path,
                    SUM(page_views) AS page_views,
                    SUM(unique_visitors) AS unique_visitors
                FROM site_visit_page_stats
                WHERE visit_date BETWEEN ? AND ?
                GROUP BY page_path
                ORDER BY page_views DESC
                LIMIT 15
            ");
            $pagesStmt->execute([$startDate, $today]);
        }
        $topPages = $pagesStmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Breakdown by Device.
        // 5. Podzial na urzadzenia.
        if ($countryFilter) {
            $deviceStmt = $db->prepare("
                SELECT
                    device_type,
                    COUNT(*) AS page_views,
                    COUNT(DISTINCT visitor_hash) AS unique_visitors
                FROM site_visit_events
                WHERE visit_date BETWEEN ? AND ? AND country_code = ?
                GROUP BY device_type
                ORDER BY page_views DESC
            ");
            $deviceStmt->execute([$startDate, $today, $countryFilter]);
        } else {
            $deviceStmt = $db->prepare("
                SELECT
                    device_type,
                    SUM(page_views) AS page_views,
                    SUM(unique_visitors) AS unique_visitors
                FROM site_visit_device_stats
                WHERE visit_date BETWEEN ? AND ?
                GROUP BY device_type
                ORDER BY page_views DESC
            ");
            $deviceStmt->execute([$startDate, $today]);
        }
        $devices = $deviceStmt->fetchAll(PDO::FETCH_ASSOC);

        // 6. Recent visit log (live feed).
        // 6. Ostatnie logi wejsc (podglad na zywo).
        if ($countryFilter) {
            $recentStmt = $db->prepare("
                SELECT
                    created_at,
                    country_code,
                    device_type,
                    page_path,
                    referrer_host,
                    player_id
                FROM site_visit_events
                WHERE country_code = ?
                ORDER BY id DESC
                LIMIT 25
            ");
            $recentStmt->execute([$countryFilter]);
        } else {
            $recentStmt = $db->prepare("
                SELECT
                    created_at,
                    country_code,
                    device_type,
                    page_path,
                    referrer_host,
                    player_id
                FROM site_visit_events
                ORDER BY id DESC
                LIMIT 25
            ");
            $recentStmt->execute();
        }
        $recentEvents = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $today,
            'countryFilter' => $countryFilter,
            'summary' => $summary,
            'countries' => $countryList,
            'timeline' => $timeline,
            'topPages' => $topPages,
            'devices' => $devices,
            'recentEvents' => $recentEvents,
        ];
    }
}
