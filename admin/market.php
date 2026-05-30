<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/market.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db    = Database::getInstance()->getConnection();
$msg   = '';
$error = '';

$TREND_CATEGORIES = ['economic','political','environmental','technological','social','military'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        die('<p class="alert alert-error">' . t('common.csrf_error') . '</p>');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'set_price') {
        $p = (float)($_POST['price'] ?? 0);
        if ($p > 0) {
            $db->prepare("UPDATE market_state SET current_price = :p WHERE id = 1")->execute([':p' => $p]);
            AdminLog::log('market_price_set', "Manual price set to: {$p}$");
            $msg = t('admin.market.msg_price_set', ['price' => $p]);
        } else {
            $error = t('admin.market.err_price_zero');
        }
    }

    elseif ($action === 'set_multiplier') {
        $mult = max(0.1, min(5.0, (float)($_POST['volatility'] ?? 1.0)));
        $db->prepare("UPDATE market_state SET volatility = :v WHERE id = 1")->execute([':v' => $mult]);
        AdminLog::log('market_multiplier_set', "Volatility set to: {$mult}");
        $msg = t('admin.market.msg_multiplier_set', ['val' => $mult]);
    }

    elseif ($action === 'set_trend') {
        $trendId = (int)($_POST['trend_id'] ?? 0);
        $db->query("UPDATE market_trends SET active = FALSE");
        if ($trendId) {
            $db->prepare("UPDATE market_trends SET active = TRUE, activated_at = NOW() WHERE id = :id")
               ->execute([':id' => $trendId]);
            $nameStmt = $db->prepare("SELECT trend_name FROM market_trends WHERE id = :id");
            $nameStmt->execute([':id' => $trendId]);
            $trendName = $nameStmt->fetchColumn();
            AdminLog::log('trend_change', "Manual trend set to: {$trendName} (ID: {$trendId})");
            $msg = t('admin.market.msg_trend_activated', ['name' => $trendName]);
        } else {
            AdminLog::log('trend_change', 'All trends deactivated');
            $msg = t('admin.market.msg_trends_deactivated');
        }
    }

    elseif ($action === 'market_tick') {
        require_once __DIR__ . '/../src/MarketTick.php';
        require_once __DIR__ . '/../src/MarketTrend.php';
        $newPrice = (new MarketTick())->updatePrices((new MarketTrend())->getActiveTrend());
        AdminLog::log('market_tick', "Manual market tick forced. New price: {$newPrice}$");
        $msg = t('admin.market.msg_tick_done', ['price' => $newPrice]);
    }

    elseif ($action === 'add_trend') {
        $name     = trim($_POST['trend_name'] ?? '');
        $cat      = in_array($_POST['category'] ?? '', $TREND_CATEGORIES) ? $_POST['category'] : 'economic';
        $modifier = max(0.1, min(5.0,  (float)($_POST['price_modifier']  ?? 1.0)));
        $hours    = max(1,   min(8760, (int)($_POST['duration_hours']    ?? 8)));
        $tpl      = trim($_POST['message_template'] ?? '');
        if ($name === '') {
            $error = t('admin.market.err_trend_name_empty');
        } else {
            $db->prepare("INSERT INTO market_trends (trend_name, category, price_modifier, duration_hours, message_template) VALUES (?,?,?,?,?)")
               ->execute([$name, $cat, $modifier, $hours, $tpl]);
            AdminLog::log('trend_add', "Trend added: {$name}");
            $msg = t('admin.market.msg_trend_added', ['name' => $name]);
        }
    }

    elseif ($action === 'edit_trend') {
        $id       = (int)($_POST['trend_id'] ?? 0);
        $name     = trim($_POST['trend_name'] ?? '');
        $cat      = in_array($_POST['category'] ?? '', $TREND_CATEGORIES) ? $_POST['category'] : 'economic';
        $modifier = max(0.1, min(5.0,  (float)($_POST['price_modifier']  ?? 1.0)));
        $hours    = max(1,   min(8760, (int)($_POST['duration_hours']    ?? 8)));
        $tpl      = trim($_POST['message_template'] ?? '');
        if (!$id || $name === '') {
            $error = t('admin.market.err_trend_name_empty');
        } else {
            $db->prepare("UPDATE market_trends SET trend_name=?, category=?, price_modifier=?, duration_hours=?, message_template=? WHERE id=?")
               ->execute([$name, $cat, $modifier, $hours, $tpl, $id]);
            AdminLog::log('trend_edit', "Trend #{$id} updated: {$name}");
            $msg = t('admin.market.msg_trend_updated', ['name' => $name]);
        }
    }

    elseif ($action === 'delete_trend') {
        $id = (int)($_POST['trend_id'] ?? 0);
        if ($id) {
            $nameStmt = $db->prepare("SELECT trend_name FROM market_trends WHERE id=?");
            $nameStmt->execute([$id]);
            $delName = $nameStmt->fetchColumn() ?: "#{$id}";
            $db->prepare("DELETE FROM market_trends WHERE id=?")->execute([$id]);
            AdminLog::log('trend_delete', "Trend #{$id} deleted: {$delName}");
            $msg = t('admin.market.msg_trend_deleted', ['name' => $delName]);
        }
    }
}

// Dane rynku 
$market      = $db->query("SELECT * FROM market_state WHERE id = 1")->fetch();
$activeTrend = $db->query("
    SELECT * FROM market_trends
    WHERE active = TRUE AND activated_at IS NOT NULL
      AND activated_at > DATE_SUB(NOW(), INTERVAL duration_hours HOUR)
    ORDER BY activated_at DESC LIMIT 1
")->fetch();

$trendTimeLeft = 'ďż˝';
if ($activeTrend) {
    $expires = (new DateTime($activeTrend['activated_at']))->modify('+' . (int)$activeTrend['duration_hours'] . ' hours');
    $diff    = (new DateTime())->diff($expires);
    $trendTimeLeft = $diff->h . 'h ' . $diff->i . 'm';
}

// Paginacja i filtr trendw 
$perPage     = max(5, min(100, (int)($_GET['per_page'] ?? 10)));
$page        = max(1, (int)($_GET['page'] ?? 1));
$filterCat   = $_GET['cat'] ?? '';
$filterName  = trim($_GET['search'] ?? '');
$editId      = (int)($_GET['edit'] ?? 0);

$where  = [];
$params = [];
if ($filterCat && in_array($filterCat, $TREND_CATEGORIES)) {
    $where[]  = 'category = ?';
    $params[] = $filterCat;
}
if ($filterName !== '') {
    $where[]  = 'trend_name LIKE ?';
    $params[] = '%' . $filterName . '%';
}
$whereSQL = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$totalStmt = $db->prepare("SELECT COUNT(*) FROM market_trends {$whereSQL}");
$totalStmt->execute($params);
$totalTrends = (int)$totalStmt->fetchColumn();
$totalPages  = max(1, (int)ceil($totalTrends / $perPage));
$page        = min($page, $totalPages);
$offset      = ($page - 1) * $perPage;

$trendStmt = $db->prepare("SELECT * FROM market_trends {$whereSQL} ORDER BY id LIMIT ? OFFSET ?");
$trendStmt->bindValue(1, $perPage, PDO::PARAM_INT);
$trendStmt->bindValue(2, $offset,  PDO::PARAM_INT);
foreach ($params as $i => $v) {
 // Rebind named params (index offset +3 because LIMIT=1, OFFSET=2)
 // Already using positional append before LIMIT
}
// Re-execute with all params in correct order
$allParams = array_merge($params, [$perPage, $offset]);
$trendStmt2 = $db->prepare("SELECT * FROM market_trends {$whereSQL} ORDER BY id LIMIT ? OFFSET ?");
foreach ($allParams as $i => $v) {
    $type = ($i >= count($params)) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $trendStmt2->bindValue($i + 1, $v, $type);
}
$trendStmt2->execute();
$pagedTrends = $trendStmt2->fetchAll();

// All trends for "Aktywuj trend" select
$allTrends = $db->query("SELECT id, trend_name, price_modifier, duration_hours FROM market_trends ORDER BY id")->fetchAll();

// Trend do edycji
$editTrend = null;
if ($editId) {
    $es = $db->prepare("SELECT * FROM market_trends WHERE id=? LIMIT 1");
    $es->execute([$editId]);
    $editTrend = $es->fetch() ?: null;
}

$viewData = [
    'msg'             => $msg,
    'error'           => $error,
    'market'          => $market,
    'activeTrend'     => $activeTrend,
    'allTrends'       => $allTrends,
    'trendTimeLeft'   => $trendTimeLeft,
    'pagedTrends'     => $pagedTrends,
    'totalTrends'     => $totalTrends,
    'totalPages'      => $totalPages,
    'page'            => $page,
    'perPage'         => $perPage,
    'filterCat'       => $filterCat,
    'filterName'      => $filterName,
    'editId'          => $editId,
    'editTrend'       => $editTrend,
    'TREND_CATEGORIES'=> $TREND_CATEGORIES,
];

$pageTitle = t('admin.market.page_title');
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/market/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) {
        GameLog::error('admin/market.php', 'Unhandled exception', $e);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) {
        GameLog::pageEnd('admin/market.php', $_codexGuardStart);
    }
}
