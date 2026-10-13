/* Player menu working copy and server-confirmed save. / Robocza kopia menu i zapis potwierdzony przez serwer. */
(function () {
    'use strict';
    var root = document.getElementById('player-menu-editor');
    if (!root) return;
    var config = JSON.parse(document.getElementById('pm-data').textContent);
    var L = config.texts, $ = function (id) { return document.getElementById('pm-' + id); };
    var original = { groups: config.groups, items: config.items, version: config.version };
    var state = clone(original), selected = null, dirty = false, saving = false, expanded = new Set();
    function clone(v) { return JSON.parse(JSON.stringify(v)); }
    function key(row, group) { return (group ? 'g:' : 'i:') + (group ? row.group_key : row.id); }
    function current() { if (!selected) return null; return selected.startsWith('g:') ? state.groups.find(function (r) { return key(r, true) === selected; }) : state.items.find(function (r) { return key(r, false) === selected; }); }
    function isGroup() { return !!selected && selected.startsWith('g:'); }
    function special(row) { return specialValue(row); }
    function groupKey(row) { return row.group_key || config.defaultGroups[row.id] || 'company'; }
    function parent(row) { return state.groups.find(function (g) { return g.group_key === groupKey(row); }); }
    function order(a, b) { return Number(a.sort_order) - Number(b.sort_order) || Number(a.id || 0) - Number(b.id || 0); }
    function icon(row) { return row.icon_upload || config.icons[row.icon_key] || config.defaults[row.url_key || row.group_key] || config.fallback; }
    function node(tag, cls, text) { var el = document.createElement(tag); if (cls) el.className = cls; if (text !== undefined) el.textContent = text; return el; }
    function button(row, group, cls) { var b = node('button', cls + (selected === key(row, group) ? ' selected' : ''), row.label || L.choose); b.type = 'button'; b.dataset.select = key(row, group); var img = node('img'); img.src = icon(row); img.alt = ''; b.prepend(img); if (!Number(row.active)) b.append(node('small', 'pm-hidden-label', L.hidden)); return b; }
    function mark(structure) {
        dirty = true;
        $('status').textContent = L.dirty;
        renderTree(); renderPreview();
        if (structure) { renderSummary(); return; }
        var row = current();
        if (!row) return;
        $('heading').textContent = row.label || L.choose;
        $('result-name').textContent = row.label || L.choose;
        $('result-status').textContent = Number(row.active) ? L.visible : L.hidden;
        $('identity-icon').src = icon(row);
        $('icon-preview').src = icon(row);
        $('result-icon').src = icon(row);
    }
    function visible(row) {
        if (!Number(row.active) && !$('hidden').checked) return false;
        if (parent(row) && !Number(parent(row).active) && !$('hidden').checked) return false;
        var role = $('role').value, required = config.protectedRoutes[String(row.url_key).replace(/^\/+|\/+$/g, '')];
        return role === 'all' || !required || role === required;
    }
    function render() { renderTree(); renderPreview(); renderSummary(); }
    function renderTree() {
        var tree = $('tree'); tree.replaceChildren();
        var search = $('search').value.trim().toLocaleLowerCase();
        var groups = state.groups.slice().sort(order), items = state.items.slice().sort(order), found = false;
        items.filter(special).forEach(function (r) { if (!search || r.label.toLocaleLowerCase().includes(search)) { tree.append(button(r, false, 'pm-tree-row')); found = true; } });
        groups.forEach(function (g) {
            var children = items.filter(function (r) { return !['home','help','chat','logout'].includes(String(r.url_key).replace(/^\/+|\/+$/g, '')) && groupKey(r) === g.group_key; });
            var matches = children.filter(function (r) { return !search || g.label.toLocaleLowerCase().includes(search) || r.label.toLocaleLowerCase().includes(search); });
            if (search && !g.label.toLocaleLowerCase().includes(search) && !matches.length) return;
            found = true;
            var details = node('details', 'pm-group'); details.dataset.group = g.group_key;
            details.open = expanded.has(g.group_key) || (selected && matches.some(function (r) { return key(r, false) === selected; })) || !!search;
            var summary = node('summary'); summary.append(node('span', '', g.label + ' (' + children.length + ')'));
            if (!Number(g.active)) summary.append(node('small', 'pm-hidden-label', L.hidden));
            details.append(summary); details.append(button(g, true, 'pm-tree-row pm-group-row'));
            matches.forEach(function (r) { details.append(button(r, false, 'pm-tree-row pm-child')); });
            tree.append(details);
        });
        $('count').textContent = String(state.groups.length + state.items.length);
        if (!found) { tree.append(node('p', 'muted', L.no_results)); var clear = node('button', '', L.clear_search); clear.type = 'button'; clear.addEventListener('click', function () { $('search').value = ''; renderTree(); }); tree.append(clear); }
    }
    function renderPreview() {
        var box = $('preview-content'); box.replaceChildren();
        var items = state.items.slice().sort(order), groups = state.groups.slice().sort(order);
        items.filter(special).forEach(function (r) { if (visible(r) && r.url_key === 'home') box.append(button(r, false, 'pm-preview-item')); });
        groups.forEach(function (g) {
            var children = items.filter(function (r) { return !specialValue(r) && groupKey(r) === g.group_key && visible(r); });
            if ((!Number(g.active) && !$('hidden').checked) || !children.length) return;
            var wrap = node('div', 'pm-preview-group'); wrap.append(button(g, true, 'pm-preview-item'));
            var list = node('div', 'pm-preview-children'); children.forEach(function (r) { list.append(button(r, false, 'pm-preview-item')); });
            wrap.append(list); box.append(wrap);
        });
        items.filter(special).forEach(function (r) { if (visible(r) && r.url_key !== 'home' && r.url_key !== 'logout') box.append(button(r, false, 'pm-preview-item')); });
        if (!box.children.length) box.append(node('p', 'muted', L.empty));
    }
    function specialValue(r) { return ['home', 'help', 'chat', 'logout'].includes(String(r.url_key).replace(/^\/+|\/+$/g, '')); }
    function renderSummary() {
        var row = current();
        $('form').hidden = !row; $('empty').hidden = !!row; $('active').disabled = !row;
        if (!row) { $('heading').textContent = ''; return; }
        var group = isGroup(), p = !group && parent(row);
        $('heading').textContent = row.label || L.choose; $('crumb').textContent = group ? L.edit_group : (p ? p.label : L.edit_item);
        $('identity-icon').src = icon(row); $('icon-preview').src = icon(row); $('result-icon').src = icon(row);
        $('active').checked = Number(row.active) === 1;
        $('pl').value = row.label || ''; $('en').value = row.label_en || ''; $('de').value = row.label_de || '';
        $('lang').value = row.lang_key || ''; $('variant').value = row.css_class || '';
        $('icon').value = config.icons[row.icon_key] ? row.icon_key : '';
        $('file').value = ''; $('file-name').textContent = row.icon_upload ? L.icon_upload : (row.icon_key || L.icon_default);
        $('parent-label').hidden = group || special(row); $('type-label').hidden = group || special(row);
        $('route-label').hidden = group; $('url-label').hidden = group; $('new-tab-wrap').hidden = group; $('variant-wrap').hidden = group;
        $('special-note').textContent = special(row) ? L.special_note : '';
        var select = $('parent'); select.replaceChildren(); state.groups.slice().sort(order).forEach(function (g) { var opt = new Option(g.label, g.group_key); select.add(opt); });
        if (!group && !special(row)) select.value = groupKey(row);
        var route = $('route'); route.replaceChildren(); config.destinations.forEach(function (name) { route.add(new Option('/' + name, name)); });
        if (!group && !/^https?:\/\//i.test(row.url_key) && !config.destinations.includes(String(row.url_key).replace(/^\//, ''))) route.add(new Option(row.url_key, row.url_key));
        if (!group) { $('type').value = row._link_type || (/^https?:\/\//i.test(row.url_key) ? 'external' : 'game'); route.value = String(row.url_key).replace(/^\//, ''); $('url').value = $('type').value === 'external' ? row.url_key : ''; $('new-tab').checked = Number(row.open_new_tab) === 1; }
        routeVisibility();
        var siblings = group ? state.groups.slice().sort(order) : state.items.filter(function (r) { return groupKey(r) === groupKey(row); }).sort(order);
        var position = siblings.indexOf(row); $('order-label').textContent = L.order + ': ' + (position + 1) + ' / ' + siblings.length;
        $('up').disabled = position <= 0; $('down').disabled = position < 0 || position >= siblings.length - 1;
        $('result-name').textContent = row.label || L.choose; $('result-group').textContent = p ? p.label : '';
        $('result-status').textContent = Number(row.active) ? L.visible : L.hidden;
        $('delete').textContent = group ? L.delete_group : L.delete_item;
        $('delete').hidden = special(row);
    }
    function routeVisibility() {
        var row = current(); if (!row || isGroup()) return;
        var ext = $('type').value === 'external' && !special(row);
        $('route-label').hidden = ext; $('url-label').hidden = !ext;
        $('new-tab-wrap').hidden = !ext;
        $('type').disabled = special(row); $('route').disabled = special(row);
        $('destination-hint').textContent = ext ? $('url').value : '/' + $('route').value;
    }
    function select(id) { selected = id; var row = current(); if (row && !isGroup() && parent(row)) expanded.add(parent(row).group_key); render(); }
    root.addEventListener('click', function (e) { var hit = e.target.closest('[data-select]'); if (hit && root.contains(hit)) select(hit.dataset.select); });
    $('tree').addEventListener('toggle', function (e) { if (!e.target.matches('details[data-group]')) return; if (e.target.open) expanded.add(e.target.dataset.group); else expanded.delete(e.target.dataset.group); }, true);
    $('search').addEventListener('input', renderTree);
    ['hidden','role'].forEach(function (id) { $(id).addEventListener('change', renderPreview); });
    $('toggle').addEventListener('click', function () { var box = $('preview-content'); box.hidden = !box.hidden; this.setAttribute('aria-expanded', String(!box.hidden)); this.textContent = box.hidden ? L.expand : L.collapse; });
    $('device').addEventListener('click', function () { var phone = $('preview-content').classList.toggle('pm-phone'); this.textContent = phone ? L.desktop : L.phone; this.setAttribute('aria-pressed', String(phone)); });
    [['pl','label'], ['en','label_en'], ['de','label_de'], ['lang','lang_key'], ['variant','css_class']].forEach(function (pair) { $(pair[0]).addEventListener('input', function () { var row = current(); if (!row) return; row[pair[1]] = this.value; mark(); }); });
    $('active').addEventListener('change', function () { current().active = this.checked ? 1 : 0; mark(); });
    $('parent').addEventListener('change', function () { var r = current(); r.group_key = this.value; r.sort_order = Math.max(0, ...state.items.filter(function (x) { return x.group_key === r.group_key; }).map(function (x) { return Number(x.sort_order) || 0; })) + 10; expanded.add(r.group_key); mark(true); });
    $('type').addEventListener('change', function () { var r = current(); r._link_type = this.value; r.url_key = this.value === 'external' ? $('url').value : ($('route').value || config.destinations[0] || ''); r.open_new_tab = 0; routeVisibility(); mark(); });
    $('route').addEventListener('change', function () { current().url_key = this.value; routeVisibility(); mark(); });
    $('url').addEventListener('input', function () { current().url_key = this.value; routeVisibility(); mark(); });
    $('new-tab').addEventListener('change', function () { current().open_new_tab = this.checked ? 1 : 0; mark(); });
    $('icon').addEventListener('change', function () { var r = current(); r.icon_key = this.value; delete r.icon_upload; mark(); });
    $('reset-icon').addEventListener('click', function () { var r = current(); r.icon_key = ''; delete r.icon_upload; mark(); });
    $('file').addEventListener('change', function () { var file = this.files[0]; if (!file) return; if (file.size > 1048576 || !['image/png','image/jpeg','image/webp'].includes(file.type)) { $('file-name').textContent = L.file_error; this.value = ''; return; } var target = current(); var reader = new FileReader(); reader.onload = function () { target.icon_upload = reader.result; $('file-name').textContent = file.name; mark(); }; reader.readAsDataURL(file); });
    function move(direction) { var r = current(), peers = (isGroup() ? state.groups : state.items.filter(function (x) { return groupKey(x) === groupKey(r); })).slice().sort(order), i = peers.indexOf(r), other = peers[i + direction]; if (!other) return; peers.forEach(function (item, index) { item.sort_order = (index + 1) * 10; }); var temp = r.sort_order; r.sort_order = other.sort_order; other.sort_order = temp; mark(true); }
    $('up').addEventListener('click', function () { move(-1); }); $('down').addEventListener('click', function () { move(1); });
    $('add-group').addEventListener('click', function () { var r = { group_key: 'draft-' + Math.random().toString(36).slice(2, 12), label: '', label_en: '', label_de: '', lang_key: '', icon_key: '', active: 1, sort_order: (state.groups.length + 1) * 10 }; state.groups.push(r); expanded.add(r.group_key); selected = key(r, true); mark(true); $('pl').focus(); });
    $('add-item').addEventListener('click', function () { if (!state.groups.length || !config.destinations.length) { $('status').textContent = L.error; return; } var group = isGroup() ? current() : (!special(current() || {}) && current() ? parent(current()) : null); var freeRoute = config.destinations.find(function (route) { return !state.items.some(function (item) { return String(item.url_key).replace(/^\//, '') === route; }); }) || config.destinations[0]; var r = { id: 'draft-' + Math.random().toString(36).slice(2, 12), label: '', label_en: '', label_de: '', lang_key: '', icon_key: '', url_key: freeRoute, group_key: (group || state.groups[0]).group_key, active: 1, sort_order: (state.items.length + 1) * 10, css_class: '', open_new_tab: 0 }; state.items.push(r); expanded.add(r.group_key); selected = key(r, false); mark(true); $('pl').focus(); });
    $('delete').addEventListener('click', function () { var r = current(); if (!r) return; if (isGroup() && state.items.some(function (x) { return !special(x) && groupKey(x) === r.group_key; })) { $('status').textContent = L.delete_group_children; return; } var id = selected; window.confirmAction((isGroup() ? L.delete_group_confirm : L.delete_confirm).replace(':name', r.label || L.choose), function () { if (id.startsWith('g:')) state.groups = state.groups.filter(function (g) { return key(g, true) !== id; }); else state.items = state.items.filter(function (x) { return key(x, false) !== id; }); selected = null; mark(true); }); });
    $('undo').addEventListener('click', function () { state = clone(original); selected = null; dirty = false; $('status').textContent = L.saved; render(); });
    root.querySelectorAll('[data-pm-save]').forEach(function (b) { b.addEventListener('click', save); });
    function payload() { var beforeGroups = new Set(original.groups.map(function (g) { return g.group_key; })), beforeItems = new Set(original.items.map(function (x) { return String(x.id); })), groups = state.groups.filter(function (g) { return beforeGroups.has(g.group_key); }), items = state.items.filter(function (x) { return beforeItems.has(String(x.id)); }); return { version: original.version, groups: groups, items: items, new_groups: state.groups.filter(function (g) { return !beforeGroups.has(g.group_key); }), new_items: state.items.filter(function (x) { return !beforeItems.has(String(x.id)); }).map(function (x) { var copy = clone(x); delete copy.id; return copy; }), deleted_groups: original.groups.filter(function (g) { return !state.groups.some(function (x) { return x.group_key === g.group_key; }); }).map(function (g) { return g.group_key; }), deleted_items: original.items.filter(function (x) { return !state.items.some(function (r) { return String(r.id) === String(x.id); }); }).map(function (x) { return x.id; }) }; }
    async function save() {
        if (saving || !dirty) return;
        saving = true; $('status').textContent = L.saving;
        $('preview-content').inert = true; $('tree').inert = true; $('form').inert = true;
        $('add-item').disabled = true; $('add-group').disabled = true; $('undo').disabled = true;
        root.querySelectorAll('[data-pm-save]').forEach(function (b) { b.disabled = true; });
        try {
            var body = new URLSearchParams({ csrf_token: config.csrf, payload: JSON.stringify(payload()) });
            var response = await fetch(config.endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: body });
            var data = await response.json();
            if (!response.ok || !data.ok) { $('status').textContent = data.error === 'conflict' ? L.conflict : L.error; if (data.error === 'conflict') showReload(); return; }
            registerSavedIcons(data.data);
            original = clone(data.data); state = clone(original); selected = null; dirty = false; $('status').textContent = L.saved; render();
        } catch (e) { $('status').textContent = L.failed; }
        finally {
            saving = false;
            $('preview-content').inert = false; $('tree').inert = false; $('form').inert = false;
            $('add-item').disabled = false; $('add-group').disabled = false; $('undo').disabled = false;
            root.querySelectorAll('[data-pm-save]').forEach(function (b) { b.disabled = false; });
        }
    }
    function registerSavedIcons(data) {
        data.groups.concat(data.items).forEach(function (row) {
            var name = row.icon_key;
            if (!/^custom-[a-f0-9]{24}$/.test(name) || config.icons[name]) return;
            config.icons[name] = '/assets/img/icons/game-nav/' + name + '.png';
            $('icon').add(new Option(name, name));
        });
    }
    function showReload() { var b = $('reload'); if (!b) { b = node('button', '', L.reload); b.type = 'button'; b.id = 'pm-reload'; b.addEventListener('click', function () { location.reload(); }); $('status').after(b); } }
    window.addEventListener('beforeunload', function (e) { if (!dirty) return; e.preventDefault(); e.returnValue = ''; });
    if (state.groups.length) expanded.add(state.groups[0].group_key);
    select(config.items.length ? key(config.items.find(function (x) { return x.url_key === 'market'; }) || config.items[0], false) : (config.groups.length ? key(config.groups[0], true) : null));
}());
