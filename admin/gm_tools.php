<?php
/**
 * admin/gm_tools.php Narzdzia Game Mastera
 *
 * Funkcje:
 * - Broadcast wiadomoci do wszystkich graczy
 * - Reset gracza (wyzerowanie konta)
 * - Klonowanie konta testowego
 * - Podgld ekonomii (sumy globalne)
 * - Czyszczenie wygasych danych
 * - Zmiana prdkoci gry (tick multiplier)
 */
require_once __DIR__ . '/init.php';
GameLog::info('admin/gm_tools.php', 'entry');
AdminAuth::requireLogin();

$db  = Database::getInstance()->getConnection();
$msg = $msgType = '';

// AKCJE POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        die('<p class="alert alert-error">Bd CSRF.</p>');
    }

    $action = $_POST['action'] ?? '';

 // BROADCAST 
    if ($action === 'broadcast') {
        $title   = trim($_POST['bc_title']   ?? '');
        $message = trim($_POST['bc_message'] ?? '');
        $type    = $_POST['bc_type'] ?? 'info';

        if (!$title || !$message) {
            $msg = 'Tytu i tre s wymagane.'; $msgType = 'error';
        } else {
            $players = $db->query("SELECT id FROM players WHERE status != 'bankrupt'")->fetchAll();
            $sent = 0;
            $bcStmt = $db->prepare("
                INSERT INTO hr_events (player_id, type, title, message, is_read)
                VALUES (?, ?, ?, ?, 0)
            ");
            foreach ($players as $p) {
                $bcStmt->execute([$p['id'], 'gm_broadcast', $title, $message]);
                $sent++;
            }
            AdminLog::log('broadcast', "Broadcast do {$sent} graczy: {$title}");
            $msg = "Wysano broadcast do {$sent} graczy."; $msgType = 'success';
        }
    }

 // RESET GRACZA 
    elseif ($action === 'reset_player') {
        $resetId    = (int)($_POST['reset_player_id'] ?? 0);
        $keepLogin  = isset($_POST['keep_login']);
        $startCash  = (float)($_POST['start_cash'] ?? 5000000);

        if (!$resetId) {
            $msg = 'Nie wybrano gracza.'; $msgType = 'error';
        } else {
            $pStmt = $db->prepare("SELECT id, email FROM players WHERE id = ?");
            $pStmt->execute([$resetId]);
            $resetPlayer = $pStmt->fetch();

            if (!$resetPlayer) {
                $msg = 'Gracz nie istnieje.'; $msgType = 'error';
            } else {
                $db->beginTransaction();
                try {
 // Kolejno ma znaczenie zalenoci FK najpierw usu dzieci
                    $simpleDeletes = [
                        'well_staff_assignments'  => 'player_id',
                        'well_incidents'          => 'player_id',
                        'well_events'             => 'player_id',
                        'failure_log'             => 'player_id',
                        'industrial_disasters'    => 'player_id',
                        'well_pipeline_events'    => 'player_id',
                        'well_pipeline_tick_stats'=> 'player_id',
                        'well_pipelines'          => 'player_id',
                        'well_road_trips'         => 'player_id',
                        'marine_deliveries'       => 'player_id',
                        'logistics_hub_events'    => 'player_id',
                        'wells'                   => 'player_id', // well_upgrades kasuje si przez ON DELETE CASCADE
                        'storage'                 => 'player_id',
                        'market_offers'           => 'player_id',
                        'loan_payments'           => 'player_id',
                        'loan_applications'       => 'player_id',
                        'loans'                   => 'player_id',
                        'bailiff_proceedings'     => 'player_id',
                        'bank_negotiations'          => 'player_id',
                        'bank_trust_scores'          => 'player_id',
                        'bank_trust_log'             => 'player_id',
                        'bankruptcy_events'          => 'player_id',
                        'black_market_transactions'  => 'player_id',
                        'recruitment_requests'    => 'player_id',
                        'hr_events'               => 'player_id',
                        'technical_staff'         => 'player_id',
                        'technical_tasks'         => 'player_id',
                        'technical_task_queue'    => 'player_id',
                        'technical_notifications' => 'player_id',
                        'player_finance_decisions'=> 'player_id',
                        'finance_logs'            => 'player_id',
                        'candidate_reviews'       => 'player_id',
                        'pipelines'               => 'player_id',
                    ];

                    $requestIds = [];
                    try {
                        $rqStmt = $db->prepare("SELECT id FROM recruitment_requests WHERE player_id = ?");
                        $rqStmt->execute([$resetId]);
                        $requestIds = array_map('intval', $rqStmt->fetchAll(PDO::FETCH_COLUMN));
                    } catch (Throwable $e) {}

                    foreach ($simpleDeletes as $table => $col) {
                        try {
                            $db->prepare("DELETE FROM `{$table}` WHERE `{$col}` = ?")->execute([$resetId]);
                        } catch (Throwable $e) { /* tabela moe nie istnie */ }
                    }

 // board_members i powizane (employee_contracts, employment_history maj ON DELETE CASCADE)
                    try {
                        $db->prepare("DELETE FROM `board_members` WHERE `player_id` = ?")->execute([$resetId]);
                    } catch (Throwable $e) {}

 // candidates nie ma player_id powizane przez recruitment_requests (ju usunite)
 // Usu osierocone candidates bez aktywnych requestw
                    try {
                        $db->prepare("DELETE FROM `candidates` WHERE `player_id` = ?")->execute([$resetId]);
                    } catch (Throwable $e) {}
                    foreach ($requestIds as $requestId) {
                        try {
                            $db->prepare("DELETE FROM `candidates` WHERE `request_id` = ?")->execute([$requestId]);
                        } catch (Throwable $e) {}
                    }
                    try {
                        $db->exec("DELETE c FROM `candidates` c
                                   LEFT JOIN `recruitment_requests` rr ON rr.id = c.request_id
                                   WHERE c.request_id IS NOT NULL AND rr.id IS NULL");
                    } catch (Throwable $e) {}

 // Reset gracza
                    $db->prepare("
                        UPDATE players SET
                            cash                = ?,
                            status              = 'active',
                            credit_score        = 200,
                            black_market_score  = 0,
                            last_tick_at        = NOW()
                        WHERE id = ?
                    ")->execute([$startCash, $resetId]);

 // Utwrz domylny magazyn
                    try {
                        $db->prepare("INSERT INTO storage (player_id, capacity, used) VALUES (?, 10000, 0)
                                      ON DUPLICATE KEY UPDATE capacity = 10000, used = 0")
                           ->execute([$resetId]);
                    } catch (Throwable $e) {}

                    $db->commit();
                    AdminLog::log('reset_player', "Reset gracza #{$resetId} ({$resetPlayer['email']}), start cash: \${$startCash}", $resetId);
                    $msg = "Gracz #{$resetId} ({$resetPlayer['email']}) zresetowany. Gotwka: \$" . number_format($startCash, 0, '.', ' ');
                    $msgType = 'success';

                } catch (Throwable $e) {
                    $db->rollBack();
                    $msg = 'Bd resetu: ' . $e->getMessage(); $msgType = 'error';
                    error_log('GM reset_player error: ' . $e->getMessage());
                }
            }
        }
    }

 // KLONOWANIE KONTA TESTOWEGO 
    elseif ($action === 'clone_test') {
        $sourceId = (int)($_POST['source_id'] ?? 0);
        $newEmail = trim($_POST['new_email'] ?? '');
        $newPass  = trim($_POST['new_pass']  ?? '');

        if (!$sourceId || !$newEmail || !$newPass) {
            $msg = 'Wypenij wszystkie pola.'; $msgType = 'error';
        } else {
 // Sprawd czy email nie zajty
            $exists = $db->prepare("SELECT id FROM players WHERE email = ?");
            $exists->execute([$newEmail]);
            if ($exists->fetch()) {
                $msg = 'Ten email jest ju zajty.'; $msgType = 'error';
            } else {
                $src = $db->prepare("SELECT * FROM players WHERE id = ?");
                $src->execute([$sourceId]);
                $source = $src->fetch();

                if (!$source) {
                    $msg = 'Gracz rdowy nie istnieje.'; $msgType = 'error';
                } else {
                    $db->beginTransaction();
                    try {
                        $hash = password_hash($newPass, PASSWORD_BCRYPT);
                        $db->prepare("
                            INSERT INTO players (email, password, cash, status, last_tick_at, created_at)
                            VALUES (?, ?, ?, 'active', NOW(), NOW())
                        ")->execute([$newEmail, $hash, $source['cash']]);
                        $newId = (int)$db->lastInsertId();

 // Klonuj magazyn
                        $stor = $db->prepare("SELECT capacity, used FROM storage WHERE player_id = ?");
                        $stor->execute([$sourceId]);
                        $s = $stor->fetch();
                        if ($s) {
                            $db->prepare("INSERT INTO storage (player_id, capacity, used) VALUES (?, ?, ?)")
                               ->execute([$newId, $s['capacity'], $s['used']]);
                        }

 // Klonuj odwierty
                        $wellsStmt = $db->prepare("SELECT * FROM wells WHERE player_id = ?");
                        $wellsStmt->execute([$sourceId]);
                        foreach ($wellsStmt->fetchAll() as $w) {
                            $db->prepare("
                                INSERT INTO wells (player_id, well_type, location_name, depth_m,
                                    base_production_per_hour, upkeep_cost_per_hour, technical_condition,
                                    pressure, reservoir_remaining, reservoir_max, status)
                                VALUES (?,?,?,?,?,?,?,?,?,?,?)
                            ")->execute([
                                $newId, $w['well_type'], $w['location_name'], $w['depth_m'],
                                $w['base_production_per_hour'], $w['upkeep_cost_per_hour'], $w['technical_condition'],
                                $w['pressure'], $w['reservoir_remaining'], $w['reservoir_max'], $w['status']
                            ]);
                        }

 // Klonuj rurocig
                        try {
                            $db->prepare("INSERT INTO pipelines (player_id, name) VALUES (?, 'Rurocig gwny')")
                               ->execute([$newId]);
                        } catch (Throwable $e) {}

                        $db->commit();
                        AdminLog::log('clone_player', "Sklonowano gracza #{$sourceId}  #{$newId} ({$newEmail})", $newId);
                        $msg = "Sklonowano konto  #{$newId} ({$newEmail}), haso: ustawione.";
                        $msgType = 'success';

                    } catch (Throwable $e) {
                        $db->rollBack();
                        $msg = 'Bd klonowania: ' . $e->getMessage(); $msgType = 'error';
                    }
                }
            }
        }
    }

 // CZYSZCZENIE DANYCH 
    elseif ($action === 'cleanup') {
        $cleaned = 0;
 // Wygase kandydaci
        $r = $db->exec("DELETE FROM candidates WHERE expires_at < NOW()");
        $cleaned += $r;
 // Stare logi
        $r = $db->exec("DELETE FROM admin_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
        $cleaned += $r;
 // Zakoczone rekrutacje starsze ni 7 dni
        $r = $db->exec("DELETE FROM recruitment_requests WHERE status = 'completed' AND ready_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $cleaned += $r;
 // Przeczytane hr_events starsze ni 30 dni
        $r = $db->exec("DELETE FROM hr_events WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $cleaned += $r;

        AdminLog::log('cleanup', "Czyszczenie bazy: usunito {$cleaned} wierszy");
        $msg = "Wyczyszczono {$cleaned} starych rekordw."; $msgType = 'success';
    }

 // DODAJ GOTWK WSZYSTKIM 
    elseif ($action === 'bulk_cash') {
        $amount = (int)($_POST['bulk_amount'] ?? 0);
        if ($amount === 0) {
            $msg = 'Kwota nie moe by 0.'; $msgType = 'error';
        } else {
            $db->prepare("UPDATE players SET cash = cash + ? WHERE status != 'bankrupt'")->execute([$amount]);
            $count = $db->query("SELECT COUNT(*) FROM players WHERE status != 'bankrupt'")->fetchColumn();
            $sign = $amount > 0 ? '+' : '';
            AdminLog::log('bulk_cash', "Globalna zmiana gotwki {$sign}\${$amount} dla {$count} graczy");
            $msg = "Zmieniono gotwk {$sign}\$" . number_format($amount, 0, '.', ' ') . " dla {$count} graczy.";
            $msgType = 'success';
        }
    }
}

// DANE 

// Globalna ekonomia
$econ = $db->query("
    SELECT
        COUNT(*)                                              AS players_total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END)   AS players_active,
        SUM(cash)                                             AS total_cash,
        AVG(cash)                                             AS avg_cash,
        MIN(cash)                                             AS min_cash,
        MAX(cash)                                             AS max_cash
    FROM players WHERE status != 'bankrupt'
")->fetch();

$wellStats = $db->query("
    SELECT
        COUNT(*)                                              AS total_wells,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END)   AS active_wells,
        SUM(CASE WHEN status IN ('broken','paused_cash') THEN 1 ELSE 0 END) AS broken_wells,
        SUM(base_production_per_hour)                         AS total_prod,
        AVG(technical_condition)                               AS avg_condition
    FROM wells
")->fetch();

$loanStats = $db->query("
    SELECT
        COUNT(*)                                              AS total_loans,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END)   AS active_loans,
        SUM(CASE WHEN status = 'active' THEN remaining_amount ELSE 0 END) AS total_debt
    FROM loans
")->fetch();

$staffStats = $db->query("
    SELECT
        (SELECT COUNT(*) FROM board_members WHERE status = 'active') AS board_count,
        (SELECT COUNT(*) FROM technical_staff WHERE status IN ('active','busy')) AS tech_count,
        (SELECT COUNT(*) FROM candidates WHERE expires_at > NOW())  AS candidates_count
")->fetch();

$market = $db->query("SELECT current_price FROM market_state WHERE id = 1")->fetch();

$players = $db->query("SELECT id, email FROM players ORDER BY id")->fetchAll();

$cronCheck = $db->query("SELECT MAX(last_tick_at) AS lt FROM players")->fetch();
$lastTickAgo = $cronCheck['lt'] ? round((time() - strtotime($cronCheck['lt'])) / 60) : 999;

$viewData = [
    'msg'         => $msg,
    'msgType'     => $msgType,
    'econ'        => $econ,
    'wellStats'   => $wellStats,
    'loanStats'   => $loanStats,
    'staffStats'  => $staffStats,
    'market'      => $market,
    'players'     => $players,
    'lastTickAgo' => $lastTickAgo,
];

$pageTitle = 'GM Tools';
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/gm_tools/main.php';
require_once __DIR__ . '/partials/footer.php';

