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
const BUILD   = 1;
const VERSION = '1.0.0';
const CHANGELOG_PL = 'Nowa wersja dostępna.';
const CHANGELOG_EN = 'New version available.';
// ─────────────────────────────────────────────────────────────────────────────

apiJson([
    'build'        => BUILD,
    'version'      => VERSION,
    'download_url' => 'https://github.com/mario453-tech/www/releases/latest',
    'changelog_pl' => CHANGELOG_PL,
    'changelog_en' => CHANGELOG_EN,
    'force'        => false,
]);
