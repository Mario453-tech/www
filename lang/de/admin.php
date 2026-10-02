<?php
declare(strict_types=1);

/**
 * German admin translations loader.
 * Niemiecki loader translacji admina z fallbackiem.
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
