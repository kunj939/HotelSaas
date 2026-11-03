<?php
declare(strict_types=1);
// Daily autopay job. Run from Hostinger hPanel → Advanced → Cron Jobs, once a day around 7:00 AM:
//   php /home/USER/public_html/public/api/payu_cron.php
// It sends pre-debit notices, charges due autopay months and settles pending PayU payments.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$c=require __DIR__.'/config.local.php';
require __DIR__.'/db.php';
require __DIR__.'/payu.php';
payuConf();
$mandates=rows('SELECT m.*,(SELECT email FROM users u WHERE u.restaurant_id=m.restaurant_id AND u.role="owner" ORDER BY id LIMIT 1) AS email FROM autopay_mandates m JOIN restaurants r ON r.id=m.restaurant_id WHERE m.status="active" AND r.status<>"suspended" AND r.deleted_at IS NULL');
foreach($mandates as $m){
 try { echo 'Mandate '.$m['id'].' (restaurant '.$m['restaurant_id'].'): '.autopayStep($m).PHP_EOL; }
 catch(Throwable $e){error_log('Autopay mandate '.$m['id'].': '.$e->getMessage());echo 'Mandate '.$m['id'].' error: '.$e->getMessage().PHP_EOL;}
}
foreach(rows('SELECT txnid FROM payu_transactions WHERE status IN ("initiated","pending") AND created_at<DATE_SUB(NOW(),INTERVAL 10 MINUTE) AND created_at>DATE_SUB(NOW(),INTERVAL 10 DAY)') as $t){
 try { echo 'Txn '.$t['txnid'].': '.payuSettle($t['txnid']).PHP_EOL; }
 catch(Throwable $e){error_log('PayU settle '.$t['txnid'].': '.$e->getMessage());}
}
