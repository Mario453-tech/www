<?php require_once dirname(__DIR__, 3) . '/components/incident_icons.php'; ?>
<?php
    $incidentsOnPage = count($hubIncidents);
    $incidentPageStart = $incidentsOnPage ? (($hubIncidentsPage ?? 1) - 1) * 20 + 1 : 0;
    $incidentPageEnd = $incidentsOnPage ? $incidentPageStart + $incidentsOnPage - 1 : 0;
?>
<form class="logistics-incident-filters" method="get" action="/logistics#logistics-incidents-section">
    <label for="logistics-incident-search"><?= t('logistics.design.search_incidents') ?>
        <input id="logistics-incident-search" type="search" name="incident_q" value="<?= htmlspecialchars($hubIncidentQuery ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="100">
    </label>
    <label for="logistics-incident-severity"><?= t('logistics.design.severity') ?>
        <select id="logistics-incident-severity" name="incident_severity">
            <option value=""><?= t('logistics.design.all_severities') ?></option>
            <?php foreach (['critical', 'high', 'medium', 'low'] as $severityOption): ?>
            <option value="<?= $severityOption ?>" <?= ($hubIncidentSeverity ?? '') === $severityOption ? 'selected' : '' ?>><?= t('logistics.hub.incidents_severity_' . $severityOption) ?></option>
            <?php endforeach ?>
        </select>
    </label>
    <button class="btn btn-sm btn-secondary" type="submit"><?= t('logistics.design.filter') ?></button>
</form>
<details class="logistics-panel logistics-incidents-panel" open>
    <summary class="logistics-incidents-heading">
        <h3 id="logistics-hub-incidents-heading">
            <?= incidentIconSvg('truck', 'incident-svg--heading') ?>
            <?= t('logistics.hub.incidents_title') ?> (<?= (int)($hubIncidentsTotal ?? $incidentsOnPage) ?>)
        </h3>
        <span><?= t('logistics.hub.incidents_range', ['start' => $incidentPageStart, 'end' => $incidentPageEnd, 'total' => (int)($hubIncidentsTotal ?? $incidentsOnPage)]) ?></span>
        <?= incidentIconSvg('chevron-up', 'incident-svg--panel-chevron') ?>
    </summary>
    <?php if ($incidentsOnPage === 0): ?>
    <p class="logistics-empty"><?= t('logistics.design.no_incidents') ?></p>
    <?php endif ?>
    <div class="logistics-incidents-list">
    <?php foreach ($hubIncidents as $incidentIndex => $hi):
        if ($incidentIndex === 3): ?>
    </div>
    <details class="logistics-incidents-extra">
        <summary><?= t('logistics.hub.incidents_show_all', ['count' => $incidentsOnPage]) ?><?= incidentIconSvg('chevron-down', 'incident-svg--expand') ?></summary>
        <div class="logistics-incidents-list">
        <?php endif;
        $severity = (string)($hi['severity'] ?? 'low');
        $severity = in_array($severity, ['critical', 'high', 'medium', 'low'], true) ? $severity : 'low';
        $hubName = (string)($hi['hub_name'] ?? ('Hub #' . (int)($hi['hub_id'] ?? 0)));
        $meta = json_decode((string)($hi['meta_json'] ?? ''), true);
        $meta = is_array($meta) ? $meta : [];
    ?>
    <article class="logistics-incidents-row logistics-incidents-row--<?= $severity ?>">
        <div class="logistics-incidents-badge"><span class="logistics-incidents-dot" aria-hidden="true"></span><?= t('logistics.hub.incidents_severity_' . $severity) ?></div>
        <div class="logistics-incidents-body">
            <div class="logistics-incidents-message"><?= htmlspecialchars((string)$hi['message'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="logistics-incidents-meta">
                <span class="logistics-incidents-source"><span class="logistics-source-icon logistics-source-icon--<?= ($hi['source'] ?? 'hub') === 'pipeline' ? 'pipeline' : 'hub' ?>" aria-hidden="true"></span><?= htmlspecialchars($hubName, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (($meta['extra_loss_bbl'] ?? 0) > 0): ?>
                <span>· <?= t('logistics.hub.incidents_loss', ['amount' => number_format((float)$meta['extra_loss_bbl'], 1, ',', ' ')]) ?></span>
                <?php endif ?>
                <?php if (($meta['condition_dmg'] ?? 0) > 0): ?>
                <span>· <?= t('logistics.hub.incidents_damage', ['points' => (int)$meta['condition_dmg']]) ?></span>
                <?php endif ?>
            </div>
        </div>
        <time class="logistics-incidents-time" datetime="<?= htmlspecialchars((string)$hi['created_at'], ENT_QUOTES, 'UTF-8') ?>"><?= incidentIconSvg('clock', 'incident-svg--time') ?><?= date('d.m, H:i', strtotime((string)$hi['created_at'])) ?></time>
    </article>
    <?php endforeach ?>
    </div>
    <?php if ($incidentsOnPage > 3): ?></details><?php endif ?>

    <?php if ((int)($hubIncidentsTotalPages ?? 1) > 1):
        $hubIncidentsBaseParams = $_GET;
        $hubIncidentsBaseParams['tab'] = $hubIncidentsBaseParams['tab'] ?? 'logistics';
    ?>
    <nav class="logistics-incidents-pagination" aria-label="<?= t('logistics.hub.incidents_pagination') ?>">
        <span><?= t('logistics.hub.incidents_page', ['page' => (int)$hubIncidentsPage, 'total' => (int)$hubIncidentsTotalPages]) ?></span>
        <div>
            <?php if ($hubIncidentsPage > 1):
                $hubIncidentsBaseParams['hub_incident_page'] = $hubIncidentsPage - 1;
            ?>
            <a href="?<?= htmlspecialchars(http_build_query($hubIncidentsBaseParams), ENT_QUOTES, 'UTF-8') ?>#logistics-hub-incidents-heading" class="btn btn-xs btn-secondary"><?= t('logistics.pagination_prev') ?></a>
            <?php endif ?>
            <?php if ($hubIncidentsPage < $hubIncidentsTotalPages):
                $hubIncidentsBaseParams['hub_incident_page'] = $hubIncidentsPage + 1;
            ?>
            <a href="?<?= htmlspecialchars(http_build_query($hubIncidentsBaseParams), ENT_QUOTES, 'UTF-8') ?>#logistics-hub-incidents-heading" class="btn btn-xs btn-secondary"><?= t('logistics.pagination_next') ?></a>
            <?php endif ?>
        </div>
    </nav>
    <?php endif ?>
</details>
