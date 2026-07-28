<?php if (($pagination['pages'] ?? 1) > 1): ?>
<nav class="hr-pagination" aria-label="<?= $esc(tPlain('admin.hr.pagination_label')) ?>">
    <?php for ($pageNumber = 1; $pageNumber <= (int)$pagination['pages']; $pageNumber++): ?>
    <?php $query = array_merge($_GET, ['page' => $pageNumber, 'tab' => $tab]); ?>
    <a href="?<?= $esc(http_build_query($query)) ?>" class="<?= (int)$pagination['page'] === $pageNumber ? 'active' : '' ?>">
        <?= $pageNumber ?>
    </a>
    <?php endfor ?>
</nav>
<?php endif ?>
