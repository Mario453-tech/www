// Exercise real rendered permit forms without posting player data.
// Sprawdz rzeczywiste formularze bez wysylania danych gracza.
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const assert = require('node:assert/strict');
const repo = path.resolve(__dirname, '..');
async function run() {
    const browser = await chromium.launch({headless: true, channel: 'msedge'});
    try {
        for (const locale of ['pl', 'en']) {
            const html = execFileSync(process.env.PHP_BINARY || 'php', [path.join(__dirname, 'fixtures/legal_design_render.php'), locale], {encoding: 'utf8'});
            assert(!html.includes('Warning:'));
            for (const width of [320, 360, 390, 768, 1024, 1440]) {
                const page = await browser.newPage({viewport: {width, height: 950}});
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                await page.setContent(html);
                for (const css of ['style', 'legal', 'modal', 'legal_design']) await page.addStyleTag({path: path.join(repo, 'assets/css/' + css + '.css')});
                await page.addStyleTag({content: 'main {margin:24px auto; width:calc(100% - 32px); max-width:1230px;}'});
                for (const js of ['modal', 'legal', 'legal_design']) await page.addScriptTag({path: path.join(repo, 'assets/js/' + js + '.js')});
                const L = await page.locator('#legal-design-root').evaluate(el => ({...el.dataset}));
                if (process.env.LEGAL_SCREENSHOTS && locale === 'pl' && width === 1440) await page.screenshot({path: path.join(process.env.LEGAL_SCREENSHOTS, 'legal-overview.png')});
                assert.equal(await page.locator('.legal-design-stat strong').nth(1).innerText(), '1 / 4');
                assert.equal(await page.locator('.legal-design-stat strong').nth(2).innerText(), '1 / 3');
                assert.equal(await page.locator('.legal-design-stat small').count(), 0);
                assert(!(await page.locator('#legal-design-root').innerText()).includes('undefined'));
                await page.locator('.legal-design-row').filter({hasText: 'Bliski Wschód'}).click();
                const dialog = page.locator('dialog');
                assert.equal(await dialog.locator('.legal-design-permit').count(), 2);
                assert.equal(await dialog.locator('button:disabled').count(), 1);
                assert.equal(await dialog.locator('.legal-design-permit-alert').count(), 2);
                const box = await dialog.boundingBox();
                assert(Math.abs(box.x + box.width / 2 - width / 2) < 2);
                assert(Math.abs(box.y + box.height / 2 - 475) < 4);
                assert.equal(await dialog.locator('h2').evaluate(el => getComputedStyle(el).fontSize), '16px');
                assert(await dialog.evaluate(el => el.scrollWidth <= el.clientWidth));
                assert((await dialog.locator('.legal-design-dialog-titles').boundingBox()).width >= Math.min(200, width - 100));
                await dialog.locator('.legal-design-permit').nth(1).locator('.legal-design-history-button').click();
                assert.equal(await dialog.locator('.legal-design-overdue').innerText(), L.overdue);
                assert.equal(await dialog.locator('.legal-design-timeline-row--decision span').innerText(), L.noDecision);
                assert.equal(await dialog.locator('.legal-design-timeline-row--submitted span').innerText(), '28.09.2026, 21:51');
                assert.equal(await dialog.locator('.legal-design-history-attempt').count(), 2);
                assert.equal(await dialog.locator('.legal-design-history-attempt').nth(1).locator('p').count(), 2);
                assert(!(await dialog.innerText()).includes(L.noPrior));
                if (process.env.LEGAL_SCREENSHOTS && locale === 'pl' && [390, 1440].includes(width)) await page.screenshot({path: path.join(process.env.LEGAL_SCREENSHOTS, 'legal-history-' + width + '.png')});
                await dialog.locator('.legal-design-footer-action').click();
                if (process.env.LEGAL_SCREENSHOTS && locale === 'pl' && [390, 1440].includes(width)) await page.screenshot({path: path.join(process.env.LEGAL_SCREENSHOTS, 'legal-region-' + width + '.png')});
                await page.keyboard.press('Escape');
                assert.equal(await dialog.evaluate(el => el.open), false);
                await page.locator('.legal-design-row').filter({hasText: 'Rosja / Syberia'}).click();
                assert.equal(await dialog.locator('.legal-submit-form button').textContent(), L.retry);
                assert.equal(await dialog.locator('.legal-submit-form input[name=action]').inputValue(), 'submit_hub_application');
                await dialog.locator('.legal-design-permit').nth(1).locator('.legal-design-history-button').click();
                assert.equal(await dialog.locator('.legal-design-timeline-row--decision span').innerText(), L.noDecision);
                await page.keyboard.press('Escape');
                await page.locator('.legal-design-row').filter({hasText: 'Upgrade'}).click();
                await dialog.locator('.legal-design-history-button').click();
                assert.equal(await dialog.locator('.legal-design-history-summary .legal-design-badge').evaluate(el => el.classList.contains('legal-design-status--pending')), true);
                assert.equal(await dialog.locator('.legal-design-timeline-row--overdue span').innerText(), '29.09.2026, 01:51 · ' + L.overdue.toLowerCase());
                await page.keyboard.press('Escape');
                await page.locator('.legal-design-row').filter({hasText: 'Afryka Subsaharyjska'}).click();
                await page.evaluate(() => {
                    document.addEventListener('submit', event => {
                        if (!event.defaultPrevented) {
                            window.fixturePost = Object.fromEntries(new FormData(event.target));
                            event.preventDefault();
                        }
                    });
                });
                await dialog.locator('form.legal-submit-form button').click();
                assert.equal(await dialog.evaluate(el => el.open), false);
                await page.locator('#app-modal .modal-btn--confirm').click();
                const post = await page.evaluate(() => window.fixturePost);
                assert.equal(post.csrf_token, 'fixture');
                assert.equal(post.region_id, '2');
                assert.equal(post.action, 'submit_application');
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
                assert.deepEqual(errors, []);
                await page.close();
            }
        }
        console.log('Legal browser: PL/EN, 6 widths, truthful history, upgrade, CSRF form confirmation OK');
    } finally { await browser.close(); }
}
run().catch(error => { console.error(error); process.exitCode = 1; });
