<?php
/**
 * admin/logistics_hubs.php Panel zarzdzania systemowymi hubami logistycznymi.
 *
 * Logika podzielona na traity w src/AdminHub/:
 * - AdminHubPostActionsTrait obsuga POST
 * - AdminHubDataFetchTrait pobieranie danych
 * - AdminHubConfigFieldTrait renderowanie pl konfiguracji
 *
 * Widok: templates/views/admin/logistics/main.php
 */
require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

require_once dirname(__DIR__) . '/src/AdminHub/PostActionsTrait.php';
require_once dirname(__DIR__) . '/src/AdminHub/DataFetchTrait.php';
require_once dirname(__DIR__) . '/src/AdminHub/ConfigFieldTrait.php';

$hub_admin = new class {
    use AdminHubPostActionsTrait;
    use AdminHubDataFetchTrait;
    use AdminHubConfigFieldTrait;
};

$db     = Database::getInstance()->getConnection();
$csrf   = CSRF::generateToken();
$msg    = '';
$msgErr = false;

// Obsuga POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $msg    = t('admin.logistics.err_csrf');
        $msgErr = true;
    } else {
        $action  = $_POST['action'] ?? '';
        $adminId = (int) AdminAuth::getAdminId();
        $hubSvc  = new HubService($db);

        $result  = $hub_admin->handlePostAction($db, $hubSvc, $action, $adminId);
        $msg     = $result['msg'];
        $msgErr  = $result['err'];
    }

    $_SESSION['admin_hub_msg']     = $msg;
    $_SESSION['admin_hub_msg_err'] = $msgErr;
    $anchor = (($_POST['action'] ?? '') === 'save_config') ? '#hub-config-section' : '';
    header('Location: /admin/logistics_hubs.php' . (isset($_GET['hub_id']) ? '?hub_id=' . (int) $_GET['hub_id'] : '') . $anchor);
    exit;
}

// Flash message 
if (!empty($_SESSION['admin_hub_msg'])) {
    $msg    = $_SESSION['admin_hub_msg'];
    $msgErr = (bool) ($_SESSION['admin_hub_msg_err'] ?? false);
    unset($_SESSION['admin_hub_msg'], $_SESSION['admin_hub_msg_err']);
}

// Dane do widoku 
$hubSvc       = new HubService($db);
$filterStatus = trim($_GET['status']     ?? '');
$filterRegion = (int) ($_GET['region_id'] ?? 0);
$filterCond   = trim($_GET['cond']        ?? '');
$viewHubId    = (int) ($_GET['hub_id']    ?? 0);
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 5;

$hubConfigMap = $hub_admin->loadHubConfigMap($db);
$cfgGet       = fn(string $g, string $k, string $d = '0') => $hubConfigMap[$g][$k] ?? $d;
$allRegions   = $hub_admin->loadAllRegions($db);

$allHubs = $hub_admin->filterHubs($hubSvc->getAllHubs(''), $filterStatus, $filterRegion);
if ($filterCond !== '') {
    $allHubs = array_values(array_filter($allHubs, function ($h) use ($filterCond) {
        $c = (float) $h['condition_pct'];
        return match ($filterCond) {
            'ok'       => $c >= 70.0,
            'warn'     => $c >= 50.0 && $c < 70.0,
            'bad'      => $c >= 30.0 && $c < 50.0,
            'critical' => $c <  30.0,
            default    => true,
        };
    }));
}

$hubStats    = $hub_admin->computeHubStats($allHubs);
$totalHubs   = $hubStats['total'];
$activeCount = $hubStats['active'];
$pausedCount = $hubStats['paused'];

$hubsByRegion = [];
foreach ($allHubs as $h) {
    $rName = $h['region_name'] ?? ('Region #' . $h['region_id']);
    $hubsByRegion[$rName][] = $h;
}
ksort($hubsByRegion);

$totalPages = max(1, (int) ceil(count($allHubs) / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;
$hubs       = array_slice($allHubs, $offset, $perPage);

$hubsPageByRegion = [];
foreach ($hubs as $h) {
    $rName = $h['region_name'] ?? ('Region #' . $h['region_id']);
    $hubsPageByRegion[$rName][] = $h;
}

$typeMap = [
    'small'  => t('admin.logistics.type_small'),
    'medium' => t('admin.logistics.type_medium'),
    'large'  => t('admin.logistics.type_large'),
];
$statusMap = [
    'active'     => t('admin.logistics.status_active'),
    'paused'     => t('admin.logistics.status_paused'),
    'damaged'    => t('admin.logistics.status_damaged'),
    'disabled'   => t('admin.logistics.status_disabled'),
    'building'   => t('admin.logistics.status_building'),
    'overloaded' => t('admin.logistics.status_overloaded'),
];
$modeMap = [
    'eco'      => t('admin.logistics.mode_eco'),
    'standard' => t('admin.logistics.mode_standard'),
    'max'      => t('admin.logistics.mode_max'),
];
$statusBadge = [
    'active'     => 'green',
    'paused'     => 'yellow',
    'damaged'    => 'red',
    'disabled'   => 'red',
    'building'   => 'blue',
    'overloaded' => 'orange',
];

$detail        = $hub_admin->loadHubDetail($hubSvc, $viewHubId);
$viewHub       = $detail['hub'];
$viewWells     = $detail['wells'];
$viewLastStats = $detail['lastStats'];

// Widok 
require __DIR__ . '/../templates/views/admin/logistics/main.php';
