<?php
declare(strict_types=1);

/**
 * German language file - loader.
 * Niemiecki plik jezykowy - loader.
 *
 * Missing German keys fall back to Polish until modules are fully translated.
 * Brakujace klucze DE spadaja do PL, dopoki moduly nie zostana przetlumaczone.
 */

$lang = require __DIR__ . '/pl.php';

$lang = array_replace($lang, require __DIR__ . '/de/common.php');
$lang = array_replace($lang, require __DIR__ . '/de/auth.php');
$lang = array_replace($lang, require __DIR__ . '/de/admin.php');
$lang = array_replace($lang, require __DIR__ . '/de/bribery.php');
$lang = array_replace($lang, require __DIR__ . '/de/board.php');
$lang = array_replace($lang, require __DIR__ . '/de/bank.php');
$lang = array_replace($lang, require __DIR__ . '/de/components.php');
$lang = array_replace($lang, require __DIR__ . '/de/contracts.php');
$lang = array_replace($lang, require __DIR__ . '/de/credibility.php');
$lang = array_replace($lang, require __DIR__ . '/de/director.php');
$lang = array_replace($lang, require __DIR__ . '/de/finance.php');
$lang = array_replace($lang, require __DIR__ . '/de/help.php');
$lang = array_replace($lang, require __DIR__ . '/de/home.php');
$lang = array_replace($lang, require __DIR__ . '/de/hr.php');
$lang = array_replace($lang, require __DIR__ . '/de/incidents.php');
$lang = array_replace($lang, require __DIR__ . '/de/legal.php');
$lang = array_replace($lang, require __DIR__ . '/de/logistics.php');
$lang = array_replace($lang, require __DIR__ . '/de/map.php');
$lang = array_replace($lang, require __DIR__ . '/de/market.php');
$lang = array_replace($lang, require __DIR__ . '/de/nav.php');
$lang = array_replace($lang, require __DIR__ . '/de/notifications.php');
$lang = array_replace($lang, require __DIR__ . '/de/profile.php');
$lang = array_replace($lang, require __DIR__ . '/de/protection.php');
$lang = array_replace($lang, require __DIR__ . '/de/recovery.php');
$lang = array_replace($lang, require __DIR__ . '/de/sabotage.php');
$lang = array_replace($lang, require __DIR__ . '/de/technical.php');
$lang = array_replace($lang, require __DIR__ . '/de/training.php');

if (file_exists(__DIR__ . '/de/privacy.php')) {
    $lang = array_replace($lang, require __DIR__ . '/de/privacy.php');
}

return $lang;
