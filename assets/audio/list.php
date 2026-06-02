<?php
/**
 * /assets/audio/list.php
 * Zwraca JSON z list plikw MP3/OGG/WAV w tym samym katalogu.
 * Nie wymaga autoryzacji pliki i tak s publiczne.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // 5 min cache

$dir   = __DIR__;
$exts  = ['mp3', 'ogg', 'wav', 'm4a'];
$files = [];

foreach (new DirectoryIterator($dir) as $f) {
    if ($f->isDot() || !$f->isFile()) continue;
    if (!in_array(strtolower($f->getExtension()), $exts, true)) continue;
    $files[] = '/assets/audio/' . $f->getFilename();
}

sort($files); // Stable order; JS handles random playback.

echo json_encode($files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
