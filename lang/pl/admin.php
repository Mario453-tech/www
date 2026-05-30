<?php
declare(strict_types=1);

/**
 * Admin translations loader.
 * Ladowacz tlumaczen admina.
 */

$lang = [];
$dir = __DIR__ . '/admin';
$files = glob($dir . '/*.php') ?: [];
sort($files, SORT_STRING);

foreach ($files as $file) {
    $chunk = require $file;
    if (is_array($chunk)) {
        $lang += $chunk;
    }
}

return $lang;
