const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'msedge', timeout: 15000 });
    try {
        for (const width of [320, 360, 390, 768, 1024, 1440]) {
            const page = await browser.newPage({ viewport: { width, height: 1000 } });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/api/internal/AdminNewsApi.php', route => route.fulfill({ contentType: 'application/json', body: '{"news":[]}' }));
            await page.goto('http://127.0.0.1:8768/tests/fixtures/home_dashboard_render.php');
            if (width <= 600) await page.evaluate(() => document.body.classList.add('nav-open'));
            for (const [group, count] of [['operations', 3], ['business', 4], ['company', 3]]) {
                const button = page.locator(`[data-nav-group="${group}"]`);
                await button.click();
                const panel = page.locator(`[data-nav-panel="${group}"]`);
                assert.equal(await panel.isVisible(), true);
                assert.equal(await page.locator('[data-nav-panel]:visible').count(), 1);
                assert.equal(await panel.locator('a img').count(), count);
                assert.ok(await panel.locator('img').evaluateAll(images => images.every(image => image.complete && image.naturalWidth > 0)));
                assert.equal(await button.getAttribute('aria-expanded'), 'true');
                assert.notEqual(await button.evaluate(element => getComputedStyle(element).borderColor), 'rgba(0, 0, 0, 0)');
                await button.click();
                assert.equal(await panel.isVisible(), false);
                await button.focus();
                await page.keyboard.press('Enter');
                assert.equal(await panel.isVisible(), true);
                await page.keyboard.press('Escape');
                assert.equal(await panel.isVisible(), false);
            }
            if (width <= 600) await page.evaluate(() => document.body.classList.remove('nav-open'));
            await page.locator('#oe-activity-tab-messages').click();
            assert.equal(await page.locator('#oe-activity-panel-messages .notification-item').count(), 2);
            assert.equal(await page.locator('.oe-activity-row--warning').first().evaluate(element => getComputedStyle(element, '::before').backgroundColor), 'rgb(214, 179, 75)');
            await page.locator('#oe-activity-tab-alerts').click();
            assert.equal(await page.locator('#oe-activity-panel-alerts .oe-activity-row--critical').count(), 1);
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false);
            assert.deepEqual(errors, []);
            if (process.env.UI_SCREENSHOT_DIR && [390, 1440].includes(width)) {
                fs.mkdirSync(process.env.UI_SCREENSHOT_DIR, { recursive: true });
                if (width === 1440) await page.locator('[data-nav-group="operations"]').click();
                await page.screenshot({ path: path.join(process.env.UI_SCREENSHOT_DIR, `navigation-activity-${width}.png`), fullPage: true });
            }
            await page.close();
        }
        console.log('Navigation and activity checks passed: 320-1440px, icons, groups, keyboard, status rows.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
