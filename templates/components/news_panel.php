<?php
// News panel component / Komponent panelu aktualnosci
?>
<section class="card news-panel" aria-labelledby="news-heading">
    <h2 id="news-heading"><?= t('news.panel_title') ?></h2>
    <div class="news-list" id="newsList"
         data-empty="<?= htmlspecialchars(tPlain('news.empty'), ENT_QUOTES, 'UTF-8') ?>"
         data-error="<?= htmlspecialchars(tPlain('news.load_error'), ENT_QUOTES, 'UTF-8') ?>">
        <p class="news-loading"><?= t('common.loading') ?></p>
    </div>
</section>
