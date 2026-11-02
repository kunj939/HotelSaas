/* TEST/DEMO ONLY. No real server, database or authentication.
 * DELETE demo.html + assets/demo.js before production. */
(()=>{
'use strict';
const originalFetch=window.fetch.bind(window);
const uuid=()=>Math.random().toString(36).slice(2)+Date.now();
let actor=null, orderId=1040;
const restaurant={id:1,slug:'demo-kitchen',name:'The Royal Kitchen',tagline:'Where every bite tells a story',welcome_message:'Welcome to a world of unforgettable flavours. Explore our chef’s favourites, made fresh just for you.',primary_color:'#186953',accent_color:'#E4B866',theme_mode:'light',menu_layout:'list',table_count:20,qr_mode:'common',ordering_enabled:1,status:'active',subscription_ends:'2027-11-01',monthly_fee:599,can_order:true,cover_url:'assets/photos/hero-thali.jpg',cover_credit:'Hopeskilled',cover_source:'https://commons.wikimedia.org/wiki/File:Indian_Thali_Og.jpg',logo_url:'',phone:'',address:'',opening_hours:'11:00 AM – 11:00 PM'};
const categories=[{id:1,name:'Starters'},{id:2,name:'Main Course'},{id:3,name:'Breads & Rice'},{id:4,name:'Beverages'},{id:5,name:'Desserts'}];
const starters=[['Paneer Tikka',259,1,'veg','Smoky tandoori paneer marinated with herbs.'],['Hara Bhara Kebab',199,1,'veg','Crisp green kebabs filled with tender vegetables.'],['Chicken Malai Tikka',329,1,'nonveg','Soft creamy chicken with a delicate tandoori finish.'],['Dahi Ke Kebab',219,1,'veg','Golden-crisp kebabs with a silky hung-curd centre.'],['Paneer Butter Masala',299,2,'veg','Creamy tomato gravy with tender cubes of paneer.'],['Dal Makhani',259,2,'veg','Black lentils simmered slowly with butter.'],['Kadai Paneer',279,2,'veg','Paneer, peppers, warming roasted spices.'],['Butter Chicken',359,2,'nonveg','Tender chicken in fragrant buttery sauce.'],['Butter Naan',65,3,'veg','Soft tandoor-baked bread brushed with butter.'],['Garlic Naan',79,3,'veg','Fresh naan, garlic, herbs and a golden crust.'],['Jeera Rice',169,3,'veg','Long-grain basmati with toasted cumin.'],['Masala Chai',69,4,'veg','Indian tea brewed with fragrant spices.'],['Sweet Lassi',109,4,'veg','Chilled, thick yoghurt drink.'],['Gulab Jamun',129,5,'veg','Warm golden milk dumplings in rose syrup.'],['Rasmalai',169,5,'veg','Delicate milk cakes with saffron cream.']];
// Real Wikimedia Commons photos. Full metadata: public/assets/photos/credits.json
// Entry: [file, photographer, licence, source page]. Dishes without an entry use the illustration.
const PHOTOS={
'Paneer Tikka':['assets/photos/paneer-tikka.jpg','Gpkp','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Paneer_Tikka_Masala_(2026)_01.jpg'],
'Hara Bhara Kebab':['assets/photos/hara-bhara-kebab.jpg','Manjula Bharath','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Hara_Bhara_Kabab.JPG'],
'Chicken Malai Tikka':['assets/photos/chicken-malai-tikka.jpg','Renupradhul','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Chicken_Malai_Tikka.JPG'],
'Dahi Ke Kebab':['assets/photos/dahi-ke-kebab.jpg','Mayurbhandari','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Dahi_ke_Kebabs.JPG'],
'Paneer Butter Masala':['assets/photos/paneer-butter-masala.jpg','Rohith Thulasidas','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Paneer_Butter_Masala_-_Garnished.jpg'],
'Dal Makhani':['assets/photos/dal-makhani.jpg','Nithyasrm','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Dal_makhani.JPG'],
'Kadai Paneer':['assets/photos/kadai-paneer.jpg','Monali.mishra','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Kadai_Paneer_With_Gravy.jpg'],
'Butter Chicken':['assets/photos/butter-chicken.jpg','Marajozkee','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Chicken_butter_masala.jpg'],
'Butter Naan':['assets/photos/butter-naan.jpg','K.Sreeram Wiki','CC0','https://commons.wikimedia.org/wiki/File:Butter_Naan_With_Paneer_Butter_Masala.jpg'],
'Garlic Naan':['assets/photos/garlic-naan.jpg','Durvankur Patil','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Garlic_Naan.JPG'],
'Jeera Rice':['assets/photos/jeera-rice.jpg','Marajozkee','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Jeera_rice.jpg'],
'Masala Chai':['assets/photos/masala-chai.jpg','Sarkar Sayantan','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Masala_Tea_-_2.jpg'],
'Sweet Lassi':['assets/photos/sweet-lassi.jpg','Shumayla Zafri','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Sweet_lassi_with_roohafza.JPG'],
'Gulab Jamun':['assets/photos/gulab-jamun.jpg','Gaurav Dhwaj Khadka','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Gulab_Jamun_1.jpg'],
'Rasmalai':['assets/photos/rasmalai.jpg','Dharmadhyaksha','CC BY-SA 4.0','https://commons.wikimedia.org/wiki/File:Rasmalai_-_02.JPG'],
};
const photoFields=name=>{const p=PHOTOS[name];return {image_url:p?p[0]:'',image_credit:p?p[1]:'',image_license:p?p[2]:'',image_source_url:p?p[3]:''}};
let items=starters.map((row,i)=>({id:i+1,restaurant_id:1,name:row[0],base_price:row[1],category_id:row[2],food_type:row[3],description:row[4],available:1,options:row[0]==='Paneer Butter Masala'?[{name:'Half',price:189},{name:'Full',price:299}]:[],addons:row[0]==='Butter Naan'?[{name:'Extra butter',price:20}]:[],...photoFields(row[0])}));
let orders=[{id:1039,table_no:5,status:'new',total:428,created_at:'2026-11-01 19:45:00',items:[{dish_name:'Paneer Butter Masala',quantity:1,variant_name:'Half',addons:[],line_total:189},{dish_name:'Hara Bhara Kebab',quantity:1,variant_name:'',addons:[],line_total:239}],note:''}];
let auth='csrf-demo';let payments=[];
const json=(body,status=200)=>Promise.resolve(new Response(JSON.stringify(body),{status,headers:{'Content-Type':'application/json'}}));
const bad=(error,status=400)=>json({error},status);
window.fetch=async function(input,opts={}){
 const url=String(input);
 if(!url.includes('api/index.php?route='))return originalFetch(input,opts);
 const qs=new URL(url,location.href).searchParams,route=qs.get('route');let b={};try{b=JSON.parse(opts.body||'{}')}catch(e){}
 if(route==='bootstrap')return json({actor,csrf:auth,app_url:location.origin+location.pathname.replace(/\/[^/]+$/,'')});
 if(route==='auth/login'){
  actor=b.email==='kvmeena2004@outlook.com'?{id:1,restaurant_id:null,role:'super',full_name:'Platform Admin',email:b.email}:{id:2,restaurant_id:1,role:'owner',full_name:'Demo Restaurant Owner',email:b.email};
  return json({actor,csrf:auth});
 }
 if(route==='auth/logout'){actor=null;return json({ok:true,csrf:auth});}
 if(route==='public/restaurant')return json({restaurant,categories,items});
 if(route==='public/order'){
  const tab=Number(b.table_no);if(!tab||tab>restaurant.table_count)return bad('Invalid table number');
  let count=0;const details=[];for(let row of b.items||[]){const dish=items.find(x=>x.id===row.item_id);if(!dish)return bad('Dish unavailable');let unit=dish.options.length?Number(dish.options.find(x=>x.name===row.variant)?.price):Number(dish.base_price);unit+=dish.addons.filter(x=>row.addons.includes(x.name)).reduce((s,x)=>s+Number(x.price),0);count+=unit*row.quantity;details.push({dish_name:dish.name,quantity:row.quantity,variant_name:row.variant,addons:row.addons.map(name=>({name})),line_total:unit*row.quantity})}
  const o={id:++orderId,table_no:tab,total:count,status:'new',tracking_key:uuid().padEnd(64,'a').slice(0,64),client_request_id:b.client_request_id,created_at:new Date().toISOString().replace('T',' ').slice(0,19),items:details,note:b.note};orders.unshift(o);return json({order:o},201);
 }
 if(route==='public/status'){const o=orders.find(x=>String(x.id)===qs.get('id'));return o?json({order:o}):bad('Not found',404)}
 if(route==='owner/overview')return json({restaurant,stats:{today_orders:orders.length,live_orders:orders.filter(o=>!['served','rejected'].includes(o.status)).length,order_value:orders.reduce((s,o)=>s+o.total,0)}});
 if(route==='owner/menu')return json({categories,items});
 if(route==='owner/category'){categories.push({id:categories.length+1,name:b.name});return json({ok:true})}
 if(route==='owner/menu/save'){if(b.id){items=items.map(x=>x.id===b.id?{...x,...b}:x)}else{b.id=Date.now();items.push({...b})}return json({ok:true,id:b.id})}
 if(route==='owner/menu/delete'){items=items.filter(x=>x.id!==b.id);return json({ok:true})}
 if(route==='owner/menu/toggle'){const i=items.find(x=>x.id===b.id);if(i)i.available=i.available?0:1;return json({ok:true})}
 if(route==='owner/orders'){let range=qs.get('range');return json({orders:range==='live'?orders.filter(o=>!['served','rejected'].includes(o.status)):orders})}
 if(route==='owner/order/status'){let o=orders.find(x=>x.id===b.id);if(!o)return bad('Not found',404);o.status=b.status;return json({ok:true})}
 if(route==='owner/analytics')return json({summary:{orders:orders.length,served:orders.filter(o=>o.status==='served').length,rejected:0,served_order_value:orders.filter(o=>o.status==='served').reduce((s,o)=>s+o.total,0)},days:[{day:'2026-11-01',orders:orders.length,value:orders.reduce((s,o)=>s+o.total,0)}],top:[{dish_name:'Paneer Butter Masala',qty:28},{dish_name:'Butter Naan',qty:19}],peak:[{hour:19,orders:24}]});
 if(route==='owner/settings')return json({restaurant});
 if(route==='owner/settings/save'){Object.assign(restaurant,b);return json({ok:true})}
 if(route==='owner/password')return json({ok:true});
 if(route==='owner/photos/suggest')return json({photos:[],message:'Live photo suggestions require the PHP backend and an internet connection.'});
 if(route==='owner/upload')return bad('Real uploads require a deployed PHP server');
 if(route==='admin/overview')return json({stats:{restaurants:1,active:1,expired:0,mrr:599},orders:{all_orders:orders.length,this_month:orders.length},collected:{amount:payments.reduce((s,p)=>s+p.amount,0)},growth:[{month:'2026-11',restaurants:1}]});
 if(route==='admin/restaurants')return json({restaurants:[{...restaurant,month_orders:orders.length,menu_count:items.length,owner_email:'owner@demo.test'}]});
 if(route==='admin/restaurants/create')return json({ok:true,id:2});
 if(route==='admin/restaurants/update'){Object.assign(restaurant,b);return json({ok:true})}
 if(route==='admin/payment'){payments.push(b);return json({ok:true})}
 if(route==='admin/password')return json({ok:true});
 return bad('Unknown mock API route',404);
};
// On demo.html, automatically redirect empty hash to a real clickable sample customer experience.
if(!location.hash)location.hash='#/r/demo-kitchen';
})();
