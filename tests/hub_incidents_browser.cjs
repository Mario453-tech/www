const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'php';
const fixture = path.join(__dirname, 'fixtures', 'hub_incidents_render.php');
const shots = process.env.UI_SCREENSHOT_DIR;

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'msedge' });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('https://fixture.test/assets/**', route => {
            const file = path.join(root, new URL(route.request().url()).pathname);
            route.fulfill({ body: fs.readFileSync(file), contentType: file.endsWith('.css') ? 'text/css' : 'text/javascript' });
        });
        for (const locale of ['pl', 'en']) {
            for (const surface of ['overview', 'list', 'config', 'incidents', 'logistics']) {
                const content = execFileSync(php, [fixture, surface, locale], { encoding: 'utf8' });
                const admin = ['overview', 'list', 'config'].includes(surface);
                const css = admin
                    ? ['admin.css', 'admin_logistics.css', 'admin_hubs.css', 'admin_staffing.css', 'admin_hubs_view.css']
                    : surface === 'logistics'
                        ? ['style.css', 'logistics.css', 'logistics_incidents.css']
                        : ['style.css', 'well-grid.css', 'technical.css', 'technical_incidents.css'];
                const body = surface === 'logistics'
                    ? `<main class="logistics-page">${content}</main>`
                    : `<main class="t-wrap">${content}</main>`;
                const html = admin
                    ? content.replace('<head>', '<head><base href="https://fixture.test/">')
                    : `<!doctype html><html lang="${locale}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="https://fixture.test/"><title>${surface}</title>${css.map(file => `<link rel="stylesheet" href="/assets/css/${file}">`).join('')}</head><body>${body}</body></html>`;
                await page.route('https://fixture.test/', route => route.fulfill({ body: html, contentType: 'text/html' }));
                await page.goto('https://fixture.test/');
                assert.ok((await page.title()).includes(admin ? (locale === 'pl' ? 'Huby' : 'Logistics') : surface));
                assert.ok((await page.locator('body').innerText()).length > 100);
                for (const width of [320, 360, 390, 768, 1024, 1440, 1680]) {
                    await page.setViewportSize({ width, height: 940 });
                    await page.waitForTimeout(260);
                    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
                    assert.equal(overflow, false, `${surface}/${locale}/${width} horizontal overflow`);
                    if (shots && locale === 'pl' && [320, 1680].includes(width)) {
                        fs.mkdirSync(shots, { recursive: true });
                        await page.screenshot({ path: path.join(shots, `${surface}-${width}.png`), fullPage: true });
                    }
                }
                if (surface === 'incidents') {
                    assert.equal(await page.locator('.incident-panel').count(), 2);
                    assert.equal(await page.locator('.incident-panel-heading .incident-svg--heading').count(), 2);
                    assert.ok(await page.locator('.inc-meta-source .incident-svg--meta').count() > 0);
                    assert.ok(await page.locator('.inc-time .incident-svg--time').count() > 0);
                    assert.equal(await page.locator('.incident-extra').count(), 2);
                    assert.equal(await page.locator('.incident-panel').first().locator('.inc-row:visible').count(), 3);
                    await page.locator('.incident-extra').first().locator('summary').click();
                    assert.equal(await page.locator('.incident-panel').first().locator('.inc-row:visible').count(), 4);
                    assert.ok(await page.locator('.inc-repair-link').first().getAttribute('href'));
                    await page.locator('.incident-panel-heading').first().click();
                    assert.equal(await page.locator('.incident-panel').first().locator('.inc-row:visible').count(), 0);
                } else if (surface === 'logistics') {
                    assert.equal(await page.locator('.logistics-incidents-row:visible').count(), 3);
                    assert.equal(await page.locator('.logistics-incidents-heading .incident-svg--heading').count(), 1);
                    assert.equal(await page.locator('.logistics-incidents-time .incident-svg--time').count(), 4);
                    assert.equal(await page.locator('.logistics-incidents-badge').count(), 4);
                    assert.equal(await page.locator('.logistics-incidents-pagination a').count(), 1);
                    assert.ok((await page.locator('.logistics-incidents-heading > span').innerText()).includes(locale === 'pl' ? '1–4 z 206' : '1–4 of 206'));
                    assert.ok((await page.locator('.logistics-incidents-pagination a').getAttribute('href')).includes('hub_incident_page=2'));
                    await page.locator('.logistics-incidents-extra summary').click();
                    assert.equal(await page.locator('.logistics-incidents-row:visible').count(), 4);
                    await page.locator('.logistics-incidents-heading').click();
                    assert.equal(await page.locator('.logistics-incidents-row:visible').count(), 0);
                } else {
                    assert.equal(await page.locator('.hub-view-nav a').count(), 3);
                    assert.equal(await page.locator('.hub-view-nav [aria-current="page"]').count(), 1);
                }
                assert.deepEqual(errors, [], `${surface}/${locale} script errors`);
                await page.unroute('https://fixture.test/');
            }
        }
    } finally {
        await browser.close();
    }
    console.log('Hub and incident browser checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
