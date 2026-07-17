    <section class="logistics-panel" aria-labelledby="logistics-insights-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-insights-heading"><?= t('logistics.insight_title') ?></h3>
            <span><?= t('logistics.insight_desc') ?></span>
        </div>

        <div class="logistics-insight-summary">
            <div class="logistics-insight-pill <?= (int)($logisticsInsights['unassigned_count'] ?? 0) > 0 ? 'logistics-insight-pill--warn' : 'logistics-insight-pill--ok' ?>">
                <span><?= t('logistics.insight_pill_unassigned') ?></span>
                <strong><?= (int)($logisticsInsights['unassigned_count'] ?? 0) ?></strong>
            </div>
            <div class="logistics-insight-pill <?= $lossPct >= 8 ? 'logistics-insight-pill--danger' : 'logistics-insight-pill--ok' ?>">
                <span><?= t('logistics.insight_pill_loss') ?></span>
                <strong><?= number_format($lossPct, 1, ',', ' ') ?>%</strong>
            </div>
            <div class="logistics-insight-pill <?= !empty($logisticsInsights['hub_hotspots']) ? 'logistics-insight-pill--danger' : 'logistics-insight-pill--ok' ?>">
                <span><?= t('logistics.insight_pill_hubs') ?></span>
                <strong><?= count($logisticsInsights['hub_hotspots'] ?? []) ?></strong>
            </div>
            <div class="logistics-insight-pill logistics-insight-pill--info">
                <span><?= t('logistics.insight_pill_cost') ?></span>
                <strong><?= number_format((float)$totals['cost'], 2, ',', ' ') ?> <?= $currencyLabel ?>/h</strong>
            </div>
        </div>

        <div class="logistics-insight-grid">
            <article class="logistics-insight-card">
                <div class="logistics-insight-card-hdr">
                    <h4><?= t('logistics.insight_loss_title') ?></h4>
                    <span><?= t('logistics.insight_loss_subtitle') ?></span>
                </div>
                <?php if (empty($logisticsInsights['loss_wells'])): ?>
                    <div class="logistics-insight-empty"><?= t('logistics.insight_loss_empty') ?></div>
                <?php else: ?>
                    <div class="logistics-insight-list">
                        <?php foreach ($logisticsInsights['loss_wells'] as $row): ?>
                        <div class="logistics-insight-row">
                            <div>
                                <strong>#<?= (int)$row['id'] ?></strong>
                                <span><?= t('logistics.type_' . ($row['transport'] ?? 'nieustawiony')) ?></span>
                            </div>
                            <strong class="c-bad"><?= number_format((float)$row['loss'], 1, ',', ' ') ?> <?= t('common.bbl_h') ?></strong>
                        </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </article>

            <article class="logistics-insight-card">
                <div class="logistics-insight-card-hdr">
                    <h4><?= t('logistics.insight_cost_title') ?></h4>
                    <span><?= t('logistics.insight_cost_subtitle') ?></span>
                </div>
                <?php if (empty($logisticsInsights['cost_wells'])): ?>
                    <div class="logistics-insight-empty"><?= t('logistics.insight_cost_empty') ?></div>
                <?php else: ?>
                    <div class="logistics-insight-list">
                        <?php foreach ($logisticsInsights['cost_wells'] as $row): ?>
                        <div class="logistics-insight-row">
                            <div>
                                <strong>#<?= (int)$row['id'] ?></strong>
                                <span><?= t('logistics.type_' . ($row['transport'] ?? 'nieustawiony')) ?></span>
                            </div>
                            <strong><?= number_format((float)$row['cost'], 2, ',', ' ') ?> <?= $currencyLabel ?>/h</strong>
                        </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </article>

            <article class="logistics-insight-card">
                <div class="logistics-insight-card-hdr">
                    <h4><?= t('logistics.insight_hubs_title') ?></h4>
                    <span><?= t('logistics.insight_hubs_subtitle') ?></span>
                </div>
                <?php if (empty($logisticsInsights['hub_hotspots'])): ?>
                    <div class="logistics-insight-empty"><?= t('logistics.insight_hubs_empty') ?></div>
                <?php else: ?>
                    <div class="logistics-insight-list">
                        <?php foreach ($logisticsInsights['hub_hotspots'] as $row): ?>
                        <div class="logistics-insight-row logistics-insight-row--stack">
                            <div class="logistics-insight-row-main">
                                <strong><?= htmlspecialchars($row['name']) ?></strong>
                                <span><?= t('logistics.insight_hub_load', ['pct' => number_format((float)$row['load_pct'], 1, ',', ' ')]) ?></span>
                            </div>
                            <div class="logistics-insight-row-meta">
                                <span><?= t('logistics.insight_hub_condition', ['pct' => number_format((float)$row['condition_pct'], 1, ',', ' ')]) ?></span>
                                <?php if ((float)$row['lost_bbl'] > 0): ?>
                                <strong class="c-bad"><?= t('logistics.insight_hub_loss', ['bbl' => number_format((float)$row['lost_bbl'], 1, ',', ' ')]) ?></strong>
                                <?php endif ?>
                            </div>
                        </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </article>

            <article class="logistics-insight-card">
                <div class="logistics-insight-card-hdr">
                    <h4><?= t('logistics.insight_pipe_title') ?></h4>
                    <span><?= t('logistics.insight_pipe_subtitle') ?></span>
                </div>
                <?php if (empty($logisticsInsights['worst_pipelines'])): ?>
                    <div class="logistics-insight-empty"><?= t('logistics.insight_pipe_empty') ?></div>
                <?php else: ?>
                    <div class="logistics-insight-list">
                        <?php foreach ($logisticsInsights['worst_pipelines'] as $row):
                            $wpCond   = (float)($row['condition_pct']  ?? 100);
                            $wpLoss   = (float)($row['transport_loss'] ?? 0);
                            $wpStatus = (string)($row['status'] ?? 'active');
                            $wpCondCls = $wpCond < 40 ? 'c-bad' : ($wpCond < 70 ? 'c-warn' : 'c-good');
                            $wpLossCls = $wpLoss >= 6  ? 'c-bad' : ($wpLoss > 0  ? 'c-warn' : '');
                        ?>
                        <div class="logistics-insight-row logistics-insight-row--stack">
                            <div class="logistics-insight-row-main">
                                <strong><?= htmlspecialchars((string)($row['name'] ?? ('#' . (int)($row['id'] ?? 0)))) ?></strong>
                                <span class="badge logistics-pipeline-badge logistics-pipeline-badge--<?= htmlspecialchars($wpStatus) ?>"><?= t('logistics.pipeline.status_' . $wpStatus) ?></span>
                            </div>
                            <div class="logistics-insight-row-meta">
                                <span class="<?= $wpCondCls ?>"><?= t('logistics.insight_hub_condition', ['pct' => number_format($wpCond, 1, ',', ' ')]) ?></span>
                                <?php if ($wpLoss > 0): ?>
                                <span class="<?= $wpLossCls ?>"><?= t('logistics.pipeline.label_loss') ?>: <?= number_format($wpLoss, 1, ',', ' ') ?>%</span>
                                <?php endif ?>
                            </div>
                        </div>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            </article>
        </div>

        <div class="logistics-reco-grid">
            <?php foreach (($logisticsInsights['recommendations'] ?? []) as $item): ?>
            <article class="logistics-reco-card logistics-reco-card--<?= htmlspecialchars($item['tone']) ?>">
                <div class="logistics-reco-body">
                    <h4><?= htmlspecialchars($item['title']) ?></h4>
                    <p><?= htmlspecialchars($item['text']) ?></p>
                </div>
                <a class="btn btn-sm btn-secondary logistics-reco-btn" href="<?= htmlspecialchars($item['cta_href']) ?>">
                    <?= htmlspecialchars($item['cta_label']) ?>
                </a>
            </article>
            <?php endforeach ?>
        </div>
    </section>
