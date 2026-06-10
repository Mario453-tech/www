<?php
declare(strict_types=1);

/**
 * Naprawa double-encoded UTF-8 w lang/pl.php.
 * Fix double-encoded UTF-8 in lang/pl.php.
 *
 * Problem: bajty UTF-8 polskich znakow zostaly zinterpretowane jako CP1250,
 * a potem ponownie zakodowane jako UTF-8 (np. "uzywany" -> "uĽywany").
 * Problem: UTF-8 bytes of Polish chars were interpreted as CP1250,
 * then re-encoded as UTF-8 (e.g. "uzywany" -> garbled).
 *
 * Algorytm: iconv('UTF-8','CP1250//IGNORE', broken) -> oryginalne bajty UTF-8
 * Algorithm: iconv('UTF-8','CP1250//IGNORE', broken) -> original UTF-8 bytes
 *
 * Ochrona popranych stringow: jesli wynik iconv nie jest valide UTF-8, string pozostaje.
 * Correctly encoded strings: if iconv result is not valid UTF-8, string is kept as-is.
 */

$srcFile = __DIR__ . '/lang/pl.php';
$bakFile = __DIR__ . '/lang/pl.php.bak_encoding_' . date('Ymd_His');

// Kopia zapasowa / Backup
copy($srcFile, $bakFile);
echo "Backup: $bakFile\n";

$lang = require $srcFile;

// --- Naprawa wartosci / Fix values ---
$fixed = [];
$fixedCount = 0;
$keptCount  = 0;

foreach ($lang as $key => $value) {
    $conv = @iconv('UTF-8', 'CP1250//IGNORE', $value);
    if ($conv !== false && $conv !== '' && mb_check_encoding($conv, 'UTF-8') && $conv !== $value) {
        $fixed[$key] = $conv;
        $fixedCount++;
    } else {
        $fixed[$key] = $value;
        $keptCount++;
    }
}

echo "Naprawiono: $fixedCount kluczy\n";
echo "Bez zmian:  $keptCount kluczy\n";

// --- Zapis / Write ---
// Czytamy oryginalne linie i zamieniamy wartosci linia po linii.
// We read original lines and replace values line by line.
$lines = file($srcFile, FILE_IGNORE_NEW_LINES);
$out   = [];

foreach ($lines as $line) {
 // Dopasuj linie z kluczem i wartoscia: '...key...' => '...value...',
 // Match lines with key + value: '...key...' => '...value...',
    if (preg_match("/^(\s*'([^']+)'\s*=>\s*')(.*)(',\s*)$/", $line, $m)) {
        $key       = $m[2];
        $oldVal    = $m[3];
        $newVal    = $fixed[$key] ?? $oldVal;
 // Zabezpiecz apostrofy w wartosci / Escape apostrophes in value
        $newVal    = str_replace("'", "\\'", $newVal);
        $out[]     = $m[1] . $newVal . $m[4];
    } else {
        $out[] = $line;
    }
}

$content = implode("\n", $out);
// Upewnij sie brak BOM / Ensure no BOM
$content = ltrim($content, "\xEF\xBB\xBF");

file_put_contents($srcFile, $content);
echo "Zapisano: $srcFile\n";

// --- Weryfikacja / Verify ---
$reloaded = require $srcFile;
$stillBroken = 0;
foreach ($reloaded as $k => $v) {
    $conv2 = @iconv('UTF-8', 'CP1250//IGNORE', $v);
    if ($conv2 !== false && mb_check_encoding($conv2, 'UTF-8') && $conv2 !== $v) {
        $stillBroken++;
    }
}
echo "Pozostalo zepsutych po naprawie: $stillBroken\n";
echo $stillBroken === 0 ? "OK - wszystko poprawione.\n" : "UWAGA: sa jeszcze zepsute klucze!\n";
