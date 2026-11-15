/* Local, deterministic poster templates. No remote generation or uploads. */
'use strict';
window.PartyPoster=(()=>{
 const themes={birthday:{title:'MAKE A WISH.\nMAKE A MEMORY.',subtitle:'Birthday celebrations',color:'#e9bd73'},party:{title:'GOOD FOOD.\nGREAT COMPANY.',subtitle:'Group parties & get-togethers',color:'#a5d9c6'},corporate:{title:'BRING YOUR\nTEAM TOGETHER.',subtitle:'Corporate gatherings & bulk orders',color:'#b8c9ee'}};
 function lines(ctx,text,width){const out=[];for(const paragraph of String(text||'').split('\n')){let line='';for(const word of paragraph.split(/\s+/)){if(ctx.measureText(line+(line?' ':'')+word).width<=width){line+=(line?' ':'')+word;continue;}if(line)out.push(line);line='';for(const char of word){if(ctx.measureText(line+char).width>width&&line){out.push(line);line='';}line+=char;}}out.push(line);}return out;}
 function fit(ctx,text,width,size,maxLines){let result;do{ctx.font=`bold ${size}px Georgia`;result=lines(ctx,text,width);if(result.length<=maxLines)break;size-=2;}while(size>20);return {lines:result,size};}
 function block(ctx,text,x,y,width,size,maxLines,color,font='Georgia'){const result=fit(ctx,text,width,size,maxLines);ctx.fillStyle=color;ctx.font=`bold ${result.size}px ${font}`;result.lines.slice(0,maxLines).forEach((line,i)=>ctx.fillText(line,x,y+i*result.size*1.2));return y+Math.min(result.lines.length,maxLines)*result.size*1.2;}
 async function render(restaurant,options={},menuUrl){
  const t=themes[options.type]||themes.birthday;const c=document.createElement('canvas');c.width=1080;c.height=1350;const ctx=c.getContext('2d');
  const gradient=ctx.createLinearGradient(0,0,1080,1350);gradient.addColorStop(0,'#163b34');gradient.addColorStop(1,'#081c19');ctx.fillStyle=gradient;ctx.fillRect(0,0,1080,1350);
  ctx.strokeStyle=t.color;ctx.lineWidth=2;ctx.strokeRect(32,32,1016,1286);
  for(let i=0;i<40;i++){const x=(i*257+31)%1080,y=(i*113+19)%900;ctx.fillStyle=i%2?t.color:'#ffffff';ctx.globalAlpha=.2;ctx.beginPath();ctx.arc(x,y,2+i%4,0,7);ctx.fill();}ctx.globalAlpha=1;
  ctx.fillStyle=t.color;ctx.font='bold 20px Arial';ctx.fillText('A LITTLE CELEBRATION. A LOT OF HAPPINESS.',72,96);
  block(ctx,restaurant.name,72,167,936,49,3,'#fff7e9');
  ctx.fillStyle=t.color;ctx.font='24px Arial';ctx.fillText(t.subtitle.toUpperCase(),72,345);
  block(ctx,options.heading||t.title,72,436,936,80,3,'#fff7e9');
  ctx.fillStyle=t.color;ctx.fillRect(72,735,120,5);
  block(ctx,options.details||'Gather your favourite people around a delicious meal.',72,790,936,32,3,'#e4eee8','Arial');
  if(options.offer)block(ctx,options.offer,72,925,936,34,2,t.color,'Arial');
  ctx.strokeStyle='#46645c';ctx.beginPath();ctx.moveTo(72,1018);ctx.lineTo(1008,1018);ctx.stroke();
  ctx.fillStyle=t.color;ctx.font='bold 22px Arial';ctx.fillText('ENQUIRE & PLAN YOUR CELEBRATION',72,1070);
  const phone=restaurant.phone||restaurant.whatsapp_number||'';
  block(ctx,phone||'Contact our restaurant team',72,1125,650,38,2,'#fff7e9','Arial');
  if(restaurant.address)block(ctx,restaurant.address,72,1201,650,22,3,'#d2ded8','Arial');
  if(!window.LocalQR)throw Error('QR generator could not load. Refresh and retry.');
  const svg=window.LocalQR.svg(menuUrl).replace('<svg ','<svg width="256" height="256" ');const url=URL.createObjectURL(new Blob([svg],{type:'image/svg+xml'}));
  try{const img=new Image();await new Promise((resolve,reject)=>{img.onload=resolve;img.onerror=()=>reject(Error('Menu QR could not render'));img.src=url;});ctx.fillStyle='#fff';ctx.fillRect(766,1044,242,242);ctx.drawImage(img,776,1054,222,222);}finally{URL.revokeObjectURL(url);}
  ctx.fillStyle=t.color;ctx.font='bold 15px Arial';ctx.textAlign='center';ctx.fillText('SCAN TO EXPLORE OUR MENU',887,1306);
  return c;
 }
 async function download(canvas,name){const blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/png'));if(!blob)throw Error('Poster could not be exported');const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=(String(name||'restaurant').toLowerCase().replace(/[^a-z0-9]+/g,'-')||'restaurant')+'-party-poster.png';document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),30000);}
 return {render,download,themes};
})();
