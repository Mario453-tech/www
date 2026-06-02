<?php
declare(strict_types=1);

function checkViewFile(string $path): array
{
    $content = file_get_contents($path);
    $errors  = [];

    if (preg_match('/\$db->|Database::getInstance|PDO::/i', $content)) {
        $errors[] = " Zapytanie DB w widoku: $path";
    }
    if (preg_match('/style="[^>]*"/', $content)) {
        $errors[] = " Inline style w widoku: $path";
    }
    if (preg_match('/<(table|tr|td|th|thead|tbody)\b/i', $content)) {
        $errors[] = " Tag HTML table w widoku: $path";
    }
    if (!preg_match('/extract\(\$viewData|extract\(\$data/', $content)) {
        $errors[] = "  Brak extract(\$viewData) w widoku: $path";
    }

    return $errors;
}

function checkControllerFile(string $path): array
{
    $content    = file_get_contents($path);
    $errors     = [];
    $htmlBlocks = preg_match_all('/<(div|section|article|main|nav|aside|h[1-6]|p|ul|ol)\b/i', $content);

    if ($htmlBlocks > 5) {
        $errors[] = " Zbyt dużo HTML w kontrolerze ($htmlBlocks tagów): $path";
    }
    if (!preg_match('/require.*templates\/views\//i', $content)) {
        $errors[] = "  Brak require templates/views/ w kontrolerze: $path";
    }

    return $errors;
}

$viewDir = __DIR__ . '/../templates/views/';
$errors  = [];

foreach (glob($viewDir . '**/*.php', GLOB_BRACE) as $file) {
    $errors = array_merge($errors, checkViewFile($file));
}

echo empty($errors)
    ? " Wszystkie testy separacji przeszły\n"
    : implode("\n", $errors) . "\n";
