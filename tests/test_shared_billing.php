<?php
declare(strict_types=1);
// Disposable local database only. No payment gateway is contacted.
$config=getenv('TABLEBLOOM_TEST_CONFIG');
if(!$config){echo "SKIP: set TABLEBLOOM_TEST_CONFIG\n";exit;}
$c=require $config;
require __DIR__.'/../public/api/db.php';
require __DIR__.'/../public/api/payu.php';
function check(bool $ok,string $why):void {if(!$ok)throw new RuntimeException($why);}
$key='shared-'.bin2hex(random_bytes(8));$ids=[];
try{
 execq('INSERT INTO restaurants(slug,name,monthly_fee,status,subscription_ends) VALUES(?,?,599,"active",DATE_ADD(CURDATE(),INTERVAL 1 MONTH))',[$key,'Shared billing QA']);
 $root=(int)db()->lastInsertId();$ids[]=$root;
 execq('INSERT INTO users(restaurant_id,role,full_name,email,password_hash) VALUES(?,"owner",?,?,?)',[$root,'Owner',$key.'@example.test',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
 $uid=(int)db()->lastInsertId();execq('INSERT INTO restaurant_access(user_id,restaurant_id) VALUES(?,?)',[$uid,$root]);
 $home=one('SELECT * FROM restaurants WHERE id=?',[$root]);
 check(billingQuote($home,'monthly')['total']===949.0,'One restaurant: base price plus one setup fee');
 for($n=2;$n<=4;$n++){
  // Legacy outlets had their own price, setup flag and expiry. They must now share the home plan too.
  execq('INSERT INTO restaurants(slug,name,monthly_fee,subscription_ends) VALUES(?,?,599,"2000-01-01")',[$key.'-'.$n,'Outlet '.$n]);
  $child=(int)db()->lastInsertId();$ids[]=$child;
  execq('INSERT INTO restaurant_access(user_id,restaurant_id) VALUES(?,?)',[$uid,$child]);
  execq('INSERT INTO audit_logs(user_id,restaurant_id,action) VALUES(?,?,"owner.outlet.create")',[$uid,$child]);
  $r=one('SELECT * FROM restaurants WHERE id=?',[$child]);$effective=restaurantSubscription($r);
  check(billingRestaurantId($child)===$root,'Outlet must resolve to its creator home account');
  check($effective['subscription_ends']===$home['subscription_ends'],'Legacy child expiry must not independently stop orders');
  check($effective['monthly_fee']===0&&$effective['setup_fee_paid']===1,'No per-outlet base or setup fee');
  $q=billingQuote($r,'monthly');
  check($q['extra_fee']===max(0,$n-2)*250.0,'Second free, third +250, fourth +500');
  check($q['total']===949.0+max(0,$n-2)*250.0,'Shared checkout must have only one base and setup fee');
  check(upiQuote($r,'upfront6')['total']===(planPrice('autopay6')+max(0,$n-2)*250)*6+350,'UPI upfront uses the same outlet pricing');
 }
 execq('UPDATE restaurants SET deleted_at=NOW() WHERE id=?',[$child]);
 check(extraOutlets($root)===1,'Deleted outlets must not be charged');
 execq('UPDATE restaurants SET deleted_at=NULL,status="suspended" WHERE id=?',[$child]);
 check(extraOutlets($root)===1,'Stopped outlets must not be charged');
 execq('UPDATE restaurants SET status="active" WHERE id=?',[$child]);
 // Old in-flight child payments retain their amount and extend the shared account.
 execq('INSERT INTO payu_transactions(restaurant_id,txnid,kind,plan,amount,setup_fee) VALUES(?,?,"checkout","autopay6",1099,500)',[$child,$key]);
 $t=one('SELECT * FROM payu_transactions WHERE id=?',[(int)db()->lastInsertId()]);
 payuApplySuccess($t,'test-shared-payment','UPI');
 $paid=billingRestaurant($root);
 check($paid['subscription_ends']>$home['subscription_ends'],'Payment must extend the shared plan');
 check(billingQuote($paid,'monthly')['total']===1099.0,'Paid account: 599 base + 500 for two extra outlets, no repeated setup');
 check((float)one('SELECT amount FROM subscription_payments WHERE restaurant_id=?',[$root])['amount']===1099.0,'Preserve old payment amount');
 check(!one('SELECT id FROM autopay_mandates WHERE restaurant_id=?',[$child]),'Do not establish a duplicate child mandate');
 payuApplySuccess($t,'test-shared-payment','UPI');
 check(billingRestaurant($root)['subscription_ends']===$paid['subscription_ends'],'Settlement remains idempotent');
 echo "PASS: second free, extra outlets 250, shared renewal/setup, old outlet compatibility, deleted/stopped outlets and payment snapshots\n";
}finally{
 foreach(array_reverse($ids) as $id){execq('DELETE FROM audit_logs WHERE restaurant_id=?',[$id]);execq('DELETE FROM restaurants WHERE id=?',[$id]);}
}
