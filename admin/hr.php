<?php

/**
 * admin/hr.php panel HR: kandydaci, historia, statystyki, edycja specjalizacji.
 * admin/hr.php HR panel: candidates, history, stats, specialization editor.
 */

require_once __DIR__ . '/init.php';
require_once dirname(__DIR__) . '/src/Employee/EmployeeSystemConfigService.php';
require_once dirname(__DIR__) . '/src/HR/EmployeeStrikeService.php';
require_once dirname(__DIR__) . '/src/HR/AdminHRConfigService.php';
require_once dirname(__DIR__) . '/src/HR/AdminHRQueryService.php';
AdminAuth::requireLogin();

$db  = Database::getInstance()->getConnection();
$tabAliases = [
    'candidates' => 'dashboard',
    'stats' => 'employees',
    'specializations' => 'roles',
    'history' => 'logs',
    'tests' => 'dashboard',
];
$validTabs = [
    'dashboard', 'employees', 'roles', 'effects', 'assignments', 'morale',
    'raises', 'strikes', 'settings', 'dialogues', 'logs',
];
$requestedTab = (string)($_GET['tab'] ?? 'dashboard');
$tab = $tabAliases[$requestedTab] ?? $requestedTab;
if (!in_array($tab, $validTabs, true)) {
    $tab = 'dashboard';
}
$flash = $_SESSION['admin_hr_flash'] ?? null;
unset($_SESSION['admin_hr_flash']);
$msg = is_array($flash) && ($flash['type'] ?? '') === 'success' ? (string)($flash['message'] ?? '') : '';
$err = is_array($flash) && ($flash['type'] ?? '') === 'error' ? (string)($flash['message'] ?? '') : '';
$validDepartments = ['hr', 'technical', 'finance', 'legal', 'logistics'];
$validRarities = ['common', 'uncommon', 'rare', 'very_rare'];
$hubOperatorCode = 'hub_operator';
$defaultSalaryByDepartment = [
    'hr' => [8000, 15000],
    'technical' => [8000, 18000],
    'finance' => [9000, 16000],
    'legal' => [10000, 18000],
    'logistics' => [4500, 13000],
];
$hrTimingConfigKeys = [
    'raise_response_hours',
    'raise_postpone_hours',
    'negotiation_round_hours',
    'negotiation_cooldown_hours',
    'threat_cycles_required',
];

// Handle the new HR admin actions through one PRG boundary. / Obsluz nowe akcje admina HR przez jedna granice PRG.
$adminHrAction = (string)($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $adminHrAction !== '') {
    $redirectTab = in_array((string)($_POST['return_tab'] ?? ''), $validTabs, true)
        ? (string)$_POST['return_tab']
        : 'dashboard';
    $flash = ['type' => 'error', 'message' => t('common.csrf_error')];
    if (CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        try {
            $adminService = new AdminHRConfigService($db);
            $logDetails = '';
            switch ($adminHrAction) {
                case 'save_settings':
                    $group = (string)($_POST['config_group'] ?? '');
                    $values = is_array($_POST['config'] ?? null) ? $_POST['config'] : [];
                    $changes = $adminService->saveSettings($group, $values);
                    $logDetails = 'Updated HR configuration group ' . $group . ': '
                        . json_encode($changes, JSON_THROW_ON_ERROR);
                    break;
                case 'save_dialogue':
                    $dialogueId = max(0, (int)($_POST['dialogue_id'] ?? 0));
                    $savedId = $adminService->saveDialogue(
                        is_array($_POST['dialogue'] ?? null) ? $_POST['dialogue'] : [],
                        $dialogueId > 0 ? $dialogueId : null
                    );
                    $logDetails = 'Saved employee dialogue template id=' . $savedId;
                    break;
                case 'duplicate_dialogue':
                    $savedId = $adminService->duplicateDialogue((int)($_POST['dialogue_id'] ?? 0));
                    $logDetails = 'Duplicated employee dialogue template as id=' . $savedId;
                    break;
                case 'toggle_dialogue':
                    $dialogueId = (int)($_POST['dialogue_id'] ?? 0);
                    $active = !empty($_POST['dialogue_active']);
                    $adminService->toggleDialogue($dialogueId, $active);
                    $logDetails = 'Set employee dialogue template id=' . $dialogueId
                        . ' active=' . ($active ? '1' : '0');
                    break;
                case 'reset_dialogues':
                    $adminService->resetDialogues();
                    $logDetails = 'Restored seeded employee dialogue templates';
                    break;
                default:
                    throw new InvalidArgumentException('Unknown HR admin action.');
            }
            AdminLog::log(
                'hr_admin_action',
                $logDetails,
                null,
                AdminAuth::getAdminUsername()
            );
            $flash = ['type' => 'success', 'message' => t('admin.hr.msg_action_saved')];
        } catch (Throwable $e) {
            AdminLog::log(
                'hr_admin_action_error',
                'HR admin action failed: action=' . $adminHrAction . ', error=' . $e->getMessage(),
                null,
                AdminAuth::getAdminUsername()
            );
            $flash = ['type' => 'error', 'message' => t('admin.hr.err_action_failed')];
        }
    }
    $_SESSION['admin_hr_flash'] = $flash;
    header('Location: /admin/hr.php?tab=' . rawurlencode($redirectTab));
    exit;
}

// Save typed raise settings using PRG. / Zapisz typowane ustawienia podwyzek przez PRG.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_raise_config'])) {
    $flash = ['type' => 'error', 'message' => t('common.csrf_error')];
    if (CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        try {
            $configService = new EmployeeSystemConfigService($db);
            $allowed = array_filter(
                $configService->definitions(),
                static fn(array $definition, string $key): bool => str_starts_with($key, 'raise_'),
                ARRAY_FILTER_USE_BOTH
            );
            $submitted = is_array($_POST['raise_config'] ?? null) ? $_POST['raise_config'] : [];
            $input = array_intersect_key($submitted, $allowed);
            $changes = $configService->save($input);
            AdminLog::log(
                'hr_raise_config_update',
                'Updated employee raise configuration: ' . json_encode($changes, JSON_THROW_ON_ERROR),
                null,
                AdminAuth::getAdminUsername()
            );
            $flash = ['type' => 'success', 'message' => t('admin.hr.msg_raise_config_saved')];
        } catch (Throwable $e) {
            AdminLog::log(
                'hr_raise_config_error',
                'Employee raise configuration update failed: ' . $e->getMessage(),
                null,
                AdminAuth::getAdminUsername()
            );
            $flash = ['type' => 'error', 'message' => t('admin.hr.err_raise_config_invalid')];
        }
    }
    $_SESSION['admin_hr_flash'] = $flash;
    header('Location: /admin/hr.php?tab=raises');
    exit;
}

// Save whitelisted HR timing settings using PRG. / Zapisz dozwolone ustawienia czasu HR przez PRG.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_hr_timing_config'])) {
    $flash = ['type' => 'error', 'message' => t('common.csrf_error')];
    if (CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        try {
            $configService = new EmployeeSystemConfigService($db);
            $allowed = array_intersect_key($configService->definitions(), array_flip($hrTimingConfigKeys));
            $submitted = is_array($_POST['hr_timing_config'] ?? null) ? $_POST['hr_timing_config'] : [];
            $changes = $configService->save(array_intersect_key($submitted, $allowed));
            AdminLog::log(
                'hr_timing_config_update',
                'Updated employee timing configuration: ' . json_encode($changes, JSON_THROW_ON_ERROR),
                null,
                AdminAuth::getAdminUsername()
            );
            $flash = ['type' => 'success', 'message' => t('admin.hr.msg_timing_config_saved')];
        } catch (Throwable $e) {
            AdminLog::log(
                'hr_timing_config_error',
                'Employee timing configuration update failed: ' . $e->getMessage(),
                null,
                AdminAuth::getAdminUsername()
            );
            $flash = ['type' => 'error', 'message' => t('admin.hr.err_timing_config_invalid')];
        }
    }
    $_SESSION['admin_hr_flash'] = $flash;
    header('Location: /admin/hr.php?tab=raises');
    exit;
}

// Enable test strike negotiations through an explicit admin action. / Wlacz negocjacje strajku testowego jawna akcja admina.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enable_test_negotiations'])) {
    $flash = ['type' => 'error', 'message' => t('common.csrf_error')];
    if (CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        try {
            $changes = (new EmployeeSystemConfigService($db))->save(['feature_negotiations' => true]);
            AdminLog::log(
                'hr_test_negotiations_enabled',
                'Enabled employee strike negotiations for HR tests: ' . json_encode($changes, JSON_THROW_ON_ERROR),
                null,
                AdminAuth::getAdminUsername()
            );
            $flash = ['type' => 'success', 'message' => t('admin.hr.msg_test_negotiations_enabled')];
        } catch (Throwable $e) {
            AdminLog::log(
                'hr_test_negotiations_enable_error',
                'Employee strike negotiations test enable failed: ' . $e->getMessage(),
                null,
                AdminAuth::getAdminUsername()
            );
            $flash = ['type' => 'error', 'message' => t('admin.hr.err_test_negotiations_enable_failed')];
        }
    }
    $_SESSION['admin_hr_flash'] = $flash;
    header('Location: /admin/hr.php?tab=tests');
    exit;
}

// Force a real test strike for a player department. / Wymus realny testowy strajk dzialu gracza.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['force_test_strike'])) {
    $flash = ['type' => 'error', 'message' => t('common.csrf_error')];
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['admin_hr_flash'] = $flash;
        header('Location: /admin/hr.php?tab=tests');
        exit;
    } else {
        $playerId = (int)($_POST['test_strike_player_id'] ?? 0);
        $department = (string)($_POST['test_strike_department'] ?? '');
        try {
            $enableNegotiations = !empty($_POST['enable_test_negotiations_after_strike']);
            $result = (new EmployeeStrikeService($db))->forceActiveForTesting($playerId, $department);
            $negotiationChanges = $enableNegotiations
                ? (new EmployeeSystemConfigService($db))->save(['feature_negotiations' => true])
                : [];
            AdminLog::log(
                'hr_test_strike_forced',
                'Forced HR test strike: player_id=' . $playerId
                    . ', department=' . $department
                    . ', strike_id=' . (int)$result['strike_id']
                    . ', members=' . (int)$result['member_count']
                    . ', negotiations_enabled=' . ($enableNegotiations ? 'yes' : 'no')
                    . ', negotiation_config_changes=' . json_encode($negotiationChanges, JSON_THROW_ON_ERROR),
                null,
                AdminAuth::getAdminUsername()
            );
            $flash = ['type' => 'success', 'message' => t(
                $enableNegotiations ? 'admin.hr.msg_test_strike_forced_with_negotiations' : 'admin.hr.msg_test_strike_forced',
                [
                'player' => $playerId,
                'department' => $department,
                'count' => (int)$result['member_count'],
                ]
            )];
        } catch (Throwable $e) {
            AdminLog::log(
                'hr_test_strike_error',
                'Forced HR test strike failed: ' . $e->getMessage(),
                null,
                AdminAuth::getAdminUsername()
            );
            $flash = ['type' => 'error', 'message' => t('admin.hr.err_test_strike_failed')];
        }
    }
    $_SESSION['admin_hr_flash'] = $flash;
    header('Location: /admin/hr.php?tab=tests');
    exit;
}

// Add a technical staff perk. / Dodaj perk pracownika technicznego.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_spec'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $newCode  = trim(preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['new_spec_code'] ?? '')));
        $newName  = trim($_POST['new_spec_name']   ?? '');
        $newRole  = trim($_POST['new_spec_role']   ?? 'operator');
        $newRarity= trim($_POST['new_spec_rarity'] ?? 'common');
        if (!in_array($newRole, ['operator', 'technician'], true)) {
            $newRole = 'operator';
        }
        if (!in_array($newRarity, $validRarities, true)) {
            $newRarity = 'common';
        }
        if ($newCode === $hubOperatorCode) {
            $err = t('admin.hr.err_hub_operator_reserved');
        } elseif ($newCode === '' || $newName === '') {
            $err = t('admin.hr.err_spec_empty');
        } else {
            try {
                $db->prepare("
                    INSERT INTO staff_specializations
                        (code, name, role, rarity,
                         prod_bonus, wear_reduction, incident_reduction,
                         spiral_reduction, repair_speed,
                         incident_return_reduction, catastrophe_reduction)
                    VALUES (?,?,?,?, 0,0,0, 0,0, 0,0)
                ")->execute([$newCode, $newName, $newRole, $newRarity]);
                AdminLog::log('hr_spec_add', "Added technical staff perk: {$newCode}", null, AdminAuth::getAdminUsername());
                $msg = t('admin.hr.msg_spec_added', ['code' => $newCode]);
            } catch (Throwable $e) {
                $err = str_contains($e->getMessage(), 'Duplicate') ? t('admin.hr.err_spec_duplicate') : t('common.db_error');
            }
        }
        $tab = 'specializations';
    }
}

// Add a recruitable employee position. / Dodaj stanowisko rekrutacyjne pracownika.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_hr_spec'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $newCode   = trim(preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['new_hr_code']   ?? '')));
        $newName   = trim($_POST['new_hr_name']   ?? '');
        $newDept   = trim($_POST['new_hr_dept']   ?? '');
        $newRarity = trim($_POST['new_hr_rarity'] ?? 'common');
        if ($newCode === $hubOperatorCode) {
            $newDept = 'technical';
        }
        if (!in_array($newDept, $validDepartments, true)) {
            $newDept = '';
        }
        if (!in_array($newRarity, $validRarities, true)) {
            $newRarity = 'common';
        }
        $salaryMin = max(1, (float)($_POST['new_hr_salary_min'] ?? ($defaultSalaryByDepartment[$newDept][0] ?? 8000)));
        $salaryMax = max($salaryMin, (float)($_POST['new_hr_salary_max'] ?? ($defaultSalaryByDepartment[$newDept][1] ?? 15000)));
        if ($newCode === '' || $newName === '' || $newDept === '') {
            $err = t('admin.hr.err_spec_empty');
        } else {
            try {
                $db->prepare("
                    INSERT INTO hr_specializations
                        (code, name, department, rarity, base_salary_min, base_salary_max)
                    VALUES (?,?,?,?,?,?)
                ")->execute([$newCode, $newName, $newDept, $newRarity, $salaryMin, $salaryMax]);
                AdminLog::log('hr_hrspec_add', "Added recruitable employee position: {$newCode}", null, AdminAuth::getAdminUsername());
                $msg = t('admin.hr.msg_hrspec_added', ['code' => $newCode]);
            } catch (Throwable $e) {
                $err = str_contains($e->getMessage(), 'Duplicate') ? t('admin.hr.err_spec_duplicate') : t('common.db_error');
            }
        }
        $tab = 'specializations';
    }
}

// Delete a technical staff perk. / Usun perk pracownika technicznego.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_spec'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $code = trim($_POST['code'] ?? '');
        if ($code !== '') {
            try {
                $used = $db->prepare("SELECT COUNT(*) FROM technical_staff WHERE specialization = ?");
                $used->execute([$code]);
                if ((int)$used->fetchColumn() > 0) {
                    throw new RuntimeException('specialization_in_use');
                }
                $db->prepare("DELETE FROM staff_specializations WHERE code = ?")->execute([$code]);
                AdminLog::log('hr_spec_delete', "Deleted technical specialization: {$code}", null, AdminAuth::getAdminUsername());
                $msg = t('admin.hr.msg_spec_deleted', ['code' => $code]);
            } catch (Throwable $e) {
                $err = t('common.db_error');
            }
        }
        $tab = 'specializations';
    }
}

// Delete a recruitable employee position. / Usun stanowisko rekrutacyjne pracownika.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_hr_spec'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $id = (int)($_POST['hr_spec_id'] ?? 0);
        if ($id > 0) {
            try {
                $usedStmt = $db->prepare("
                    SELECT
                        (SELECT COUNT(*) FROM candidates WHERE specialization_id = ?) +
                        (SELECT COUNT(*) FROM board_members WHERE specialization_id = ?) +
                        (SELECT COUNT(*) FROM headhunter_searches WHERE specialization_id = ?) +
                        (SELECT COUNT(*) FROM headhunter_candidates WHERE specialization_id = ?)
                ");
                $usedStmt->execute([$id, $id, $id, $id]);
                if ((int)$usedStmt->fetchColumn() > 0) {
                    throw new RuntimeException('hr_specialization_in_use');
                }
                $db->prepare("DELETE FROM hr_specializations WHERE id = ?")->execute([$id]);
                AdminLog::log('hr_hrspec_delete', "Deleted HR specialization id={$id}", null, AdminAuth::getAdminUsername());
                $msg = t('admin.hr.msg_hrspec_deleted');
            } catch (Throwable $e) {
                $err = t('common.db_error');
            }
        }
        $tab = 'specializations';
    }
}

// Edit a technical staff perk. / Edytuj perk pracownika technicznego.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_spec'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $code     = trim(preg_replace('/[^a-z0-9_]/', '', $_POST['code'] ?? ''));
        $specName = trim($_POST['spec_name'] ?? '');
        if ($code === $hubOperatorCode) {
            $err = t('admin.hr.err_hub_operator_reserved');
            $tab = 'specializations';
        } elseif ($code === '' || $specName === '') {
            $err = t('admin.hr.err_spec_empty');
            $tab = 'specializations';
        } else {
            $fields = [
                'prod_bonus'               => max(0, min(1, (float)($_POST['prod_bonus']               ?? 0))),
                'wear_reduction'           => max(0, min(1, (float)($_POST['wear_reduction']           ?? 0))),
                'incident_reduction'       => max(0, min(1, (float)($_POST['incident_reduction']       ?? 0))),
                'spiral_reduction'         => max(0, min(1, (float)($_POST['spiral_reduction']         ?? 0))),
                'repair_speed'             => max(0, min(1, (float)($_POST['repair_speed']             ?? 0))),
                'incident_return_reduction'=> max(0, min(1, (float)($_POST['incident_return_reduction']?? 0))),
                'catastrophe_reduction'    => max(0, min(1, (float)($_POST['catastrophe_reduction']    ?? 0))),
            ];
            try {
                $stmt = $db->prepare("
                    UPDATE staff_specializations SET
                        name                      = :name,
                        prod_bonus                = :prod_bonus,
                        wear_reduction            = :wear_reduction,
                        incident_reduction        = :incident_reduction,
                        spiral_reduction          = :spiral_reduction,
                        repair_speed              = :repair_speed,
                        incident_return_reduction = :incident_return_reduction,
                        catastrophe_reduction     = :catastrophe_reduction
                    WHERE code = :code
                ");
                $stmt->execute(array_merge([':code' => $code, ':name' => $specName], array_combine(
                    array_map(fn($k) => ':' . $k, array_keys($fields)),
                    array_values($fields)
                )));
                if ($stmt->rowCount() < 1) {
                    $err = t('admin.hr.err_spec_empty');
                } else {
                    AdminLog::log('hr_spec_edit', "Edited technical staff perk: {$code}", null, AdminAuth::getAdminUsername());
                    $msg = t('admin.hr.msg_spec_saved');
                }
            } catch (Throwable $e) {
                $err = t('common.db_error');
            }
            $tab = 'specializations';
        }
    }
}

// Edit a recruitable employee position. / Edytuj stanowisko rekrutacyjne pracownika.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_hr_spec'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $id     = (int)($_POST['hr_spec_id'] ?? 0);
        $name   = trim($_POST['hr_spec_name']    ?? '');
        $rarity = trim($_POST['hr_spec_rarity']  ?? 'common');
        $dept   = trim($_POST['hr_spec_dept']    ?? '');
        if (!in_array($rarity, $validRarities, true)) {
            $rarity = 'common';
        }
        if (!in_array($dept, $validDepartments, true)) {
            $dept = '';
        }
        $salaryMin = max(1, (float)($_POST['hr_salary_min'] ?? 0));
        $salaryMax = max($salaryMin, (float)($_POST['hr_salary_max'] ?? 0));
        if ($id > 0 && $name !== '') {
            try {
                if ($dept === '' || $salaryMin <= 0 || $salaryMax <= 0) {
                    throw new InvalidArgumentException('invalid_hr_spec');
                }
                $db->prepare("
                    UPDATE hr_specializations
                    SET name = ?,
                        rarity = ?,
                        department = CASE WHEN code = 'hub_operator' THEN 'technical' ELSE ? END,
                        base_salary_min = ?,
                        base_salary_max = ?
                    WHERE id = ?
                ")->execute([$name, $rarity, $dept, $salaryMin, $salaryMax, $id]);
                AdminLog::log('hr_hrspec_edit', "Edited recruitable employee position id={$id}", null, AdminAuth::getAdminUsername());
                $msg = t('admin.hr.msg_hrspec_saved');
            } catch (Throwable $e) {
                $err = t('common.db_error');
            }
        }
        $tab = 'specializations';
    }
}

// Delete expired candidates. / Usun wygaslych kandydatow.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_candidates'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        try {
            $deleted = $db->exec("DELETE FROM candidates WHERE expires_at < NOW()");
            AdminLog::log(
                'hr_candidates_cleanup',
                'Deleted expired HR candidates: count=' . (int)$deleted,
                null,
                AdminAuth::getAdminUsername()
            );
            $msg = t('admin.hr.msg_candidates_cleaned', ['count' => $deleted]);
        } catch (Throwable $e) {
            $err = t('common.db_error');
        }
        $tab = 'candidates';
    }
}

// Redirect every classic admin form after POST. / Przekieruj kazdy klasyczny formularz admina po POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['admin_hr_flash'] = [
        'type' => $err !== '' ? 'error' : 'success',
        'message' => $err !== '' ? $err : $msg,
    ];
    header('Location: /admin/hr.php?tab=' . rawurlencode((string)$tab));
    exit;
}

// Keep legacy data loaders behind canonical tabs. / Zachowaj stare loadery danych za kanonicznymi zakladkami.
$legacyDataTab = match ($tab) {
    'dashboard' => 'candidates',
    'employees' => 'stats',
    'roles' => 'specializations',
    'logs' => 'history',
    default => $tab,
};

// Dane: kandydaci aktywni 
$candidates = [];
if ($legacyDataTab === 'candidates') {
    try {
        $candidates = $db->query("
            SELECT c.*,
                   br.name  AS role_name,
                   hs.name  AS spec_name,
                   hs.rarity,
                   hs.department,
                   hr.name  AS region_name,
                   p.email  AS player_email,
                   TIMESTAMPDIFF(YEAR, c.birth_date, CURDATE())  AS age,
                   TIMESTAMPDIFF(HOUR, NOW(), c.expires_at)      AS hours_remaining
            FROM candidates c
            JOIN board_roles br             ON c.role_id        = br.id
            LEFT JOIN hr_specializations hs ON c.specialization_id = hs.id
            LEFT JOIN hr_regions hr         ON c.region_code    = hr.code
            LEFT JOIN players p             ON p.id             = c.player_id
            WHERE c.expires_at > NOW()
            ORDER BY c.expires_at ASC
            LIMIT 200
        ")->fetchAll();
    } catch (Throwable $e) {}
}

// Dane: historia zatrudnienia 
$history    = [];
$histPage   = max(1, (int)($_GET['hpage'] ?? 1));
$histPer    = 50;
$histOffset = ($histPage - 1) * $histPer;
$histTotal  = 0;
if ($legacyDataTab === 'history') {
    try {
        $histTotal = (int)$db->query("SELECT COUNT(*) FROM employment_history")->fetchColumn();
        $history   = $db->prepare("
            SELECT eh.*,
                   bm.first_name, bm.last_name,
                   br.name AS role_name,
                   bm.player_id,
                   p.email AS player_email
            FROM employment_history eh
            LEFT JOIN board_members bm ON eh.member_id = bm.id
            LEFT JOIN board_roles   br ON bm.role_id   = br.id
            LEFT JOIN players       p  ON p.id         = bm.player_id
            ORDER BY eh.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $history->bindValue(1, $histPer,    PDO::PARAM_INT);
        $history->bindValue(2, $histOffset, PDO::PARAM_INT);
        $history->execute();
        $history = $history->fetchAll();
    } catch (Throwable $e) {}
}
$histPages = max(1, (int)ceil($histTotal / $histPer));

// Dane: statystyki HR graczy 
$stats = [];
if ($legacyDataTab === 'stats') {
    try {
        $stats = $db->query("
            SELECT p.id AS player_id, p.email AS player_email,
                   COUNT(ts.id)                        AS staff_count,
                   ROUND(AVG(ts.skill_level), 1)       AS avg_skill,
                   ROUND(SUM(ts.salary) / 720.0, 2)    AS salary_per_hour,
                   SUM(CASE WHEN ts.status = 'busy'   THEN 1 ELSE 0 END) AS busy_count,
                   SUM(CASE WHEN ts.status = 'active' THEN 1 ELSE 0 END) AS active_count
            FROM players p
            LEFT JOIN technical_staff ts ON ts.player_id = p.id AND ts.status != 'fired'
            WHERE p.status != 'bankrupt'
            GROUP BY p.id, p.email
            ORDER BY staff_count DESC
            LIMIT 100
        ")->fetchAll();
    } catch (Throwable $e) {}
}

// Dane: specjalizacje techniczne (staff_specializations) 
$staffSpecs = [];
$hrSpecs    = [];
if ($legacyDataTab === 'specializations') {
    try {
        $rows = $db->query("
            SELECT * FROM staff_specializations ORDER BY role, rarity DESC, name ASC
        ")->fetchAll();
        foreach ($rows as $r) {
            $staffSpecs[$r['role'] ?? 'inne'][] = $r;
        }
    } catch (Throwable $e) {}
    try {
        $rows = $db->query("
            SELECT * FROM hr_specializations ORDER BY department, rarity DESC, name ASC
        ")->fetchAll();
        foreach ($rows as $r) {
            $hrSpecs[$r['department'] ?? 'inne'][] = $r;
        }
    } catch (Throwable $e) {}
}

$raiseConfigDefinitions = [];
$raiseConfigValues = [];
$hrTimingConfigDefinitions = [];
$hrTimingConfigValues = [];
if ($legacyDataTab === 'raises') {
    try {
        $configService = new EmployeeSystemConfigService($db);
        $raiseConfigDefinitions = array_filter(
            $configService->definitions(),
            static fn(array $definition, string $key): bool => str_starts_with($key, 'raise_'),
            ARRAY_FILTER_USE_BOTH
        );
        $raiseConfigValues = array_intersect_key($configService->all(), $raiseConfigDefinitions);
        $hrTimingConfigDefinitions = array_intersect_key($configService->definitions(), array_flip($hrTimingConfigKeys));
        $hrTimingConfigValues = array_intersect_key($configService->all(), $hrTimingConfigDefinitions);
    } catch (Throwable $e) {
        $err = t('common.db_error');
    }
}
$testStrikeTargets = [];
if ($legacyDataTab === 'candidates') {
    try {
        $testStrikeTargets = $db->query(
            "SELECT es.player_id, p.email AS player_email, es.department_code, COUNT(*) AS employee_count,
                    SUM(CASE WHEN es.relation_status='on_strike' THEN 1 ELSE 0 END) AS striking_count
               FROM employee_state es
               JOIN players p ON p.id=es.player_id
              WHERE es.relation_status NOT IN ('inactive','leaving')
              GROUP BY es.player_id, p.email, es.department_code
              ORDER BY es.player_id DESC, es.department_code ASC
              LIMIT 200"
        )->fetchAll();
    } catch (Throwable $e) {
        $err = t('common.db_error');
    }
}

$filters = [
    'player_id' => max(0, (int)($_GET['player_id'] ?? 0)),
    'department' => trim((string)($_GET['department'] ?? '')),
    'status' => trim((string)($_GET['status'] ?? '')),
    'source_type' => trim((string)($_GET['source_type'] ?? '')),
    'target_type' => trim((string)($_GET['target_type'] ?? '')),
    'event_key' => trim((string)($_GET['event_key'] ?? '')),
];
$listPage = max(1, (int)($_GET['page'] ?? 1));
$dashboard = [];
$employeeList = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$assignmentList = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$raiseList = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$strikeList = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$eventList = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$roleEffects = [];
$settingsGroups = [];
$dialogues = [];
$dialoguePagination = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$dialogueContexts = EmployeeDialogueTemplateService::CONTEXTS;
$dialogueTones = EmployeeDialogueTemplateService::TONES;
$strikeRounds = [];
try {
    $queryService = new AdminHRQueryService($db);
    if ($tab === 'dashboard') {
        $dashboard = $queryService->dashboard();
    } elseif (in_array($tab, ['employees', 'morale'], true)) {
        $employeeList = $queryService->employees($filters, $listPage);
    } elseif ($tab === 'effects') {
        $roleEffects = $queryService->roleEffects();
    } elseif ($tab === 'assignments') {
        $assignmentList = $queryService->assignments($filters, $listPage);
    } elseif ($tab === 'raises') {
        $raiseList = $queryService->raises($filters, $listPage);
    } elseif ($tab === 'strikes') {
        $strikeList = $queryService->strikes($filters, $listPage);
        $strikeRounds = $queryService->negotiationRounds(max(0, (int)($_GET['strike_id'] ?? 0)));
    } elseif ($tab === 'settings') {
        $settingsGroups = (new AdminHRConfigService($db))->groupedSettings();
    } elseif ($tab === 'dialogues') {
        $allDialogues = $queryService->dialogues([
            'context_key' => (string)($_GET['context_key'] ?? ''),
            'department_code' => (string)($_GET['department'] ?? ''),
            'tone' => (string)($_GET['tone'] ?? ''),
            'is_active' => (string)($_GET['active'] ?? ''),
        ]);
        $dialogueTotal = count($allDialogues);
        $dialoguePages = max(1, (int)ceil($dialogueTotal / 30));
        $dialoguePage = min($listPage, $dialoguePages);
        $dialogues = array_slice($allDialogues, ($dialoguePage - 1) * 30, 30);
        $dialoguePagination = [
            'rows' => $dialogues,
            'total' => $dialogueTotal,
            'page' => $dialoguePage,
            'pages' => $dialoguePages,
        ];
    } elseif ($tab === 'logs') {
        $eventList = $queryService->events($filters, $listPage);
    }
} catch (Throwable $e) {
    $err = t('common.db_error');
    AdminLog::log(
        'hr_admin_read_error',
        'HR admin data load failed: tab=' . $tab . ', error=' . $e->getMessage(),
        null,
        AdminAuth::getAdminUsername()
    );
}
$pageTitle = t('admin.hr.page_title');
$csrfToken = CSRF::generateToken();

$viewData = [
    'tab'        => $tab,
    'candidates' => $candidates,
    'history'    => $history,
    'histPage'   => $histPage,
    'histPages'  => $histPages,
    'histTotal'  => $histTotal,
    'stats'      => $stats,
    'staffSpecs' => $staffSpecs,
    'hrSpecs'    => $hrSpecs,
    'csrfToken'  => $csrfToken,
    'msg'        => $msg,
    'err'        => $err,
    'pageTitle'  => $pageTitle,
    'validDepartments' => $validDepartments,
    'validRarities' => $validRarities,
    'raiseConfigDefinitions' => $raiseConfigDefinitions,
    'raiseConfigValues' => $raiseConfigValues,
    'hrTimingConfigDefinitions' => $hrTimingConfigDefinitions,
    'hrTimingConfigValues' => $hrTimingConfigValues,
    'testStrikeTargets' => $testStrikeTargets,
    'filters' => $filters,
    'dashboard' => $dashboard,
    'employeeList' => $employeeList,
    'assignmentList' => $assignmentList,
    'raiseList' => $raiseList,
    'strikeList' => $strikeList,
    'eventList' => $eventList,
    'roleEffects' => $roleEffects,
    'settingsGroups' => $settingsGroups,
    'dialogues' => $dialogues,
    'dialoguePagination' => $dialoguePagination,
    'dialogueContexts' => $dialogueContexts,
    'dialogueTones' => $dialogueTones,
    'strikeRounds' => $strikeRounds,
];

$adminExtraCss = ['/assets/css/admin_hr.css'];
$extraJs = ['/assets/js/admin_hr.js'];
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/hr/main.php';
require_once __DIR__ . '/partials/footer.php';
