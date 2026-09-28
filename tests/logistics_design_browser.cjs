const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'php';
const fixture = path.join(__dirname, 'fixtures', 'logistics_design_render.php');
const screenshots = process.env.UI_SCREENSHOT_DIR;

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'msedge' });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('https://fixture.test/assets/**', route => {
            const file = path.join(root, new URL(route.request().url()).pathname);
            const type = file.endsWith('.css') ? 'text/css' : file.endsWith('.svg') ? 'image/svg+xml' : 'text/javascript';
            route.fulfill({ body: fs.readFileSync(file), contentType: type });
        });
        for (const locale of ['pl', 'en']) {
            const content = execFileSync(php, [fixture, locale], { encoding: 'utf8' });
            const styles = ['style.css', 'logistics.css', 'logistics_incidents.css', 'logistics_pipeline_staffing.css', 'logistics_design.css'];
            const html = `<!doctype html><html lang="${locale}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Logistics fixture</title><base href="https://fixture.test/">${styles.map(file => `<link rel="stylesheet" href="/assets/css/${file}">`).join('')}</head><body><main class="game-shell-module">${content}</main><script>window.HubUI={};window.hubBuyNewSubmit=function(){};</script><script src="/assets/js/logistics_hub_browser.js"></script><script src="/assets/js/logistics_design.js"></script></body></html>`;
            await page.route('https://fixture.test/', route => route.fulfill({ body: html, contentType: 'text/html' }));
            await page.goto('https://fixture.test/');
            assert.equal(await page.locator('.logistics-section-nav a').count(), 6);
            assert.equal(await page.locator('.logistics-design-section').count(), 6);
            assert.equal(await page.locator('.logistics-hub-card').count(), 2);
            assert.equal(await page.locator('.logistics-pipeline-card').count(), 2);
            assert.equal(await page.locator('[data-lhb-card]').count(), 2);
            assert.equal(await page.locator('.logistics-entity-row').count(), 4);
            for (const width of [320, 360, 390, 768, 1024, 1440]) {
                await page.setViewportSize({ width, height: 900 });
                await page.waitForTimeout(120);
                const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
                if (overflow) {
                    const culprits = await page.evaluate(() => ({ widths: [document.documentElement, document.body, document.querySelector('main'), document.querySelector('.logistics-design'), document.querySelector('.logistics-section-nav'), ...document.querySelectorAll('.logistics-design-section')].map(element => [element.className, element.clientWidth, element.scrollWidth]), innerWidths: Array.from(document.querySelectorAll('#logistics-owned-section *')).filter(element => element.scrollWidth > element.clientWidth + 1 && element.scrollWidth < 500).slice(0, 15).map(element => [element.className, element.clientWidth, element.scrollWidth]), elements: Array.from(document.querySelectorAll('body *'))
                        .filter(element => element.getBoundingClientRect().right > innerWidth + 1)
                        .slice(0, 12).map(element => ({ tag: element.tagName, className: String(element.className).slice(0, 80), right: Math.round(element.getBoundingClientRect().right) })) }));
                    assert.equal(overflow, false, `${locale}/${width}: horizontal page overflow ${JSON.stringify(culprits)}`);
                }
                if (screenshots && locale === 'pl' && [390, 1440].includes(width)) {
                    fs.mkdirSync(screenshots, { recursive: true });
                    await page.screenshot({ path: path.join(screenshots, `logistics-${width}.png`), fullPage: false });
                }
            }
            await page.setViewportSize({ width: 1440, height: 900 });
            const hubButtons = page.locator('#logistics-owned-section .logistics-entity-row');
            await hubButtons.nth(1).click();
            assert.equal(await hubButtons.nth(1).getAttribute('aria-current'), 'true');
            assert.equal(await hubButtons.nth(0).getAttribute('aria-current'), null);
            assert.equal(await page.locator('#logistics-owned-section .logistics-hub-card:visible').count(), 1);
            if (screenshots && locale === 'pl') { await page.waitForTimeout(400); await page.screenshot({ path: path.join(screenshots, 'logistics-hubs-1440.png') }); }
            await page.setViewportSize({ width: 320, height: 800 });
            await hubButtons.nth(0).focus();
            await page.keyboard.press('Enter');
            assert.equal(await hubButtons.nth(0).getAttribute('aria-current'), 'true');
            assert.equal(await page.locator('#logistics-owned-section .logistics-hub-card:visible').count(), 1);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, `${locale}: mobile hub detail overflows`);
            await page.setViewportSize({ width: 1440, height: 900 });
            const pipeButtons = page.locator('#logistics-pipelines-section .logistics-entity-row');
            await pipeButtons.nth(1).click();
            assert.equal(await pipeButtons.nth(1).getAttribute('aria-current'), 'true');
            assert.equal(await page.locator('#logistics-pipelines-section .logistics-pipeline-card:visible').count(), 1);
            if (screenshots && locale === 'pl') { await page.waitForTimeout(400); await page.screenshot({ path: path.join(screenshots, 'logistics-pipes-1440.png') }); }
            await page.locator('.logistics-section-nav a[href="#logistics-incidents-section"]').click();
            assert.equal(await page.locator('.logistics-section-nav a[aria-current="location"]').getAttribute('href'), '#logistics-incidents-section');
            assert.equal(await page.locator('.logistics-incident-filters input[type="search"]').count(), 1);
            assert.equal(await page.locator('.logistics-incidents-row').count(), 1);
            if (screenshots && locale === 'pl') { await page.waitForTimeout(400); await page.screenshot({ path: path.join(screenshots, 'logistics-incidents-1440.png') }); }
            await page.locator('.logistics-section-nav a[href="#logistics-market-section"]').click();
            await page.locator('#lhb-search').fill('Beta');
            assert.equal(await page.locator('[data-lhb-card]:visible').count(), 1);
            assert.ok((await page.locator('[data-lhb-card]:visible').innerText()).includes('Beta'));
            if (screenshots && locale === 'pl') { await page.waitForTimeout(400); await page.screenshot({ path: path.join(screenshots, 'logistics-market-filtered-1440.png') }); }
            assert.deepEqual(errors, [], `${locale}: browser errors`);
            await page.unroute('https://fixture.test/');
        }
    } finally {
        await browser.close();
    }
    console.log('Logistics design browser checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
