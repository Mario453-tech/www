<?php
/**
 * Admin Boardroom management
 * Manage header/footer config, roles (CRUD), role images.
 */
require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db = Database::getInstance()->getConnection();
$errors  = [];
$success = [];

// Upload directory for boardroom images.
// PL: Katalog uploadu obrazow boardroomu.
$uploadDir = __DIR__ . '/../assets/images/boardroom/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

/**
 * Validates and stores a boardroom image upload.
 * PL: Waliduje i zapisuje upload obrazu boardroomu.
 */
function boardroomStoreImageUpload(array $file, string $uploadDir, string $prefix, int $maxBytes): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }
    if ((int)($file['size'] ?? 0) <= 0 || (int)$file['size'] > $maxBytes) {
        throw new RuntimeException(t('boardroom.err_avatar_format'));
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = $fi ? finfo_file($fi, (string)$file['tmp_name']) : false;
    if ($fi) {
        finfo_close($fi);
    }
    if (!$realMime || !isset($allowed[$realMime]) || @getimagesize((string)$file['tmp_name']) === false) {
        throw new RuntimeException(t('boardroom.err_avatar_format'));
    }

    $fname = $prefix . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$realMime];
    $target = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $fname;
    if (!move_uploaded_file((string)$file['tmp_name'], $target)) {
        throw new RuntimeException(t('boardroom.err_avatar_format'));
    }

    return '/assets/images/boardroom/' . $fname;
}

// POST HANDLERS

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = t('common.csrf_error');
    }

    $action = $_POST['action'] ?? '';

 // Add new role
    if (!$errors && $action === 'add_role') {
        try {
            $code = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['role_code'] ?? '')));
            $name = trim($_POST['role_name'] ?? '');
            $desc = trim($_POST['role_description'] ?? '');
            $icon = trim($_POST['role_icon'] ?? '');
            $sort = (int)($_POST['role_sort_order'] ?? 0);

            if (!$code || !$name) {
                $errors[] = t('boardroom.err_code_name_required');
            } else {
 // Check if code exists
                $chk = $db->prepare("SELECT id FROM board_roles WHERE code = ?");
                $chk->execute([$code]);
                if ($chk->fetch()) {
                    $errors[] = t('boardroom.err_code_exists', ['code' => $code]);
                } else {
                    $stmt = $db->prepare("INSERT INTO board_roles (code, name, description, icon, sort_order, is_active) VALUES (?,?,?,?,?,1)");
                    $stmt->execute([$code, $name, $desc, $icon, $sort]);
                    $success[] = t('boardroom.msg_role_added', ['name' => $name]);
                }
            }
        } catch (Throwable $e) {
            $errors[] = t('boardroom.err_generic', ['msg' => $e->getMessage()]);
        }
    }

 // Update role
    if (!$errors && $action === 'update_role') {
        try {
            $roleId = (int)($_POST['role_id'] ?? 0);
            $name   = trim($_POST['role_name'] ?? '');
            $desc   = trim($_POST['role_description'] ?? '');
            $icon   = trim($_POST['role_icon'] ?? '');
            $sort   = (int)($_POST['role_sort_order'] ?? 0);
            $active = isset($_POST['role_active']) ? 1 : 0;

            if (!$roleId || !$name) {
                $errors[] = t('boardroom.err_id_name_required');
            } else {
                $stmt = $db->prepare("UPDATE board_roles SET name=?, description=?, icon=?, sort_order=?, is_active=? WHERE id=?");
                $stmt->execute([$name, $desc, $icon, $sort, $active, $roleId]);

 // Avatar upload for role
                if (isset($_FILES['role_avatar']) && $_FILES['role_avatar']['error'] === UPLOAD_ERR_OK) {
                    try {
                        $relPath = boardroomStoreImageUpload($_FILES['role_avatar'], $uploadDir, 'role_' . $roleId, 3 * 1024 * 1024);
                        if ($relPath !== null) {
                            $db->prepare("UPDATE board_roles SET avatar_path=? WHERE id=?")->execute([$relPath, $roleId]);
                        }
                    } catch (RuntimeException $uploadError) {
                        $errors[] = $uploadError->getMessage();
                    }
                }

                if (empty($errors)) $success[] = t('boardroom.msg_role_updated', ['id' => $roleId]);
            }
        } catch (Throwable $e) {
            $errors[] = t('boardroom.err_generic', ['msg' => $e->getMessage()]);
        }
    }

 // Delete role
    if (!$errors && $action === 'delete_role') {
        try {
            $roleId = (int)($_POST['role_id'] ?? 0);
 // Check if any active members use this role
            $chk = $db->prepare("SELECT COUNT(*) FROM board_members WHERE role_id=? AND status='active'");
            $chk->execute([$roleId]);
            if ((int)$chk->fetchColumn() > 0) {
                $errors[] = t('boardroom.err_role_has_members');
            } else {
                $db->prepare("DELETE FROM board_roles WHERE id=?")->execute([$roleId]);
                $success[] = t('boardroom.msg_role_deleted');
            }
        } catch (Throwable $e) {
            $errors[] = t('boardroom.err_generic', ['msg' => $e->getMessage()]);
        }
    }

}

// FETCH DATA 

try {
    $roles = $db->query("SELECT * FROM board_roles ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
 // Fallback gdy brak kolumny sort_order dodaj j przez panel DB lub uruchom migracj
    $roles = $db->query("SELECT * FROM board_roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
}

// Member counts per role
$memberCounts = [];
try {
    $mc = $db->query("SELECT role_id, COUNT(*) as cnt FROM board_members WHERE status='active' GROUP BY role_id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($mc as $r) $memberCounts[(int)$r['role_id']] = (int)$r['cnt'];
} catch (Throwable $e) {}

$viewData = [
    'errors'       => $errors,
    'success'      => $success,
    'roles'        => $roles,
    'memberCounts' => $memberCounts,
];

$pageTitle = t('boardroom.admin_nav');
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/boardroom/main.php';
require_once __DIR__ . '/partials/footer.php';
