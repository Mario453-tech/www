<?php
/**
 * fix_lang_dupes.php
 * - Usuwa zduplikowany blok bank/black_market/auth/news (drugi blok)
 * - Naprawia ostatnie krzaki w pierwszym bloku (auth.*, black_market.player_error)
 */
declare(strict_types=1);

$file = __DIR__ . '/lang/pl.php';
$content = file_get_contents($file);

$backupDir = __DIR__ . '/backup';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
$bak = $backupDir . '/pl.php.bak_dupes_' . date('Ymd_His');
copy($file, $bak);
echo "BAK: $bak\n";

// ── 1. Usun drugi blok (duplikat) ─────────────────────────────────────────────
// Drugi blok zaczyna sie od "\n// Bank action handler" (druga w pliku)
// i konczy na "'news.time_days_ago' => ':count dni temu',"
// Po nim jest "\n\t\n    // Director notifications"

$marker   = "// Bank action handler";
$endKey   = "'news.time_days_ago'";
$afterEnd = "// Director notifications";

$first  = strpos($content, $marker);
$second = strpos($content, $marker, $first + strlen($marker));

if ($second === false) {
    echo "[INFO] Drugi blok nie znaleziony.\n";
} else {
    // Znajdz koniec bloku: ostatnia linia zawierajaca news.time_days_ago
    $endKeyPos = strpos($content, $endKey, $second);
    if ($endKeyPos !== false) {
        // Koniec tej linii
        $lineEnd = strpos($content, "\n", $endKeyPos);
        if ($lineEnd === false) $lineEnd = strlen($content) - 1;

        // Usun od poczatku drugiego markera do konca linii news.time_days_ago
        // Ale zachowaj newline przed // Director notifications
        // Wylicz od czego zaczyna sie drugi blok: szukaj newline przed nim
        $blockStart = strrpos($content, "\n", $second - strlen($content) - 1);
        if ($blockStart === false) $blockStart = $second;

        $removed = substr($content, $blockStart, $lineEnd - $blockStart);
        $content = substr($content, 0, $blockStart) . substr($content, $lineEnd);
        echo "[OK] Usunieto duplikat (" . strlen($removed) . " B).\n";
    }
}

// ── 2. Napraw pozostale krzaki w pierwszym bloku auth.* itp. ─────────────────
$fixes = [
    // auth.email_verify
    ["'Potwierd adres e-mail'",                    "'Potwierdź adres e-mail'"],
    ["'Cze <strong style=\\'color:#c8a84b\\'>:name</strong>,'",
     "'Cześć <strong style=\\'color:#c8a84b\\'>:name</strong>,'"],
    ["'POTWIERD E-MAIL '",                          "'POTWIERDŹ E-MAIL »'"],
    ["'[OilCorp] Potwierd swj adres e-mail'",      "'[OilCorp] Potwierdź swój adres e-mail'"],
    // auth.reset_email_greeting
    ["'Cze <strong>:name</strong>,'",              "'Cześć <strong>:name</strong>,'"],
    // black_market.player_error
    ["=> 'Bd gracza'",                             "=> 'Błąd gracza'"],
];

$changed = 0;
foreach ($fixes as [$old, $new]) {
    $count = substr_count($content, $old);
    if ($count > 0) {
        $content = str_replace($old, $new, $content);
        $changed += $count;
        echo "  [FIX] x$count  " . mb_substr($old, 0, 60) . "\n";
    }
}

echo "Poprawiono: $changed wystapien.\n";

// ── Zapisz ───────────────────────────────────────────────────────────────────
file_put_contents($file, $content);
echo "[OK] Zapisano.\n";

// ── Lint ─────────────────────────────────────────────────────────────────────
$out = [];
$ret = 0;
exec('"C:\\xampp1\\bin\\php\\php8.2.29\\php.exe" -l ' . escapeshellarg($file) . ' 2>&1', $out, $ret);
echo "Lint: " . ($ret === 0 ? 'OK' : 'BLAD') . "\n";
foreach ($out as $l) echo "  $l\n";

// ── Weryfikacja duplikatow ────────────────────────────────────────────────────
echo "\nWeryfikacja duplikatow:\n";
$keys = ['bank.action_err_csrf', 'news.time_days_ago', 'auth.email_verify_title', 'board_access.denied'];
$content = file_get_contents($file);
foreach ($keys as $k) {
    $cnt = substr_count($content, "'$k'");
    echo "  $k: $cnt" . ($cnt > 1 ? ' <-- DUPLIKAT!' : ' OK') . "\n";
}
