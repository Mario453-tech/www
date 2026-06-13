<?php
declare(strict_types=1);

/**
 * English language file - loader.
 * Polski - loader jezyka angielskiego.
 *
 * Missing English keys fall back to Polish until modules are translated.
 * Brakujace klucze EN spadaja do PL, dopoki moduly nie zostana przetlumaczone.
 */

$lang = require __DIR__ . '/pl.php';

$lang = array_replace($lang, require __DIR__ . '/en/common.php');
$lang = array_replace($lang, require __DIR__ . '/en/auth.php');
$lang = array_replace($lang, require __DIR__ . '/en/admin.php');
$lang = array_replace($lang, require __DIR__ . '/en/bribery.php');
$lang = array_replace($lang, require __DIR__ . '/en/board.php');
$lang = array_replace($lang, require __DIR__ . '/en/bank.php');
$lang = array_replace($lang, require __DIR__ . '/en/components.php');
$lang = array_replace($lang, require __DIR__ . '/en/credibility.php');
$lang = array_replace($lang, require __DIR__ . '/en/director.php');
$lang = array_replace($lang, require __DIR__ . '/en/finance.php');
$lang = array_replace($lang, require __DIR__ . '/en/hr.php');
$lang = array_replace($lang, require __DIR__ . '/en/incidents.php');
$lang = array_replace($lang, require __DIR__ . '/en/legal.php');
$lang = array_replace($lang, require __DIR__ . '/en/logistics.php');
$lang = array_replace($lang, require __DIR__ . '/en/map.php');
$lang = array_replace($lang, require __DIR__ . '/en/market.php');
$lang = array_replace($lang, require __DIR__ . '/en/notifications.php');
$lang = array_replace($lang, require __DIR__ . '/en/profile.php');
$lang = array_replace($lang, require __DIR__ . '/en/protection.php');
$lang = array_replace($lang, require __DIR__ . '/en/sabotage.php');
$lang = array_replace($lang, require __DIR__ . '/en/technical.php');

return $lang;
