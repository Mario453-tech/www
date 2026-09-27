<script src="/assets/js/admin_dashboard_flash.js" defer></script>
<?php
$csrfField = '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';
$showForm = $editNews !== null || (($_GET['add'] ?? '') === '1');
$pinnedCount = count(array_filter($newsList, static fn(array $item): bool => !empty($item['is_pinned'])));
?>

<main class="news-page"
      id="news-page"
      data-summary-template="<?= t('admin.news.showing_count') ?>"
      data-page-template="<?= t('admin.news.page_label') ?>"
      data-previous-label="<?= t('admin.news.previous_page') ?>"
      data-next-label="<?= t('admin.news.next_page') ?>">
    <div class="news-brandbar">
        <span class="news-brand"><span>OIL</span> EMPIRE</span>
        <span class="news-brand-divider" aria-hidden="true"></span>
        <span class="news-brand-caption"><?= t('admin.news.admin_panel') ?></span>
    </div>

    <header class="news-page-header">
        <div>
            <h1><?= t('admin.news.heading') ?></h1>
            <p><?= t('admin.news.subtitle') ?></p>
        </div>
        <a class="news-add-button" href="/admin/news.php?add=1">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v16M4 12h16"/></svg>
            <?= t('admin.news.submit_add') ?>
        </a>
    </header>

    <?php if ($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif ?>
    <?php if ($err): ?>
        <div class="alert alert-error"><?= htmlspecialchars($err, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif ?>

    <?php if ($showForm): ?>
    <section class="news-form-panel" id="news-form-panel" aria-labelledby="news-form-heading">
        <div class="news-form-heading">
            <h2 id="news-form-heading"><?= $editNews ? t('admin.news.edit_heading') : t('admin.news.add_heading') ?></h2>
            <a href="/admin/news.php" class="news-form-cancel"><?= t('admin.news.btn_cancel') ?></a>
        </div>
        <form method="post" action="/admin/news.php" class="news-form" id="admin-news-form">
            <?= $csrfField ?>
            <?php if ($editNews): ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="news_id" value="<?= (int)$editNews['id'] ?>">
            <?php else: ?>
                <input type="hidden" name="action" value="add">
            <?php endif ?>
            <div class="news-field">
                <label for="admin-news-title"><?= t('admin.news.title_label') ?></label>
                <div class="news-title-editor-wrap">
                    <textarea id="admin-news-title" name="title" class="news-textarea news-title-textarea" rows="4"
                              placeholder="<?= t('admin.news.placeholder_title') ?>"><?= htmlspecialchars($editNews['title_html'] ?? $editNews['title'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            </div>
            <div class="news-field">
                <label for="admin-news-content"><?= t('admin.news.content_label') ?></label>
                <div class="news-editor-wrap">
                    <textarea id="admin-news-content" name="content" class="news-textarea" rows="12"
                              placeholder="<?= t('admin.news.placeholder_content') ?>"><?= htmlspecialchars($editNews['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            </div>
            <button type="submit" class="news-add-button news-form-submit">
                <?= $editNews ? t('admin.news.submit_edit') : t('admin.news.submit_add') ?>
            </button>
        </form>
    </section>
    <?php endif ?>

    <div class="news-toolbar" hidden>
        <label class="news-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="7"/><path d="m16 16 5 5"/></svg>
            <span class="sr-only"><?= t('admin.news.search_placeholder') ?></span>
            <input id="news-search-input" type="search" autocomplete="off"
                   placeholder="<?= t('admin.news.search_placeholder') ?>">
        </label>
        <label class="news-select-wrap">
            <span class="sr-only"><?= t('admin.news.filter_label') ?></span>
            <select id="news-status-filter">
                <option value="all"><?= t('admin.news.filter_all') ?></option>
                <option value="active"><?= t('admin.news.filter_active') ?></option>
                <option value="pinned"><?= t('admin.news.filter_pinned') ?></option>
            </select>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 9 7 7 7-7"/></svg>
        </label>
        <label class="news-select-wrap news-sort-wrap">
            <span class="sr-only"><?= t('admin.news.sort_label') ?></span>
            <select id="news-sort">
                <option value="newest"><?= t('admin.news.sort_newest') ?></option>
                <option value="oldest"><?= t('admin.news.sort_oldest') ?></option>
            </select>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 9 7 7 7-7"/></svg>
        </label>
    </div>

    <nav class="news-tabs" aria-label="<?= t('admin.news.filter_label') ?>" hidden>
        <button type="button" class="news-tab is-active" data-news-tab="all" aria-current="page">
            <?= t('admin.news.tab_all') ?> <span><?= count($newsList) ?></span>
        </button>
        <button type="button" class="news-tab" data-news-tab="active">
            <?= t('admin.news.tab_active') ?> <span><?= count($newsList) ?></span>
        </button>
        <button type="button" class="news-tab" data-news-tab="pinned">
            <?= t('admin.news.tab_pinned') ?> <span><?= $pinnedCount ?></span>
        </button>
    </nav>

    <section class="news-results" aria-label="<?= t('admin.news.heading') ?>">
        <?php if (empty($newsList)): ?>
            <p class="news-empty"><?= t('admin.news.list_empty') ?></p>
        <?php else: ?>
            <div class="news-list" id="news-list">
                <?php foreach ($newsList as $n): ?>
                    <article class="news-row<?= $n['is_pinned'] ? ' news-row--pinned' : '' ?>"
                             data-news-item
                             data-news-active="1"
                             data-news-pinned="<?= (int)(bool)$n['is_pinned'] ?>"
                             data-news-date="<?= htmlspecialchars((string)$n['created_at'], ENT_QUOTES, 'UTF-8') ?>"
                             data-news-search="<?= htmlspecialchars(($n['title_plain'] ?? strip_tags($n['title_html'])) . ' ' . $n['content_plain'] . ' ' . $n['created_by'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="news-row-badges">
                            <?php if ($n['is_pinned']): ?>
                                <span class="news-badge news-badge--pinned">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 3 8 0-1 5 3 4v2H6v-2l3-4-1-5ZM12 14v7"/></svg>
                                    <?= t('admin.news.status_pinned') ?>
                                </span>
                            <?php endif ?>
                            <span class="news-badge news-badge--active"><span class="news-status-dot" aria-hidden="true"></span><?= t('admin.news.status_active') ?></span>
                        </div>
                        <div class="news-row-main">
                            <div class="news-row-title"><?= $n['title_html'] ?></div>
                            <p class="news-row-summary"><?= htmlspecialchars($n['content_plain'], ENT_QUOTES, 'UTF-8') ?></p>
                            <div class="news-row-meta">
                                <span>#<?= (int)$n['id'] ?></span>
                                <span><?= date('d.m.Y, H:i', strtotime($n['created_at'])) ?></span>
                                <span><?= htmlspecialchars($n['created_by'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                        <div class="news-row-actions">
                            <a href="/admin/news.php?edit=<?= (int)$n['id'] ?>" class="news-action news-action--edit">
                                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 16 12-12 4 4L8 20H4v-4ZM14 6l4 4"/></svg>
                                <?= t('admin.news.btn_edit') ?>
                            </a>
                            <form method="post" action="/admin/news.php">
                                <?= $csrfField ?>
                                <input type="hidden" name="action" value="<?= $n['is_pinned'] ? 'unpin' : 'pin' ?>">
                                <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
                                <button type="submit" class="news-action news-action--pin">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m8 3 8 0-1 5 3 4v2H6v-2l3-4-1-5ZM12 14v7"/><?php if ($n['is_pinned']): ?><path d="M3 21 21 3" class="news-icon-slash"/><?php endif ?></svg>
                                    <?= $n['is_pinned'] ? t('admin.news.btn_unpin') : t('admin.news.btn_pin') ?>
                                </button>
                            </form>
                            <form method="post" action="/admin/news.php"
                                  data-confirm="<?= t('admin.news.delete_confirm') ?>" data-confirm-type="danger">
                                <?= $csrfField ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
                                <button type="submit" class="news-action news-action--delete">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3m3 0-1 13H7L6 7m4 4v6m4-6v6"/></svg>
                                    <?= t('admin.news.btn_delete') ?>
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach ?>
            </div>
            <p class="news-empty" id="news-no-matches" role="status" hidden><?= t('admin.news.no_matches') ?></p>
            <footer class="news-pagination-footer" id="news-pagination-footer" hidden>
                <p id="news-range" aria-live="polite"></p>
                <nav id="news-pages" aria-label="<?= t('admin.news.pagination_label') ?>"></nav>
            </footer>
        <?php endif ?>
    </section>
</main>

<?php if ($showForm): ?>
    <script src="https://cdn.tiny.cloud/1/n2m8igiixgfiasr4l4gha8fjz6hxp12sudqgnecovtt6y2nq/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script src="/assets/js/admin_news_editor.js"></script>
<?php endif ?>
<script src="<?= asset('/assets/js/admin_news_list.js') ?>"></script>
