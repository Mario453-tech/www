<h4><?= wwIcon('history') ?><?= t('ww.history') ?></h4>
<p class="ww-muted"><?= t('ww.history_scope') ?></p>
<label class="ww-filter"><?= t('ww.category') ?><select data-well-history-filter><?php foreach (['all','failures','service','production','transport','configuration'] as $category): ?><option value="<?= $category ?>"><?= t('ww.filter_' . $category) ?></option><?php endforeach ?></select></label>
<div data-well-history role="status" aria-live="polite"></div>
<div class="ww-pagination"><button class="ww-button" type="button" data-history-direction="-1" disabled><?= t('ww.newer') ?></button><span data-history-page></span><button class="ww-button" type="button" data-history-direction="1" disabled><?= t('ww.older') ?></button><button class="ww-button" type="button" data-history-retry><?= t('ww.refresh') ?></button></div>
