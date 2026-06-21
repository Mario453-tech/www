<?php /** Modal ustawień cookies — dołączany przez banner.php */ ?>

<div id="privacy-modal-overlay"
     class="privacy-modal-overlay"
     role="dialog"
     aria-modal="true"
     aria-labelledby="privacy-modal-title"
     aria-hidden="true"
     hidden>
    <div class="privacy-modal">
        <div class="privacy-modal__header">
            <h2 class="privacy-modal__title" id="privacy-modal-title">
                <?= htmlspecialchars(t('privacy.modal.title')) ?>
            </h2>
            <button type="button"
                    class="privacy-modal__close"
                    aria-label="<?= htmlspecialchars(t('privacy.modal.aria_close')) ?>">
                ✕
            </button>
        </div>

        <div class="privacy-modal__body">
            <p class="privacy-modal__intro"><?= htmlspecialchars(t('privacy.modal.intro')) ?></p>

            <?php foreach ($__privacyBannerData['categories'] as $cat): ?>
            <div class="privacy-category">
                <div class="privacy-category__info">
                    <p class="privacy-category__name"><?= htmlspecialchars($cat['label']) ?></p>
                    <p class="privacy-category__desc"><?= htmlspecialchars($cat['description']) ?></p>
                    <?php if ($cat['required']): ?>
                        <span class="privacy-required-badge"><?= htmlspecialchars(t('privacy.modal.required_badge')) ?></span>
                    <?php endif ?>
                </div>
                <label class="privacy-toggle" aria-label="<?= htmlspecialchars($cat['label']) ?>">
                    <input type="checkbox"
                           class="privacy-category-toggle"
                           value="<?= htmlspecialchars($cat['key']) ?>"
                           <?= $cat['required'] ? 'checked disabled' : '' ?>>
                    <span class="privacy-toggle__slider"></span>
                </label>
            </div>
            <?php endforeach ?>
        </div>

        <div class="privacy-modal__footer">
            <button type="button" id="privacy-modal-accept-all" class="privacy-btn privacy-btn--accept">
                <?= htmlspecialchars(t('privacy.modal.btn_accept_all')) ?>
            </button>
            <button type="button" id="privacy-modal-save" class="privacy-btn privacy-btn--decline">
                <?= htmlspecialchars(t('privacy.modal.btn_save')) ?>
            </button>
        </div>
    </div>
</div>
