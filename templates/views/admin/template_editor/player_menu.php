<?php
// The editor receives the same snapshot that the batch endpoint validates.
// Edytor otrzymuje te sama migawke, ktora sprawdza endpoint zapisu.
[$menuGroups, $menuItems] = PlayerMenuEditor::rows($db);
$menuText = [];
foreach (['title','saved','dirty','saving','failed','save','undo','reload','conflict','error','preview','collapse','expand','show_hidden','desktop','phone','structure','search','no_results','clear_search','add_item','add_group','edit_item','edit_group','pl','en','de','parent','link_type','game_page','external','destination','url','new_tab','order','up','down','visible','hidden','icon','icon_default','icon_upload','icon_library','advanced','lang_key','variant','none','delete_item','delete_group','delete_confirm','delete_group_confirm','delete_group_children','result','role','role_all','role_note','empty','special_note','file_error','destination_hint','choose','reset_icon','select_item'] as $key) {
    $menuText[$key] = tPlain('admin.template_editor.menu_' . $key);
}
$menuIcons = [];
foreach ($navIconChoices as $key) $menuIcons[$key] = asset(PlayerNavIcon::url($key));
$menuDefaults = [];
foreach (array_merge($menuGroups, $menuItems) as $row) {
    $route = (string)($row['url_key'] ?? $row['group_key']);
    $menuDefaults[$route] = asset(PlayerNavIcon::url(PlayerNavIcon::fallback($route)));
}
foreach ($navDestinations as $route) {
    $menuDefaults[$route] = asset(PlayerNavIcon::url(PlayerNavIcon::fallback($route)));
}
$menuConfig = [
    'groups' => $menuGroups, 'items' => $menuItems,
    'version' => PlayerMenuEditor::version($menuGroups, $menuItems),
    'texts' => $menuText, 'icons' => $menuIcons, 'defaults' => $menuDefaults,
    'fallback' => asset(PlayerNavIcon::url('dashboard')),
    'destinations' => $navDestinations,
    'defaultGroups' => array_column(array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'group' => PlayerNavigationSettings::defaultGroup((string)$row['url_key']),
    ], $menuItems), 'group', 'id'),
    'protectedRoutes' => BoardAccess::NAV_ROLE_MAP,
    'csrf' => CSRF::generateToken(),
    'endpoint' => '/admin/player_menu_save.php',
];
?>
<section class="pm-editor" id="player-menu-editor">
    <header class="pm-top">
        <div><h1><?= htmlspecialchars($menuText['title'], ENT_QUOTES, 'UTF-8') ?></h1></div>
        <div class="pm-top-actions"><span id="pm-status" role="status" aria-live="polite"><?= htmlspecialchars($menuText['saved'], ENT_QUOTES, 'UTF-8') ?></span><button type="button" class="btn btn-primary" data-pm-save><?= htmlspecialchars($menuText['save'], ENT_QUOTES, 'UTF-8') ?></button></div>
    </header>
    <section class="pm-preview" aria-label="<?= htmlspecialchars($menuText['preview'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="pm-preview-bar"><h2><?= htmlspecialchars($menuText['preview'], ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="pm-preview-controls"><label><input type="checkbox" id="pm-hidden"> <?= htmlspecialchars($menuText['show_hidden'], ENT_QUOTES, 'UTF-8') ?></label>
            <label><?= htmlspecialchars($menuText['role'], ENT_QUOTES, 'UTF-8') ?> <select id="pm-role"><option value="all"><?= htmlspecialchars($menuText['role_all'], ENT_QUOTES, 'UTF-8') ?></option><?php foreach (BoardAccess::PROTECTED_ROLES as $role): ?><option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($role), ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
            <button type="button" id="pm-device" aria-pressed="false"><?= htmlspecialchars($menuText['phone'], ENT_QUOTES, 'UTF-8') ?></button>
            <button type="button" id="pm-toggle" aria-expanded="true" aria-controls="pm-preview-content"><?= htmlspecialchars($menuText['collapse'], ENT_QUOTES, 'UTF-8') ?></button></div>
        </div><div id="pm-preview-content" class="pm-preview-content"></div>
        <p class="muted pm-role-note"><?= htmlspecialchars($menuText['role_note'], ENT_QUOTES, 'UTF-8') ?></p>
    </section>
    <div class="pm-workspace">
        <aside class="pm-structure" aria-label="<?= htmlspecialchars($menuText['structure'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="pm-structure-head"><h2><?= htmlspecialchars($menuText['structure'], ENT_QUOTES, 'UTF-8') ?> <small id="pm-count"></small></h2>
            <label class="pm-search-label" for="pm-search"><?= htmlspecialchars($menuText['search'], ENT_QUOTES, 'UTF-8') ?></label><input type="search" id="pm-search"></div>
            <div id="pm-tree"></div>
            <div class="pm-structure-actions"><button type="button" id="pm-add-item"><?= htmlspecialchars($menuText['add_item'], ENT_QUOTES, 'UTF-8') ?></button><button type="button" id="pm-add-group"><?= htmlspecialchars($menuText['add_group'], ENT_QUOTES, 'UTF-8') ?></button></div>
        </aside>
        <section class="pm-details" id="pm-details" aria-label="<?= htmlspecialchars($menuText['select_item'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="pm-identity"><img id="pm-identity-icon" alt="" src="<?= htmlspecialchars($menuConfig['fallback'], ENT_QUOTES, 'UTF-8') ?>"><div><span id="pm-crumb"></span><h2 id="pm-heading"></h2></div><label class="pm-visible"><input id="pm-active" type="checkbox"> <span><?= htmlspecialchars($menuText['visible'], ENT_QUOTES, 'UTF-8') ?></span></label></div>
            <div id="pm-form" hidden>
                <section class="pm-form-section"><h3><?= htmlspecialchars($menuText['edit_item'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <div class="pm-languages"><label><?= htmlspecialchars($menuText['pl'], ENT_QUOTES, 'UTF-8') ?><input id="pm-pl" maxlength="64" required></label><label><?= htmlspecialchars($menuText['en'], ENT_QUOTES, 'UTF-8') ?><input id="pm-en" maxlength="64"></label><label><?= htmlspecialchars($menuText['de'], ENT_QUOTES, 'UTF-8') ?><input id="pm-de" maxlength="64"></label></div>
                    <div class="pm-routing"><label id="pm-parent-label"><?= htmlspecialchars($menuText['parent'], ENT_QUOTES, 'UTF-8') ?><select id="pm-parent"></select></label>
                    <label id="pm-type-label"><?= htmlspecialchars($menuText['link_type'], ENT_QUOTES, 'UTF-8') ?><select id="pm-type"><option value="game"><?= htmlspecialchars($menuText['game_page'], ENT_QUOTES, 'UTF-8') ?></option><option value="external"><?= htmlspecialchars($menuText['external'], ENT_QUOTES, 'UTF-8') ?></option></select></label>
                    <label id="pm-route-label"><?= htmlspecialchars($menuText['destination'], ENT_QUOTES, 'UTF-8') ?><select id="pm-route"></select></label>
                    <label id="pm-url-label" hidden><?= htmlspecialchars($menuText['url'], ENT_QUOTES, 'UTF-8') ?><input type="url" id="pm-url" maxlength="64" placeholder="https://example.org"></label></div>
                    <label class="pm-new-tab" id="pm-new-tab-wrap" hidden><input type="checkbox" id="pm-new-tab"> <?= htmlspecialchars($menuText['new_tab'], ENT_QUOTES, 'UTF-8') ?></label>
                    <p class="muted" id="pm-destination-hint"></p>
                    <div class="pm-order"><span id="pm-order-label"></span><button type="button" id="pm-up" aria-label="<?= htmlspecialchars($menuText['up'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($menuText['up'], ENT_QUOTES, 'UTF-8') ?></button><button type="button" id="pm-down" aria-label="<?= htmlspecialchars($menuText['down'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($menuText['down'], ENT_QUOTES, 'UTF-8') ?></button></div>
                </section>
                <section class="pm-form-section"><h3><?= htmlspecialchars($menuText['icon'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <div class="pm-icon-row"><img id="pm-icon-preview" alt="" src="<?= htmlspecialchars($menuConfig['fallback'], ENT_QUOTES, 'UTF-8') ?>"><label><?= htmlspecialchars($menuText['icon_library'], ENT_QUOTES, 'UTF-8') ?><select id="pm-icon"><option value=""><?= htmlspecialchars($menuText['icon_default'], ENT_QUOTES, 'UTF-8') ?></option><?php foreach ($menuIcons as $key => $iconUrl): ?><option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label><?= htmlspecialchars($menuText['icon_upload'], ENT_QUOTES, 'UTF-8') ?><input type="file" id="pm-file" accept="image/png,image/jpeg,image/webp"></label><button type="button" id="pm-reset-icon"><?= htmlspecialchars($menuText['reset_icon'], ENT_QUOTES, 'UTF-8') ?></button></div><p id="pm-file-name" class="muted" role="status"></p>
                </section>
                <details class="pm-advanced"><summary><?= htmlspecialchars($menuText['advanced'], ENT_QUOTES, 'UTF-8') ?></summary><div class="pm-advanced-fields"><label><?= htmlspecialchars($menuText['lang_key'], ENT_QUOTES, 'UTF-8') ?><input id="pm-lang" maxlength="64"></label><label id="pm-variant-wrap"><?= htmlspecialchars($menuText['variant'], ENT_QUOTES, 'UTF-8') ?><select id="pm-variant"><option value=""><?= htmlspecialchars($menuText['none'], ENT_QUOTES, 'UTF-8') ?></option><?php foreach (['btn-success','btn-info','btn-primary','btn-secondary','btn-danger'] as $variant): ?><option value="<?= $variant ?>"><?= $variant ?></option><?php endforeach; ?></select></label><p class="muted" id="pm-special-note"></p></div></details>
                <div class="pm-result"><img id="pm-result-icon" alt=""><strong id="pm-result-name"></strong><span id="pm-result-group"></span><span id="pm-result-status"></span></div>
                <div class="pm-form-actions"><button type="button" class="btn btn-danger" id="pm-delete"></button><span class="pm-form-spacer"></span><button type="button" id="pm-undo"><?= htmlspecialchars($menuText['undo'], ENT_QUOTES, 'UTF-8') ?></button><button type="button" class="btn btn-primary" data-pm-save><?= htmlspecialchars($menuText['save'], ENT_QUOTES, 'UTF-8') ?></button></div>
            </div><p id="pm-empty" class="muted"><?= htmlspecialchars($menuText['empty'], ENT_QUOTES, 'UTF-8') ?></p>
        </section>
    </div>
</section>
<script type="application/json" id="pm-data"><?= json_encode($menuConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
