<?php /** Zakładka: Wersje polityk */ ?>

<div class="privacy-section">
    <div class="section-header">
        <h2><?= t('privacy.policy.tab_heading') ?></h2>
        <a href="?tab=policy&add_policy=1" class="btn btn-primary btn-sm"><?= t('privacy.policy.btn_add') ?></a>
    </div>

    <?php if (isset($_GET['add_policy']) || $edit_policy): ?>
    <div class="card form-card">
        <h3><?= $edit_policy ? t('privacy.policy.btn_edit') : t('privacy.policy.btn_add') ?></h3>
        <form method="post" action="?tab=policy">
            <?= CSRF::field() ?>
            <input type="hidden" name="tab" value="policy">
            <input type="hidden" name="action" value="<?= $edit_policy ? 'policy_update' : 'policy_create' ?>">
            <?php if ($edit_policy): ?>
                <input type="hidden" name="id" value="<?= (int)$edit_policy['id'] ?>">
            <?php endif ?>

            <?php if (!$edit_policy): ?>
            <div class="form-group">
                <label><?= t('privacy.policy.label_type') ?> *</label>
                <select name="policy_type" required>
                    <option value="cookies"><?= t('privacy.policy.type_cookies') ?></option>
                    <option value="privacy"><?= t('privacy.policy.type_privacy') ?></option>
                </select>
            </div>
            <div class="form-group">
                <label><?= t('privacy.policy.label_version') ?> *</label>
                <input type="text" name="version" required maxlength="20" placeholder="np. 1.1">
            </div>
            <?php endif ?>

            <div class="form-group">
                <label><?= t('privacy.policy.label_title') ?> *</label>
                <input type="text" name="title" required maxlength="300"
                       value="<?= htmlspecialchars($edit_policy['title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><?= t('privacy.policy.label_content') ?></label>
                <textarea name="content" rows="15"><?= htmlspecialchars($edit_policy['content'] ?? '') ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= t('privacy.policy.btn_save') ?></button>
                <a href="?tab=policy" class="btn btn-secondary"><?= t('privacy.policy.btn_cancel') ?></a>
            </div>
        </form>
    </div>
    <?php endif ?>

    <?php if (empty($policies)): ?>
        <p class="muted"><?= t('privacy.policy.empty') ?></p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= t('privacy.policy.col_type') ?></th>
                    <th><?= t('privacy.policy.col_version') ?></th>
                    <th><?= t('privacy.policy.col_title') ?></th>
                    <th><?= t('privacy.policy.col_active') ?></th>
                    <th><?= t('privacy.policy.col_published') ?></th>
                    <th><?= t('privacy.policy.col_actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($policies as $p): ?>
                <tr>
                    <td><?= $p['policy_type'] === 'cookies' ? t('privacy.policy.type_cookies') : t('privacy.policy.type_privacy') ?></td>
                    <td><?= htmlspecialchars($p['version']) ?></td>
                    <td><?= htmlspecialchars($p['title']) ?></td>
                    <td>
                        <?php if ($p['is_active']): ?>
                            <span class="badge badge-success"><?= t('privacy.policy.active_badge') ?></span>
                        <?php else: ?>
                            —
                        <?php endif ?>
                    </td>
                    <td><?= $p['published_at'] ? htmlspecialchars(substr($p['published_at'], 0, 10)) : '—' ?></td>
                    <td class="actions">
                        <?php if (!$p['is_active']): ?>
                        <a href="?tab=policy&edit_policy=<?= (int)$p['id'] ?>" class="btn btn-sm btn-secondary"><?= t('privacy.policy.btn_edit') ?></a>
                        <form method="post" style="display:inline">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="tab" value="policy">
                            <input type="hidden" name="action" value="policy_activate">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-primary"><?= t('privacy.policy.btn_activate') ?></button>
                        </form>
                        <?php else: ?>
                            <span class="muted small"><?= t('privacy.policy.active_cannot_edit_hint') ?></span>
                        <?php endif ?>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
</div>
