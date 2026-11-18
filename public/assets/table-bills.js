/* Owner dashboard: table bills, staff-entered WhatsApp/phone orders, delivery details, tracking messages.
   Loaded before app.js and uses its helpers (api, S, esc, money, dateText, opts, card, metric, adminLayout, run, toast, posterMenuUrl, trackingLink). */
'use strict';
const SOURCE_LABEL={qr:'Table QR',whatsapp:'WhatsApp',phone:'Phone',walkin:'Walk-in'};
const FOOD_MARK={veg:'🟢',nonveg:'🔴',egg:'🟡'};
const lineAmount=l=>{const it=l.item,opts=it.options||[],chosen=opts.length?opts.find(o=>o.name===l.variant):null;let unit=chosen?Number(chosen.price):Number(it.base_price);for(const a of it.addons||[])if(l.addons.has(a.name))unit+=Number(a.price);return unit*l.qty};
const priceText=it=>{const opts=it.options||[];return opts.length?opts.map(o=>`${o.name} ${money(o.price)}`).join(' / '):money(it.base_price)};

// ---- Order labels and messages ----
// Where the food goes: delivery, a table, takeaway or the counter.
function fulfilLabel(o){if(o.fulfilment==='delivery')return 'Delivery';if(+o.table_no)return `Table ${o.table_no}`;return o.fulfilment==='takeaway'?'Takeaway':'Counter'}
// wa.me link that opens WhatsApp with the message ready, for a 10-digit Indian mobile. Empty when the number is unusable.
function whatsappLink(phone,text){const d=String(phone||'').replace(/\D/g,'').slice(-10);return /^[6-9]\d{9}$/.test(d)?`https://wa.me/91${d}?text=${encodeURIComponent(text)}`:''}
// The message staff send after an order: token, what was ordered, total, delivery address and the tracking link.
function confirmationMessage(d){
 const greet=d.name?`Hi ${d.name}!`:'Hi!';
 const where=d.fulfilment==='delivery'?'Home delivery':(d.tableNo?`Table ${d.tableNo}`:'Takeaway');
 const out=[`${greet} Your order at ${d.restaurant} is confirmed.`,`Token: #${d.token}`,`Type: ${where}`];
 if(d.items&&d.items.length){out.push('');for(const i of d.items)out.push(`${i.quantity} x ${i.name}`)}
 if(d.total!=null)out.push('',`Total: ${money(d.total)}`);
 if(d.fulfilment==='delivery'&&d.address)out.push(`Deliver to: ${d.address}`);
 out.push('',`Track your order: ${d.link}`);
 return out.join('\n');
}
// Button link for an existing order card: sends the token and tracking link to the customer's phone.
function orderSendLink(o){
 if(!o.token_no||!o.customer_phone)return '';
 const text=confirmationMessage({restaurant:o.restaurant_name,name:o.customer_name,token:o.token_no,fulfilment:o.fulfilment,tableNo:o.table_no,address:o.delivery_address,total:o.total,items:(o.items||[]).map(it=>({quantity:it.quantity,name:it.dish_name+(it.variant_name?` (${it.variant_name})`:'')})),link:trackingLink({slug:o.restaurant_slug},o.token_no,o.token_day)});
 return whatsappLink(o.customer_phone,text);
}

// ---- Tables board ----
async function ownerTables(){
 resetTimer();S.tableView='board';
 const outlet=S.actor?.active_restaurant_id;
 const data=await api('owner/tables');
 if(S.ownerTab!=='tables'||S.tableView!=='board'||outlet!==S.actor?.active_restaurant_id)return;
 const tables=data.tables||[];
 const open=tables.filter(t=>t.session);
 const newTotal=open.reduce((n,t)=>n+(t.session.new_orders||0),0);
 const billValue=open.reduce((n,t)=>n+Number(t.session.total||0),0);
 // Ring the bell (and show a toast) when a new order lands while this board is open.
 if(S.tablesPrimed&&newTotal>(S.tablesSeenNew||0)){toast('🔔 New order received!');if(S.sound)playSound()}
 S.tablesSeenNew=newTotal;S.tablesPrimed=true;
 const tiles=tables.map(t=>{const s=t.session;
  if(!s)return `<button class="table-tile" data-table-new="${t.table_no}" aria-label="Table ${t.table_no} is free. Take an order"><span class="table-no">${t.table_no}</span><span class="caption">Free · tap to take order</span></button>`;
  return `<button class="table-tile occupied${s.new_orders?' needs-action':''}" data-table-bill="${s.id}" aria-label="Table ${t.table_no} open bill"><span class="table-no">${t.table_no}</span><b>${money(s.total)}</b><span class="caption">${s.orders} order${s.orders===1?'':'s'}${s.live?` · ${s.live} live`:''}</span>${s.new_orders?`<span class="badge warn">${s.new_orders} new</span>`:''}<span class="caption">Opened ${esc(dateText(s.opened_at).split(', ').pop())}</span></button>`;
 }).join('');
 const body=data.counter
  ? '<div class="empty">Counter mode has no table bills. Customers pay at the counter with a token. Use Orders for WhatsApp and phone orders.</div>'
  : `<div class="grid4">${metric('Open bills',open.length)}${metric('Free tables',tables.length-open.length)}${metric('Open bill value',money(billValue),'Before tax, not yet paid')}${metric('New orders',newTotal)}</div><div class="table-grid">${tiles||'<p class="muted small">Add tables in Settings to see them here.</p>'}</div>`;
 adminLayout('owner','Tables & bills','Open tables, take phone or WhatsApp orders, print each table\'s bill and close it when paid.',
  card('Tables',`<div class="row wrap between"><p class="caption">Orders for a table are grouped into one open bill until you close it.</p>${data.counter?'':'<button class="btn" id="new-phone-order" type="button">＋ WhatsApp / phone order</button>'}</div><div class="mt20">${body}</div>`));
 $$('[data-table-bill]').forEach(b=>b.onclick=()=>run(()=>ownerBill(+b.dataset.tableBill)));
 $$('[data-table-new]').forEach(b=>b.onclick=()=>run(()=>openManualOrder(+b.dataset.tableNew)));
 const phone=$('#new-phone-order');if(phone)phone.onclick=()=>run(()=>openManualOrder(0));
 if(!data.counter)S.timer=setInterval(()=>{if(document.visibilityState==='visible'&&S.ownerTab==='tables'&&S.tableView==='board')ownerTables().catch(e=>console.warn(e))},8000);
}

// ---- Printable bill ----
// Bill layout for table bills and single-order bills. Fixed columns, clear sections, print-friendly.
function billHtml(bill){
 const r=bill.restaurant,open=bill.status==='open',isOrder=bill.kind==='order';
 const qty=bill.lines.reduce((n,l)=>n+Number(l.quantity),0);
 const mobile=d=>{const x=String(d||'').replace(/\D/g,'').slice(-10);return x.length===10?`+91 ${x.slice(0,5)} ${x.slice(5)}`:esc(d)};
 const items=bill.lines.map(l=>`<tr><td><div class="bill-name">${esc(l.name)}</div>${l.extras.length?`<div class="bill-extra">+ ${l.extras.map(x=>esc(x)).join(', ')}</div>`:''}</td><td class="num">${Number(l.quantity)}</td><td class="num">${money(l.unit)}</td><td class="num">${money(l.amount)}</td></tr>`).join('');
 const tokens=bill.orders.filter(o=>o.status!=='rejected').map(o=>`#${o.token_no}`).join(', ');
 const info=isOrder
  ? [['Token',`<b class="bill-token">#${esc(bill.token_no)}</b>`],['Type',esc(fulfilLabel(bill))],['Order',`#${esc(bill.id)}`],['Date',esc(dateText(bill.opened_at))],...(bill.customer_name?[['Name',esc(bill.customer_name)]]:[]),...(bill.customer_phone?[['Mobile',mobile(bill.customer_phone)]]:[])]
  : [['Table',bill.table_no?esc(bill.table_no):'Takeaway'],['Bill',`#${esc(bill.id)}`],['Date',esc(dateText(bill.opened_at))],...(tokens?[['Tokens',esc(tokens)]]:[])];
 const row=([k,v])=>`<div class="bill-row"><span>${k}</span><span>${v}</span></div>`;
 const address=isOrder&&bill.delivery_address?`<div class="bill-address"><span>Deliver to</span><p>${esc(bill.delivery_address)}</p></div>`:'';
 return `<article class="bill-paper" id="bill-print">
  <header class="bill-head"><div class="bill-brand">${esc(r.name)}</div>${r.address?`<div class="bill-sub">${esc(r.address)}</div>`:''}${r.phone?`<div class="bill-sub">Ph: ${esc(r.phone)}</div>`:''}${r.gstin?`<div class="bill-sub">GSTIN: ${esc(r.gstin)}</div>`:''}</header>
  <div class="bill-rule"></div>
  <section class="bill-info">${info.map(row).join('')}${address}</section>
  <div class="bill-rule"></div>
  <table class="bill-table"><colgroup><col class="c-item"><col class="c-qty"><col class="c-rate"><col class="c-amt"></colgroup><thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Rate</th><th class="num">Amount</th></tr></thead><tbody>${items||'<tr><td colspan="4">No items on this bill</td></tr>'}</tbody></table>
  <div class="bill-rule"></div>
  <div class="bill-totals"><div><span>Items</span><span>${qty}</span></div><div><span>Subtotal</span><span>${money(bill.subtotal)}</span></div>${bill.tax>0?`<div><span>Tax (${esc(bill.tax_percent)}%)</span><span>${money(bill.tax)}</span></div>`:''}<div class="bill-grand"><span>TOTAL</span><span>${money(bill.total)}</span></div></div>
  <div class="bill-rule"></div>
  <p class="bill-foot">${isOrder?'Order bill':(open?'Draft bill. Final once the table is closed.':`Paid and closed ${esc(dateText(bill.closed_at))}`)}</p>
  <p class="bill-foot strong">Thank you. Please visit again.</p>
 </article>`;
}
async function ownerBill(id){
 resetTimer();S.tableView=id;
 const {bill}=await api('owner/table/bill','GET',{id});
 if(S.ownerTab!=='tables'||S.tableView!==id)return;
 const open=bill.status==='open';
 adminLayout('owner',`${bill.table_no?'Table '+bill.table_no:'Takeaway'} bill`,open?'Print the bill for the guest, then mark it paid to close the table.':'Closed bill. You can print it again.',
  `<div class="bill-toolbar no-print row wrap"><button class="btn ghost mini" id="bill-back" type="button">← Tables</button><label class="field bill-paper-select"><span>Paper</span><select id="bill-paper" class="input">${opts([['thermal','Thermal 80mm'],['a4','A4 page']],S.billPaper||'thermal')}</select></label><button class="btn" id="bill-print" type="button">🖨 Print bill</button>${open?'<button class="btn gold-btn" id="bill-close" type="button">✓ Mark paid & close</button>':''}</div><div class="bill-wrap">${billHtml(bill)}</div>`);
 $('#bill-back').onclick=()=>run(ownerTables);
 $('#bill-paper').onchange=e=>{S.billPaper=e.target.value};
 $('#bill-print').onclick=()=>printBill();
 const close=$('#bill-close');
 if(close)close.onclick=()=>{
  if(!confirm(`Close bill #${bill.id}${bill.table_no?' for Table '+bill.table_no:''}? Total ${money(bill.total)}. The next order at this table starts a new bill.`))return;
  run(async()=>{const res=await api('owner/table/close','POST',{id:bill.id});toast('Bill closed. Total '+money(res.bill.total));await ownerBill(bill.id)});
 };
}
function printBill(){
 const paper=S.billPaper||'thermal';
 $('#bill-page-style')?.remove();
 const style=document.createElement('style');style.id='bill-page-style';
 style.textContent=`@page{size:${paper==='a4'?'A4':'80mm auto'};margin:${paper==='a4'?'12mm':'3mm'}}`;
 document.head.append(style);
 document.body.dataset.paper=paper;document.body.classList.add('printing-bill');
 const done=()=>{document.body.classList.remove('printing-bill');style.remove();window.removeEventListener('afterprint',done)};
 window.addEventListener('afterprint',done);
 window.print();
}

// ---- Staff-entered orders (WhatsApp, phone, walk-in, delivery) ----
async function openManualOrder(tableNo=0){
 const [board,menu]=await Promise.all([api('owner/tables'),api('owner/menu')]);
 const counter=!!board.counter;
 const items=menu.items.filter(i=>Number(i.available)===1).map(i=>({...i,options:i.options||[],addons:i.addons||[]}));
 const cart=new Map();
 const requestId=crypto.randomUUID?.()||('rq-'+Date.now()+'-'+Math.random().toString(36).slice(2));
 const tables=board.tables||[];
 const firstTable=Number(tableNo)||tables[0]?.table_no||1;
 const tableOptions=tables.map(t=>`<option value="${t.table_no}" ${t.table_no===firstTable?'selected':''}>Table ${t.table_no}${t.session?' (open bill)':''}</option>`).join('');
 const typeOptions=[...(counter?[]:[['dine_in','Dine-in (table)']]),['takeaway','Takeaway'],['delivery','Home delivery']];
 const startType=counter?'takeaway':(Number(tableNo)>0?'dine_in':'takeaway');
 const wrap=document.createElement('div');wrap.className='modal-wrap';
 wrap.innerHTML=`<div class="modal mo-modal" role="dialog" aria-modal="true" aria-label="Take an order">
  <div class="row between"><h2 class="serif">Take an order</h2><button class="icon-btn" id="mo-close" type="button" aria-label="Close">×</button></div>
  <div class="grid2 mt20"><label class="field"><span>Order came from</span><select id="mo-source" class="input">${opts([['whatsapp','WhatsApp'],['phone','Phone call'],['walkin','Walk-in']],'whatsapp')}</select></label>
  <label class="field"><span>Order type</span><select id="mo-type" class="input">${opts(typeOptions,startType)}</select></label></div>
  <div class="grid2 mt10" id="mo-table-wrap"><label class="field"><span>Table</span><select id="mo-table" class="input">${tableOptions}</select></label></div>
  <div class="grid2 mt10"><label class="field"><span id="mo-name-label">Customer name (optional)</span><input id="mo-name" class="input" maxlength="60" autocomplete="off"></label><label class="field"><span id="mo-phone-label">Mobile (optional)</span><input id="mo-phone" class="input" type="tel" inputmode="tel" maxlength="16" autocomplete="off"></label></div>
  <label class="field mt10" id="mo-address-wrap"><span>Delivery address (flat or house no., street, landmark, area)</span><textarea id="mo-address" class="input" rows="2" maxlength="400" autocomplete="off"></textarea></label>
  <label class="field mt10"><span>Note (optional)</span><input id="mo-note" class="input" maxlength="500" autocomplete="off"></label>
  <div class="mo-grid">
   <div class="stack-sm"><input id="mo-search" class="input" placeholder="Search dishes" aria-label="Search dishes" autocomplete="off"><div id="mo-items" class="mo-list"></div></div>
   <div class="stack-sm"><b>Order</b><div id="mo-cart" class="mo-cart"></div><div class="row between"><span class="small muted">Total (estimate)</span><b id="mo-total">₹0</b></div></div>
  </div>
  <button id="mo-submit" class="btn full mt20" type="button">Send order to Orders</button>
  <p class="caption mt10">Priced on the server with your menu. It gets a token number, and you get a message with the tracking link to send the customer.</p>
 </div>`;
 document.body.append(wrap);
 const q=sel=>wrap.querySelector(sel);
 // Show only the fields the chosen order type needs, and mark what delivery requires.
 const syncType=()=>{const type=q('#mo-type').value,delivery=type==='delivery';
  q('#mo-table-wrap').hidden=type!=='dine_in';q('#mo-address-wrap').hidden=!delivery;
  q('#mo-name-label').textContent=delivery?'Customer name (required)':'Customer name (optional)';
  q('#mo-phone-label').textContent=delivery?'Mobile (required)':'Mobile (optional)';};
 q('#mo-type').onchange=syncType;syncType();
 const close=()=>wrap.remove();
 q('#mo-close').onclick=close;
 wrap.onclick=e=>{if(e.target===wrap)close()};
 const paintItems=()=>{
  const search=(q('#mo-search').value||'').toLowerCase().trim();
  const list=items.filter(i=>!search||i.name.toLowerCase().includes(search));
  q('#mo-items').innerHTML=list.length?list.map(i=>`<button class="mo-add" type="button" data-add="${i.id}"><span>${FOOD_MARK[i.food_type]||''} ${esc(i.name)}</span><small>${esc(priceText(i))}</small></button>`).join(''):'<p class="caption">No dish matches. Try another word.</p>';
 };
 const paintCart=()=>{
  const lines=[...cart.values()];
  q('#mo-cart').innerHTML=lines.length?lines.map(l=>`<div class="mo-line"><div class="row between"><b>${esc(l.item.name)}</b><button class="icon-btn" type="button" data-remove="${l.item.id}" aria-label="Remove ${esc(l.item.name)}">×</button></div>${l.item.options.length?`<select class="input" data-variant="${l.item.id}" aria-label="Size for ${esc(l.item.name)}">${l.item.options.map(o=>`<option value="${esc(o.name)}" ${o.name===l.variant?'selected':''}>${esc(o.name)} · ${money(o.price)}</option>`).join('')}</select>`:''}${l.item.addons.length?`<div class="mo-addons">${l.item.addons.map(a=>`<label class="radio-line"><input type="checkbox" data-addon="${l.item.id}" value="${esc(a.name)}" ${l.addons.has(a.name)?'checked':''}> ${esc(a.name)} +${money(a.price)}</label>`).join('')}</div>`:''}<div class="row between"><div class="row"><button class="btn mini ghost" type="button" data-qty="${l.item.id}" data-d="-1" aria-label="Less">−</button><b>${l.qty}</b><button class="btn mini ghost" type="button" data-qty="${l.item.id}" data-d="1" aria-label="More">+</button></div><b>${money(lineAmount(l))}</b></div></div>`).join(''):'<p class="caption">Tap dishes on the left to add them.</p>';
  q('#mo-total').textContent=money(lines.reduce((n,l)=>n+lineAmount(l),0));
 };
 q('#mo-search').oninput=paintItems;
 q('#mo-items').onclick=e=>{const b=e.target.closest('[data-add]');if(!b)return;const id=+b.dataset.add,cur=cart.get(id);
  if(cur)cur.qty=Math.min(20,cur.qty+1);else{const item=items.find(i=>i.id===id);if(!item)return;cart.set(id,{item,variant:item.options[0]?.name||'',addons:new Set(),qty:1})}paintCart()};
 q('#mo-cart').onclick=e=>{
  const qty=e.target.closest('[data-qty]'),rm=e.target.closest('[data-remove]');
  if(qty){const id=+qty.dataset.qty,l=cart.get(id);if(!l)return;l.qty+=Number(qty.dataset.d);if(l.qty<1)cart.delete(id);paintCart()}
  if(rm){cart.delete(+rm.dataset.remove);paintCart()}
 };
 q('#mo-cart').onchange=e=>{
  const v=e.target.dataset.variant,a=e.target.dataset.addon;
  if(v){const l=cart.get(+v);if(l){l.variant=e.target.value;paintCart()}}
  if(a){const l=cart.get(+a);if(l){e.target.checked?l.addons.add(e.target.value):l.addons.delete(e.target.value);paintCart()}}
 };
 paintItems();paintCart();
 const submit=q('#mo-submit');
 submit.onclick=()=>run(async()=>{
  const lines=[...cart.values()];if(!lines.length)return toast('Add at least one dish');
  const type=q('#mo-type').value,name=q('#mo-name').value.trim(),phone=q('#mo-phone').value.replace(/\D/g,''),address=q('#mo-address').value.trim(),table=type==='dine_in'?Number(q('#mo-table').value):0;
  if(type==='delivery'&&(name.length<2||phone.length<10||address.length<10))return toast('For delivery, enter the name, a 10-digit mobile and the full address');
  if(type==='dine_in'&&!table)return toast('Choose the table for this order');
  submit.disabled=true;submit.textContent='Sending…';
  let res;
  try{
   res=await api('owner/order/manual','POST',{source:q('#mo-source').value,fulfilment:type,table_no:table,customer_name:name,customer_phone:phone,delivery_address:type==='delivery'?address:'',note:q('#mo-note').value.trim(),client_request_id:requestId,items:lines.map(l=>({item_id:l.item.id,variant:l.variant,addons:[...l.addons],quantity:l.qty}))});
  }finally{if(wrap.isConnected){submit.disabled=false;submit.textContent='Send order to Orders'}}
  close();
  showOrderConfirmation(res,{fulfilment:type,table,name,phone,address,items:lines.map(l=>({quantity:l.qty,name:l.item.name+(l.variant?` (${l.variant})`:'')}))});
  toast(`Order sent. Token #${res.order.token_no}`);
  if(S.ownerTab==='tables'&&S.tableView==='board')await ownerTables();
  else if(S.ownerTab==='tables'&&typeof S.tableView==='number')await ownerBill(S.tableView);
  else if(S.ownerTab==='orders')await ownerOrders();
 });
}
// After an order: the token, the tracking link and the message to send. WhatsApp opens only when there is a mobile number.
function showOrderConfirmation(res,d){
 const o=res.order,r=res.restaurant||{name:'',slug:''};
 const link=trackingLink(r,o.token_no,o.token_day);
 const text=confirmationMessage({restaurant:r.name,name:d.name,token:o.token_no,fulfilment:d.fulfilment,tableNo:d.table,address:d.address,total:o.total,items:d.items,link});
 const wa=whatsappLink(d.phone,text);
 const wrap=document.createElement('div');wrap.className='modal-wrap';
 wrap.innerHTML=`<div class="modal" style="max-width:560px" role="dialog" aria-modal="true" aria-label="Order sent">
  <div class="row between"><h2 class="serif">Order sent · Token #${esc(o.token_no)}</h2><button class="icon-btn" id="conf-close" type="button" aria-label="Close">×</button></div>
  <p class="caption mt10">Send this to the customer. The tracking link opens their order with the token already filled in.</p>
  <textarea id="conf-text" class="mt10" rows="10" readonly>${esc(text)}</textarea>
  <div class="row wrap mt20">${wa?`<a class="btn" target="_blank" rel="noopener" href="${esc(wa)}">💬 Send on WhatsApp</a>`:''}<button class="btn outline" id="conf-copy" type="button">Copy message</button></div>
  ${wa?'':'<p class="caption mt10">No mobile number was entered, so copy the message and send it yourself.</p>'}
 </div>`;
 document.body.append(wrap);
 const close=()=>wrap.remove();
 wrap.querySelector('#conf-close').onclick=close;
 wrap.onclick=e=>{if(e.target===wrap)close()};
 wrap.querySelector('#conf-copy').onclick=async()=>{try{await navigator.clipboard.writeText(text);toast('Message copied')}catch(e){const box=wrap.querySelector('#conf-text');box.focus();box.select();toast('Select the message and copy it')}};
}

// ---- One order's bill (from its token), from the order card or the new-order prompt ----
async function openOrderBill(id,autoPrint=false){
 const {bill}=await api('owner/order/bill','GET',{id});
 const wrap=document.createElement('div');wrap.className='modal-wrap';
 wrap.innerHTML=`<div class="modal" style="max-width:460px;max-height:92vh;overflow:auto" role="dialog" aria-modal="true" aria-label="Order bill">
  <div class="row between"><h2 class="serif">Token #${esc(bill.token_no)} bill</h2><button class="icon-btn" id="ob-close" type="button" aria-label="Close">×</button></div>
  <div class="row wrap mt10 bill-toolbar no-print"><label class="field bill-paper-select"><span>Paper</span><select id="ob-paper" class="input">${opts([['thermal','Thermal 80mm'],['a4','A4 page']],S.billPaper||'thermal')}</select></label><button class="btn" id="ob-print" type="button">🖨 Print bill</button></div>
  <div class="bill-wrap mt10">${billHtml(bill)}</div>
 </div>`;
 document.body.append(wrap);
 const close=()=>wrap.remove();
 wrap.querySelector('#ob-close').onclick=close;
 wrap.onclick=e=>{if(e.target===wrap)close()};
 wrap.querySelector('#ob-paper').onchange=e=>{S.billPaper=e.target.value};
 wrap.querySelector('#ob-print').onclick=()=>printBill();
 if(autoPrint)setTimeout(printBill,150);
}

// ---- New-order watch: any owner tab, every 10 seconds, asks "Print the bill now?" for each new order ----
// ---- Voice alerts: the browser speaks each new order (Chrome needs one click on the page first; the toggle is that click) ----
function loadVoicePref(){try{return localStorage.getItem('rq_voice')==='1'}catch(e){return false}}
function orderVoiceText(o){
 const token=o.token_no?` टोकन ${o.token_no}.`:'';
 const kind=o.fulfilment==='delivery'?'होम डिलीवरी':o.fulfilment==='takeaway'?'टेकअवे':'';
 let from;
 if(+o.table_no)from=`टेबल ${o.table_no} से`;
 else if(o.source==='whatsapp')from='व्हाट्सऐप से'+(kind?`, ${kind}`:'');
 else from=kind||'काउंटर';
 return `नया ऑर्डर, ${from}.${token}`;
}
function speakText(text){
 if(!('speechSynthesis' in window))return;
 try{
  const u=new SpeechSynthesisUtterance(text);
  const voices=speechSynthesis.getVoices();const v=voices.find(x=>/^hi[-_]IN/i.test(x.lang))||voices.find(x=>/^en[-_]IN/i.test(x.lang))||voices.find(x=>/^en/i.test(x.lang));
  if(v)u.voice=v;
  u.lang=v?v.lang:'hi-IN';u.rate=0.95;u.volume=1;
  speechSynthesis.speak(u);
 }catch(e){console.warn(e)}
}
function speakOrder(o){if(S.voice)speakText(orderVoiceText(o))}
function toggleVoice(){
 S.voice=!S.voice;
 try{localStorage.setItem('rq_voice',S.voice?'1':'0')}catch(e){}
 if(S.voice)speakText('वॉइस अलर्ट चालू है। हर नया ऑर्डर बताया जाएगा।');
 else if('speechSynthesis' in window)speechSynthesis.cancel();
 return S.voice;
}
function startOrderWatch(){if(S.voice===undefined)S.voice=loadVoicePref();clearInterval(S.orderWatch);S.watch={outlet:null,previous:new Set(),primed:false};S.orderWatch=setInterval(()=>checkNewOrders().catch(e=>console.warn(e)),10e3)}
async function checkNewOrders(){
 if(!S.actor||!['owner','staff'].includes(S.actor.role)||document.visibilityState!=='visible')return;
 const outlet=S.actor.active_restaurant_id,w=S.watch;
 if(!w)return;
 if(w.outlet!==outlet){w.outlet=outlet;w.previous=new Set();w.primed=false}
 const {orders}=await api('owner/orders','GET',{range:'live'});
 if(S.watch!==w||S.actor?.active_restaurant_id!==outlet)return;
 const fresh=orders.filter(o=>o.status==='new'&&!w.previous.has(o.id));
 w.previous=new Set(orders.map(o=>o.id));
 // The first check after sign-in only learns what is already open; it never asks about old orders.
 if(!w.primed){w.primed=true;return}
 for(const o of fresh){speakOrder(o);askPrintBill(o)}
}
const printAskQueue=[];let printAskOpen=false;
function askPrintBill(o){printAskQueue.push(o);if(!printAskOpen)nextPrintAsk()}
function nextPrintAsk(){
 const o=printAskQueue.shift();
 if(!o){printAskOpen=false;return}
 printAskOpen=true;
 const wrap=document.createElement('div');wrap.className='modal-wrap';
 wrap.innerHTML=`<div class="modal" style="max-width:460px" role="alertdialog" aria-modal="true" aria-labelledby="ask-title">
  <div class="eyebrow">New order</div><h2 id="ask-title" class="serif mt10">Token #${esc(o.token_no)} · ${esc(fulfilLabel(o))}</h2>
  <p class="small mt10">${o.items.map(it=>`${Number(it.quantity)} × ${esc(it.dish_name)}`).join('<br>')}</p>
  <p class="small mt10"><b>${money(o.total)}</b>${o.customer_name?` · ${esc(o.customer_name)}`:''}</p>
  <p class="bold mt20">Print the bill now?</p>
  <div class="row wrap mt10"><button class="btn" id="ask-yes" type="button">Yes, print bill</button><button class="btn ghost" id="ask-no" type="button">No</button></div>
 </div>`;
 document.body.append(wrap);
 const done=()=>{wrap.remove();nextPrintAsk()};
 wrap.querySelector('#ask-yes').onclick=()=>{run(()=>openOrderBill(o.id,true));done()};
 wrap.querySelector('#ask-no').onclick=done;
 wrap.onclick=e=>{if(e.target===wrap)done()};
}
