<?php
// Skan lang/pl.php pod katem typowych wzorcow mojibake (po linii 3855)
$file  = __DIR__ . '/lang/pl.php';
$lines = file($file);
$bad   = [];

// Wzorce typowych polskich slow z brakujacymi diakrytykami (mojibake)
// Zidentyfikuj linie gdzie wartosc klucza zawiera takie fragmenty
$patterns = [
    'Bd '      => 'Blad',
    "'Bd'"     => 'Blad',
    'Uy'       => 'Uzyj',
    'Zuy'      => 'Zuzycie',
    'Rod'      => 'Srodki/Srodowisko',
    'Ilo'      => 'Ilosc',
    'Prz'      => 'Przejsc/Przenie',
    'Moa'      => 'Mozna',
    'Utr'      => 'Utracic',
    'Ruo'      => 'Rurocig',
    'Obn'      => 'Obnizony',
    'Moe'      => 'Moze',
    'Jel'      => 'Jesli',
    'Wal'      => 'Waluta?',
    'Poc'      => 'Poczatek',
    'Pot'      => 'Potwierdz',
    'Prosz'    => 'Prosze (skrocone?)',
    'Cze '     => 'Czesc (skrot)',
    'inyniera' => 'inzyniera',
    'inynierw' => 'inzynierow',
    'hubw'     => 'hubow',
    'odwiertw' => 'odwiertow',
    'wygasa'   => 'wygasla (czas przesz.)',
    'koczy'    => 'konczy',
    'Prosz '   => 'Prosze',
    'decyzj'   => 'decyzje',
    'przedue'  => 'przedluz',
    ' zosta '  => 'zostal',
    'Mnonik'   => 'Mnoznik',
    'Nastpna'  => 'Nastepna',
    'Szczeg'   => 'Szczegoly (skr)',
    'adow'     => 'ladow',
    'przegld'  => 'przeglad',
    'Cik'      => 'Ciezki',
    'Nadzr'    => 'Nadzor',
    'Przegld'  => 'Przeglad',
    'Przepyw'  => 'Przeplyw',
    'cznie'    => 'lacznie',
    'Czciowy'  => 'Czesciowy',
    'Peny'     => 'Pelny',
    'Wyczony'  => 'Wylaczony',
    'Przeciony'=> 'Przeciazony',
];

foreach ($lines as $i => $line) {
    $lineNum = $i + 1;
    if ($lineNum < 3856) continue; // Tylko od linii 3856

    // Sprawdz czy to linia z wartoscia klucza
    if (!str_contains($line, "=>")) continue;
    if (!preg_match('/\'[^\']+\'\s*=>/', $line)) continue;

    foreach ($patterns as $pattern => $hint) {
        if (str_contains($line, $pattern)) {
            $bad[] = "$lineNum [$hint]: " . trim($line);
            break; // Jedno trafienie per linia wystarczy
        }
    }
}

echo count($bad) . " linii z potencjalnymi krzakami od linii 3856:\n";
echo str_repeat('-', 80) . "\n";
foreach ($bad as $b) {
    echo $b . "\n";
}
