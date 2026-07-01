<?php
declare(strict_types=1);

/**
 * GET /api/v1/app/version.php
 *
 * Public endpoint — no auth required.
 * Returns the latest app build number and download URL.
 * Bump $BUILD and $VERSION manually after each CI release.
 */
require_once dirname(__DIR__) . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError(405, 'Method Not Allowed — use GET');
}

// ── Bump these two values after each CI release ──────────────────────────────
const BUILD   = 2;
const VERSION = '1.1.0';
const CHANGELOG_PL = 'Wszystkie działy gry w menu bocznym (Rynek, Bank, HR, Dział Prawny, Logistyka, Sala Zarządu, Sabotaż). Dział Techniczny z pełnymi zakładkami.';
const CHANGELOG_EN = 'All game departments in side menu (Market, Bank, HR, Legal, Logistics, Boardroom, Sabotage). Full Technical Department.';
// ─────────────────────────────────────────────────────────────────────────────

apiJson([
    'build'        => BUILD,
    'version'      => VERSION,
    'download_url' => 'https://github.com/mario453-tech/www/releases/latest',
    'changelog_pl' => CHANGELOG_PL,
    'changelog_en' => CHANGELOG_EN,
    'force'        => false,
]);
