<?php
// Public news API for players.
// PL: Publiczne API newsow dla graczy.
ob_start();
require_once __DIR__ . '/init.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

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
    echo json_encode(['news' => [], 'pinned' => []]);
    exit;
}

// Fetch active news with pinned items first.
// PL: Pobierz aktywne newsy, z przypietymi na gorze.
try {
    $stmt = $db->query("
        SELECT id, title, content, is_pinned, created_by, created_at
        FROM admin_news
        WHERE active = 1
        ORDER BY is_pinned DESC, created_at DESC
        LIMIT 20
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['date_fmt'] = humanNewsTime((string)$row['created_at']);
        $row['is_pinned'] = (int)$row['is_pinned'];
    }
    unset($row);

    echo json_encode(['news' => $rows]);
} catch (Throwable $e) {
    echo json_encode(['news' => []]);
}
