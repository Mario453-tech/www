<?php $bufferCards = is_array($marineBuffers ?? null) ? $marineBuffers : []; ?>
<section class="logistics-panel logistics-tanker-panel" aria-labelledby="logistics-tanker-buffer-heading">
    <div class="logistics-panel-head">
        <div>
            <h3 id="logistics-tanker-buffer-heading"><?= t('logistics.design.tanker_buffer_title') ?></h3>
            <span><?= t('logistics.design.tanker_buffer_desc', ['count' => count($bufferCards)]) ?></span>
        </div>
        <span class="logistics-tanker-icon" aria-hidden="true"></span>
    </div>
    <?php if ($bufferCards === []): ?>
        <p class="logistics-empty"><?= t('logistics.design.tanker_buffer_empty') ?></p>
    <?php else: ?>
        <div class="logistics-tanker-list">
            <?php foreach ($bufferCards as $buffer):
                $bufferBbl = max(0.0, (float)($buffer['marine_buffer_bbl'] ?? 0));
                $thresholdBbl = max(0.0, (float)($buffer['min_load_bbl'] ?? $marineMinLoadBbl));
                $missingBbl = max(0.0, $thresholdBbl - $bufferBbl);
                $bufferPct = $thresholdBbl > 0 ? min(100.0, round($bufferBbl / $thresholdBbl * 100, 1)) : 100.0;
                $wellLabel = !empty($buffer['well_name'])
                    ? (string)$buffer['well_name']
                    : t('marine.well_unknown', ['id' => (int)($buffer['well_id'] ?? 0)]);
            ?>
            <article class="logistics-tanker-card">
                <h4><?= htmlspecialchars($wellLabel, ENT_QUOTES, 'UTF-8') ?></h4>
                <p class="logistics-tanker-volume"><strong><?= number_format($bufferBbl, 1, ',', ' ') ?></strong> / <?= number_format($thresholdBbl, 0, ',', ' ') ?> <?= t('common.bbl') ?></p>
                <div class="logistics-tanker-progress" role="progressbar" aria-label="<?= htmlspecialchars(t('marine.col_progress'), ENT_QUOTES, 'UTF-8') ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $bufferPct ?>"><span data-progress-width="<?= $bufferPct ?>"></span></div>
                <p class="logistics-tanker-meta"><span><?= t('logistics.design.tanker_fill', ['pct' => number_format($bufferPct, 1, ',', ' ')]) ?></span><strong><?= $missingBbl > 0 ? t('marine.buffer_missing', ['bbl' => number_format($missingBbl, 1, ',', ' ')]) : t('marine.buffer_ready') ?></strong></p>
            </article>
            <?php endforeach ?>
        </div>
    <?php endif ?>
</section>
