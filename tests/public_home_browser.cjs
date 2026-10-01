const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'php';
const fixture = path.join(__dirname, 'fixtures', 'public_home_render.php');
const screenshots = process.env.HOME_SCREENSHOT_DIR;

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'msedge' });
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        page.on('console', message => {
            if (message.type() === 'error') errors.push(message.text());
        });

        await page.route('https://fixture.test/favicon.png', route => {
            route.fulfill({ body: fs.readFileSync(path.join(root, 'favicon.png')), contentType: 'image/png' });
        });
        await page.route('https://fixture.test/assets/**', route => {
            const pathname = new URL(route.request().url()).pathname;
            const file = path.join(root, pathname);
            const extension = path.extname(file).toLowerCase();
            const types = {
                '.css': 'text/css',
                '.js': 'text/javascript',
                '.svg': 'image/svg+xml',
                '.png': 'image/png',
                '.ico': 'image/x-icon',
            };
            route.fulfill({ body: fs.readFileSync(file), contentType: types[extension] || 'application/octet-stream' });
        });

        for (const locale of ['pl', 'en']) {
            await page.route('https://fixture.test/', route => {
                const html = execFileSync(php, [fixture, locale], { encoding: 'utf8' });
                route.fulfill({ body: html, contentType: 'text/html' });
            });
            await page.goto('https://fixture.test/');

            assert.equal(await page.locator('[role="tab"]').count(), 3);
            assert.equal(await page.locator('[role="tabpanel"]:visible').count(), 1);
            assert.equal(await page.locator('a[href="/login"]').count() > 0, true);
            assert.equal(await page.locator('a[href="/register"]').count() > 0, true);

            await page.locator('[data-home-tab="logistics"]').click();
            assert.equal(await page.locator('[data-home-panel="logistics"]').isVisible(), true);
            await page.locator('[data-home-tab="logistics"]').press('End');
            assert.equal(await page.locator('[data-home-tab="management"]').getAttribute('aria-selected'), 'true');

            for (const width of [320, 360, 390, 768, 1024, 1440]) {
                await page.setViewportSize({ width, height: 900 });
                assert.equal(
                    await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1),
                    true,
                    `${locale}/${width}: horizontal overflow`
                );
            }

            await page.setViewportSize({ width: 390, height: 900 });
            const menu = page.locator('[data-home-menu]');
            await menu.click();
            assert.equal(await menu.getAttribute('aria-expanded'), 'true');
            await page.keyboard.press('Escape');
            assert.equal(await menu.getAttribute('aria-expanded'), 'false');

            await page.setViewportSize({ width: 1440, height: 900 });
            const preview = page.locator('[data-home-preview-src]').first();
            await preview.click();
            assert.equal(await page.locator('#home-screenshot-dialog').evaluate(dialog => dialog.open), true);
            await page.keyboard.press('Escape');
            assert.equal(await page.locator('#home-screenshot-dialog').evaluate(dialog => dialog.open), false);
            assert.equal(await preview.evaluate(element => element === document.activeElement), true);

            if (screenshots && locale === 'pl') {
                await page.locator('[data-home-tab="extraction"]').click();
                fs.mkdirSync(screenshots, { recursive: true });
                await page.screenshot({ path: path.join(screenshots, 'home-1440.png'), fullPage: true });
                await page.setViewportSize({ width: 390, height: 900 });
                await page.screenshot({ path: path.join(screenshots, 'home-390.png'), fullPage: true });
            }

            assert.deepEqual(errors, [], `${locale}: browser errors`);
            await page.unroute('https://fixture.test/');
        }
        console.log('Public home browser: PL/EN, tabs, modal, mobile menu and 6 widths OK');
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
