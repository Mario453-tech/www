<?php
// Public news API for players.
// PL: Publiczne API newsow dla graczy.
ob_start();
require_once __DIR__ . '/init.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');

/**
 * Sanitizes admin news HTML before exposing it to the player UI.
 * PL: Czyci HTML aktualnosci admina przed pokazaniem w UI gracza.
 */
function sanitizeNewsHtml(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    if (!class_exists('DOMDocument')) {
        return strip_tags($html, '<p><br><strong><b><em><i><u><s><span><div><ul><ol><li><a><h2><h3><h4><blockquote>');
    }

    $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'span', 'div',
        'ul', 'ol', 'li', 'a', 'h2', 'h3', 'h4', 'blockquote',
    ];
    $dropTags = ['script', 'style', 'iframe', 'object', 'embed', 'meta', 'link'];

    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML(
        '<?xml encoding="utf-8" ?><div id="news-html-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = $doc->getElementById('news-html-root');
    if (!$root instanceof DOMElement) {
        return '';
    }

    $sanitizeStyle = static function (string $style): string {
        $allowed = [
            'color',
            'background-color',
            'text-align',
            'font-weight',
            'font-style',
            'text-decoration',
        ];
        $parts = array_filter(array_map('trim', explode(';', $style)));
        $safe = [];

        foreach ($parts as $part) {
            [$prop, $value] = array_pad(explode(':', $part, 2), 2, '');
            $prop = strtolower(trim($prop));
            $value = trim($value);

            if ($prop === '' || $value === '' || !in_array($prop, $allowed, true)) {
                continue;
            }

            $isValid = match ($prop) {
                'color', 'background-color' => (bool) preg_match('/^(#[0-9a-f]{3,8}|rgba?\([0-9.,%\s]+\)|[a-z]+)$/i', $value),
                'text-align' => in_array(strtolower($value), ['left', 'right', 'center', 'justify'], true),
                'font-weight' => (bool) preg_match('/^(normal|bold|[1-9]00)$/i', $value),
                'font-style' => in_array(strtolower($value), ['normal', 'italic', 'oblique'], true),
                'text-decoration' => (bool) preg_match('/^(none|underline|line-through)$/i', strtolower($value)),
                default => false,
            };

            if ($isValid) {
                $safe[] = $prop . ': ' . $value;
            }
        }

        return implode('; ', $safe);
    };

    $sanitizeNode = null;
    $sanitizeNode = static function (DOMNode $node) use (&$sanitizeNode, $doc, $allowedTags, $dropTags, $sanitizeStyle): void {
        if (!$node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);

        foreach (iterator_to_array($node->childNodes) as $child) {
            $sanitizeNode($child);
        }

        if (in_array($tag, $dropTags, true)) {
            $node->parentNode?->removeChild($node);
            return;
        }

        if (!in_array($tag, $allowedTags, true)) {
            $fragment = $doc->createDocumentFragment();
            while ($node->firstChild) {
                $fragment->appendChild($node->firstChild);
            }
            $node->parentNode?->replaceChild($fragment, $node);
            return;
        }

        $attrsToRemove = [];
        foreach (iterator_to_array($node->attributes) as $attr) {
            $name = strtolower($attr->nodeName);
            $value = (string) $attr->nodeValue;

            if (str_starts_with($name, 'on')) {
                $attrsToRemove[] = $attr->nodeName;
                continue;
            }

            if ($name === 'style') {
                $safeStyle = $sanitizeStyle($value);
                if ($safeStyle === '') {
                    $attrsToRemove[] = $attr->nodeName;
                } else {
                    $node->setAttribute('style', $safeStyle);
                }
                continue;
            }

            if ($tag === 'a' && $name === 'href') {
                if (!preg_match('~^(https?://|mailto:|/|#)~i', $value)) {
                    $attrsToRemove[] = $attr->nodeName;
                }
                continue;
            }

            if ($tag === 'a' && in_array($name, ['target', 'rel'], true)) {
                continue;
            }

            if ($name === 'class') {
                continue;
            }

            $attrsToRemove[] = $attr->nodeName;
        }

        foreach ($attrsToRemove as $attrName) {
            $node->removeAttribute($attrName);
        }
    };

    foreach (iterator_to_array($root->childNodes) as $childNode) {
        $sanitizeNode($childNode);
    }

    $output = '';
    foreach ($root->childNodes as $childNode) {
        $output .= $doc->saveHTML($childNode);
    }

    return trim($output);
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
        $row['content_html'] = sanitizeNewsHtml((string)($row['content'] ?? ''));
    }
    unset($row);

    echo json_encode(['news' => $rows]);
} catch (Throwable $e) {
    echo json_encode(['news' => []]);
}
