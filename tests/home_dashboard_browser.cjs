const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const fs = require('fs');
const path = require('path');

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'msedge' });
    try {
        for (const locale of ['pl', 'en']) {
            for (const width of [320, 360, 390, 768, 1024, 1440]) {
                const page = await browser.newPage({ viewport: { width, height: 900 } });
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                await page.route('**/src/AdminNewsApi.php', route => route.fulfill({
                    contentType: 'application/json',
                    body: JSON.stringify({ news: [{ title: 'Nowe lokalizacje', content_html: 'Aktualność testowa',
                        date_fmt: '08.10.2026', is_pinned: false }] })
                }));
                await page.route('**/api/dashboard-stats.php?period=7d', route => route.fulfill({
                    contentType: 'application/json',
                    body: JSON.stringify({ state: 'ready', period: '7d', overview: {
                        production_rate: 70, revenue_rate: 700, production_change: null, revenue_change: null
                    }, series: [{ label: '2026-10-01', value: 70 }], chart_points: '24,80 616,80',
                    wells: { total: 1, active: 1, attention: 0, critical: 0,
                        top: [{ id: 1, name: 'E-1', production: 70, condition: 100 }],
                        transport_mix: { road: 0, pipeline: 1, sea: 0 }, active_routes: 1 },
                    logistics: { mix: { road: 0, pipeline: 1, sea: 0 }, active_routes: 1,
                        on_time_pct: null, loss_bbl: 0, cost_rate: 10 },
                    finance: { revenue_rate: 700, cost_rate: 100, net_rate: 600,
                        costs: { extraction: 50, logistics: 10, staff: 30, other: 10 } } })
                }));
                await page.goto(`http://127.0.0.1:8768/tests/fixtures/home_dashboard_render.php?locale=${locale}`);
                if (!await page.locator('#company-stats').isVisible()) throw new Error(`${locale}/${width}: stats invisible`);
                if (!await page.locator('#chatShell').isVisible()) throw new Error(`${locale}/${width}: embedded chat invisible`);
                if (locale === 'pl' && [390, 1440].includes(width) && process.env.UI_SCREENSHOT_DIR) {
                    fs.mkdirSync(process.env.UI_SCREENSHOT_DIR, { recursive: true });
                    await page.screenshot({ path: path.join(process.env.UI_SCREENSHOT_DIR, `dashboard-chat-${width}.png`), fullPage: true });
                }
                await page.locator('#oe-stat-tab-wells').click();
                if (!await page.locator('#oe-stat-panel-wells').isVisible()) throw new Error(`${locale}/${width}: wells tab failed`);
                await page.locator('#oe-stat-tab-wells').focus();
                await page.keyboard.press('ArrowRight');
                if (await page.locator('#oe-stat-tab-logistics').getAttribute('aria-selected') !== 'true') throw new Error(`${locale}/${width}: keyboard tab failed`);
                await page.locator('[data-stat-period="7d"]').click();
                await page.waitForFunction(() => document.querySelector('[data-stat="production_rate"]').textContent.trim() === '70');
                await page.locator('#oe-activity-tab-news').click();
                if (!await page.locator('#oe-activity-panel-news').isVisible()) throw new Error(`${locale}/${width}: news tab failed`);
                await page.locator('#newsList .news-item').first().waitFor();
                const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
                if (overflow) throw new Error(`${locale}/${width}: horizontal overflow`);
                if (errors.length) throw new Error(`${locale}/${width}: ${errors.join('; ')}`);
                if (locale === 'pl' && [390, 1440].includes(width) && process.env.UI_SCREENSHOT_DIR) {
                    fs.mkdirSync(process.env.UI_SCREENSHOT_DIR, { recursive: true });
                    await page.screenshot({ path: path.join(process.env.UI_SCREENSHOT_DIR, `dashboard-${width}.png`), fullPage: true });
                }
                await page.close();
            }
        }
        process.stdout.write('Dashboard browser checks passed: PL/EN, 320-1440px, tabs, keyboard, periods.\n');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
