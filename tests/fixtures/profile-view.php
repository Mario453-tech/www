<?php
declare(strict_types=1);
// Render the real profile view with isolated data for browser checks.
// Renderuj prawdziwy profil z izolowanymi danymi do testow przegladarkowych.
$strings = require dirname(__DIR__, 2) . '/lang/pl/profile.php';
function t(string $key, array $params = []): string {
    global $strings;
    $text = $strings[$key] ?? ($key === 'common.currency' ? 'zł' : $key);
    foreach ($params as $name => $value) $text = str_replace('{' . $name . '}', (string)$value, $text);
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}
function asset(string $path): string { return $path; }
$viewData = [
    'playerId' => 7, 'playerData' => ['username'=>'Stefan', 'active_wells'=>3, 'well_count'=>5, 'safety_procedures_level'=>3],
    'csrfToken'=>'test-token', 'errors'=>[], 'success'=>[], 'avatarUrl'=>null,
    'companyName'=>'Testowa Spółka', 'memberSince'=>'27.10.2026', 'lastLogin'=>'27.10.2026 10:00',
    'sellStats'=>['total_sold'=>12000],
    'bmStats'=>['total_transactions'=>3, 'total_revenue'=>5000, 'total_penalties'=>100, 'times_detected'=>1],
    'bmScore'=>40, 'credibilityScore'=>80, 'credibilityLevel'=>'high',
];
require dirname(__DIR__, 2) . '/templates/views/profile/main.php';
