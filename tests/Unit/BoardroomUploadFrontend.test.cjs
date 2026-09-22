'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');
const source = fs.readFileSync(path.join(__dirname, '../../assets/js/admin-template-upload.js'), 'utf8');

function setup(options = {}) {
    const messages = Object.fromEntries(['name', 'file', 'size', 'image', 'csrf', 'http', 'response', 'network', 'timeout', 'server', 'saved'].map(key => [key, key]));
    messages.progress = 'chunk :current/:total';
    const listeners = {};
    const timers = new Map();
    const requests = [];
    const status = { textContent: '' };
    Object.defineProperty(status, 'innerHTML', { set() { throw new Error('Unsafe HTML rendering'); } });
    const button = { disabled: false };
    const name = { value: '' };
    const checkbox = { checked: options.role !== false, disabled: false };
    const select = { value: 'M', disabled: false };
    const token = { value: options.token === undefined ? 'csrf-test' : options.token };
    const file = { size: options.size === undefined ? 1048592 : options.size, type: options.mime === undefined ? 'image/png' : options.mime,
        slice(start, end) { return { size: Math.max(0, Math.min(end, this.size) - start) }; } };
    const picker = { files: options.noFile ? [] : [file], disabled: false };
    const form = {
        dataset: { uploadMessages: JSON.stringify(messages) },
        elements: [button, picker, checkbox, select],
        addEventListener(type, callback) { listeners[type] = callback; },
        querySelector(selector) {
            if (selector.includes('csrf_token')) return token;
            if (!selector.includes('data-role="hr"')) return null;
            return selector.includes('role-cb') ? checkbox : select;
        },
        setAttribute() {},
        removeAttribute() {}
    };
    const elements = { 'br-bg-upload-form': form, 'br-bg-file-picker': picker, 'br-bg-file-status': status,
        'br-bg-submit-btn': button, 'br-bg-name-input': name, 'br-bg-name-preview': { textContent: '' } };
    vm.runInNewContext(source, {
        document: { getElementById: id => elements[id] || null, querySelector: () => null },
        window: { location: { origin: 'https://test.invalid', reload() {} } },
        URL, Uint8Array, AbortController,
        crypto: options.crypto || webcrypto,
        setTimeout(callback, delay) { timers.set(delay, callback); return delay; },
        clearTimeout(id) { timers.delete(id); },
        async fetch(url, config) {
            requests.push({ url, config });
            if (options.fetch) return options.fetch(url, config, timers);
            const index = Number(url.searchParams.get('chunk_index'));
            const total = Number(url.searchParams.get('total_chunks'));
            return { ok: true, json: async () => ({ ok: true, done: index === total - 1, chunk: index, msg: 'saved' }) };
        }
    });
    return { requests, status, button, picker, listeners, timers, name,
        submit: () => listeners.submit({ preventDefault() {} }) };
}

test('1MiB chunks preserve CSRF, upload ID and response contract', async () => {
    const app = setup();
    await app.submit();
    assert.equal(app.requests.length, 2);
    assert.deepEqual(app.requests.map(r => r.config.body.size), [1048576, 16]);
    const id = app.requests[0].url.searchParams.get('upload_id');
    assert.match(id, /^[a-f0-9]{32}$/);
    for (const { url, config } of app.requests) {
        assert.equal(url.searchParams.get('upload_id'), id);
        assert.equal(url.searchParams.get('csrf_token'), 'csrf-test');
        assert.equal(url.searchParams.get('bg_name'), 'hr_M');
        assert.equal(config.headers['Content-Type'], 'application/octet-stream');
    }
    assert.equal(app.status.textContent, 'saved');
    assert.equal(app.button.disabled, true);
    assert.ok(app.timers.has(1200));
});

for (const [options, error] of [
    [{ role: false }, 'name'], [{ noFile: true }, 'file'], [{ size: 0 }, 'size'],
    [{ size: 20971521 }, 'size'], [{ mime: 'text/html' }, 'image'], [{ token: '' }, 'csrf']
]) {
    test('preflight rejects ' + JSON.stringify(options), async () => {
        const app = setup(options);
        await app.submit();
        assert.equal(app.status.textContent, error);
        assert.equal(app.requests.length, 0);
        assert.equal(app.button.disabled, false);
    });
}

for (const [label, fetch, error] of [
    ['HTTP rejection', async () => ({ ok: false }), 'http'],
    ['HTML instead of JSON', async () => ({ ok: true, json() { throw new SyntaxError('<html>secret</html>'); } }), 'response'],
    ['network', async () => { throw new Error('private network details'); }, 'network'],
    ['null response', async () => ({ ok: true, json: async () => null }), 'response'],
    ['early completion', async () => ({ ok: true, json: async () => ({ ok: true, done: true }) }), 'response'],
    ['wrong acknowledgement', async () => ({ ok: true, json: async () => ({ ok: true, done: false, chunk: 8 }) }), 'response'],
    ['backend error rendered safely', async () => ({ ok: true, json: async () => ({ ok: false, err: '<img src=x onerror=alert(1)>' }) }), '<img src=x onerror=alert(1)>'],
    ['timeout', async (url, config, timers) => { timers.get(60000)(); throw new Error('aborted'); }, 'timeout']
]) {
    test(label + ' restores controls and allows retry', async () => {
        const app = setup({ fetch });
        await app.submit();
        assert.equal(app.status.textContent, error);
        assert.equal(app.requests.length, 1);
        assert.equal(app.button.disabled, false);
        assert.equal(app.picker.disabled, false);
        assert.equal(app.timers.has(60000), false);
        await app.submit();
        assert.equal(app.requests.length, 2);
        assert.notEqual(app.requests[0].url.searchParams.get('upload_id'), app.requests[1].url.searchParams.get('upload_id'));
    });
}

test('missing final completion is not reported as success', async () => {
    const app = setup({ size: 1, fetch: async () => ({ ok: true, json: async () => ({ ok: true, done: false, chunk: 0 }) }) });
    await app.submit();
    assert.equal(app.status.textContent, 'response');
    assert.equal(app.button.disabled, false);
    assert.equal(app.timers.has(1200), false);
});

test('20MiB boundary is accepted without exceeding 32 chunks', async () => {
    const app = setup({ size: 20971520 });
    await app.submit();
    assert.equal(app.requests.length, 20);
    assert.ok(app.requests.every(r => r.config.body.size === 1048576));
    assert.equal(app.status.textContent, 'saved');
});

test('duplicate submit during an upload sends nothing extra', async () => {
    let release;
    const app = setup({ size: 1, fetch: () => new Promise(resolve => { release = resolve; }) });
    const pending = app.submit();
    await app.submit();
    assert.equal(app.requests.length, 1);
    release({ ok: true, json: async () => ({ ok: true, done: true }) });
    await pending;
    assert.equal(app.status.textContent, 'saved');
});

test('unexpected local exception is not disclosed', async () => {
    const app = setup({ crypto: { getRandomValues() { throw new Error('internal details'); } } });
    await app.submit();
    assert.equal(app.status.textContent, 'server');
    assert.equal(app.button.disabled, false);
});

test('upload template uses external JS and accessible status', () => {
    const template = fs.readFileSync(path.join(__dirname, '../../templates/views/admin/template_editor/main.php'), 'utf8');
    assert.match(template, /src="\/assets\/js\/admin-template-upload.js/);
    assert.doesNotMatch(template, /<script>/);
    assert.match(template, /role="status" aria-live="polite"/);
    assert.doesNotMatch(source, /innerHTML|alert\(/);
});
