<?php
$csrfField = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
?>

<h1><?= t('admin.news.heading') ?></h1>

<?php if ($msg): ?>
<div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
<?php endif ?>
<?php if ($err): ?>
<div class="alert alert-error"><?= htmlspecialchars($err) ?></div>
<?php endif ?>

<div class="news-admin-layout">

    <!-- FORMULARZ DODAJ / EDYTUJ -->
    <section class="panel news-form-panel">
        <p class="panel-title">
            <?= $editNews
                ? '<span class="news-form-mode news-form-mode--edit">✏️ ' . t('admin.news.edit_heading') . '</span>'
                : '<span class="news-form-mode">➕ ' . t('admin.news.add_heading') . '</span>'
            ?>
        </p>

        <form method="post" action="/admin/news.php" class="news-form">
            <?= $csrfField ?>
            <?php if ($editNews): ?>
            <input type="hidden" name="action"  value="edit">
            <input type="hidden" name="news_id" value="<?= (int)$editNews['id'] ?>">
            <?php else: ?>
            <input type="hidden" name="action" value="add">
            <?php endif ?>

            <div class="news-field">
                <label class="news-label"><?= t('admin.news.title_label') ?></label>
                <input type="text" name="title" class="news-input"
                       maxlength="120" required
                       placeholder="Tytuł aktualności…"
                       value="<?= htmlspecialchars($editNews['title'] ?? '') ?>">
            </div>

            <div class="news-field">
                <label class="news-label"><?= t('admin.news.content_label') ?></label>
                <textarea name="content" class="news-textarea" rows="7"
                          placeholder="Treść aktualności…" required><?= htmlspecialchars($editNews['content'] ?? '') ?></textarea>
            </div>

            <div class="news-form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editNews ? t('admin.news.submit_edit') : t('admin.news.submit_add') ?>
                </button>
                <?php if ($editNews): ?>
                <a href="/admin/news.php" class="btn btn-secondary"><?= t('admin.news.btn_cancel') ?></a>
                <?php endif ?>
            </div>
        </form>
    </section>

    <!-- LISTA NEWSÓW -->
    <section class="panel news-list-panel">
        <div class="panel-title-row">
            <p class="panel-title"><?= t('admin.news.heading') ?></p>
            <span class="news-count-badge"><?= count($newsList) ?></span>
        </div>

        <?php if (empty($newsList)): ?>
        <div class="news-empty">
            <span class="news-empty-icon">📭</span>
            <p><?= t('admin.news.list_empty') ?></p>
        </div>
        <?php else: ?>
        <div class="news-card-list">
            <?php foreach ($newsList as $n): ?>
            <article class="news-card<?= $n['is_pinned'] ? ' news-card--pinned' : '' ?>">

                <div class="news-card-header">
                    <div class="news-card-meta">
                        <span class="news-card-id">#<?= (int)$n['id'] ?></span>
                        <?php if ($n['is_pinned']): ?>
                        <span class="news-card-pin-badge">📌 <?= t('admin.news.status_pinned') ?></span>
                        <?php else: ?>
                        <span class="news-card-active-badge">✓ <?= t('admin.news.status_active') ?></span>
                        <?php endif ?>
                    </div>
                    <div class="news-card-date">
                        <span><?= date('d.m.Y', strtotime($n['created_at'])) ?></span>
                        <span class="news-card-time"><?= date('H:i', strtotime($n['created_at'])) ?></span>
                    </div>
                </div>

                <h3 class="news-card-title"><?= htmlspecialchars($n['title']) ?></h3>

                <p class="news-card-content"><?= htmlspecialchars(mb_strimwidth($n['content'], 0, 120, '…')) ?></p>

                <div class="news-card-footer">
                    <span class="news-card-author">👤 <?= htmlspecialchars($n['created_by']) ?></span>
                    <div class="news-card-actions">
                        <a href="/admin/news.php?edit=<?= (int)$n['id'] ?>"
                           class="btn btn-secondary btn-sm">✏️ <?= t('admin.news.btn_edit') ?></a>

                        <form method="post" action="/admin/news.php" class="form-inline">
                            <?= $csrfField ?>
                            <input type="hidden" name="action"  value="<?= $n['is_pinned'] ? 'unpin' : 'pin' ?>">
                            <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $n['is_pinned'] ? 'btn-secondary' : 'btn-news-pin' ?>">
                                <?= $n['is_pinned'] ? '📌 ' . t('admin.news.btn_unpin') : '📌 ' . t('admin.news.btn_pin') ?>
                            </button>
                        </form>

                        <form method="post" action="/admin/news.php" class="form-inline"
                              onsubmit="return confirm('<?= t('admin.news.delete_confirm') ?>')">
                            <?= $csrfField ?>
                            <input type="hidden" name="action"  value="delete">
                            <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">🗑 <?= t('admin.news.btn_delete') ?></button>
                        </form>
                    </div>
                </div>

            </article>
            <?php endforeach ?>
        </div>
        <?php endif ?>
    </section>

</div>
