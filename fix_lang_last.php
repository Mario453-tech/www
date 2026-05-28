<?php
// Ostatni pass poprawek krzakow w lang/pl.php
$file = __DIR__ . '/lang/pl.php';
$content = file_get_contents($file);

$backupDir = __DIR__ . '/backup';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
$bak = $backupDir . '/pl.php.bak_last_' . date('Ymd_His');
copy($file, $bak);

$fixes = [
    // hr_hiring
    ["'Bd zatrudniania inyniera.'",             "'Błąd zatrudniania inżyniera.'"],
    // hr_events
    ["{first} {last} zosta zwolniony.",          "{first} {last} został zwolniony."],
    ["'Koczcy si kontrakt: {first} {last}'",    "'Kończący się kontrakt: {first} {last}'"],
    ["'Panie Dyrektorze, za {days} dni koczy si kontrakt pracownika {first} {last} ({role}). Prosz o decyzj w sprawie przeduenia.'",
     "'Panie Dyrektorze, za {days} dni kończy się kontrakt pracownika {first} {last} ({role}). Proszę o decyzję w sprawie przedłużenia.'"],
    // hr_headhunter
    ["=> 'Bd: {error}'",                        "=> 'Błąd: {error}'"],
    // geology
    ["=> 'Pytka'",                               "=> 'Płytka'"],
    // logistics pipeline - pominiete w poprzednim passie
    ["'Ostatni przegld'",                        "'Ostatni przegląd'"],
    ["'Brak inyniera'",                          "'Brak inżyniera'"],
    // admin.logistics.cfg_acquisition
    ["'Mnonik kosztu wejcia'",                   "'Mnożnik kosztu wejścia'"],
    // Naprawa kluczy ktore byly wewnatrz duplikatu a nie wurden naprawione w pierwszym bloku
    // (Zosta/Zostaly pattern - upewnij sie ze wszystkie formy sa naprawione)
    [" zosta ",                                  " został "],
];

$changed = 0;
foreach ($fixes as [$old, $new]) {
    $count = substr_count($content, $old);
    if ($count > 0) {
        $content = str_replace($old, $new, $content);
        $changed += $count;
        echo "  [FIX] x$count  " . mb_substr($old, 0, 70) . "\n";
    }
}

echo "Zmienionych: $changed\n";
file_put_contents($file, $content);

$out = []; $ret = 0;
exec('"C:\\xampp1\\bin\\php\\php8.2.29\\php.exe" -l ' . escapeshellarg($file) . ' 2>&1', $out, $ret);
echo "Lint: " . ($ret === 0 ? 'OK' : 'BLAD') . "\n";
foreach ($out as $l) echo "  $l\n";
