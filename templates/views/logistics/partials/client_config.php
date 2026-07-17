<?php
$logisticsClientConfig = [];
require __DIR__ . '/hub_client_config.php';
require __DIR__ . '/protection_client_config.php';

$logisticsClientConfig['optimizer'] = [
    'api' => '/src/LogisticsApi.php',
    'csrf_token' => CSRF::generateToken(),
    'locale' => $currencyLocale,
    'currency' => $currencyLabel,
    'lang' => [
        'api_missing' => t('logistics_js.api_missing'),
        'loading' => t('logistics.loading'),
        'err' => t('logistics.err'),
        'optimizing' => t('logistics_js.optimizing'),
        'run' => t('logistics.run'),
        'cancel' => t('logistics.cancel'),
        'no_changes' => t('logistics.no_changes'),
        'changed_count' => t('logistics.changed_count'),
        'confirm_btn' => t('logistics.confirm_btn'),
        'label_mode' => t('logistics.label_mode'),
        'label_fee' => t('logistics.label_fee'),
        'type_rurociag' => t('logistics.type_rurociag'),
        'type_ciezarowki' => t('logistics.type_ciezarowki'),
        'type_tankowiec' => t('logistics.type_tankowiec'),
        'type_nieustawiony' => t('logistics.type_nieustawiony'),
        'label_loss' => t('logistics.label_loss'),
        'label_cost' => t('logistics.label_cost'),
        'label_eff' => t('logistics.label_eff'),
        'label_before' => t('logistics.label_before'),
        'label_after' => t('logistics.label_after'),
        'well_type_onshore' => t('logistics_js.well_type_onshore'),
        'well_type_offshore' => t('logistics_js.well_type_offshore'),
        'mode_balans' => t('logistics.mode_balans'),
        'mode_max_prod' => t('logistics.mode_maxprod'),
        'mode_min_cost' => t('logistics.mode_mincost'),
    ],
];

$logisticsClientConfig['pipeline'] = [
    'api' => '/src/PipelineApi.php',
    'csrf_token' => CSRF::generateToken(),
    'lang' => [
        'loading' => t('logistics.loading'),
        'err' => t('common.generic_error'),
        'action_error' => t('logistics.pipeline.action_error'),
        'label_cost' => t('logistics.pipeline.building_label_cost'),
        'label_hours' => t('logistics.pipeline.buy_label_hours'),
        'insufficient' => t('pipeline.err_insufficient_funds'),
        'already_exists' => t('pipeline.err_already_exists'),
        'ok_started' => t('pipeline.ok_build_started'),
        'buy_confirm_btn' => t('logistics.pipeline.buy_confirm_btn'),
        'confirm_header' => t('logistics.pipeline.confirm_header'),
        'confirm_btn' => t('logistics.pipeline.confirm_btn'),
        'back_btn' => t('logistics.pipeline.back_btn'),
        'confirm_default' => t('modal.confirm'),
        'repair_title' => t('logistics.pipeline.btn_repair'),
        'maintenance_title' => t('logistics.pipeline.btn_maintenance'),
        'toggle_title' => t('logistics.pipeline.btn_suspend') . ' / ' . t('logistics.pipeline.btn_resume'),
        'currency' => $currencyLabel,
        'locale' => $currencyLocale,
    ],
];

$logisticsClientJson = json_encode(
    $logisticsClientConfig,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);
?>
<script type="application/json" id="logistics-client-config"><?= $logisticsClientJson !== false ? $logisticsClientJson : '{}' ?></script>
