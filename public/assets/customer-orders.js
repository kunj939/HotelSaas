/* Returning guests can recover their public receipt without cookies or saved browser data. */
function readSubmitted(slug){
 try{const list=JSON.parse(localStorage.getItem('rq_submitted_'+slug)||'[]');return Array.isArray(list)?list.filter(o=>o&&typeof o==='object'&&Number.isInteger(Number(o.id))&&Number(o.id)>0).slice(0,20):[]}catch(e){return []}
}
function saveSubmitted(){try{localStorage.setItem('rq_submitted_'+S.lastSlug,JSON.stringify(S.submitted.slice(0,20)))}catch(e){/* The token still works when browser storage is blocked. */}}
function tokenDate(day){return /^\d{4}-\d{2}-\d{2}$/.test(day||'')?new Date(day+'T12:00:00').toLocaleDateString('en-IN',{day:'numeric',month:'short',year:'numeric'}):''}
function trackingCard(o){
 const items=Array.isArray(o.items)?o.items:[];
 return `<article class="customer-card token-receipt" id="receipt-${Number(o.id)}"><div class="row between wrap"><div><span class="eyebrow">Your token</span><h2 class="serif token-receipt-number">#${esc(o.token_no??o.id)}</h2></div><span class="badge">${+o.table_no?`Table ${esc(o.table_no)}`:'Counter'}</span></div><p class="caption">${esc(tokenDate(o.token_day))}</p>${items.length?`<div class="token-items">${items.map(item=>`<div class="row between"><span>${Number(item.quantity)} × ${esc(item.dish_name)}${item.variant_name?` <span class="muted">(${esc(item.variant_name)})</span>`:''}${item.addons?.length?`<small class="muted"> · ${item.addons.map(a=>esc(a.name)).join(', ')}</small>`:''}</span><b>${money(item.line_total)}</b></div>`).join('')}</div>`:''}<div class="row between mt20"><span class="small">Order total</span><b>${money(o.total)}</b></div><div class="mt20"><span id="status-${Number(o.id)}" class="badge warn" role="status">${esc(ORDER_LABEL[o.status]||'Checking order status…')}</span></div></article>`;
}
function trackingPage(){
 return `<div class="tracking-page"><button class="btn ghost mini mb10" data-customer="menu">← Menu</button><div class="eyebrow">Back for your order?</div><h1 class="serif section-title mt10">Track my order</h1><p class="small muted mt10">Enter your token from ${esc(S.restaurant.name)}. No login needed.</p><form id="token-lookup" class="customer-card mt20"><div class="token-lookup-fields"><label class="field"><span>Token number</span><input class="input" id="lookup-token" type="text" inputmode="numeric" pattern="[0-9]{1,10}" maxlength="10" placeholder="e.g. 24" required value="${esc(S.lookupToken||'')}"></label><label class="field"><span>Order date</span><input class="input" id="lookup-day" type="date" required min="2000-01-01" max="${esc(S.restaurant.order_today)}" value="${esc(S.lookupDay||S.restaurant.order_today)}"></label></div><p class="caption">Today is selected. Tokens restart each day; choose an earlier date for an older order.</p><button class="btn full mt20" type="submit">Find my order</button><p class="small lookup-error" id="lookup-error" role="alert" hidden></p></form><div class="stack mt20" id="tracking-list">${S.submitted.length?S.submitted.map(trackingCard).join(''):'<p class="small muted center">Your order will appear here.</p>'}</div><button class="btn full mt20" data-customer="menu">Browse the menu</button></div>`;
}
function bindTokenLookup(){
 const form=$('#token-lookup');if(!form)return;
 form.onsubmit=async event=>{
  event.preventDefault();unlockAudio();const button=form.querySelector('button[type="submit"]'),error=$('#lookup-error'),slug=S.lastSlug;
  S.lookupToken=$('#lookup-token').value.trim();S.lookupDay=$('#lookup-day').value;button.disabled=true;button.textContent='Finding your order…';error.hidden=true;
  try{
   const order=(await api('public/token','GET',{slug,token:S.lookupToken,day:S.lookupDay})).order;
   if(S.lastSlug!==slug||S.step!=='track')return;
   const existing=S.submitted.find(o=>Number(o.id)===Number(order.id));
   const receipt={...existing,...order,public_lookup:!existing?.tracking_key,placed_at:new Date(order.created_at.replace(' ','T')).getTime(),calls_seen:Number(order.call_count)||0};
   S.submitted=[receipt,...S.submitted.filter(o=>Number(o.id)!==Number(order.id))].slice(0,20);saveSubmitted();renderCustomer();
   $('#receipt-'+Number(order.id))?.scrollIntoView({block:'nearest',behavior:'smooth'});
  }catch(e){if(form.isConnected){error.textContent=e.message;error.hidden=false}}
  finally{button.disabled=false;button.textContent='Find my order'}
 };
}
async function copyOrderToken(){
 const o=S.pendingOrder;if(!o)return;
 const text=`${S.restaurant.name}\nToken #${o.token_no??o.id} · ${tokenDate(o.token_day)}\nTrack your order: ${posterMenuUrl(S.restaurant)}`;
 try{await navigator.clipboard.writeText(text);toast('Token and restaurant link copied')}catch(e){toast('Your token is #'+(o.token_no??o.id)+'. Take a screenshot to keep it.')}
}
