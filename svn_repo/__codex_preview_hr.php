<?php
$lang = require __DIR__ . '/lang/pl.php';
function t(string $key, array $params = []): string {
    global $lang;
    $text = $lang[$key] ?? $key;
    foreach ($params as $k => $v) {
        $text = str_replace(':' . $k, (string)$v, $text);
    }
    return $text;
}
$csrfToken = 'preview';
$viewData = [
    'employees' => [[
        'id' => 1,
        'source' => 'technical_staff',
        'first_name' => 'Jan',
        'last_name' => 'Nowak',
        'gender' => 'M',
        'role_name' => 'Inżynier odwiertów',
        'salary' => 14500,
        'experience_years' => 7,
        'nationality' => 'Polska',
        'days_employed' => 43,
        'contract_end' => '2026-08-15',
        'contract_days_left' => 18,
        'birth_date' => '1991-05-18',
        'trait_loyalty' => 7,
        'trait_corruption_risk' => 3,
        'trait_ambition' => 8,
        'skill_organization' => 7,
        'skill_negotiation' => 5,
        'skill_analysis' => 8,
        'skill_stress' => 6,
        'skill_ethics' => 8,
        'spec_name' => 'Drilling Engineer',
    ]],
    'directors' => [[
        'id' => 11,
        'first_name' => 'Anna',
        'last_name' => 'Kowalska',
        'gender' => 'F',
        'role_name' => 'Dyrektor Kadr',
        'salary' => 28500,
        'experience_years' => 14,
        'nationality' => 'Polska',
        'days_employed' => 120,
        'age' => 41,
        'skill_organization' => 9,
        'skill_negotiation' => 8,
        'skill_analysis' => 7,
        'skill_stress' => 8,
        'skill_ethics' => 9,
    ]],
    'contracts' => [[
        'first_name' => 'Jan',
        'last_name' => 'Nowak',
        'role_name' => 'Inżynier odwiertów',
        'contract_start' => '2026-02-01',
        'contract_end' => '2026-08-15',
        'salary' => 14500,
        'days_left' => 18,
    ]],
    'expiring' => [[1]],
    'history' => [[
        'created_at' => '2026-05-03 11:20:00',
        'action' => 'hired',
        'first_name' => 'Jan',
        'last_name' => 'Nowak',
        'role_name' => 'Inżynier odwiertów',
        'reason' => 'Umowa 1 rok',
    ]],
    'regions' => [
        ['name' => 'Polska', 'code' => 'PL', 'skill_modifier' => 1.05, 'salary_modifier' => 0.95, 'availability' => 73],
        ['name' => 'Europa', 'code' => 'EU', 'skill_modifier' => 1.12, 'salary_modifier' => 1.08, 'availability' => 66],
        ['name' => 'USA / Kanada', 'code' => 'US_CA', 'skill_modifier' => 1.20, 'salary_modifier' => 1.25, 'availability' => 54],
    ],
    'specializations' => [
        ['id' => 1, 'department' => 'technical', 'name' => 'Drilling Engineer'],
        ['id' => 2, 'department' => 'technical', 'name' => 'Reservoir Engineer'],
    ],
    'hhActiveSearch' => null,
    'hhCandidates' => [[
        'id' => 21,
        'first_name' => 'Marek',
        'last_name' => 'Lis',
        'spec_name' => 'Reservoir Engineer',
        'current_company' => 'Baltic Energy',
        'skill_level' => 9,
        'salary_expectation' => 32500,
        'signing_bonus_min' => 120000,
        'join_probability' => 64,
        'loyalty' => 4,
        'hours_remaining' => 19,
    ]],
    'hhRecentSearches' => [[
        'spec_name' => 'Reservoir Engineer',
        'status' => 'completed',
        'result_count' => 1,
        'cost' => 180000,
    ]],
    'csrfToken' => $csrfToken,
];
?><!doctype html>
<html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/css/recruitment.css"><link rel="stylesheet" href="/assets/css/hr.css"></head><body class="hr-body"><?php include __DIR__ . '/templates/views/hr/main.php'; ?></body></html>
