<?php
declare(strict_types=1);
// Hostinger-compatible JSON API. Requires PHP 8.2+ and PDO MySQL.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
$configFile = __DIR__.'/config.local.php';
if (!is_file($configFile)) {
 http_response_code(503); echo json_encode(['error'=>'Setup required: copy api/config.example.php to api/config.local.php.']); exit;
}
$c = require $configFile;
if (!is_array($c)) { http_response_code(500); echo '{"error":"Invalid config"}'; exit; }
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
 session_name('rq_session');
 session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
 session_start();
}
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
require __DIR__.'/db.php';
require __DIR__.'/payu.php';
function respond($data,int $status=200):never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit; }
function fail(string $message,int $code=400):never { respond(['error'=>$message],$code); }
function body():array { $x=json_decode(file_get_contents('php://input') ?: '{}',true);if(!is_array($x))fail('Invalid JSON');return $x; }
function val($v,int $max=200):string { return trim(mb_substr((string)($v??''),0,$max)); }
function hexcolor($v,string $default):string {$x=val($v,7);return preg_match('/^#[0-9a-fA-F]{6}$/D',$x)?$x:$default;}
function num($v,float $min=0,float $max=999999):float {if(!is_numeric($v)||!is_finite((float)$v)) fail('Invalid numeric value');$n=(float)$v;if($n<$min||$n>$max) fail('Value out of range');return round($n,2);}
function actor():?array {if(!isset($_SESSION['uid']))return null;$a=one('SELECT id,restaurant_id,role,full_name,email,active FROM users WHERE id=?',[(int)$_SESSION['uid']]);return $a&&$a['active'] ? $a:null;}
function requireRole(string ...$roles):array {$a=actor();if(!$a||!in_array($a['role'],$roles,true))fail('Please sign in with the required account',401);return $a;}
// Restaurants this owner/staff login may open. users.restaurant_id is the home restaurant; owners can be linked to more.
function outlets(array $a):array {
 if($a['role']==='super')return [];
 $list=rows('SELECT r.id,r.name,r.slug FROM restaurant_access ra JOIN restaurants r ON r.id=ra.restaurant_id WHERE ra.user_id=? AND r.status<>"suspended" AND r.deleted_at IS NULL ORDER BY r.id=? DESC,r.name',[$a['id'],(int)$a['restaurant_id']]);
 if(!$list&&$a['restaurant_id'])$list=rows('SELECT id,name,slug FROM restaurants WHERE id=? AND status<>"suspended" AND deleted_at IS NULL',[$a['restaurant_id']]);
 return $list;
}
function outletIds(array $a):array {return array_map(fn($o)=>(int)$o['id'],outlets($a));}
function placeholders(array $ids):string {return implode(',',array_fill(0,count($ids),'?'));}
// The restaurant currently open in the dashboard (switchable when the owner has several outlets).
function rid(array $a):int {
 $ids=outletIds($a);if(!$ids)fail('This restaurant account is stopped. Contact TableBloom support.',403);$sel=(int)($_SESSION['rid']??0);
 return in_array($sel,$ids,true)?$sel:(in_array((int)$a['restaurant_id'],$ids,true)?(int)$a['restaurant_id']:$ids[0]);
}
function actorInfo():?array {$a=actor();if(!$a||$a['role']==='super')return $a;$a['outlets']=outlets($a);$a['active_restaurant_id']=$a['outlets']?rid($a):null;return $a;}
// Orders of any outlet this login can open; used so the combined order view can act on every outlet's orders.
function outletOrder(array $a,int $id):?array {$ids=outletIds($a);return $ids?one('SELECT id,restaurant_id,status FROM orders WHERE id=? AND restaurant_id IN ('.placeholders($ids).')',array_merge([$id],$ids)):null;}
function csrf():void {if(!hash_equals($_SESSION['csrf'],(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))fail('Session expired. Refresh and retry.',419);}
function mutation():void {if($_SERVER['REQUEST_METHOD']!=='POST')fail('Method not allowed',405);csrf();}
function audit(string $action,?int $rid=null,string $detail=''):void {$a=actor();execq('INSERT INTO audit_logs(user_id,restaurant_id,action,detail) VALUES(?,?,?,?)',[$a['id']??null,$rid,$action,substr($detail,0,400)]);}
function statusActive(array $r):bool {return in_array($r['status'],['active','trial'],true) && $r['subscription_ends']>=date('Y-m-d') && (int)$r['ordering_enabled']===1;}
function orderRow(array $o):array { $o['items']=rows('SELECT dish_name,variant_name,addons_json,quantity,unit_price,line_total FROM order_items WHERE order_id=?',[$o['id']]); foreach($o['items'] as &$item){$item['addons']=json_decode($item['addons_json']??'[]',true)?:[];unset($item['addons_json']);}return $o; }
function imagePath($image):bool { $x=val($image,800);return !$x || preg_match('~^/uploads/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$~',$x) || (str_starts_with($x,'https://upload.wikimedia.org/') && !str_contains($x,'"')); }
function jsonOptions($arr,int $max=15):string {
 if(!is_array($arr) || count($arr)>$max)fail('Too many item options');$out=[];
 foreach($arr as $a){if(!is_array($a))fail('Invalid item option');$name=val($a['name']??'',80);if(!$name)fail('Option name required');$out[]=['name'=>$name,'price'=>num($a['price']??0,0,50000)];}
 return json_encode($out,JSON_UNESCAPED_UNICODE);
}
function iphash():string {global $c;return hash_hmac('sha256',$_SERVER['REMOTE_ADDR']??'unknown',$c['app_secret']);}
try {
 $route=trim((string)($_GET['route']??'bootstrap'),'/');
 if($route==='bootstrap')respond(['actor'=>actorInfo(),'csrf'=>$_SESSION['csrf'],'app_url'=>$c['app_url']]);
 if($route==='auth/login') {
  mutation();$b=body();$email=strtolower(val($b['email']??'',190));$password=(string)($b['password']??'');
  if(count(rows('SELECT id FROM auth_attempts WHERE ip_hash=? AND attempted_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)',[iphash()]))>=8)fail('Too many attempts. Try again in 15 minutes.',429);
  $u=one('SELECT * FROM users WHERE email=? AND active=1',[$email]);
  if(!$u||!password_verify($password,$u['password_hash'])){
   execq('INSERT INTO auth_attempts(ip_hash) VALUES(?)',[iphash()]);fail('Incorrect email or password',401);
  }
  // Owners/staff need at least one restaurant that is not stopped or deleted.
  if($u['role']!=='super'&&!outlets($u))fail('Account suspended. Contact support.',403);
  session_regenerate_id(true);unset($_SESSION['rid']);$_SESSION['uid']=(int)$u['id'];$_SESSION['csrf']=bin2hex(random_bytes(24));
  respond(['actor'=>actorInfo(),'csrf'=>$_SESSION['csrf']]);
 }
 if($route==='auth/logout'){mutation();$_SESSION=[];session_regenerate_id(true);$_SESSION['csrf']=bin2hex(random_bytes(24));respond(['ok'=>true,'csrf'=>$_SESSION['csrf']]);}
 if($route==='public/restaurant'){
  $slug=val($_GET['slug']??'',90);$r=one('SELECT id,slug,name,tagline,welcome_message,logo_url,cover_url,primary_color,accent_color,theme_mode,menu_layout,table_count,qr_mode,ordering_enabled,status,subscription_ends,opening_hours FROM restaurants WHERE slug=? AND deleted_at IS NULL',[$slug]);
  if(!$r)fail('Restaurant not found',404);
  $categories=rows('SELECT id,name FROM categories WHERE restaurant_id=? ORDER BY sort_order,id',[$r['id']]);
  $items=rows('SELECT id,category_id,name,description,food_type,base_price,options_json,addons_json,image_url,image_credit,image_license,image_source_url,available FROM menu_items m WHERE restaurant_id=? AND deleted_at IS NULL ORDER BY (SELECT c.sort_order FROM categories c WHERE c.id=m.category_id),m.sort_order,m.name',[$r['id']]);
  foreach($items as &$item){$item['options']=json_decode($item['options_json']??'[]',true)?:[];$item['addons']=json_decode($item['addons_json']??'[]',true)?:[];unset($item['options_json'],$item['addons_json']);}
  $r['can_order']=statusActive($r);unset($r['subscription_ends'],$r['status']);respond(['restaurant'=>$r,'categories'=>$categories,'items'=>$items]);
 }
 if($route==='public/order'){
  mutation();$b=body();$slug=val($b['slug']??'',90);$r=one('SELECT * FROM restaurants WHERE slug=? AND deleted_at IS NULL',[$slug]);if(!$r)fail('Restaurant unavailable',404);
  if(!statusActive($r))fail('Online ordering is currently paused',403);
  $counter=$r['qr_mode']==='counter';
  $table=$counter?0:(int)($b['table_no']??0);if(!$counter&&($table<1||$table>(int)$r['table_count']))fail('Please select a valid table number');
  if($r['qr_mode']==='table'){$expected=(int)($b['qr_table']??0);if($expected!==$table)fail('Table does not match the scanned QR');}
  $token=val($b['client_request_id']??'',80);if(!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$token))fail('Invalid order request');
  $existing=one('SELECT id,tracking_key,table_no,token_no,total,status FROM orders WHERE restaurant_id=? AND client_request_id=?',[$r['id'],$token]);
  if($existing)respond(['order'=>$existing,'duplicate'=>true]);
  $lines=$b['items']??[];if(!is_array($lines)||count($lines)<1||count($lines)>30)fail('Cart must have 1–30 items');
  $recent=one('SELECT COUNT(*) AS n FROM orders WHERE restaurant_id=? AND ip_hash=? AND created_at>DATE_SUB(NOW(),INTERVAL 10 MINUTE)',[$r['id'],iphash()]);
  if((int)$recent['n']>=8)fail('Too many recent orders. Please ask restaurant staff for help.',429);
  $prepared=[];$total=0;
  foreach($lines as $l){
   if(!is_array($l))fail('Invalid cart');$itemId=(int)($l['item_id']??0);$qty=(int)($l['quantity']??0);
   if($qty<1||$qty>20)fail('Quantity must be 1–20');
   $m=one('SELECT * FROM menu_items WHERE id=? AND restaurant_id=? AND available=1 AND deleted_at IS NULL',[$itemId,$r['id']]);if(!$m)fail('An item is no longer available. Please review cart.');
   $opts=json_decode($m['options_json']??'[]',true)?:[];$addons=json_decode($m['addons_json']??'[]',true)?:[];
   $variant=val($l['variant']??'',80);$unit=(float)$m['base_price'];
   if($opts){$found=false;foreach($opts as $o){if($o['name']===$variant){$unit=(float)$o['price'];$found=true;break;}}if(!$found)fail('Select a valid size for '.$m['name']);}
   else {$variant='';}
   $selected=$l['addons']??[];if(!is_array($selected)||count($selected)>10)fail('Invalid extras');$checked=[];
   foreach(array_unique($selected) as $extra){$match=null;foreach($addons as $a)if($a['name']===$extra){$match=$a;break;}if(!$match)fail('Invalid extra for '.$m['name']);$unit+=(float)$match['price'];$checked[]=$match;}
   $line=round($unit*$qty,2);$total+=$line;
   $prepared[]=['item_id'=>$itemId,'name'=>$m['name'],'variant'=>$variant,'addons'=>$checked,'quantity'=>$qty,'unit'=>round($unit,2),'line'=>$line];
  }
  if($total<=0||$total>100000)fail('Invalid order amount');
  $customerName=val($b['customer_name']??'',60);if(mb_strlen($customerName)<2)fail('Please enter your name so we can call you');
  $phone=preg_replace('/\D+/','',val($b['customer_phone']??'',20));if(strlen($phone)===12&&str_starts_with($phone,'91'))$phone=substr($phone,2);
  if($phone!==''&&!preg_match('/^[6-9]\d{9}$/',$phone))fail('Enter a valid 10-digit mobile number, or leave it empty');
  $customerPhone=$phone===''?null:$phone;
  $track=bin2hex(random_bytes(32));$note=val($b['note']??'',500);
  try {
   db()->beginTransaction();
   // Daily token: 1, 2, 3… per restaurant, restarting each day. The counter row lock keeps numbers unique under load.
   $day=date('Y-m-d');
   execq('INSERT INTO token_counters(restaurant_id,day,last_no) VALUES(?,?,LAST_INSERT_ID(1)) ON DUPLICATE KEY UPDATE last_no=LAST_INSERT_ID(last_no+1)',[$r['id'],$day]);
   $tokenNo=(int)one('SELECT LAST_INSERT_ID() AS n')['n'];
   execq('INSERT INTO orders(restaurant_id,table_no,token_no,token_day,total,note,customer_name,customer_phone,client_request_id,tracking_key,ip_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?)',[$r['id'],$table,$tokenNo,$day,round($total,2),$note,$customerName,$customerPhone,$token,$track,iphash()]);
   $oid=(int)db()->lastInsertId();
   foreach($prepared as $p)execq('INSERT INTO order_items(order_id,item_id,dish_name,variant_name,addons_json,quantity,unit_price,line_total) VALUES(?,?,?,?,?,?,?,?)',[$oid,$p['item_id'],$p['name'],$p['variant'],json_encode($p['addons'],JSON_UNESCAPED_UNICODE),$p['quantity'],$p['unit'],$p['line']]);
   db()->commit();
  } catch(PDOException $e){if(db()->inTransaction())db()->rollBack();if($e->getCode()==='23000'){$existing=one('SELECT id,tracking_key,table_no,token_no,total,status FROM orders WHERE restaurant_id=? AND client_request_id=?',[$r['id'],$token]);if($existing)respond(['order'=>$existing,'duplicate'=>true]);}throw $e;}
  respond(['order'=>['id'=>$oid,'tracking_key'=>$track,'table_no'=>$table,'token_no'=>$tokenNo,'status'=>'new','total'=>round($total,2)]],201);
 }
 if($route==='public/status'){
  $id=(int)($_GET['id']??0);$key=val($_GET['key']??'',64);
  if(!preg_match('/^[a-f0-9]{64}$/D',$key))fail('Invalid tracking link',403);
  $o=one('SELECT id,table_no,token_no,status,call_count,called_at,total,created_at,updated_at FROM orders WHERE id=? AND tracking_key=?',[$id,$key]);if(!$o)fail('Order not found',404);respond(['order'=>$o]);
 }
 // All routes below require authentication.
 if(str_starts_with($route,'owner/')){$a=requireRole('owner','staff');$restaurantId=rid($a);}
 if($route==='owner/switch'){
  mutation();$b=body();$id=(int)($b['restaurant_id']??0);if(!in_array($id,outletIds($a),true))fail('You do not have access to this restaurant',403);
  $_SESSION['rid']=$id;respond(['actor'=>actorInfo()]);
 }
 if($route==='owner/overview'){
  $restaurant=one('SELECT * FROM restaurants WHERE id=?',[$restaurantId]);
  $stats=one('SELECT COUNT(*) AS today_orders,COALESCE(SUM(CASE WHEN status NOT IN ("rejected") THEN total ELSE 0 END),0) AS order_value, SUM(CASE WHEN status IN ("new","accepted","preparing","ready") THEN 1 ELSE 0 END) AS live_orders FROM orders WHERE restaurant_id=? AND DATE(created_at)=CURDATE()',[$restaurantId]);
  respond(['restaurant'=>$restaurant,'stats'=>$stats]);
 }
 if($route==='owner/billing'){
  $r=one('SELECT id,name,phone,status,subscription_ends,monthly_fee,setup_fee_paid FROM restaurants WHERE id=?',[$restaurantId]);
  $mandate=one('SELECT id,mode,amount,cycles_total,cycles_done,next_debit_date,predebit_debit_on,attempts,status,created_at FROM autopay_mandates WHERE restaurant_id=? ORDER BY id DESC LIMIT 1',[$restaurantId]);
  $payments=rows('SELECT amount,paid_on,method,reference_no,note FROM subscription_payments WHERE restaurant_id=? ORDER BY paid_on DESC,id DESC LIMIT 24',[$restaurantId]);
  respond(['restaurant'=>$r,'mandate'=>$mandate,'payments'=>$payments,'quotes'=>['monthly'=>billingQuote($r,'monthly'),'autopay6'=>billingQuote($r,'autopay6')],'autopay_months'=>PLAN_AUTOPAY_MONTHS,'payu_ready'=>payuReady(),'can_pay'=>$a['role']==='owner']);
 }
 if($route==='owner/billing/checkout'){
  mutation();if($a['role']!=='owner')fail('Only the restaurant owner can pay the subscription',403);
  $b=body();$plan=val($b['plan']??'',10);if(!in_array($plan,['monthly','autopay6'],true))fail('Choose a plan');
  $phone=preg_replace('/\D+/','',val($b['phone']??'',20));if(strlen($phone)===12&&str_starts_with($phone,'91'))$phone=substr($phone,2);
  if(!preg_match('/^[6-9]\d{9}$/',$phone))fail('Enter a valid 10-digit mobile number for PayU');
  if(!payuReady())fail('Online payment is not set up yet. Contact TableBloom support.',503);
  if($plan==='autopay6'&&one('SELECT id FROM autopay_mandates WHERE restaurant_id=? AND status="active"',[$restaurantId]))fail('6-month autopay is already active for this restaurant');
  $r=one('SELECT * FROM restaurants WHERE id=?',[$restaurantId]);
  audit('subscription.checkout',$restaurantId,$plan);respond(payuCheckout($r,$a,$plan,$phone));
 }
 if($route==='owner/billing/stop-autopay'){
  mutation();if($a['role']!=='owner')fail('Only the restaurant owner can change autopay',403);
  if(!execq('UPDATE autopay_mandates SET status="cancelled" WHERE restaurant_id=? AND status="active"',[$restaurantId]))fail('No active autopay');
  execq('UPDATE restaurants SET monthly_fee=? WHERE id=?',[monthlyFee($restaurantId),$restaurantId]);audit('subscription.autopay.stop',$restaurantId);respond(['ok'=>true]);
 }
 if($route==='owner/menu'){
  $cats=rows('SELECT * FROM categories WHERE restaurant_id=? ORDER BY sort_order,id',[$restaurantId]);
  $items=rows('SELECT * FROM menu_items WHERE restaurant_id=? AND deleted_at IS NULL ORDER BY sort_order,id DESC',[$restaurantId]);
  foreach($items as &$it){$it['options']=json_decode($it['options_json']??'[]',true)?:[];$it['addons']=json_decode($it['addons_json']??'[]',true)?:[];unset($it['options_json'],$it['addons_json']);}
  respond(['categories'=>$cats,'items'=>$items]);
 }
 if($route==='owner/category'){
  mutation();if($a['role']==='staff')fail('Owner permission required',403);$b=body();$name=val($b['name']??'',100);if(!$name)fail('Category name required');
  execq('INSERT IGNORE INTO categories(restaurant_id,name) VALUES(?,?)',[$restaurantId,$name]);audit('category.save',$restaurantId,$name);respond(['ok'=>true]);
 }
 if($route==='owner/menu/save'){
  mutation();if($a['role']==='staff')fail('Owner permission required',403);$b=body();$id=(int)($b['id']??0);$name=val($b['name']??'',160);if(!$name)fail('Dish name required');
  $cat=(int)($b['category_id']??0);if($cat&&!one('SELECT id FROM categories WHERE restaurant_id=? AND id=?',[$restaurantId,$cat]))fail('Invalid category');
  $options=jsonOptions($b['options']??[]);$addons=jsonOptions($b['addons']??[]);$base=num($b['base_price']??0,0,50000);if($options==='[]'&&$base<=0)fail('Price must be greater than zero');
  $type=val($b['food_type']??'unspecified',20);if(!in_array($type,['veg','nonveg','egg','unspecified'],true))fail('Invalid food type');
  $img=val($b['image_url']??'',800);if(!imagePath($img))fail('Only uploaded or Wikimedia Commons photos are allowed');
  $data=[$cat?:null,$name,val($b['description']??'',500),$type,$base,$options,$addons,$img?:null,val($b['image_credit']??'',500)?:null,val($b['image_license']??'',100)?:null,val($b['image_source_url']??'',800)?:null,(int)!empty($b['available'])];
  if($id){if(!one('SELECT id FROM menu_items WHERE restaurant_id=? AND id=? AND deleted_at IS NULL',[$restaurantId,$id]))fail('Item not found',404);
   execq('UPDATE menu_items SET category_id=?,name=?,description=?,food_type=?,base_price=?,options_json=?,addons_json=?,image_url=?,image_credit=?,image_license=?,image_source_url=?,available=? WHERE id=? AND restaurant_id=?',[...$data,$id,$restaurantId]);
  }else{execq('INSERT INTO menu_items(restaurant_id,category_id,name,description,food_type,base_price,options_json,addons_json,image_url,image_credit,image_license,image_source_url,available) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',[$restaurantId,...$data]);$id=(int)db()->lastInsertId();}
  audit('menu.save',$restaurantId,(string)$id);respond(['ok'=>true,'id'=>$id]);
 }
 if($route==='owner/menu/delete'){
  mutation();if($a['role']==='staff')fail('Owner permission required',403);$id=(int)(body()['id']??0);
  execq('UPDATE menu_items SET deleted_at=NOW() WHERE id=? AND restaurant_id=?',[$id,$restaurantId]);audit('menu.delete',$restaurantId,(string)$id);respond(['ok'=>true]);
 }
 if($route==='owner/menu/toggle'){
  mutation();if($a['role']==='staff')fail('Owner permission required',403);$id=(int)(body()['id']??0);
  execq('UPDATE menu_items SET available=1-available WHERE id=? AND restaurant_id=? AND deleted_at IS NULL',[$id,$restaurantId]);respond(['ok'=>true]);
 }
 if($route==='owner/orders'){
  $range=val($_GET['range']??'live',10);$scope=($_GET['scope']??'')==='all'?outletIds($a):[$restaurantId];
  $where='o.restaurant_id IN ('.placeholders($scope).')';$p=$scope;
  if($range==='live'){$where.=' AND o.status IN ("new","accepted","preparing","ready")';}
  elseif($range==='today'){$where.=' AND o.created_at>=CURDATE()';}
  elseif($range==='past'){$where.=' AND o.created_at>=DATE_SUB(NOW(), INTERVAL 60 DAY)';}
  else fail('Invalid order range');
  $out=rows("SELECT o.id,o.restaurant_id,r.name AS restaurant_name,o.table_no,o.token_no,o.status,o.call_count,o.called_at,o.total,o.note,o.customer_name,o.customer_phone,o.created_at,o.updated_at FROM orders o JOIN restaurants r ON r.id=o.restaurant_id WHERE $where ORDER BY o.created_at DESC,o.id DESC LIMIT 250",$p);
  foreach($out as &$o)$o=orderRow($o);respond(['orders'=>$out]);
 }
 if($route==='owner/order/status'){
  mutation();$b=body();$id=(int)($b['id']??0);$target=val($b['status']??'',20);
  $allowed=['new'=>['accepted','rejected'],'accepted'=>['preparing','rejected'],'preparing'=>['ready'],'ready'=>['served'],'served'=>[],'rejected'=>[]];
  $order=outletOrder($a,$id);if(!$order)fail('Order not found',404);
  if(!in_array($target,$allowed[$order['status']]??[],true))fail('Invalid status change');
  $n=execq('UPDATE orders SET status=? WHERE id=? AND restaurant_id=? AND status=?',[$target,$id,$order['restaurant_id'],$order['status']]);if(!$n)fail('Order changed elsewhere. Refresh and retry.',409);
  audit('order.status',(int)$order['restaurant_id'],"$id -> $target");respond(['ok'=>true]);
 }
 if($route==='owner/order/call'){
  // "Call token": marks the order ready and makes the customer's phone ring. Calling again rings again.
  mutation();$b=body();$id=(int)($b['id']??0);$order=outletOrder($a,$id);if(!$order)fail('Order not found',404);
  if(!in_array($order['status'],['accepted','preparing','ready'],true))fail('Accept the order before calling its token');
  execq('UPDATE orders SET status="ready",call_count=LEAST(call_count+1,250),called_at=NOW() WHERE id=?',[$id]);
  audit('order.call',(int)$order['restaurant_id'],(string)$id);respond(['ok'=>true]);
 }
 if($route==='owner/analytics'){
  $month=val($_GET['month']??date('Y-m'),7);if(!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/D',$month))fail('Invalid month');
  $from=$month.'-01';$until=date('Y-m-d',strtotime($from.' +1 month'));
  $summary=one('SELECT COUNT(*) AS orders, SUM(status="served") AS served, SUM(status="rejected") AS rejected, COALESCE(SUM(CASE WHEN status="served" THEN total ELSE 0 END),0) AS served_order_value, COALESCE(SUM(CASE WHEN status<>"rejected" THEN total ELSE 0 END),0) AS nonrejected_value FROM orders WHERE restaurant_id=? AND created_at>=? AND created_at<?',[$restaurantId,$from,$until]);
  $days=rows('SELECT DATE(created_at) AS day,COUNT(*) AS orders,COALESCE(SUM(CASE WHEN status="served" THEN total ELSE 0 END),0) AS value FROM orders WHERE restaurant_id=? AND created_at>=? AND created_at<? GROUP BY DATE(created_at) ORDER BY day',[$restaurantId,$from,$until]);
  $top=rows('SELECT oi.dish_name,SUM(oi.quantity) AS qty FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.restaurant_id=? AND o.created_at>=? AND o.created_at<? AND o.status<>"rejected" GROUP BY oi.dish_name ORDER BY qty DESC LIMIT 8',[$restaurantId,$from,$until]);
  $peak=rows('SELECT HOUR(created_at) AS hour,COUNT(*) AS orders FROM orders WHERE restaurant_id=? AND created_at>=? AND created_at<? GROUP BY HOUR(created_at) ORDER BY orders DESC LIMIT 5',[$restaurantId,$from,$until]);
  respond(['summary'=>$summary,'days'=>$days,'top'=>$top,'peak'=>$peak]);
 }
 if($route==='owner/password'){
  mutation();$b=body();$old=(string)($b['current_password']??'');$new=(string)($b['new_password']??'');
  if(strlen($new)<12)fail('Use a new password of at least 12 characters');
  $u=one('SELECT password_hash FROM users WHERE id=?',[$a['id']]);
  if(!$u||!password_verify($old,$u['password_hash']))fail('Current password is incorrect',403);
  execq('UPDATE users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$a['id']]);audit('account.password.change',$restaurantId);respond(['ok'=>true]);
 }
 if($route==='owner/settings')respond(['restaurant'=>one('SELECT * FROM restaurants WHERE id=?',[$restaurantId])]);
 if($route==='owner/settings/save'){
  mutation();if($a['role']==='staff')fail('Owner permission required',403);$b=body();$r=one('SELECT * FROM restaurants WHERE id=?',[$restaurantId]);
  $tables=(int)($b['table_count']??20);if($tables<1||$tables>500)fail('Table count must be 1–500');
  $mode=val($b['qr_mode']??'common',10);if(!in_array($mode,['common','table','counter'],true))fail('Invalid QR mode');
  $theme=val($b['theme_mode']??'light',10);if(!in_array($theme,['light','dark'],true))fail('Invalid theme');
  $layout=val($b['menu_layout']??'list',10);if(!in_array($layout,['list','grid'],true))fail('Invalid layout');
  $logo=val($b['logo_url']??'',500);$cover=val($b['cover_url']??'',500);if(!imagePath($logo)||!imagePath($cover))fail('Invalid image URL');
  execq('UPDATE restaurants SET name=?,tagline=?,welcome_message=?,logo_url=?,cover_url=?,primary_color=?,accent_color=?,theme_mode=?,menu_layout=?,phone=?,address=?,opening_hours=?,table_count=?,qr_mode=?,ordering_enabled=? WHERE id=?',[
   val($b['name']??$r['name'],160),val($b['tagline']??'',250),val($b['welcome_message']??'',500),$logo?:null,$cover?:null,
   hexcolor($b['primary_color']??'','#186953'),hexcolor($b['accent_color']??'','#E4B866'),$theme,$layout,val($b['phone']??'',40),val($b['address']??'',400),val($b['opening_hours']??'',180),$tables,$mode,(int)!empty($b['ordering_enabled']),$restaurantId]);
  audit('settings.save',$restaurantId);respond(['ok'=>true]);
 }
 if($route==='owner/upload'){
  mutation();if($a['role']==='staff')fail('Owner permission required',403);
  if(!isset($_FILES['file'])||$_FILES['file']['error']!==UPLOAD_ERR_OK)fail('Upload failed');$f=$_FILES['file'];
  if($f['size']>2500000)fail('Image maximum size is 2.5 MB');
  $kind=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
  if(!isset($extensions[$kind])||!getimagesize($f['tmp_name']))fail('Only valid JPG, PNG and WebP images allowed');
  $path='/uploads/'.bin2hex(random_bytes(16)).'.'.$extensions[$kind];
  if(!move_uploaded_file($f['tmp_name'],dirname(__DIR__).$path))fail('Could not save image',500);
  respond(['url'=>$path]);
 }
 if($route==='owner/photos/suggest'){
  requireRole('owner','staff');$term=val($_GET['dish']??'',80);if(strlen($term)<3)respond(['photos'=>[]]);
  if(!function_exists('curl_init'))respond(['photos'=>[],'message'=>'PHP cURL is unavailable. Use your own upload.']);
  $query=http_build_query(['action'=>'query','generator'=>'search','gsrsearch'=>$term.' food','gsrnamespace'=>6,'gsrlimit'=>12,'prop'=>'imageinfo','iiprop'=>'url|extmetadata','iiurlwidth'=>480,'format'=>'json','formatversion'=>2]);
  $ch=curl_init('https://commons.wikimedia.org/w/api.php?'.$query);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_USERAGENT=>'RestaurantQRCodeSaaS/1.0 (photo suggestion; attribution preserved)']);
  $raw=curl_exec($ch);curl_close($ch);if(!$raw)respond(['photos'=>[],'message'=>'Photo suggestions temporarily unavailable. Upload an image instead.']);
  $payload=json_decode($raw,true);$photos=[];
  foreach(($payload['query']['pages']??[]) as $page){$info=$page['imageinfo'][0]??[];$meta=$info['extmetadata']??[];$lic=strip_tags($meta['LicenseShortName']['value']??'');
   if(!preg_match('/^(CC0|CC BY(?:-SA)?(?:\s|$)|CC-BY(?:-SA)?(?:\s|$)|Public domain|PD-)/i',$lic))continue;
   $url=$info['thumburl']??'';$source=$info['descriptionurl']??'';
   if(!str_starts_with($url,'https://upload.wikimedia.org/')||!str_starts_with($source,'https://commons.wikimedia.org/'))continue;
   $artist=strip_tags($meta['Artist']['value']??'Wikimedia Commons contributor');$artist=html_entity_decode($artist,ENT_QUOTES,'UTF-8');
   $photos[]=['image_url'=>$url,'credit'=>mb_substr($artist,0,300),'license'=>$lic,'source_url'=>$source,'title'=>$page['title']??''];
  }
  respond(['photos'=>array_slice($photos,0,6),'message'=>count($photos)?'Check photo relevance before selecting.':'No suitable licensed photos found; use your own upload.']);
 }
 if(str_starts_with($route,'admin/'))$admin=requireRole('super');
 if($route==='admin/overview'){
  $stats=one('SELECT COUNT(*) AS restaurants,SUM(status="active" AND subscription_ends>=CURDATE()) AS active,SUM(subscription_ends<CURDATE()) AS expired,COALESCE(SUM(CASE WHEN status="active" AND subscription_ends>=CURDATE() THEN monthly_fee ELSE 0 END),0) AS mrr FROM restaurants WHERE deleted_at IS NULL');
  $orders=one('SELECT COUNT(*) AS all_orders,SUM(created_at>=DATE_FORMAT(NOW(),"%Y-%m-01")) AS this_month FROM orders');
  $collected=one('SELECT COALESCE(SUM(amount),0) AS amount FROM subscription_payments WHERE paid_on>=DATE_FORMAT(CURDATE(),"%Y-%m-01")');
  $growth=rows('SELECT DATE_FORMAT(created_at,"%Y-%m") AS month,COUNT(*) AS restaurants FROM restaurants WHERE created_at>=DATE_SUB(NOW(),INTERVAL 12 MONTH) GROUP BY DATE_FORMAT(created_at,"%Y-%m") ORDER BY month');
  respond(['stats'=>$stats,'orders'=>$orders,'collected'=>$collected,'growth'=>$growth]);
 }
 if($route==='admin/restaurants'){
  $list=rows('SELECT r.*, (SELECT COUNT(*) FROM orders o WHERE o.restaurant_id=r.id AND o.created_at>=DATE_FORMAT(NOW(),"%Y-%m-01")) AS month_orders,(SELECT COUNT(*) FROM menu_items m WHERE m.restaurant_id=r.id AND m.deleted_at IS NULL) AS menu_count,(SELECT email FROM users u WHERE u.restaurant_id=r.id AND u.role="owner" ORDER BY id LIMIT 1) AS owner_email,(SELECT CONCAT(m.status," ",m.cycles_done,"/",m.cycles_total) FROM autopay_mandates m WHERE m.restaurant_id=r.id ORDER BY m.id DESC LIMIT 1) AS autopay,(SELECT GROUP_CONCAT(CONCAT(u.id,":",u.email) ORDER BY u.email SEPARATOR ",") FROM restaurant_access ra JOIN users u ON u.id=ra.user_id WHERE ra.restaurant_id=r.id AND u.role="owner" AND NOT (u.restaurant_id<=>r.id)) AS linked_owners,(SELECT COUNT(*) FROM restaurant_access ra WHERE ra.user_id=(SELECT u.id FROM users u WHERE u.restaurant_id=r.id AND u.role="owner" ORDER BY u.id LIMIT 1)) AS owner_outlets FROM restaurants r ORDER BY r.created_at DESC LIMIT 1000');respond(['restaurants'=>$list]);
 }
 if($route==='admin/restaurants/create'){
  mutation();$b=body();$name=val($b['name']??'',160);$slug=strtolower(val($b['slug']??'',90));$email=strtolower(val($b['email']??'',190));$password=(string)($b['password']??'');
  if(!$name||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug))fail('Name and valid URL slug required');
  if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12)fail('Valid email and password of at least 12 characters required');
  $tables=(int)($b['table_count']??20);if($tables<1||$tables>500)fail('1–500 tables allowed');
  $fee=num($b['monthly_fee']??599,0,100000);
  db()->beginTransaction();
  try {
   execq('INSERT INTO restaurants(slug,name,table_count,monthly_fee,subscription_ends) VALUES(?,?,?,?,DATE_ADD(CURDATE(),INTERVAL 7 DAY))',[$slug,$name,$tables,$fee]);$id=(int)db()->lastInsertId();
   execq('INSERT INTO users(restaurant_id,role,full_name,email,password_hash) VALUES(?,?,?,?,?)',[$id,'owner',val($b['owner_name']??$name,120),$email,password_hash($password,PASSWORD_DEFAULT)]);
   execq('INSERT INTO restaurant_access(user_id,restaurant_id) VALUES(?,?)',[(int)db()->lastInsertId(),$id]);
   foreach(['Starters','Main Course','Breads & Rice','Beverages','Desserts'] as $pos=>$category)execq('INSERT INTO categories(restaurant_id,name,sort_order) VALUES(?,?,?)',[$id,$category,$pos]);
   db()->commit();
  }catch(Throwable $e){db()->rollBack();throw $e;}
  audit('restaurant.create',$id,$slug);respond(['id'=>$id,'slug'=>$slug,'ok'=>true]);
 }
 if($route==='admin/restaurants/update'){
  mutation();$b=body();$id=(int)($b['id']??0);if(!one('SELECT id FROM restaurants WHERE id=?',[$id]))fail('Not found',404);
  $status=val($b['status']??'',15);if(!in_array($status,['trial','active','suspended'],true))fail('Invalid status');
  $date=val($b['subscription_ends']??'',10);if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4)))fail('Invalid date');
  execq('UPDATE restaurants SET status=?,subscription_ends=?,monthly_fee=?,setup_fee_paid=? WHERE id=?',[$status,$date,num($b['monthly_fee']??599,0,100000),empty($b['setup_fee_paid'])?0:1,$id]);audit('subscription.update',$id,$status.' to '.$date);respond(['ok'=>true]);
 }
 if($route==='admin/restaurants/link'){
  // Gives an existing owner login access to this restaurant too (multi-outlet owner).
  mutation();$b=body();$id=(int)($b['restaurant_id']??0);$email=strtolower(val($b['email']??'',190));
  if(!one('SELECT id FROM restaurants WHERE id=?',[$id]))fail('Not found',404);
  $u=one('SELECT id FROM users WHERE email=? AND role="owner"',[$email]);if(!$u)fail('No restaurant owner login uses that email');
  execq('INSERT IGNORE INTO restaurant_access(user_id,restaurant_id) VALUES(?,?)',[$u['id'],$id]);audit('owner.outlet.link',$id,$email);respond(['ok'=>true]);
 }
 if($route==='admin/restaurants/unlink'){
  mutation();$b=body();$id=(int)($b['restaurant_id']??0);$uid=(int)($b['user_id']??0);
  if(one('SELECT id FROM users WHERE id=? AND restaurant_id=?',[$uid,$id]))fail('This is the owner\'s own restaurant and cannot be unlinked');
  execq('DELETE FROM restaurant_access WHERE user_id=? AND restaurant_id=?',[$uid,$id]);audit('owner.outlet.unlink',$id,(string)$uid);respond(['ok'=>true]);
 }
 if($route==='admin/restaurants/stop'){
  // Stop = suspend: customers cannot order and the owner/staff are locked out at once. Resume reopens it.
  mutation();$b=body();$id=(int)($b['id']??0);$stop=!empty($b['stop']);
  if(!one('SELECT id FROM restaurants WHERE id=? AND deleted_at IS NULL',[$id]))fail('Not found',404);
  execq('UPDATE restaurants SET status=? WHERE id=?',[$stop?'suspended':'active',$id]);audit($stop?'restaurant.stop':'restaurant.resume',$id);respond(['ok'=>true]);
 }
 if($route==='admin/restaurants/export'){
  // Full data backup for one restaurant, to keep before deleting. Secrets (password hashes, tracking keys, IP hashes) are left out.
  $id=(int)($_GET['id']??0);$r=one('SELECT * FROM restaurants WHERE id=?',[$id]);if(!$r)fail('Not found',404);
  $orders=rows('SELECT * FROM orders WHERE restaurant_id=? ORDER BY id',[$id]);foreach($orders as &$o)unset($o['tracking_key'],$o['ip_hash']);unset($o);
  audit('restaurant.export',$id);
  respond(['exported_at'=>date('c'),'restaurant'=>$r,
   'logins'=>rows('SELECT id,restaurant_id,role,full_name,email,active,created_at FROM users WHERE restaurant_id=? OR id IN (SELECT user_id FROM restaurant_access WHERE restaurant_id=?)',[$id,$id]),
   'categories'=>rows('SELECT * FROM categories WHERE restaurant_id=? ORDER BY sort_order,id',[$id]),
   'menu_items'=>rows('SELECT * FROM menu_items WHERE restaurant_id=? ORDER BY id',[$id]),
   'orders'=>$orders,
   'order_items'=>rows('SELECT oi.* FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.restaurant_id=? ORDER BY oi.id',[$id]),
   'subscription_payments'=>rows('SELECT * FROM subscription_payments WHERE restaurant_id=? ORDER BY id',[$id]),
   'payu_transactions'=>rows('SELECT * FROM payu_transactions WHERE restaurant_id=? ORDER BY id',[$id]),
   'autopay_mandates'=>rows('SELECT * FROM autopay_mandates WHERE restaurant_id=? ORDER BY id',[$id])]);
 }
 if($route==='admin/restaurants/delete'){
  // Soft delete: hidden from customers and owners, autopay stopped, but every record is kept and can be restored.
  mutation();$b=body();$id=(int)($b['id']??0);
  if(!execq('UPDATE restaurants SET deleted_at=NOW() WHERE id=? AND deleted_at IS NULL',[$id]))fail('Not found or already deleted',404);
  execq('UPDATE autopay_mandates SET status="cancelled" WHERE restaurant_id=? AND status="active"',[$id]);
  audit('restaurant.delete',$id);respond(['ok'=>true]);
 }
 if($route==='admin/restaurants/restore'){
  mutation();$b=body();$id=(int)($b['id']??0);
  if(!execq('UPDATE restaurants SET deleted_at=NULL WHERE id=? AND deleted_at IS NOT NULL',[$id]))fail('Not found or not deleted',404);
  audit('restaurant.restore',$id);respond(['ok'=>true]);
 }
 if($route==='admin/restaurants/purge'){
  // Permanent: removes the restaurant, its menu, orders, payments and its own owner/staff logins (database cascades), plus its uploaded photos.
  mutation();$b=body();$id=(int)($b['id']??0);$r=one('SELECT * FROM restaurants WHERE id=? AND deleted_at IS NOT NULL',[$id]);
  if(!$r)fail('Only a deleted restaurant can be removed permanently. Delete it first.');
  if(val($b['confirm_slug']??'',90)!==$r['slug'])fail('Type the restaurant URL slug exactly to confirm');
  $files=array_column(rows('SELECT image_url FROM menu_items WHERE restaurant_id=?',[$id]),'image_url');$files[]=$r['logo_url'];$files[]=$r['cover_url'];
  execq('DELETE FROM restaurants WHERE id=?',[$id]);
  foreach(array_unique(array_filter($files)) as $f)if(preg_match('~^/uploads/[a-f0-9]{32}\.(jpg|jpeg|png|webp)$~D',(string)$f)&&is_file(dirname(__DIR__).$f))unlink(dirname(__DIR__).$f);
  audit('restaurant.purge',null,$r['slug'].' · '.$r['name']);respond(['ok'=>true]);
 }
 if($route==='admin/payment'){
  mutation();$b=body();$id=(int)($b['restaurant_id']??0);if(!one('SELECT id FROM restaurants WHERE id=?',[$id]))fail('Invalid restaurant');
  $amount=num($b['amount']??0,1,100000);$date=val($b['paid_on']??date('Y-m-d'),10);if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))fail('Invalid date');
  execq('INSERT INTO subscription_payments(restaurant_id,amount,paid_on,method,reference_no,note) VALUES(?,?,?,?,?,?)',[$id,$amount,$date,val($b['method']??'UPI',50),val($b['reference_no']??'',120),val($b['note']??'',300)]);audit('subscription.payment',$id,(string)$amount);respond(['ok'=>true]);
 }
 if($route==='admin/password'){
  mutation();$b=body();$id=(int)($b['restaurant_id']??0);$pw=(string)($b['password']??'');if(strlen($pw)<12)fail('Use at least 12 characters');
  execq('UPDATE users SET password_hash=? WHERE restaurant_id=? AND role="owner"',[password_hash($pw,PASSWORD_DEFAULT),$id]);audit('owner.password.reset',$id);respond(['ok'=>true]);
 }
 fail('Unknown API route',404);
} catch(PDOException $e){error_log('Restaurant SaaS DB failure: '.$e->getMessage());fail('Database request failed. Check server logs.',500);}
catch(Throwable $e){error_log('Restaurant SaaS error: '.$e->getMessage());fail('Server request failed. Check server logs.',500);}
