<?php
// Shared upload validation; wspolna walidacja uploadu.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (
    ($_GET['ajax_upload'] ?? '') === '1' || ($_POST['action'] ?? '') === 'upload_bg_chunk'
)) {
    require_once __DIR__ . '/../src/BoardroomUploadService.php';
    require_once __DIR__ . '/../src/i18n.php';
    $result = ['ok' => false, 'err' => tPlain('admin.template_editor.upload_server')];
    try {
        require_once __DIR__ . '/init.php';
        if (!AdminAuth::isLoggedIn() || empty($_SESSION['admin_id']) || session_id() === '') {
            throw new DomainException('auth');
        }
        $base64 = ($_POST['action'] ?? '') === 'upload_bg_chunk';
        $input = $base64 ? $_POST : $_GET;
        if (!is_string($input['csrf_token'] ?? null) || !CSRF::validateToken($input['csrf_token'])) {
            throw new DomainException('csrf');
        }
        $data = $base64 ? ($input['chunk_data'] ?? null)
            : file_get_contents('php://input', false, null, 0, BoardroomUploadService::MAX_CHUNK + 1);
        if (!is_string($data)) throw new DomainException('image');
        // Legacy base64 clients have no ID; stare klienty base64 nie przesylaja ID.
        if ($base64 && !array_key_exists('upload_id', $input) && is_string($input['bg_name'] ?? null)) {
            $legacyKey = hash('sha256', $input['bg_name']);
            if (($input['chunk_index'] ?? null) === '0' || ($input['chunk_index'] ?? null) === 0) {
                $_SESSION['boardroom_upload_ids'][$legacyKey] = bin2hex(random_bytes(16));
            }
            $input['upload_id'] = $_SESSION['boardroom_upload_ids'][$legacyKey] ?? '';
        }
        $service = new BoardroomUploadService(
            sys_get_temp_dir() . '/oilempire-boardroom-' . hash('sha256', dirname(__DIR__)),
            __DIR__ . '/../assets/images'
        );
        $result = $service->receive((string)$_SESSION['admin_id'] . ':' . session_id(), $input, $data, $base64);
        if ($result['done']) {
            $result['msg'] = tPlain('admin.template_editor.br_bg_msg_saved', ['name' => $input['bg_name']]);
            AdminLog::log('BOARDROOM_BACKGROUND_UPLOAD', 'Boardroom background published as PNG: ' . $input['bg_name'], null, 'template');
        }
    } catch (DomainException $e) {
        $code = in_array($e->getMessage(), ['metadata', 'size', 'image', 'dimensions', 'missing', 'retry', 'expired', 'auth', 'csrf'], true)
            ? $e->getMessage() : 'server';
        $result = ['ok' => false, 'err' => tPlain('admin.template_editor.upload_' . $code)];
        if (class_exists('GameLog', false)) GameLog::info('admin/template_editor', 'Boardroom upload rejected', ['reason' => $code]);
    } catch (Throwable $e) {
        $result = ['ok' => false, 'err' => tPlain('admin.template_editor.upload_server')];
        if (class_exists('GameLog', false)) GameLog::info('admin/template_editor', 'Boardroom upload failed', ['exception' => get_class($e)]);
    }
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
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
        `lang_key`   VARCHAR(64) NOT NULL DEFAULT '',
        `url_key`    VARCHAR(64) NOT NULL,
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
    try { Database::addColumnIfMissing('nav_items', 'lang_key', "VARCHAR(64) NOT NULL DEFAULT '' AFTER `label`"); } catch (Throwable $__e) {}

    // Jednorazowe usuniecie kolumny icon (zbyt mala dla SVG, zastapiona SVG z plikow).
    // One-time removal of the icon column (too small for SVG, replaced by file-based SVG).
    $navIconColDropped = (string)($db->query("SELECT `value` FROM site_config WHERE `key`='nav_icon_col_dropped' LIMIT 1")->fetchColumn() ?: '0');
    if ($navIconColDropped !== '1') {
        try { $db->exec("ALTER TABLE nav_items DROP COLUMN IF EXISTS `icon`"); } catch (Throwable $__e) {}
        $db->prepare("INSERT IGNORE INTO site_config (`key`, `value`) VALUES ('nav_icon_col_dropped', '1')")->execute();
        $db->prepare("UPDATE site_config SET `value`='1' WHERE `key`='nav_icon_col_dropped'")->execute();
    }

    $defaults = [
        ['site_name',    'OilCorp'],
        ['site_tagline', 'Strategiczna gra naftowa'],
        ['footer_text',  '&copy; {year} OilCorp. Wszystkie prawa zastrzeone.'],
        ['footer_js',    '/assets/js/game.js'],
        ['nav_items_seeded', '0'],
        ['legal_nav_ensured', '0'],
        ['nav_icon_col_dropped', '0'],
        ['nav_lang_key_seeded', '0'],
    ];
    $ins = $db->prepare("INSERT IGNORE INTO site_config (`key`, `value`) VALUES (?, ?)");
    foreach ($defaults as [$k, $v]) { $ins->execute([$k, $v]); }

    $navSeeded = (string)($db->query("SELECT `value` FROM site_config WHERE `key`='nav_items_seeded' LIMIT 1")->fetchColumn() ?: '0');
    $shouldSeedNavItems = ($navSeeded !== '1')
        && ((int)$db->query("SELECT COUNT(*) FROM nav_items")->fetchColumn() === 0);

    if ($shouldSeedNavItems) {
        $navDefaults = [
            // label,       url_key,     lang_key,         sort, active, css_class,   location
            ['Dashboard', 'home',      'nav.home',      10, 1, '', 'header'],
            ['Mapa',      'map',       'nav.map',       20, 1, '', 'header'],
            ['Rynek',     'market',    'nav.market',    30, 1, '', 'header'],
            ['Bank',      'bank',      'nav.bank',      40, 1, '', 'header'],
            ['Zarząd',    'hr',        'nav.hr',        50, 1, '', 'header'],
            ['Technika',  'technical', 'nav.technical', 60, 1, '', 'header'],
            ['Pomoc',     'help',      'nav.help',      70, 1, '', 'header'],
            ['Wyloguj',   'logout',    'nav.logout',    99, 1, 'btn-danger', 'header'],
        ];
        $navIns = $db->prepare("INSERT INTO nav_items (label,url_key,lang_key,sort_order,active,css_class,location) VALUES (?,?,?,?,?,?,?)");
        foreach ($navDefaults as $row) { $navIns->execute($row); }
    }

    if ($shouldSeedNavItems) {
        $actionsDefaults = [
            // label,            url_key,  lang_key,                sort, active, css_class,       location
            ['Rynek ropy',    'market', 'nav.action.market',    10, 1, 'btn-success',   'actions'],
            ['Kup odwiert',   'map',    'nav.action.map',       20, 1, 'btn-info',      'actions'],
            ['Zarząd / HR',   'hr',     'nav.action.hr',        30, 1, 'btn-secondary', 'actions'],
            ['Bank',          'bank',   'nav.action.bank',      40, 1, 'btn-secondary', 'actions'],
            ['Dział prawny',  'legal',  'nav.action.legal',     50, 1, 'btn-secondary', 'actions'],
        ];
        $actIns = $db->prepare("INSERT INTO nav_items (label,url_key,lang_key,sort_order,active,css_class,location) VALUES (?,?,?,?,?,?,?)");
        foreach ($actionsDefaults as $row) { $actIns->execute($row); }
    }

    if ($shouldSeedNavItems) {
        $footerDefaults = [
            // label,        url_key,      lang_key,                  sort, active, css_class, location
            ['Regulamin',  '/regulamin', 'nav.footer.regulamin', 10, 1, '', 'footer'],
            ['Polityka',   '/polityka',  'nav.footer.polityka',  20, 1, '', 'footer'],
            ['Kontakt',    '/kontakt',   'nav.footer.kontakt',   30, 1, '', 'footer'],
            ['Instrukcja', 'help',       'nav.footer.help',      40, 1, '', 'footer'],
        ];
        $footerIns = $db->prepare("INSERT INTO nav_items (label,url_key,lang_key,sort_order,active,css_class,location) VALUES (?,?,?,?,?,?,?)");
        foreach ($footerDefaults as $row) { $footerIns->execute($row); }
        $db->prepare("UPDATE site_config SET `value`='1' WHERE `key`='nav_items_seeded'")->execute();
    }

    // Jednorazowe domniemanie pozycji "Dzia prawny" dla istniejcych instalacji,
    // gdzie nawigacja zostaa zaseedowana ZANIM dzia prawny powsta. Wykonuje si
    // raz (flaga legal_nav_ensured) i nie nadpisuje rcznych decyzji admina.
    $legalNavEnsured = (string)($db->query("SELECT `value` FROM site_config WHERE `key`='legal_nav_ensured' LIMIT 1")->fetchColumn() ?: '0');
    if ($legalNavEnsured !== '1') {
        $db->exec(
            "INSERT INTO nav_items (label, url_key, lang_key, sort_order, active, css_class, location)
             SELECT 'Dział prawny', 'legal', 'nav.action.legal', 50, 1, 'btn-secondary', 'actions' FROM DUAL
             WHERE NOT EXISTS (
                 SELECT 1 FROM nav_items WHERE url_key = 'legal' AND location = 'actions'
             )"
        );
        $db->prepare("UPDATE site_config SET `value`='1' WHERE `key`='legal_nav_ensured'")->execute();
    }

    // Jednorazowe uzupelnienie lang_key dla istniejacych wierszy (jesli puste).
    // One-time backfill of lang_key for existing rows that were seeded before this column existed.
    $navLangKeySeeded = (string)($db->query("SELECT `value` FROM site_config WHERE `key`='nav_lang_key_seeded' LIMIT 1")->fetchColumn() ?: '0');
    if ($navLangKeySeeded !== '1') {
        $keyMap = [
            // header
            'home'      => 'nav.home',
            'map'       => 'nav.map',
            'market'    => 'nav.market',
            'bank'      => 'nav.bank',
            'hr'        => 'nav.hr',
            'technical' => 'nav.technical',
            'help'      => 'nav.help',
            'logout'    => 'nav.logout',
            'legal'     => 'nav.legal',
            'logistics' => 'nav.logistics',
            'finance'   => 'nav.finance',
            'sabotage'  => 'nav.sabotage',
        ];
        $actionKeyMap = [
            'market'    => 'nav.action.market',
            'map'       => 'nav.action.map',
            'hr'        => 'nav.action.hr',
            'bank'      => 'nav.action.bank',
            'legal'     => 'nav.action.legal',
            'technical' => 'nav.action.technical',
            'logistics' => 'nav.action.logistics',
            'finance'   => 'nav.action.finance',
            'sabotage'  => 'nav.action.sabotage',
        ];
        $footerKeyMap = [
            '/regulamin' => 'nav.footer.regulamin',
            '/polityka'  => 'nav.footer.polityka',
            '/kontakt'   => 'nav.footer.kontakt',
            'help'       => 'nav.footer.help',
        ];
        try {
            $upd = $db->prepare("UPDATE nav_items SET lang_key=? WHERE url_key=? AND location=? AND (lang_key IS NULL OR lang_key='')");
            foreach ($keyMap as $urlKey => $lk) {
                $upd->execute([$lk, $urlKey, 'header']);
            }
            foreach ($actionKeyMap as $urlKey => $lk) {
                $upd->execute([$lk, $urlKey, 'actions']);
            }
            foreach ($footerKeyMap as $urlKey => $lk) {
                $upd->execute([$lk, $urlKey, 'footer']);
            }
        } catch (Throwable $__e) {}
        $db->prepare("INSERT IGNORE INTO site_config (`key`, `value`) VALUES ('nav_lang_key_seeded', '1')")->execute();
        $db->prepare("UPDATE site_config SET `value`='1' WHERE `key`='nav_lang_key_seeded'")->execute();
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
            $langKey  = trim($_POST['lang_key'] ?? '');
            $sort     = (int)($_POST['sort_order'] ?? 0);
            $active   = isset($_POST['active']) ? 1 : 0;
            $cssClass = trim($_POST['css_class'] ?? '');
            if ($id > 0 && $label && $urlKey) {
                $db->prepare("UPDATE nav_items SET label=?,url_key=?,lang_key=?,sort_order=?,active=?,css_class=? WHERE id=?")
                   ->execute([$label, $urlKey, $langKey, $sort, $active, $cssClass, $id]);
                $msg = t('admin.template_editor.msg_nav_saved');
            } else {
                $err = t('admin.template_editor.err_invalid_data');
            }

        } elseif ($action === 'add_nav') {
            $label   = trim($_POST['new_label']    ?? '');
            $urlKey  = trim($_POST['new_url_key']  ?? '');
            $langKey = trim($_POST['new_lang_key'] ?? '');
            $loc     = in_array($_POST['new_location'] ?? '', ['header','footer','actions']) ? $_POST['new_location'] : 'header';
            $sort    = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM nav_items WHERE location=" . $db->quote($loc))->fetchColumn();
            if ($label && $urlKey) {
                $db->prepare("INSERT INTO nav_items (label,url_key,lang_key,sort_order,active,css_class,location) VALUES (?,?,?,?,1,'',?)")
                   ->execute([$label, $urlKey, $langKey, $sort, $loc]);
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

                // Header image upload.
                // PL: Upload obrazka naglowka.
                $brUploadDir = __DIR__ . '/../assets/images/boardroom/';
                if (!is_dir($brUploadDir)) mkdir($brUploadDir, 0755, true);
                if (isset($_FILES['header_image']) && $_FILES['header_image']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['header_image'];
                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                    ];
                    $tmpPath = (string)($file['tmp_name'] ?? '');
                    $mime = is_file($tmpPath) ? (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath) : false;
                    $imageInfo = is_file($tmpPath) ? @getimagesize($tmpPath) : false;
                    if (!is_string($mime) || !isset($allowed[$mime]) || $imageInfo === false) {
                        $err = t('boardroom.err_img_format');
                    } elseif ($file['size'] > 5 * 1024 * 1024) {
                        $err = t('boardroom.err_img_size');
                    } else {
                        $fname = 'header_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
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
