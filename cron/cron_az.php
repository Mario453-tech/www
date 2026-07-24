<?php
declare(strict_types=1);

/**
 * cron/cron_az.php
 * Dedykowany plik crona wykonywany przez mechanizm CRON na hostingu az.pl / home.pl.
 * Dedicated CRON entrypoint for az.pl / home.pl hosting mechanism.
 */
define('FORCE_TICK_INTERNAL', true);

require_once __DIR__ . '/../src/init.php';

$nowFormatted = date('Y-m-d H:i:s');

if (class_exists('GameLog', false)) {
    GameLog::info('cron_az', "CRON AZ.PL URUCHOMIONY POMYŚLNIE: {$nowFormatted}", [
        'sapi' => php_sapi_name(),
        'time' => $nowFormatted,
    ]);
}

// Czerwony wyróżniony komunikat po polsku w konsoli CLI i w przeglądarce
// Bold red status message in Polish for both CLI console and HTML browser view
if (php_sapi_name() === 'cli') {
    echo "\033[1;31m[CRON AZ.PL] Tick uruchomiony pomyślnie o {$nowFormatted}\033[0m\n";
} else {
    echo "<div style=\"color:#e05555; font-weight:bold; font-family:monospace; padding:8px; border:1px solid rgba(224,85,85,0.4); background:rgba(224,85,85,0.1); margin-bottom:10px;\">[CRON AZ.PL] Tick uruchomiony pomyślnie o {$nowFormatted}</div>";
}

require_once __DIR__ . '/tick.php';
