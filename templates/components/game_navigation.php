<nav class="user-nav user-nav--bar game-nav" id="user-nav" aria-label="<?= t('header.nav_aria') ?>">
    <div class="game-nav__primary">
        <?php if ($__homeNav): ?><a class="game-nav__item<?= in_array($__curPath, ['/', '/index.php'], true) ? ' nav-active' : '' ?>" href="<?= url('home') ?>"<?= in_array($__curPath, ['/', '/index.php'], true) ? ' aria-current="page"' : '' ?>>
            <img src="<?= asset(PlayerNavIcon::url($__homeNav['icon'])) ?>" alt=""><span><?= htmlspecialchars($__homeNav['label'], ENT_QUOTES, 'UTF-8') ?></span>
        </a><?php endif; ?>
        <?php foreach ($__groupedNav as $__groupId => $__group): if (!$__group['items']) continue; ?>
        <button class="game-nav__item game-nav__toggle<?= $__group['active'] ? ' nav-active' : '' ?>" type="button"
                data-nav-group="<?= htmlspecialchars($__groupId, ENT_QUOTES, 'UTF-8') ?>"
                aria-expanded="<?= $__group['active'] ? 'true' : 'false' ?>"
                aria-controls="nav-context-<?= htmlspecialchars($__groupId, ENT_QUOTES, 'UTF-8') ?>">
            <img src="<?= asset(PlayerNavIcon::url($__group['icon'])) ?>" alt="">
            <span><?= htmlspecialchars($__group['label'], ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <?php endforeach; ?>
        <span class="game-nav__spacer"></span>
        <?php if ($__helpNav): ?><a class="game-nav__item" href="<?= url('help') ?>"><img src="<?= asset(PlayerNavIcon::url($__helpNav['icon'])) ?>" alt=""><span><?= htmlspecialchars($__helpNav['label'], ENT_QUOTES, 'UTF-8') ?></span></a><?php endif; ?>
        <?php if ($__chatNav): ?><a class="game-nav__item<?= $__curPath === '/chat' ? ' nav-active' : '' ?>" href="<?= url('chat') ?>"><img src="<?= asset(PlayerNavIcon::url($__chatNav['icon'])) ?>" alt=""><span><?= htmlspecialchars($__chatNav['label'], ENT_QUOTES, 'UTF-8') ?></span></a><?php endif; ?>
    </div>
    <?php foreach ($__groupedNav as $__groupId => $__group): if (!$__group['items']) continue; ?>
    <div class="game-nav__context" id="nav-context-<?= htmlspecialchars($__groupId, ENT_QUOTES, 'UTF-8') ?>" data-nav-panel="<?= htmlspecialchars($__groupId, ENT_QUOTES, 'UTF-8') ?>"<?= $__group['active'] ? '' : ' hidden' ?>>
        <span class="game-nav__group-label"><?= htmlspecialchars($__group['label'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php foreach ($__group['items'] as $__link): ?>
        <a href="<?= htmlspecialchars($__link['href'], ENT_QUOTES, 'UTF-8') ?>" class="game-nav__subitem<?= $__link['active'] ? ' nav-active' : '' ?>"<?= $__link['active'] ? ' aria-current="page"' : '' ?>><img src="<?= asset(PlayerNavIcon::url($__link['icon'])) ?>" alt=""><?= htmlspecialchars($__link['label'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</nav>
