<?php
declare(strict_types=1);

/**
 * Audits and repairs common UTF-8/BOM/mojibake issues in project text files.
 * Audytuje i naprawia typowe problemy UTF-8/BOM/mojibake w plikach tekstowych projektu.
 *
 * Usage:
 *   php tools/repair_encoding.php
 *   php tools/repair_encoding.php --path=lang/pl/market.php
 *   php tools/repair_encoding.php --staged
 *   php tools/repair_encoding.php --fix --path=public
 *   php tools/repair_encoding.php --fix --no-backup --path=tests/tmp
 */

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$fix = in_array('--fix', $args, true);
$staged = in_array('--staged', $args, true);
$noBackup = in_array('--no-backup', $args, true);
$json = in_array('--json', $args, true);
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
    'txt' => true,
    'xml' => true,
    'yml' => true,
    'yaml' => true,
];

$ignoredDirs = [
    '.git' => true,
    '.idea' => true,
    '.vscode' => true,
    'backup' => true,
    'backups' => true,
    'cache' => true,
    'node_modules' => true,
    'vendor' => true,
];

$ignoredFiles = [
    '01240275_oil.sql' => true,
];

/**
 * These replacements are intentionally literal and narrow.
 * Te podmiany sa celowo doslowne i waskie.
 *
 * @return array<string, string>
 */
function encodingRepairMap(): array
{
    $h = static fn(string $hex): string => (string)hex2bin($hex);

    return [
        $h('c384e280a6') => 'ą',
        $h('c384e280a1') => 'ć',
        $h('c384e284a2') => 'ę',
        $h('c4b9e2809a') => 'ł',
        $h('c4b9e2809e') => 'ń',
        $h('c482c582') => 'ó',
        $h('c4b9e280ba') => 'ś',
        $h('c4b9c59f') => 'ź',
        $h('c4b9c4bd') => 'ż',
        $h('c384e2809e') => 'Ą',
        $h('c384e280a0') => 'Ć',
        $h('c384c298') => 'Ę',
        $h('c4b9c281') => 'Ł',
        $h('c4b9efbfbd') => 'Ń',
        $h('c482e2809c') => 'Ó',
        $h('c4b9c5a1') => 'Ś',
        $h('c4b9c485') => 'Ź',
        $h('c4b9c2bb') => 'Ż',
        $h('c382c2b7') => '·',
        $h('c38220') => ' ',
        $h('c382c2a0') => ' ',
        $h('c382') => '',
        $h('c3a2e282acc593') => "'",
        $h('c3a2e282acc5a5') => "'",
        $h('c3a2e282acc5be') => '"',
        $h('c3a2e282ace2809c') => '-',
        $h('c3a2e282ace2809d') => '-',
        $h('c3a2e282acc2a6') => '...',
        $h('c3a2e280a0e28099') => '->',
        $h('c3a2e280a0efbfbd') => '<-',
        $h('c3a2e280a0e28098') => '^',
        $h('c3a2e280a0e2809c') => 'v',
        $h('c3a2c593e2809c') => 'OK',
        $h('c3a2c59be2809c') => 'OK',
        $h('c3a2c59be28094') => 'x',
        $h('c3a2c29dc59a') => 'x',
    ];
}

/** @return array<string, string> */
function suspiciousNeedles(): array
{
    $h = static fn(string $hex): string => (string)hex2bin($hex);

    return [
        "\xEF\xBF\xBD" => 'replacement-character',
        $h('c384') => 'possible-polish-mojibake',
        $h('c4b9') => 'possible-polish-mojibake',
        $h('c482') => 'possible-polish-mojibake',
        $h('c382') => 'possible-typography-mojibake',
        $h('c3a2') => 'possible-typography-mojibake',
    ];
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function isAbsolutePath(string $path): bool
{
    return preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1 || str_starts_with($path, '/');
}

function shouldSkipPath(string $path, array $ignoredDirs): bool
{
    foreach (explode('/', normalizePath($path)) as $part) {
        if (isset($ignoredDirs[$part])) {
            return true;
        }
    }
    return false;
}

function shouldScanFile(string $path, array $allowedExtensions, array $ignoredDirs, array $ignoredFiles): bool
{
    if (shouldSkipPath($path, $ignoredDirs)) {
        return false;
    }

    if (isset($ignoredFiles[basename($path)])) {
        return false;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return isset($allowedExtensions[$ext]);
}

/** @return list<string> */
function collectFilesFromPath(string $path, array $allowedExtensions, array $ignoredDirs, array $ignoredFiles): array
{
    if (is_file($path)) {
        return shouldScanFile($path, $allowedExtensions, $ignoredDirs, $ignoredFiles) ? [$path] : [];
    }

    if (!is_dir($path)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            static function (SplFileInfo $current) use ($ignoredDirs): bool {
                return !$current->isDir() || !isset($ignoredDirs[$current->getFilename()]);
            }
        )
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        $filePath = $file->getPathname();
        if (shouldScanFile($filePath, $allowedExtensions, $ignoredDirs, $ignoredFiles)) {
            $files[] = $filePath;
        }
    }

    return $files;
}

/** @return list<string> */
function stagedFiles(string $root, array $allowedExtensions, array $ignoredDirs, array $ignoredFiles): array
{
    $cmd = 'git -C ' . escapeshellarg($root) . ' diff --cached --name-only --diff-filter=ACMR';
    $output = shell_exec($cmd);
    if (!is_string($output) || trim($output) === '') {
        return [];
    }

    $files = [];
    foreach (preg_split('/\R/', trim($output)) ?: [] as $relativePath) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($path) && shouldScanFile($path, $allowedExtensions, $ignoredDirs, $ignoredFiles)) {
            $files[] = $path;
        }
    }

    return $files;
}

function lineForOffset(string $contents, int $offset): int
{
    return substr_count(substr($contents, 0, max(0, $offset)), "\n") + 1;
}

/** @return list<array{label:string,line:int,count:int}> */
function detectIssues(string $contents): array
{
    $issues = [];

    if (str_starts_with($contents, "\xEF\xBB\xBF")) {
        $issues[] = ['label' => 'bom-at-start', 'line' => 1, 'count' => 1];
    }

    $embeddedOffset = strpos($contents, "\xEF\xBB\xBF", 1);
    if ($embeddedOffset !== false) {
        $issues[] = ['label' => 'embedded-bom', 'line' => lineForOffset($contents, $embeddedOffset), 'count' => substr_count($contents, "\xEF\xBB\xBF") - 1];
    }

    if (preg_match('//u', $contents) !== 1) {
        $issues[] = ['label' => 'invalid-utf8', 'line' => 0, 'count' => 1];
        return $issues;
    }

    foreach (suspiciousNeedles() as $needle => $label) {
        $pos = strpos($contents, $needle);
        if ($pos !== false) {
            $issues[] = [
                'label' => $label,
                'line' => lineForOffset($contents, $pos),
                'count' => substr_count($contents, $needle),
            ];
        }
    }

    return $issues;
}

/**
 * Returns repaired contents and human-readable changes.
 * Zwraca naprawiona tresc i opis zmian czytelny dla czlowieka.
 *
 * @return array{contents:string,changes:list<string>,manual:list<string>}
 */
function repairContents(string $contents): array
{
    $changes = [];
    $manual = [];

    if (str_starts_with($contents, "\xEF\xBB\xBF")) {
        $contents = substr($contents, 3);
        $changes[] = 'removed-start-bom';
    }

    if (preg_match('//u', $contents) !== 1) {
        $converted = iconv('CP1250', 'UTF-8//IGNORE', $contents);
        if (is_string($converted) && $converted !== '' && preg_match('//u', $converted) === 1) {
            $contents = $converted;
            $changes[] = 'converted-cp1250-to-utf8';
        } else {
            $manual[] = 'invalid-utf8';
            return ['contents' => $contents, 'changes' => $changes, 'manual' => $manual];
        }
    }

    foreach (encodingRepairMap() as $bad => $good) {
        $count = substr_count($contents, $bad);
        if ($count <= 0) {
            continue;
        }

        $contents = str_replace($bad, $good, $contents);
        $changes[] = 'replace:' . $bad . '=>' . $good . ':' . $count;
    }

    if (str_contains($contents, "\xEF\xBF\xBD")) {
        $manual[] = 'replacement-character';
    }

    return ['contents' => $contents, 'changes' => $changes, 'manual' => $manual];
}

function backupFile(string $root, string $file): string
{
    $relative = ltrim(str_replace([':', '\\', '/'], ['_', DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], substr($file, strlen($root))), DIRECTORY_SEPARATOR);
    $backupPath = $root . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'encoding' . DIRECTORY_SEPARATOR
        . date('Y-m-d_H-i-s') . DIRECTORY_SEPARATOR . $relative . '.back';
    $dir = dirname($backupPath);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create backup directory: ' . $dir);
    }
    if (!copy($file, $backupPath)) {
        throw new RuntimeException('Cannot create backup: ' . $backupPath);
    }
    return $backupPath;
}

$files = [];
if ($staged) {
    $files = stagedFiles($root, $allowedExtensions, $ignoredDirs, $ignoredFiles);
} else {
    $targets = $pathArgs ?: [$root];
    foreach ($targets as $target) {
        $path = isAbsolutePath($target) ? $target : $root . DIRECTORY_SEPARATOR . $target;
        $files = array_merge($files, collectFilesFromPath($path, $allowedExtensions, $ignoredDirs, $ignoredFiles));
    }
}

$files = array_values(array_unique($files));
sort($files, SORT_STRING);

$reports = [];
$fixed = [];
$manual = [];
$errors = [];

foreach ($files as $file) {
    $original = file_get_contents($file);
    if (!is_string($original)) {
        $errors[] = ['file' => $file, 'error' => 'read-failed'];
        continue;
    }

    $issues = detectIssues($original);
    $repair = repairContents($original);
    $changed = $repair['contents'] !== $original;
    $relative = normalizePath(substr($file, strlen($root) + 1));

    if ($issues !== [] || $changed || $repair['manual'] !== []) {
        $reports[] = [
            'file' => $relative,
            'issues' => $issues,
            'changes' => $repair['changes'],
            'manual' => $repair['manual'],
            'changed' => $changed,
        ];
    }

    foreach ($repair['manual'] as $label) {
        $manual[] = ['file' => $relative, 'label' => $label];
    }

    if (!$fix || !$changed) {
        continue;
    }

    try {
        if (!$noBackup) {
            backupFile($root, $file);
        }

        if (file_put_contents($file, $repair['contents']) === false) {
            $errors[] = ['file' => $relative, 'error' => 'write-failed'];
            continue;
        }

        $fixed[] = $relative;
    } catch (Throwable $e) {
        $errors[] = ['file' => $relative, 'error' => $e->getMessage()];
    }
}

if ($json) {
    echo json_encode([
        'mode' => $fix ? 'fix' : 'audit',
        'scanned' => count($files),
        'reports' => $reports,
        'fixed' => $fixed,
        'manual' => $manual,
        'errors' => $errors,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($errors !== [] ? 2 : ($reports !== [] && !$fix ? 1 : 0));
}

echo 'Encoding repair ' . ($fix ? 'FIX' : 'AUDIT') . PHP_EOL;
echo 'Scanned: ' . count($files) . PHP_EOL;

if ($reports === []) {
    echo 'No encoding issues found.' . PHP_EOL;
} else {
    foreach ($reports as $report) {
        echo '- ' . $report['file'] . PHP_EOL;
        foreach ($report['issues'] as $issue) {
            $line = $issue['line'] > 0 ? ':' . $issue['line'] : '';
            echo '  issue ' . $issue['label'] . $line . ' x' . $issue['count'] . PHP_EOL;
        }
        foreach ($report['changes'] as $change) {
            echo '  change ' . $change . PHP_EOL;
        }
        foreach ($report['manual'] as $label) {
            echo '  manual ' . $label . PHP_EOL;
        }
    }
}

foreach ($fixed as $file) {
    echo 'Fixed: ' . $file . PHP_EOL;
}

foreach ($errors as $error) {
    fwrite(STDERR, 'Error: ' . $error['file'] . ' [' . $error['error'] . ']' . PHP_EOL);
}

if (!$fix && $reports !== []) {
    echo PHP_EOL . 'Dry run only. Add --fix to write changes.' . PHP_EOL;
}

if ($manual !== []) {
    echo PHP_EOL . 'Manual review required for ' . count($manual) . ' issue(s).' . PHP_EOL;
}

exit($errors !== [] ? 2 : 0);
