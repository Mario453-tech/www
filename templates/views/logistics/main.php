<?php require __DIR__ . '/partials/bootstrap.php'; ?>
<div class="logistics-page logistics-design" data-details-label="<?= htmlspecialchars(t('logistics.design.details'), ENT_QUOTES, 'UTF-8') ?>">
    <nav class="logistics-section-nav" aria-label="<?= htmlspecialchars(t('logistics.design.nav_aria'), ENT_QUOTES, 'UTF-8') ?>">
        <a href="#logistics-summary" aria-current="location"><?= t('logistics.design.overview') ?></a>
        <a href="#logistics-transport-section"><?= t('logistics.design.transport') ?></a>
        <a href="#logistics-owned-section"><?= t('logistics.design.my_hubs') ?></a>
        <a href="#logistics-market-section"><?= t('logistics.design.market') ?></a>
        <a href="#logistics-pipelines-section"><?= t('logistics.design.pipelines') ?></a>
        <a href="#logistics-incidents-section"><?= t('logistics.design.incidents') ?> <span class="logistics-nav-count"><?= (int)($hubIncidentsTotal ?? 0) ?></span></a>
    </nav>

    <section class="logistics-design-section" id="logistics-summary" aria-labelledby="logistics-summary-title">
        <h2 class="visually-hidden" id="logistics-summary-title"><?= t('logistics.design.overview_heading') ?></h2>
        <?php require __DIR__ . '/sections/flash_kpi.php'; ?>
        <div class="logistics-design-overview-grid">
            <?php require __DIR__ . '/sections/flow_section.php'; ?>
            <?php require __DIR__ . '/sections/decisions_section.php'; ?>
        </div>
        <?php require __DIR__ . '/sections/alerts.php'; ?>
        <?php require __DIR__ . '/sections/transport_mix_section.php'; ?>
        <details class="logistics-insights-details">
            <summary><?= t('logistics.insight_title') ?></summary>
            <?php require __DIR__ . '/sections/insights_section.php'; ?>
        </details>
    </section>

    <section class="logistics-design-section" id="logistics-transport-section" aria-labelledby="logistics-transport-section-title">
        <header class="logistics-design-heading logistics-design-heading--transport">
            <div><h2 id="logistics-transport-section-title"><?= t('logistics.design.transport') ?></h2><p><?= t('logistics.design.transport_desc') ?></p></div>
            <?php require __DIR__ . '/sections/optimizer_cta.php'; ?>
        </header>
        <?php require __DIR__ . '/sections/transport_kpi.php'; ?>
        <?php require __DIR__ . '/sections/transport_table.php'; ?>
        <div class="logistics-transport-status-grid">
            <?php require __DIR__ . '/sections/road_trips_section.php'; ?>
            <?php require __DIR__ . '/sections/tanker_buffer_card.php'; ?>
        </div>
        <?php require __DIR__ . '/sections/marine_section.php'; ?>
    </section>

    <section class="logistics-design-section" id="logistics-owned-section" aria-labelledby="logistics-owned-section-title">
        <header class="logistics-design-heading"><h2 id="logistics-owned-section-title"><?= t('logistics.design.my_hubs') ?></h2><p><?= t('logistics.design.my_hubs_desc') ?></p></header>
        <?php require __DIR__ . '/sections/hub_summary.php'; ?>
        <?php require __DIR__ . '/sections/owned_hubs_section.php'; ?>
        <?php require __DIR__ . '/sections/unassigned_wells_section.php'; ?>
    </section>

    <section class="logistics-design-section" id="logistics-market-section" aria-labelledby="logistics-market-section-title">
        <header class="logistics-design-heading"><h2 id="logistics-market-section-title"><?= t('logistics.design.market') ?></h2><p><?= t('logistics.design.market_desc') ?></p></header>
        <?php require __DIR__ . '/sections/available_hubs_market.php'; ?>
    </section>

    <section class="logistics-design-section" id="logistics-pipelines-section" aria-labelledby="logistics-pipelines-section-title">
        <header class="logistics-design-heading"><h2 id="logistics-pipelines-section-title"><?= t('logistics.design.pipelines') ?></h2><p><?= t('logistics.design.pipelines_desc') ?></p></header>
        <?php require __DIR__ . '/sections/pipelines_section.php'; ?>
        <?php require __DIR__ . '/sections/protection_sections.php'; ?>
    </section>

    <section class="logistics-design-section" id="logistics-incidents-section" aria-labelledby="logistics-incidents-section-title">
        <header class="logistics-design-heading"><h2 id="logistics-incidents-section-title"><?= t('logistics.design.incidents_heading') ?></h2><p><?= t('logistics.design.incidents_desc') ?></p></header>
        <?php require __DIR__ . '/sections/hub_incidents_section.php'; ?>
    </section>
    <?php require __DIR__ . '/modals/hub_modals.php'; ?>
</div>

<?php require __DIR__ . '/modals/optimizer_modal.php'; ?>
<?php require __DIR__ . '/modals/pipeline_modals.php'; ?>
<?php require __DIR__ . '/modals/pipeline_staffing_modal.php'; ?>
<?php require __DIR__ . '/partials/client_config.php'; ?>
