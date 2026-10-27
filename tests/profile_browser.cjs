const { test } = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');

test('real profile view is responsive and preserves interactive forms', async () => {
    const html = execFileSync(process.env.PHP_BIN || 'C:/xampp1/bin/php/php8.5.0/php.exe', [path.join(root, 'tests/fixtures/profile-view.php')], { encoding: 'utf8' });
    const browser = await chromium.launch({ headless: true, executablePath: 'C:/Program Files/Google/Chrome/Application/chrome.exe' });
    try {
        const page = await browser.newPage();
        await page.setContent(html);
        await page.addStyleTag({ path: path.join(root, 'assets/css/profile.css') });
        await page.addScriptTag({ path: path.join(root, 'assets/js/profile.js') });
        await page.locator('#company-name').fill('Stefan');
        assert.match(await page.locator('#company-name-preview').innerText(), /Podgląd nazwy firmy: Stefan/);
        const current = page.locator('#current-password');
        await current.fill('not-a-secret');
        await page.locator('[data-password-toggle="current-password"]').click();
        assert.equal(await current.getAttribute('type'), 'text');
        await page.locator('[data-password-toggle="current-password"]').click();
        assert.equal(await current.getAttribute('type'), 'password');
        assert.equal(await page.locator('.profile-market-stats dd.profile-value--positive').evaluate(el => getComputedStyle(el).color), 'rgb(82, 210, 139)');
        assert.equal(await page.locator('.profile-market-stats dd.profile-value--negative').evaluate(el => getComputedStyle(el).color), 'rgb(250, 98, 109)');
        for (const width of [320, 360, 390, 768, 1024, 1440]) {
            await page.setViewportSize({ width, height: 900 });
            const metrics = await page.evaluate(() => {
                const cards = ['.profile-identity','.profile-company','.profile-account-stats','.profile-market-stats','.profile-reputation','.profile-security'];
                return {
                    scrollWidth: document.documentElement.scrollWidth,
                    viewport: innerWidth,
                    top: cards.map(selector => document.querySelector(selector).getBoundingClientRect().top),
                    photoHeight: document.querySelector('.profile-photo-button').getBoundingClientRect().height,
                    eyeHeight: document.querySelector('[data-password-toggle]').getBoundingClientRect().height,
                };
            });
            assert.ok(metrics.scrollWidth <= metrics.viewport, `horizontal overflow at ${width}: ${JSON.stringify(metrics)}`);
            assert.ok(metrics.photoHeight >= 40 && metrics.eyeHeight >= 40, `small touch target at ${width}`);
            if (width < 690) assert.ok(metrics.top.every((top, i) => !i || top > metrics.top[i - 1]), `wrong mobile order at ${width}: ${JSON.stringify(metrics.top)}`);
        }
    } finally { await browser.close(); }
});
