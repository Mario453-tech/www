const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const php = process.env.PHP_BINARY || 'php';

(async () => {
    const browser = await chromium.launch({headless: true, channel: 'msedge'});
    try {
        const page = await browser.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('https://fixture.test/assets/**', route => {
            const file = path.join(root, new URL(route.request().url()).pathname);
            route.fulfill({body: fs.readFileSync(file), contentType: file.endsWith('.css') ? 'text/css' : 'text/javascript'});
        });
        await page.route('https://cdn.tiny.cloud/**', route => route.fulfill({body: 'window.tinymce={init(){},triggerSave(){}};', contentType: 'text/javascript'}));
        for (const locale of ['pl', 'en']) {
            for (const surface of ['bank', 'help_editor', 'news', 'tech']) {
                const html = execFileSync(php, [path.join(__dirname, 'fixtures/admin_dashboard_render.php'), surface, locale], {encoding: 'utf8'});
                const css = ['admin.css', 'modal.css', 'admin_news.css', 'dashboard.css', ...(surface === 'tech' ? ['home.css', 'director.css'] : [])];
                const full = '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="https://fixture.test/"><title>Fixture ' + surface + '</title>' + css.map(file => '<link rel="stylesheet" href="/assets/css/' + file + '">').join('') + '</head><body><main>' + html + '</main><script src="/assets/js/modal.js"></script></body></html>';
                await page.route('https://fixture.test/', route => route.fulfill({body: full, contentType: 'text/html'}));
                await page.goto('https://fixture.test/');
                for (const width of [320, 360, 390, 768, 1024, 1440]) {
                    await page.setViewportSize({width, height: 900});
                    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
                    assert.equal(overflow, false, `${surface}/${locale}/${width} overflow`);
                    if (process.env.UI_SCREENSHOT_DIR && [320, 1440].includes(width)) {
                        fs.mkdirSync(process.env.UI_SCREENSHOT_DIR, {recursive: true});
                        await page.screenshot({path: path.join(process.env.UI_SCREENSHOT_DIR, `${surface}-${locale}-${width}.png`), fullPage: true});
                    }
                }
                if (surface === 'bank') {
                    await page.locator('#abp-credit-btn').click();
                    assert.equal(await page.locator('#abp-modal').isVisible(), true);
                    await page.locator('#abp-modal-cancel').click();
                    assert.equal(await page.locator('#abp-modal').isVisible(), false);
                }
                if (surface === 'tech') {
                    for (const [status, body] of [[500, '{"success":true}'], [200, '{"success":false}'], [200, 'not JSON']]) {
                        await page.route('https://fixture.test/src/TechNotifApi.php', route => route.fulfill({status, body, contentType: 'application/json'}));
                        await page.locator('[data-tech-id="1"]').click();
                        await page.waitForFunction(() => !document.querySelector('.tech-notif-error').hidden);
                        assert.equal(await page.locator('.tech-notif-item').count(), 2);
                        await page.unroute('https://fixture.test/src/TechNotifApi.php');
                    }
                    await page.route('https://fixture.test/src/TechNotifApi.php', route => route.fulfill({status: 200, body: '{"success":true}', contentType: 'application/json'}));
                    await page.locator('[data-tech-id="1"]').click();
                    await page.waitForFunction(() => document.querySelectorAll('.tech-notif-item').length === 1);
                    assert.equal(await page.locator('.tech-notif-count').textContent(), '1');
                    await page.unroute('https://fixture.test/src/TechNotifApi.php');
                }
                console.log(`PASS ${surface}/${locale}: six widths and interactions`);
                await page.unroute('https://fixture.test/');
            }
        }
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
