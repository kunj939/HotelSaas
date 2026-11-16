// End-to-end account and sessionless token flows on a disposable MySQL test site.
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const base=process.env.TABLEBLOOM_TEST_URL;
if(!base){console.log('SKIP: disposable test site required');process.exit(0)}
const output=process.env.TABLEBLOOM_ARTIFACTS||'.';fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'}),errors=[];
 const newPage=async(width=1280)=>{const context=await browser.newContext({viewport:{width,height:900},reducedMotion:'reduce',permissions:['clipboard-read','clipboard-write']});context.setDefaultTimeout(15000);const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));return page};
 const login=async(page,url,user,password)=>{await page.goto(url);await page.locator('#email').fill(user);await page.locator('#password').fill(password);await page.locator('#login-form button').click()};
 let root,adminId,restaurantId,slug;
 try{
  root=await newPage();await login(root,base+'/#/admin',process.env.TABLEBLOOM_TEST_EMAIL,process.env.TABLEBLOOM_TEST_PASSWORD);
  await root.locator('[data-nav="team"]:visible').click();await root.locator('#create-admin').click();
  const username='ui.'+Date.now();await root.locator('#team-name').fill('Local Team Member');await root.locator('#team-username').fill(username);
  await root.locator('#team-generate').click();const password=await root.locator('#team-password').inputValue();assert(password.length>=12);
  assert.equal(await root.locator('#team-password').getAttribute('type'),'password');await root.locator('#team-show').click();assert.equal(await root.locator('#team-password').getAttribute('type'),'text');
  await root.locator('#modal-form button[type="submit"]').click();await root.getByText('Admin login ready',{exact:true}).waitFor();
  assert((await root.locator('#modal-form').innerText()).includes('Password'));
  await root.locator('#copy-admin-login').click();assert((await root.evaluate(()=>navigator.clipboard.readText())).includes(username));
  await root.locator('#modal-form button[type="submit"]').click();
  adminId=await root.evaluate(async username=>(await api('admin/team')).admins.find(a=>a.username===username).id,username);
  await root.screenshot({path:path.join(output,'admin-team.png'),fullPage:true});

  const agent=await newPage();await login(agent,base+'/#/owner',username,password);
  await agent.locator('#agent-create-restaurant').waitFor();assert(agent.url().endsWith('#/agent'));
  assert.deepEqual([...new Set(await agent.locator('[data-nav]').evaluateAll(nodes=>nodes.map(n=>n.dataset.nav)))],['overview']);
  await agent.locator('#agent-create-restaurant').click();assert.equal(await agent.locator('#a-fee').count(),0);
  await agent.locator('#a-name').fill('Token Test Kitchen '+Date.now());slug=await agent.locator('#a-slug').inputValue();
  const email=slug+'@example.test';await agent.locator('#a-owner').fill('Kitchen Owner');await agent.locator('#a-email').fill(email);await agent.locator('#a-password').fill(password);await agent.locator('#a-mode').selectOption('counter');
  await agent.locator('#modal-form button[type="submit"]').click();await agent.locator('.modal-wrap').waitFor({state:'detached'});
  assert.equal(await agent.locator('.metric .number').first().innerText(),'1');
  restaurantId=await root.evaluate(async slug=>(await api('admin/restaurants')).restaurants.find(r=>r.slug===slug).id,slug);
  for(const width of [320,375,1280]){await agent.setViewportSize({width,height:900});assert(await agent.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Admin page overflow at '+width);await agent.screenshot({path:path.join(output,'limited-admin-'+width+'.png'),fullPage:true})}
  await agent.setViewportSize({width:375,height:900});await agent.locator('#agent-signout').click();await agent.locator('#login-form').waitFor();

  const owner=await newPage();await login(owner,base+'/#/owner',email,password);await owner.locator('#outlet-picker').waitFor();
  await owner.evaluate(async()=>{const menu=await api('owner/menu');await api('owner/menu/save','POST',{name:'Garden Pizza',category_id:menu.categories[0].id,base_price:120,available:1,options:[],addons:[]})});
  const guest=await newPage(375);await guest.goto(base+'/r/'+slug);await guest.locator('[data-customer="track"]').waitFor();
  await guest.getByRole('button',{name:/Explore Our Menu/}).click();await guest.locator('[data-add]').first().click();await guest.locator('#add-dish').click();
  assert.equal(await guest.locator('.dish-card .dish-actions').first().evaluate(el=>getComputedStyle(el).position),'static','Sticky styles stay in the dish sheet');
  await guest.locator('.floating-cart').click();await guest.locator('[data-customer="checkout"]').click();await guest.locator('[data-customer="confirm"]').click();await guest.locator('#submit-order').click();
  await guest.locator('.order-number b').waitFor();const token=await guest.locator('.order-number b').innerText();
  const order=await guest.evaluate(()=>S.pendingOrder);assert.equal(token,String(order.token_no));
  await guest.locator('#copy-order-token').click();assert((await guest.evaluate(()=>navigator.clipboard.readText())).includes('Token #'+token));
  await guest.screenshot({path:path.join(output,'order-token.png'),fullPage:true});
  await guest.context().close(); // Drops the entire guest session and localStorage.

  const returning=await newPage(375);await returning.goto(base+'/r/'+slug);await returning.locator('[data-customer="track"]').click();
  assert.equal(await returning.locator('.token-receipt').count(),0);
  await returning.locator('#lookup-token').fill(token);await returning.locator('#token-lookup button').click();
  await returning.locator('#receipt-'+order.id).waitFor();assert((await returning.locator('#receipt-'+order.id).innerText()).includes('Garden Pizza'));
  assert.equal(await returning.evaluate(()=>S.submitted[0].tracking_key),undefined);
  await owner.evaluate(async id=>{await api('owner/order/status','POST',{id,status:'accepted'});await api('owner/order/call','POST',{id})},order.id);
  await returning.evaluate(()=>refreshTracking());await returning.locator('.token-call-card').waitFor();await returning.getByRole('button',{name:'OK, coming!'}).click();
  assert.match(await returning.locator('#status-'+order.id).innerText(),/Ready/);
  for(const width of [320,375,1280]){await returning.setViewportSize({width,height:900});assert(await returning.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Tracking overflow at '+width);await returning.screenshot({path:path.join(output,'token-tracking-'+width+'.png'),fullPage:true})}
  await returning.reload();await returning.locator('[data-customer="track"]').click();await returning.locator('#receipt-'+order.id).waitFor();
  await returning.locator('#lookup-token').fill('999999');await returning.locator('#token-lookup button').click();await returning.locator('#lookup-error').waitFor();assert.match(await returning.locator('#lookup-error').innerText(),/No order found/);
  // Broken or unavailable storage must not prevent recovery or successful checkout.
  await returning.evaluate(slug=>localStorage.setItem('rq_submitted_'+slug,'broken-json'),slug);await returning.reload();await returning.locator('[data-customer="track"]').waitFor();
  await returning.context().addInitScript(()=>{Object.defineProperty(Storage.prototype,'getItem',{value(){throw Error('Storage disabled')}});Object.defineProperty(Storage.prototype,'setItem',{value(){throw Error('Storage disabled')}});Object.defineProperty(Storage.prototype,'removeItem',{value(){throw Error('Storage disabled')}})});
  await returning.reload();await returning.locator('[data-customer="track"]').click();await returning.locator('#lookup-token').fill(token);await returning.locator('#token-lookup button').click();await returning.locator('#receipt-'+order.id).waitFor();
  await returning.locator('[data-customer="menu"]').first().click();await returning.locator('[data-add]').first().click();await returning.locator('#add-dish').click();await returning.locator('.floating-cart').click();await returning.locator('[data-customer="checkout"]').click();await returning.locator('[data-customer="confirm"]').click();await returning.locator('#submit-order').click();await returning.locator('.order-number b').waitFor();assert.notEqual(await returning.locator('.order-number b').innerText(),token);
  assert.deepEqual(errors,[]);
  console.log('PASS: create/admin login/onboarding UI; limited navigation; visible token and copy; fresh browser recovery; live call; reload; corrupt/blocked storage; 320/375/1280 layouts');
 }finally{
  if(root&&restaurantId)await root.evaluate(async({id,slug})=>{await api('admin/restaurants/delete','POST',{id,confirm_slug:slug});await api('admin/restaurants/purge','POST',{id,confirm_slug:slug})},{id:restaurantId,slug});
  if(root&&adminId)await root.evaluate(async id=>api('admin/team/access','POST',{id,active:false}),adminId);
  await browser.close();
 }
})().catch(e=>{console.error(e);process.exit(1)});
