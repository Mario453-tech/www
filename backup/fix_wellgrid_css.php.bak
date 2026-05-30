<?php
// Naprawa broken UTF-8 bytes w well-grid.css
// Usuwa nieważne sekwencje UTF-8 (orphaned CP1250 bytes z emoji)
$f    = __DIR__ . '/assets/css/well-grid.css';
$c    = file_get_contents($f);
$before = strlen($c);

// Usuwa bajty nieprawidlowe w UTF-8 (//IGNORE dropuje invalid sequences)
$fixed = iconv('UTF-8', 'UTF-8//IGNORE', $c);

$after = strlen($fixed);
echo "Przed : $before B\n";
echo "Po    : $after B (usunieto " . ($before - $after) . " B)\n";

if ($before === $after) {
    echo "[OK] Plik byl juz poprawnym UTF-8 — brak zmian.\n";
    exit;
}

// Backup
$bak = $f . '.bak_wg_' . date('Ymd_His');
copy($f, $bak);
echo "BAK   : $bak\n";

file_put_contents($f, $fixed);
echo "[OK] Zapisano.\n";

// Weryfikacja
$check = iconv('UTF-8', 'UTF-8//IGNORE', $fixed);
echo "Weryfikacja: " . (strlen($check) === strlen($fixed) ? 'OK - poprawny UTF-8' : 'BLAD') . "\n";
