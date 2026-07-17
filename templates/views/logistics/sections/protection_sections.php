    <!-- SEKCJA: Ochrona (transport drogowy, huby, rurociagi)                -->
    <!-- SECTION: Protection (road transport, hubs, pipelines)               -->
    <!--  -->
    <?php
    /**
     * Renderuje sekcje ochrony (tabela celow) + modal wyboru opcji dla danego typu celu.
     * Renders a protection section (target table) + option picker modal for a target type.
     */
    $renderProtectionSection = static function (
        string $targetKey,
        array $targets,
        array $options,
        string $colTargetLabel
    ): void {
        if ($targets === []) {
            return;
        }
        $headingId = 'logistics-protection-heading-' . $targetKey;
        $modalId   = 'protection-modal-' . $targetKey;
    ?>
    <section class="logistics-panel" aria-labelledby="<?= $headingId ?>">
        <div class="logistics-panel-head">
            <h3 id="<?= $headingId ?>"><?= t('protection.section_title_' . $targetKey) ?></h3>
            <span><?= t('protection.section_desc_' . $targetKey) ?></span>
        </div>
        <div class="logistics-table logistics-table--protection">
            <div class="logistics-table-head">
                <span><?= htmlspecialchars($colTargetLabel) ?></span>
                <span><?= t('protection.col_protection') ?></span>
                <span><?= t('protection.col_until') ?></span>
                <span><?= t('protection.col_action') ?></span>
            </div>
            <?php foreach ($targets as $protTarget): ?>
            <div class="logistics-table-row">
                <span><?= htmlspecialchars((string)$protTarget['name']) ?></span>
                <?php if ($protTarget['active'] !== null): ?>
                <span class="c-good"><?= htmlspecialchars($protTarget['active']['name']) ?></span>
                <span><?= htmlspecialchars(substr($protTarget['active']['ends_at'], 0, 16)) ?></span>
                <span>
                    <button type="button" class="btn btn-xs btn-secondary protection-add-btn"
                            data-target="<?= htmlspecialchars($targetKey) ?>"
                            data-target-id="<?= (int)$protTarget['id'] ?>"
                            data-renew="1">
                        <?= t('protection.btn_renew') ?>
                    </button>
                </span>
                <?php else: ?>
                <span class="c-muted2"><?= t('protection.status_none') ?></span>
                <span></span>
                <span>
                    <button type="button" class="btn btn-xs btn-primary protection-add-btn"
                            data-target="<?= htmlspecialchars($targetKey) ?>"
                            data-target-id="<?= (int)$protTarget['id'] ?>">
                        <?= t('protection.btn_add') ?>
                    </button>
                </span>
                <?php endif ?>
            </div>
            <?php endforeach ?>
        </div>
    </section>

    <div id="<?= $modalId ?>" class="logistics-modal-overlay protection-modal-overlay protection-modal" hidden>
        <div class="logistics-modal-box">
            <div class="logistics-modal-hdr">
                <span><?= t('protection.modal_title') ?></span>
                <button type="button" class="logistics-modal-close" data-protection-close></button>
            </div>
            <div class="protection-option-list">
                <?php foreach ($options as $protOpt):
                    $protDisabled = $protOpt['locked_reason'] !== null || !$protOpt['affordable'];
                    $protBlockReason = '';
                    if ($protOpt['locked_reason'] === 'credibility') {
                        $protBlockReason = t('protection.locked_credibility', ['min' => (int)$protOpt['min_company_credibility']]);
                    } elseif ($protOpt['locked_reason'] === 'legal_level') {
                        $protBlockReason = t('protection.locked_legal', ['min' => (int)$protOpt['min_legal_level']]);
                    } elseif (!$protOpt['affordable']) {
                        $protBlockReason = t('protection.not_affordable');
                    }
                ?>
                <article class="protection-option<?= $protDisabled ? ' protection-option--locked' : '' ?>">
                    <div class="protection-option__head">
                        <strong><?= htmlspecialchars((string)$protOpt['name']) ?></strong>
                        <span><?= number_format((float)$protOpt['cost'], 0, ',', ' ') ?> <?= $currencyLabel ?></span>
                    </div>
                    <p class="protection-option__desc"><?= htmlspecialchars((string)$protOpt['description']) ?></p>
                    <ul class="protection-option__effects">
                        <?php foreach ($protOpt['effect_lines'] as $effectLine): ?>
                        <li><?= htmlspecialchars($effectLine) ?></li>
                        <?php endforeach ?>
                    </ul>
                    <div class="protection-option__meta">
                        <span><?= t('protection.label_duration') ?> <?= t('protection.duration_minutes', ['min' => (int)$protOpt['duration_minutes']]) ?></span>
                        <span><?= t('protection.label_payment') ?></span>
                    </div>
                    <?php if ($protDisabled): ?>
                    <div class="protection-option__blocked"><?= $protBlockReason ?></div>
                    <?php else: ?>
                    <button type="button" class="btn btn-sm btn-primary protection-buy-btn"
                            data-option-code="<?= htmlspecialchars((string)$protOpt['code']) ?>"
                            data-option-name="<?= htmlspecialchars((string)$protOpt['name']) ?>"
                            data-option-cost="<?= number_format((float)$protOpt['cost'], 0, ',', ' ') ?>">
                        <?= t('protection.btn_buy') ?>
                    </button>
                    <?php endif ?>
                </article>
                <?php endforeach ?>
            </div>
            <div class="logistics-modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-protection-close>
                    <?= t('protection.btn_cancel') ?>
                </button>
            </div>
        </div>
    </div>
    <?php
    };

    $renderProtectionSection(
        'road',
        is_array($roadProtectionWells ?? null) ? $roadProtectionWells : [],
        is_array($roadProtectionOptions ?? null) ? $roadProtectionOptions : [],
        t('protection.col_well')
    );
    $renderProtectionSection(
        'hub',
        is_array($hubProtectionTargets ?? null) ? $hubProtectionTargets : [],
        is_array($hubProtectionOptions ?? null) ? $hubProtectionOptions : [],
        t('protection.col_hub')
    );
    $renderProtectionSection(
        'pipeline',
        is_array($pipelineProtectionTargets ?? null) ? $pipelineProtectionTargets : [],
        is_array($pipelineProtectionOptions ?? null) ? $pipelineProtectionOptions : [],
        t('protection.col_pipeline')
    );
    ?>
