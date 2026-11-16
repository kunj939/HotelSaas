// Run on a disposable preview with TABLEBLOOM_TEST_URL and PLAYWRIGHT_MODULE set.
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const base=process.env.TABLEBLOOM_TEST_URL;
if(!base){console.log('SKIP: preview URL required');process.exit(0)}
const output=process.env.TABLEBLOOM_ARTIFACTS;
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'chrome'});
 try{
  const page=await browser.newPage({viewport:{width:375,height:850},reducedMotion:'reduce'});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base);await page.waitForFunction(()=>typeof showDishModal==='function');
  await page.evaluate(()=>{S.items=[{id:99999,name:'Cheese Capsicum Pizza',description:'Prepared fresh and served with care.',image_url:'assets/photos/kaku/ai-cheese-capsicum-pizza.jpg',available:1,base_price:120,options:[{name:'Small',price:120},{name:'Medium',price:210},{name:'Large',price:280}],addons:[]}];});
  for(const theme_mode of ['dark','light'])for(const [primary_color,accent_color] of [['#333333','#E4B866'],['#186953','#E4B866'],['#FFFFFF','#FFFFFF'],['#000000','#000000'],['#777777','#777777']]){
   await page.evaluate(theme=>{applyTheme(theme);showDishModal(99999)}, {theme_mode,primary_color,accent_color});
   const contrasts=await page.evaluate(()=>{
    const canvas=document.createElement('canvas'),ctx=canvas.getContext('2d');
    const rgb=color=>{ctx.clearRect(0,0,1,1);ctx.fillStyle=color;ctx.fillRect(0,0,1,1);return [...ctx.getImageData(0,0,1,1).data].slice(0,3)};
    const luminance=color=>rgb(color).map(n=>n/255).map(n=>n<=.04045?n/12.92:((n+.055)/1.055)**2.4).reduce((n,v,i)=>n+v*[.2126,.7152,.0722][i],0);
    const ratio=(a,b)=>{const x=luminance(a),y=luminance(b);return(Math.max(x,y)+.05)/(Math.min(x,y)+.05)};
    const style=s=>getComputedStyle(document.querySelector(s));
    return {quantity:ratio(style('.qty-large b').color,style('.qty-large').backgroundColor),plus:ratio(style('#plus').color,style('#plus').backgroundColor),add:ratio(style('#add-dish').color,style('#add-dish').backgroundColor),selected:ratio(style('.option.selected').color,style('.option.selected').backgroundColor),label:ratio(style('#dish-wrap .eyebrow').color,style('#dish-wrap .modal').backgroundColor)};
   });
   for(const [name,ratio] of Object.entries(contrasts))assert(ratio>=4.5,`${theme_mode} ${primary_color}/${accent_color} ${name}: ${ratio}`);
  }
  for(const width of [320,375,1280]){
   await page.setViewportSize({width,height:850});
   await page.evaluate(()=>{applyTheme({theme_mode:'dark',primary_color:'#333333',accent_color:'#E4B866'});showDishModal(99999);document.querySelector('#dish-wrap .modal').scrollTop=9999});
   assert.equal(await page.locator('.qty-large b').innerText(),'1');assert(await page.locator('#minus').isDisabled());
   await page.locator('input[value="Medium"]').check();
   assert.match(await page.locator('#add-dish').innerText(),/210/);
   await page.locator('#plus').click();assert.equal(await page.locator('.qty-large b').innerText(),'2');
   assert.match(await page.locator('#add-dish').innerText(),/420/);
   assert(await page.locator('#minus').isEnabled());
   assert(await page.locator('input[value="Medium"]').isChecked());
   assert(await page.evaluate(()=>document.querySelector('#dish-wrap .modal').scrollWidth<=document.querySelector('#dish-wrap .modal').clientWidth),'Dish sheet overflow');
   if(output){fs.mkdirSync(output,{recursive:true});await page.locator('#dish-wrap .modal').screenshot({path:path.join(output,'dish-dark-'+width+'.png')});}
  }
  await page.evaluate(()=>applyTheme(null));
  assert.equal(await page.evaluate(()=>document.documentElement.dataset.theme),'light');
  assert.equal(await page.evaluate(()=>getComputedStyle(document.documentElement).colorScheme),'light');
  assert.deepEqual(errors,[]);
  console.log('PASS: 10 light/dark brand palettes, actual text contrast >=4.5, portion/quantity totals, mobile widths, theme reset');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
