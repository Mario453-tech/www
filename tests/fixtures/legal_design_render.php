<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2) . '/src/i18n.php';
require_once dirname(__DIR__, 2) . '/src/LegalService.php';
$_SESSION = ['locale' => $argv[1] ?? 'pl'];
class CSRF { public static function field(): string { return '<input type="hidden" name="csrf_token" value="fixture">'; } }
function url(string $path, array $params = []): string { return '/' . $path; }

function entry(int $id, string $name, string $status, float $cost, array $app = []): array {
    return ['config' => ['region_id' => $id, 'region_name' => $name, 'risk_level' => 'critical',
        'application_cost' => $cost, 'base_review_minutes' => 120, 'hub_permit_cost' => $cost,
        'hub_review_minutes' => 120], 'permit' => ['status' => $status, 'application' => $app]];
}
$application = ['submitted_at' => '2026-09-28 21:51:00', 'decision_due_at' => '2026-09-28 23:51:00',
    'decided_at' => null, 'cost' => 1000000];
$viewData = array_fill_keys(['active', 'inProgress', 'available', 'locked', 'capitalLocked',
    'credibilityLocked', 'levelLocked', 'hubActive', 'hubInProgress', 'hubAvailable', 'hubLocked', 'bribeQuotes'], []);
$viewData += ['cash' => 0, 'bankBalance' => 600000, 'legalLevel' => 3, 'credibilityScore' => 85,
    'credibilityLevel' => 'high', 'credibilityMin' => 0, 'briberyEnabled' => false,
    'sabotageModuleEnabled' => false, 'error' => '', 'success' => '', 'hasHubSection' => true];
$viewData['active'] = [entry(1, 'Bliski Wschód', 'transitional', 1000000, ['decided_at' => '2026-09-23 23:36:00']),
    entry(2, 'Afryka Subsaharyjska', 'transitional', 500000, ['decided_at' => '2026-09-23 23:36:00']),
    entry(3, 'Rosja / Syberia', 'granted', 500000, ['decided_at' => '2026-09-23 23:36:00']),
    entry(4, 'Upgrade', 'transitional', 500000, $application + ['upgrade_pending' => 1,
        'upgrade_decision_due_at' => '2026-09-29 01:51:00'])];
$viewData['hubActive'] = [entry(2, 'Afryka Subsaharyjska', 'granted', 500000, ['decided_at' => '2026-09-23 23:36:00'])];
$viewData['hubInProgress'] = [entry(1, 'Bliski Wschód', 'pending', 1000000, $application)];
$viewData['hubAvailable'] = [entry(3, 'Rosja / Syberia', 'no_decision', 500000, array_replace($application, ['decided_at' => '2026-09-29 00:00:00']))];
$viewData['legalHistory'] = [1 => ['local' => [
    ['source' => 'archive', 'status' => 'refused', 'cost' => 500000, 'submitted_at' => '2026-09-26 10:00:00', 'decision_due_at' => '2026-09-26 12:00:00', 'decided_at' => '2026-09-26 12:00:00'],
    ['source' => 'fee', 'status' => 'submitted', 'cost' => 500000, 'submitted_at' => '2026-09-25 10:00:00', 'decision_due_at' => null, 'decided_at' => null],
]]];
?><!doctype html><html lang="<?= htmlspecialchars($_SESSION['locale']) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body><main><?php
require dirname(__DIR__, 2) . '/templates/views/legal/main.php';
?></main></body></html>
