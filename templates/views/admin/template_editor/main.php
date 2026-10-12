<?php extract($viewData, EXTR_SKIP); ?>

<h1> <?= t('admin.template_editor.heading') ?></h1>
<p class="muted te-desc"><?= t('admin.template_editor.desc') ?></p>

<?php if ($msg): ?><p class="alert alert-success" role="status" aria-live="polite"> <?= htmlspecialchars($msg) ?></p><?php endif ?>
<?php if ($err): ?><p class="alert alert-error" role="alert"> <?= htmlspecialchars($err) ?></p><?php endif ?>

<div class="te-tabs">
    <a href="?tab=nav<?= $editNavId ? '&nav='.$editNavId : '' ?>" class="te-tab <?= $activeTab === 'nav' ? 'active' : '' ?>"> <?= t('admin.template_editor.tab_nav') ?></a>
    <a href="?tab=footer<?= $editNavId ? '&nav='.$editNavId : '' ?>" class="te-tab <?= $activeTab === 'footer' ? 'active' : '' ?>"> <?= t('admin.template_editor.tab_footer') ?></a>
    <a href="?tab=actions<?= $editNavId ? '&nav='.$editNavId : '' ?>" class="te-tab <?= $activeTab === 'actions' ? 'active' : '' ?>"> <?= t('admin.template_editor.tab_actions') ?></a>
    <a href="?tab=config" class="te-tab <?= $activeTab === 'config' ? 'active' : '' ?>"> <?= t('admin.template_editor.tab_config') ?></a>
    <a href="?tab=boardroom" class="te-tab <?= $activeTab === 'boardroom' ? 'active' : '' ?>"> <?= t('admin.template_editor.tab_boardroom') ?></a>
</div>

<?php if ($activeTab === 'nav'): ?>
<!--  ZAKADKA: NAWIGACJA  -->
<section class="te-player-preview" data-player-menu-preview aria-label="<?= t('admin.template_editor.player_menu_preview') ?>">
    <h2><?= t('admin.template_editor.player_menu_preview') ?></h2>
    <p class="muted"><?= t('admin.template_editor.player_menu_hint') ?></p>
    <div class="te-player-main">
        <?php if ($playerMenuSpecial['home']): ?>
            <a class="te-player-main-item" href="?tab=nav&amp;nav=<?= (int)$playerMenuSpecial['home']['id'] ?>"><img src="<?= asset(PlayerNavIcon::url($playerMenuSpecial['home']['icon'])) ?>" alt=""><span><?= htmlspecialchars($playerMenuSpecial['home']['label'], ENT_QUOTES, 'UTF-8') ?></span></a>
        <?php endif; ?>
        <?php foreach ($playerMenuPreview as $groupKey => $group): if (!$group['items']) continue; ?>
            <div class="te-player-group">
                <a class="te-player-main-item" href="?tab=nav&amp;group=<?= rawurlencode($groupKey) ?>"><img src="<?= asset(PlayerNavIcon::url($group['icon'])) ?>" alt=""><strong><?= htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8') ?></strong></a>
                <div class="te-player-links">
                <?php foreach ($group['items'] as $link): ?>
                    <a href="?tab=nav&nav=<?= (int)$link['id'] ?>"><img src="<?= asset(PlayerNavIcon::url($link['icon'])) ?>" alt=""><span><?= htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8') ?></span></a>
                <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php foreach (['help', 'chat'] as $special): if (!$playerMenuSpecial[$special]) continue; ?>
            <a class="te-player-main-item" href="?tab=nav&nav=<?= (int)$playerMenuSpecial[$special]['id'] ?>"><img src="<?= asset(PlayerNavIcon::url($playerMenuSpecial[$special]['icon'])) ?>" alt=""><span><?= htmlspecialchars($playerMenuSpecial[$special]['label'], ENT_QUOTES, 'UTF-8') ?></span></a>
        <?php endforeach; ?>
    </div>
    <p class="muted"><?= t('admin.template_editor.player_menu_visibility') ?></p>
</section>
<div class="te-layout">
    <aside>
        <div class="te-sidebar">
            <div class="te-sidebar-hdr"><?= t('admin.template_editor.main_groups') ?></div>
            <ul class="te-list">
                <?php foreach ($navGroups as $group): ?>
                <li><a href="?tab=nav&amp;group=<?= rawurlencode($group['group_key']) ?>" class="<?= ($editGroupKey === $group['group_key']) ? 'active' : '' ?>">
                    <img class="te-nav-icon" src="<?= asset(PlayerNavIcon::url(PlayerNavIcon::resolve((string)$group['icon_key'], (string)$group['group_key']))) ?>" alt="">
                    <span><?= htmlspecialchars(PlayerNavigationSettings::label($group), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (!$group['active']): ?><span class="te-badge"><?= t('admin.template_editor.badge_hidden') ?></span><?php endif; ?>
                </a></li>
                <?php endforeach; ?>
            </ul>
            <div class="te-sidebar-hdr"><?= t('admin.template_editor.sidebar_nav', ['count' => count($navItems)]) ?></div>
            <ul class="te-list">
            <?php foreach ($navItems as $n): ?>
                <li>
                    <a href="?tab=nav&nav=<?= $n['id'] ?>" class="<?= (int)$n['id'] === $editNavId ? 'active' : '' ?>">
                        <?php if (($n['url_key'] ?? '') !== 'logout'): ?><img class="te-nav-icon" src="<?= asset(PlayerNavIcon::url(PlayerNavIcon::resolve((string)($n['icon_key'] ?? ''), (string)$n['url_key']))) ?>" alt=""><?php endif; ?>
                        <span><?= htmlspecialchars(PlayerNavigationSettings::label($n), ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="te-sort">#<?= (int)$n['sort_order'] ?></span>
                        <?php if (!$n['active']): ?>
                            <span class="te-badge"><?= t('admin.template_editor.badge_hidden') ?></span>
                        <?php else: ?>
                            <span class="te-ok"></span>
                        <?php endif ?>
                    </a>
                </li>
            <?php endforeach ?>
            </ul>
            <div class="te-add-form">
                <h3><?= t('admin.template_editor.add_group') ?></h3>
                <form method="post" enctype="multipart/form-data">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="add_nav_group">
                    <label><?= t('admin.template_editor.label_label') ?></label>
                    <input type="text" name="group_label" maxlength="64" required>
                    <label><?= t('admin.template_editor.label_en') ?></label>
                    <input type="text" name="group_label_en" maxlength="64" required>
                    <label><?= t('admin.template_editor.label_de') ?></label>
                    <input type="text" name="group_label_de" maxlength="64" required>
                    <label><?= t('admin.template_editor.label_lang_key') ?></label>
                    <input type="text" name="group_lang_key" maxlength="64" placeholder="nav.group.xxx">
                    <label><?= t('admin.template_editor.label_order') ?></label>
                    <input type="number" name="group_sort_order" min="0" max="32000" value="40">
                    <label><?= t('admin.template_editor.icon_label') ?></label>
                    <div data-icon-picker><img data-icon-preview class="te-icon-preview" src="<?= asset(PlayerNavIcon::url('dashboard')) ?>" alt="">
                    <select name="group_icon_key" data-icon-select><option value="" data-icon-url="<?= asset(PlayerNavIcon::url('dashboard')) ?>"><?= t('admin.template_editor.icon_default') ?></option>
                    <?php foreach ($navIconChoices as $iconName): ?><option value="<?= htmlspecialchars($iconName, ENT_QUOTES, 'UTF-8') ?>" data-icon-url="<?= asset(PlayerNavIcon::url($iconName)) ?>"><?= htmlspecialchars($iconName, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select>
                    <label><?= t('admin.template_editor.icon_upload') ?></label>
                    <input type="file" name="icon_file" accept="image/png,image/jpeg,image/webp" data-icon-file></div>
                    <button class="btn btn-secondary te-add-btn" type="submit">+ <?= t('admin.template_editor.add_group') ?></button>
                </form>
            </div>
            <div class="te-add-form">
                <h3><?= t('admin.template_editor.add_subitem') ?></h3>
                <form method="post" enctype="multipart/form-data">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="add_nav">
                    <input type="hidden" name="new_location" value="header">
                    <label><?= t('admin.template_editor.label_label') ?></label>
                    <input type="text" name="new_label" maxlength="64" placeholder="<?= t('admin.template_editor.placeholder_label_nav') ?>" required>
                    <label><?= t('admin.template_editor.label_en') ?></label>
                    <input type="text" name="new_label_en" maxlength="64" required>
                    <label><?= t('admin.template_editor.label_de') ?></label>
                    <input type="text" name="new_label_de" maxlength="64" required>
                    <label><?= t('admin.template_editor.label_url_key') ?></label>
                    <select name="new_url_key" required>
                        <option value=""><?= t('admin.template_editor.choose_destination') ?></option>
                        <?php foreach ($navDestinations as $route): ?><option value="<?= htmlspecialchars($route, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars('/' . $route, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select>
                    <label><?= t('admin.template_editor.label_lang_key') ?></label>
                    <input type="text" name="new_lang_key" placeholder="nav.xxx">
                    <label for="te-new-group"><?= t('admin.template_editor.parent_group') ?></label>
                    <select id="te-new-group" name="new_group_key" required>
                        <?php foreach ($navGroups as $group): ?><option value="<?= htmlspecialchars($group['group_key'], ENT_QUOTES, 'UTF-8') ?>"<?= ($editGroupKey === $group['group_key']) ? ' selected' : '' ?>><?= htmlspecialchars(PlayerNavigationSettings::label($group), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select>
                    <label for="te-new-icon"><?= t('admin.template_editor.icon_label') ?></label>
                    <div data-icon-picker><img data-icon-preview class="te-icon-preview te-icon-preview--subitem" src="<?= asset(PlayerNavIcon::url('dashboard')) ?>" alt="">
                    <select id="te-new-icon" name="new_icon_key" data-icon-select>
                        <option value="" data-icon-url="<?= asset(PlayerNavIcon::url('dashboard')) ?>"><?= t('admin.template_editor.icon_default') ?></option>
                        <?php foreach ($navIconChoices as $iconName): ?><option value="<?= htmlspecialchars($iconName, ENT_QUOTES, 'UTF-8') ?>" data-icon-url="<?= asset(PlayerNavIcon::url($iconName)) ?>"><?= htmlspecialchars($iconName, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select>
                    <label for="te-new-file"><?= t('admin.template_editor.icon_upload') ?></label>
                    <input id="te-new-file" type="file" name="icon_file" accept="image/png,image/jpeg,image/webp" data-icon-file></div>
                    <small class="muted"><?= t('admin.template_editor.icon_hint') ?></small>
                    <button type="submit" class="btn btn-secondary te-add-btn">+ <?= t('admin.template_editor.btn_add_nav') ?></button>
                </form>
            </div>
        </div>
    </aside>
    <div class="te-editor">
        <?php if ($editGroup): ?>
        <h2><?= t('admin.template_editor.edit_group') ?>: <?= htmlspecialchars(PlayerNavigationSettings::label($editGroup), ENT_QUOTES, 'UTF-8') ?></h2>
        <form method="post" enctype="multipart/form-data" class="te-meta te-group-editor">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="save_nav_group">
            <input type="hidden" name="group_key" value="<?= htmlspecialchars($editGroup['group_key'], ENT_QUOTES, 'UTF-8') ?>">
            <div><label><?= t('admin.template_editor.label_label') ?></label><input name="group_label" maxlength="64" value="<?= htmlspecialchars($editGroup['label'], ENT_QUOTES, 'UTF-8') ?>" required></div>
            <div><label><?= t('admin.template_editor.label_en') ?></label><input name="group_label_en" maxlength="64" value="<?= htmlspecialchars($editGroup['label_en'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
            <div><label><?= t('admin.template_editor.label_de') ?></label><input name="group_label_de" maxlength="64" value="<?= htmlspecialchars($editGroup['label_de'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
            <div><label><?= t('admin.template_editor.label_lang_key') ?></label><input name="group_lang_key" maxlength="64" value="<?= htmlspecialchars($editGroup['lang_key'], ENT_QUOTES, 'UTF-8') ?>"></div>
            <div><label><?= t('admin.template_editor.label_order') ?></label><input type="number" name="group_sort_order" min="0" max="32000" value="<?= (int)$editGroup['sort_order'] ?>"></div>
            <div data-icon-picker><label><?= t('admin.template_editor.icon_label') ?></label><img data-icon-preview class="te-icon-preview" src="<?= asset(PlayerNavIcon::url(PlayerNavIcon::resolve((string)$editGroup['icon_key'], (string)$editGroup['group_key']))) ?>" alt=""><select name="group_icon_key" data-icon-select>
                <option value="" data-icon-url="<?= asset(PlayerNavIcon::url(PlayerNavIcon::resolve('', (string)$editGroup['group_key']))) ?>"<?= $editGroup['icon_key'] === '' ? ' selected' : '' ?>><?= t('admin.template_editor.icon_default') ?></option>
                <?php foreach ($navIconChoices as $iconName): ?><option value="<?= htmlspecialchars($iconName, ENT_QUOTES, 'UTF-8') ?>" data-icon-url="<?= asset(PlayerNavIcon::url($iconName)) ?>"<?= $editGroup['icon_key'] === $iconName ? ' selected' : '' ?>><?= htmlspecialchars($iconName, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
            </select><label><?= t('admin.template_editor.icon_upload') ?></label><input type="file" name="icon_file" accept="image/png,image/jpeg,image/webp" data-icon-file></div>
            <div><label><input type="checkbox" name="group_active" value="1"<?= $editGroup['active'] ? ' checked' : '' ?>> <?= t('admin.template_editor.label_visible') ?></label></div>
            <button type="submit" class="btn btn-primary"><?= t('admin.template_editor.btn_save') ?></button>
        </form>
        <?php elseif ($editNav && $editNav['location'] === 'header'): ?>
        <h2><?= t('admin.template_editor.edit_heading') ?>: <?= htmlspecialchars(PlayerNavigationSettings::label($editNav), ENT_QUOTES, 'UTF-8') ?></h2>
        <form method="post" id="editNavForm" enctype="multipart/form-data">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="save_nav">
            <input type="hidden" name="nav_id" value="<?= (int)$editNav['id'] ?>">
            <div class="te-meta">
                <div><label><?= t('admin.template_editor.label_lang_key') ?></label>
                    <input type="text" name="lang_key" value="<?= htmlspecialchars($editNav['lang_key'] ?? '') ?>" placeholder="nav.xxx"></div>
                <div><label><?= t('admin.template_editor.label_label') ?></label>
                    <input type="text" name="label" value="<?= htmlspecialchars($editNav['label'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                <div><label><?= t('admin.template_editor.label_en') ?></label><input name="label_en" maxlength="64" value="<?= htmlspecialchars($editNav['label_en'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                <div><label><?= t('admin.template_editor.label_de') ?></label><input name="label_de" maxlength="64" value="<?= htmlspecialchars($editNav['label_de'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></div>
                <div><label><?= t('admin.template_editor.label_url_key') ?></label>
                    <?php if (in_array($editNav['url_key'], ['home', 'help', 'chat', 'logout'], true)): ?>
                        <input type="text" value="<?= htmlspecialchars($editNav['url_key'], ENT_QUOTES, 'UTF-8') ?>" disabled>
                        <input type="hidden" name="url_key" value="<?= htmlspecialchars($editNav['url_key'], ENT_QUOTES, 'UTF-8') ?>">
                    <?php else: ?>
                        <select name="url_key" required>
                            <?php if (!in_array(ltrim($editNav['url_key'], '/'), $navDestinations, true)): ?><option value="<?= htmlspecialchars($editNav['url_key'], ENT_QUOTES, 'UTF-8') ?>" selected disabled><?= htmlspecialchars($editNav['url_key'], ENT_QUOTES, 'UTF-8') ?> — <?= t('admin.template_editor.choose_destination') ?></option><?php endif; ?>
                            <?php foreach ($navDestinations as $route): ?><option value="<?= htmlspecialchars($route, ENT_QUOTES, 'UTF-8') ?>"<?= ltrim($editNav['url_key'], '/') === $route ? ' selected' : '' ?>><?= htmlspecialchars('/' . $route, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <small class="muted"><?= t('admin.template_editor.hint_url_key') ?></small></div>
                <div><label><?= t('admin.template_editor.label_css_class') ?></label>
                    <input type="text" name="css_class" value="<?= htmlspecialchars($editNav['css_class']) ?>"></div>
                <div><label><?= t('admin.template_editor.label_order') ?></label>
                    <input type="number" name="sort_order" value="<?= (int)$editNav['sort_order'] ?>" min="0"></div>
                <?php if (!in_array(trim($editNav['url_key'], '/'), ['home', 'help', 'chat', 'logout'], true)): ?>
                <div><label for="te-parent-group"><?= t('admin.template_editor.parent_group') ?></label>
                    <select id="te-parent-group" name="group_key">
                        <?php foreach ($navGroups as $group): ?><option value="<?= htmlspecialchars($group['group_key'], ENT_QUOTES, 'UTF-8') ?>"<?= (($editNav['group_key'] ?: PlayerNavigationSettings::defaultGroup($editNav['url_key'])) === $group['group_key']) ? ' selected' : '' ?>><?= htmlspecialchars(PlayerNavigationSettings::label($group), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select></div>
                <?php endif; ?>
                <div class="te-icon-edit" data-icon-picker><label for="te-icon"><?= t('admin.template_editor.icon_label') ?></label>
                    <img data-icon-preview class="te-icon-preview te-icon-preview--subitem" src="<?= asset(PlayerNavIcon::url(PlayerNavIcon::resolve((string)($editNav['icon_key'] ?? ''), (string)$editNav['url_key']))) ?>" alt="">
                    <select id="te-icon" name="icon_key" data-icon-select>
                        <option value="" data-icon-url="<?= asset(PlayerNavIcon::url(PlayerNavIcon::resolve('', (string)$editNav['url_key']))) ?>"<?= empty($editNav['icon_key']) ? ' selected' : '' ?>><?= t('admin.template_editor.icon_default') ?></option>
                        <?php foreach ($navIconChoices as $iconName): ?><option value="<?= htmlspecialchars($iconName, ENT_QUOTES, 'UTF-8') ?>" data-icon-url="<?= asset(PlayerNavIcon::url($iconName)) ?>"<?= ($editNav['icon_key'] ?? '') === $iconName ? ' selected' : '' ?>><?= htmlspecialchars($iconName, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                    </select>
                    <label for="te-icon-file"><?= t('admin.template_editor.icon_upload') ?></label>
                    <input id="te-icon-file" type="file" name="icon_file" accept="image/png,image/jpeg,image/webp" data-icon-file>
                    <small class="muted"><?= t('admin.template_editor.icon_hint') ?></small>
                </div>
                <div class="te-visible-row">
                    <label><input type="checkbox" name="active" value="1" <?= $editNav['active'] ? 'checked' : '' ?>> <?= t('admin.template_editor.label_visible') ?></label>
                </div>
            </div>
            <div class="te-preview">
                <span class="muted te-preview-label"><?= t('admin.template_editor.preview_btn') ?></span><br>
                <span class="te-player-main-item"><img src="<?= asset(PlayerNavIcon::url(PlayerNavIcon::resolve((string)($editNav['icon_key'] ?? ''), (string)$editNav['url_key']))) ?>" alt=""><span><?= htmlspecialchars(PlayerNavigationSettings::label($editNav), ENT_QUOTES, 'UTF-8') ?></span></span>
            </div>
            <div class="te-form-actions">
                <button type="submit" class="btn btn-primary"> <?= t('admin.template_editor.btn_save') ?></button>
                <button type="submit" form="deleteNavForm" class="btn btn-danger"><?= t('admin.template_editor.btn_delete') ?></button>
            </div>
        </form>
        <form method="post" id="deleteNavForm" class="hidden" data-confirm="<?= t('admin.template_editor.confirm_delete') ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="delete_nav">
            <input type="hidden" name="nav_id" value="<?= (int)$editNav['id'] ?>">
        </form>
        <?php else: ?>
        <p class="muted te-empty"> <?= t('admin.template_editor.no_item_selected') ?></p>
        <?php endif ?>
    </div>
</div>

<?php elseif ($activeTab === 'footer'): ?>
<!--  ZAKADKA: LINKI STOPKI  -->
<div class="te-layout">
    <aside>
        <div class="te-sidebar">
            <div class="te-sidebar-hdr"><?= t('admin.template_editor.sidebar_footer', ['count' => count($footerItems)]) ?></div>
            <ul class="te-list">
            <?php foreach ($footerItems as $n): ?>
                <li>
                    <a href="?tab=footer&nav=<?= $n['id'] ?>" class="<?= (int)$n['id'] === $editNavId ? 'active' : '' ?>">
                        <span><?= htmlspecialchars(!empty($n['lang_key']) ? t($n['lang_key']) : $n['label']) ?></span>
                        <span class="te-sort">#<?= (int)$n['sort_order'] ?></span>
                        <?php if (!$n['active']): ?>
                            <span class="te-badge"><?= t('admin.template_editor.badge_hidden') ?></span>
                        <?php else: ?>
                            <span class="te-ok"></span>
                        <?php endif ?>
                    </a>
                </li>
            <?php endforeach ?>
            </ul>
            <div class="te-add-form">
                <form method="post">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="add_nav">
                    <input type="hidden" name="new_location" value="footer">
                    <label><?= t('admin.template_editor.label_label') ?></label>
                    <input type="text" name="new_label" placeholder="<?= t('admin.template_editor.placeholder_label_footer') ?>" required>
                    <label><?= t('admin.template_editor.label_url_key') ?></label>
                    <input type="text" name="new_url_key" placeholder="<?= t('admin.template_editor.placeholder_url_footer') ?>" required>
                    <label><?= t('admin.template_editor.label_lang_key') ?></label>
                    <input type="text" name="new_lang_key" placeholder="nav.footer.xxx">
                    <button type="submit" class="btn btn-secondary te-add-btn">+ <?= t('admin.template_editor.btn_add_footer') ?></button>
                </form>
            </div>
        </div>
    </aside>
    <div class="te-editor">
        <?php
        $editFooterNav = null;
        foreach ($footerItems as $n) { if ((int)$n['id'] === $editNavId) { $editFooterNav = $n; break; } }
        ?>
        <?php if ($editFooterNav): ?>
        <h2><?= t('admin.template_editor.edit_heading') ?>: <?= htmlspecialchars(!empty($editFooterNav['lang_key']) ? t($editFooterNav['lang_key']) : $editFooterNav['label']) ?></h2>
        <form method="post" id="editFooterForm">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="save_nav">
            <input type="hidden" name="nav_id" value="<?= (int)$editFooterNav['id'] ?>">
            <div class="te-meta">
                <div><label><?= t('admin.template_editor.label_lang_key') ?></label>
                    <input type="text" name="lang_key" value="<?= htmlspecialchars($editFooterNav['lang_key'] ?? '') ?>" placeholder="nav.footer.xxx"></div>
                <div><label><?= t('admin.template_editor.label_label') ?></label>
                    <input type="text" name="label" value="<?= htmlspecialchars($editFooterNav['label']) ?>" required></div>
                <div><label><?= t('admin.template_editor.label_url_key') ?></label>
                    <input type="text" name="url_key" value="<?= htmlspecialchars($editFooterNav['url_key']) ?>" required>
                    <small class="muted"><?= t('admin.template_editor.hint_url_key') ?></small></div>
                <div><label><?= t('admin.template_editor.label_css_class') ?></label>
                    <input type="text" name="css_class" value="<?= htmlspecialchars($editFooterNav['css_class']) ?>"></div>
                <div><label><?= t('admin.template_editor.label_order') ?></label>
                    <input type="number" name="sort_order" value="<?= (int)$editFooterNav['sort_order'] ?>" min="0"></div>
                <div class="te-visible-row">
                    <label><input type="checkbox" name="active" value="1" <?= $editFooterNav['active'] ? 'checked' : '' ?>> <?= t('admin.template_editor.label_visible') ?></label>
                </div>
            </div>
            <div class="te-preview">
                <span class="muted te-preview-label"><?= t('admin.template_editor.preview_link') ?></span><br>
                <a class="footer-link <?= htmlspecialchars($editFooterNav['css_class']) ?> te-preview-link">
                    <?= htmlspecialchars(!empty($editFooterNav['lang_key']) ? t($editFooterNav['lang_key']) : $editFooterNav['label']) ?>
                </a>
            </div>
            <div class="te-form-actions">
                <button type="submit" class="btn btn-primary"> <?= t('admin.template_editor.btn_save') ?></button>
                <button type="button" class="btn btn-danger"
                    onclick="if(confirm('<?= t('admin.template_editor.confirm_delete') ?>')) document.getElementById('deleteFooterForm').submit()">
                     <?= t('admin.template_editor.btn_delete') ?>
                </button>
            </div>
        </form>
        <form method="post" id="deleteFooterForm" class="hidden">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="delete_nav">
            <input type="hidden" name="nav_id" value="<?= (int)$editFooterNav['id'] ?>">
        </form>
        <?php else: ?>
        <p class="muted te-empty"> <?= t('admin.template_editor.no_item_selected') ?></p>
        <?php endif ?>
    </div>
</div>

<?php elseif ($activeTab === 'actions'): ?>
<!--  ZAKADKA: PRZYCISKI AKCJE  -->
<div class="te-layout">
    <aside>
        <div class="te-sidebar">
            <div class="te-sidebar-hdr"><?= t('admin.template_editor.sidebar_actions', ['count' => count($actionItems)]) ?></div>
            <ul class="te-list">
            <?php foreach ($actionItems as $n): ?>
                <li>
                    <a href="?tab=actions&nav=<?= $n['id'] ?>" class="<?= (int)$n['id'] === $editNavId ? 'active' : '' ?>">
                        <span><?= htmlspecialchars(!empty($n['lang_key']) ? t($n['lang_key']) : $n['label']) ?></span>
                        <span class="te-sort">#<?= (int)$n['sort_order'] ?></span>
                        <?php if (!$n['active']): ?>
                            <span class="te-badge"><?= t('admin.template_editor.badge_hidden') ?></span>
                        <?php else: ?>
                            <span class="te-ok"></span>
                        <?php endif ?>
                    </a>
                </li>
            <?php endforeach ?>
            </ul>
            <div class="te-add-form">
                <form method="post">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="add_nav">
                    <input type="hidden" name="new_location" value="actions">
                    <label><?= t('admin.template_editor.label_label') ?></label>
                    <input type="text" name="new_label" placeholder="<?= t('admin.template_editor.placeholder_label_nav') ?>" required>
                    <label><?= t('admin.template_editor.label_url_key') ?></label>
                    <input type="text" name="new_url_key" placeholder="<?= t('admin.template_editor.placeholder_url_actions') ?>" required>
                    <label><?= t('admin.template_editor.label_lang_key') ?></label>
                    <input type="text" name="new_lang_key" placeholder="nav.action.xxx">
                    <button type="submit" class="btn btn-secondary te-add-btn">+ <?= t('admin.template_editor.btn_add_action') ?></button>
                </form>
            </div>
        </div>
    </aside>
    <div class="te-editor">
        <?php
        $editActionNav = null;
        foreach ($actionItems as $n) { if ((int)$n['id'] === $editNavId) { $editActionNav = $n; break; } }
        ?>
        <?php if ($editActionNav): ?>
        <h2><?= t('admin.template_editor.edit_heading') ?>: <?= htmlspecialchars(!empty($editActionNav['lang_key']) ? t($editActionNav['lang_key']) : $editActionNav['label']) ?></h2>
        <form method="post" id="editActionForm">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="save_nav">
            <input type="hidden" name="nav_id" value="<?= (int)$editActionNav['id'] ?>">
            <div class="te-meta">
                <div><label><?= t('admin.template_editor.label_lang_key') ?></label>
                    <input type="text" name="lang_key" value="<?= htmlspecialchars($editActionNav['lang_key'] ?? '') ?>" placeholder="nav.action.xxx"></div>
                <div><label><?= t('admin.template_editor.label_label') ?></label>
                    <input type="text" name="label" value="<?= htmlspecialchars($editActionNav['label']) ?>" required></div>
                <div><label><?= t('admin.template_editor.label_url_key') ?></label>
                    <input type="text" name="url_key" value="<?= htmlspecialchars($editActionNav['url_key']) ?>" required>
                    <small class="muted"><?= t('admin.template_editor.hint_url_key') ?></small></div>
                <div><label><?= t('admin.template_editor.label_css_class_btn') ?></label>
                    <input type="text" name="css_class" value="<?= htmlspecialchars($editActionNav['css_class']) ?>" placeholder="btn-success">
                    <small class="muted"><?= t('admin.template_editor.hint_css_class') ?></small></div>
                <div><label><?= t('admin.template_editor.label_order') ?></label>
                    <input type="number" name="sort_order" value="<?= (int)$editActionNav['sort_order'] ?>" min="0"></div>
                <div class="te-visible-row">
                    <label><input type="checkbox" name="active" value="1" <?= $editActionNav['active'] ? 'checked' : '' ?>> <?= t('admin.template_editor.label_visible') ?></label>
                </div>
            </div>
            <div class="te-preview">
                <span class="muted te-preview-label"><?= t('admin.template_editor.preview_btn') ?></span><br>
                <a class="btn <?= htmlspecialchars($editActionNav['css_class']) ?> te-preview-btn">
                    <?= htmlspecialchars(!empty($editActionNav['lang_key']) ? t($editActionNav['lang_key']) : $editActionNav['label']) ?>
                </a>
            </div>
            <div class="te-form-actions">
                <button type="submit" class="btn btn-primary"> <?= t('admin.template_editor.btn_save') ?></button>
                <button type="button" class="btn btn-danger"
                    onclick="if(confirm('<?= t('admin.template_editor.confirm_delete_action') ?>')) document.getElementById('deleteActionForm').submit()">
                     <?= t('admin.template_editor.btn_delete') ?>
                </button>
            </div>
        </form>
        <form method="post" id="deleteActionForm" class="hidden">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="delete_nav">
            <input type="hidden" name="nav_id" value="<?= (int)$editActionNav['id'] ?>">
        </form>
        <?php else: ?>
        <p class="muted te-empty"> <?= t('admin.template_editor.no_item_selected') ?></p>
        <?php endif ?>
    </div>
</div>

<?php elseif ($activeTab === 'boardroom'): ?>
<!--  ZAKADKA: BOARDROOM  -->
<div class="te-config-wrap">
    <form method="post" enctype="multipart/form-data">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="save_boardroom">

        <div class="panel te-panel">
            <p class="panel-title"><?= t('admin.template_editor.br_section_header') ?></p>
            <div class="te-config-fields">
                <div>
                    <label class="te-label"><?= t('admin.template_editor.br_label_title') ?></label>
                    <input type="text" name="header_title" value="<?= htmlspecialchars($brConfig['header_title'] ?? '') ?>" maxlength="100" class="te-input-md">
                </div>
                <div>
                    <label class="te-label"><?= t('admin.template_editor.br_label_subtitle') ?></label>
                    <input type="text" name="header_subtitle" value="<?= htmlspecialchars($brConfig['header_subtitle'] ?? '') ?>" maxlength="200" class="te-input-lg">
                </div>
            </div>
            <div class="br-field">
                <label class="te-label"><?= t('admin.template_editor.br_label_image') ?></label>
                <?php $brImg = $brConfig['header_image'] ?? ''; ?>
                <?php if ($brImg): ?>
                    <div class="br-img-preview">
                        <img src="<?= htmlspecialchars($brImg) ?>" alt="Header" class="br-img-thumb">
                        <button type="submit" name="action" value="remove_boardroom_image" class="br-btn-remove">
                            <?= t('admin.template_editor.br_btn_remove_img') ?>
                        </button>
                    </div>
                <?php endif ?>
                <input type="file" name="header_image" accept="image/jpeg,image/png,image/webp" class="br-file-input">
                <div class="br-file-hint"><?= t('admin.template_editor.br_file_hint') ?></div>
            </div>
        </div>

        <div class="panel te-panel">
            <p class="panel-title"><?= t('admin.template_editor.br_section_footer') ?></p>
            <div class="te-config-fields">
                <div>
                    <label class="te-label"><?= t('admin.template_editor.br_label_footer') ?></label>
                    <input type="text" name="footer_text" value="<?= htmlspecialchars($brConfig['footer_text'] ?? '') ?>" maxlength="300" placeholder="<?= t('admin.template_editor.br_ph_footer') ?>" class="te-input-xl">
                </div>
            </div>
        </div>

        <div class="panel te-panel">
            <p class="panel-title"><?= t('admin.template_editor.br_section_links') ?></p>
            <div id="br-footer-links-list">
            <?php foreach ($brFooterLinks as $link): ?>
                <div class="br-link-row">
                    <input type="text" name="footer_link_label[]" value="<?= htmlspecialchars($link['label'] ?? '') ?>" placeholder="<?= t('admin.template_editor.br_label_link_label') ?>">
                    <input type="text" name="footer_link_url[]" value="<?= htmlspecialchars($link['url'] ?? '') ?>" placeholder="<?= t('admin.template_editor.br_label_link_url') ?>">
                    <button type="button" data-br-remove-footer class="br-btn-remove-link"></button>
                </div>
            <?php endforeach ?>
            </div>
            <button type="button" data-br-add-footer data-label="<?= t('admin.template_editor.br_label_link_label') ?>" data-url="<?= t('admin.template_editor.br_label_link_url') ?>" class="br-btn-add-link">
                <?= t('admin.template_editor.br_btn_add_link') ?>
            </button>
        </div>

        <div class="form-row">
            <button type="submit" class="btn btn-primary"><?= t('admin.template_editor.br_btn_save') ?></button>
            <a href="/boardroom" target="_blank" class="btn btn-secondary"> Podgld Boardroom</a>
        </div>
    </form>

    <!--  Ta sceny (kombinacje rl + pe)  -->
    <div class="panel te-panel">
        <p class="panel-title"> <?= t('admin.template_editor.br_section_backgrounds') ?></p>
        <p class="te-hint"><?= t('admin.template_editor.br_bg_hint') ?></p>

        <div class="br-bg-info">
            <strong><?= t('admin.template_editor.br_bg_naming_title') ?></strong>
            <code>boardroom_bg.png</code>  <?= t('admin.template_editor.br_bg_naming_default') ?><br>
            <code>boardroom_bg_hr_M.png</code>  <?= t('admin.template_editor.br_bg_naming_single') ?><br>
            <code>boardroom_bg_hr_M_tech_F.png</code>  <?= t('admin.template_editor.br_bg_naming_multi') ?>
        </div>

        <!-- Istniejce pliki -->
        <?php $existingBgs = array_filter($brBgMatrix, fn($b) => $b['exists']); ?>
        <?php if (!empty($existingBgs)): ?>
        <div class="br-bg-grid">
            <?php foreach ($existingBgs as $bg): ?>
            <div class="br-bg-card br-bg-exists">
                <div class="br-bg-thumb-wrap">
                    <img src="<?= htmlspecialchars($bg['preview']) ?>?v=<?= time() ?>" alt="" class="br-bg-thumb" onerror="this.style.display='none'">
                </div>
                <div class="br-bg-card-info">
                    <code class="br-bg-fname"><?= htmlspecialchars($bg['file']) ?></code>
                    <span class="br-bg-label"><?= htmlspecialchars($bg['label']) ?></span>
                </div>
                <form method="post" class="br-bg-delete-form">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action"  value="delete_boardroom_bg">
                    <input type="hidden" name="bg_name" value="<?= htmlspecialchars($bg['name']) ?>">
                    <button type="submit" class="br-btn-remove"
                            onclick="return confirm('<?= t('admin.template_editor.br_bg_confirm_delete') ?>')"></button>
                </form>
            </div>
            <?php endforeach ?>
        </div>
        <?php endif ?>

        <!-- Upload nowego ta (base64  omija upload_tmp_dir) -->
        <details class="br-bg-upload-wrap">
            <summary class="btn btn-secondary btn-sm"><?= t('admin.template_editor.br_bg_btn_upload') ?></summary>
            <div class="br-bg-upload-body">
                <form method="post" id="br-bg-upload-form"
                      data-upload-messages="<?= htmlspecialchars(json_encode([
                          'name' => tPlain('admin.template_editor.br_bg_err_name'),
                          'file' => tPlain('admin.template_editor.br_bg_err_file'),
                          'size' => tPlain('admin.template_editor.upload_size'),
                          'image' => tPlain('admin.template_editor.upload_image'),
                          'csrf' => tPlain('admin.template_editor.upload_csrf'),
                          'progress' => tPlain('admin.template_editor.upload_progress'),
                          'http' => tPlain('admin.template_editor.upload_http'),
                          'response' => tPlain('admin.template_editor.upload_response'),
                          'network' => tPlain('admin.template_editor.upload_network'),
                          'timeout' => tPlain('admin.template_editor.upload_timeout'),
                          'server' => tPlain('admin.template_editor.upload_server'),
                          'saved' => tPlain('admin.template_editor.upload_saved'),
                      ], JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action"       value="save_boardroom_bg">
                    <input type="hidden" name="bg_name"      id="br-bg-name-input" value="">
                    <input type="hidden" name="bg_file_b64"  id="br-bg-file-b64" value="">
                    <input type="hidden" name="bg_file_mime" id="br-bg-file-mime" value="">

                    <div class="br-bg-upload-fields">
                        <div>
                            <label class="te-label"><?= t('admin.template_editor.br_bg_label_roles') ?></label>
                            <div class="br-bg-role-checks">
                                <?php foreach ($brSceneRoles as $code => $roleLabel): ?>
                                <label class="br-bg-role-check">
                                    <input type="checkbox" class="br-bg-role-cb" data-role="<?= $code ?>">
                                    <?= htmlspecialchars($roleLabel) ?>
                                    <select class="br-bg-gender-sel" data-role="<?= $code ?>" disabled>
                                        <option value="M">M</option>
                                        <option value="F">F</option>
                                        <option value=""></option>
                                    </select>
                                </label>
                                <?php endforeach ?>
                            </div>
                        </div>
                        <div>
                            <label class="te-label"><?= t('admin.template_editor.br_bg_label_preview_name') ?></label>
                            <div class="br-bg-name-preview">
                                <code id="br-bg-name-preview">boardroom_bg.png</code>
                            </div>
                        </div>
                        <div>
                            <label class="te-label"><?= t('admin.template_editor.br_bg_label_file') ?></label>
                            <input type="file" id="br-bg-file-picker" accept="image/jpeg,image/png,image/webp" class="br-file-input">
                            <div class="br-file-hint"><?= t('admin.template_editor.br_bg_file_hint') ?></div>
                            <div id="br-bg-file-status" class="mt-xs font-sm muted2" role="status" aria-live="polite" aria-atomic="true"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm" id="br-bg-submit-btn"><?= t('admin.template_editor.br_bg_btn_save') ?></button>
                </form>
            </div>
        </details>
    </div>
</div>

<script src="/assets/js/admin-template-upload.js?v=1" defer></script>

<?php else: ?>
<!--  ZAKADKA: NAGWEK & STOPKA  -->
<div class="te-config-wrap">
    <?php $publicHomepageEnabled = ($cfgRows['public_homepage_enabled'] ?? '1') === '1'; ?>
    <div class="panel te-panel">
        <h2 class="panel-title"><?= t('admin.template_editor.public_homepage_title') ?></h2>
        <p class="muted"><?= t('admin.template_editor.public_homepage_description') ?></p>
        <form method="post">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="save_public_homepage">
            <div class="te-visible-row">
                <label>
                    <input type="checkbox" name="public_homepage_enabled" value="1" <?= $publicHomepageEnabled ? 'checked' : '' ?>>
                    <?= t('admin.template_editor.public_homepage_label') ?>
                </label>
            </div>
            <p class="muted">
                <?= t($publicHomepageEnabled
                    ? 'admin.template_editor.public_homepage_status_enabled'
                    : 'admin.template_editor.public_homepage_status_disabled') ?>
            </p>
            <div class="form-row">
                <button type="submit" class="btn btn-primary"><?= t('admin.template_editor.public_homepage_save') ?></button>
                <a href="/" target="_blank" rel="noopener" class="btn btn-secondary"><?= t('admin.template_editor.btn_preview_site') ?></a>
            </div>
        </form>
    </div>

    <form method="post">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="save_config">

        <div class="panel te-panel">
            <p class="panel-title"> <?= t('admin.template_editor.section_header') ?></p>
            <div class="te-config-fields">
                <div>
                    <label class="te-label"><?= t('admin.template_editor.label_site_name') ?></label>
                    <input type="text" name="site_name" value="<?= htmlspecialchars($cfgRows['site_name'] ?? 'OilCorp') ?>" class="te-input-md">
                </div>
                <div>
                    <label class="te-label"><?= t('admin.template_editor.label_site_tagline') ?></label>
                    <input type="text" name="site_tagline" value="<?= htmlspecialchars($cfgRows['site_tagline'] ?? '') ?>" class="te-input-lg">
                </div>
            </div>
        </div>

        <div class="panel te-panel">
            <p class="panel-title"> <?= t('admin.template_editor.section_footer') ?></p>
            <div class="te-config-fields">
                <div>
                    <label class="te-label"><?= t('admin.template_editor.label_footer_text') ?></label>
                    <input type="text" name="footer_text" value="<?= htmlspecialchars($cfgRows['footer_text'] ?? '') ?>" class="te-input-xl">
                </div>
                <div>
                    <label class="te-label"><?= t('admin.template_editor.label_footer_js') ?></label>
                    <input type="text" name="footer_js" value="<?= htmlspecialchars($cfgRows['footer_js'] ?? '/assets/js/game.js') ?>" class="te-input-md">
                    <small class="muted"><?= t('admin.template_editor.hint_footer_js') ?></small>
                </div>
            </div>
        </div>

        <div class="form-row">
            <button type="submit" class="btn btn-primary"> <?= t('admin.template_editor.btn_save_config') ?></button>
            <a href="/" target="_blank" class="btn btn-secondary"> <?= t('admin.template_editor.btn_preview_site') ?></a>
        </div>
    </form>

    <div class="panel te-panel">
        <p class="panel-title"><?= t('admin.template_editor.footer_preview') ?></p>
        <div class="te-footer-preview">
            <?= str_replace('{year}', date('Y'), $cfgRows['footer_text'] ?? '') ?>
        </div>
    </div>
</div>
<?php endif ?>
