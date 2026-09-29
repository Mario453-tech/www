<section class="logistics-panel logistics-decisions" aria-labelledby="logistics-decisions-heading">
    <div class="logistics-panel-head">
        <div><h3 id="logistics-decisions-heading"><?= t('logistics.design.decisions') ?></h3><span><?= t('logistics.design.decisions_desc') ?></span></div>
    </div>
    <ol class="logistics-decisions-list">
        <?php foreach (array_slice($logisticsInsights['recommendations'] ?? [], 0, 2) as $item): ?>
        <li class="logistics-decisions-item logistics-decisions-item--<?= htmlspecialchars((string)$item['tone'], ENT_QUOTES, 'UTF-8') ?>">
            <div><strong><?= htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8') ?></strong><span><?= htmlspecialchars((string)$item['text'], ENT_QUOTES, 'UTF-8') ?></span></div>
            <a href="<?= htmlspecialchars((string)$item['cta_href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$item['cta_label'], ENT_QUOTES, 'UTF-8') ?> →</a>
        </li>
        <?php endforeach ?>
    </ol>
</section>
