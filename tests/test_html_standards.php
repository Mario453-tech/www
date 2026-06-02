<?php
declare(strict_types=1);

$dirs = [
    __DIR__ . '/../public/',
    __DIR__ . '/../admin/',
    __DIR__ . '/../templates/',
];

$errors = [];

foreach ($dirs as $dir) {
    foreach (glob($dir . '*.php') as $file) {
        $content = file_get_contents($file);
        $rel     = str_replace(__DIR__ . '/../', '', $file);

        $tables = preg_match_all('/<(table|tr|td|th|thead|tbody)\b/i', $content);
        $inline = preg_match_all('/style="[^"]*"/', $content);
        $styleB = preg_match_all('/<style[\s>]/i', $content);

        if ($tables > 0) $errors[] = " TABLE TAG   | $tables szt. | $rel";
        if ($inline > 0) $errors[] = " INLINE CSS  | $inline szt. | $rel";
        if ($styleB > 0) $errors[] = " STYLE BLOCK | $styleB szt. | $rel";
    }
}

if (empty($errors)) {
    echo " Wszystkie pliki zgodne ze standardem HTML5\n";
} else {
    echo implode("\n", $errors) . "\n";
    echo "\nBłędów: " . count($errors) . "\n";
}
