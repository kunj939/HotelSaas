// Exercise the actual menu editor handlers without a server or DOM dependency.
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const source=fs.readFileSync('public/assets/app.js','utf8');
const elements=new Map(),handlers={},calls=[];
function element(id){if(!elements.has(id))elements.set(id,{value:'',innerHTML:'',disabled:false});return elements.get(id)}
let save;
const submit={disabled:false},modal={isConnected:true,querySelector:()=>submit,addEventListener:(event,fn)=>handlers[event]=fn};
const ctx={URL,FormData,console,Error,JSON,Number,String,navigator:{},document:{},S:{ownerMenu:{categories:[]},catalog:[]},
 $:element,$$:()=>[],esc:String,opts:()=>'',image:d=>d.image_url,toast:()=>{},
 modalForm:(title,ui,fn)=>{save=fn;return modal},run:fn=>fn(),
 api:async(route,method,data)=>{calls.push({route,data});return {url:'/uploads/'+'a'.repeat(32)+'.png'}},ownerMenu:async()=>{}};
vm.createContext(ctx);
vm.runInContext(source.slice(source.indexOf('function remoteImageUrl'),source.indexOf('function photo(item)')),ctx);
vm.runInContext(source.slice(source.indexOf('function menuEditor('),source.indexOf('async function ownerOrders(')),ctx);
async function main(){
 ctx.menuEditor(null);
 element('#photo-url').value='https://example.com/dish.jpg';
 await element('#photo-use-url').onclick();
 assert.equal(ctx.S.editing.image_url,'https://example.com/dish.jpg');
 element('#photo-url').value='javascript:alert(1)';
 await assert.rejects(element('#photo-use-url').onclick(),/HTTPS/);
 assert.equal(ctx.S.editing.image_url,'https://example.com/dish.jpg');
 const file=new Blob(['image'],{type:'image/png'});
 await handlers.paste({clipboardData:{items:[{kind:'file',type:'image/png',getAsFile:()=>file}]},preventDefault(){}});
 assert.equal(calls.at(-1).route,'owner/upload');
 assert.ok(ctx.S.editing.image_url.startsWith('/uploads/'));
 assert.equal(element('#photo-url').value,'');
 assert.equal(submit.disabled,false);
 await assert.rejects(handlers.paste({clipboardData:{items:[{kind:'file',type:'image/png',getAsFile:()=>({type:'image/png',size:2500001})}]},preventDefault(){}}),/2.5 MB/);
 element('#photo-remove').onclick();assert.equal(ctx.S.editing.image_url,'');
 element('#photo-url').value='https://example.com/new.jpg';
 element('#dish-name').value='Paneer';element('#size-template').value='single';element('#single-price').value='100';
 await save();assert.equal(calls.at(-1).route,'owner/menu/save');assert.equal(calls.at(-1).data.image_url,'https://example.com/new.jpg');
 ctx.navigator.clipboard={read:async()=>[{types:['image/png'],getType:async()=>file}]};
 await element('#photo-paste').onclick();assert.ok(ctx.S.editing.image_url.startsWith('/uploads/'));
 ctx.navigator.clipboard.read=async()=>[{types:['text/plain'],getType:async()=>new Blob(['https://example.com/new.jpg'])}];
 await element('#photo-paste').onclick();assert.equal(ctx.S.editing.image_url,'https://example.com/new.jpg');
 ctx.navigator.clipboard.read=async()=>{throw Error('Permission denied')};
 await element('#photo-paste').onclick();assert.equal(ctx.S.editing.image_url,'https://example.com/new.jpg');
 ctx.navigator.clipboard={};await element('#photo-paste').onclick();
 ctx.navigator.clipboard.read=async()=>[{types:['text/plain'],getType:async()=>new Blob(['not an image'])}];
 await element('#photo-paste').onclick();assert.equal(ctx.S.editing.image_url,'https://example.com/new.jpg');
 ctx.api=async()=>{throw Error('Network unavailable')};
 await assert.rejects(handlers.paste({clipboardData:{items:[{kind:'file',type:'image/png',getAsFile:()=>file}]},preventDefault(){}}),/Network/);
 assert.equal(submit.disabled,false);assert.equal(ctx.S.editing.image_url,'https://example.com/new.jpg');
 console.log('PASS: menu link selection, rejection, paste upload, size limit, removal, save, paste button (image/link/permission/unsupported/empty) and upload failure');
}
main().catch(e=>{console.error(e);process.exitCode=1});
