<?php
declare(strict_types=1);

/**
 * cron-5min.php
 * Root CRON entrypoint for az.pl / home.pl hosting mechanism.
 * PL: Glowny wejsciowy plik crona wykonywany co 5 minut przez mechanizm CRON na hostingu az.pl / home.pl.
 * Plik musi lezec w /public_html i nazywac sie cron-5min.php aby az.pl go wykrylo.
 */
define('FORCE_TICK_INTERNAL', true);

// Load bootstrap to get GameLog / Laduj bootstrap aby uzyskac dostep do GameLog
require_once __DIR__ . '/src/init.php';

$cronStartedAt = date('Y-m-d H:i:s');

// Red highlighted GameLog entry — visible in admin panel game_debug.log viewer
// Czerwony wyrozniajacy wpis GameLog — widoczny w panelu admina w widoku game_debug.log
if (class_exists('GameLog', false)) {
    GameLog::info('CRON_AZ', "[CRON AZ.PL] Wywolano automatyczny tick crona o {$cronStartedAt}", [
        'source'  => 'cron-5min.php',
        'sapi'    => php_sapi_name(),
        'time'    => $cronStartedAt,
        'trigger' => 'az.pl-cron-5min',
    ]);
}

// Execute tick engine / Uruchom silnik tika
require_once __DIR__ . '/cron/tick.php';
