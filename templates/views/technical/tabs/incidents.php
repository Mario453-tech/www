<?php require_once dirname(__DIR__, 3) . '/components/incident_icons.php'; ?>
<div class="incident-page-heading">
    <h1><?= t('technical.incidents_title') ?></h1>
    <p><?= t('technical.incidents_subtitle') ?></p>
</div>
<details class="g-card incident-panel" open>
    <summary class="g-card-title incident-panel-heading">
        <span><?= incidentIconSvg('rig', 'incident-svg--heading') ?><?= t('technical.incidents_wells_title') ?> (<?= (int)$incTotal ?>)</span>
        <small><?= t('technical.incidents_wells_hint') ?></small>
        <?= incidentIconSvg('chevron-up', 'incident-svg--panel-chevron') ?>
    </summary>

    <?php if (empty($incidents)): ?>
    <div class="empty-state"><?= t('technical.no_incidents') ?></div>
    <?php else: ?>
    <div class="inc-list">
    <?php foreach ($incidents as $incidentIndex => $inc):
        if ($incidentIndex === 3): ?>
    </div>
    <details class="incident-extra">
        <summary><?= t('technical.incidents_show_all', ['count' => count($incidents)]) ?><?= incidentIconSvg('chevron-down', 'incident-svg--expand') ?></summary>
        <div class="inc-list">
        <?php endif;
        $lvl       = $inc['level'];
        $cause     = $inc['cause_type'];
        $lvlCls    = match($lvl) { 'micro'=>'inc-micro','minor'=>'inc-minor','medium'=>'inc-medium','major'=>'inc-major', default=>'' };
        $resolved  = !empty($inc['repaired_at']);
    ?>
    <article class="inc-row <?= $lvlCls ?> <?= $resolved ? 'inc-row--resolved' : 'inc-row--active' ?>">
        <div class="inc-badge"><span class="inc-status-dot" aria-hidden="true"></span><?= t($resolved ? 'technical.incidents_resolved' : 'technical.incidents_active') ?></div>
        <div class="inc-body">
            <div class="inc-msg"><?= htmlspecialchars($inc['message']) ?></div>
            <div class="inc-meta">
                <span class="inc-meta-source"><?= incidentIconSvg($cause === 'operator' ? 'person' : 'gear', 'incident-svg--meta') ?><?= t('technical.inc_cause_' . $cause, [], ucfirst($cause)) ?></span>
                <span class="sep">·</span>
                <span><?= !empty($inc['well_name']) ? htmlspecialchars((string)$inc['well_name'], ENT_QUOTES, 'UTF-8') : t('technical.well_num', ['id' => $inc['well_id']]) ?></span>
                <span class="sep">·</span>
                <span class="<?= $inc['prod_drop'] >= 40 ? 'c-bad' : ($inc['prod_drop'] >= 10 ? 'c-warn' : 'c-muted2') ?>">
                    <?= $inc['prod_drop'] ?>% <?= t('technical.inc_prod') ?>
                </span>
                <?php if ($inc['cost'] > 0): ?>
                <span class="sep">·</span>
                <span class="c-gold"><?= t('technical.inc_cost') ?>: $<?= number_format($inc['cost'], 0, '.', ' ') ?></span>
                <?php endif ?>
                <?php if ($inc['deg_damage'] > 0): ?>
                <span class="sep">·</span>
                <span class="c-bad"><?= t('technical.inc_cond_drop', ['pts' => $inc['deg_damage']]) ?></span>
                <?php endif ?>
                <span class="sep">·</span>
                <span class="c-muted2"><?= $inc['auto_repair'] ? t('technical.inc_auto_repair') : t('technical.inc_needs_tech') ?></span>
                <span class="sep">·</span>
                <span class="c-muted2"><?= t('technical.inc_level_' . $lvl, [], strtoupper($lvl)) ?></span>
            </div>
            <?php if (!$inc['auto_repair'] && !$resolved): ?>
            <?php /* Route repair requests to the maintenance team.
                     Przekieruj zlecenia napraw do zespolu utrzymania. */ ?>
            <a class="btn btn-sm btn-primary inc-repair-link" href="<?= htmlspecialchars(url('technical', ['tab' => 'team', 'repair_well' => (int)$inc['well_id']]), ENT_QUOTES, 'UTF-8') ?>#tech-mnt">
                <?= t('incident.btn_repair') ?>
            </a>
            <?php endif ?>
        </div>
        <time class="inc-time" datetime="<?= htmlspecialchars((string)$inc['created_at'], ENT_QUOTES, 'UTF-8') ?>"><?= incidentIconSvg('clock', 'incident-svg--time') ?><?= date('d.m, H:i', strtotime((string)$inc['created_at'])) ?></time>
    </article>
    <?php endforeach ?>
    </div>
    <?php if (count($incidents) > 3): ?></details><?php endif ?>

    <?php if ($incTotalPages > 1): ?>
    <nav class="inc-pagination">
        <?php if ($incPage > 1): ?>
        <a href="?tab=incidents&inc_page=<?= $incPage - 1 ?>" class="btn btn-sm btn-secondary">‹ <?= t('common.prev') ?></a>
        <?php endif ?>
        <span class="inc-page-info"><?= $incPage ?> / <?= $incTotalPages ?></span>
        <?php if ($incPage < $incTotalPages): ?>
        <a href="?tab=incidents&inc_page=<?= $incPage + 1 ?>" class="btn btn-sm btn-secondary"><?= t('common.next') ?> ›</a>
        <?php endif ?>
    </nav>
    <?php endif ?>

    <?php endif ?>
</details>

<?php if (!empty($hubIncidents)): ?>
<details class="g-card incident-panel incident-panel--logistics" open>
    <summary class="g-card-title incident-panel-heading">
         <span><?= incidentIconSvg('truck', 'incident-svg--heading') ?><?= t('logistics.hub.incidents_title') ?> (<?= (int)$hubIncTotal ?>)</span>
         <small><?= t('technical.incidents_hubs_hint', ['count' => count($hubIncidents), 'earlier' => max(0, $hubIncTotal - count($hubIncidents))]) ?></small>
         <?= incidentIconSvg('chevron-up', 'incident-svg--panel-chevron') ?>
    </summary>
    <div class="inc-list">
    <?php foreach ($hubIncidents as $incidentIndex => $hi):
        if ($incidentIndex === 3): ?>
    </div>
    <details class="incident-extra">
        <summary><?= t('technical.incidents_show_all', ['count' => count($hubIncidents)]) ?><?= incidentIconSvg('chevron-down', 'incident-svg--expand') ?></summary>
        <div class="inc-list">
        <?php endif;
        $sev     = $hi['severity'] ?? 'low';
        $lvlCls  = match($sev) { 'critical'=>'inc-major', 'high'=>'inc-medium', 'medium'=>'inc-minor', default=>'inc-micro' };
    ?>
    <article class="inc-row <?= $lvlCls ?>">
        <div class="inc-badge"><span class="inc-status-dot" aria-hidden="true"></span><?= t(in_array($sev, ['critical', 'high'], true) ? 'technical.incidents_critical' : 'technical.incidents_warning') ?></div>
        <div class="inc-body">
            <div class="inc-msg"><?= htmlspecialchars($hi['message']) ?></div>
            <div class="inc-meta">
                <span class="inc-meta-source"><?= incidentIconSvg('truck', 'incident-svg--meta') ?><?= htmlspecialchars($hi['hub_name'] ?? 'Hub #' . $hi['hub_id']) ?></span>
                <?php if (!empty($hi['meta_json'])): $meta = json_decode($hi['meta_json'], true) ?? []; ?>
                <?php if (($meta['extra_loss_bbl'] ?? 0) > 0): ?>
                <span class="sep">·</span>
                <span class="c-bad"><?= number_format((float)$meta['extra_loss_bbl'], 1, ',', ' ') ?> bbl</span>
                <?php endif ?>
                <?php if (($meta['condition_dmg'] ?? 0) > 0): ?>
                <span class="sep">·</span>
                <span class="c-warn"><?= t('technical.inc_cond_drop', ['pts' => (int)$meta['condition_dmg']]) ?></span>
                <?php endif ?>
                <?php endif ?>
                <span class="sep">·</span>
            </div>
        </div>
        <time class="inc-time" datetime="<?= htmlspecialchars((string)$hi['created_at'], ENT_QUOTES, 'UTF-8') ?>"><?= incidentIconSvg('clock', 'incident-svg--time') ?><?= date('d.m, H:i', strtotime((string)$hi['created_at'])) ?></time>
    </article>
    <?php endforeach ?>
    </div>
    <?php if (count($hubIncidents) > 3): ?></details><?php endif ?>
    <?php if ($hubIncTotal > count($hubIncidents)): ?>
    <div class="incident-more-history">
        <?= t('logistics.hub.incidents_more', ['n' => $hubIncTotal - count($hubIncidents)]) ?>
        → <a href="/logistics#logistics-hub-incidents-heading"><?= t('logistics.hub.incidents_goto') ?></a>
    </div>
    <?php endif ?>
</details>
<?php endif ?>
