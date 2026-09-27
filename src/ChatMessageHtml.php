<?php
declare(strict_types=1);

/** Safe formatting for admin broadcasts. / Bezpieczne formatowanie komunikatow admina. */
final class ChatMessageHtml
{
    public static function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }
        if (!class_exists(DOMDocument::class)) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_NONET);
            $body = $doc->getElementsByTagName('body')->item(0);
            return $body ? trim(self::children($body)) : '';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function children(DOMNode $parent): string
    {
        $html = '';
        foreach ($parent->childNodes as $node) {
            if ($node instanceof DOMText) {
                $html .= htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            } elseif ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template'], true)) {
                    continue;
                }
                $inner = self::children($node);
                if ($tag === 'br') {
                    $html .= '<br>';
                } elseif (in_array($tag, ['p', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li'], true)) {
                    $html .= '<' . $tag . '>' . $inner . '</' . $tag . '>';
                } else {
                    $html .= $inner;
                }
            }
        }
        return $html;
    }

    public static function plainText(string $safeHtml): string
    {
        $spaced = preg_replace('~<br\s*/?>|</(?:p|li)>~i', "\n", $safeHtml);
        $text = html_entity_decode(strip_tags((string)$spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(str_replace("\u{00A0}", ' ', $text));
    }

    /**
     * Keep the mobile/plain-text contract. / Zachowaj kontrakt zwyklego tekstu dla mobile.
     * @param array<string,mixed> $message
     * @return array<string,mixed>
     */
    public static function forApi(array $message): array
    {
        unset($message['message_html']);
        if ((int)($message['is_admin'] ?? 0) === 1) {
            $message['message_html'] = self::sanitize((string)($message['message'] ?? ''));
            $message['message'] = self::plainText($message['message_html']);
        }
        return $message;
    }
}
