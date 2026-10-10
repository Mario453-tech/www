const {chromium} = require(process.env.PLAYWRIGHT_MODULE);
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
(async () => {
 const browser = await chromium.launch({headless:true, channel:'msedge'});
 try {
  const page = await browser.newPage();
  const errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.route('https://well.test/assets/**', route => {
   const file=path.join(root,new URL(route.request().url()).pathname);
   const contentType=file.endsWith('.js')?'text/javascript':file.endsWith('.svg')?'image/svg+xml':'text/css';
   route.fulfill({body:fs.existsSync(file)?fs.readFileSync(file):'',contentType});
  });
  let historyRequests=[];
  await page.route('https://well.test/public/well_history.php?**', async route => {
   const params=new URL(route.request().url()).searchParams;
   const filter=params.get('filter')||'all';
   const requestedPage=Number(params.get('page')||1);
   historyRequests.push(`${filter}:${requestedPage}`);
   if(filter==='all') await new Promise(resolve=>setTimeout(resolve,120));
   await route.fulfill({
    contentType:'application/json',
    body:JSON.stringify({events:[{id:requestedPage,date:'2026-10-10 01:00:00',category:filter==='production'?'Production':'Service',title:'Review',description:'History entry',icon:'flow'}],has_more:requestedPage<3,page:requestedPage,total_pages:3,total_events:45,page_size:20,filter})
   });
  });
  for(const locale of ['pl','en','de']) {
   historyRequests=[];
   const html=execFileSync(process.env.PHP_BINARY,[path.join(root,'tests/fixtures/well_workspace_render.php'),locale],{encoding:'utf8',maxBuffer:4e6});
   await page.route('https://well.test/',r=>r.fulfill({body:html,contentType:'text/html'}));
   await page.goto('https://well.test/');
   assert.ok(await page.locator('[data-well-select]').count()>0,'real local wells must render');
   const visibleSelectorStatus=page.locator('[data-well-select] .ww-selector-status span').first();
   assert.equal(await visibleSelectorStatus.isVisible(),true,'well selector status text must stay visible alongside color and icon');
   assert.equal(await visibleSelectorStatus.evaluate(el=>el.classList.contains('sr-only')),false,'well selector status text must not be visually hidden');
   assert.notEqual((await visibleSelectorStatus.innerText()).trim(),'','well selector status text must not be empty');
   const first=page.locator('[data-well]:visible');
   await first.locator('[data-well-tab="equipment"]').click();
   assert.equal(await first.locator('[data-well-panel="equipment"]').isVisible(),true,'equipment tab must work');
   assert.equal(await page.locator('[role="tabpanel"]:visible').count(),1);
   for (const tab of ['overview','equipment','storage','transport','geology','history']) {
    await first.locator(`[data-well-tab="${tab}"]`).click();
    assert.equal(await first.locator(`[data-well-panel="${tab}"]`).isVisible(),true);
   }
   assert.equal(await first.locator('[data-history-retry]').isDisabled(),true,'history controls must stay disabled while a request is in flight');
   await first.locator('[data-well-history-filter]').selectOption('production');
   await first.locator('.ww-history-list li', {hasText:'Production'}).waitFor();
   assert.equal(await first.locator('.ww-history-list li').count(),1,'history endpoint must render events');
   assert.match(await first.locator('.ww-history-list li').innerText(),/Production/,'latest filter must win during an in-flight request');
   assert.deepEqual(historyRequests.slice(0,2),['all:1','production:1'],'filter change must queue a fresh history request');
   assert.equal(await first.locator('.ww-history-event-icon').count(),1,'each history event needs a type icon');
   const historyMask=await first.locator('.ww-history-event-icon').first().evaluate(el=>getComputedStyle(el,'::before').maskImage||getComputedStyle(el,'::before').webkitMaskImage);
   assert.notEqual(historyMask,'none','history event icon mask must resolve');
   assert.equal(await first.locator('[data-history-pages] .ww-page-number').count(),3,'numbered pagination must be visible');
   await first.locator('[data-history-page-target="2"]').click();
   await first.locator('[data-history-page-target="2"][aria-current="page"]').waitFor();
   assert.equal(historyRequests.at(-1),'production:2','numbered pagination must request the chosen page');
   assert.match(await first.locator('[data-history-retention]').innerText(),/2|48/,'history retention must be explained');
   await first.locator('[data-well-tab="overview"]').focus();
   await page.keyboard.press('ArrowRight');
   assert.equal(await first.locator('[data-well-panel="equipment"]').isVisible(),true,'keyboard tab navigation must work');
   const enabledAction=first.locator('[data-well-action]:not([disabled])').first();
   if(await enabledAction.count()) {
    await enabledAction.click();
    await page.locator('#app-modal[role="dialog"]').waitFor();
    await page.locator('#app-modal .modal-btn--cancel').click();
   }
   for (const selector of await page.locator('[data-well-select]').all()) {
    const id=await selector.getAttribute('data-well-select'); await selector.click();
    assert.equal(await page.locator(`[data-well="${id}"]`).isVisible(),true);
    assert.equal(await page.locator('[data-well]:visible').count(),1);
   }
   for(const width of [320,360,390,768,1024,1440,1920]) {
    await page.setViewportSize({width,height:1000});
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,`${locale} ${width} overflow`);
    if(process.env.WELL_SCREENSHOT_DIR) {
     fs.mkdirSync(process.env.WELL_SCREENSHOT_DIR,{recursive:true});
     await page.screenshot({path:path.join(process.env.WELL_SCREENSHOT_DIR,`${locale}-${width}.png`),fullPage:true});
    }
   }
   assert.equal(await page.locator('.ww [style],.ww [onclick],.ww a[href="#"]').count(),0);
   const hrefs=await page.locator('.ww a').evaluateAll(nodes=>nodes.map(a=>a.getAttribute('href')));
   assert.ok(hrefs.length>0,'workspace must expose real links');
   assert.equal(hrefs.some(href=>!href || href==='#' || href.startsWith('javascript:')),false,'workspace must not contain dead links');
   const endpoints=await page.locator('.ww [data-well-action]').evaluateAll(nodes=>nodes.map(n=>n.dataset.endpoint));
   assert.equal(endpoints.some(endpoint=>!endpoint || !endpoint.startsWith('/')),false,'all action controls need real endpoints');
   const copy=await page.locator('.ww').innerText();
   assert.equal(/EKSPORT|EXPORT HISTORY|SZYBKI PODGLĄD|QUICK PREVIEW/i.test(copy),false,'excluded UI must not render');
   console.log(`${locale}: selectors, six tabs, keyboard, history, links, modal, seven widths PASS`);
  }
  assert.deepEqual(errors,[]);
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
