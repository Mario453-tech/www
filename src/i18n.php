<?php
declare(strict_types=1);

/**
 * laduje i zwraca tablice tlumaczen (jedna instancja per request).
 * Loads and returns the translation array (one instance per request).
 *
 * @return array<string, string>
 */
function _langLoad(): array
{
    static $lang = null;
    if ($lang === null) {
        $locale  = $_SESSION['locale'] ?? 'pl';
        $allowed = ['pl', 'en'];
        if (!in_array($locale, $allowed, true)) $locale = 'pl';
        $file = __DIR__ . '/../lang/' . $locale . '.php';
        $lang = file_exists($file) ? (include $file) : [];
    }
    return $lang;
}

/**
 * Tlumaczenie z HTML-escaping do uzycia bezposrednio w szablonach HTML.
 * Translation with HTML-escaping use directly in HTML templates.
 *
 * @param string $key Klucz w formacie modul.klucz / Key in module.key format
 * @param array<string, mixed> $replace Zamienniki :placeholder i {placeholder} / Placeholder replacements
 */
function t(string $key, array $replace = []): string
{
    $lang = _langLoad();
    $str  = $lang[$key] ?? $key;

    foreach ($replace as $k => $v) {
        $str = str_replace(':' . $k, (string)$v, $str);
        $str = str_replace('{' . $k . '}', (string)$v, $str);
    }

    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Tlumaczenie BEZ HTML-escaping do zapisu do bazy danych, logow, maili.
 * Translation WITHOUT HTML-escaping use for DB storage, logs, emails.
 *
 * @param string $key Klucz w formacie modul.klucz / Key in module.key format
 * @param array<string, mixed> $replace Zamienniki :placeholder i {placeholder} / Placeholder replacements
 */
function tPlain(string $key, array $replace = []): string
{
    $lang = _langLoad();
    $str  = $lang[$key] ?? $key;

    foreach ($replace as $k => $v) {
        $str = str_replace(':' . $k, (string)$v, $str);
        $str = str_replace('{' . $k . '}', (string)$v, $str);
    }

    return $str;
}
