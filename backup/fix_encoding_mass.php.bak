<?php
/**
 * fix_encoding_mass.php
 *
 * Masowa naprawa double-encoding UTF-8/CP1250 w calym projekcie.
 * Skanuje .php, .js, .css, .html rekurencyjnie od katalogu skryptu.
 *
 * Algorytm: iconv('UTF-8','CP1250//IGNORE') odwraca double-encoding.
 * NIE jest idempotentny — pliki juz naprawione sa wykluczone przez SKIP_FILES.
 *
 * Uzycie:
 *   php fix_encoding_mass.php              -- skanuj i napraw
 *   php fix_encoding_mass.php --dry-run    -- tylko podglad, brak zapisu
 *   php fix_encoding_mass.php --scan-only  -- pokaz pliki wymagajace naprawy
 *   php fix_encoding_mass.php --min-diff=N -- minimalny diff w bajtach (domyslnie 4)
 */
declare(strict_types=1);

// ── Konfiguracja ──────────────────────────────────────────────────────────────

// Rozszerzenia do sprawdzenia
const EXTENSIONS = ['php', 'js', 'css', 'html'];

// Minimalny diff bajtow zeby uznac plik za double-encoded (filtr szumu)
const DEFAULT_MIN_DIFF = 4;

// Katalogi do pominiecia (wzgledne od root)
const SKIP_DIRS = [
    '.claude',
    'backup',
    'serwer',
    '_backup',       // backup snapshots
    'node_modules',
    'vendor',
    '.git',
];

// Pliki juz naprawione recznie lub z Unicode > CP1250 (emoji, znaki specjalne).
// NIE uruchamiac na nich iconv ponownie — zniszczy poprawne znaki.
const SKIP_FILES = [
    // well_grid — naprawione recznie (maja x U+00D7, middot U+00B7, emoji SVG)
    'templates/components/well_grid/layers.php',
    'templates/components/well_grid/transport.php',
    'assets/js/well_grid.js',
    // emoji.js ma realne emoji 4-bajtowe UTF-8 — iconv by je zniszczyl
    'assets/js/emoji.js',
    // skrypty naprawcze — ten skrypt sam w sobie
    'fix_encoding_mass.php',
    'fix_template_encoding.php',
    'fix_emoji_to_svg.php',
];

// ── Parsowanie argumentow ─────────────────────────────────────────────────────

$args     = array_slice($_SERVER['argv'] ?? [], 1);
$dryRun   = in_array('--dry-run', $args, true);
$scanOnly = in_array('--scan-only', $args, true);
$minDiff  = DEFAULT_MIN_DIFF;
foreach ($args as $a) {
    if (str_starts_with($a, '--min-diff=')) {
        $minDiff = max(1, (int) substr($a, 11));
    }
}

if ($scanOnly) {
    $dryRun = true;
}

$root = rtrim(__DIR__, '/\\') . DIRECTORY_SEPARATOR;

echo "=== fix_encoding_mass.php ===\n";
echo "Root   : {$root}\n";
echo "Tryb   : " . ($scanOnly ? 'SCAN-ONLY' : ($dryRun ? 'DRY-RUN' : 'ZAPIS')) . "\n";
echo "Min diff: {$minDiff} B\n\n";

// ── Normalizacja skip-files do kluczy ─────────────────────────────────────────

$skipFilesNorm = [];
foreach (SKIP_FILES as $sf) {
    $skipFilesNorm[strtolower(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sf))] = true;
}

// ── Iterator plikow ───────────────────────────────────────────────────────────

/**
 * Sprawdza czy sciezka zawiera jeden z wykluczonych katalogow.
 * Obsluguje rowniez wzorce z wildcardami (np. '_backup' matches '_backup_*').
 */
function inSkipDir(string $path, string $root): bool
{
    $rel   = strtolower(substr($path, strlen($root)));
    $parts = explode(DIRECTORY_SEPARATOR, $rel);
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        foreach (SKIP_DIRS as $sd) {
            $sd = strtolower(rtrim($sd, '/\\'));
            // Dokladne dopasowanie lub prefix (dla _backup_20260503 itp.)
            if ($part === $sd || str_starts_with($part, $sd)) {
                return true;
            }
        }
    }
    return false;
}

function isSkipFile(string $fullPath, string $root, array $skipFilesNorm): bool
{
    $rel = strtolower(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, substr($fullPath, strlen($root))));
    return isset($skipFilesNorm[$rel]);
}

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

// ── Glowna petla ──────────────────────────────────────────────────────────────

$stats = ['fixed' => 0, 'skipped' => 0, 'ok' => 0, 'errors' => 0];
$needFix = [];

foreach ($iter as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $fullPath = $fileInfo->getPathname();
    $ext      = strtolower($fileInfo->getExtension());

    if (!in_array($ext, EXTENSIONS, true)) {
        continue;
    }

    if (inSkipDir($fullPath, $root)) {
        continue;
    }

    if (isSkipFile($fullPath, $root, $skipFilesNorm)) {
        $stats['skipped']++;
        continue;
    }

    // Wczytaj plik
    $content = file_get_contents($fullPath);
    if ($content === false) {
        echo "[BLAD]  Nie mozna odczytac: {$fullPath}\n";
        $stats['errors']++;
        continue;
    }

    // Usun BOM
    $hasBom = str_starts_with($content, "\xef\xbb\xbf");
    if ($hasBom) {
        $content = substr($content, 3);
    }

    // Sprawdz iconv diff
    $fixed = iconv('UTF-8', 'CP1250//IGNORE', $content);
    if ($fixed === false) {
        echo "[BLAD]  iconv() fail: {$fullPath}\n";
        $stats['errors']++;
        continue;
    }

    $diff = strlen($content) - strlen($fixed);

    if ($diff < $minDiff && !$hasBom) {
        $stats['ok']++;
        continue;
    }

    $relPath = substr($fullPath, strlen($root));
    $needFix[] = ['rel' => $relPath, 'full' => $fullPath, 'diff' => $diff, 'bom' => $hasBom, 'fixed' => $fixed];
}

// ── Sortuj po diff malejaco ───────────────────────────────────────────────────

usort($needFix, fn($a, $b) => $b['diff'] - $a['diff']);

// ── Raport / Zapis ────────────────────────────────────────────────────────────

if (empty($needFix)) {
    echo "Brak plikow wymagajacych naprawy.\n";
} else {
    echo "Pliki do naprawy (" . count($needFix) . "):\n";
    echo str_repeat('-', 80) . "\n";

    foreach ($needFix as $item) {
        $bomNote = $item['bom'] ? ' [BOM]' : '';
        printf("  %-65s  -%d B%s\n", $item['rel'], $item['diff'], $bomNote);

        if ($scanOnly || $dryRun) {
            $stats['fixed']++;
            continue;
        }

        // Kopia zapasowa
        $backup = $item['full'] . '.bak_mass_' . date('Ymd_His');
        if (!copy($item['full'], $backup)) {
            echo "    [BLAD] Nie mozna utworzyc kopii zapasowej!\n";
            $stats['errors']++;
            continue;
        }

        // Zapis
        if (file_put_contents($item['full'], $item['fixed']) === false) {
            echo "    [BLAD] Nie mozna zapisac!\n";
            $stats['errors']++;
            continue;
        }

        echo "    [OK]  Zapisano. BAK: " . basename($backup) . "\n";
        $stats['fixed']++;
    }
}

// ── Podsumowanie ──────────────────────────────────────────────────────────────

echo "\n" . str_repeat('=', 40) . "\n";
echo "Naprawiono / Fixed  : {$stats['fixed']}\n";
echo "OK juz / Already OK : {$stats['ok']}\n";
echo "Pominieto / Skipped : {$stats['skipped']}\n";
echo "Bledy / Errors      : {$stats['errors']}\n";

if ($dryRun && !$scanOnly) {
    echo "\n[DRY-RUN] Brak zmian. Uruchom bez --dry-run aby zapisac.\n";
}
if ($scanOnly) {
    echo "\n[SCAN] Uruchom bez --scan-only aby zapisac zmiany.\n";
}
