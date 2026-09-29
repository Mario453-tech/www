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
            const styles = ['style.css', 'logistics.css', 'logistics_incidents.css', 'logistics_pipeline_staffing.css', 'logistics_design.css'];
            await page.route(/^https:\/\/fixture\.test\/(?:logistics)?(?:\?.*)?$/, route => {
                const query = new URL(route.request().url()).search.slice(1);
                const content = execFileSync(php, [fixture, locale, query], { encoding: 'utf8' });
                const html = `<!doctype html><html lang="${locale}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Logistics fixture</title><base href="https://fixture.test/">${styles.map(file => `<link rel="stylesheet" href="/assets/css/${file}">`).join('')}</head><body><main class="game-shell-module">${content}</main><script>window.HubUI={};window.hubBuyNewSubmit=function(){};</script><script src="/assets/js/logistics_hub_browser.js"></script><script src="/assets/js/logistics_design.js"></script></body></html>`;
                route.fulfill({ body: html, contentType: 'text/html' });
            });
            await page.goto('https://fixture.test/');
            assert.equal(await page.locator('.logistics-section-nav a').count(), 6);
            assert.equal(await page.locator('.logistics-design-section').count(), 6);
            assert.equal(await page.locator('.logistics-hub-card').count(), 2);
            assert.equal(await page.locator('.logistics-pipeline-card').count(), 2);
            assert.equal(await page.locator('[data-lhb-card]').count(), 10);
            assert.equal(await page.locator('.logistics-entity-row').count(), 4);
            assert.equal(await page.locator('.logistics-table--mix .logistics-table-row').count(), 3);
            assert.equal(await page.locator('.logistics-road-trip-card .logistics-facts > div').count(), 4);
            assert.equal(await page.locator('.logistics-tanker-card').count(), 1);
            assert.equal(await page.locator('.logistics-transport-kpi').count(), 3);
            assert.equal(await page.locator('.logistics-table--protection-combined .logistics-table-row').count(), 2);
            assert.match(await page.locator('.logistics-table--marine-active').innerText(), /(?:Dotarł, czeka na port|Arrived, waiting for port)/);
            assert.match(await page.locator('.logistics-transport-kpi').first().innerText(), /2/);
            assert.equal(await page.locator('#logistics-transport-section .logistics-optimizer-trigger').count(), 1);
            assert.match(await page.locator('.logistics-kpi').first().innerText(), /2 (aktyw|active)/);
            assert.equal(await page.locator('#logistics-summary .logistics-alert').count(), 0);
            assert.equal((await page.locator('.logistics-table--mix').innerText()).includes('Nie ustawiono'), false);
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
            const hubLayout = await page.locator('#logistics-owned-section .logistics-entity-browser').evaluate(el => {
                const list = el.querySelector('.logistics-entity-list').getBoundingClientRect();
                const detail = el.querySelector('.logistics-entity-detail').getBoundingClientRect();
                return { listBottom: list.bottom, detailTop: detail.top };
            });
            assert.ok(hubLayout.detailTop >= hubLayout.listBottom, 'hub detail appears below the full-width list');
            const kpiTops = await page.locator('#logistics-pipelines-section .logistics-insight-pill').evaluateAll(elements => elements.map(el => Math.round(el.getBoundingClientRect().top)));
            assert.equal(new Set(kpiTops).size, 1, 'five pipeline indicators share one row on desktop');
            await page.locator('.logistics-section-nav a[href="#logistics-transport-section"]').click();
            if (screenshots && locale === 'pl') { await page.waitForTimeout(400); await page.screenshot({ path: path.join(screenshots, 'logistics-transport-1440.png') }); }
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
            const activeIncidentNav = await page.locator('.logistics-section-nav a[aria-current="location"]').getAttribute('href');
            const navPositions = await page.evaluate(() => ({ scroll: scrollY, incidents: document.getElementById('logistics-incidents-section').getBoundingClientRect().top, pipelines: document.getElementById('logistics-pipelines-section').getBoundingClientRect().top }));
            assert.equal(activeIncidentNav, '#logistics-incidents-section', JSON.stringify(navPositions));
            assert.equal(await page.locator('.logistics-incident-filters input[type="search"]').count(), 1);
            assert.equal(await page.locator('#logistics-incidents-section h2').count(), 1);
            assert.equal(await page.locator('.logistics-incidents-row').count(), 1);
            await page.locator('.logistics-incidents-source a').click();
            assert.equal(await page.locator('#logistics-owned-section .logistics-entity-row').first().getAttribute('aria-current'), 'true');
            await page.locator('.logistics-section-nav a[href="#logistics-incidents-section"]').click();
            if (screenshots && locale === 'pl') { await page.waitForTimeout(400); await page.screenshot({ path: path.join(screenshots, 'logistics-incidents-1440.png') }); }
            await page.locator('.logistics-section-nav a[href="#logistics-market-section"]').click();
            assert.match(await page.locator('#lhb-count').innerText(), /12/);
            await page.locator('.logistics-market-pagination a').last().click();
            assert.equal(await page.locator('[data-lhb-card]').count(), 2);
            assert.match(await page.locator('#lhb-count').innerText(), /12/);
            await page.locator('#lhb-search').fill('Beta');
            await page.locator('.logistics-market-filter button[type="submit"]').click();
            assert.match(page.url(), /#logistics-market-section$/, 'market filters retain the current section');
            assert.equal(await page.locator('[data-lhb-card]').count(), 1);
            assert.ok((await page.locator('[data-lhb-card]').innerText()).includes('Beta'));
            await page.locator('[data-lhb-toggle]').focus();
            await page.keyboard.press('Space');
            assert.equal(await page.locator('[data-lhb-toggle]').getAttribute('aria-expanded'), 'false');
            await page.keyboard.press('Space');
            assert.equal(await page.locator('[data-lhb-toggle]').getAttribute('aria-expanded'), 'true');
            await page.waitForTimeout(200);
            const marketPosition = await page.locator('#logistics-market-section').evaluate(el => ({ top: el.getBoundingClientRect().top, height: innerHeight, scroll: scrollY, behavior: getComputedStyle(document.documentElement).scrollBehavior }));
            assert.equal(marketPosition.top < marketPosition.height, true, `${page.url()} ${JSON.stringify(marketPosition)} ${JSON.stringify(errors)}`);
            if (screenshots && locale === 'pl') { await page.waitForTimeout(400); await page.screenshot({ path: path.join(screenshots, 'logistics-market-filtered-1440.png') }); }
            assert.deepEqual(errors, [], `${locale}: browser errors`);
            await page.emulateMedia({ reducedMotion: 'reduce' });
            await page.reload();
            assert.match(await page.locator('[data-progress-width]').first().getAttribute('style'), /width:\s*\d/);
            await page.emulateMedia({ reducedMotion: 'no-preference' });
            await page.unroute(/^https:\/\/fixture\.test\/(?:logistics)?(?:\?.*)?$/);
        }
    } finally {
        await browser.close();
    }
    console.log('Logistics design browser checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
