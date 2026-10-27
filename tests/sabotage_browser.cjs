const { test } = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const root = path.resolve(__dirname, '..');
const fixture = `<!doctype html><div class="sabotage-page" data-sabotage-suggest-url="http://app.local/sabotage" data-sabotage-currency="zł" data-sabotage-cash="200000" data-sabotage-market-score="40" data-sabotage-market-threshold="30" data-sabotage-empty="Wybierz" data-sabotage-funds="Brak gotówki" data-sabotage-market="Brak śladu" data-sabotage-cooldown="Przerwa do">
<button data-sabotage-action data-id="7" data-name="Budżet" data-description="Kosztuje" data-severity="Średnia" data-risk="medium" data-chance="65,0%" data-cooldown="180" data-market="0" aria-pressed="false">Budżet</button>
<button data-sabotage-action data-id="8" data-name="Donos" data-description="Ryzyko" data-severity="Wysoka" data-risk="high" data-chance="55,0%" data-cooldown="180" data-market="1" aria-pressed="false">Donos</button>
<form data-sabotage-search-form><input data-sabotage-search name="q" aria-expanded="false"><span id="sabotage-suggestions" role="listbox" hidden></span><select data-sabotage-status name="status"><option value="all">Wszyscy</option><option value="active">Aktywni</option></select></form>
<button data-sabotage-target data-id="2" data-name="Stefan" data-status="active" data-quotes='{"7":150000,"8":90000}' data-cooldowns='{}' aria-pressed="false">Stefan</button>
<button data-sabotage-target data-id="3" data-name="Nieaktywny" data-status="inactive" data-quotes='{"7":150000}' data-cooldowns='{}' aria-pressed="false" disabled>Nieaktywny</button>
<strong data-sabotage-chosen-action></strong><p data-sabotage-chosen-description></p><span data-sabotage-chosen-risk></span><strong data-sabotage-chosen-target></strong><span data-sabotage-chance></span><span data-sabotage-cost></span><span data-sabotage-cooldown></span><p data-sabotage-disabled></p><button data-sabotage-execute disabled>Wykonaj</button>
<form id="sabotage-action-form"><input name="option_id"><input name="target_player_id"></form>
<dialog id="sabotage-confirm-dialog"><span data-sabotage-confirm-action></span><span data-sabotage-confirm-target></span><span data-sabotage-confirm-cost></span><span data-sabotage-confirm-cooldown></span><button data-sabotage-cancel>Anuluj</button><button data-sabotage-confirm>Potwierdź</button></dialog></div>`;

test('selected option and target drive server quote and accessible confirmation', async () => {
    const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    try {
        const page = await browser.newPage();
        await page.route('http://app.local/sabotage', route => route.fulfill({ body: fixture, contentType: 'text/html' }));
        await page.route('**/sabotage?suggest=*', route => route.fulfill({ json: [{ id: 2, username: 'Stefan', company_name: 'Stefan', status: 'active' }] }));
        await page.goto('http://app.local/sabotage');
        await page.addScriptTag({ path: path.join(root, 'assets/js/sabotage.js') });
        assert.equal(await page.locator('[data-sabotage-execute]').isDisabled(), true);
        await page.locator('[data-sabotage-action][data-id="7"]').click();
        await page.locator('[data-sabotage-target][data-id="2"]').click();
        assert.equal(await page.locator('[data-sabotage-execute]').isEnabled(), true);
        assert.match(await page.locator('[data-sabotage-cost]').innerText(), /150\s?000/);
        await page.locator('[data-sabotage-execute]').click();
        assert.equal(await page.locator('#sabotage-confirm-dialog').evaluate(el => el.open), true);
        assert.equal(await page.locator('[data-sabotage-confirm-target]').innerText(), 'Stefan');
        await page.locator('[data-sabotage-cancel]').click();
        assert.equal(await page.locator('[data-sabotage-execute]').evaluate(el => document.activeElement === el), true);
        await page.locator('[data-sabotage-search]').fill('st');
        await page.locator('#sabotage-suggestions [role="option"]').first().waitFor();
        assert.equal(await page.locator('[data-sabotage-search]').getAttribute('aria-expanded'), 'true');
        await page.locator('[data-sabotage-action][data-id="8"]').click();
        assert.match(await page.locator('[data-sabotage-cost]').innerText(), /90\s?000/);
    } finally { await browser.close(); }
});

test('real PHP view stays usable from 320 to 1440 CSS pixels', async () => {
    const html = execFileSync(process.env.PHP_BIN || 'C:/xampp1/bin/php/php8.5.0/php.exe', [path.join(root, 'tests/fixtures/sabotage-view.php')], { encoding: 'utf8' });
    const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    try {
        const page = await browser.newPage();
        await page.setContent(html);
        await page.addStyleTag({ path: path.join(root, 'assets/css/sabotage.css') });
        assert.equal(await page.locator('form#sabotage-action-form input[name="operation_token"]').count(), 1);
        assert.match(await page.locator('.sabotage-pagination a').last().getAttribute('href'), /hpage=2/);
        for (const width of [320, 360, 390, 768, 1024, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            const metrics = await page.evaluate(() => ({
                actual: document.documentElement.scrollWidth,
                viewport: window.innerWidth,
                columns: getComputedStyle(document.querySelector('.sabotage-option-grid')).gridTemplateColumns.split(' ').length,
                touchHeight: document.querySelector('.sabotage-target-row').getBoundingClientRect().height,
            }));
            assert.ok(metrics.actual <= metrics.viewport, `horizontal overflow at ${width}: ${JSON.stringify(metrics)}`);
            assert.ok(metrics.touchHeight >= 44, `target too short at ${width}`);
            assert.equal(metrics.columns, width < 640 ? 1 : 2, `action columns at ${width}`);
        }
    } finally { await browser.close(); }
});
