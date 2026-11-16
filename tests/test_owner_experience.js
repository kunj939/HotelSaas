// Browser regression on a disposable site. Uses an existing owner fixture with a menu.
// Set TABLEBLOOM_TEST_URL, TABLEBLOOM_OWNER_EMAIL, TABLEBLOOM_TEST_PASSWORD,
// TABLEBLOOM_OWNER_SLUG and optionally PLAYWRIGHT_MODULE / TABLEBLOOM_ARTIFACTS.
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const base=process.env.TABLEBLOOM_TEST_URL,slug=process.env.TABLEBLOOM_OWNER_SLUG;
if(!base||!slug){console.log('SKIP: disposable owner fixture required');process.exit(0)}
const output=process.env.TABLEBLOOM_ARTIFACTS||'.';
fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 try{
  const context=await browser.newContext({viewport:{width:1280,height:960},reducedMotion:'reduce',permissions:['clipboard-read','clipboard-write']});
  const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base+'/#/owner');
  await page.locator('#email').fill(process.env.TABLEBLOOM_OWNER_EMAIL);
  await page.locator('#password').fill(process.env.TABLEBLOOM_TEST_PASSWORD);
  await page.locator('#login-form button').click();await page.locator('#outlet-picker').waitFor();
  await page.locator('[data-nav="billing"]:visible').click();
  await page.locator('.setup-offer').waitFor();
  assert.equal(await page.locator('.setup-fee-was').innerText(),'₹500');
  assert.match(await page.locator('.setup-offer').innerText(),/₹350/);
  assert.match(await page.locator('.setup-offer-badge').innerText(),/30% OFF/);
  const quote=await page.evaluate(async()=>{const b=await api('owner/billing');return money(b.quotes.monthly.total)});
  assert((await page.locator('.admin-main').innerText()).includes(quote));
  await page.screenshot({path:path.join(output,'billing-1280.png'),fullPage:true});
  await page.locator('#outlet-picker').click();await page.locator('#add-outlet').click();
  const pricingCopy=await page.locator('.outlet-pricing').innerText();
  assert.match(pricingCopy,/second outlet|second.*FREE|third outlet/);
  assert.match(pricingCopy,/250/);assert.match(pricingCopy,/No separate base plan or extra setup fee/);
  assert(!pricingCopy.includes('7-day free trial'));
  const newName='City Centre '+Date.now();await page.locator('#o-name').fill(newName);
  assert.match(await page.locator('#o-url').innerText(),/\/r\/city-centre-/);
  await page.locator('#o-slug').fill('custom-'+Date.now());
  const custom=await page.locator('#o-slug').inputValue();await page.locator('#o-name').fill(newName+' Pizza');
  assert.equal(await page.locator('#o-slug').inputValue(),custom,'Keep manually chosen menu link');
  await page.locator('#o-address').fill('City Centre, Gurugram');
  await page.locator('#o-mode').selectOption('table');await page.locator('#o-tables').fill('2');
  await page.locator('#o-menu').selectOption({index:1});
  await page.screenshot({path:path.join(output,'add-outlet.png'),fullPage:true});
  await page.locator('#modal-form button[type="submit"]').click();
  await page.locator('.modal-wrap').waitFor({state:'detached'});
  await page.waitForFunction(n=>document.querySelector('#outlet-picker')?.textContent.includes(n),newName);
  await page.locator('[data-nav="menu"]:visible').click();await page.locator('.admin-menu-row').first().waitFor();
  await page.locator('[data-nav="settings"]:visible').click();await page.locator('.qr-card').first().waitFor();
  assert.equal(await page.locator('.qr-card').count(),2);
  const menu=base+'/r/'+custom;
  assert.equal(await page.locator('.menu-link-box a').getAttribute('href'),menu);
  await page.locator('#copy-link').click();assert.equal(await page.evaluate(()=>navigator.clipboard.readText()),menu);
  const downloaded=page.waitForEvent('download');await page.locator('[data-download-qr="0"]').click();
  const file=await downloaded;assert.match(file.suggestedFilename(),/-table-1-qr.svg$/);
  await file.saveAs(path.join(output,'table-1-qr.svg'));
  const printable=context.waitForEvent('page');await page.locator('#print-qr').click();
  const printPage=await printable;await printPage.waitForLoadState();
  assert.equal(await printPage.locator('.qr-card').count(),2);await printPage.close();
  // Render the exported SVG independently, just as a printer/image viewer will.
  const svg=fs.readFileSync(path.join(output,'table-1-qr.svg'),'utf8');
  const preview=await context.newPage();await preview.setContent(svg);
  await preview.locator('svg').first().evaluate(el=>{el.style.width='640px';el.style.height='940px'});
  await preview.locator('svg').first().screenshot({path:path.join(output,'table-1-qr.png')});await preview.close();
  fs.writeFileSync(path.join(output,'expected-qr-url.txt'),menu+'?table=1');
  for(const width of [320,375,1280]){
   await page.setViewportSize({width,height:960});await page.locator('#outlet-picker').click();
   await page.locator('#add-outlet').waitFor();
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Outlets overflow at '+width);
   await page.screenshot({path:path.join(output,'outlets-'+width+'.png'),fullPage:true});
   await page.locator('#add-outlet').click();
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Add-outlet overflow at '+width);
   await page.locator('#close-modal').click();
   await page.locator('[data-nav="settings"]:visible').click();await page.locator('.qr-card').first().waitFor();
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Settings overflow at '+width);
   await page.screenshot({path:path.join(output,'settings-'+width+'.png'),fullPage:true});
  }
  for(const url of [menu+'?table=2',base+'/index.html#/r/'+custom+'?table=2']){
   await page.goto(url);await page.waitForFunction(()=>S.restaurant&&S.qrTable===2);
   assert.equal(await page.evaluate(()=>S.table),'2');
   assert.equal(await page.evaluate(()=>document.baseURI),base+'/');
   await page.reload();await page.waitForFunction(()=>S.restaurant&&S.qrTable===2);
   assert(await page.evaluate(()=>document.querySelector('link[rel="stylesheet"]').sheet!==null));
  }
  // Ordinary common/counter links must not inherit an old table query.
  await page.goto(base+'/r/'+slug);await page.waitForFunction(()=>S.restaurant&&S.qrTable===0);
  assert.deepEqual(errors,[]);
  console.log('PASS: offer rendering, create/copy/switch outlet, independent menu, clean and legacy links, table reload, copy/download/print, 320/375/1280 layouts');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
