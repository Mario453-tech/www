<?php
declare(strict_types=1);

// =============================================================================
// Polish language file - LOADER
// Polski plik jezykowy - LADOWACZ
//
// This file merges all module translation files from lang/pl/.
// Ten plik scala wszystkie pliki tlumaczen modulow z lang/pl/.
//
// To edit translations for a module, edit the corresponding file:
// Aby edytowac tlumaczenia modulu, edytuj odpowiedni plik:
//   lang/pl/admin.php
//   lang/pl/auth.php
//   lang/pl/bank.php
//   lang/pl/board.php
//   lang/pl/common.php
//   lang/pl/components.php
//   lang/pl/director.php
//   lang/pl/finance.php
//   lang/pl/hr.php
//   lang/pl/incidents.php
//   lang/pl/logistics.php
//   lang/pl/map.php
//   lang/pl/market.php
//   lang/pl/notifications.php
//   lang/pl/profile.php
//   lang/pl/technical.php
// =============================================================================

$lang = [];

$lang += require __DIR__ . '/pl/admin.php';
$lang += require __DIR__ . '/pl/auth.php';
$lang += require __DIR__ . '/pl/bank.php';
$lang += require __DIR__ . '/pl/board.php';
$lang += require __DIR__ . '/pl/common.php';
$lang += require __DIR__ . '/pl/components.php';
$lang += require __DIR__ . '/pl/director.php';
$lang += require __DIR__ . '/pl/finance.php';
$lang += require __DIR__ . '/pl/hr.php';
$lang += require __DIR__ . '/pl/incidents.php';
$lang += require __DIR__ . '/pl/logistics.php';
$lang += require __DIR__ . '/pl/map.php';
$lang += require __DIR__ . '/pl/market.php';
$lang += require __DIR__ . '/pl/notifications.php';
$lang += require __DIR__ . '/pl/profile.php';
$lang += require __DIR__ . '/pl/technical.php';

return $lang;
