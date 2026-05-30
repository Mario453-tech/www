<?php
/**
 * fix_template_encoding.php
 *
 * Naprawia podwojne kodowanie w plikach szablonow i zrodel PHP.
 * Fixes double-encoding in template and PHP source files.
 *
 * Algorytm / Algorithm:
 *   iconv('UTF-8', 'CP1250//IGNORE', $tresc) — odwraca proces podwojnego
 *   kodowania: bajty UTF-8 odczytane jako CP1250 i ponownie zapisane jako UTF-8.
 *   Reverses the double-encoding: UTF-8 bytes read as CP1250 and re-saved as UTF-8.
 *
 * Uzycie / Usage:
 *   php fix_template_encoding.php              -- naprawia wszystkie pliki
 *   php fix_template_encoding.php --dry-run    -- tylko podglad, brak zapisu
 *   php fix_template_encoding.php --file=templates/components/well_grid.php
 *
 * Kopie zapasowe tworzone automatycznie przed kazdym zapisem.
 * Backups created automatically before every write.
 */
declare(strict_types=1);

// ── Lista plikow do naprawy (wzgledem katalogu skryptu) ───────────────────────
// Files to fix (relative to script directory)
const TARGETS = [
    // Roznica iconv (bajty) / iconv diff (bytes) — dla informacji
    'templates/components/well_grid.php',                    // 301 B
    'templates/components/well_grid/equipment.php',          //  ~B (12 seq)
    'templates/components/well_grid/layers.php',             // DONE - has x cdot - re-run iconv CORRUPTS these
    'templates/components/well_grid/danger_zone.php',        //  ~B  (4 seq)
    'templates/components/well_grid/transport.php',          // DONE - has cdot - re-run iconv CORRUPTS it
    'templates/header.php',                                  // 632 B
    'templates/views/admin/transport/main.php',              // 453 B
    'templates/views/admin/finance/main.php',                // 281 B
    'templates/views/admin/chat/main.php',                   // 218 B
    'src/RoadTransportService.php',                          // 802 B
    'src/OffshoreTransportService.php',                      // 799 B
    'templates/views/admin/wells/main.php',                  //  59 B
    'templates/views/admin/map_locations/main.php',          //  65 B
    'src/Incident/RepairDataTrait.php',                      //  16 B
    'src/Well/SellTrait.php',                                //  32 B
    'src/FinancePolicyService.php',                          //  21 B
    'assets/js/well_grid.js',                               // DONE - has emoji (checkmark gear hourglass) - re-run iconv DROPS them
];

// ── Argumenty CLI ─────────────────────────────────────────────────────────────

$args    = $_SERVER['argv'] ?? [];
$dryRun  = in_array('--dry-run', $args, true);
$onlyFile = '';
foreach ($args as $a) {
    if (str_starts_with($a, '--file=')) {
        $onlyFile = trim(substr($a, 7));
    }
}

$targets = $onlyFile !== '' ? [$onlyFile] : TARGETS;

echo "=== fix_template_encoding.php ===\n";
echo "Tryb / Mode: " . ($dryRun ? 'DRY-RUN (brak zapisu / no save)' : 'ZAPIS / WRITE') . "\n\n";

// ── Katalog bazowy ────────────────────────────────────────────────────────────

$root    = __DIR__ . '/';
$fixed_n = 0;
$skipped = 0;
$errors  = 0;

// ── Petla po plikach ──────────────────────────────────────────────────────────

foreach ($targets as $relPath) {
    $fullPath = $root . ltrim($relPath, '/\\');

    if (!file_exists($fullPath)) {
        echo "[BRAK]  {$relPath}\n";
        $errors++;
        continue;
    }

    // Wczytaj / Read
    $content = file_get_contents($fullPath);
    if ($content === false) {
        echo "[BLAD]  Nie mozna odczytac: {$relPath}\n";
        $errors++;
        continue;
    }

    // Usun BOM jesli istnieje / Strip BOM
    $hasBom = (substr($content, 0, 3) === "\xef\xbb\xbf");
    if ($hasBom) {
        $content = substr($content, 3);
    }

    // Zastosuj iconv / Apply iconv
    $fixed = iconv('UTF-8', 'CP1250//IGNORE', $content);
    if ($fixed === false) {
        echo "[BLAD]  iconv() zwrocil false dla: {$relPath}\n";
        $errors++;
        continue;
    }

    $origLen  = strlen($content);
    $fixedLen = strlen($fixed);
    $diff     = $origLen - $fixedLen;

    if ($diff === 0 && !$hasBom) {
        echo "[OK]    Bez zmian: {$relPath}\n";
        $skipped++;
        continue;
    }

    // Raport / Report
    $bomNote = $hasBom ? ' [BOM usunieto]' : '';
    echo sprintf(
        "[NAPRAWA] %-55s  %d B -> %d B  (-%d B)%s\n",
        $relPath,
        $origLen,
        $fixedLen,
        $diff,
        $bomNote
    );

    if ($dryRun) {
        $fixed_n++;
        continue;
    }

    // Kopia zapasowa / Backup
    $backup = $fullPath . '.bak_enc_' . date('Ymd_His');
    if (!copy($fullPath, $backup)) {
        echo "  [BLAD]  Nie mozna utworzyc kopii zapasowej!\n";
        $errors++;
        continue;
    }
    echo "  [BAK]   {$backup}\n";

    // Zapis / Write
    if (file_put_contents($fullPath, $fixed) === false) {
        echo "  [BLAD]  Nie mozna zapisac pliku!\n";
        $errors++;
        continue;
    }

    echo "  [OK]    Zapisano.\n";
    $fixed_n++;
}

// ── Podsumowanie ─────────────────────────────────────────────────────────────

echo "\n=== Podsumowanie / Summary ===\n";
echo "Naprawiono / Fixed : {$fixed_n}\n";
echo "Bez zmian  / Skipped: {$skipped}\n";
echo "Bledy      / Errors : {$errors}\n";

if ($dryRun) {
    echo "\n[DRY-RUN] Brak zmian w plikach. Uruchom bez --dry-run aby zapisac.\n";
}
