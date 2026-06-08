<?php
try {
    GameLog::info('admin_partial_footer', 'Render footer partial');
} catch (Throwable $e) {
    error_log('[admin/partials/footer] ' . $e->getMessage());
}
?>
</div><!-- /.admin-wrap -->
</div><!-- /.admin-layout -->
<script>
window.MODAL_LANG = <?= json_encode([
    'confirm'     => t('modal.confirm'),
    'cancel'      => t('modal.cancel'),
    'ok'          => t('modal.ok'),
    'title_error' => t('modal.title_error'),
    'title_info'  => t('modal.title_info'),
    'title_warn'  => t('modal.title_warn'),
    'title_success' => t('modal.title_success'),
    'close'         => t('modal.close'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= asset('/assets/js/modal.js') ?>"></script>
<?php foreach (($extraJs ?? []) as $src): ?>
<script src="<?= asset($src) ?>"></script>
<?php endforeach ?>
<script src="<?= asset('/assets/js/ajax_pagination.js') ?>"></script>
<script>
(function () {
    var burger = document.getElementById('admin-mobile-burger');
    var sidebar = document.getElementById('admin-sidebar');
    var overlay = document.getElementById('admin-sidebar-overlay');
    var body = document.body;
    if (!burger || !sidebar || !overlay || !body) return;

    function setOpen(isOpen) {
        body.classList.toggle('admin-nav-open', isOpen);
        burger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        burger.setAttribute('aria-label', isOpen ? '<?= t('admin.nav.mobile_close') ?>' : '<?= t('admin.nav.mobile_open') ?>');
    }

    burger.addEventListener('click', function () {
        setOpen(!body.classList.contains('admin-nav-open'));
    });

    overlay.addEventListener('click', function () {
        setOpen(false);
    });

    sidebar.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 980) {
                setOpen(false);
            }
        });
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 980) {
            setOpen(false);
        }
    });
})();
</script>
</body>
</html>
