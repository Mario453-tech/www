<?php
declare(strict_types=1);

/**
 * Partial German locale: translated modules override the Polish fallback.
 * Czesciowa wersja niemiecka: moduly nadpisuja polskie teksty zastepcze.
 * Keep this locale out of the public selector until all modules and currency labels are translated.
 * Nie udostepniaj tej wersji przed przetlumaczeniem modulow i walut.
 */

$lang = require __DIR__ . '/pl.php';

return array_replace(
    $lang,
    require __DIR__ . '/de/auth.php',
    require __DIR__ . '/de/common.php',
    require __DIR__ . '/de/nav.php',
    require __DIR__ . '/de/notifications.php',
    require __DIR__ . '/de/profile.php'
);
