<?php
/**
 * Benchmark seeder — wypełnia bazę N graczami z realistycznym portfelem,
 * aby zmierzyć faktyczny czas ticka. Tylko do testów wydajności (ci_test).
 *
 * Uzycie: SEED_PLAYERS=100 SEED_WELLS=4 php tests/bench_seed.php
 */
declare(strict_types=1);

$cfg = require __DIR__ . '/../config/database.php';
$dsn = 'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['dbname'] . ';charset=' . $cfg['charset'];
$db  = new PDO($dsn, $cfg['user'], $cfg['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$N      = (int)(getenv('SEED_PLAYERS') ?: 100);
$WELLS  = (int)(getenv('SEED_WELLS') ?: 4);
$BASE   = 1000000; // zakres ID, by nie kolidowac z testami (900M)

// Czyszczenie poprzedniego seedu
foreach ([
    'finance_logs','technical_notifications','technical_task_queue','technical_tasks',
    'logistics_hub_events','logistics_hub_tick_stats','logistics_hub_assignments',
    'well_pipelines','pipelines','logistics_hubs','well_upgrades','storage','wells',
    'technical_staff','board_members','board_roles','players'
] as $t) {
    try { $db->exec("DELETE FROM `$t` WHERE 1=1"); } catch (Throwable $e) {}
}

// Konfiguracja gry wymagana przez tick
$cfgRows = [
    ['cron_secret_key', 0], ['oil_price_current', 80],
    ['global_incident_multiplier',1],['global_disaster_multiplier',1],
];
$cfgStmt = $db->prepare("INSERT INTO well_config (`key`,`value`,`label`) VALUES (?,?,?)
    ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
foreach ($cfgRows as $r) { try { $cfgStmt->execute([$r[0], $r[1], $r[0]]); } catch (Throwable $e) {} }

// Jedna rola techniczna wspolna
$roleId = $BASE;
$db->prepare("INSERT INTO board_roles (id, code, name, created_at) VALUES (?, 'technical', 'Technical', NOW())")
   ->execute([$roleId]);

$pStmt = $db->prepare(
    "INSERT INTO players (id, username, email, password_hash, cash, status, created_at, last_tick_at, last_active_at, safety_procedures_level, procedure_integrity, financial_state)
     VALUES (?,?,?,?,50000000.00,'active', NOW(), DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW(), 3, 80, 'normal')"
);
$sStmt = $db->prepare("INSERT INTO storage (player_id, capacity, used, updated_at) VALUES (?, 100000.00, 5000.00, NOW())");
$mStmt = $db->prepare(
    "INSERT INTO board_members (id, role_id, status, first_name, last_name, birth_date, nationality, experience_years, skill_organization, skill_negotiation, skill_analysis, skill_stress, skill_ethics, trait_loyalty, trait_corruption_risk, trait_ambition, salary)
     VALUES (?, ?, 'active', 'Jan', 'Bench', '1980-01-01', 'PL', 10, 5,5,5,5,5,5,5,5, 10000.00)"
);
$wStmt = $db->prepare(
    "INSERT INTO wells (id, player_id, status, created_at, region_id, zone_key, location_name, name, transport_type, transport_capacity_pct, base_production_per_hour, depth_m, technical_condition, wear_level)
     VALUES (?, ?, 'active', NOW(), 77, 'A1', 'Bench Pole', 'Bench Well', ?, 120.0, 37.5, 2000, 90, 10.0)"
);
$tStmt = $db->prepare(
    "INSERT INTO technical_staff (id, player_id, manager_id, first_name, last_name, spec_code, specialization, spec_name, skill_level, salary, status)
     VALUES (?, ?, ?, 'Jan', 'Bench', ?, ?, ?, 6, 9000, 'active')"
);
$hStmt = $db->prepare(
    "INSERT INTO logistics_hubs (id, player_id, region_id, zone_key, name, hub_type, acquisition_type, status, work_mode, slot_limit, condition_pct, initial_condition_pct, nominal_capacity_bph, real_capacity_bph, buffer_capacity_bbl, buffer_current_bbl, opex_per_tick, lease_fee_per_tick, build_cost, repair_cost_estimate)
     VALUES (?, ?, 77, 'A1', 'Bench Hub', 'medium', 'new', 'active', 'standard', 4, 90, 90, 200, 200, 500, 100, 100, 0, 100000, 200000)"
);
$aStmt = $db->prepare("INSERT INTO logistics_hub_assignments (hub_id, well_id, status, assigned_at, created_at, updated_at) VALUES (?, ?, 'active', NOW(), NOW(), NOW())");
$plStmt = $db->prepare("INSERT INTO pipelines (id, player_id, name, capacity_bbl_h, condition_pct, status, last_inspected_at, transport_loss, built_at) VALUES (?, ?, 'Bench PL', 120, 100, 'active', NOW(), 2.5, NOW())");

$t0 = microtime(true);
$db->beginTransaction();
$id = $BASE + 1;
for ($p = 0; $p < $N; $p++) {
    $pid = $id++;
    $pStmt->execute([$pid, "bench_$pid", "bench_$pid@x.test", 'x']);
    $sStmt->execute([$pid]);
    $mid = $id++;
    $mStmt->execute([$mid, $roleId]);

    $transport = ($p % 3 === 0) ? 'rurociag' : (($p % 3 === 1) ? 'ciezarowki' : 'tankowiec');
    $firstWellId = null;
    for ($w = 0; $w < $WELLS; $w++) {
        $wid = $id++;
        if ($firstWellId === null) $firstWellId = $wid;
        $wStmt->execute([$wid, $pid, $transport]);
    }
    // 2 pracownikow technicznych
    $tStmt->execute([$id++, $pid, $mid, 'maintenance_engineer', 'maintenance_engineer', 'Inzynier']);
    $tStmt->execute([$id++, $pid, $mid, 'drilling_operator', 'drilling_operator', 'Operator']);

    // hub + assignment dla pierwszego odwiertu
    $hid = $id++;
    $hStmt->execute([$hid, $pid]);
    $aStmt->execute([$hid, $firstWellId]);

    // legacy pipeline
    $plStmt->execute([$id++, $pid]);
}
$db->commit();
$t1 = microtime(true);

printf("Seeded %d players × %d wells in %.2fs\n", $N, $WELLS, $t1 - $t0);
printf("Players: %d | Wells: %d | Staff: %d | Hubs: %d\n",
    $db->query("SELECT COUNT(*) FROM players")->fetchColumn(),
    $db->query("SELECT COUNT(*) FROM wells")->fetchColumn(),
    $db->query("SELECT COUNT(*) FROM technical_staff")->fetchColumn(),
    $db->query("SELECT COUNT(*) FROM logistics_hubs")->fetchColumn()
);
