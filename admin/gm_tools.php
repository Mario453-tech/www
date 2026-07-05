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
$walletSvc = new WalletService($db);

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
        $startCash  = (float)($_POST['start_cash'] ?? WalletConfig::NEW_PLAYER_STARTING_CASH);

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
                            cash                = 0,
                            bank_balance        = 0,
                            wallet_initialized  = 0,
                            status              = 'active',
                            credit_score        = 200,
                            black_market_score  = 0,
                            last_tick_at        = NOW()
                        WHERE id = ?
                    ")->execute([$resetId]);

                    if (!$walletSvc->initNewPlayer($resetId, $startCash)) {
                        throw new RuntimeException('wallet_reset_failed');
                    }

 // Utwrz domylny magazyn
                    try {
                        $db->prepare('INSERT INTO storage (player_id, capacity, used) VALUES (?, ?, 0)
                                      ON DUPLICATE KEY UPDATE capacity = VALUES(capacity), used = 0')
                           ->execute([$resetId, WalletConfig::NEW_PLAYER_STORAGE_CAPACITY]);
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

 // USUWANIE KONT GRACZY
    elseif ($action === 'delete_players') {
        $rawIds    = $_POST['player_ids'] ?? [];
        $deleteIds = array_values(array_filter(array_map('intval', (array)$rawIds)));

        if (empty($deleteIds)) {
            $msg = t('admin.gm.delete_players_none'); $msgType = 'error';
        } else {
            $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));

 // Kolejnosc ma znaczenie — najpierw dzieci (FK), potem players.
 // Order matters — children first (FK), then players.
            $allTables = [
                'well_staff_assignments', 'well_incidents', 'well_events', 'failure_log',
                'industrial_disasters', 'well_pipeline_events', 'well_pipeline_tick_stats',
                'well_pipelines', 'well_road_trips', 'marine_deliveries', 'logistics_hub_events',
                'wells', 'storage', 'market_offers', 'loan_payments', 'loan_applications', 'loans',
                'bailiff_proceedings', 'bank_negotiations', 'bank_trust_scores', 'bank_trust_log',
                'bankruptcy_events', 'black_market_transactions', 'recruitment_requests', 'hr_events',
                'technical_staff', 'technical_tasks', 'technical_task_queue', 'technical_notifications',
                'player_finance_decisions', 'player_finance_settings', 'finance_logs', 'candidate_reviews',
                'pipelines', 'board_members', 'director_notifications', 'offline_reports', 'chat_bans',
                'email_verifications', 'trusted_devices', 'drilling_permit_applications',
                'hub_permit_applications', 'bribery_attempts', 'company_credibility',
            ];

            $deleted = 0;
            $db->beginTransaction();
            try {
                foreach ($allTables as $table) {
                    try {
                        $db->prepare("DELETE FROM `{$table}` WHERE `player_id` IN ({$placeholders})")
                           ->execute($deleteIds);
                    } catch (Throwable $e) {}
                }
 // Osierocone candidates
                try {
                    $db->exec("DELETE c FROM `candidates` c
                               LEFT JOIN `recruitment_requests` rr ON rr.id = c.request_id
                               WHERE c.request_id IS NOT NULL AND rr.id IS NULL");
                    $db->prepare("DELETE FROM `candidates` WHERE `player_id` IN ({$placeholders})")
                       ->execute($deleteIds);
                } catch (Throwable $e) {}

                $db->prepare("DELETE FROM `players` WHERE `id` IN ({$placeholders})")->execute($deleteIds);
                $db->commit();

                $deleted = count($deleteIds);
                $who = AdminAuth::getAdminUsername();
                AdminLog::log('delete_players', "Usunieto {$deleted} kont graczy: " . implode(', ', $deleteIds), null, $who);
                $msg = t('admin.gm.delete_players_ok', ['count' => $deleted]); $msgType = 'success';
 // Odswierz liste graczy po usunieciu.
 // Refresh player list after deletion.
                $players = $db->query("SELECT id, email FROM players ORDER BY id")->fetchAll();
            } catch (Throwable $e) {
                $db->rollBack();
                $msg = t('admin.gm.delete_players_err', ['msg' => $e->getMessage()]); $msgType = 'error';
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

 // PELNY RESET GRY — kasuje WSZYSTKIE dane graczy, logi i runtime,
 // zostawia tylko konta adminow i tabele konfiguracyjne/referencyjne.
 // FULL GAME RESET — wipes ALL player data, logs and runtime, keeps only
 // admin accounts and config/reference tables, so the game can start fresh.
    elseif ($action === 'full_wipe') {
        $phrase   = trim($_POST['wipe_confirm'] ?? '');
        $expected = 'KASUJ WSZYSTKO';

        if ($phrase !== $expected) {
            $msg = t('admin.gm.wipe_bad_phrase', ['phrase' => $expected]); $msgType = 'error';
        } else {
 // Allowlista tabel do ZACHOWANIA — konfiguracja, tresc, konta adminow.
 // Wszystko inne (dane graczy, logi, runtime) zostanie wyczyszczone.
 // Allowlist of tables to KEEP — config, content, admin accounts.
 // Everything else (player data, logs, runtime) gets wiped.
            $keep = [
 // Konta i uwierzytelnianie adminow / Admin accounts and auth
                'admins', 'admin_trusted_devices', 'admin_password_resets', 'admin_login_attempts',
 // Tresc i pomoc / Content and help
                'admin_help_pages', 'admin_news', 'game_help_pages', 'static_pages',
 // Konfiguracja globalna / Global config
                'site_config', 'nav_items', 'bank_settings', 'board_roles', 'boardroom_config',
                'bribery_config', 'chat_blocked_words', 'disaster_message_templates',
                'legal_region_config', 'logistics_hub_config', 'logistics_region_zones',
                'protection_options', 'sabotage_options', 'transport_config', 'well_config',
 // Dane referencyjne / Reference data
                'geological_layers', 'hr_regions', 'hr_specializations', 'staff_specializations',
                'name_pool', 'world_locations', 'world_regions', 'ports',
 // Katalog / rynek — tresc zarzadzana przez GM / GM-managed content
                'wells_for_sale', 'market_trends', 'market_state',
            ];

            try {
 // Enumeruj realne tabele w bazie (obsluguje tez tabele nieobecne w dumpie).
 // Enumerate actual tables in the DB (also covers tables not present in the dump).
                $allTables = $db->query(
                    "SELECT TABLE_NAME FROM information_schema.TABLES
                      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
                )->fetchAll(PDO::FETCH_COLUMN);

                $keepSet   = array_flip($keep);
                $wipeList  = array_values(array_filter($allTables, fn($t) => !isset($keepSet[$t])));
                sort($wipeList);

                $wiped = 0; $failed = [];
                $db->exec('SET FOREIGN_KEY_CHECKS = 0');
                foreach ($wipeList as $table) {
                    try {
                        $db->exec("TRUNCATE TABLE `{$table}`");
                        $wiped++;
                    } catch (Throwable $e) {
 // Fallback dla tabel, ktorych nie da sie TRUNCATE (np. widok/FK) — DELETE.
 // Fallback for tables that cannot be TRUNCATEd — plain DELETE.
                        try { $db->exec("DELETE FROM `{$table}`"); $wiped++; }
                        catch (Throwable $e2) { $failed[] = $table; }
                    }
                }
                $db->exec('SET FOREIGN_KEY_CHECKS = 1');

 // admin_logs zostalo wyczyszczone — ten wpis rozpoczyna swiezy slad audytu.
 // admin_logs was wiped — this entry starts a fresh audit trail.
                AdminLog::log('full_wipe',
                    "PELNY RESET GRY: wyczyszczono {$wiped} tabel, zachowano " . count($keep)
                    . ' konfiguracyjnych/kont adminow'
                    . ($failed ? '. BLAD tabel: ' . implode(', ', $failed) : ''));
                GameLog::error('admin', 'FULL GAME WIPE executed', null,
                    ['wiped' => $wiped, 'failed' => $failed, 'admin' => $_SESSION['admin_user'] ?? '?']);

                if ($failed) {
                    $msg = t('admin.gm.wipe_partial', ['wiped' => $wiped, 'failed' => implode(', ', $failed)]);
                    $msgType = 'error';
                } else {
                    $msg = t('admin.gm.wipe_ok', ['wiped' => $wiped]); $msgType = 'success';
                }
 // Odswiez liste graczy (teraz pusta) na potrzeby renderu.
 // Refresh player list (now empty) for the render below.
                $players = [];
            } catch (Throwable $e) {
                try { $db->exec('SET FOREIGN_KEY_CHECKS = 1'); } catch (Throwable $e2) {}
                $msg = t('admin.gm.wipe_err', ['msg' => $e->getMessage()]); $msgType = 'error';
                GameLog::error('admin', 'FULL GAME WIPE failed', $e);
            }
        }
    }

 // DODAJ GOTWK WSZYSTKIM
    elseif ($action === 'bulk_cash') {
        $rawAmount = trim((string)($_POST['bulk_amount'] ?? ''));
        if ($rawAmount === '' || !is_numeric($rawAmount)) {
            $msg = 'Podaj prawidlowa kwote.'; $msgType = 'error';
        } else {
            $amount = round((float)$rawAmount, 2);
            if (!is_finite($amount) || abs($amount) > 1_000_000_000.0) {
                $msg = 'Kwota jest poza dozwolonym zakresem.'; $msgType = 'error';
            } elseif (abs($amount) < 0.01) {
                $msg = 'Kwota nie moe by 0.'; $msgType = 'error';
            } else {
                $targetIds = array_map(
                    'intval',
                    $db->query("SELECT id FROM players WHERE status != 'bankrupt'")->fetchAll(PDO::FETCH_COLUMN)
                );

                if ($targetIds === []) {
                    $msg = 'Brak aktywnych graczy do aktualizacji.'; $msgType = 'error';
                } else {
                    $adminUser = AdminAuth::getAdminUsername();
                    $sign = $amount > 0 ? '+' : '';
                    $auditAmount = abs($amount);
                    $auditText = 'Admin bulk cash adjustment by ' . $adminUser . ' (' . $sign . number_format($amount, 2, '.', '') . ')';

                    $db->beginTransaction();
                    try {
                        $db->prepare("UPDATE players SET cash = cash + ? WHERE status != 'bankrupt'")->execute([$amount]);

                        $fts = new FinancialTransactionService($db);
                        foreach ($targetIds as $playerId) {
                            $txId = $amount > 0
                                ? $fts->logTransaction(null, $playerId, $auditAmount, FinancialTransactionService::TYPE_ADMIN_ADJUSTMENT, $auditText, 'admin_bulk_cash', null)
                                : $fts->logTransaction($playerId, null, $auditAmount, FinancialTransactionService::TYPE_ADMIN_ADJUSTMENT, $auditText, 'admin_bulk_cash', null);
                            if ($txId === null) {
                                throw new RuntimeException('bulk_cash_audit_failed');
                            }
                        }

                        $db->commit();
                        $count = count($targetIds);
                        AdminLog::log('bulk_cash', "Globalna zmiana gotwki {$sign}\${$amount} dla {$count} graczy", null, $adminUser);
                        $msg = "Zmieniono gotwk {$sign}\$" . number_format($amount, 2, '.', ' ') . " dla {$count} graczy.";
                        $msgType = 'success';
                    } catch (Throwable $e) {
                        $db->rollBack();
                        $msg = 'Bd zmiany gotwki: ' . $e->getMessage();
                        $msgType = 'error';
                    }
                }
            }
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
$extraJs   = ['/assets/js/admin_gm.js'];
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/gm_tools/main.php';
require_once __DIR__ . '/partials/footer.php';

