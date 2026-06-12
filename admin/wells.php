<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/wells.php') : microtime(true);
try {

/**
 * admin/wells.php - Well management panel (GM tools + config)
 * admin/wells.php - Panel zarzadzania odwiertami (narzedzia GM + konfiguracja)
 */
require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();
require_once __DIR__ . '/../src/WellService.php';

$wellSvc = new WellService();
$db      = Database::getInstance()->getConnection();
$msg     = $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    $msg     = t('common.csrf_error');
    $msgType = 'error';
}

// --- POST: save global config params ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $msgType !== 'error' && isset($_POST['config'])) {
    $updated = 0;
    foreach ($_POST['config'] as $key => $val) {
        $val = str_replace([' ', ','], ['', '.'], $val);
        if (is_numeric($val) && $wellSvc->updateConfig($key, (float)$val)) $updated++;
    }
    $msg     = "Zapisano $updated parametrów.";
    $msgType = 'success';
}

// --- POST: GM full well edit ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $msgType !== 'error' && isset($_POST['gm_edit_well_id'])) {
    $editId = (int)$_POST['gm_edit_well_id'];
    if ($editId <= 0) {
        $msg     = 'Nieprawidłowe ID odwiertu.';
        $msgType = 'error';
    } else {
        $fields = [];
        $params = [];

 // Enum fields
        $enumDefs = [
            'status'          => ['active','paused_storage','paused_cash','paused_staff',
                                  'no_operator','no_technician','broken','blowout',
                                  'contaminated','seized','layer_switch','sold','servicing'],
            'transport_type'  => ['rurociag','ciezarowki','tankowiec'],
            'hub_outbound_transport_type' => ['nieustawiony','rurociag','ciezarowki','tankowiec'],
            'production_mode' => ['eco','normal','boost'],
            'equipment_tier'  => ['black_market','standard','premium'],
        ];
        foreach ($enumDefs as $col => $allowed) {
            if (isset($_POST[$col]) && in_array($_POST[$col], $allowed, true)) {
                $fields[] = "`$col` = ?";
                $params[] = $_POST[$col];
            }
        }

 // Numeric fields [type, min, max] (null = no limit)
        $numDefs = [
            'technical_condition'    => ['int',   0,   100],
            'wear_level'             => ['float', 0,   null],
            'base_production_per_hour' => ['float', 0, null],
            'upkeep_cost_per_hour'   => ['float', 0,   null],
            'transport_capacity_pct' => ['float', 0,   null],
            'transport_opex_pct'     => ['float', 0,   null],
            'pressure'               => ['float', 0,   2.0],
            'reservoir_remaining'    => ['float', 0,   null],
            'reservoir_max'          => ['float', 1,   null],
            'risk_level'             => ['int',   1,   10],
            'risk_score'             => ['float', 0,   100],
            'production_boost_pct'   => ['float', 0,   null],
        ];
        foreach ($numDefs as $col => [$type, $min, $max]) {
            if (isset($_POST[$col]) && $_POST[$col] !== '') {
                $raw = str_replace([' ', ','], ['', '.'], (string)$_POST[$col]);
                if (is_numeric($raw)) {
                    $v = $type === 'int' ? (int)$raw : (float)$raw;
                    if ($min !== null) $v = max($min, $v);
                    if ($max !== null) $v = min($max, $v);
                    $fields[] = "`$col` = ?";
                    $params[]  = $v;
                }
            }
        }

        if ($fields) {
            $params[] = $editId;
            $db->prepare('UPDATE wells SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
        }

 // Upgrade management: hidden sentinel ensures empty checkbox set = remove all
        if (isset($_POST['gm_upgrades_submitted'])) {
            $allowedUpgrades   = ['pump_electric', 'monitoring', 'water_injection'];
            $submittedUpgrades = array_values(array_filter(
                (array)($_POST['gm_upgrades'] ?? []),
                static fn($u) => in_array($u, $allowedUpgrades, true)
            ));

            $stmt = $db->prepare("SELECT upgrade_type FROM well_upgrades WHERE well_id = ?");
            $stmt->execute([$editId]);
            $currentUpgrades = array_column($stmt->fetchAll(), 'upgrade_type');

            foreach ($submittedUpgrades as $u) {
                if (!in_array($u, $currentUpgrades, true)) {
                    $db->prepare("INSERT INTO well_upgrades (well_id, upgrade_type, cost_paid) VALUES (?, ?, 0.00)")
                       ->execute([$editId, $u]);
                }
            }
            foreach ($currentUpgrades as $u) {
                if (!in_array($u, $submittedUpgrades, true)) {
                    $db->prepare("DELETE FROM well_upgrades WHERE well_id = ? AND upgrade_type = ?")
                       ->execute([$editId, $u]);
                }
            }
        }

        GameLog::info('admin/wells.php', 'GM well edit', [
            'well_id'  => $editId,
            'fields'   => array_map(fn($f) => preg_replace('/`([^`]+)`.*/','$1',$f), $fields),
            'admin_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
        $msg     = "Odwiert #$editId zaktualizowany przez GM.";
        $msgType = 'success';
    }
}

// --- Config data ---
$config  = $wellSvc->getAllConfig();
$grouped = [];
foreach ($config as $c) $grouped[$c['category']][] = $c;

// --- Player filter ---
$filterPlayerId = (int)($_GET['pid'] ?? 0);

// Players list for filter dropdown
$players = $db->query(
    "SELECT id, COALESCE(username, email) AS label FROM players ORDER BY label"
)->fetchAll();

// --- Hub assignments indexed by well_id ---
$hubByWell = [];
try {
    $hubRows = $db->query("
        SELECT a.well_id, h.id AS hub_id, h.name AS hub_name, h.status AS hub_status
          FROM logistics_hub_assignments a
          JOIN logistics_hubs h ON h.id = a.hub_id
         WHERE a.status = 'active'
    ")->fetchAll();
    foreach ($hubRows as $hr) {
        $hubByWell[(int)$hr['well_id']] = $hr;
    }
} catch (Throwable $e) {
    GameLog::error('admin/wells.php', 'Hub assignment query failed', $e);
}

// --- Wells query (with optional player filter) ---
$wellsSql = "
    SELECT w.*, p.email AS username,
           GROUP_CONCAT(wu.upgrade_type ORDER BY wu.upgrade_type SEPARATOR ',') AS upgrade_list
      FROM wells w
      JOIN players p ON w.player_id = p.id
      LEFT JOIN well_upgrades wu ON wu.well_id = w.id
";
if ($filterPlayerId > 0) {
    $wellsSql .= " WHERE w.player_id = " . (int)$filterPlayerId;
}
$wellsSql .= " GROUP BY w.id ORDER BY w.player_id, w.id";
$wells = $db->query($wellsSql)->fetchAll();

// --- Last events ---
$eventsWhere = $filterPlayerId > 0 ? "WHERE we.player_id = $filterPlayerId" : '';
$events = $db->query("
    SELECT we.*, p.email AS username
      FROM well_events we
      JOIN wells w   ON we.well_id    = w.id
      JOIN players p ON we.player_id  = p.id
    $eventsWhere
     ORDER BY we.created_at DESC
     LIMIT 100
")->fetchAll();

$viewData = [
    'msg'           => $msg,
    'msgType'       => $msgType,
    'grouped'       => $grouped,
    'wells'         => $wells,
    'events'        => $events,
    'players'       => $players,
    'filterPlayerId'=> $filterPlayerId,
    'hubByWell'     => $hubByWell,
];

$pageTitle = 'Odwierty — GM';
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/wells/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/wells.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) http_response_code(500);
    echo 'Wystapil blad aplikacji.';
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/wells.php', $_codexGuardStart);
    }
}
