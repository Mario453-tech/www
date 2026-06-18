<?php extract($viewData, EXTR_SKIP); ?>

<h1><?= t('admin.transport.title') ?></h1>

<?php if ($msg): ?>
<p role="status" class="alert alert-success"><?= htmlspecialchars($msg) ?></p>
<?php endif ?>

<?php if ($err): ?>
<p role="alert" class="alert alert-error"><?= htmlspecialchars($err) ?></p>
<?php endif ?>

<?php if (!$configTableExists): ?>
<p class="alert alert-warning"><?= t('admin.transport.no_table_warning') ?></p>
<section class="panel">
    <p class="panel-title">SQL - <?= t('admin.transport.sql_create_title') ?></p>
    <pre class="pre-sql">CREATE TABLE transport_config (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  transport_type ENUM('rurociag','ciezarowki','tankowiec') NOT NULL,
  config_key     VARCHAR(30) NOT NULL,
  config_value   DECIMAL(14,4) NOT NULL,
  updated_at     DATETIME NOT NULL,
  UNIQUE KEY uq_type_key (transport_type, config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</pre>
</section>
<?php endif ?>

<section class="panel mb-8">
    <p class="panel-title"><?= t('admin.transport.stats_title') ?></p>
    <div class="cards">
        <?php foreach (['rurociag', 'ciezarowki', 'tankowiec'] as $type):
            $stats = $transportStats[$type] ?? null;
        ?>
        <div class="card">
            <p class="label"><?= $typeNames[$type] ?></p>
            <p class="value <?= $stats ? '' : 'muted' ?>">
                <?= $stats ? (int)$stats['cnt'] : 0 ?> <?= t('admin.transport.wells_suffix') ?>
            </p>
            <?php if ($stats): ?>
            <p class="card-note">
                <?= t('admin.transport.stats_note', [
                    'cap'  => round((float)$stats['avg_cap'], 1),
                    'opex' => round((float)$stats['avg_opex'], 1),
                ]) ?>
            </p>
            <?php endif ?>
        </div>
        <?php endforeach ?>
    </div>
</section>

<form method="post">
    <?= CSRF::field() ?>
    <input type="hidden" name="action" value="save_multipliers">

    <?php foreach (['rurociag', 'ciezarowki', 'tankowiec'] as $type): ?>
    <section class="panel">
        <p class="panel-title">
            <?= $typeLabels[$type][0] ?>
            <span class="panel-title-sub"><?= $typeLabels[$type][1] ?></span>
        </p>
        <div class="config-rows">
            <?php foreach ($fieldDefs as $key => [$label, $hint, $min, $max]):
                // min_load_bbl dotyczy tylko tankowcow; dla rurociagu i ciezarowek jest pomijane.
                // min_load_bbl applies to tankers only; skipped for pipelines and trucks.
                if ($key === 'min_load_bbl' && $type !== 'tankowiec') continue;
            ?>
            <div class="config-row">
                <div>
                    <div class="config-key-label"><?= $label ?></div>
                    <div class="config-key-code"><?= $hint ?></div>
                </div>
                <div class="config-input-group">
                    <input
                        type="number"
                        name="<?= $type ?>_<?= $key ?>"
                        value="<?= $config[$type][$key] ?>"
                        min="<?= $min ?>"
                        max="<?= $max ?>"
                        step="0.01"
                        class="config-input input-w-sm"
                        <?= !$configTableExists ? 'disabled' : '' ?>
                    >
                    <span class="muted font-xs">
                        <?= t('admin.transport.default_label') ?>: <?= $defaults[$type][$key] ?>
                    </span>
                </div>
            </div>
            <?php endforeach ?>
        </div>
    </section>
    <?php endforeach ?>

    <?php if ($configTableExists): ?>
    <div class="form-row">
        <button
            type="submit"
            class="btn btn-primary"
            onclick="confirmSubmit(this, '<?= t('admin.transport.confirm_save') ?>'); return false;"
        >
            <?= t('admin.transport.btn_save') ?>
        </button>
        <button
            type="submit"
            name="action"
            value="reset_defaults"
            class="btn btn-secondary"
            onclick="confirmSubmit(this, '<?= t('admin.transport.confirm_reset') ?>'); return false;"
        >
            <?= t('admin.transport.btn_reset') ?>
        </button>
    </div>
    <?php endif ?>
</form>

<section class="panel mt-4">
    <p class="panel-title"><?= t('admin.transport.apply_title') ?></p>
    <p class="panel-hint"><?= t('admin.transport.apply_hint') ?></p>
    <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="apply_to_wells">
        <div class="form-row">
            <select name="apply_type" class="select-md">
                <option value="rurociag"><?= t('admin.transport.type_pipe') ?></option>
                <option value="ciezarowki"><?= t('admin.transport.type_truck') ?></option>
                <option value="tankowiec"><?= t('admin.transport.type_tanker') ?></option>
            </select>
            <label class="form-label-inline" for="apply_capacity"><?= t('admin.transport.field_capacity') ?> %:</label>
            <input
                type="number"
                id="apply_capacity"
                name="apply_capacity"
                value="<?= $config['rurociag']['capacity'] ?>"
                min="10"
                max="200"
                step="0.5"
                class="input-w-sm"
            >
            <label class="form-label-inline" for="apply_opex"><?= t('admin.transport.field_opex') ?> %:</label>
            <input
                type="number"
                id="apply_opex"
                name="apply_opex"
                value="<?= $config['rurociag']['opex'] ?>"
                min="0"
                max="50"
                step="0.1"
                class="input-w-sm"
            >
            <button
                type="submit"
                class="btn btn-danger btn-sm"
                onclick="confirmSubmit(this, '<?= t('admin.transport.confirm_apply') ?>'); return false;"
            >
                <?= t('admin.transport.btn_apply') ?>
            </button>
        </div>
    </form>
</section>

<section class="panel mt-4">
    <p class="panel-title"><?= t('admin.transport.guide_title') ?></p>
    <div class="panel-info">
        <p><?= t('admin.transport.guide_intro') ?></p>
        <p><?= t('admin.transport.guide_scale') ?></p>
        <ul>
            <li><strong><?= t('admin.transport.field_incident') ?></strong>: <?= t('admin.transport.guide_incident') ?></li>
            <li><strong><?= t('admin.transport.field_disaster') ?></strong>: <?= t('admin.transport.guide_disaster') ?></li>
            <li><strong><?= t('admin.transport.field_wear') ?></strong>: <?= t('admin.transport.guide_wear') ?></li>
            <li><strong><?= t('admin.transport.field_spiral') ?></strong>: <?= t('admin.transport.guide_spiral') ?></li>
            <li><strong><?= t('admin.transport.field_capacity') ?></strong>: <?= t('admin.transport.guide_capacity') ?></li>
            <li><strong><?= t('admin.transport.field_opex') ?></strong>: <?= t('admin.transport.guide_opex') ?></li>
            <li><strong><?= t('admin.transport.field_cost_per_bbl') ?></strong>: <?= t('admin.transport.guide_cost_per_bbl') ?></li>
            <li><strong><?= t('admin.transport.field_min_load_bbl') ?></strong>: <?= t('admin.transport.guide_min_load_bbl') ?></li>
        </ul>
        <p><?= t('admin.transport.guide_blackmarket') ?></p>
    </div>
</section>

<script>
(() => {
    const config = <?= $configJson ?: '{}' ?>;
    const select = document.querySelector('select[name="apply_type"]');
    const capacityInput = document.getElementById('apply_capacity');
    const opexInput = document.getElementById('apply_opex');
    if (!select || !capacityInput || !opexInput) return;

    const syncFields = () => {
        const row = config[select.value];
        if (!row) return;
        capacityInput.value = row.capacity ?? capacityInput.value;
        opexInput.value = row.opex ?? opexInput.value;
    };

    select.addEventListener('change', syncFields);
    syncFields();
})();
</script>

<!--  -->
<!-- PORTY MORSKIE (Etap 5) — seed i podgląd                               -->
<!--  -->
<section class="admin-card" style="margin-top:24px">
    <h2>⚓ Porty morskie (Etap 5)</h2>
    <p class="help-text">Porty systemowe obsługują dostawy tankowców. Każdy region z odwiertem morskim musi mieć co najmniej 1 aktywny port — bez portu odwiert morski wstrzymuje produkcję.</p>

    <form method="post" style="margin-bottom:16px">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="seed_ports">
        <button type="submit" class="btn btn-primary btn-sm"
                onclick="return confirm('Seed portów: tworzy 1 port na region (pomija istniejące). Kontynuować?')">
            Zasiej domyślne porty (1 na region)
        </button>
        <span class="help-text" style="margin-left:8px">Bezpieczne — nie nadpisze istniejących portów.</span>
    </form>

    <?php if (empty($portsData)): ?>
        <div class="alert alert-info">Brak portów w bazie. Uruchom seed lub wykonaj migrację <code>migrations/etap5_marine_ports.sql</code>.</div>
    <?php else: ?>
    <table class="admin-table" style="font-size:.83rem">
        <thead>
            <tr>
                <th>ID</th><th>Nazwa</th><th>Region</th><th>Typ</th>
                <th>Status</th><th>Kolejka</th><th>Koszt/bbl</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($portsData as $port): ?>
            <?php
                $statusClass = match($port['status']) {
                    'active'     => 'badge-ok',
                    'overloaded' => 'badge-warn',
                    'damaged'    => 'badge-danger',
                    'closed'     => 'badge-muted',
                    default      => '',
                };
                $queueWaiting = (int)($port['queue_waiting'] ?? 0);
                $queueClass   = $queueWaiting > (int)($port['queue_limit'] ?? 20) * 0.8 ? 'c-warn' : '';
            ?>
            <tr>
                <td><?= (int)$port['id'] ?></td>
                <td><?= htmlspecialchars($port['name']) ?></td>
                <td><?= htmlspecialchars($port['region_name'] ?? 'Region #' . $port['region_id']) ?></td>
                <td><?= htmlspecialchars($port['port_type']) ?></td>
                <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($port['status']) ?></span></td>
                <td class="<?= $queueClass ?>">
                    <?= $queueWaiting ?>/<?= (int)($port['queue_limit'] ?? 20) ?>
                </td>
                <td><?= number_format((float)($port['handling_cost_per_bbl'] ?? 0), 2, ',', '.') ?> PLN</td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
    <?php endif ?>
</section>
