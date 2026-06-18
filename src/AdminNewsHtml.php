<?php

/**
 * AdminNewsHtml - sanitizing helpers for admin news rendered in player UI.
 * PL: AdminNewsHtml - helpery czyszczenia HTML newsow admina w UI gracza.
 */
class AdminNewsHtml
{
    /**
     * Sanitize formatted news title from TinyMCE.
     * PL: Czysci formatowany tytul aktualnosci z TinyMCE.
     */
    public static function sanitizeTitle(string $html): string
    {
        return self::sanitize($html, [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'span', 'div',
            'h1', 'h2', 'h3', 'h4',
        ]);
    }

    /**
     * Sanitize formatted news body from TinyMCE.
     * PL: Czysci formatowana tresc aktualnosci z TinyMCE.
     */
    public static function sanitizeContent(string $html): string
    {
        return self::sanitize($html, [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'span', 'div',
            'ul', 'ol', 'li', 'a', 'h1', 'h2', 'h3', 'h4', 'blockquote',
        ]);
    }

    /**
     * Convert TinyMCE HTML to compact plain text.
     * PL: Zamienia HTML z TinyMCE na zwiezly tekst.
     */
    public static function plainText(string $html, int $limit = 0): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return self::limitText($text, $limit);
    }

    /**
     * Limit plain text without breaking multibyte characters when possible.
     * PL: Skraca zwykly tekst bez psucia znakow wielobajtowych gdy to mozliwe.
     */
    public static function limitText(string $text, int $limit): string
    {
        if ($limit <= 0) {
            return $text;
        }

        if ($limit > 0 && function_exists('mb_strimwidth')) {
            return mb_strimwidth($text, 0, $limit, '', 'UTF-8');
        }

        if ($limit > 0 && strlen($text) > $limit) {
            return substr($text, 0, $limit);
        }

        return $text;
    }

    /**
     * Sanitize HTML against a small TinyMCE allowlist.
     * PL: Czysci HTML wedlug malej allowlisty TinyMCE.
     *
     * @param array<int,string> $allowedTags
     */
    private static function sanitize(string $html, array $allowedTags): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        if (!class_exists('DOMDocument')) {
            $fallback = strip_tags($html, '<' . implode('><', $allowedTags) . '>');
            return (string) preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $fallback);
        }

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

        self::sanitizeChildren($doc, $root, $allowedTags, $dropTags);

        $output = '';
        foreach ($root->childNodes as $childNode) {
            $output .= $doc->saveHTML($childNode);
        }

        return trim($output);
    }

    /**
     * Walk and sanitize child nodes recursively.
     * PL: Przechodzi rekurencyjnie po wezlach potomnych i je czysci.
     *
     * @param array<int,string> $allowedTags
     * @param array<int,string> $dropTags
     */
    private static function sanitizeChildren(DOMDocument $doc, DOMNode $root, array $allowedTags, array $dropTags): void
    {
        foreach (iterator_to_array($root->childNodes) as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }

            self::sanitizeChildren($doc, $child, $allowedTags, $dropTags);
            self::sanitizeElement($doc, $child, $allowedTags, $dropTags);
        }
    }

    /**
     * Sanitize a single HTML element and its attributes.
     * PL: Czysci pojedynczy element HTML i jego atrybuty.
     *
     * @param array<int,string> $allowedTags
     * @param array<int,string> $dropTags
     */
    private static function sanitizeElement(DOMDocument $doc, DOMElement $node, array $allowedTags, array $dropTags): void
    {
        $tag = strtolower($node->tagName);

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
                $safeStyle = self::sanitizeStyle($value);
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

            $attrsToRemove[] = $attr->nodeName;
        }

        foreach ($attrsToRemove as $attrName) {
            $node->removeAttribute($attrName);
        }
    }

    /**
     * Keep only safe inline styles used by TinyMCE.
     * PL: Zachowuje tylko bezpieczne style inline z TinyMCE.
     */
    private static function sanitizeStyle(string $style): string
    {
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
            };

            if ($isValid) {
                $safe[] = $prop . ': ' . $value;
            }
        }

        return implode('; ', $safe);
    }
}
