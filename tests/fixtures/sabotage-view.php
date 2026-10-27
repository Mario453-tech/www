<?php
declare(strict_types=1);
// Render the real page view with isolated test data. Never included in the game.
// Renderuj prawdziwy widok z danymi testowymi, bez uzycia w grze.
$strings = require dirname(__DIR__, 2) . '/lang/pl/sabotage.php';
function tPlain(string $key, array $params = []): string {
    global $strings;
    $text = $strings[$key] ?? ($key === 'common.currency' ? 'zł' : $key);
    foreach ($params as $name => $value) $text = str_replace(':' . $name, (string)$value, $text);
    return $text;
}
function t(string $key, array $params = []): string { return htmlspecialchars(tPlain($key, $params), ENT_QUOTES, 'UTF-8'); }
function url(string $page, array $params = []): string { return '/sabotage' . ($params ? '?' . http_build_query($params) : ''); }
final class CSRF { public static function field(): string { return '<input type="hidden" name="csrf_token" value="test">'; } }
$option = ['id'=>7, 'name'=>'Budżet', 'description'=>'Koszt operacji', 'severity'=>'medium', 'base_chance_pct'=>65, 'cost_type'=>'fixed', 'cost_value'=>150000, 'cooldown_minutes'=>180, 'requires_black_market'=>0];
$targets = [
    ['id'=>2, 'company_name'=>'Stefan Co', 'username'=>'Stefan', 'status'=>'active', 'cash'=>400000],
    ['id'=>3, 'company_name'=>'Nieaktywny', 'username'=>'Idle', 'status'=>'inactive', 'cash'=>50000],
];
$viewData = [
    'error'=>'', 'success'=>'', 'moduleEnabled'=>true, 'options'=>[$option],
    'targets'=>$targets, 'targetPage'=>1, 'targetPages'=>2, 'targetTotal'=>15,
    'targetQuery'=>'', 'targetStatus'=>'all', 'selectedTarget'=>0,
    'attempts'=>[['option_name'=>'Budżet', 'option_code'=>'budget', 'target_company'=>'Stefan Co', 'target_username'=>'Stefan', 'status'=>'success', 'created_at'=>'2026-10-27 10:00:00']],
    'historyPage'=>1, 'historyPages'=>2, 'historyTotal'=>6,
    'cooldownMap'=>[], 'costQuotes'=>[2=>[7=>150000],3=>[7=>150000]],
    'bmThreshold'=>30, 'operationToken'=>'test-operation-token', 'playerData'=>['cash'=>200000, 'black_market_score'=>40],
];
require dirname(__DIR__, 2) . '/templates/views/sabotage/main.php';
