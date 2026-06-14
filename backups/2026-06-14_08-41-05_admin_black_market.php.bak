<?php
require_once __DIR__ . '/init.php';
AdminAuth::requireLogin();

$db = Database::getInstance()->getConnection();
$bm = new BlackMarketService($db);

$pageTitle = t('black_market.admin_heading');
$success   = '';
$error     = '';

// POST: edycja black_score lub konfiguracji 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_score') {
        $pid   = (int)($_POST['player_id'] ?? 0);
        $score = (float)($_POST['black_score'] ?? 0);
        if ($pid > 0) {
            $bm->setPlayerBlackScore($pid, $score);
            AdminLog::log('black_market_score_update', "Player #$pid => score=$score");
            $success = t('black_market.admin_saved');
        }
    }

    if ($action === 'force_generate') {
        try {
            require_once __DIR__ . '/../src/BlackMarketService.php';
            $oilPriceRow = $db->query("SELECT oil_price FROM tick_stats WHERE oil_price IS NOT NULL ORDER BY id DESC LIMIT 1")->fetchColumn();
            $oilPrice    = $oilPriceRow !== false ? (float)$oilPriceRow : 500.0;

            $activePlayers = $db->query("
                SELECT id FROM players
                WHERE financial_state != 'crisis'
                AND id IN (SELECT DISTINCT player_id FROM wells WHERE status NOT IN ('seized','blowout','sold'))
            ")->fetchAll(PDO::FETCH_COLUMN);

            $generated = 0;
            foreach ($activePlayers as $pid) {
                $generated += $bm->generateOffers((int)$pid, $oilPrice);
            }
            AdminLog::log('black_market_force_generate', "Wygenerowano $generated ofert dla " . count($activePlayers) . " graczy");
            $success = "Wygenerowano $generated ofert dla " . count($activePlayers) . " graczy (cena ropy: $oilPrice PLN)";
        } catch (Throwable $e) {
            $error = 'Błąd generowania ofert: ' . $e->getMessage();
        }
    }

    if ($action === 'update_config') {
        $configKeys = [
            'bm_offer_interval_ticks', 'bm_score_decay_per_tick',
            'bm_min_bbl', 'bm_max_bbl',
            'bm_price_mult_min', 'bm_price_mult_max',
            'bm_base_risk_min', 'bm_base_risk_max',
            'bm_score_gain_min', 'bm_score_gain_max',
            'bm_penalty_low_pct', 'bm_penalty_mid_pct', 'bm_penalty_high_pct',
            'bm_offer_ttl_ticks_min', 'bm_offer_ttl_ticks_max',
            'credit_score_legal_recovery_rate',
        ];
        $stmt = $db->prepare("
            INSERT INTO well_config (`key`, `value`) VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE `value` = :v2
        ");
        foreach ($configKeys as $k) {
            if (isset($_POST[$k])) {
                $v = (string)(float)$_POST[$k];
                $stmt->execute([':k' => $k, ':v' => $v, ':v2' => $v]);
            }
        }
        AdminLog::log('black_market_config_update', 'Config keys updated');
        $success = t('black_market.admin_saved');
    }
}

// Dane 
$globalStats = $bm->getGlobalStats();
$playersData = $bm->getPlayersBlackMarketData();

$filterPid   = isset($_GET['player_id']) ? (int)$_GET['player_id'] : null;
$transactions = $bm->getAllTransactions(50, 0, $filterPid);

// Config values
$cfgKeys = [
    'bm_offer_interval_ticks' => 3, 'bm_score_decay_per_tick' => 0.5,
    'bm_min_bbl' => 200, 'bm_max_bbl' => 2000,
    'bm_price_mult_min' => 1.3, 'bm_price_mult_max' => 2.0,
    'bm_base_risk_min' => 15, 'bm_base_risk_max' => 40,
    'bm_score_gain_min' => 3, 'bm_score_gain_max' => 8,
    'bm_penalty_low_pct' => 7.5, 'bm_penalty_mid_pct' => 15, 'bm_penalty_high_pct' => 27.5,
    'bm_offer_ttl_ticks_min' => 6, 'bm_offer_ttl_ticks_max' => 18,
    'credit_score_legal_recovery_rate' => 0.1,
];
try {
    $keys = array_keys($cfgKeys);
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $cfgStmt = $db->prepare("SELECT `key`, `value` FROM well_config WHERE `key` IN ($placeholders)");
    $cfgStmt->execute($keys);
    foreach ($cfgStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cfgKeys[$row['key']] = (float)$row['value'];
    }
} catch (Throwable $e) {}

$csrfToken = CSRF::generateToken();

$cfgLabels = [
    'bm_offer_interval_ticks'          => t('black_market.cfg_offer_interval'),
    'bm_score_decay_per_tick'          => t('black_market.cfg_score_decay'),
    'bm_min_bbl'                       => t('black_market.cfg_min_bbl'),
    'bm_max_bbl'                       => t('black_market.cfg_max_bbl'),
    'bm_price_mult_min'                => t('black_market.cfg_price_mult_min'),
    'bm_price_mult_max'                => t('black_market.cfg_price_mult_max'),
    'bm_base_risk_min'                 => t('black_market.cfg_risk_min'),
    'bm_base_risk_max'                 => t('black_market.cfg_risk_max'),
    'bm_score_gain_min'                => t('black_market.cfg_score_gain_min'),
    'bm_score_gain_max'                => t('black_market.cfg_score_gain_max'),
    'bm_penalty_low_pct'               => t('black_market.cfg_penalty_low'),
    'bm_penalty_mid_pct'               => t('black_market.cfg_penalty_mid'),
    'bm_penalty_high_pct'              => t('black_market.cfg_penalty_high'),
    'bm_offer_ttl_ticks_min'           => t('black_market.cfg_ttl_min'),
    'bm_offer_ttl_ticks_max'           => t('black_market.cfg_ttl_max'),
    'credit_score_legal_recovery_rate' => t('black_market.cfg_credit_recovery'),
];

$viewData = [
    'success'     => $success,
    'error'       => $error,
    'globalStats' => $globalStats,
    'playersData' => $playersData,
    'transactions'=> $transactions,
    'filterPid'   => $filterPid,
    'cfgKeys'     => $cfgKeys,
    'cfgLabels'   => $cfgLabels,
    'csrfToken'   => $csrfToken,
];

require_once __DIR__ . '/partials/header.php';
require __DIR__ . '/../templates/views/admin/black_market/main.php';
require_once __DIR__ . '/partials/footer.php';
