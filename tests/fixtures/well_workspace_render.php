<?php
declare(strict_types=1);
// CLI-only read-only render of local game data / Render lokalnych danych tylko w CLI.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
require $root . '/src/init.php';
$_SESSION['locale'] = in_array($argv[1] ?? '', ['pl','en','de'], true) ? $argv[1] : 'pl';
$playerId = (int)($argv[2] ?? 2);
$wellWorkspace = WellWorkspaceData::prepare(WellGridData::prepare((new Well($playerId))->getWells(), (new Player($playerId))->getData(), new Storage($playerId)), new WellService());
?><!doctype html><html lang="<?= getLocale() ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/well-workspace.css"><link rel="stylesheet" href="/assets/css/modal.css"></head><body>
<?php require $root . '/templates/views/wells/main.php'; ?>
<script src="/assets/js/modal.js"></script><script src="/assets/js/well-workspace.js"></script></body></html>
