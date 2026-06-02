<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$fixBom = in_array('--fix-bom', $args, true);
$json = in_array('--json', $args, true);
$showClean = in_array('--show-clean', $args, true);
$onlyBom = in_array('--only-bom', $args, true);
$pathFilters = [];
$limit = 250;
$extensions = [
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

$suspicious = [
    hex2bin('efbfbd') => 'replacement-character',
    hex2bin('c4b9') => 'mojibake-L',
    hex2bin('c384') => 'mojibake-A',
    hex2bin('c382') => 'mojibake-B',
    hex2bin('c482') => 'mojibake-C',
    hex2bin('c3a2e282ac') => 'mojibake-quote',
    hex2bin('c3a2e280a0') => 'mojibake-arrow',
];

foreach ($args as $arg) {
    if (str_starts_with($arg, '--path=')) {
        $pathFilters[] = trim(str_replace('\\', '/', substr($arg, 7)), '/');
        continue;
    }

    if (str_starts_with($arg, '--extensions=')) {
        $extensions = [];
        foreach (explode(',', substr($arg, 13)) as $ext) {
            $ext = strtolower(trim($ext, " \t\n\r\0\x0B."));
            if ($ext !== '') {
                $extensions[$ext] = true;
            }
        }
        continue;
    }

    if (str_starts_with($arg, '--limit=')) {
        $limit = max(0, (int)substr($arg, 8));
    }
}

function normalizePathForGit(string $path): string
{
    return str_replace('\\', '/', $path);
}

function runGitList(string $root): array
{
    $cmd = 'git -C ' . escapeshellarg($root) . ' ls-files';
    $output = shell_exec($cmd);
    if (!is_string($output)) {
        fwrite(STDERR, 'Cannot run git ls-files.' . PHP_EOL);
        exit(2);
    }

    $files = preg_split('/\R/', trim($output));
    if (!is_array($files) || $files === ['']) {
        return [];
    }

    return $files;
}

function shouldScanTrackedFile(string $relativePath, array $extensions, array $pathFilters): bool
{
    $relativePath = normalizePathForGit($relativePath);
    if ($pathFilters) {
        $matchesPath = false;
        foreach ($pathFilters as $filter) {
            if ($relativePath === $filter || str_starts_with($relativePath, $filter . '/')) {
                $matchesPath = true;
                break;
            }
        }
        if (!$matchesPath) {
            return false;
        }
    }

    $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
    return isset($extensions[$ext]);
}

function lineForOffset(string $contents, int $offset): int
{
    if ($offset <= 0) {
        return 1;
    }

    return substr_count(substr($contents, 0, $offset), "\n") + 1;
}

function collectNeedlePositions(string $contents, string $needle): array
{
    $positions = [];
    $offset = 0;
    while (($pos = strpos($contents, $needle, $offset)) !== false) {
        $positions[] = $pos;
        $offset = $pos + strlen($needle);
    }

    return $positions;
}

function lineEndingInfo(string $contents): array
{
    $crlf = 0;
    $lf = 0;
    $cr = 0;
    $length = strlen($contents);

    for ($i = 0; $i < $length; $i++) {
        $char = $contents[$i];
        if ($char === "\r") {
            if (($i + 1) < $length && $contents[$i + 1] === "\n") {
                $crlf++;
                $i++;
            } else {
                $cr++;
            }
            continue;
        }

        if ($char === "\n") {
            $lf++;
        }
    }

    if ($crlf > 0 && ($lf > 0 || $cr > 0)) {
        $type = 'mixed';
    } elseif ($crlf > 0) {
        $type = 'crlf';
    } elseif ($lf > 0) {
        $type = 'lf';
    } elseif ($cr > 0) {
        $type = 'cr';
    } else {
        $type = 'none';
    }

    return [
        'type' => $type,
        'crlf' => $crlf,
        'lf' => $lf,
        'cr' => $cr,
    ];
}

function addIssue(array &$issues, string $type, int $line = 0, int $count = 1, string $detail = ''): void
{
    $issues[] = [
        'type' => $type,
        'line' => $line,
        'count' => $count,
        'detail' => $detail,
    ];
}

$trackedFiles = runGitList($root);
$reports = [];
$fixed = [];
$scanned = 0;

foreach ($trackedFiles as $relativePath) {
    if (!shouldScanTrackedFile($relativePath, $extensions, $pathFilters)) {
        continue;
    }

    $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($absolutePath)) {
        continue;
    }

    $contents = file_get_contents($absolutePath);
    if (!is_string($contents)) {
        continue;
    }

    $scanned++;
    $issues = [];

    if (str_starts_with($contents, "\xEF\xBB\xBF")) {
        if ($fixBom) {
            file_put_contents($absolutePath, substr($contents, 3));
            $contents = substr($contents, 3);
            $fixed[] = $relativePath;
        } else {
            addIssue($issues, 'bom-at-start', 1, 1, 'safe to remove automatically');
        }
    }

    $embeddedBomPositions = collectNeedlePositions($contents, "\xEF\xBB\xBF");
    foreach ($embeddedBomPositions as $position) {
        if ($position === 0) {
            continue;
        }
        addIssue($issues, 'embedded-bom', lineForOffset($contents, $position), 1, 'manual review recommended');
    }

    $lineEndings = lineEndingInfo($contents);

    if (!$onlyBom) {
        if (preg_match('//u', $contents) !== 1) {
            addIssue($issues, 'invalid-utf8', 0, 1, 'file is not valid UTF-8');
        } else {
            foreach ($suspicious as $needle => $label) {
                $positions = collectNeedlePositions($contents, $needle);
                if (!$positions) {
                    continue;
                }
                addIssue($issues, $label, lineForOffset($contents, $positions[0]), count($positions), 'possible mojibake');
            }
        }

        if ($lineEndings['type'] === 'mixed') {
            addIssue(
                $issues,
                'mixed-line-endings',
                0,
                $lineEndings['crlf'] + $lineEndings['lf'] + $lineEndings['cr'],
                'crlf=' . $lineEndings['crlf'] . ', lf=' . $lineEndings['lf'] . ', cr=' . $lineEndings['cr']
            );
        }
    }

    if ($issues || $showClean) {
        $reports[] = [
            'file' => normalizePathForGit($relativePath),
            'line_endings' => $lineEndings,
            'issues' => $issues,
        ];
    }
}

if ($json) {
    echo json_encode([
        'scanned' => $scanned,
        'files_with_issues' => count(array_filter($reports, static fn (array $report): bool => (bool)$report['issues'])),
        'fixed_bom' => $fixed,
        'reports' => $reports,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($reports ? 1 : 0);
}

$issueReports = array_values(array_filter($reports, static fn (array $report): bool => (bool)$report['issues']));

echo 'Git encoding audit' . PHP_EOL;
echo 'Scanned files: ' . $scanned . PHP_EOL;
echo 'Files with issues: ' . count($issueReports) . PHP_EOL;

if ($fixed) {
    echo 'Fixed BOM at start:' . PHP_EOL;
    foreach ($fixed as $file) {
        echo '- ' . normalizePathForGit($file) . PHP_EOL;
    }
}

if (!$issueReports) {
    echo 'No encoding issues found.' . PHP_EOL;
    exit(0);
}

echo PHP_EOL . 'Issues:' . PHP_EOL;
$printed = 0;
foreach ($issueReports as $report) {
    if ($limit > 0 && $printed >= $limit) {
        $remaining = count($issueReports) - $printed;
        echo '... ' . $remaining . ' more files hidden. Use --limit=0 to show all.' . PHP_EOL;
        break;
    }

    echo '- ' . $report['file'] . ' [line-endings=' . $report['line_endings']['type'] . ']' . PHP_EOL;
    foreach ($report['issues'] as $issue) {
        $line = $issue['line'] > 0 ? ' line ' . $issue['line'] : '';
        $detail = $issue['detail'] !== '' ? ' - ' . $issue['detail'] : '';
        echo '  * ' . $issue['type'] . $line . ' count=' . $issue['count'] . $detail . PHP_EOL;
    }
    $printed++;
}

exit(1);
