<?php
/**
 * fix_emoji_to_svg.php  v2
 *
 * Kompleksowe rozwiazanie:
 * 1. Naprawia podwojne kodowanie (iconv UTF-8->CP1250).
 * 2. Wykrywa WSZYSTKIE emoji/symbole w kazdym pliku.
 * 3. Tworzy brakujace pliki SVG automatycznie (XML char references = czysty ASCII).
 * 4. Podmiena emoji na wgIco() uzywajac tokenizera PHP (kontekst HTML vs PHP string).
 *
 * Comprehensive solution:
 * 1. Fixes double-encoding.
 * 2. Detects ALL emoji/symbols in every file.
 * 3. Auto-creates missing SVG files (XML char refs = pure ASCII, no encoding issues).
 * 4. Replaces emoji with wgIco() using PHP tokenizer (HTML vs PHP string context).
 *
 * Uzycie / Usage:
 *   php fix_emoji_to_svg.php              -- zapis / write
 *   php fix_emoji_to_svg.php --dry-run    -- podglad / preview
 */
declare(strict_types=1);

$root   = __DIR__ . '/';
$args   = $_SERVER['argv'] ?? [];
$dryRun = in_array('--dry-run', $args, true);

define('SVG_DIR', __DIR__ . '/assets/img/icons/wg/');

//  Znane emoji => nazwa ikony SVG 
// Known emoji => existing SVG icon name (descriptive icons already created)
const KNOWN_EMOJI = [
    "\xE2\x9A\x99\xEF\xB8\x8F" => 'gear',          // 
    "\xE2\x9A\x99"             => 'gear',           // 
    "\xF0\x9F\x92\xB0"         => 'coin',           // 
    "\xF0\x9F\x92\xB8"         => 'coin',           // 
    "\xF0\x9F\xAA\xA8"         => 'rock',           // 
    "\xE2\x96\xB2"             => 'chevron-up',     // 
    "\xE2\x96\xBC"             => 'chevron-down',   // 
    "\xE2\x96\xB4"             => 'chevron-up',     //  (small)
    "\xE2\x96\xBE"             => 'chevron-down',   //  (small)
    "\xE2\xAC\x86\xEF\xB8\x8F" => 'arrow-up',      // 
    "\xE2\xAC\x86"             => 'arrow-up',       // 
    "\xE2\x8F\xB3"             => 'hourglass',      // 
    "\xE2\x8F\xB1"             => 'hourglass',      // 
    "\xE2\x9A\xA0\xEF\xB8\x8F" => 'warning',       // 
    "\xE2\x9A\xA0"             => 'warning',        // 
    "\xF0\x9F\x8C\xBF"         => 'leaf',           // 
    "\xE2\x9A\xA1\xEF\xB8\x8F" => 'lightning',     // 
    "\xE2\x9A\xA1"             => 'lightning',      // 
    "\xF0\x9F\x94\x84"         => 'refresh',        // 
    "\xF0\x9F\x97\x91\xEF\xB8\x8F" => 'trash',     // 
    "\xF0\x9F\x97\x91"         => 'trash',          // 
    "\xE2\x9D\x93"             => 'question',       // 
    "\xF0\x9F\x9F\xA2"         => 'circle-check',   // 
    "\xE2\x9C\x85"             => 'circle-check',   // 
    "\xF0\x9F\x94\xB4"         => 'warning',        // 
    "\xE2\x9D\x8C"             => 'warning',        // 
    "\xE2\xAF\xB8\xEF\xB8\x8F" => 'pause',         // 
    "\xE2\xAF\xB8"             => 'pause',          // 
    "\xF0\x9F\x9B\xA2\xEF\xB8\x8F" => 'oil-drop',  // 
    "\xF0\x9F\x9B\xA2"         => 'oil-drop',       // 
    "\xE2\x9B\xAD\xEF\xB8\x8F" => 'oil-drop',      // 
    "\xE2\x9B\xAD"             => 'oil-drop',       // 
    "\xE2\x86\x91"             => 'arrow-up',       // 
    "\xE2\x86\x93"             => 'chevron-down',   // 
];

//  Pliki docelowe 
const TARGETS_SVG = [
    'templates/components/well_grid.php',
    'templates/components/well_grid/equipment.php',
    'templates/components/well_grid/layers.php',
    'templates/components/well_grid/danger_zone.php',
    'templates/components/well_grid/transport.php',
];

echo "=== fix_emoji_to_svg.php v2 ===\n";
echo "Tryb / Mode: " . ($dryRun ? 'DRY-RUN' : 'ZAPIS/WRITE') . "\n\n";

$totalFixed = 0;

foreach (TARGETS_SVG as $relPath) {
    $changed = processFile($root . $relPath, $relPath, $dryRun);
    if ($changed) $totalFixed++;
}

echo "\n=== Gotowe. Zmienionych plikow: {$totalFixed} ===\n";
if ($dryRun) echo "[DRY-RUN] Brak zapisu. Uruchom bez --dry-run.\n";


// 
// Glowna funkcja przetwarzania pliku
// 

function processFile(string $fullPath, string $relPath, bool $dryRun): bool
{
    if (!file_exists($fullPath)) {
        echo "[BRAK]   {$relPath}\n";
        return false;
    }

    $orig = file_get_contents($fullPath);
    if ($orig === false) {
        echo "[BLAD]   Nie mozna odczytac: {$relPath}\n";
        return false;
    }

    // Strip BOM
    if (str_starts_with($orig, "\xef\xbb\xbf")) {
        $orig = substr($orig, 3);
    }

    // Krok 1: iconv (napraw podwojne kodowanie)
    $content = iconv('UTF-8', 'CP1250//IGNORE', $orig);
    if ($content === false) {
        echo "[BLAD]   iconv() zwrocil false: {$relPath}\n";
        return false;
    }

    // Krok 2: wykryj emoji w pliku po naprawie kodowania
    $emojiMap = discoverEmoji($content);

    if (empty($emojiMap)) {
        if ($content === $orig) {
            echo "[OK]     Bez zmian: {$relPath}\n";
        } else {
            // iconv cos naprawil ale nie bylo emoji
            echo "[KODOWANIE] {$relPath} (iconv fix, brak emoji)\n";
            if (!$dryRun) saveFile($fullPath, $content, 'iconv');
        }
        return false;
    }

    echo "\n[EMOJI]  {$relPath}: " . count($emojiMap) . " unikalnych sekwencji\n";
    foreach ($emojiMap as $bytes => $name) {
        $hex  = implode(' ', array_map(fn($b) => sprintf('%02X', ord($b)), str_split($bytes)));
        $disp = mb_convert_encoding($bytes, 'UTF-8', 'UTF-8'); // just pass-through for display
        echo "         [{$hex}] => '{$name}'\n";
    }

    // Krok 3: upewnij sie ze pliki SVG istnieja
    foreach ($emojiMap as $bytes => $name) {
        ensureSvg($name, $bytes);
    }

    // Krok 4: podmien emoji uzywajac tokenizera PHP
    $fixed = replaceEmojiInFile($content, $emojiMap);

    if ($fixed === $orig) {
        echo "[OK]     Bez zmian po podmianie: {$relPath}\n";
        return false;
    }

    $diff = strlen($orig) - strlen($fixed);
    echo "[NAPRAWA] {$relPath}  (-{$diff} B)\n";

    if ($dryRun) return true;

    saveFile($fullPath, $fixed, 'svg');
    return true;
}

function saveFile(string $fullPath, string $content, string $suffix): void
{
    $bak = $fullPath . '.bak_' . $suffix . '_' . date('Ymd_His');
    copy($fullPath, $bak);
    echo "  [BAK]    {$bak}\n";
    file_put_contents($fullPath, $content);
    echo "  [OK]     Zapisano.\n";
}


// 
// Wykrywanie emoji w tresci pliku
// 

/**
 * Finds all unique emoji/symbol sequences in the content.
 * Skips characters in the Latin / Latin-Extended ranges (U+0000-U+02FF)
 * so Polish letters (¹, ê, ó…) are NOT touched.
 *
 * Returns: [bytes => icon_name]
 */
function discoverEmoji(string $content): array
{
    $known = KNOWN_EMOJI;
    $map   = [];

    // Sort known by length DESC so longer sequences (with variation selectors) win
    uksort($known, fn($a, $b) => strlen($b) - strlen($a));

    // 1. Check known emoji first
    foreach ($known as $bytes => $name) {
        if (str_contains($content, $bytes)) {
            $map[$bytes] = $name;
        }
    }

    // 2. Find any remaining non-ASCII sequences not already in map
    //    Match full grapheme clusters (base char + combining chars / variation selectors)
    preg_match_all('/[^\x00-\x7F](?:[\x80-\xBF])*(?:\xEF\xB8[\x8F\x8E])?/u', $content, $m);
    foreach (array_unique($m[0] ?? []) as $seq) {
        if (isset($map[$seq])) continue;

        // Get primary codepoint
        $cp = mb_ord($seq, 'UTF-8');
        if ($cp === false) continue;

        // Skip Latin, Latin Extended, IPA, combining diacritics — keep emoji/symbols
        if ($cp <= 0x02FF) continue;     // Latin / Latin Extended (incl. Polish)
        if ($cp >= 0x0300 && $cp <= 0x036F) continue; // Combining diacritical marks
        if ($cp >= 0x0400 && $cp <= 0x04FF) continue; // Cyrillic

        // Variation selectors alone — skip
        if ($cp >= 0xFE00 && $cp <= 0xFE0F) continue;

        // Generate icon name from codepoints (safe ASCII filename)
        $name     = emojiToName($seq);
        $map[$seq] = $name;
    }

    // Sort by length DESC for greedy replacement
    uksort($map, fn($a, $b) => strlen($b) - strlen($a));
    return $map;
}

/**
 * Generates a safe ASCII filename from UTF-8 grapheme cluster.
 * E.g.  (U+2699 U+FE0F) => 'u2699'
 */
function emojiToName(string $emoji): string
{
    $cps  = [];
    $len  = mb_strlen($emoji, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($emoji, $i, 1, 'UTF-8');
        $cp   = mb_ord($char, 'UTF-8');
        if ($cp === false) continue;
        if ($cp >= 0xFE00 && $cp <= 0xFE0F) continue; // skip variation selectors
        $cps[] = sprintf('u%04x', $cp);
    }
    return $cps ? implode('-', $cps) : sprintf('u%04x', mb_ord($emoji, 'UTF-8') ?: 0);
}


// 
// Auto-tworzenie plikow SVG
// 

/**
 * Creates an SVG file for the given emoji if it doesn't exist yet.
 * Uses XML character references (pure ASCII) — no encoding issues ever.
 *
 * Tworzy plik SVG dla emoji jesli nie istnieje.
 * Uzywa referencji XML (czysty ASCII) — zero problemow z kodowaniem.
 */
function ensureSvg(string $name, string $emoji): void
{
    $path = SVG_DIR . $name . '.svg';
    if (file_exists($path)) return;

    // Build XML char references for all codepoints
    $xmlChars = '';
    $len      = mb_strlen($emoji, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($emoji, $i, 1, 'UTF-8');
        $cp   = mb_ord($char, 'UTF-8');
        if ($cp === false) continue;
        $xmlChars .= '&#x' . strtoupper(dechex($cp)) . ';';
    }

    // SVG z emoji jako tekst — fonts systemowe renderuja poprawnie
    // SVG with emoji as text — system fonts render correctly
    $svg = '<?xml version="1.0" encoding="UTF-8"?>'
         . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" role="img">'
         . '<text x="12" y="19" text-anchor="middle" font-size="16"'
         . ' font-family="\'Apple Color Emoji\',\'Segoe UI Emoji\',\'Noto Color Emoji\',sans-serif">'
         . $xmlChars
         . '</text>'
         . '</svg>';

    file_put_contents($path, $svg);
    echo "  [SVG+]   Auto-SVG: assets/img/icons/wg/{$name}.svg\n";
}


// 
// Podmiana emoji — tokenizer PHP
// 

/**
 * Replaces emoji in a PHP file using PHP's own tokenizer.
 * - T_INLINE_HTML  (HTML outside PHP tags) => <?= wgIco('name') ?>
 * - T_CONSTANT_ENCAPSED_STRING (PHP string literal) => wgIco('name') . '...'
 */
function replaceEmojiInFile(string $content, array $emojiMap): string
{
    $tokens  = @token_get_all($content);
    $rebuilt = '';

    foreach ($tokens as $token) {
        if (!is_array($token)) {
            $rebuilt .= $token;
            continue;
        }
        [$type, $text] = $token;

        switch ($type) {
            case T_INLINE_HTML:
                // HTML context: EMOJI => <?= wgIco('name') ?>
                foreach ($emojiMap as $emoji => $name) {
                    $text = str_replace($emoji, "<?= wgIco('{$name}') ?>", $text);
                }
                break;

            case T_CONSTANT_ENCAPSED_STRING:
                // PHP string literal: 'EMOJI text' => wgIco('name') . ' text'
                $text = replaceInPhpString($text, $emojiMap);
                break;

            default:
                // Other tokens (keywords, operators, etc.) — leave unchanged
                break;
        }