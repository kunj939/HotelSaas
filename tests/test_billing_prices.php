<?php
declare(strict_types=1);
// Only run with a disposable MySQL database. No payment gateway is contacted.
$config=getenv('TABLEBLOOM_TEST_CONFIG');
if(!$config){echo "SKIP: set TABLEBLOOM_TEST_CONFIG to a disposable site's config.local.php\n";exit;}
$c=require $config;
require __DIR__.'/../public/api/db.php';
require __DIR__.'/../public/api/payu.php';
function check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$slug='billing-qa-'.bin2hex(random_bytes(10));
execq('INSERT INTO restaurants(slug,name,monthly_fee,subscription_ends) VALUES(?,?,?,CURDATE())',[$slug,'Billing QA',799.50]);
$rid=(int)db()->lastInsertId();
try{
 $r=one('SELECT * FROM restaurants WHERE id=?',[$rid]);
 check(billingQuote($r,'monthly')['total']===1299.5,'Monthly quote must use the agreed rate plus setup');
 execq('INSERT INTO payu_transactions(restaurant_id,txnid,kind,plan,amount,setup_fee) VALUES(?,?,?,?,?,?)',[$rid,$slug,'checkout','autopay6',1199.25,500]);
 $t=one('SELECT * FROM payu_transactions WHERE id=?',[(int)db()->lastInsertId()]);
 payuApplySuccess($t,'test-payment','UPI');
 $r=one('SELECT * FROM restaurants WHERE id=?',[$rid]);
 check((float)$r['monthly_fee']===799.5,'Settlement must retain the admin rate');
 check(billingQuote($r,'monthly')['total']===799.5,'Setup must be paid only once');
 $m=one('SELECT * FROM autopay_mandates WHERE restaurant_id=?',[$rid]);
 check((float)$m['amount']===699.25,'Mandate must retain the original checkout amount');
 payuApplySuccess($t,'test-payment','UPI');
 check((int)one('SELECT COUNT(*) AS n FROM subscription_payments WHERE restaurant_id=?',[$rid])['n']===1,'Settlement must be idempotent');
 check(one('SELECT subscription_ends FROM restaurants WHERE id=?',[$rid])['subscription_ends']===$r['subscription_ends'],'Duplicate settlement must not extend twice');
 for($i=0;$i<3;$i++)autopayFailedAttempt((int)$m['id']);
 check(one('SELECT status FROM autopay_mandates WHERE id=?',[$m['id']])['status']==='failed','Failed mandate must stop');
 check((float)one('SELECT monthly_fee FROM restaurants WHERE id=?',[$rid])['monthly_fee']===799.5,'Failed mandate must not reset the agreed rate');
 echo "PASS: billing quotes, settlement, mandate snapshot, duplicate payment and failed-autopay rate preservation\n";
}finally{
 execq('DELETE FROM audit_logs WHERE restaurant_id=?',[$rid]);
 execq('DELETE FROM restaurants WHERE id=?',[$rid]);
}
