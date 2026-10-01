const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function fixture() {
    const boxes = Array.from({length: 10}, (_, i) => ({value: String(i + 11), checked: i === 0, addEventListener(type, fn) { this[type] = fn; }}));
    const hidden = {value: '1'};
    const selectAll = {checked: false, addEventListener(type, fn) { this[type] = fn; }};
    const count = {dataset: {label: 'Selected :count'}};
    const link = {href: 'https://example.test/admin/gm_tools.php?delete_page=3&selected%5B0%5D=11&selected%5B1%5D=1'};
    let submitted = false;
    let confirmCallback;
    const form = {
        dataset: {confirm: 'Confirm', confirmTitle: 'Delete', confirmLabel: 'Delete'},
        querySelectorAll(selector) {
            if (selector === '[data-gm-carried-selection]') return [hidden];
            return selector.includes('type="checkbox"') ? boxes : [...boxes, hidden];
        },
        querySelector(selector) { return selector.includes('pagination') ? {querySelectorAll: () => [link]} : count; },
        addEventListener(type, fn) { this[type] = fn; }
    };
    vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../assets/js/admin_gm.js'), 'utf8'), {
        document: {querySelector: () => form, getElementById: () => selectAll},
        window: {location: {href: link.href}}, URL,
        confirmAction(message, callback) { confirmCallback = callback; },
        HTMLFormElement: {prototype: {submit() { submitted = true; }}}
    });
    return {boxes, selectAll, count, link, form, get submitted() { return submitted; }, confirm: () => confirmCallback()};
}

test('page selection excludes hidden accounts from the select-all state', () => {
    const f = fixture();
    assert.equal(f.count.textContent, 'Selected 2');
    f.selectAll.checked = true;
    f.selectAll.change();
    assert.equal(f.count.textContent, 'Selected 11');
    assert.equal(f.selectAll.checked, true);
    assert.equal(f.selectAll.indeterminate, false);
    f.selectAll.checked = false;
    f.selectAll.change();
    assert.equal(f.count.textContent, 'Selected 1');
    assert.deepEqual(new URL(f.link.href).searchParams.getAll('selected[]'), ['1']);
});

test('unchecked IDs are removed from indexed PHP query parameters', () => {
    const f = fixture();
    f.boxes[0].checked = false;
    f.boxes[0].change();
    const params = new URL(f.link.href).searchParams;
    assert.equal(params.has('selected[0]'), false);
    assert.deepEqual(params.getAll('selected[]'), ['1']);
    assert.equal(params.get('delete_page'), '3');
});

test('keyboard form submission requires confirmation before submitting', () => {
    const f = fixture();
    let prevented = false;
    f.form.submit({preventDefault() { prevented = true; }});
    assert.equal(prevented, true);
    assert.equal(f.submitted, false);
    f.confirm();
    assert.equal(f.submitted, true);
});
