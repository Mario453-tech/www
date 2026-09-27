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
                const css = ['admin.css', 'modal.css', 'admin_news.css', ...(surface === 'news' ? [] : ['dashboard.css']), ...(surface === 'tech' ? ['home.css', 'director.css'] : [])];
                const shell = content => '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="https://fixture.test/"><title>Fixture ' + surface + '</title>' + css.map(file => '<link rel="stylesheet" href="/assets/css/' + file + '">').join('') + '</head><body><div class="admin-wrap">' + content + '</div><script src="/assets/js/modal.js"></script></body></html>';
                const full = shell(html);
                await page.route('https://fixture.test/', route => route.fulfill({body: full, contentType: 'text/html'}));
                await page.goto('https://fixture.test/');
                for (const width of [320, 360, 390, 768, 1024, 1440, 1536]) {
                    await page.setViewportSize({width, height: width === 1536 ? 1024 : 900});
                    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
                    assert.equal(overflow, false, `${surface}/${locale}/${width} overflow`);
                    if (surface === 'news') {
                        const cards = page.locator('.news-row');
                        assert.equal(await cards.count(), 9);
                        assert.equal(await page.locator('.news-row:visible').count(), 5);
                        const first = await cards.nth(0).boundingBox();
                        const second = await cards.nth(1).boundingBox();
                        const border = await cards.nth(0).evaluate(node => getComputedStyle(node).borderTopWidth);
                        assert.equal(border, '1px', `news/${locale}/${width} card border`);
                        assert.ok(first && second && (Math.abs(first.x - second.x) > 10 || Math.abs(first.y - second.y) > 10), `news/${locale}/${width} card separation`);
                    }
                    if (process.env.UI_SCREENSHOT_DIR && [320, 1440, 1536].includes(width)) {
                        fs.mkdirSync(process.env.UI_SCREENSHOT_DIR, {recursive: true});
                        await page.screenshot({path: path.join(process.env.UI_SCREENSHOT_DIR, `${surface}-${locale}-${width}.png`), fullPage: true});
                    }
                }
                if (surface === 'news') {
                    await page.setViewportSize({width: 1536, height: 1024});
                    await page.evaluate(() => {
                        const wrap = document.querySelector('.admin-wrap');
                        const layout = document.createElement('div');
                        const sidebar = document.createElement('aside');
                        layout.className = 'admin-layout';
                        sidebar.className = 'admin-sidebar';
                        sidebar.innerHTML = '<div class="sidebar-brand">ADMIN</div>';
                        wrap.before(layout);
                        layout.append(sidebar, wrap);
                    });
                    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1), false, `news/${locale}/1536 sidebar overflow`);
                    if (process.env.UI_SCREENSHOT_DIR) {
                        await page.screenshot({path: path.join(process.env.UI_SCREENSHOT_DIR, `news-${locale}-1536-shell.png`), fullPage: true});
                    }
                    const editLink = page.locator('.news-row').first().locator('a.news-action--edit');
                    await editLink.focus();
                    assert.equal(await editLink.evaluate(node => document.activeElement === node), true, `news/${locale} edit action focus`);
                    assert.match(await editLink.getAttribute('href'), /\/admin\/news\.php\?edit=9$/);
                    await page.locator('#news-search-input').fill('incydentow');
                    assert.equal(await page.locator('.news-row:visible').count(), 1);
                    await page.locator('#news-status-filter').selectOption('pinned');
                    assert.equal(await page.locator('#news-no-matches').isVisible(), true);
                    await page.locator('#news-search-input').fill('');
                    assert.equal(await page.locator('.news-row:visible').count(), 1);
                    await page.locator('[data-news-tab="all"]').click();
                    await page.locator('[data-news-page="2"]').first().click();
                    assert.equal(await page.locator('.news-row:visible').count(), 4);
                    await page.locator('#news-sort').selectOption('oldest');
                    assert.equal(await page.locator('.news-row:visible').count(), 5);
                    const visibleIds = await page.locator('.news-row:visible .news-row-meta span:first-child').allTextContents();
                    assert.deepEqual(visibleIds.slice(0, 2), ['#9', '#2']);
                    await page.locator('.news-row:visible').first().locator('.news-action--delete').click();
                    assert.equal(await page.locator('#app-modal').isVisible(), true);
                    await page.locator('#modal-actions .modal-btn--cancel').click();
                    await page.waitForFunction(() => !document.querySelector('#app-modal').classList.contains('modal-visible'));
                    const addHtml = execFileSync(php, [path.join(__dirname, 'fixtures/admin_dashboard_render.php'), 'news', locale, 'add'], {encoding: 'utf8'});
                    await page.route('https://fixture.test/admin/news.php?add=1', route => route.fulfill({body: shell(addHtml), contentType: 'text/html'}));
                    await page.locator('a.news-add-button').click();
                    assert.equal(await page.locator('#admin-news-form').isVisible(), true);
                    await page.unroute('https://fixture.test/admin/news.php?add=1');
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
                console.log(`PASS ${surface}/${locale}: seven widths and interactions`);
                await page.unroute('https://fixture.test/');
            }
        }
        assert.deepEqual(errors, []);
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
