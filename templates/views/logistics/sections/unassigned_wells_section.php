    <!--  -->
    <!-- SEKCJA: Odwierty bez huba                                          -->
    <!--  -->
    <?php if (!empty($hubUnassigned)): ?>
    <section class="logistics-panel logistics-panel--warn" aria-labelledby="logistics-unassigned-heading">
        <div class="logistics-panel-head">
            <h3 id="logistics-unassigned-heading"><?= t('logistics.hub.unassigned_title') ?></h3>
            <span><?= t('logistics.hub.unassigned_desc') ?></span>
        </div>
        <div class="logistics-table">
            <div class="logistics-table-head">
                <span><?= t('logistics.col_well') ?></span>
                <span><?= t('logistics.hub.label_region') ?></span>
                <span><?= t('logistics.col_capacity') ?></span>
                <span></span>
            </div>
            <?php foreach ($hubUnassigned as $uw):
                $uwCooldownUntil  = $uw['cooldown_until'] ?? null;
                $uwCooldownActive = $uwCooldownUntil && strtotime($uwCooldownUntil) > time();
                $uwCooldownSecs   = $uwCooldownActive ? max(0, strtotime($uwCooldownUntil) - time()) : 0;
                $uwCooldownH      = intdiv($uwCooldownSecs, 3600);
                $uwCooldownM      = intdiv($uwCooldownSecs % 3600, 60);
                $uwCooldownLabel  = $uwCooldownH > 0 ? "{$uwCooldownH}h {$uwCooldownM}min" : "{$uwCooldownM}min";
            ?>
            <div class="logistics-table-row">
                <span>#<?= (int)$uw['id'] ?> <?= htmlspecialchars($uw['name'] ?? $uw['location_name'] ?? '') ?></span>
                <span><?= htmlspecialchars($uw['region_name'] ?? (($locale === 'en' ? 'Region #' : 'Region #') . $uw['region_id'])) ?>
                    <?= ($uw['zone_key'] ?? '') !== '' ? '/ ' . htmlspecialchars($uw['zone_key']) : '' ?>
                </span>
                <span><?= number_format((float)$uw['base_production_per_hour'], 1, ',', ' ') ?> bph</span>
                <span>
                    <?php if ($uwCooldownActive): ?>
                    <span class="hub-cooldown-badge"
                          title="<?= t('logistics.hub.cooldown_title') ?>"
                          data-cooldown-until="<?= htmlspecialchars($uwCooldownUntil) ?>">
                        <?= $uwCooldownLabel ?>
                    </span>
                    <?php else: ?>
                    <button class="btn btn-xs btn-primary" type="button" data-hub-action="assign" data-well-id="<?= (int)$uw['id'] ?>">
                         <?= t('logistics.hub.unassigned_assign') ?>
                    </button>
                    <?php endif ?>
                </span>
            </div>
            <?php endforeach ?>
        </div>
        <?php if ($unassignedTotalPages > 1): ?>
        <div class="logistics-pagination">
            <div class="logistics-pagination-info">
                <?= $unassignedPage ?> / <?= $unassignedTotalPages ?> (<?= $unassignedTotal ?>)
            </div>
            <div class="logistics-pagination-buttons">
                <?php if ($unassignedPage > 1): ?>
                <a href="?tab=logistics&unassigned_page=<?= $unassignedPage - 1 ?>" class="btn btn-xs btn-secondary">
                     <?= t('logistics.pagination_prev') ?>
                </a>
                <?php endif ?>
                <?php if ($unassignedPage < $unassignedTotalPages): ?>
                <a href="?tab=logistics&unassigned_page=<?= $unassignedPage + 1 ?>" class="btn btn-xs btn-secondary">
                    <?= t('logistics.pagination_next') ?>
                </a>
                <?php endif ?>
            </div>
        </div>
        <?php endif ?>
    </section>
    <?php endif ?>
