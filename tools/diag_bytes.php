<?php
// Diagnostyk bajtow w plikach po naprawie encoding
$files = [
    'assets/css/well-grid.css',
    'assets/css/admin.css',
    'lang/pl.php',
];

foreach ($files as $f) {
    $c = file_get_contents(__DIR__ . '/' . $f);
    if ($c === false) { echo "[BRAK] $f\n"; continue; }

    $len = strlen($c);
    $bad = [];

    $i = 0;
    while ($i < $len) {
        $b = ord($c[$i]);
        if ($b < 0x80) { $i++; continue; }

        if (($b & 0xE0) === 0xC0) $seq = 2;
        elseif (($b & 0xF0) === 0xE0) $seq = 3;
        elseif (($b & 0xF8) === 0xF0) $seq = 4;
        else {
            $ctx = substr($c, max(0, $i - 20), 40);
            $bad[] = sprintf('pos %d: 0x%02X ctx: %s', $i, $b, addslashes($ctx));
            $i++;
            continue;
        }
        $ok = true;
        for ($j = 1; $j < $seq; $j++) {
            if ($i + $j >= $len || (ord($c[$i + $j]) & 0xC0) !== 0x80) {
                $ctx = substr($c, max(0, $i - 20), 40);
                $bad[] = sprintf('pos %d: brak kontynuacji, ctx: %s', $i, addslashes($ctx));
                $ok = false;
                break;
            }
        }
        $i += $ok ? $seq : 1;
    }

    echo "\n=== $f ===\n";
    echo "Rozmiar: $len B, Bledy UTF-8: " . count($bad) . "\n";
    if (!empty($bad)) {
        foreach (array_slice($bad, 0, 10) as $b) echo "  $b\n";
        if (count($bad) > 10) echo "  ... i " . (count($bad) - 10) . " wiecej.\n";
    } else {
        echo "  [OK] Poprawny UTF-8.\n";
    }
}
