const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

(async () => {
    const browser = await chromium.launch({ headless: true, channel: 'msedge', timeout: 15000 });
    try {
        const root = path.join(__dirname, '..');
        const source = fs.readFileSync(path.join(root, 'assets/js/chat.js'), 'utf8');
        const handler = source.match(/dom\.input\.addEventListener\('keydown', function \(event\) \{([\s\S]*?)\n            \}\);/)[1];
        const icons = ['home', 'mapa', 'rynek', 'dyrektor', 'pomoc', 'czat'].map(name => {
            const data = fs.readFileSync(path.join(root, 'assets/img/icons/game-nav', name + '.svg')).toString('base64');
            return `<img width="20" height="20" alt="${name}" src="data:image/svg+xml;base64,${data}">`;
        }).join('');
        for (const width of [320, 360, 390, 768, 1024, 1440]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            await page.setContent(`<nav>${icons}</nav><form><textarea required></textarea><button>Send</button></form>`);
            await page.evaluate(body => {
                window.sent = 0;
                const dom = { form: document.querySelector('form'), input: document.querySelector('textarea'), sendBtn: document.querySelector('button') };
                dom.form.addEventListener('submit', event => { event.preventDefault(); window.sent++; });
                dom.input.addEventListener('keydown', event => new Function('event', 'dom', body)(event, dom));
            }, handler);
            await page.locator('textarea').fill('Test');
            await page.locator('textarea').press('Shift+Enter');
            assert.match(await page.locator('textarea').inputValue(), /\n/);
            await page.locator('textarea').press('Enter');
            assert.equal(await page.evaluate(() => window.sent), 1);
            assert.ok(await page.evaluate(() => [...document.images].every(image => image.complete && image.naturalWidth > 0)));
            assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
            await page.close();
        }
        console.log('Icons and keyboard browser checks passed: 320-1440px');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
