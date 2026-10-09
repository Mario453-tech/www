const assert = require('node:assert/strict');
const { test } = require('node:test');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../../assets/js/chat.js'), 'utf8');
const match = source.match(/dom\.input\.addEventListener\('keydown', function \(event\) \{([\s\S]*?)\n            \}\);/);
assert.ok(match, 'Composer keyboard handler must exist');
const handler = new Function('event', 'dom', match[1]);

test('Enter submits once; Shift+Enter and IME preserve input', () => {
    for (const [options, hidden, disabled, expected] of [
        [{ key: 'Enter' }, false, false, 1],
        [{ key: 'Enter', shiftKey: true }, false, false, 0],
        [{ key: 'Enter', isComposing: true }, false, false, 0],
        [{ key: 'Enter', keyCode: 229 }, false, false, 0],
        [{ key: 'a' }, false, false, 0],
        [{ key: 'Enter' }, true, false, 0],
        [{ key: 'Enter' }, false, true, 0],
    ]) {
        let sent = 0;
        let prevented = false;
        handler({ ...options, preventDefault() { prevented = true; } }, {
            form: { hidden, requestSubmit() { sent++; } }, sendBtn: { disabled },
        });
        assert.equal(sent, expected);
        if (expected) assert.equal(prevented, true);
        if (options.shiftKey || options.isComposing || options.keyCode === 229) assert.equal(prevented, false);
    }
});
