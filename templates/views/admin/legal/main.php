<?php extract($viewData, EXTR_SKIP); ?>

<h1><?= t('admin.legal.title') ?></h1>
<p class="panel-hint"><?= t('admin.legal.subtitle') ?></p>

<?php if ($msg): ?><p class="alert alert-success"><?= htmlspecialchars($msg) ?></p><?php endif ?>
<?php if ($err): ?><p class="alert alert-error"><?= htmlspecialchars($err) ?></p><?php endif ?>

<!-- Statystyki wiercenie / Drilling stats -->
<section class="panel mb-8">
    <div class="cards">
        <div class="card"><p class="label"><?= t('admin.legal.stat_total') ?></p><p class="value"><?= (int)$stats['total'] ?></p></div>
        <div class="card"><p class="label"><?= t('admin.legal.stat_pending') ?></p><p class="value orange"><?= (int)$stats['pending'] + (int)($stats['delayed'] ?? 0) ?></p></div>
        <div class="card"><p class="label"><?= t('admin.legal.stat_granted') ?></p><p class="value green"><?= (int)$stats['granted'] ?></p></div>
        <div class="card"><p class="label"><?= t('admin.legal.stat_refused') ?></p><p class="value red"><?= (int)$stats['refused'] ?></p></div>
        <div class="card"><p class="label"><?= t('admin.legal.stat_regions') ?></p><p class="value"><?= count($regions) ?></p></div>
        <!-- P2a hub stats / Statystyki hubów P2a -->
        <div class="card"><p class="label"><?= t('admin.legal.hub.stat_total') ?></p><p class="value"><?= (int)$hubStats['total'] ?></p></div>
        <div class="card"><p class="label"><?= t('admin.legal.hub.stat_granted') ?></p><p class="value green"><?= (int)$hubStats['granted'] ?></p></div>
    </div>
</section>

<!-- Zakładki -->
<nav class="admin-tabs">
    <a href="?tab=regions"          class="admin-tab <?= $tab === 'regions'          ? 'active' : '' ?>"><?= t('admin.legal.tab_regions') ?></a>
    <a href="?tab=applications"     class="admin-tab <?= $tab === 'applications'     ? 'active' : '' ?>"><?= t('admin.legal.tab_applications') ?></a>
    <a href="?tab=hub_applications" class="admin-tab <?= $tab === 'hub_applications' ? 'active' : '' ?>"><?= t('admin.legal.hub.tab_applications') ?></a>
</nav>

<!-- ===== TAB: KONFIGURACJA REGIONÓW ===== -->
<?php if ($tab === 'regions'): ?>

<!-- Seed konfiguracji regionów -->
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.legal.seed_title') ?></p>
    <p class="panel-hint"><?= t('admin.legal.seed_hint') ?></p>
    <form method="post" action="/admin/legal.php?tab=regions"
          class="js-confirm-form"
          data-confirm="<?= htmlspecialchars(tPlain('admin.legal.seed_confirm')) ?>"
          data-confirm-title="<?= htmlspecialchars(tPlain('admin.legal.btn_seed_regions')) ?>">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="seed_regions">
        <button type="submit" class="btn btn-primary">
            <?= t('admin.legal.btn_seed_regions') ?>
        </button>
    </form>
</section>

<!-- Migracja przejściowa -->
<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.legal.migration_title') ?></p>
    <p class="panel-hint"><?= t('admin.legal.migration_hint') ?></p>
    <form method="post" action="/admin/legal.php?tab=regions"
          class="js-confirm-form"
          data-confirm="<?= htmlspecialchars(tPlain('admin.legal.migration_confirm')) ?>"
          data-confirm-title="<?= htmlspecialchars(tPlain('admin.legal.btn_run_migration')) ?>"
          data-confirm-type="warning">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="run_migration">
        <button type="submit" class="btn btn-warning">
            <?= t('admin.legal.btn_run_migration') ?>
        </button>
    </form>
</section>

<section class="panel">
    <p class="panel-title"><?= t('admin.legal.regions_title') ?></p>
    <p class="panel-hint"><?= t('admin.legal.regions_intro') ?></p>

    <?php if (empty($regions)): ?>
    <p class="panel-hint"><?= t('admin.legal.no_regions') ?></p>
    <form method="post" action="/admin/legal.php?tab=regions"
          class="admin-legal-seed-form"
          data-confirm="<?= htmlspecialchars(tPlain('admin.legal.seed_confirm')) ?>"
          data-confirm-title="<?= htmlspecialchars(tPlain('admin.legal.btn_seed_regions')) ?>">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="seed_regions">
    <button type="submit" class="btn btn-primary"><?= t('admin.legal.btn_seed_regions') ?></button>
    </form>
    <?php else: ?>

    <div class="detail-grid legal-admin-guide">
        <article>
            <p class="dl"><?= t('admin.legal.guide_basics_title') ?></p>
            <p class="detail-note-sm"><?= t('admin.legal.guide_basics_text') ?></p>
        </article>
        <article>
            <p class="dl"><?= t('admin.legal.guide_risk_title') ?></p>
            <p class="detail-note-sm"><?= t('admin.legal.guide_risk_text') ?></p>
        </article>
        <article>
            <p class="dl"><?= t('admin.legal.guide_requirements_title') ?></p>
            <p class="detail-note-sm"><?= t('admin.legal.guide_requirements_text') ?></p>
        </article>
        <article>
            <p class="dl"><?= t('admin.legal.guide_hub_title') ?></p>
            <p class="detail-note-sm"><?= t('admin.legal.guide_hub_text') ?></p>
        </article>
    </div>

    <button class="legal-table-toggle" onclick="this.closest('.panel').querySelector('.table-scroll-wrap').classList.toggle('legal-table-expanded');this.closest('.panel').classList.toggle('legal-table-expanded')">
        Pokaż zaawansowane kolumny
    </button>
    <div class="table-scroll-wrap">
    <table class="data-table legal-config-table">
        <thead>
            <tr>
                <th rowspan="2"><?= t('admin.legal.col_region') ?></th>
                <th colspan="3"><?= t('admin.legal.group_region_state') ?></th>
                <th colspan="3"><?= t('admin.legal.group_drilling_permit') ?></th>
                <th colspan="5" class="col-advanced"><?= t('admin.legal.group_drilling_permit') ?> (zaawansowane)</th>
                <th colspan="2"><?= t('admin.legal.group_player_requirements') ?></th>
                <th colspan="3" class="col-advanced"><?= t('admin.legal.group_hub_permit') ?></th>
                <th rowspan="2"><?= t('admin.legal.col_actions_short') ?></th>
            </tr>
            <tr>
                <th><?= t('admin.legal.col_risk') ?></th>
                <th><?= t('admin.legal.col_enabled') ?></th>
                <th><?= t('admin.legal.col_offshore') ?></th>
                <th><?= t('admin.legal.col_cost') ?></th>
                <th><?= t('admin.legal.col_review_min') ?></th>
                <th class="col-advanced" title="<?= htmlspecialchars(tPlain('admin.legal.col_delay_pct_hint')) ?>"><?= t('admin.legal.col_delay_pct') ?></th>
                <th class="col-advanced"><?= t('admin.legal.col_delay_min') ?></th>
                <th class="col-advanced"><?= t('admin.legal.col_delay_max') ?></th>
                <th class="col-advanced" title="<?= htmlspecialchars(tPlain('admin.legal.col_refusal_pct_hint')) ?>"><?= t('admin.legal.col_refusal_pct') ?></th>
                <th class="col-advanced" title="<?= htmlspecialchars(tPlain('admin.legal.col_nodec_pct_hint')) ?>"><?= t('admin.legal.col_nodec_pct') ?></th>
                <th class="col-advanced"><?= t('admin.legal.col_cooldown') ?></th>
                <th><?= t('admin.legal.col_capital') ?></th>
                <th><?= t('admin.legal.col_legal_level') ?></th>
                <th class="col-advanced" title="<?= htmlspecialchars(tPlain('admin.legal.hub.col_enabled_hint')) ?>"><?= t('admin.legal.hub.col_enabled') ?></th>
                <th class="col-advanced"><?= t('admin.legal.hub.col_cost') ?></th>
                <th class="col-advanced"><?= t('admin.legal.hub.col_review_min') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($regions as $cfg): ?>
        <tr>
            <form method="post" action="/admin/legal.php?tab=regions">
            <?= CSRF::field() ?>
            <input type="hidden" name="action"    value="save_region_config">
            <input type="hidden" name="region_id" value="<?= (int)$cfg['region_id'] ?>">
            <td class="legal-config-table__region">
                <strong><?= htmlspecialchars((string)($cfg['region_name'] ?? 'Region ' . $cfg['region_id'])) ?></strong><br>
                <small><?= htmlspecialchars((string)($cfg['region_code'] ?? '')) ?> #<?= (int)$cfg['region_id'] ?></small>
            </td>
            <td>
                <select name="risk_level" class="input-sm">
                    <?php foreach (['low','medium','high','critical'] as $rl): ?>
                    <option value="<?= $rl ?>" <?= $cfg['risk_level'] === $rl ? 'selected' : '' ?>><?= $rl ?></option>
                    <?php endforeach ?>
                </select>
            </td>
            <td><input type="checkbox" name="enabled" value="1" <?= (int)$cfg['enabled'] ? 'checked' : '' ?>></td>
            <td><input type="checkbox" name="is_offshore" value="1" <?= (int)($cfg['is_offshore'] ?? 0) ? 'checked' : '' ?>></td>
            <td><input type="number" name="application_cost"     value="<?= (float)$cfg['application_cost'] ?>"    min="0"   step="1000"  class="input-sm input-num-110"></td>
            <td><input type="number" name="base_review_minutes"  value="<?= (int)$cfg['base_review_minutes'] ?>"   min="1"   step="5"     class="input-sm input-num-70"></td>
            <td class="col-advanced"><input type="number" name="delay_risk_pct"       value="<?= (float)$cfg['delay_risk_pct'] ?>"      min="0"   max="100" step="1" class="input-sm input-num-60"></td>
            <td class="col-advanced"><input type="number" name="delay_min_minutes"    value="<?= (int)($cfg['delay_min_minutes'] ?? 10) ?>" min="1" step="1" class="input-sm input-num-60"></td>
            <td class="col-advanced"><input type="number" name="delay_max_minutes"    value="<?= (int)($cfg['delay_max_minutes'] ?? 30) ?>" min="1" step="1" class="input-sm input-num-60"></td>
            <td class="col-advanced"><input type="number" name="refusal_risk_pct"     value="<?= (float)$cfg['refusal_risk_pct'] ?>"    min="0"   max="100" step="1" class="input-sm input-num-60"></td>
            <td class="col-advanced"><input type="number" name="no_decision_risk_pct" value="<?= (float)$cfg['no_decision_risk_pct'] ?>" min="0"  max="100" step="1" class="input-sm input-num-60"></td>
            <td class="col-advanced"><input type="number" name="refusal_cooldown_minutes" value="<?= (int)$cfg['refusal_cooldown_minutes'] ?>" min="0" step="30" class="input-sm input-num-70"></td>
            <td><input type="number" name="required_capital"     value="<?= (float)$cfg['required_capital'] ?>"    min="0"   step="100000" class="input-sm input-num-110"></td>
            <td><input type="number" name="required_legal_level" value="<?= (int)($cfg['required_legal_level'] ?? 0) ?>" min="0" max="10" step="1" class="input-sm input-num-60"></td>
            <!-- P2a: hub permit fields / Pola zezwolen na huby -->
            <td class="col-advanced"><input type="checkbox" name="hub_permit_enabled" value="1" <?= (int)($cfg['hub_permit_enabled'] ?? 0) ? 'checked' : '' ?>></td>
            <td class="col-advanced"><input type="number"   name="hub_permit_cost"    value="<?= (float)($cfg['hub_permit_cost'] ?? 500000) ?>" min="0" step="10000" class="input-sm input-num-110"></td>
            <td class="col-advanced"><input type="number"   name="hub_review_minutes" value="<?= (int)($cfg['hub_review_minutes'] ?? 120) ?>"   min="1" step="5"      class="input-sm input-num-70"></td>
            <td><button type="submit" class="btn btn-sm btn-primary"><?= t('admin.legal.btn_save') ?></button></td>
            </form>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
    <?php endif ?>
</section>

<!-- ===== TAB: WNIOSKI GRACZY ===== -->
<?php elseif ($tab === 'applications'): ?>

<section class="panel">
    <p class="panel-title"><?= t('admin.legal.applications_title') ?></p>

    <?php if (empty($applications)): ?>
    <p class="panel-hint"><?= t('admin.legal.no_applications') ?></p>
    <?php else: ?>

    <div class="table-scroll-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th><?= t('admin.legal.col_player') ?></th>
                <th><?= t('admin.legal.col_region_app') ?></th>
                <th><?= t('admin.legal.col_status') ?></th>
                <th><?= t('admin.legal.col_submitted') ?></th>
                <th><?= t('admin.legal.col_due') ?></th>
                <th><?= t('admin.legal.col_decided') ?></th>
                <th><?= t('admin.legal.col_actions') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($applications as $app): ?>
        <?php
        $statusCss = match ((string)$app['status']) {
            'granted', 'transitional' => 'badge-active',
            'pending', 'delayed'      => 'badge-pending',
            'refused', 'no_decision'  => 'badge-inactive',
            default                   => '',
        };
        ?>
        <tr>
            <td><?= (int)$app['id'] ?></td>
            <td>
                <?= htmlspecialchars((string)($app['company_name'] ?? $app['username'] ?? 'ID ' . $app['player_id'])) ?>
                <br><small>#<?= (int)$app['player_id'] ?></small>
            </td>
            <td><?= htmlspecialchars((string)($app['region_name'] ?? '#' . $app['region_id'])) ?></td>
            <td><span class="badge <?= $statusCss ?>"><?= htmlspecialchars((string)$app['status']) ?></span>
                <?php if ((int)($app['delay_count'] ?? 0) > 0): ?>
                <br><small><?= t('admin.legal.delay_count_label', ['n' => (int)$app['delay_count']]) ?></small>
                <?php endif ?>
            </td>
            <td><small><?= htmlspecialchars(substr((string)($app['submitted_at'] ?? '—'), 0, 16)) ?></small></td>
            <td><small><?= htmlspecialchars(substr((string)($app['decision_due_at'] ?? '—'), 0, 16)) ?></small></td>
            <td><small><?= htmlspecialchars(substr((string)($app['decided_at'] ?? '—'), 0, 16)) ?></small></td>
            <td>
                <?php
                // Brief §16.3: nazwa gracza i regionu do treści modalu potwierdzenia.
                // Brief §16.3: player and region names for the confirmation modal body.
                $confPlayer = (string)($app['company_name'] ?? $app['username'] ?? ('#' . $app['player_id']));
                $confRegion = (string)($app['region_name'] ?? ('#' . $app['region_id']));
                ?>
                <div class="legal-admin-actions">
                <?php foreach ([
                    'manual_grant'        => ['btn-success', t('admin.legal.action_grant')],
                    'manual_transitional' => ['btn-secondary', t('admin.legal.action_transitional')],
                    'manual_no_decision'  => ['btn-warning', t('admin.legal.action_no_decision')],
                    'manual_refuse'       => ['btn-danger',  t('admin.legal.action_refuse')],
                    'manual_reset_pending'=> ['btn-secondary', t('admin.legal.action_reset')],
                ] as $act => [$btnCss, $btnLabel]): ?>
                <?php
                $confMsg = tPlain('admin.legal.confirm_manual', [
                    'action' => $btnLabel,
                    'player' => $confPlayer,
                    'region' => $confRegion,
                ]);
                ?>
                <form method="post" action="/admin/legal.php?tab=applications"
                      class="js-confirm-form"
                      data-confirm="<?= htmlspecialchars($confMsg) ?>"
                      data-confirm-title="<?= htmlspecialchars($btnLabel) ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="<?= $act ?>">
                    <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
                    <button type="submit" class="btn btn-xs <?= $btnCss ?>"><?= $btnLabel ?></button>
                </form>
                <?php endforeach ?>
                </div>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
    <?php endif ?>
</section>

<?php endif ?>

<!-- ===== TAB: WNIOSKI O ZEZWOLENIA NA HUBY / HUB PERMIT APPLICATIONS ===== -->
<?php if ($tab === 'hub_applications'): ?>

<section class="panel">
    <p class="panel-title"><?= t('admin.legal.hub.applications_title') ?></p>

    <?php if (empty($hubApplications)): ?>
    <p class="panel-hint"><?= t('admin.legal.hub.no_applications') ?></p>
    <?php else: ?>

    <div class="table-scroll-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th><?= t('admin.legal.col_player') ?></th>
                <th><?= t('admin.legal.col_region_app') ?></th>
                <th><?= t('admin.legal.col_status') ?></th>
                <th><?= t('admin.legal.col_submitted') ?></th>
                <th><?= t('admin.legal.col_due') ?></th>
                <th><?= t('admin.legal.col_decided') ?></th>
                <th><?= t('admin.legal.col_actions') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($hubApplications as $app): ?>
        <?php
        $statusCss = match ((string)$app['status']) {
            'granted'                => 'badge-active',
            'pending', 'delayed'     => 'badge-pending',
            'refused', 'no_decision' => 'badge-inactive',
            default                  => '',
        };
        ?>
        <tr>
            <td><?= (int)$app['id'] ?></td>
            <td>
                <?= htmlspecialchars((string)($app['company_name'] ?? $app['username'] ?? 'ID ' . $app['player_id'])) ?>
                <br><small>#<?= (int)$app['player_id'] ?></small>
            </td>
            <td><?= htmlspecialchars((string)($app['region_name'] ?? '#' . $app['region_id'])) ?></td>
            <td>
                <span class="badge <?= $statusCss ?>"><?= htmlspecialchars((string)$app['status']) ?></span>
                <?php if ((int)($app['delay_count'] ?? 0) > 0): ?>
                <br><small><?= t('admin.legal.delay_count_label', ['n' => (int)$app['delay_count']]) ?></small>
                <?php endif ?>
            </td>
            <td><small><?= htmlspecialchars(substr((string)($app['submitted_at'] ?? '—'), 0, 16)) ?></small></td>
            <td><small><?= htmlspecialchars(substr((string)($app['decision_due_at'] ?? '—'), 0, 16)) ?></small></td>
            <td><small><?= htmlspecialchars(substr((string)($app['decided_at'] ?? '—'), 0, 16)) ?></small></td>
            <td>
                <div class="legal-admin-actions">
                <?php foreach ([
                    'hub_manual_grant'       => ['btn-success',   t('admin.legal.action_grant')],
                    'hub_manual_no_decision' => ['btn-warning',   t('admin.legal.action_no_decision')],
                    'hub_manual_refuse'      => ['btn-danger',    t('admin.legal.action_refuse')],
                    'hub_manual_reset'       => ['btn-secondary', t('admin.legal.action_reset')],
                ] as $act => [$btnCss, $btnLabel]): ?>
                <form method="post" action="/admin/legal.php?tab=hub_applications"
                      class="js-confirm-form"
                      data-confirm="<?= htmlspecialchars(tPlain('admin.legal.confirm_action'), ENT_QUOTES) ?>"
                      data-confirm-label="<?= htmlspecialchars($btnLabel, ENT_QUOTES) ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="<?= $act ?>">
                    <input type="hidden" name="app_id" value="<?= (int)$app['id'] ?>">
                    <button type="submit" class="btn btn-xs <?= $btnCss ?>"><?= $btnLabel ?></button>
                </form>
                <?php endforeach ?>
                </div>
            </td>
        </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    </div>
    <?php endif ?>
</section>

<?php endif /* hub_applications tab */ ?>
