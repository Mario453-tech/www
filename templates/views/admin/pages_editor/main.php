<?php extract($viewData, EXTR_SKIP); ?>

<link rel="stylesheet" href="/assets/css/help_editor.css">

<h1> <?= t('admin.pages_editor.heading') ?></h1>
<p class="muted he-desc">
    <?= t('admin.pages_editor.desc') ?> <code>/slug</code> <?= t('admin.pages_editor.desc_auto') ?>
</p>

<?php if ($msg): ?><p class="alert alert-success"> <?= $msg ?></p><?php endif ?>
<?php if ($err): ?><p class="alert alert-error"> <?= htmlspecialchars($err) ?></p><?php endif ?>

<div class="he-layout">

<!--  SIDEBAR  -->
<aside>
    <div class="he-sidebar">
        <div class="he-sidebar-hdr"><?= t('admin.pages_editor.sidebar_header', ['count' => count($pages)]) ?></div>
        <ul class="he-list">
        <?php foreach ($pages as $p): ?>
            <li>
                <a href="?edit=<?= $p['id'] ?>" class="<?= (int)$p['id'] === $editId ? 'active' : '' ?>">
                    <span><?= htmlspecialchars($p['icon']) ?></span>
                    <span><?= htmlspecialchars($p['title']) ?></span>
                    <?php if (!$p['active']): ?>
                        <span class="he-badge"><?= t('admin.pages_editor.badge_hidden') ?></span>
                    <?php else: ?>
                        <span class="he-ok"></span>
                    <?php endif ?>
                </a>
            </li>
        <?php endforeach ?>
        </ul>

        <div class="he-add-form">
            <form method="post">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="add">
                <label class="he-form-label" for="new_slug"><?= t('admin.pages_editor.label_slug') ?></label>
                <input type="text" id="new_slug" name="new_slug" placeholder="<?= t('admin.pages_editor.placeholder_slug') ?>" pattern="[a-z0-9\-]+" required>
                <label class="he-form-label" for="new_title"><?= t('admin.pages_editor.label_title') ?></label>
                <input type="text" id="new_title" name="new_title" placeholder="<?= t('admin.pages_editor.placeholder_title') ?>" required>
                <label class="he-form-label" for="new_icon"><?= t('admin.pages_editor.label_icon') ?></label>
                <input type="text" id="new_icon" name="new_icon" placeholder="" maxlength="4" value="">
                <button type="submit" class="btn btn-secondary he-add-btn">+ <?= t('admin.pages_editor.btn_add') ?></button>
            </form>
        </div>
    </div>
</aside>

<!--  EDITOR  -->
<div class="he-editor">
<?php if ($editPage): ?>
    <h2><?= htmlspecialchars($editPage['icon'] . ' ' . $editPage['title']) ?></h2>
    <p class="muted he-meta-info">
        URL: <a href="/<?= htmlspecialchars($editPage['slug']) ?>" target="_blank"><code>/<?= htmlspecialchars($editPage['slug']) ?></code></a>
        &nbsp;·&nbsp; <?= t('admin.pages_editor.last_change') ?>: <?= $editPage['updated_at'] ?>
        <?php if ($editPage['updated_by']): ?>&nbsp;(<?= htmlspecialchars($editPage['updated_by']) ?>)<?php endif ?>
    </p>

    <form method="post" id="editForm">
        <?= CSRF::field() ?>
        <input type="hidden" name="action"  value="save">
        <input type="hidden" name="page_id" value="<?= (int)$editPage['id'] ?>">

        <div class="he-meta">
            <div>
                <label for="edit_icon"><?= t('admin.pages_editor.label_icon') ?></label>
                <input type="text" id="edit_icon" name="icon" value="<?= htmlspecialchars($editPage['icon']) ?>" maxlength="4">
            </div>
            <div>
                <label for="edit_title"><?= t('admin.pages_editor.label_page_title') ?></label>
                <input type="text" id="edit_title" name="title" value="<?= htmlspecialchars($editPage['title']) ?>" required>
            </div>
            <div>
                <label for="edit_sort_order"><?= t('admin.pages_editor.label_order') ?></label>
                <input type="number" id="edit_sort_order" name="sort_order" value="<?= (int)$editPage['sort_order'] ?>" min="0">
            </div>
            <div>
                <label for="edit_active"><?= t('admin.pages_editor.label_visible') ?></label>
                <input type="checkbox" id="edit_active" name="active" value="1" <?= $editPage['active'] ? 'checked' : '' ?> class="he-checkbox">
            </div>
        </div>

        <div class="he-tinymce">
            <textarea id="tinymce-content" name="content"><?= htmlspecialchars($editPage['content']) ?></textarea>
        </div>

        <div class="form-row">
            <button type="submit" class="btn btn-primary"> <?= t('admin.pages_editor.btn_save') ?></button>
            <a href="/<?= htmlspecialchars($editPage['slug']) ?>" target="_blank" class="btn btn-secondary"> <?= t('admin.pages_editor.btn_preview') ?></a>
            <button type="button" class="btn btn-danger"
                onclick="if(confirm('<?= t('admin.pages_editor.confirm_delete') ?>')) document.getElementById('deletePageForm').submit()">
                 <?= t('admin.pages_editor.btn_delete') ?>
            </button>
        </div>
    </form>

    <form method="post" id="deletePageForm" class="hidden">
        <?= CSRF::field() ?>
        <input type="hidden" name="action"  value="delete">
        <input type="hidden" name="page_id" value="<?= (int)$editPage['id'] ?>">
    </form>

<?php else: ?>
    <p class="muted he-empty"><?= t('admin.pages_editor.no_pages') ?></p>
<?php endif ?>
</div>

</div><!-- /.he-layout -->

<script src="https://cdn.tiny.cloud/1/n2m8igiixgfiasr4l4gha8fjz6hxp12sudqgnecovtt6y2nq/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script src="/assets/js/pages_editor.js"></script>
