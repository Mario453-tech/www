<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/balance.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db  = Database::getInstance()->getConnection();
$msg = '';
$err = '';

// Balance configuration keys stored in well_config.
// PL: Klucze konfiguracji balansu zapisane w well_config.
$BALANCE_KEYS = [
    'global_loss_multiplier'     => [t('admin.balance.key_loss'),        '1.0', t('admin.balance.hint_loss')],
    'global_incident_multiplier' => [t('admin.balance.key_incident'),    '1.0', t('admin.balance.hint_incident')],
    'global_disaster_multiplier' => [t('admin.balance.key_disaster'),    '1.0', t('admin.balance.hint_disaster')],
    'global_wear_multiplier'     => [t('admin.balance.key_wear'),        '1.0', t('admin.balance.hint_wear')],
    'global_degradation_mult'    => [t('admin.balance.key_degradation'), '1.0', t('admin.balance.hint_degradation')],
    'global_opex_multiplier'     => [t('admin.balance.key_opex'),        '1.0', t('admin.balance.hint_opex')],
    'global_production_mult'     => [t('admin.balance.key_production'),  '1.0', t('admin.balance.hint_production')],
    'global_tax_multiplier'      => [t('admin.finance.cfg_tax_label'),   '1.0', t('admin.finance.cfg_tax_desc')],
];

// Load current values from well_config.
// PL: Wczytaj aktualne wartosci z well_config.
$currentConfig = [];
try {
    $rows = $db->query("SELECT `key`, `value` FROM well_config")->fetchAll();
    foreach ($rows as $r) {
        $currentConfig[$r['key']] = $r['value'];
    }
} catch (Throwable $e) {}

// POST actions.
// PL: Akcje POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? ''))
        die('<p class="alert alert-error">' . t('common.csrf_error') . '</p>');

    $action = $_POST['action'] ?? '';

    if ($action === 'save_balance') {
        $changed = [];
        foreach (array_keys($BALANCE_KEYS) as $key) {
            if (!isset($_POST[$key])) continue;
            $val = max(0.1, min(10.0, (float)$_POST[$key]));
            try {
                $db->prepare("
                    INSERT INTO well_config (`key`, `value`, label, category)
                    VALUES (?, ?, ?, 'balance')
                    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
                ")->execute([$key, $val, $BALANCE_KEYS[$key][0]]);
                $currentConfig[$key] = $val;
                $changed[] = "{$key}={$val}";
            } catch (Throwable $e) {
                $err = t('admin.balance.err_save_key', ['key' => $key]) . ': ' . $e->getMessage();
                break;
            }
        }
        if (!$err) {
            AdminLog::log('balance_config_save', 'Balance panel: ' . implode(', ', $changed));
            $msg = t('admin.balance.msg_saved');
        }
    }

    elseif ($action === 'reset_balance') {
        foreach (array_keys($BALANCE_KEYS) as $key) {
            try {
                $db->prepare("
                    INSERT INTO well_config (`key`, `value`, label, category)
                    VALUES (?, 1.0, ?, 'balance')
                    ON DUPLICATE KEY UPDATE `value` = 1.0
                ")->execute([$key, $BALANCE_KEYS[$key][0]]);
                $currentConfig[$key] = 1.0;
            } catch (Throwable $e) {}
        }
        AdminLog::log('balance_config_reset', 'Reset balance panelu do 1.0');
        $msg = t('admin.balance.msg_reset');
    }

    elseif ($action === 'emergency_nerf') {
        $target = $_POST['nerf_target'] ?? '';
        $factor = max(0.1, min(2.0, (float)($_POST['nerf_factor'] ?? 1.0)));
        $nerfKeys = [
            'incidents' => ['global_incident_multiplier', 'global_disaster_multiplier'],
            'loss'      => ['global_loss_multiplier', 'global_opex_multiplier'],
            'all_risk'  => ['global_incident_multiplier', 'global_disaster_multiplier', 'global_wear_multiplier', 'global_degradation_mult'],
            'production'=> ['global_production_mult'],
            'tax'       => ['global_tax_multiplier'],
        ];
        if (isset($nerfKeys[$target])) {
            foreach ($nerfKeys[$target] as $key) {
                try {
                    $db->prepare("
                        INSERT INTO well_config (`key`, `value`, label, category)
                        VALUES (?, ?, ?, 'balance')
                        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
                    ")->execute([$key, $factor, $BALANCE_KEYS[$key][0] ?? $key]);
                    $currentConfig[$key] = $factor;
                } catch (Throwable $e) {}
            }
            AdminLog::log('balance_emergency_nerf', "Emergency: {$target}  factor={$factor}");
            $msg = t('admin.balance.msg_emergency', ['target' => $target, 'factor' => $factor]);
        }
    }
}

// Context statistics.
// PL: Statystyki kontekstu.
$market    = $db->query("SELECT current_price FROM market_state WHERE id = 1")->fetch();
$oilPrice  = (float)($market['current_price'] ?? 70);

$prodStats = $db->query("
    SELECT
        COUNT(*) AS active_wells,
        SUM(base_production_per_hour) AS total_base_prod,
        AVG(technical_condition) AS avg_condition,
        AVG(wear_level) AS avg_wear
    FROM wells WHERE status = 'active'
")->fetch();

$pipeStats = [];
try {
    $pipeStats = $db->query("
        SELECT
            AVG(CASE WHEN status IN ('active','degraded','leak','critical') THEN transport_loss END) AS avg_loss,
            AVG(CASE WHEN status IN ('active','degraded','leak','critical') THEN condition_pct END) AS avg_condition,
            SUM(CASE WHEN status IN ('active','degraded','leak','critical') THEN 1 ELSE 0 END) AS active_count,
            COUNT(*) AS total_count
        FROM well_pipelines
    ")->fetch();
} catch (Throwable $e) {
    try {
        $pipeStats = $db->query("
            SELECT
                AVG(CASE WHEN status='active' THEN transport_loss END) AS avg_loss,
                AVG(CASE WHEN status='active' THEN condition_pct END) AS avg_condition,
                SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_count,
                COUNT(*) AS total_count
            FROM pipelines
        ")->fetch();
    } catch (Throwable $fallbackError) {}
}

$activePlayerCount = (int)$db->query("SELECT COUNT(*) FROM players WHERE status = 'active'")->fetchColumn();

$productionMult = isset($currentConfig['global_production_mult'])
    ? max(0.1, min(10.0, (float)$currentConfig['global_production_mult']))
    : 1.0;

// Estimated revenue per player after the global production multiplier.
// PL: Szacunkowy przychod per gracz po globalnym mnozniku produkcji.
$avgProdPerPlayer = ($activePlayerCount > 0 && (float)($prodStats['total_base_prod'] ?? 0) > 0)
    ? (float)$prodStats['total_base_prod'] / $activePlayerCount
    : 0.0;
$estRevenuePerDay = $avgProdPerPlayer * $productionMult * $oilPrice * 24 * 0.7;

$viewData = [
    'msg'               => $msg,
    'err'               => $err,
    'oilPrice'          => $oilPrice,
    'activePlayerCount' => $activePlayerCount,
    'prodStats'         => $prodStats,
    'pipeStats'         => $pipeStats,
    'estRevenuePerDay'  => $estRevenuePerDay,
    'BALANCE_KEYS'      => $BALANCE_KEYS,
    'currentConfig'     => $currentConfig,
];

$pageTitle = t('admin.balance.title');
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/balance/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/balance.php', t('common.unhandled_exception'), $e);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/balance.php', $_codexGuardStart);
}
