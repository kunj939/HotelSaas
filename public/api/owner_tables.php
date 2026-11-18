<?php
declare(strict_types=1);
// Owner and staff routes for table bills and staff-entered orders (WhatsApp, phone, walk-in).
// Included from index.php after the owner login check, so $route, $a and $restaurantId are already set.

// Builds the printable bill for one table session. Prices come from order_items, saved when each order was placed,
// so a later menu price change never changes a past bill. Rejected orders are left out.
function billFor(int $restaurantId,array $s):array {
 $restaurant=one('SELECT name,address,phone,gstin,tax_percent FROM restaurants WHERE id=?',[$restaurantId])?:[];
 $orders=rows('SELECT id,token_no,token_day,source,status,created_at FROM orders WHERE table_session_id=? AND restaurant_id=? ORDER BY id',[(int)$s['id'],$restaurantId]);
 $live=[];
 foreach($orders as &$o){$o['id']=(int)$o['id'];$o['token_no']=(int)$o['token_no'];if($o['status']!=='rejected')$live[$o['id']]=true;}
 unset($o);
 $lines=[];$subtotal=0.0;
 if($orders){
  $ids=array_map(fn($o)=>(int)$o['id'],$orders);
  foreach(rows('SELECT order_id,dish_name,variant_name,addons_json,quantity,unit_price,line_total FROM order_items WHERE order_id IN ('.placeholders($ids).') ORDER BY id',$ids) as $it){
   if(!isset($live[(int)$it['order_id']]))continue;
   $extras=array_map(fn($x)=>(string)($x['name']??''),json_decode($it['addons_json']??'[]',true)?:[]);
   $label=$it['dish_name'].($it['variant_name']?' ('.$it['variant_name'].')':'');
   $key=$label.'|'.implode(',',$extras).'|'.(string)$it['unit_price'];
   if(!isset($lines[$key]))$lines[$key]=['name'=>$label,'extras'=>$extras,'unit'=>round((float)$it['unit_price'],2),'quantity'=>0,'amount'=>0.0];
   $lines[$key]['quantity']+=(int)$it['quantity'];
   $lines[$key]['amount']=round($lines[$key]['amount']+(float)$it['line_total'],2);
   $subtotal+=(float)$it['line_total'];
  }
 }
 $subtotal=round($subtotal,2);
 $tax=round($subtotal*(float)($restaurant['tax_percent']??0)/100,2);
 // A closed bill keeps the amounts it was closed with, even if the tax setting changes later.
 if($s['status']==='closed'){$subtotal=round((float)$s['subtotal'],2);$tax=round((float)$s['tax'],2);}
 return [
  'id'=>(int)$s['id'],'table_no'=>(int)$s['table_no'],'status'=>(string)$s['status'],
  'opened_at'=>$s['opened_at'],'closed_at'=>$s['closed_at'],
  'restaurant'=>['name'=>$restaurant['name']??'','address'=>$restaurant['address']??null,'phone'=>$restaurant['phone']??null,'gstin'=>$restaurant['gstin']??null],
  'lines'=>array_values($lines),'orders'=>$orders,
  'subtotal'=>$subtotal,'tax_percent'=>$subtotal>0?round($tax*100/$subtotal,2):0.0,'tax'=>$tax,'total'=>round($subtotal+$tax,2),
 ];
}

if($route==='owner/tables'){
 $r=one('SELECT id,table_count,qr_mode FROM restaurants WHERE id=?',[$restaurantId]);
 $open=[];
 foreach(rows('SELECT ts.id,ts.table_no,ts.opened_at,COUNT(o.id) AS orders,COALESCE(SUM(CASE WHEN o.status<>"rejected" THEN o.total ELSE 0 END),0) AS total,COALESCE(SUM(CASE WHEN o.status IN ("new","accepted","preparing","ready") THEN 1 ELSE 0 END),0) AS live,COALESCE(SUM(CASE WHEN o.status="new" THEN 1 ELSE 0 END),0) AS new_orders,MAX(o.created_at) AS last_order_at FROM table_sessions ts LEFT JOIN orders o ON o.table_session_id=ts.id WHERE ts.restaurant_id=? AND ts.status="open" GROUP BY ts.id,ts.table_no,ts.opened_at',[$restaurantId]) as $s){
  $open[(int)$s['table_no']]=['id'=>(int)$s['id'],'opened_at'=>$s['opened_at'],'orders'=>(int)$s['orders'],'total'=>round((float)$s['total'],2),'live'=>(int)$s['live'],'new_orders'=>(int)$s['new_orders'],'last_order_at'=>$s['last_order_at']];
 }
 $count=$r['qr_mode']==='counter'?0:(int)$r['table_count'];
 $tables=[];
 for($t=1;$t<=$count;$t++)$tables[]=['table_no'=>$t,'session'=>$open[$t]??null];
 // A table count lowered after a bill was opened must still show that bill.
 foreach($open as $t=>$session)if($t>$count)$tables[]=['table_no'=>$t,'session'=>$session];
 respond(['tables'=>$tables,'counter'=>$r['qr_mode']==='counter']);
}

if($route==='owner/table/bill'){
 $id=(int)($_GET['id']??0);$s=one('SELECT * FROM table_sessions WHERE id=? AND restaurant_id=?',[$id,$restaurantId]);
 if(!$s)fail('Table bill not found',404);
 respond(['bill'=>billFor($restaurantId,$s)]);
}

if($route==='owner/table/close'){
 mutation();$id=(int)(body()['id']??0);
 // Lock the bill first: an order being added at this moment waits, so the bill cannot miss it.
 db()->beginTransaction();
 $s=one('SELECT * FROM table_sessions WHERE id=? AND restaurant_id=? FOR UPDATE',[$id,$restaurantId]);
 $problem=null;
 if(!$s)$problem=['Table bill not found',404];
 elseif($s['status']!=='open')$problem=['This bill is already closed',409];
 else{
  $pending=(int)one('SELECT COUNT(*) AS n FROM orders WHERE table_session_id=? AND restaurant_id=? AND status IN ("new","accepted","preparing","ready")',[$id,$restaurantId])['n'];
  if($pending)$problem=["Serve or reject the $pending open order".($pending>1?'s':'').' before closing this bill',409];
 }
 if($problem){db()->rollBack();fail($problem[0],$problem[1]);}
 $bill=billFor($restaurantId,$s);
 execq('UPDATE table_sessions SET status="closed",closed_at=NOW(),closed_by=?,subtotal=?,tax=?,total=? WHERE id=?',[$a['id'],$bill['subtotal'],$bill['tax'],$bill['total'],$id]);
 db()->commit();
 audit('table.bill.close',$restaurantId,$id.' '.$bill['total']);
 respond(['ok'=>true,'bill'=>billFor($restaurantId,one('SELECT * FROM table_sessions WHERE id=?',[$id]))]);
}

if($route==='owner/order/manual'){
 // Staff enter a WhatsApp, phone or walk-in order. It is priced, tokenised and queued exactly like a guest QR order.
 mutation();$b=body();
 $r=one('SELECT * FROM restaurants WHERE id=? AND deleted_at IS NULL',[$restaurantId]);if(!$r)fail('Restaurant not found',404);
 // Staff can still take phone and WhatsApp orders while QR ordering is paused, but not after the subscription ends.
 if(!statusActive(array_merge($r,['ordering_enabled'=>1])))fail('Your subscription has ended or the restaurant is stopped. Renew to take orders.',403);
 $source=val($b['source']??'',10);if(!in_array($source,['whatsapp','phone','walkin'],true))fail('Choose where this order came from');
 $shop=['name'=>$r['name'],'slug'=>$r['slug']];
 $fulfilment=val($b['fulfilment']??'',10);if(!in_array($fulfilment,['dine_in','takeaway','delivery'],true))fail('Choose dine-in, takeaway or delivery');
 if($r['qr_mode']==='counter'&&$fulfilment==='dine_in')$fulfilment='takeaway';
 $table=0;
 if($fulfilment==='dine_in'){$table=(int)($b['table_no']??0);if($table<1||$table>(int)$r['table_count'])fail('Choose the table for a dine-in order');}
 $token=val($b['client_request_id']??'',80);if(!preg_match('/^[a-zA-Z0-9-]{16,80}$/D',$token))fail('Invalid order request');
 $existing=one('SELECT id,tracking_key,table_no,token_no,token_day,total,status FROM orders WHERE restaurant_id=? AND client_request_id=?',[$restaurantId,$token]);
 if($existing)respond(['order'=>$existing,'duplicate'=>true,'restaurant'=>$shop]);
 [$prepared,$total]=priceCart($r,$b['items']??[]);
 $name=val($b['customer_name']??'',60);
 $phone=preg_replace('/\D+/','',val($b['customer_phone']??'',20));if(strlen($phone)===12&&str_starts_with($phone,'91'))$phone=substr($phone,2);
 if($phone!==''&&!preg_match('/^[6-9]\d{9}$/',$phone))fail('Enter a valid 10-digit mobile number, or leave it empty');
 // Delivery needs someone to call and a full address the rider can find.
 $address=val($b['delivery_address']??'',400);
 if($fulfilment==='delivery'){
  if(mb_strlen($name)<2)fail('Enter the customer name for delivery');
  if($phone==='')fail('Enter the customer mobile number for delivery');
  if(mb_strlen($address)<10)fail('Enter the full delivery address (at least 10 characters)');
 }else{$address='';}
 try{
  $order=insertOrder($r,$table,$prepared,$total,['source'=>$source,'fulfilment'=>$fulfilment,'delivery_address'=>$address===''?null:$address,'note'=>val($b['note']??'',500),'customer_name'=>$name===''?null:$name,'customer_phone'=>$phone===''?null:$phone,'client_request_id'=>$token,'ip_hash'=>staffHash()]);
 }catch(PDOException $e){
  if($e->getCode()==='23000'){$existing=one('SELECT id,tracking_key,table_no,token_no,token_day,total,status FROM orders WHERE restaurant_id=? AND client_request_id=?',[$restaurantId,$token]);if($existing)respond(['order'=>$existing,'duplicate'=>true,'restaurant'=>$shop]);}
  throw $e;
 }
 audit('order.manual',$restaurantId,$source.' token '.$order['token_no']);
 respond(['ok'=>true,'order'=>$order,'restaurant'=>$shop,'fulfilment'=>$fulfilment],201);
}
