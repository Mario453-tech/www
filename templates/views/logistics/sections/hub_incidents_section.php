    <!--  -->
    <!-- SEKCJA: Incydenty logistyczne hubow                                -->
    <!--  -->
    <?php if (!empty($hubIncidents)): ?>
    <section class="logistics-panel" aria-labelledby="logistics-hub-incidents-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-hub-incidents-heading"><?= t('logistics.hub.incidents_title') ?></h3>
            <span><?= t('logistics.hub.incidents_desc') ?></span>
        </div>
        <div class="logistics-hub-incidents-list">
        <?php foreach ($hubIncidents as $hi):
            $sev     = $hi['severity'] ?? 'low';
            $sevIcon = match($sev) {
                'critical' => '',
                'high'     => '',
                'medium'   => '',
                default    => '',
            };
            $evType  = $hi['event_type'] ?? '';
            $typeKey = str_replace('hub_incident_', '', $evType);
        ?>
        <div class="logistics-hub-incident-row logistics-hub-incident--<?= htmlspecialchars($sev) ?>">
            <div class="logistics-hub-incident-icon"><?= $sevIcon ?></div>
            <div class="logistics-hub-incident-body">
                <div class="logistics-hub-incident-title">
                    <strong><?= htmlspecialchars($hi['title'] ?? t('logistics.hub.incident.title.' . $typeKey)) ?></strong>
                    <span class="logistics-hub-incident-hub">
                        &middot; <?= htmlspecialchars($hi['hub_name'] ?? (($locale === 'en' ? 'Hub #' : 'Hub #') . $hi['hub_id'])) ?>
                    </span>
                </div>
                <div class="logistics-hub-incident-msg"><?= htmlspecialchars($hi['message']) ?></div>
                <div class="logistics-hub-incident-meta">
                    <span class="c-muted2"><?= date('d.m H:i', strtotime($hi['created_at'])) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach ?>
        </div>
        <?php if ((int)($hubIncidentsTotalPages ?? 1) > 1):
            $hubIncidentsPage = (int)($hubIncidentsPage ?? 1);
            $hubIncidentsTotalPages = (int)($hubIncidentsTotalPages ?? 1);
            $hubIncidentsBaseParams = $_GET;
            $hubIncidentsBaseParams['tab'] = $hubIncidentsBaseParams['tab'] ?? 'logistics';
        ?>
        <div class="logistics-pagination">
            <div class="logistics-pagination-info">
                <?= $hubIncidentsPage ?> / <?= $hubIncidentsTotalPages ?> (<?= (int)($hubIncidentsTotal ?? 0) ?>)
            </div>
            <div class="logistics-pagination-buttons">
                <?php if ($hubIncidentsPage > 1):
                    $hubIncidentsBaseParams['hub_incident_page'] = $hubIncidentsPage - 1;
                ?>
                <a href="?<?= htmlspecialchars(http_build_query($hubIncidentsBaseParams)) ?>#logistics-hub-incidents-heading" class="btn btn-xs btn-secondary">
                    <?= t('logistics.pagination_prev') ?>
                </a>
                <?php endif ?>
                <?php if ($hubIncidentsPage < $hubIncidentsTotalPages):
                    $hubIncidentsBaseParams['hub_incident_page'] = $hubIncidentsPage + 1;
                ?>
                <a href="?<?= htmlspecialchars(http_build_query($hubIncidentsBaseParams)) ?>#logistics-hub-incidents-heading" class="btn btn-xs btn-secondary">
                    <?= t('logistics.pagination_next') ?>
                </a>
                <?php endif ?>
            </div>
        </div>
        <?php endif ?>
    </section>
    <?php endif ?>
