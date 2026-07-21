<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= t('admin.logistics.title') ?></title>
<link rel="stylesheet" href="<?= asset('/assets/css/admin.css') ?>">
<link rel="stylesheet" href="<?= asset('/assets/css/admin_logistics.css') ?>">
<link rel="stylesheet" href="<?= asset('/assets/css/admin_hubs.css') ?>">
<script>window.ADMIN_LOGISTICS_LANG = { seed_confirm: <?= json_encode(tPlain('admin.logistics.seed_confirm'), JSON_UNESCAPED_UNICODE) ?> };</script>
</head>
<body class="admin-body">
<div class="admin-container">

<div class="admin-breadcrumb">
    <a href="/admin/index.php"> Panel admina</a> / <?= t('admin.logistics.breadcrumb') ?>
</div>

<h2> <?= t('admin.logistics.title') ?></h2>
<p class="c-muted"><?= t('admin.logistics.subtitle') ?></p>

<?php if ($msg): ?>
<div class="admin-alert admin-alert--<?= $msgErr ? 'danger' : 'success' ?>">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif ?>

<!--  Pasek statystyk  -->
<div class="stats-bar">
    <span><?= t('admin.logistics.stats_total') ?>: <strong><?= $totalHubs ?></strong></span>
    <span><?= t('admin.logistics.stats_active') ?>: <strong class="c-good"><?= $activeCount ?></strong></span>
    <span><?= t('admin.logistics.stats_paused') ?>: <strong class="c-warn"><?= $pausedCount ?></strong></span>
    <span><?= t('admin.logistics.stats_other') ?>: <strong><?= $totalHubs - $activeCount - $pausedCount ?></strong></span>
</div>

<!--  Weryfikacja ticku (OPEX, straty, odwierty bez huba)  -->
<?php
$tickStats     = $hub_admin->getTickVerificationStats($db);
$hasUnassigned = $tickStats['unassigned_wells'] > 0;
?>
<details class="admin-details admin-details--verify" <?= $hasUnassigned ? 'open' : '' ?>>
    <summary> <?= t('admin.logistics.tick_verify_title') ?></summary>
    <div class="verify-grid">
        <div class="verify-card <?= $hasUnassigned ? 'verify-card--warn' : '' ?>">
            <div class="verify-label"><?= t('admin.logistics.verify_unassigned') ?></div>
            <div class="verify-value"><?= $tickStats['unassigned_wells'] ?></div>
            <div class="verify-sub"><?= number_format($tickStats['unassigned_production'], 1) ?> bph</div>
        </div>
        <div class="verify-card">
            <div class="verify-label"><?= t('admin.logistics.verify_assigned') ?></div>
            <div class="verify-value c-good"><?= $tickStats['hub_assignments'] ?></div>
            <div class="verify-sub"><?= t('admin.logistics.verify_to_hubs') ?></div>
        </div>
        <div class="verify-card">
            <div class="verify-label"><?= t('admin.logistics.verify_opex') ?></div>
            <div class="verify-value"><?= number_format($tickStats['total_opex_charged'], 0, ',', ' ') ?> PLN</div>
            <div class="verify-sub"><?= t('admin.logistics.verify_per_tick') ?></div>
        </div>
        <div class="verify-card <?= $tickStats['fallback_losses_bbl'] > 0 ? 'verify-card--warn' : '' ?>">
            <div class="verify-label"><?= t('admin.logistics.verify_fallback_loss') ?></div>
            <div class="verify-value"><?= number_format($tickStats['fallback_losses_bbl'], 1) ?> bbl</div>
            <div class="verify-sub">$<?= number_format($tickStats['fallback_losses_value'], 0, ',', ' ') ?></div>
        </div>
    </div>
    <?php if ($hasUnassigned): ?>
    <div class="verify-alert">
         <?= t('admin.logistics.verify_fallback_alert', ['count' => $tickStats['unassigned_wells']]) ?>
    </div>
    <?php endif ?>
</details>

<?php require __DIR__ . '/sections/staffing_diagnostics.php'; ?>

<!--  Seed masowy  -->
<div class="seed-box">
    <h4> <?= t('admin.logistics.seed_title') ?></h4>
    <p class="c-muted"><?= t('admin.logistics.seed_desc') ?></p>
    <form method="POST" id="seed-region-form">
        <input type="hidden" name="action"      value="seed_region">
        <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($csrf) ?>">
        <div class="admin-form-row">
            <div>
                <label><?= t('admin.logistics.seed_region_label') ?></label>
                <select name="region_id" class="admin-input" id="seed-region-select">
                    <option value=""><?= t('admin.logistics.seed_region_empty') ?></option>
                    <?php foreach ($allRegions as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?> (#<?= $r['id'] ?>)</option>
                    <?php endforeach ?>
                </select>
            </div>
            <div>
                <label><?= t('admin.logistics.seed_count_label') ?></label>
                <input type="number" name="count" value="20" min="1" max="50" class="admin-input admin-input--short">
            </div>
            <button type="submit" class="btn btn-warn btn-sm" id="seed-region-submit">
                 <?= t('admin.logistics.seed_submit') ?>
            </button>
        </div>
    </form>
</div>

<!--  Create a single hub  -->
<details class="admin-details">
    <summary><?= t('admin.logistics.create_title') ?></summary>
    <form method="POST" class="admin-details-form">
        <input type="hidden" name="action"     value="create_hub">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
        <div class="admin-form-row">
            <div>
                <label><?= t('admin.logistics.create_name_label') ?></label>
                <input type="text" name="name" maxlength="120" required class="admin-input"
                       placeholder="<?= t('admin.logistics.create_name_ph') ?>">
            </div>
            <div>
                <label><?= t('admin.logistics.create_type_label') ?></label>
                <select name="hub_type" class="admin-input">
                    <option value="small"><?= t('admin.logistics.cfg_type_small') ?></option>
                    <option value="medium"><?= t('admin.logistics.cfg_type_medium') ?></option>
                    <option value="large"><?= t('admin.logistics.cfg_type_large') ?></option>
                </select>
            </div>
            <div>
                <label><?= t('admin.logistics.create_acquisition_label') ?></label>
                <select name="acquisition_type" class="admin-input">
                    <option value="new"><?= t('admin.logistics.acquisition_new') ?></option>
                    <option value="used"><?= t('admin.logistics.acquisition_used') ?></option>
                    <option value="rental"><?= t('admin.logistics.acquisition_rental') ?></option>
                </select>
            </div>
            <div>
                <label><?= t('admin.logistics.create_region_label') ?></label>
                <select name="region_id" class="admin-input" required>
                    <option value=""><?= t('admin.logistics.seed_region_empty') ?></option>
                    <?php foreach ($allRegions as $r): ?>
                    <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div>
                <label><?= t('admin.logistics.create_zone_label') ?></label>
                <input type="text" name="zone_key" maxlength="32" class="admin-input admin-input--short"
                       placeholder="<?= t('admin.logistics.create_zone_ph') ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-sm"> <?= t('admin.logistics.create_submit') ?></button>
        </div>
    </form>
</details>

<!--  Filtry listy  -->
<form method="GET" class="filter-row">
    <?php if ($viewHubId): ?>
    <input type="hidden" name="hub_id" value="<?= $viewHubId ?>">
    <?php endif ?>
    <div>
        <label><?= t('admin.logistics.filter_status_label') ?></label>
        <select name="status" class="admin-input">
            <option value=""><?= t('admin.logistics.filter_status_all') ?></option>
            <?php foreach ($statusMap as $sv => $sl): ?>
            <option value="<?= $sv ?>" <?= $filterStatus === $sv ? 'selected' : '' ?>><?= $sl ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div>
        <label><?= t('admin.logistics.filter_region_label') ?></label>
        <select name="region_id" class="admin-input">
            <option value=""><?= t('admin.logistics.filter_region_all') ?></option>
            <?php foreach ($allRegions as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= $filterRegion === (int)$r['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($r['name']) ?>
            </option>
            <?php endforeach ?>
        </select>
    </div>
    <div>
        <label><?= t('admin.logistics.filter_cond_label') ?></label>
        <select name="cond" class="admin-input">
            <option value=""><?= t('admin.logistics.filter_cond_all') ?></option>
            <option value="ok"       <?= $filterCond === 'ok'       ? 'selected' : '' ?>><?= t('admin.logistics.filter_cond_ok') ?></option>
            <option value="warn"     <?= $filterCond === 'warn'     ? 'selected' : '' ?>><?= t('admin.logistics.filter_cond_warn') ?></option>
            <option value="bad"      <?= $filterCond === 'bad'      ? 'selected' : '' ?>><?= t('admin.logistics.filter_cond_bad') ?></option>
            <option value="critical" <?= $filterCond === 'critical' ? 'selected' : '' ?>><?= t('admin.logistics.filter_cond_critical') ?></option>
        </select>
    </div>
    <button type="submit" class="btn btn-secondary btn-sm"><?= t('admin.logistics.filter_submit') ?></button>
    <a href="/admin/logistics_hubs.php" class="btn btn-secondary btn-sm"><?= t('admin.logistics.filter_reset') ?></a>
</form>

<!--  Hub details (after click)  -->
<?php if ($viewHub):
    $condPctView = (float)$viewHub['condition_pct'];
    $condClsView = $condPctView < 30 ? 'c-bad' : ($condPctView < 60 ? 'c-warn' : 'c-good');
?>
<div class="hub-detail-box">
    <h4> <?= t('admin.logistics.detail_title', ['name' => htmlspecialchars($viewHub['name']), 'id' => $viewHub['id']]) ?></h4>

    <!--  Info statyczne  -->
    <div class="hub-detail-info">
        <dl class="hub-detail-dl">
            <dt><?= t('admin.logistics.hub_type') ?></dt>        <dd><?= $typeMap[$viewHub['hub_type']] ?? $viewHub['hub_type'] ?></dd>
            <dt><?= t('admin.logistics.hub_acquisition') ?></dt> <dd><?= t('admin.logistics.acquisition_' . ($viewHub['acquisition_type'] ?? 'new')) ?></dd>
            <dt><?= t('admin.logistics.hub_region') ?></dt>      <dd><?= htmlspecialchars($viewHub['region_name'] ?? '#' . $viewHub['region_id']) ?></dd>
            <dt><?= t('admin.logistics.filter_status_label') ?></dt><dd><span class="badge badge-<?= $statusBadge[$viewHub['status']] ?? 'yellow' ?>"><?= $statusMap[$viewHub['status']] ?? $viewHub['status'] ?></span></dd>
            <dt><?= t('admin.logistics.hub_condition') ?></dt>   <dd class="<?= $condClsView ?>"><?= number_format($condPctView, 1) ?>%</dd>
            <dt><?= t('admin.logistics.hub_initial_condition') ?></dt><dd><?= number_format((float)($viewHub['initial_condition_pct'] ?? $condPctView), 1) ?>%</dd>
            <dt><?= t('admin.logistics.hub_wear') ?></dt>        <dd><?= number_format((float)$viewHub['wear_level'], 4) ?></dd>
            <dt><?= t('admin.logistics.hub_lease_fee') ?></dt>   <dd><?= number_format((float)($viewHub['lease_fee_per_tick'] ?? 0), 2, ',', ' ') ?> USD</dd>
            <dt><?= t('admin.logistics.hub_slots') ?></dt>       <dd><?= $viewHub['assigned_count'] ?>/<?= $viewHub['slot_limit'] ?></dd>
            <dt><?= t('admin.logistics.hub_mode') ?></dt>        <dd><?= $modeMap[$viewHub['work_mode']] ?? $viewHub['work_mode'] ?></dd>
            <dt><?= t('admin.logistics.hub_level') ?></dt>       <dd><?= $viewHub['level'] ?></dd>
            <?php if ($viewLastStats): ?>
            <dt><?= t('admin.logistics.hub_last_load') ?></dt>   <dd><?= number_format((float)$viewLastStats['load_pct'], 1) ?>%</dd>
            <dt><?= t('admin.logistics.hub_last_lost') ?></dt>   <dd><?= number_format((float)$viewLastStats['lost_volume_bbl'], 2) ?> bbl</dd>
            <?php endif ?>
        </dl>
    </div>

    <!--  Szybkie akcje  -->
    <div class="hub-detail-quick-actions">
        <form method="POST">
            <input type="hidden" name="action"     value="repair_hub">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="hub_id"     value="<?= $viewHub['id'] ?>">
            <button type="submit" class="btn btn-sm btn-primary" <?= $condPctView >= 100 ? 'disabled' : '' ?>>
                 <?= t('admin.logistics.btn_repair') ?>
            </button>
        </form>
        <form method="POST">
            <input type="hidden" name="action"     value="toggle_pause">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="hub_id"     value="<?= $viewHub['id'] ?>">
            <button type="submit" class="btn btn-sm btn-warn">
                <?= $viewHub['status'] === 'paused' ? ' ' . t('admin.logistics.btn_resume') : ' ' . t('admin.logistics.btn_pause') ?>
            </button>
        </form>
    </div>

    <!--  Parametry edytowalne (karty)  -->
    <div class="hub-detail-params">

        <div class="hub-param-card">
            <div class="hub-param-label"><?= t('admin.logistics.hub_mode') ?></div>
            <form method="POST" class="hub-param-form">
                <input type="hidden" name="action"     value="set_mode">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="hub_id"     value="<?= $viewHub['id'] ?>">
                <select name="mode" class="admin-input admin-select-dark">
                    <?php foreach ($modeMap as $m => $mLabel): ?>
                    <option value="<?= $m ?>" <?= $viewHub['work_mode'] === $m ? 'selected' : '' ?>><?= $mLabel ?></option>
                    <?php endforeach ?>
                </select>
                <button type="submit" class="btn btn-sm btn-secondary"> <?= t('admin.logistics.btn_save') ?></button>
            </form>
        </div>

        <div class="hub-param-card">
            <div class="hub-param-label"><?= t('admin.logistics.filter_status_label') ?></div>
            <form method="POST" class="hub-param-form">
                <input type="hidden" name="action"     value="set_status">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="hub_id"     value="<?= $viewHub['id'] ?>">
                <select name="status" class="admin-input admin-select-dark">
                    <?php foreach ($statusMap as $sv => $sl): ?>
                    <option value="<?= $sv ?>" <?= $viewHub['status'] === $sv ? 'selected' : '' ?>><?= $sl ?></option>
                    <?php endforeach ?>
                </select>
                <button type="submit" class="btn btn-sm btn-secondary"> <?= t('admin.logistics.btn_save') ?></button>
            </form>
        </div>

        <div class="hub-param-card">
            <div class="hub-param-label"><?= t('admin.logistics.hub_condition') ?> <small class="c-muted">(0-100%)</small></div>
            <div class="hub-param-current <?= $condClsView ?>"><?= number_format($condPctView, 1) ?>%</div>
            <form method="POST" class="hub-param-form">
                <input type="hidden" name="action"     value="set_condition">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="hub_id"     value="<?= $viewHub['id'] ?>">
                <input type="number" name="condition_pct" value="<?= number_format($condPctView, 1) ?>"
                       min="0" max="100" step="0.1" class="admin-input admin-input-dark">
                <button type="submit" class="btn btn-sm btn-secondary"> <?= t('admin.logistics.btn_save') ?></button>
            </form>
        </div>

        <div class="hub-param-card">
            <div class="hub-param-label"><?= t('admin.logistics.hub_name') ?></div>
            <div class="hub-param-current c-muted"><?= htmlspecialchars($viewHub['name']) ?></div>
            <form method="POST" class="hub-param-form">
                <input type="hidden" name="action"     value="rename_hub">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="hub_id"     value="<?= $viewHub['id'] ?>">
                <input type="text" name="name" value="<?= htmlspecialchars($viewHub['name']) ?>"
                       maxlength="120" class="admin-input admin-input-dark">
                <button type="submit" class="btn btn-sm btn-secondary"> <?= t('admin.logistics.btn_save') ?></button>
            </form>
        </div>

    </div>

    <!--  Wymus awarie huba (admin)  -->
    <details class="admin-details hub-incident-force">
        <summary><?= t('admin.logistics.incident_force_title') ?></summary>
        <p class="c-muted hub-incident-force__description"><?= t('admin.logistics.incident_force_desc') ?></p>
        <form method="POST" class="hub-param-form"
              data-confirm="<?= htmlspecialchars(tPlain('admin.logistics.incident_force_confirm'), ENT_QUOTES, 'UTF-8') ?>"
              data-confirm-title="<?= htmlspecialchars(tPlain('admin.logistics.incident_force_title'), ENT_QUOTES, 'UTF-8') ?>"
              data-confirm-label="<?= htmlspecialchars(tPlain('admin.logistics.incident_force_submit'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action"     value="force_incident">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="hub_id"     value="<?= (int)$viewHub['id'] ?>">
            <div class="hub-incident-force__fields">
                <div>
                    <label class="hub-incident-force__label"><?= t('admin.logistics.incident_force_type_label') ?></label>
                    <select name="incident_type" class="admin-input admin-select-dark">
                        <?php foreach ([
                            'transfer_failure'  => t('admin.logistics.incident_type_transfer_failure'),
                            'equipment_damage'  => t('admin.logistics.incident_type_equipment_damage'),
                            'local_leak'        => t('admin.logistics.incident_type_local_leak'),
                            'loading_error'     => t('admin.logistics.incident_type_loading_error'),
                            'storage_jam'       => t('admin.logistics.incident_type_storage_jam'),
                            'critical_overload' => t('admin.logistics.incident_type_critical_overload'),
                        ] as $val => $lbl): ?>
                        <option value="<?= $val ?>"><?= $lbl ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div>
                    <label class="hub-incident-force__label"><?= t('admin.logistics.incident_force_player_label') ?></label>
                    <input type="number" name="notify_player" value="0" min="0" class="admin-input admin-input-dark hub-incident-force__player" placeholder="0">
                </div>
                <button type="submit" class="btn btn-sm btn-danger"> <?= t('admin.logistics.incident_force_submit') ?></button>
            </div>
        </form>
    </details>

    <!--  Przypisane odwierty  -->
    <?php if (!empty($viewWells)): ?>
    <h5><?= t('admin.logistics.detail_wells_title', ['count' => count($viewWells)]) ?></h5>
    <div class="data-list">
        <div class="list-header">
            <span><?= t('admin.logistics.well_col_id') ?></span>
            <span><?= t('admin.logistics.well_col_name') ?></span>
            <span><?= t('admin.logistics.well_col_player') ?></span>
            <span><?= t('admin.logistics.well_col_region') ?></span>
            <span><?= t('admin.logistics.well_col_status') ?></span>
            <span><?= t('admin.logistics.well_col_prod') ?></span>
        </div>
        <?php foreach ($viewWells as $w): ?>
        <div class="list-row">
            <span><?= (int)$w['id'] ?></span>
            <span><?= htmlspecialchars($w['name'] ?? $w['location_name'] ?? '') ?></span>
            <span><?= (int)$w['player_id'] ?></span>
            <span><?= htmlspecialchars($w['region_name'] ?? '#' . $w['region_id']) ?></span>
            <span><?= $w['status'] ?></span>
            <span><?= number_format((float)$w['base_production_per_hour'], 1) ?></span>
        </div>
        <?php endforeach ?>
    </div>
    <?php else: ?>
    <p class="c-muted"><?= t('admin.logistics.detail_wells_empty') ?></p>
    <?php endif ?>

    <a href="/admin/logistics_hubs.php?<?= http_build_query(array_filter(['status' => $filterStatus, 'region_id' => $filterRegion ?: null])) ?>"
       class="btn btn-xs btn-secondary"> <?= t('admin.logistics.detail_close') ?></a>
</div>
<?php endif ?>

<!-- Hub list -->
<div class="hub-list-header">
    <h3><?= t('admin.logistics.list_title') ?> <small class="c-muted">(<?= $totalHubs ?>)</small></h3>
    <?php if ($totalPages > 1): ?>
    <div class="hub-pagination-info"><?= t('admin.logistics.page_info', ['page' => $page, 'total' => $totalPages]) ?></div>
    <?php endif ?>
</div>

<?php if (empty($allHubs)): ?>
    <div class="admin-alert admin-alert--warn"><?= t('admin.logistics.list_empty') ?></div>
<?php else: ?>

<?php
$pageQs  = array_filter(['status' => $filterStatus, 'region_id' => $filterRegion ?: null, 'cond' => $filterCond, 'hub_id' => $viewHubId ?: null]);
$pageUrl = fn(int $p) => '/admin/logistics_hubs.php?' . http_build_query($pageQs + ['page' => $p]);
?>

<?php foreach ($hubsPageByRegion as $regionName => $regionHubs): ?>
<div class="hub-region-section">
    <div class="hub-region-header"> <?= htmlspecialchars($regionName) ?> <small class="c-muted">(<?= count($regionHubs) ?>)</small></div>
    <div class="hub-admin-grid">
    <?php foreach ($regionHubs as $hub):
        $hubId         = (int)$hub['id'];
        $condPct       = (float)$hub['condition_pct'];
        $condCls       = $condPct < 30 ? 'c-bad' : ($condPct < 60 ? 'c-warn' : ($condPct < 70 ? 'c-warn' : 'c-good'));
        $slotsFull     = (int)$hub['assigned_count'] >= (int)$hub['slot_limit'];
        $hubStatus     = $hub['status'];
        $badgeCls      = $statusBadge[$hubStatus] ?? 'yellow';
        $hubName       = htmlspecialchars($hub['name']);
        $isPaused      = $hubStatus === 'paused';
        $confirmPause  = htmlspecialchars(t('admin.logistics.' . ($isPaused ? 'confirm_resume' : 'confirm_pause'), ['name' => $hub['name']]));
        $confirmRepair = htmlspecialchars(t('admin.logistics.confirm_repair', ['name' => $hub['name']]));
    ?>
    <div class="hub-admin-card">
        <h4>
            <span class="hub-card-id">#<?= $hubId ?></span>
            <?= $hubName ?>
            <span class="badge badge-<?= $badgeCls ?>"><?= $statusMap[$hubStatus] ?? $hubStatus ?></span>
        </h4>
        <div class="hub-stat-row">
            <span><?= t('admin.logistics.hub_type') ?></span>
            <span><?= $typeMap[$hub['hub_type']] ?? $hub['hub_type'] ?></span>
        </div>
        <div class="hub-stat-row">
            <span><?= t('admin.logistics.hub_acquisition') ?></span>
            <span><?= t('admin.logistics.acquisition_' . ($hub['acquisition_type'] ?? 'new')) ?></span>
        </div>
        <div class="hub-stat-row">
            <span><?= t('admin.logistics.hub_condition') ?></span>
            <span class="<?= $condCls ?>"><?= number_format($condPct, 1) ?>%</span>
        </div>
        <?php if ((float)($hub['lease_fee_per_tick'] ?? 0) > 0): ?>
        <div class="hub-stat-row">
            <span><?= t('admin.logistics.hub_lease_fee') ?></span>
            <span><?= number_format((float)$hub['lease_fee_per_tick'], 2, ',', ' ') ?> PLN</span>
        </div>
        <?php endif ?>
        <div class="hub-stat-row">
            <span><?= t('admin.logistics.hub_slots') ?></span>
            <span class="<?= $slotsFull ? 'c-bad' : '' ?>"><?= $hub['assigned_count'] ?>/<?= $hub['slot_limit'] ?></span>
        </div>
        <div class="hub-stat-row">
            <span><?= t('admin.logistics.hub_mode') ?></span>
            <span><?= $modeMap[$hub['work_mode']] ?? $hub['work_mode'] ?></span>
        </div>
        <div class="hub-actions-row">
            <a href="?hub_id=<?= $hubId ?>&status=<?= urlencode($filterStatus) ?>&region_id=<?= $filterRegion ?>&cond=<?= urlencode($filterCond) ?>&page=<?= $page ?>"
               class="btn btn-xs btn-secondary"> <?= t('admin.logistics.btn_detail') ?></a>

            <form method="POST" class="hub-inline-form"
                  data-confirm="<?= $confirmPause ?>"
                  data-confirm-title="<?= htmlspecialchars(tPlain('admin.logistics.' . ($isPaused ? 'btn_resume' : 'btn_pause')), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action"     value="toggle_pause">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="hub_id"     value="<?= $hubId ?>">
                <button type="submit" class="btn btn-xs btn-warn">
                    <?= t('admin.logistics.' . ($isPaused ? 'btn_resume' : 'btn_pause')) ?>
                </button>
            </form>

            <?php if ($condPct < 100): ?>
            <form method="POST" class="hub-inline-form"
                  data-confirm="<?= $confirmRepair ?>"
                  data-confirm-title="<?= htmlspecialchars(tPlain('admin.logistics.btn_repair'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action"     value="repair_hub">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="hub_id"     value="<?= $hubId ?>">
                <button type="submit" class="btn btn-xs btn-primary"><?= t('admin.logistics.btn_repair') ?></button>
            </form>
            <?php endif ?>
        </div>
    </div>
    <?php endforeach ?>
    </div>
</div>
<?php endforeach ?>

<!--  Paginacja  -->
<?php if ($totalPages > 1):
    $window  = 2;
    $visible = [];
    for ($p = 1; $p <= $totalPages; $p++) {
        if ($p === 1 || $p === $totalPages || abs($p - $page) <= $window) {
            $visible[] = $p;
        }
    }
    $visible = array_unique($visible);
    sort($visible);
?>
<div class="hub-pagination">
    <?php if ($page > 1): ?>
    <a href="<?= $pageUrl($page - 1) ?>" class="btn btn-sm btn-secondary"><?= t('admin.logistics.page_prev') ?></a>
    <?php endif ?>

    <?php $prev = null; foreach ($visible as $p):
        if ($prev !== null && $p - $prev > 1): ?>
        <span class="hub-pagination-dots">...</span>
    <?php endif ?>
    <a href="<?= $pageUrl($p) ?>" class="btn btn-sm <?= $p === $page ? 'btn-primary' : 'btn-secondary' ?>"><?= $p ?></a>
    <?php $prev = $p; endforeach ?>

    <?php if ($page < $totalPages): ?>
    <a href="<?= $pageUrl($page + 1) ?>" class="btn btn-sm btn-secondary"><?= t('admin.logistics.page_next') ?></a>
    <?php endif ?>
    <span class="hub-pagination-info"><?= t('admin.logistics.page_info', ['page' => $page, 'total' => $totalPages]) ?></span>
</div>
<?php endif ?>

<?php endif ?>

<?php require __DIR__ . '/sections/configuration.php'; ?>

</div><!-- /admin-container -->
<script src="<?= asset('/assets/js/modal.js') ?>"></script>
<script src="<?= asset('/assets/js/admin_logistics_hubs.js') ?>"></script>
</body>
</html>
