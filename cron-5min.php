<?php
/**
 * Cron az.pl uruchamiany co 5 minut przez serwer.
 * Plik musi znajdowa si w katalogu gwnym konta (public_html/).
 * Nazwa: cron-5min.php az.pl wywouje go automatycznie co 5 minut.
 */

define('FORCE_TICK_INTERNAL', true); // pomija guard HTTP w cron/tick.php
require_once __DIR__ . '/cron/tick.php';
