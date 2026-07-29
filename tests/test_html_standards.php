<?php
declare(strict_types=1);

$targets = [
    __DIR__ . '/../public/hr.php',
    __DIR__ . '/../admin/hr.php',
    __DIR__ . '/../templates/views/hr',
    __DIR__ . '/../templates/views/admin/hr',
];

$errors = [];
$files = [];

foreach ($targets as $target) {
    if (is_file($target)) {
        $files[] = $target;
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php') {
            $files[] = $fileInfo->getPathname();
        }
    }
}

foreach (array_unique($files) as $file) {
    $content = (string)file_get_contents($file);
    $relativePath = str_replace('\\', '/', str_replace(__DIR__ . '/../', '', $file));
    $styleBlocks = preg_match_all('/<style[\s>]/i', $content);
    preg_match_all('/style="([^"]*)"/i', $content, $matches);
    $invalidInline = 0;

    foreach ($matches[1] ?? [] as $styleValue) {
        if (preg_match('/^(?:\s*--[a-z0-9-]+\s*:\s*[^;]+;?\s*)+$/i', trim($styleValue)) !== 1) {
            $invalidInline++;
        }
    }

    if ($invalidInline > 0) {
        $errors[] = "INLINE CSS | {$invalidInline} | {$relativePath}";
    }
    if ($styleBlocks > 0) {
        $errors[] = "STYLE BLOCK | {$styleBlocks} | {$relativePath}";
    }
}

if ($errors === []) {
    echo "HR HTML standards: OK\n";
    exit(0);
}

echo implode("\n", $errors) . "\n";
echo "\nErrors: " . count($errors) . "\n";
exit(1);
