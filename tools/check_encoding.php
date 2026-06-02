<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$fixBom = in_array('--fix-bom', $args, true);
$staged = in_array('--staged', $args, true);
$pathArgs = [];

foreach ($args as $arg) {
    if (str_starts_with($arg, '--path=')) {
        $pathArgs[] = substr($arg, 7);
    }
}

$allowedExtensions = [
    'css' => true,
    'html' => true,
    'htm' => true,
    'inc' => true,
    'js' => true,
    'json' => true,
    'md' => true,
    'php' => true,
    'phtml' => true,
    'sql' => true,
];

$ignoredDirs = [
    '.git' => true,
    'backup' => true,
    'cache' => true,
    'node_modules' => true,
    'vendor' => true,
];

$suspicious = [
    hex2bin('efbfbd') => 'replacement-character',
    hex2bin('c4b9') => 'mojibake-L',
    hex2bin('c384') => 'mojibake-A',
    hex2bin('c382') => 'mojibake-B',
    hex2bin('c482') => 'mojibake-C',
    hex2bin('c3a2e282ac') => 'mojibake-quote',
    hex2bin('c3a2e280a0') => 'mojibake-arrow',
];

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function isIgnored(string $path, array $ignoredDirs): bool
{
    $parts = explode('/', normalizePath($path));
    foreach ($parts as $part) {
        if (isset($ignoredDirs[$part])) {
            return true;
        }
    }
    return false;
}

function shouldCheckFile(string $path, array $allowedExtensions, array $ignoredDirs): bool
{
    if (isIgnored($path, $ignoredDirs)) {
        return false;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return isset($allowedExtensions[$ext]);
}

function collectFilesFromPath(string $path, array $allowedExtensions, array $ignoredDirs): array
{
    if (is_file($path)) {
        return shouldCheckFile($path, $allowedExtensions, $ignoredDirs) ? [$path] : [];
    }

    if (!is_dir($path)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $current) use ($ignoredDirs): bool {
                if ($current->isDir()) {
                    return !isset($ignoredDirs[$current->getFilename()]);
                }
                return true;
            }
        )
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $filePath = $file->getPathname();
        if (shouldCheckFile($filePath, $allowedExtensions, $ignoredDirs)) {
            $files[] = $filePath;
        }
    }

    return $files;
}

function stagedFiles(string $root, array $allowedExtensions, array $ignoredDirs): array
{
    $cmd = 'git -C ' . escapeshellarg($root) . ' diff --cached --name-only --diff-filter=ACMR';
    $output = shell_exec($cmd);
    if (!is_string($output) || trim($output) === '') {
        return [];
    }

    $files = [];
    foreach (preg_split('/\R/', trim($output)) ?: [] as $relativePath) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($path) && shouldCheckFile($path, $allowedExtensions, $ignoredDirs)) {
            $files[] = $path;
        }
    }

    return $files;
}

function lineOfNeedle(string $contents, string $needle): int
{
    $pos = strpos($contents, $needle);
    if ($pos === false) {
        return 0;
    }

    return substr_count(substr($contents, 0, $pos), "\n") + 1;
}

$files = [];
if ($staged) {
    $files = stagedFiles($root, $allowedExtensions, $ignoredDirs);
} else {
    $targets = $pathArgs ?: [$root];
    foreach ($targets as $target) {
        $path = $target;
        if (!preg_match('/^[A-Za-z]:[\/\\\\]/', $path) && !str_starts_with($path, '/')) {
            $path = $root . DIRECTORY_SEPARATOR . $target;
        }
        $files = array_merge($files, collectFilesFromPath($path, $allowedExtensions, $ignoredDirs));
    }
}

$files = array_values(array_unique($files));
sort($files, SORT_STRING);

$errors = [];
$fixed = [];

foreach ($files as $file) {
    $contents = file_get_contents($file);
    if (!is_string($contents)) {
        $errors[] = [$file, 'read-failed', 0];
        continue;
    }

    if (str_starts_with($contents, "\xEF\xBB\xBF")) {
        if ($fixBom) {
            file_put_contents($file, substr($contents, 3));
            $contents = substr($contents, 3);
            $fixed[] = $file;
        } else {
            $errors[] = [$file, 'bom-at-start', 1];
        }
    }

    if (preg_match('//u', $contents) !== 1) {
        $errors[] = [$file, 'invalid-utf8', 0];
        continue;
    }

    foreach ($suspicious as $needle => $label) {
        if (str_contains($contents, $needle)) {
            $errors[] = [$file, $label, lineOfNeedle($contents, $needle)];
        }
    }
}

foreach ($fixed as $file) {
    echo 'Fixed BOM: ' . normalizePath($file) . PHP_EOL;
}

if ($errors) {
    fwrite(STDERR, 'Encoding check failed:' . PHP_EOL);
    foreach ($errors as [$file, $label, $line]) {
        $suffix = $line > 0 ? ':' . $line : '';
        fwrite(STDERR, '- ' . normalizePath($file) . $suffix . ' [' . $label . ']' . PHP_EOL);
    }
    exit(1);
}

echo 'Encoding check passed (' . count($files) . ' files).' . PHP_EOL;
