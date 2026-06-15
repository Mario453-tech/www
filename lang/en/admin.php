<?php
declare(strict_types=1);

/**
 * Admin translations loader.
 * English admin translations loader with Polish fallback.
 */

$lang = [];

foreach ([__DIR__ . '/admin', __DIR__ . '/../pl/admin'] as $dir) {
    $files = glob($dir . '/*.php') ?: [];
    sort($files, SORT_STRING);

    foreach ($files as $file) {
        $chunk = require $file;
        if (is_array($chunk)) {
            $lang += $chunk;
        }
    }
}

return $lang;
