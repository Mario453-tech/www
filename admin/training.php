<?php
/**
 * admin/training.php - panel zarzadzania systemem szkolen.
 * admin/training.php - training system management panel.
 *
 * Zakladki / Tabs:
 *   programs — CRUD dla training_programs
 *   monitor  — podglad aktywnych i ostatnich szkolen graczy
 */

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db      = Database::getInstance()->getConnection();
$adminId = AdminAuth::getAdminId();

$validTabs = ['programs', 'monitor'];
$tab       = (string)($_GET['tab'] ?? 'programs');
if (!in_array($tab, $validTabs, true)) {
    $tab = 'programs';
}

$msg = '';
$err = '';

// ================================================================
// POST — akcje formularzy (tylko zakladka programs)
// ================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $action = (string)($_POST['action'] ?? '');
        $tab    = in_array((string)($_POST['tab'] ?? ''), $validTabs, true)
                  ? (string)$_POST['tab'] : $tab;

        // ---- Dodaj nowy program ----
        if ($action === 'program_create') {
            $result = _training_save_program($db, null, $_POST);
            if ($result['success']) {
                AdminLog::log('training_program_create', 'Created program: ' . ($_POST['code'] ?? ''));
                $_SESSION['admin_flash_msg'] = tPlain('admin.training.msg.program_created');
            } else {
                $_SESSION['admin_flash_error'] = $result['error'];
            }
            header('Location: ?tab=programs');
            exit;
        }

        // ---- Edytuj istniejacy program ----
        if ($action === 'program_update') {
            $id     = (int)($_POST['id'] ?? 0);
            $result = _training_save_program($db, $id, $_POST);
            if ($result['success']) {
                AdminLog::log('training_program_update', 'Updated program id=' . $id);
                $_SESSION['admin_flash_msg'] = tPlain('admin.training.msg.program_updated');
            } else {
                $_SESSION['admin_flash_error'] = $result['error'];
            }
            header('Location: ?tab=programs');
            exit;
        }

        // ---- Wlacz / wylacz program ----
        if ($action === 'program_enable' || $action === 'program_disable') {
            $id      = (int)($_POST['id'] ?? 0);
            $enabled = ($action === 'program_enable') ? 1 : 0;
            $db->prepare("UPDATE training_programs SET enabled=? WHERE id=?")->execute([$enabled, $id]);
            AdminLog::log($action, 'Program id=' . $id . ' enabled=' . $enabled);
            $_SESSION['admin_flash_msg'] = tPlain(
                $enabled ? 'admin.training.msg.program_enabled' : 'admin.training.msg.program_disabled'
            );
            header('Location: ?tab=programs');
            exit;
        }
    }
}

// Pobierz komunikaty z sesji po PRG
if (!empty($_SESSION['admin_flash_msg'])) {
    $msg = $_SESSION['admin_flash_msg'];
    unset($_SESSION['admin_flash_msg']);
}
if (!empty($_SESSION['admin_flash_error'])) {
    $err = $_SESSION['admin_flash_error'];
    unset($_SESSION['admin_flash_error']);
}

// ================================================================
// DANE dla aktywnej zakladki
// ================================================================

$viewData = ['tab' => $tab, 'msg' => $msg, 'err' => $err];

if ($tab === 'programs') {
    $programs = $db->query(
        "SELECT * FROM training_programs ORDER BY department, target_skill, base_pass_rate ASC"
    )->fetchAll(PDO::FETCH_ASSOC);

    $editId  = (int)($_GET['edit'] ?? 0);
    $editRow = null;
    if ($editId > 0) {
        $s = $db->prepare("SELECT * FROM training_programs WHERE id = ? LIMIT 1");
        $s->execute([$editId]);
        $editRow = $s->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $deptOptions  = ['technical', 'board'];
    $skillOptions = [
        'technical' => ['skill_drilling', 'skill_maintenance', 'skill_safety', 'skill_analysis'],
        'board'     => ['skill_negotiation', 'skill_ethics', 'skill_stress', 'skill_organization', 'skill_analysis'],
    ];
    $viewData = array_merge($viewData, compact('programs', 'editRow', 'deptOptions', 'skillOptions'));
}

if ($tab === 'monitor') {
    $filterStatus = (string)($_GET['filter_status'] ?? '');
    $filterDept   = (string)($_GET['filter_dept'] ?? '');

    $where  = ['1=1'];
    $params = [];
    if ($filterStatus !== '' && in_array($filterStatus, ['in_progress','passed','failed','cancelled'], true)) {
        $where[]  = 'st.status = ?';
        $params[] = $filterStatus;
    }
    if ($filterDept !== '' && in_array($filterDept, ['technical','board'], true)) {
        $where[]  = 'st.staff_type = ?';
        $params[] = $filterDept;
    }

    $sql = "
        SELECT
            st.id,
            st.player_id,
            p.username       AS player_name,
            st.staff_type,
            st.staff_id,
            COALESCE(ts.name, bm.name, CONCAT('#', st.staff_id)) AS staff_name,
            tp.name_pl       AS program_name,
            tp.target_skill,
            st.status,
            st.started_at,
            st.finishes_at,
            st.exam_score,
            st.exam_pass_min,
            st.retry_count,
            st.cost_paid
        FROM staff_trainings st
        JOIN training_programs tp ON tp.id = st.program_id
        JOIN players p            ON p.id  = st.player_id
        LEFT JOIN technical_staff ts ON ts.id = st.staff_id AND st.staff_type = 'technical'
        LEFT JOIN board_members   bm ON bm.id = st.staff_id AND st.staff_type = 'board'
        WHERE " . implode(' AND ', $where) . "
        ORDER BY
            CASE st.status WHEN 'in_progress' THEN 0 ELSE 1 END,
            st.started_at DESC
        LIMIT 200
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $trainings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $viewData = array_merge($viewData, compact('trainings', 'filterStatus', 'filterDept'));
}

// ================================================================
// RENDER
// ================================================================
$pageTitle = t('admin.training.page_title');
require_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/../templates/views/admin/training/main.php';
require_once __DIR__ . '/partials/footer.php';

// ================================================================
// FUNKCJA POMOCNICZA — zapis programu (create + update)
// ================================================================
/**
 * @return array{success:bool, error?:string}
 */
function _training_save_program(PDO $db, ?int $id, array $post): array
{
    $code       = trim((string)($post['code'] ?? ''));
    $dept       = (string)($post['department'] ?? '');
    $skill      = trim((string)($post['target_skill'] ?? ''));
    $namePl     = trim((string)($post['name_pl'] ?? ''));
    $nameEn     = trim((string)($post['name_en'] ?? ''));
    $hours      = (int)($post['duration_hours'] ?? 0);
    $cost       = (int)($post['cost'] ?? 0);
    $passRate   = (int)($post['base_pass_rate'] ?? 0);
    $enabled    = isset($post['enabled']) ? 1 : 0;

    // Walidacja
    if ($passRate < 1 || $passRate > 100) {
        return ['success' => false, 'error' => tPlain('admin.training.err.invalid_rate')];
    }
    if ($cost < 0) {
        return ['success' => false, 'error' => tPlain('admin.training.err.invalid_cost')];
    }
    if ($hours < 1) {
        return ['success' => false, 'error' => tPlain('admin.training.err.invalid_hours')];
    }
    if (!in_array($dept, ['technical', 'board'], true)) {
        return ['success' => false, 'error' => tPlain('admin.training.err.not_found')];
    }
    if ($code === '') {
        return ['success' => false, 'error' => tPlain('admin.training.err.not_found')];
    }

    if ($id === null) {
        // Sprawdz unikalnosc kodu
        $check = $db->prepare("SELECT id FROM training_programs WHERE code = ? LIMIT 1");
        $check->execute([$code]);
        if ($check->fetch()) {
            return ['success' => false, 'error' => tPlain('admin.training.err.code_exists')];
        }
        $db->prepare("
            INSERT INTO training_programs
                (code, department, target_skill, name_pl, name_en, duration_hours, cost, base_pass_rate, enabled)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([$code, $dept, $skill, $namePl, $nameEn, $hours, $cost, $passRate, $enabled]);
    } else {
        // Sprawdz unikalnosc kodu przy edycji (poza biezacym rekordem)
        $check = $db->prepare("SELECT id FROM training_programs WHERE code = ? AND id != ? LIMIT 1");
        $check->execute([$code, $id]);
        if ($check->fetch()) {
            return ['success' => false, 'error' => tPlain('admin.training.err.code_exists')];
        }
        $db->prepare("
            UPDATE training_programs
            SET code=?, department=?, target_skill=?, name_pl=?, name_en=?,
                duration_hours=?, cost=?, base_pass_rate=?, enabled=?
            WHERE id=?
        ")->execute([$code, $dept, $skill, $namePl, $nameEn, $hours, $cost, $passRate, $enabled, $id]);
    }

    return ['success' => true];
}
