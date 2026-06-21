<?php /** Zakładka: Definicje cookies — zmienne z $viewData przekazanego przez extract() */ ?>

<div class="privacy-section">
    <div class="section-header">
        <h2><?= t('privacy.cookies.tab_heading') ?></h2>
        <a href="?tab=cookies&add=1" class="btn btn-primary btn-sm"><?= t('privacy.cookies.btn_add') ?></a>
    </div>

    <?php if (isset($add) && $add || isset($_GET['add'])): ?>
    <?php $isEdit = false; $formRow = null; ?>
    <?php elseif ($edit_row): ?>
    <?php $isEdit = true; $formRow = $edit_row; ?>
    <?php else: ?>
    <?php $isEdit = false; $formRow = null; ?>
    <?php endif ?>

    <?php if (isset($_GET['add']) || $edit_row): ?>
    <div class="card form-card">
        <h3><?= $edit_row ? t('privacy.cookies.btn_edit') : t('privacy.cookies.btn_add') ?></h3>
        <form method="post" action="?tab=cookies<?= $edit_row ? '&edit='.(int)$edit_row['id'] : '' ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="tab" value="cookies">
            <input type="hidden" name="action" value="<?= $edit_row ? 'cookie_update' : 'cookie_create' ?>">
            <?php if ($edit_row): ?>
                <input type="hidden" name="id" value="<?= (int)$edit_row['id'] ?>">
            <?php endif ?>

            <div class="form-grid">
                <div class="form-group">
                    <label><?= t('privacy.cookies.label_name') ?> *</label>
                    <input type="text" name="name" required maxlength="200"
                           value="<?= htmlspecialchars($edit_row['name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label><?= t('privacy.cookies.label_category') ?> *</label>
                    <select name="category" required>
                        <?php foreach ($valid_categories as $cat): ?>
                            <option value="<?= $cat ?>"
                                <?= ($edit_row['category'] ?? '') === $cat ? 'selected' : '' ?>>
                                <?= t('privacy.category.' . $cat) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= t('privacy.cookies.label_provider') ?></label>
                    <input type="text" name="provider" maxlength="200"
                           value="<?= htmlspecialchars($edit_row['provider'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label><?= t('privacy.cookies.label_duration') ?></label>
                    <input type="text" name="duration" maxlength="100"
                           value="<?= htmlspecialchars($edit_row['duration'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label><?= t('privacy.cookies.label_type') ?></label>
                    <select name="type">
                        <?php foreach ($valid_types as $tp): ?>
                            <option value="<?= $tp ?>"
                                <?= ($edit_row['type'] ?? 'cookie') === $tp ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tp) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= t('privacy.cookies.label_key') ?></label>
                    <input type="text" name="cookie_key" maxlength="200"
                           value="<?= htmlspecialchars($edit_row['cookie_key'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label><?= t('privacy.cookies.label_purpose') ?></label>
                <textarea name="purpose" rows="3"><?= htmlspecialchars($edit_row['purpose'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label><?= t('privacy.cookies.label_notes') ?></label>
                <textarea name="notes" rows="2"><?= htmlspecialchars($edit_row['notes'] ?? '') ?></textarea>
            </div>

            <div class="form-checkboxes">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_required" value="1"
                        <?= ($edit_row['is_required'] ?? 0) ? 'checked' : '' ?>>
                    <?= t('privacy.cookies.label_required') ?>
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="is_active" value="1"
                        <?= ($edit_row['is_active'] ?? 1) ? 'checked' : '' ?>>
                    <?= t('privacy.cookies.label_active') ?>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?= t('privacy.cookies.btn_save') ?></button>
                <a href="?tab=cookies" class="btn btn-secondary"><?= t('privacy.cookies.btn_cancel') ?></a>
            </div>
        </form>
    </div>
    <?php endif ?>

    <div class="filter-bar">
        <form method="get" action="">
            <input type="hidden" name="tab" value="cookies">
            <select name="filter_category" onchange="this.form.submit()">
                <option value=""><?= t('privacy.cookies.col_category') ?>: <?= t('common.all') ?></option>
                <?php foreach ($valid_categories as $cat): ?>
                    <option value="<?= $cat ?>" <?= ($filters['category'] ?? '') === $cat ? 'selected' : '' ?>>
                        <?= t('privacy.category.' . $cat) ?>
                    </option>
                <?php endforeach ?>
            </select>
            <select name="filter_active" onchange="this.form.submit()">
                <option value=""><?= t('privacy.cookies.col_active') ?>: <?= t('common.all') ?></option>
                <option value="1" <?= ($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>Tak</option>
                <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>Nie</option>
            </select>
        </form>
    </div>

    <?php if (empty($cookies)): ?>
        <p class="muted"><?= t('privacy.cookies.empty') ?></p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= t('privacy.cookies.col_name') ?></th>
                    <th><?= t('privacy.cookies.col_category') ?></th>
                    <th><?= t('privacy.cookies.col_provider') ?></th>
                    <th><?= t('privacy.cookies.col_duration') ?></th>
                    <th><?= t('privacy.cookies.col_type') ?></th>
                    <th><?= t('privacy.cookies.col_required') ?></th>
                    <th><?= t('privacy.cookies.col_active') ?></th>
                    <th><?= t('privacy.cookies.col_actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cookies as $c): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($c['name']) ?></strong>
                        <?php if ($c['cookie_key']): ?>
                            <br><small class="muted"><?= htmlspecialchars($c['cookie_key']) ?></small>
                        <?php endif ?>
                        <?php if ($c['purpose']): ?>
                            <br><small><?= htmlspecialchars(mb_substr($c['purpose'], 0, 80)) ?></small>
                        <?php endif ?>
                    </td>
                    <td><span class="badge badge-<?= htmlspecialchars($c['category']) ?>"><?= t('privacy.category.' . $c['category']) ?></span></td>
                    <td><?= htmlspecialchars($c['provider']) ?></td>
                    <td><?= htmlspecialchars($c['duration']) ?></td>
                    <td><?= htmlspecialchars($c['type']) ?></td>
                    <td><?= $c['is_required'] ? '✅' : '—' ?></td>
                    <td><?= $c['is_active']   ? '✅' : '❌' ?></td>
                    <td class="actions">
                        <a href="?tab=cookies&edit=<?= (int)$c['id'] ?>" class="btn btn-sm btn-secondary"><?= t('privacy.cookies.btn_edit') ?></a>
                        <form method="post" style="display:inline">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="tab" value="cookies">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <?php if ($c['is_active']): ?>
                                <input type="hidden" name="action" value="cookie_deactivate">
                                <button type="submit" class="btn btn-sm btn-warning"><?= t('privacy.cookies.btn_deactivate') ?></button>
                            <?php else: ?>
                                <input type="hidden" name="action" value="cookie_activate">
                                <button type="submit" class="btn btn-sm btn-success"><?= t('privacy.cookies.btn_activate') ?></button>
                            <?php endif ?>
                        </form>
                        <?php if (!$c['is_required']): ?>
                        <form method="post" style="display:inline"
                              onsubmit="return confirm('<?= t('privacy.cookies.confirm_delete') ?>')">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="tab" value="cookies">
                            <input type="hidden" name="action" value="cookie_delete">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger"><?= t('privacy.cookies.btn_delete') ?></button>
                        </form>
                        <?php endif ?>
                    </td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <?php endif ?>
</div>
