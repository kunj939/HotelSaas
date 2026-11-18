// Pure-logic checks for the table bills, order bills and order messages (no browser or database needed).
// Run: node tests/test_table_bills.js
'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const code=fs.readFileSync(path.join(__dirname,'..','public','assets','table-bills.js'),'utf8');
// table-bills.js uses helpers from app.js; stub only what the pure functions call.
const sandbox={money:n=>'₹'+Number(n||0).toLocaleString('en-IN',{maximumFractionDigits:2,minimumFractionDigits:0}),posterMenuUrl:r=>'https://tablebloom.example/r/'+r.slug,trackingLink:(r,token,day)=>'https://tablebloom.example/r/'+r.slug+'?track='+token+'&day='+day,esc:x=>String(x??''),dateText:s=>String(s||'')};
vm.createContext(sandbox);
vm.runInContext(code+'\n;this.exported={lineAmount,fulfilLabel,whatsappLink,confirmationMessage,billHtml,orderVoiceText};',sandbox);
const {lineAmount,fulfilLabel,whatsappLink,confirmationMessage,billHtml,orderVoiceText}=sandbox.exported;

// Line amounts: the selected size plus chosen extras, times quantity.
assert.equal(lineAmount({item:{base_price:100,options:[{name:'Half',price:60},{name:'Full',price:110}],addons:[{name:'Cheese',price:20},{name:'Butter',price:10}]},variant:'Full',addons:new Set(['Cheese']),qty:2}),260);
assert.equal(lineAmount({item:{base_price:150,options:[],addons:[]},variant:'',addons:new Set(),qty:1}),150);
// A dish without a chosen size falls back to its base price (the server still validates the real order).
assert.equal(lineAmount({item:{base_price:80,options:[{name:'Small',price:60}],addons:[]},variant:'',addons:new Set(),qty:3}),240);

// Order labels: delivery, table, takeaway and counter.
assert.equal(fulfilLabel({fulfilment:'delivery',table_no:0}),'Delivery');
assert.equal(fulfilLabel({fulfilment:'dine_in',table_no:4}),'Table 4');
assert.equal(fulfilLabel({fulfilment:'takeaway',table_no:0}),'Takeaway');
assert.equal(fulfilLabel({fulfilment:'dine_in',table_no:0}),'Counter');

// WhatsApp links: a 10-digit Indian mobile, with or without the country code, and the message encoded.
assert.equal(whatsappLink('+91 90535 73091','Hi there'),'https://wa.me/919053573091?text=Hi%20there');
assert.equal(whatsappLink('919053573091','A'),'https://wa.me/919053573091?text=A');
assert.equal(whatsappLink('12345','A'),'');
assert.equal(whatsappLink('',"A"),'');

// The message after an order: token, items, delivery address and the tracking link.
const delivery=confirmationMessage({restaurant:'Kaku',name:'Rahul',token:24,fulfilment:'delivery',tableNo:0,address:'Flat 4B, DLF Phase 3',total:470,items:[{quantity:2,name:'Paneer Tikka (Half)'},{quantity:1,name:'Lassi'}],link:'https://tablebloom.example/r/kaku?track=24&day=2026-11-18'});
assert.match(delivery,/^Hi Rahul! Your order at Kaku is confirmed\./);
assert.match(delivery,/Token: #24/);
assert.match(delivery,/Type: Home delivery/);
assert.match(delivery,/2 x Paneer Tikka \(Half\)\n1 x Lassi/);
assert.match(delivery,/Total: ₹470/);
assert.match(delivery,/Deliver to: Flat 4B, DLF Phase 3/);
assert.match(delivery,/Track your order: https:\/\/tablebloom\.example\/r\/kaku\?track=24&day=2026-11-18$/);

// Table and takeaway messages never include a delivery address line.
const table=confirmationMessage({restaurant:'Kaku',token:7,fulfilment:'dine_in',tableNo:5,address:'ignored',total:100,items:[],link:'L'});
assert.match(table,/Type: Table 5/);
assert.doesNotMatch(table,/Deliver to/);
assert.match(confirmationMessage({restaurant:'Kaku',token:8,fulfilment:'takeaway',tableNo:0,items:[],link:'L'}),/^Hi! Your order at Kaku is confirmed\.\nToken: #8\nType: Takeaway/);

// Order bill (from a token): shows the token, customer and delivery details, and no table-bill footer.
const orderBill=billHtml({kind:'order',id:16,token_no:24,fulfilment:'delivery',table_no:0,status:'new',opened_at:'2026-11-18 19:09:00',customer_name:'Rahul',customer_phone:'9053573091',delivery_address:'Flat 4B, DLF Phase 3',restaurant:{name:'Kaku',address:'U Block',phone:'',gstin:null},lines:[{name:'Lassi',extras:[],unit:80,quantity:2,amount:160}],orders:[{id:16,token_no:24,status:'new'}],subtotal:160,tax_percent:0,tax:0,total:160});
assert.match(orderBill,/Token<\/span><span><b class="bill-token">#24<\/b>/);
assert.match(orderBill,/Deliver to<\/span><p>Flat 4B, DLF Phase 3<\/p>/);
assert.match(orderBill,/\+91 90535 73091/);
assert.match(orderBill,/Order bill/);
assert.doesNotMatch(orderBill,/Tokens:/);
assert.match(orderBill,/TOTAL/);

// Voice alerts: short sentences that say where the order came from and its token.
assert.equal(orderVoiceText({source:'whatsapp',fulfilment:'delivery',table_no:0,token_no:12}),'New order placed from WhatsApp. Home delivery. Token 12.');
assert.equal(orderVoiceText({source:'qr',fulfilment:'dine_in',table_no:8,token_no:12}),'New order placed. Table 8. Token 12.');
assert.equal(orderVoiceText({source:'qr',fulfilment:'takeaway',table_no:0,token_no:3}),'New order placed. Takeaway. Token 3.');
assert.equal(orderVoiceText({source:'qr',fulfilment:'takeaway',table_no:0,token_no:null}),'New order placed. Takeaway.');

console.log('PASS: table bill line amounts, order labels, WhatsApp links, order messages, order bills and voice alerts');
