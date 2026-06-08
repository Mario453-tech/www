<?php
// Public news API for players.
// PL: Publiczne API newsow dla graczy.
ob_start();
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/AdminNewsHtml.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

/**
 * Sends JSON with UTF-8 fallback and logs encode failures.
 * PL: Wysyla JSON z fallbackiem UTF-8 i loguje bledy kodowania.
 *
 * @param array<string,mixed> $payload
 */
function sendNewsJson(array $payload): void
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        if (class_exists('GameLog', false)) {
            GameLog::error('AdminNewsApi', 'json_encode FAILED', [
                'error' => json_last_error_msg(),
            ]);
        }
        echo '{"news":[]}';
        return;
    }

    echo $json;
}

/**
 * Sanitizes admin news HTML before exposing it to the player UI.
 * PL: Czyci HTML aktualnosci admina przed pokazaniem w UI gracza.
 */
function sanitizeNewsHtml(string $html): string
{
    return AdminNewsHtml::sanitizeContent($html);
}

function humanNewsTime(string $dateTime): string
{
    $ts = strtotime($dateTime);
    if ($ts === false) {
        return $dateTime;
    }

    $diff = max(0, time() - $ts);
    $minutes = (int)floor($diff / 60);
    $hours = (int)floor($diff / 3600);
    $days = (int)floor($diff / 86400);

    if ($minutes < 1) {
        return t('news.time_just_now');
    }

    if ($minutes < 60) {
        return $minutes === 1
            ? t('news.time_minute_ago')
            : t('news.time_minutes_ago', ['count' => $minutes]);
    }

    if ($hours < 24) {
        if ($hours === 1) {
            return t('news.time_hour_ago');
        }
        if ($hours >= 2 && $hours <= 4) {
            return t('news.time_hours_ago_few', ['count' => $hours]);
        }
        return t('news.time_hours_ago_many', ['count' => $hours]);
    }

    if ($days < 7) {
        return $days === 1
            ? t('news.time_day_ago')
            : t('news.time_days_ago', ['count' => $days]);
    }

    return date('d.m.Y', $ts);
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('AdminNewsApi', 'database connection FAILED', $e);
    }
    sendNewsJson(['news' => [], 'pinned' => []]);
    exit;
}

$hasTitleHtml = true;
try {
    Database::addColumnIfMissing('admin_news', 'title_html', 'TEXT NULL AFTER `title`');
} catch (Throwable $e) {
    $hasTitleHtml = false;
    if (class_exists('GameLog', false)) {
        GameLog::error('AdminNewsApi', 'admin_news.title_html migration failed', $e);
    }
}

// Fetch active news with pinned items first.
// PL: Pobierz aktywne newsy, z przypietymi na gorze.
try {
    $titleHtmlSelect = $hasTitleHtml ? 'title_html' : 'NULL AS title_html';
    $stmt = $db->query("
        SELECT id, title, {$titleHtmlSelect}, content, is_pinned, created_by, created_at
        FROM admin_news
        WHERE active = 1
        ORDER BY is_pinned DESC, created_at DESC
        LIMIT 20
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $titleSource = trim((string)($row['title_html'] ?? ''));
        if ($titleSource === '') {
            $titleSource = (string)($row['title'] ?? '');
        }
        $titleHtml = AdminNewsHtml::sanitizeTitle($titleSource);
        $titlePlain = AdminNewsHtml::plainText((string)($row['title'] ?? ''));

        $row['date_fmt'] = humanNewsTime((string)$row['created_at']);
        $row['is_pinned'] = (int)$row['is_pinned'];
        $row['title_html'] = $titleHtml !== '' ? $titleHtml : htmlspecialchars($titlePlain, ENT_QUOTES, 'UTF-8');
        $row['title_plain'] = $titlePlain;
        $row['title'] = $titlePlain;
        $row['content_html'] = sanitizeNewsHtml((string)($row['content'] ?? ''));
        unset($row['content']);
    }
    unset($row);

    sendNewsJson(['news' => $rows]);
} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('AdminNewsApi', 'news fetch FAILED', $e);
    }
    sendNewsJson(['news' => []]);
}
