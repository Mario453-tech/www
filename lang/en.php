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

return $lang;
