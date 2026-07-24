<?php
declare(strict_types=1);

/**
 * cron-5min.php
 * Root CRON entrypoint for az.pl / home.pl hosting mechanism.
 * PL: Glowny wejściowy plik crona wykonywany przez mechanizm CRON na hostingu az.pl / home.pl.
 */
define('FORCE_TICK_INTERNAL', true);

require_once __DIR__ . '/cron/tick.php';
