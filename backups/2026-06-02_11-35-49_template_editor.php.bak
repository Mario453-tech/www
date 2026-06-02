<?php
// AJAX chunk upload metadane w GET, surowe bajty w body 
// application/octet-stream omija Suhosin i ModSecurity na az.pl
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' &&
    ($_GET['ajax_upload'] ?? '') === '1'
) {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    function _ub_json(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

    try {
        require_once __DIR__ . '/init.php';

        if (!AdminAuth::isLoggedIn()) {
            _ub_json(['ok' => false, 'err' => 'Sesja wygasa  zaloguj si ponownie.']);
        }
        if (!CSRF::validateToken($_GET['csrf_token'] ?? '')) {
            _ub_json(['ok' => false, 'err' => 'Bd CSRF  odwie stron.']);
        }

        $bgName      = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['bg_name']     ?? '');
        $mime        = trim($_GET['bg_file_mime'] ?? '');
        $chunkIdx    = (int)($_GET['chunk_index']  ?? 0);
        $totalChunks = (int)($_GET['total_chunks'] ?? 1);
        $uploadId    = preg_replace('/[^a-z0-9]/', '', $_GET['upload_id']     ?? '');

        if (!$bgName)   _ub_json(['ok' => false, 'err' => 'Brak nazwy roli (bg_name).']);
        if (!$uploadId) _ub_json(['ok' => false, 'err' => 'Brak upload_id.']);

 // Odczyt surowych danych binarnych z body
        $chunkData = file_get_contents('php://input');
        if ($chunkData === false || $chunkData === '') {
            _ub_json(['ok' => false, 'err' => 'php://input pusty. CL=' . ($_SERVER['CONTENT_LENGTH'] ?? '?') . '  skontaktuj si z supportem az.pl.']);
        }

        $chunkDir = __DIR__ . '/../assets/images/boardroom/';
        if (!is_dir($chunkDir) && !mkdir($chunkDir, 0755, true)) {
            _ub_json(['ok' => false, 'err' => 'Nie mona utworzy katalogu assets/images/boardroom/.']);
        }

        $cf = $chunkDir . '.ub_' . $uploadId . '_' . $bgName . '_' . $chunkIdx;
        if (file_put_contents($cf, $chunkData) === false) {
            _ub_json(['ok' => false, 'err' => 'Bd zapisu chunka #' . $chunkIdx . '.']);
        }

        if ($chunkIdx + 1 >= $totalChunks) {
            $finalData = '';
            for ($i = 0; $i < $totalChunks; $i++) {
                $cfi = $chunkDir . '.ub_' . $uploadId . '_' . $bgName . '_' . $i;
                if (!file_exists($cfi)) {
                    _ub_json(['ok' => false, 'err' => 'Brakuje chunka #' . $i . '.']);
                }
                $finalData .= file_get_contents($cfi);
                unlink($cfi);
            }

            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
                _ub_json(['ok' => false, 'err' => 'Niedozwolony format: ' . $mime]);
            }
            if (strlen($finalData) > 20 * 1024 * 1024) {
                _ub_json(['ok' => false, 'err' => 'Plik za duy (max 20 MB).']);
            }

            $dest = __DIR__ . '/../assets/images/boardroom_bg_' . $bgName . '.png';
            if (file_put_contents($dest, $finalData) !== false) {
                GameLog::info('admin/template_editor', 'boardroom bg uploaded', ['name' => $bgName]);
                _ub_json(['ok' => true, 'done' => true, 'msg' => 'Zapisano: boardroom_bg_' . $bgName . '.png']);
            }
            _ub_json(['ok' => false, 'err' => 'Bd zapisu pliku docelowego. Sprawd uprawnienia /assets/images/.']);
        }

        _ub_json(['ok' => true, 'done' => false, 'chunk' => $chunkIdx]);

    } catch (Throwable $e) {
        _ub_json(['ok' => false, 'err' => 'Wyjtek: ' . $e->getMessage()]);
    }
}

$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/template_editor.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db  = Database::getInstance()->getConnection();
$msg = '';
$err = '';

// Bootstrap tabel 
try {
    $db->exec("CREATE TABLE IF NOT EXISTS `site_config` (
        `key`        VARCHAR(64) NOT NULL PRIMARY KEY,
        `value`      TEXT NOT NULL DEFAULT '',
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by` VARCHAR(64) NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $db->exec("CREATE TABLE IF NOT EXISTS `nav_items` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `label`      VARCHAR(64) NOT NULL,
        `url_key`    VARCHAR(64) NOT NULL,
        `icon`       VARCHAR(16) NOT NULL DEFAULT '',
        `sort_order` SMALLINT NOT NULL DEFAULT 0,
        `active`     TINYINT(1) NOT NULL DEFAULT 1,
        `css_class`  VARCHAR(32) NOT NULL DEFAULT '',
        `location`   ENUM('header','footer','actions') NOT NULL DEFAULT 'header',
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    try { Database::addColumnIfMissing('nav_items', 'location', "ENUM('header','footer','actions') NOT NULL DEFAULT 'header'"); } catch (Throwable $__e) {}
    try {
        $db->exec("ALTER TABLE nav_items MODIFY COLUMN `location` ENUM('header','footer','actions') NOT NULL DEFAULT 'header'");
    } catch (Throwable $__e) {}

    $defaults = [
        ['site_name',    'OilCorp'],
        ['site_tagline', 'Strategiczna gra naftowa'],
        ['footer_text',  '&copy; {year} OilCorp. Wszystkie prawa zastrzeone.'],
        ['footer_js',    '/assets/js/game.js'],
        ['nav_items_seeded', '0'],
    ];
    $ins = $db->prepare("INSERT IGNORE INTO site_config (`key`, `value`) VALUES (?, ?)");
    foreach ($defaults as [$k, $v]) { $ins->execute([$k, $v]); }

    $navSeeded = (string)($db->query("SELECT `value` FROM site_config WHERE `key`='nav_items_seeded' LIMIT 1")->fetchColumn() ?: '0');
    $shouldSeedNavItems = ($navSeeded !== '1')
        && ((int)$db->query("SELECT COUNT(*) FROM nav_items")->fetchColumn() === 0);

    if ($shouldSeedNavItems) {
        $navDefaults = [
            ['Dashboard', 'home',      '',    10, 1, '', 'header'],
            ['Mapa',      'map',       '',  20, 1, '', 'header'],
            ['Rynek',     'market',    '',    30, 1, '', 'header'],
            ['Bank',      'bank',      '',    40, 1, '', 'header'],
            ['Zarzd',    'hr',        '',  50, 1, '', 'header'],
            ['Technika',  'technical', '',  60, 1, '', 'header'],
            ['Pomoc',     'help',      '',  70, 1, '', 'header'],
            ['Wyloguj',   'logout',    '',    99, 1, 'btn-danger', 'header'],
        ];
        $navIns = $db->prepare("INSERT INTO nav_items (label,url_key,icon,sort_order,active,css_class,location) VALUES (?,?,?,?,?,?,?)");
        foreach ($navDefaults as $row) { $navIns->execute($row); }
    }

    if ($shouldSeedNavItems) {
        $actionsDefaults = [
            ['Rynek ropy',    'market',       '', 10, 1, 'btn-success',   'actions'],
            ['Kup odwiert',   'map',          '', 20, 1, 'btn-info',      'actions'],
            ['Zarzd / HR',   'hr',           '', 30, 1, 'btn-secondary', 'actions'],
            ['Bank',          'bank',         '', 40, 1, 'btn-secondary', 'actions'],
        ];
        $actIns = $db->prepare("INSERT INTO nav_items (label,url_key,icon,sort_order,active,css_class,location) VALUES (?,?,?,?,?,?,?)");
        foreach ($actionsDefaults as $row) { $actIns->execute($row); }
    }

    if ($shouldSeedNavItems) {
        $footerDefaults = [
            ['Regulamin',   '/regulamin',  '', 10, 1, '', 'footer'],
            ['Polityka',    '/polityka',   '', 20, 1, '', 'footer'],
            ['Kontakt',     '/kontakt',    '', 30, 1, '', 'footer'],
            ['Instrukcja',  'help',        '', 40, 1, '', 'footer'],
        ];
        $footerIns = $db->prepare("INSERT INTO nav_items (label,url_key,icon,sort_order,active,css_class,location) VALUES (?,?,?,?,?,?,?)");
        foreach ($footerDefaults as $row) { $footerIns->execute($row); }
        $db->prepare("UPDATE site_config SET `value`='1' WHERE `key`='nav_items_seeded'")->execute();
    }
} catch (Throwable $e) {
    GameLog::error('admin/template_editor', 'bootstrap failed', $e);
}

// POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $err = t('common.csrf_error');
    } else {
        $action = $_POST['action'] ?? '';
        $who    = AdminAuth::getAdminUsername();

        if ($action === 'save_config') {
            $keys = ['site_name', 'site_tagline', 'footer_text', 'footer_js'];
            $upd  = $db->prepare("INSERT INTO site_config (`key`,`value`,`updated_by`) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), updated_by=VALUES(updated_by)");
            foreach ($keys as $k) {
                $upd->execute([$k, $_POST[$k] ?? '', $who]);
            }
            $msg = t('admin.template_editor.msg_config_saved');
            GameLog::info('admin/template_editor', 'site_config saved', ['by' => $who]);

        } elseif ($action === 'save_nav') {
            $id       = (int)($_POST['nav_id'] ?? 0);
            $label    = trim($_POST['label']    ?? '');
            $urlKey   = trim($_POST['url_key']  ?? '');
            $icon     = trim($_POST['icon']     ?? '');
            $sort     = (int)($_POST['sort_order'] ?? 0);
            $active   = isset($_POST['active']) ? 1 : 0;
            $cssClass = trim($_POST['css_class'] ?? '');
            if ($id > 0 && $label && $urlKey) {
                $db->prepare("UPDATE nav_items SET label=?,url_key=?,icon=?,sort_order=?,active=?,css_class=? WHERE id=?")
                   ->execute([$label, $urlKey, $icon, $sort, $active, $cssClass, $id]);
                $msg = t('admin.template_editor.msg_nav_saved');
            } else {
                $err = t('admin.template_editor.err_invalid_data');
            }

        } elseif ($action === 'add_nav') {
            $label  = trim($_POST['new_label']   ?? '');
            $urlKey = trim($_POST['new_url_key'] ?? '');
            $icon   = trim($_POST['new_icon']    ?? '');
            $loc    = in_array($_POST['new_location'] ?? '', ['header','footer','actions']) ? $_POST['new_location'] : 'header';
            $sort   = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM nav_items WHERE location=" . $db->quote($loc))->fetchColumn();
            if ($label && $urlKey) {
                $db->prepare("INSERT INTO nav_items (label,url_key,icon,sort_order,active,css_class,location) VALUES (?,?,?,?,1,'',?)")
                   ->execute([$label, $urlKey, $icon, $sort, $loc]);
                $msg = t('admin.template_editor.msg_nav_added', ['loc' => $loc]);
            } else {
                $err = t('admin.template_editor.err_label_url_required');
            }

        } elseif ($action === 'delete_nav') {
            $id = (int)($_POST['nav_id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM nav_items WHERE id=?")->execute([$id]);
                $msg = t('admin.template_editor.msg_nav_deleted');
                GameLog::info('admin/template_editor', 'nav_item deleted', ['id' => $id, 'by' => $who]);
            }

        } elseif ($action === 'save_boardroom') {
            try {
                $brFields = ['header_title', 'header_subtitle', 'footer_text'];
                $brUpd = $db->prepare("INSERT INTO boardroom_config (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?");
                foreach ($brFields as $f) {
                    $val = trim($_POST[$f] ?? '');
                    $brUpd->execute([$f, $val, $val]);
                }

 // Footer links (JSON array)
                if (isset($_POST['footer_link_label'])) {
                    $labels = $_POST['footer_link_label'] ?? [];
                    $urls   = $_POST['footer_link_url'] ?? [];
                    $links  = [];
                    for ($i = 0; $i < count($labels); $i++) {
                        $l = trim($labels[$i] ?? '');
                        $u = trim($urls[$i] ?? '');
                        if ($l && $u) $links[] = ['label' => $l, 'url' => $u];
                    }
                    $json = json_encode($links, JSON_UNESCAPED_UNICODE);
                    $brUpd->execute(['footer_links', $json, $json]);
                }

 // Header image upload
                $brUploadDir = __DIR__ . '/../assets/images/boardroom/';
                if (!is_dir($brUploadDir)) mkdir($brUploadDir, 0755, true);
                if (isset($_FILES['header_image']) && $_FILES['header_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['header_image'];
                    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
                    if (!in_array($file['type'], $allowed)) {
                        $err = t('boardroom.err_img_format');
                    } elseif ($file['size'] > 5 * 1024 * 1024) {
                        $err = t('boardroom.err_img_size');
                    } else {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $fname = 'header_' . time() . '.' . $ext;
                        if (move_uploaded_file($file['tmp_name'], $brUploadDir . $fname)) {
                            $relPath = '/assets/images/boardroom/' . $fname;
                            $brUpd->execute(['header_image', $relPath, $relPath]);
                        } else {
                            $err = t('boardroom.err_img_save');
                        }
                    }
                }

                if (!$err) $msg = t('admin.template_editor.br_msg_saved');
                GameLog::info('admin/template_editor', 'boardroom config saved', ['by' => $who]);
            } catch (Throwable $e) {
                $err = t('boardroom.err_save', ['msg' => $e->getMessage()]);
                GameLog::error('admin/template_editor', 'save_boardroom failed', $e);
            }

        } elseif ($action === 'remove_boardroom_image') {
            $db->prepare("UPDATE boardroom_config SET `value`='' WHERE `key`='header_image'")->execute();
            $msg = t('admin.template_editor.br_msg_img_removed');
            GameLog::info('admin/template_editor', 'boardroom header image removed', ['by' => $who]);

        } elseif ($action === 'upload_bg_chunk') {
 // Chunked base64 upload each chunk is small, avoids post_max_size limit
 // Flush ALL previous output (PHP warnings, notices) before JSON
            while (ob_get_level() > 0) { ob_end_clean(); }
            header('Content-Type: application/json; charset=utf-8');
            try {
                $bgName      = preg_replace('/[^a-z0-9_]/', '', $_POST['bg_name'] ?? '');
                $mime        = trim($_POST['bg_file_mime'] ?? '');
                $chunkIdx    = (int)($_POST['chunk_index']   ?? 0);
                $totalChunks = (int)($_POST['total_chunks']  ?? 1);
                $chunkData   = $_POST['chunk_data'] ?? '';
                $sessId      = session_id() ?: md5(uniqid('', true));

                if (!$bgName)    { echo json_encode(['ok' => false, 'err' => 'Brak nazwy pliku.']);    exit; }
                if (!$chunkData) { echo json_encode(['ok' => false, 'err' => 'Brak danych chunka.']);  exit; }

 // Uywamy assets/images/boardroom/ tam na pewno s uprawnienia
                $chunkDir = __DIR__ . '/../assets/images/boardroom/';
                if (!is_dir($chunkDir)) mkdir($chunkDir, 0755, true);

                $chunkFile = $chunkDir . '.chunk_' . $sessId . '_' . $bgName . '_' . $chunkIdx;
                if (file_put_contents($chunkFile, $chunkData) === false) {
                    echo json_encode(['ok' => false, 'err' => 'Bd zapisu chunka. Sprawd uprawnienia /assets/images/boardroom/.']);
                    exit;
                }

 // All chunks received assemble
                if ($chunkIdx + 1 >= $totalChunks) {
                    $fullB64 = '';
                    for ($i = 0; $i < $totalChunks; $i++) {
                        $cf = $chunkDir . '.chunk_' . $sessId . '_' . $bgName . '_' . $i;
                        if (!file_exists($cf)) {
                            echo json_encode(['ok' => false, 'err' => 'Brakuje chunka #' . $i . '. Sprbuj ponownie.']);
                            exit;
                        }
                        $fullB64 .= file_get_contents($cf);
                        unlink($cf);
                    }

                    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                    if (!in_array($mime, $allowedMimes)) {
                        echo json_encode(['ok' => false, 'err' => 'Niedozwolony format pliku.']);
                        exit;
                    }

                    $decoded = base64_decode($fullB64, true);
                    if ($decoded === false) {
                        echo json_encode(['ok' => false, 'err' => 'Bd dekodowania base64.']);
                        exit;
                    }
                    if (strlen($decoded) > 20 * 1024 * 1024) {
                        echo json_encode(['ok' => false, 'err' => 'Plik przekracza 20 MB.']);
                        exit;
                    }

                    $dest = __DIR__ . '/../assets/images/boardroom_bg_' . $bgName . '.png';
                    if (file_put_contents($dest, $decoded) !== false) {
                        GameLog::info('admin/template_editor', 'boardroom bg uploaded (chunked)', ['name' => $bgName, 'by' => $who]);
                        echo json_encode(['ok' => true, 'done' => true, 'msg' => 'To zapisane: boardroom_bg_' . $bgName . '.png']);
                    } else {
                        echo json_encode(['ok' => false, 'err' => 'Bd zapisu pliku. Sprawd uprawnienia /assets/images/.']);
                    }
                } else {
                    echo json_encode(['ok' => true, 'done' => false, 'chunk' => $chunkIdx]);
                }
            } catch (Throwable $e) {
                GameLog::error('admin/template_editor', 'upload_bg_chunk failed', $e);
                echo json_encode(['ok' => false, 'err' => 'Bd serwera: ' . $e->getMessage()]);
            }
            exit;

        } elseif ($action === 'save_boardroom_bg') {
 // Fallback (unused chunked AJAX is primary path)
            $err = 'Uyj przycisku Zapisz to" w panelu upload.';

        } elseif ($action === 'delete_boardroom_bg') {
            $bgName = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['bg_name'] ?? '');
            if ($bgName) {
                $path = __DIR__ . '/../assets/images/boardroom_bg_' . $bgName . '.png';
                if (file_exists($path)) {
                    unlink($path);
                    $msg = t('admin.template_editor.br_bg_msg_deleted', ['name' => $bgName]);
                    GameLog::info('admin/template_editor', 'boardroom bg deleted', ['name' => $bgName, 'by' => $who]);
                }
            }
        }
    }
}

// Dane do widoku 
$cfgRows     = $db->query("SELECT `key`, `value` FROM site_config")->fetchAll(PDO::FETCH_KEY_PAIR);
$navItems    = $db->query("SELECT * FROM nav_items WHERE location='header'  ORDER BY sort_order ASC, id ASC")->fetchAll();
$footerItems = $db->query("SELECT * FROM nav_items WHERE location='footer'  ORDER BY sort_order ASC, id ASC")->fetchAll();
$actionItems = $db->query("SELECT * FROM nav_items WHERE location='actions' ORDER BY sort_order ASC, id ASC")->fetchAll();

$editNavId = (int)($_GET['nav'] ?? 0);
$editNav   = null;
foreach (array_merge($navItems, $footerItems, $actionItems) as $n) {
    if ((int)$n['id'] === $editNavId) { $editNav = $n; break; }
}

// Boardroom config
$brConfig = [];
try {
    $brConfig = $db->query("SELECT `key`,`value` FROM boardroom_config")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) {}
$brFooterLinks = json_decode($brConfig['footer_links'] ?? '[]', true) ?: [];

// Scene background combinations matrix
// Roles in scene order (matching boardroom-dynamic.js seatPositions)
$brSceneRoles = [
    'hr'        => 'HR',
    'tech'      => 'Technical',
    'finance'   => 'Finance',
    'legal'     => 'Legal',
    'logistics' => 'Logistics',
];
$brGenders = ['M', 'F'];
$brImagesDir = __DIR__ . '/../assets/images/';

// Build all meaningful combinations: single + growing combos up to all 5
$brBgMatrix = [];

// Default (empty)
$brBgMatrix[] = [
    'name'    => '',
    'label'   => 'Domylne (pusty zarzd)',
    'file'    => 'boardroom_bg.png',
    'exists'  => file_exists($brImagesDir . 'boardroom_bg.png'),
    'preview' => '/assets/images/boardroom_bg.png',
];

// Single-role combos (each role each gender)
foreach ($brSceneRoles as $code => $label) {
    foreach ($brGenders as $g) {
        $name  = $code . '_' . $g;
        $fname = 'boardroom_bg_' . $name . '.png';
        $brBgMatrix[] = [
            'name'    => $name,
            'label'   => $label . ' (' . $g . ')',
            'file'    => $fname,
            'exists'  => file_exists($brImagesDir . $fname),
            'preview' => '/assets/images/' . $fname,
        ];
    }
 // Without gender fallback
    $fname = 'boardroom_bg_' . $code . '.png';
    $brBgMatrix[] = [
        'name'    => $code,
        'label'   => $label . ' (bez pci)',
        'file'    => $fname,
        'exists'  => file_exists($brImagesDir . $fname),
        'preview' => '/assets/images/' . $fname,
    ];
}

// Multi-role combos: build from existing files in assets/images/
$existingBgs = [];
foreach (glob($brImagesDir . 'boardroom_bg_*.png') as $f) {
    $existingBgs[] = basename($f);
}
// Add any existing files not already in matrix
$matrixFiles = array_column($brBgMatrix, 'file');
foreach ($existingBgs as $fname) {
    if (!in_array($fname, $matrixFiles)) {
        $name = str_replace(['boardroom_bg_', '.png'], '', $fname);
        $brBgMatrix[] = [
            'name'    => $name,
            'label'   => $name,
            'file'    => $fname,
            'exists'  => true,
            'preview' => '/assets/images/' . $fname,
        ];
    }
}

$pageTitle = t('admin.template_editor.page_title');
$viewData = [
    'msg'            => $msg,
    'err'            => $err,
    'cfgRows'        => $cfgRows,
    'navItems'       => $navItems,
    'footerItems'    => $footerItems,
    'actionItems'    => $actionItems,
    'editNav'        => $editNav,
    'editNavId'      => $editNavId,
    'activeTab'      => $_GET['tab'] ?? 'nav',
    'brConfig'       => $brConfig,
    'brFooterLinks'  => $brFooterLinks,
    'brBgMatrix'     => $brBgMatrix,
    'brSceneRoles'   => $brSceneRoles,
];
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/template_editor/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/template_editor.php', 'Unhandled exception', $e);
    if (!headers_sent()) http_response_code(500);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/template_editor.php', $_codexGuardStart);
}
