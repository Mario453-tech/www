<?php
/**
 * Hub runtime configuration view.
 * Widok konfiguracji runtime hubow.
 */
$cfgField = static fn(string $group, string $key, string $label, string $unit = '', string $step = '1', string $note = '')
    => $hub_admin->renderCfgField(
        $group,
        $key,
        $label,
        (string)$cfgGet($group, $key, ''),
        $csrf,
        $unit,
        $step,
        $note
    );
?>
<details id="hub-config-section" class="admin-details">
    <summary><?= t('admin.logistics.cfg_section_title') ?></summary>
    <p class="c-muted"><?= t('admin.logistics.cfg_section_desc') ?></p>

    <div class="cfg-section">
        <h4><?= t('admin.logistics.cfg_hub_types_title') ?></h4>
        <form method="post" action="/admin/logistics_hubs.php#hub-config-section"
              data-confirm="<?= htmlspecialchars(tPlain('admin.logistics.cfg_seed_confirm'), ENT_QUOTES, 'UTF-8') ?>"
              data-confirm-title="<?= htmlspecialchars(tPlain('admin.logistics.cfg_seed_btn'), ENT_QUOTES, 'UTF-8') ?>"
              data-confirm-label="<?= htmlspecialchars(tPlain('admin.logistics.cfg_seed_btn'), ENT_QUOTES, 'UTF-8') ?>"
              class="cfg-seed-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="seed_hub_type_defaults">
            <button type="submit" class="btn btn-primary"><?= t('admin.logistics.cfg_seed_btn') ?></button>
            <small class="panel-hint"><?= t('admin.logistics.cfg_seed_hint') ?></small>
        </form>

        <div class="cfg-type-cols">
            <?php foreach (['small', 'medium', 'large'] as $type): ?>
                <div class="cfg-type-block">
                    <h5><?= t('admin.logistics.cfg_type_' . $type) ?></h5>
                    <?php
                    $cfgField('hub_type', "{$type}.slot_limit", t('admin.logistics.cfg_slot_limit'), 'szt', '1');
                    $cfgField('hub_type', "{$type}.nominal_bph", t('admin.logistics.cfg_nominal_bph'), 'bph', '10');
                    $cfgField('hub_type', "{$type}.buffer_bbl", t('admin.logistics.cfg_buffer_bbl'), 'bbl', '10');
                    $cfgField('hub_type', "{$type}.opex_per_tick", t('admin.logistics.cfg_opex_per_tick'), 'PLN', '100');
                    $cfgField('hub_type', "{$type}.build_cost", t('admin.logistics.cfg_build_cost'), 'PLN', '1000');
                    $cfgField('hub_type', "{$type}.repair_cost_pct", t('admin.logistics.cfg_repair_cost_pct'), '% build', '0.01', t('admin.logistics.cfg_repair_cost_note'));
                    $cfgField('hub_type', "{$type}.wear_per_tick", t('admin.logistics.cfg_wear_per_tick'), 'pkt', '0.001');
                    $cfgField('hub_type', "{$type}.overload_wear_mult", t('admin.logistics.cfg_overload_wear_mult'), 'x', '0.1');
                    $cfgField('hub_type', "{$type}.overload_risk_mult", t('admin.logistics.cfg_overload_risk_mult'), 'x', '0.1');
                    $cfgField('hub_type', "{$type}.upgrade_cost", t('admin.logistics.cfg_upgrade_cost'), 'PLN', '1000');
                    $cfgField('hub_type', "{$type}.max_level", t('admin.logistics.cfg_max_level'), 'lvl', '1');
                    ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>

    <div class="cfg-section">
        <h4><?= t('admin.logistics.cfg_acquisition_title') ?></h4>
        <div class="cfg-type-cols">
            <?php foreach (['new', 'used', 'rental'] as $type): ?>
                <div class="cfg-type-block">
                    <h5><?= t('admin.logistics.acquisition_' . $type) ?></h5>
                    <?php
                    $cfgField('acquisition', "{$type}.build_cost_mult", t('admin.logistics.cfg_acquisition_build_mult'), 'x', '0.01');
                    $cfgField('acquisition', "{$type}.opex_mult", t('admin.logistics.cfg_acquisition_opex_mult'), 'x', '0.01');
                    $cfgField('acquisition', "{$type}.start_condition_min", t('admin.logistics.cfg_acquisition_start_min'), '%', '0.1');
                    $cfgField('acquisition', "{$type}.start_condition_max", t('admin.logistics.cfg_acquisition_start_max'), '%', '0.1');
                    $cfgField('acquisition', "{$type}.wear_mult", t('admin.logistics.cfg_acquisition_wear_mult'), 'x', '0.01');
                    $cfgField('acquisition', "{$type}.risk_mult", t('admin.logistics.cfg_acquisition_risk_mult'), 'x', '0.01');
                    $cfgField('acquisition', "{$type}.lease_fee_per_tick", t('admin.logistics.cfg_acquisition_lease_fee'), 'PLN', '1');
                    ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>

    <div class="cfg-section">
        <h4><?= t('admin.logistics.cfg_work_modes_title') ?></h4>
        <div class="cfg-type-cols">
            <?php foreach (['eco', 'standard', 'max'] as $mode): ?>
                <div class="cfg-type-block">
                    <h5><?= t('admin.logistics.cfg_mode_' . $mode) ?></h5>
                    <?php
                    $cfgField('work_mode', "{$mode}.throughput_mult", t('admin.logistics.cfg_throughput_mult'), 'x', '0.01', t('admin.logistics.cfg_throughput_note'));
                    $cfgField('work_mode', "{$mode}.wear_mult", t('admin.logistics.cfg_wear_mult'), 'x', '0.01');
                    $cfgField('work_mode', "{$mode}.opex_mult", t('admin.logistics.cfg_opex_mult'), 'x', '0.01');
                    $cfgField('work_mode', "{$mode}.risk_mult", t('admin.logistics.cfg_risk_mult'), 'x', '0.01');
                    $cfgField('work_mode', "{$mode}.efficiency_mod", t('admin.logistics.cfg_efficiency_mod'), 'pkt', '0.1', t('admin.logistics.cfg_efficiency_note'));
                    ?>
                </div>
            <?php endforeach ?>
        </div>
    </div>

    <div class="cfg-section">
        <h4><?= t('admin.logistics.cfg_fallback_title') ?></h4>
        <p class="c-muted"><?= t('admin.logistics.cfg_fallback_desc') ?></p>
        <div class="cfg-group">
            <?php
            $cfgField('fallback', 'throughput_bph', t('admin.logistics.cfg_throughput_bph'), 'bph', '10', t('admin.logistics.cfg_throughput_bph_note'));
            $cfgField('fallback', 'opex_mult', t('admin.logistics.cfg_opex_mult_fb'), 'x', '0.1', t('admin.logistics.cfg_opex_mult_fb_note'));
            $cfgField('fallback', 'loss_mult', t('admin.logistics.cfg_loss_mult'), 'x', '0.1', t('admin.logistics.cfg_loss_mult_note'));
            $cfgField('fallback', 'risk_mult', t('admin.logistics.cfg_risk_mult_fb'), 'x', '0.1', t('admin.logistics.cfg_risk_mult_fb_note'));
            $cfgField('fallback', 'efficiency_pct', t('admin.logistics.cfg_efficiency_pct'), '%', '1', t('admin.logistics.cfg_efficiency_pct_note'));
            ?>
        </div>
    </div>
</details>
