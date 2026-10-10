<nav class="user-nav user-nav--bar game-nav" id="user-nav" aria-label="<?= t('header.nav_aria') ?>">
    <div class="game-nav__primary">
        <a class="game-nav__item<?= in_array($__curPath, ['/', '/index.php'], true) ? ' nav-active' : '' ?>" href="<?= url('home') ?>"<?= in_array($__curPath, ['/', '/index.php'], true) ? ' aria-current="page"' : '' ?>>
            <img src="<?= asset('/assets/img/icons/game-nav/home.svg') ?>" alt=""><span><?= t('nav.home') ?></span>
        </a>
        <?php foreach ($__groupedNav as $__groupId => $__group): if (!$__group['items']) continue; ?>
        <button class="game-nav__item game-nav__toggle<?= $__group['active'] ? ' nav-active' : '' ?>" type="button"
                data-nav-group="<?= htmlspecialchars($__groupId, ENT_QUOTES, 'UTF-8') ?>"
                aria-expanded="<?= $__group['active'] ? 'true' : 'false' ?>"
                aria-controls="nav-context-<?= htmlspecialchars($__groupId, ENT_QUOTES, 'UTF-8') ?>">
            <img src="<?= asset('/assets/img/icons/game-nav/' . ['operations' => 'mapa', 'business' => 'finanse', 'company' => 'dyrektor'][$__groupId] . '.svg') ?>" alt="">
            <span><?= htmlspecialchars($__group['label'], ENT_QUOTES, 'UTF-8') ?></span>
        </button>
        <?php endforeach; ?>
        <span class="game-nav__spacer"></span>
        <a class="game-nav__item" href="<?= url('help') ?>"><img src="<?= asset('/assets/img/icons/game-nav/pomoc.svg') ?>" alt=""><span><?= t('nav.help') ?></span></a>
        <a class="game-nav__item<?= $__curPath === '/chat' ? ' nav-active' : '' ?>" href="<?= url('chat') ?>"><img src="<?= asset('/assets/img/icons/game-nav/czat.svg') ?>" alt=""><span><?= t('nav.chat') ?></span></a>
    </div>
    <?php foreach ($__groupedNav as $__groupId => $__group): if (!$__group['items']) continue; ?>
    <div class="game-nav__context" id="nav-context-<?= htmlspecialchars($__groupId, ENT_QUOTES, 'UTF-8') ?>" data-nav-panel="<?= htmlspecialchars($__groupId, ENT_QUOTES, 'UTF-8') ?>"<?= $__group['active'] ? '' : ' hidden' ?>>
        <span class="game-nav__group-label"><?= htmlspecialchars($__group['label'], ENT_QUOTES, 'UTF-8') ?></span>
        <?php foreach ($__group['items'] as $__link): ?>
        <a href="<?= htmlspecialchars($__link['href'], ENT_QUOTES, 'UTF-8') ?>" class="game-nav__subitem<?= $__link['active'] ? ' nav-active' : '' ?>"<?= $__link['active'] ? ' aria-current="page"' : '' ?>><img src="<?= asset('/assets/img/icons/game-nav/' . $__link['icon'] . '.svg') ?>" alt=""><?= htmlspecialchars($__link['label'], ENT_QUOTES, 'UTF-8') ?></a>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
</nav>
