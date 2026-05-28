<?php
$_codexGuardStart = class_exists('GameLog', false) ? GameLog::pageStart('admin/financial-crisis.php') : microtime(true);
try {

require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db  = Database::getInstance()->getConnection();
$msg = '';
$err = '';

//  Obsługa POST 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) 
        die('<p class="alert alert-error">' . t('common.csrf_error') . '</p>');
    $action = $_POST['action'] ?? '';

    if ($action === 'reset_crisis') {
        $pid = (int)($_POST['player_id'] ?? 0);
        if ($pid > 0) {
            $db->prepare("UPDATE players SET financial_state='normal', crisis_ticks=0 WHERE id=?")
               ->execute([$pid]);
            AdminLog::log('crisis_reset', t('admin.crisis.log_reset', ['id' => $pid]), $pid, 'player');
            $msg = t('admin.crisis.msg_reset', ['id' => $pid]);
        }
    }

    if ($action === 'force_bankruptcy') {
        $pid = (int)($_POST['player_id'] ?? 0);
        if ($pid > 0) {
            require_once __DIR__ . '/../src/BankruptcyService.php';
            try {
                $bk = new BankruptcyService($pid);
                $bk->ensureRecoveryMode();
                AdminLog::log('force_bankruptcy', t('admin.crisis.log_bankruptcy', ['id' => $pid]), $pid, 'player');
                $msg = t('admin.crisis.msg_bankruptcy', ['id' => $pid]);
            } catch (Throwable $e) {
                $err = t('common.error_prefix') . $e->getMessage();
            }
        }
    }

    if ($action === 'save_config') {
        $keys = [
            'warning_cash_threshold'   => t('admin.crisis.cfg_warning_threshold'),
            'crisis_ticks_base'        => t('admin.crisis.cfg_ticks_base'),
            'score_bonus_threshold'    => t('admin.crisis.cfg_score_bonus'),
            'score_penalty_threshold'  => t('admin.crisis.cfg_score_penalty'),
        ];
        foreach ($keys as $key => $label) {
            $val = (float)($_POST[$key] ?? 0);
            $db->prepare("INSERT INTO well_config (`key`, value, label, category)
                VALUES (?, ?, ?, 'crisis')
                ON DUPLICATE KEY UPDATE value=VALUES(value)")
               ->execute([$key, $val, $label]);
        }
        AdminLog::log('crisis_config_save', t('admin.crisis.log_config_saved'));
        $msg = t('admin.crisis.msg_config_saved');
    }
}

//  Pobierz dane 

// Firmy w kryzysie/ostrzeżeniu
$crisisPlayers = $db->query("
    SELECT
        p.id, COALESCE(NULLIF(p.company_name,''), p.username) AS company,
        p.cash, p.financial_state, p.crisis_ticks, p.credit_score,
        p.bankruptcy_status,
        (SELECT COUNT(*) FROM wells WHERE player_id=p.id AND status NOT IN ('seized','blowout')) AS active_wells
    FROM players p
    WHERE p.financial_state IN ('warning','crisis')
      AND p.status != 'bankrupt'
    ORDER BY p.financial_state DESC, p.crisis_ticks DESC
")->fetchAll();

// Statystyki globalne
$stats = $db->query("
    SELECT
        SUM(financial_state='warning') AS warning_count,
        SUM(financial_state='crisis')  AS crisis_count,
        SUM(status='bankrupt')         AS bankrupt_count,
        COUNT(*)                       AS total_players
    FROM players
    WHERE status != 'completed'
")->fetch();

// Historia niedawnych bankructw
$recentBankrupt = [];
try {
    $recentBankrupt = $db->query("
        SELECT p.id, COALESCE(NULLIF(p.company_name,''), p.username) AS company,
               p.bankruptcy_at, p.credit_score
        FROM players p
        WHERE p.status = 'bankrupt' OR p.bankruptcy_status IN ('liquidation','restructuring')
        ORDER BY p.bankruptcy_at DESC LIMIT 10
    ")->fetchAll();
} catch (Throwable $e) {}

// Konfiguracja
$config = [];
try {
    $cfgRows = $db->query("SELECT `key`, value FROM well_config WHERE category='crisis'")->fetchAll();
    foreach ($cfgRows as $r) $config[$r['key']] = (float)$r['value'];
} catch (Throwable $e) {}
$config += [
    'warning_cash_threshold'  => 10000,
    'crisis_ticks_base'       => 6,
    'score_bonus_threshold'   => 1000,
    'score_penalty_threshold' => 300,
];

$configFields = [
    ['warning_cash_threshold',  t('admin.crisis.cfg_warning_threshold'), t('admin.crisis.cfg_warning_threshold_desc'), '100'],
    ['crisis_ticks_base',       t('admin.crisis.cfg_ticks_base'),        t('admin.crisis.cfg_ticks_base_desc'),        '1'],
    ['score_bonus_threshold',   t('admin.crisis.cfg_score_bonus'),       t('admin.crisis.cfg_score_bonus_desc'),       '100'],
    ['score_penalty_threshold', t('admin.crisis.cfg_score_penalty'),     t('admin.crisis.cfg_score_penalty_desc'),     '100'],
];

$viewData = [
    'msg'           => $msg,
    'err'           => $err,
    'stats'         => $stats,
    'crisisPlayers' => $crisisPlayers,
    'recentBankrupt'=> $recentBankrupt,
    'config'        => $config,
    'configFields'  => $configFields,
];

$pageTitle = t('admin.crisis.title');
require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/financial-crisis/main.php';
require_once __DIR__ . '/partials/footer.php';

} catch (Throwable $e) {
    if (class_exists('GameLog', false)) GameLog::error('admin/financial-crisis.php', t('common.unhandled_exception'), $e);
    echo t('common.app_error');
} finally {
    if (class_exists('GameLog', false)) GameLog::pageEnd('admin/financial-crisis.php', $_codexGuardStart);
}