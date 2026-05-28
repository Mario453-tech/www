<?php
// Danger zone section / Sekcja niebezpieczna
?>
                <?php if (!in_array($status, ['seized','blowout','sold'])): ?>
                <div class="wg-danger-zone">
                    <div class="wg-danger-zone__label"><?= t('wg.danger_zone') ?></div>
                    <button type="button" class="wg-btn-sell wg-btn-sell--danger-zone" onclick="wgSellPreview(<?= $wid ?>)">
                        <?= wgIco('trash') ?> <?= t('wg.btn_sell_well') ?>
                    </button>
                </div>
                <?php endif ?>
