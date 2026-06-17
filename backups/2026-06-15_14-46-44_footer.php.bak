<?php if ($authPage ?? false): ?>
    </div><?php /* close .auth-bg */ ?>
    <?php if (!empty($extraJs)): foreach ((array)$extraJs as $__js): ?>
    <script src="<?= htmlspecialchars(asset($__js)) ?>"></script>
    <?php endforeach; endif; ?>
</body>
</html>
<?php return; /* stop footer rendering for auth pages */ endif; ?>
</main>

        <footer class="footer" role="contentinfo">
            <?php
                $__footerText  = '&copy; {year} OilCorp. Wszystkie prawa zastrzeżone.';
                $__footerJs    = '/assets/js/game.js';
                $__footerLinks = [];
                try {
                    $__fDb  = Database::getInstance()->getConnection();
                    $__fCfg = $__fDb->query("SELECT `key`, `value` FROM site_config WHERE `key` IN ('footer_text','footer_js')")->fetchAll(PDO::FETCH_KEY_PAIR);
                    if (!empty($__fCfg['footer_text'])) $__footerText = $__fCfg['footer_text'];
                    if (isset($__fCfg['footer_js']))    $__footerJs   = $__fCfg['footer_js'];
                    $__footerLinks = $__fDb->query("SELECT label, url_key, icon, css_class FROM nav_items WHERE location='footer' AND active=1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
                } catch (Throwable $__fEx) { /* fallback / fallback */ }
            ?>
            <?php if (!empty($__footerLinks)): ?>
            <nav class="footer-nav" aria-label="Linki stopki">
                <?php foreach ($__footerLinks as $__fl):
                    $__flKey  = $__fl['url_key'];
                    $__flHref = (str_starts_with($__flKey, '/')) ? $__flKey : (function_exists('url') ? url($__flKey) : '/' . $__flKey);
                    $__flCss  = $__fl['css_class'] ? ' ' . htmlspecialchars($__fl['css_class']) : '';
                    $__icon   = trim((string)($__fl['icon'] ?? ''));
                ?>
                <a href="<?= htmlspecialchars($__flHref) ?>" class="footer-link<?= $__flCss ?>">
                    <?php if ($__icon !== ''): ?><span class="footer-link-icon"><?= htmlspecialchars($__icon) ?></span><?php endif ?>
                    <span><?= htmlspecialchars($__fl['label']) ?></span>
                </a>
                <?php endforeach ?>
            </nav>
            <?php endif ?>
            <p><?= str_replace('{year}', date('Y'), $__footerText) ?></p>
        </footer>
    </div>

    <?php if ($__footerJs ?? '/assets/js/game.js'): ?>
    <script>
    window.GAME_LANG = <?= json_encode([
        'confirm_sell_oil' => t('game_js.confirm_sell_oil'),
    ], JSON_UNESCAPED_UNICODE) ?>;
    </script>
    <script src="<?= htmlspecialchars(asset($__footerJs ?? '/assets/js/game.js')) ?>"></script>
    <?php endif ?>
    <script src="<?= htmlspecialchars(asset('/assets/js/ajax_pagination.js')) ?>"></script>
    <?php if (!empty($extraJs)): foreach ((array)$extraJs as $__js): ?>
    <script src="<?= htmlspecialchars(asset($__js)) ?>"></script>
    <?php endforeach; endif; ?>
</body>
</html>
