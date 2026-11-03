<?php
declare(strict_types=1);
// PayU subscription billing shared by index.php, payu_return.php and payu_cron.php.
// Expects $c (config) and db.php to be loaded. Prices are decided here, never by the browser.
const PLAN_SETUP_FEE=500.00;       // one time, charged with the first PayU payment
const PLAN_MONTHLY=599.00;         // pay every month yourself, no autopay
const PLAN_AUTOPAY=549.00;         // per month with 6-month autopay
const PLAN_AUTOPAY_MONTHS=6;
const AUTOPAY_MAX_ATTEMPTS=3;      // failed debits/pre-debits before autopay is stopped
const OUTLETS_INCLUDED=2;          // restaurants one owner login can open on the normal plan
const EXTRA_OUTLET_FEE=200.00;     // per month, for each restaurant beyond OUTLETS_INCLUDED

function payuConf():array {
 global $c;$p=$c['payu']??[];
 if(empty($p['key'])||empty($p['salt'])||str_contains((string)$p['key'],'CHANGE_ME'))throw new RuntimeException('PayU is not configured in config.local.php');
 return $p;
}
function payuReady():bool {try{payuConf();return true;}catch(RuntimeException $e){return false;}}
function payuLive():bool {return (payuConf()['mode']??'test')==='live';}
function payuPayUrl():string {return payuLive()?'https://secure.payu.in/_payment':'https://test.payu.in/_payment';}
function payuApiUrl():string {return payuLive()?'https://info.payu.in/merchant/postservice.php?form=2':'https://test.payu.in/merchant/postservice.php?form=2';}
function money2(float $n):string {return number_format($n,2,'.','');}
function planPrice(string $plan):float {return $plan==='autopay6'?PLAN_AUTOPAY:PLAN_MONTHLY;}
// Extra outlets are billed on the owner's home restaurant (users.restaurant_id), not on the linked ones.
function extraOutlets(int $rid):int {
 $n=one('SELECT COUNT(*) AS n FROM restaurant_access WHERE user_id=(SELECT id FROM users WHERE restaurant_id=? AND role="owner" ORDER BY id LIMIT 1)',[$rid]);
 return max(0,(int)($n['n']??0)-OUTLETS_INCLUDED);
}
function monthlyFee(int $rid):float {return PLAN_MONTHLY+extraOutlets($rid)*EXTRA_OUTLET_FEE;}
function billingQuote(array $r,string $plan):array {
 $fee=(int)$r['setup_fee_paid']?0.0:PLAN_SETUP_FEE;$price=planPrice($plan);$n=extraOutlets((int)$r['id']);$extra=$n*EXTRA_OUTLET_FEE;
 return ['plan'=>$plan,'price'=>$price,'extra_outlets'=>$n,'extra_fee'=>$extra,'setup_fee'=>$fee,'total'=>$price+$extra+$fee];
}
// Manual UPI (works before PayU is ready): the platform UPI ID is stored by Super Admin in platform_settings.
function setting(string $k):?string {$x=one('SELECT v FROM platform_settings WHERE k=?',[$k]);return $x?$x['v']:null;}
function upiConf():?array {$id=setting('upi_id');return $id?['id'=>$id,'name'=>setting('upi_name')?:'TableBloom']:null;}
// UPI plans: one month at the monthly price, or 6 months paid upfront at the autopay price.
function upiQuote(array $r,string $plan):array {
 $q=billingQuote($r,$plan==='upfront6'?'autopay6':'monthly');$months=$plan==='upfront6'?PLAN_AUTOPAY_MONTHS:1;$monthly=$q['price']+$q['extra_fee'];
 return ['plan'=>$plan,'months'=>$months,'monthly'=>$monthly,'extra_outlets'=>$q['extra_outlets'],'setup_fee'=>$q['setup_fee'],'total'=>$monthly*$months+$q['setup_fee']];
}
function payuTxnid(string $prefix,int $rid):string {return $prefix.$rid.'X'.bin2hex(random_bytes(6));}

// Server-to-server PayU API call (verify_payment, pre_debit_SI, si_transaction).
function payuApi(string $command,string $var1):array {
 $p=payuConf();
 $ch=curl_init(payuApiUrl());
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>45,
  CURLOPT_POSTFIELDS=>http_build_query(['key'=>$p['key'],'command'=>$command,'var1'=>$var1,'hash'=>hash('sha512',$p['key'].'|'.$command.'|'.$var1.'|'.$p['salt'])])]);
 $raw=curl_exec($ch);$err=curl_error($ch);curl_close($ch);
 if($raw===false)throw new RuntimeException('PayU '.$command.' request failed: '.$err);
 $data=json_decode((string)$raw,true);
 if(!is_array($data))throw new RuntimeException('PayU '.$command.' unexpected reply: '.substr((string)$raw,0,300));
 return $data;
}

// Builds the hosted-checkout form the browser posts to PayU. Autopay also registers a monthly mandate (SI).
function payuCheckout(array $r,array $owner,string $plan,string $phone):array {
 global $c;$p=payuConf();$q=billingQuote($r,$plan);
 $txnid=payuTxnid('TB',(int)$r['id']);
 $return=rtrim((string)$c['app_url'],'/').'/api/payu_return.php';
 $first=trim(preg_replace('/[^A-Za-z ]+/','',(string)$owner['full_name']))?:'Owner';
 $f=['key'=>$p['key'],'txnid'=>$txnid,'amount'=>money2($q['total']),
  'productinfo'=>$plan==='autopay6'?'TableBloom 6 month autopay plan':'TableBloom monthly plan',
  'firstname'=>substr($first,0,60),'email'=>(string)$owner['email'],'phone'=>$phone,'surl'=>$return,'furl'=>$return];
 $parts=[$f['key'],$f['txnid'],$f['amount'],$f['productinfo'],$f['firstname'],$f['email'],'','','','','','','','','',''];
 if($plan==='autopay6'){
  // Mandate window: starts today (required for UPI) and runs 6 months past the current expiry, so every debit fits.
  $from=max(date('Y-m-d'),(string)$r['subscription_ends']);
  $si=json_encode(['billingAmount'=>money2($q['price']+$q['extra_fee']),'billingCurrency'=>'INR','billingCycle'=>'MONTHLY','billingInterval'=>1,
   'paymentStartDate'=>date('Y-m-d'),'paymentEndDate'=>date('Y-m-d',strtotime($from.' +'.PLAN_AUTOPAY_MONTHS.' months'))],JSON_UNESCAPED_SLASHES);
  $f+=['api_version'=>'7','si'=>'1','si_details'=>$si];$parts[]=$si;
 }
 $parts[]=$p['salt'];$f['hash']=hash('sha512',implode('|',$parts));
 execq('INSERT INTO payu_transactions(restaurant_id,txnid,kind,plan,amount,setup_fee) VALUES(?,?,?,?,?,?)',[$r['id'],$txnid,'checkout',$plan,$q['total'],$q['setup_fee']]);
 return ['action'=>payuPayUrl(),'fields'=>$f];
}

// Asks PayU for the real result of a transaction and applies it once. Returns success|pending|failed.
// The browser POST is never trusted: only verify_payment decides.
function payuSettle(string $txnid):string {
 $t=one('SELECT * FROM payu_transactions WHERE txnid=?',[$txnid]);if(!$t)return 'failed';
 if($t['status']==='success'||$t['status']==='failed')return $t['status'];
 $res=payuApi('verify_payment',$txnid);$d=$res['transaction_details'][$txnid]??null;
 $st=is_array($d)?strtolower((string)($d['status']??'')):'';
 if($st==='success'){
  if(abs((float)($d['amt']??0)-(float)$t['amount'])>=0.01){error_log('PayU amount mismatch for '.$txnid.': '.($d['amt']??'?'));payuMarkFailed($t);return 'failed';}
  payuApplySuccess($t,(string)($d['mihpayid']??''),(string)($d['mode']??''));return 'success';
 }
 if($st==='pending'){execq('UPDATE payu_transactions SET status="pending" WHERE id=? AND status="initiated"',[$t['id']]);return 'pending';}
 // "Not Found" right after checkout can mean PayU has not recorded it yet; the cron closes it after an hour.
 if($st==='failure'||$st==='failed'||strtotime((string)$t['created_at'])<time()-3600)payuMarkFailed($t);
 return 'failed';
}
function payuMarkFailed(array $t):void {
 if(execq('UPDATE payu_transactions SET status="failed" WHERE id=? AND status IN ("initiated","pending")',[$t['id']])&&$t['kind']==='recurring')autopayFailedAttempt((int)$t['mandate_id']);
}

// Extends the subscription by one month and records the receipt. Safe to call twice: only the first call changes anything.
function payuApplySuccess(array $t,string $mihpayid,string $mode):void {
 $rid=(int)$t['restaurant_id'];
 db()->beginTransaction();
 try {
  if(execq('UPDATE payu_transactions SET status="success",mihpayid=?,mode=? WHERE id=? AND status<>"success"',[$mihpayid,$mode,$t['id']])!==1){db()->commit();return;}
  $monthly=(float)$t['amount']-(float)$t['setup_fee']; // plan price plus any extra-outlet fee
  execq('UPDATE restaurants SET status=IF(status="suspended","suspended","active"),subscription_ends=DATE_ADD(GREATEST(subscription_ends,CURDATE()),INTERVAL 1 MONTH),monthly_fee=?,setup_fee_paid=IF(?>0,1,setup_fee_paid) WHERE id=?',[$monthly,$t['setup_fee'],$rid]);
  $ends=(string)one('SELECT subscription_ends FROM restaurants WHERE id=?',[$rid])['subscription_ends'];
  // Debit one day before expiry so a slow UPI debit does not interrupt ordering.
  $nextDebit=date('Y-m-d',strtotime($ends.' -1 day'));
  if($t['kind']==='checkout'&&$t['plan']==='autopay6'){
   execq('UPDATE autopay_mandates SET status="cancelled" WHERE restaurant_id=? AND status="active"',[$rid]);
   execq('INSERT INTO autopay_mandates(restaurant_id,authpayuid,mode,amount,cycles_total,cycles_done,next_debit_date) VALUES(?,?,?,?,?,1,?)',[$rid,$mihpayid,$mode,$monthly,PLAN_AUTOPAY_MONTHS,$nextDebit]);
   $note='Autopay month 1 of '.PLAN_AUTOPAY_MONTHS;
  } elseif($t['kind']==='recurring'){
   $m=one('SELECT * FROM autopay_mandates WHERE id=?',[$t['mandate_id']]);$done=(int)$m['cycles_done']+1;$finished=$done>=(int)$m['cycles_total'];
   execq('UPDATE autopay_mandates SET cycles_done=?,next_debit_date=?,predebit_debit_on=NULL,invoice_no=NULL,attempts=0,status=? WHERE id=?',[$done,$nextDebit,$finished?'completed':'active',$m['id']]);
   if($finished)execq('UPDATE restaurants SET monthly_fee=? WHERE id=?',[monthlyFee($rid),$rid]);
   $note='Autopay month '.$done.' of '.$m['cycles_total'];
  } else $note='Monthly plan';
  if((float)$t['setup_fee']>0)$note.=' + one-time setup fee '.money2((float)$t['setup_fee']);
  execq('INSERT INTO subscription_payments(restaurant_id,amount,paid_on,method,reference_no,note) VALUES(?,?,CURDATE(),?,?,?)',[$rid,$t['amount'],trim('PayU '.$mode),$mihpayid,$note]);
  execq('INSERT INTO audit_logs(restaurant_id,action,detail) VALUES(?,?,?)',[$rid,'subscription.payu',$t['txnid'].' '.$t['amount']]);
  db()->commit();
 } catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
}

function autopayFailedAttempt(int $mandateId):void {
 execq('UPDATE autopay_mandates SET attempts=attempts+1,last_attempt_on=CURDATE() WHERE id=?',[$mandateId]);
 $m=one('SELECT restaurant_id,attempts FROM autopay_mandates WHERE id=?',[$mandateId]);
 if($m&&(int)$m['attempts']>=AUTOPAY_MAX_ATTEMPTS){
  execq('UPDATE autopay_mandates SET status="failed" WHERE id=? AND status="active"',[$mandateId]);
  execq('UPDATE restaurants SET monthly_fee=? WHERE id=?',[monthlyFee((int)$m['restaurant_id']),$m['restaurant_id']]);
 }
}

// One daily step for an active mandate: send the pre-debit notice ~3 days ahead (RBI needs 48h+), then debit on the notified date.
function autopayStep(array $m):string {
 $today=date('Y-m-d');$seq=(int)$m['cycles_done']+1;$upi=strtoupper((string)$m['mode'])==='UPI';
 if(one('SELECT id FROM payu_transactions WHERE mandate_id=? AND status IN ("initiated","pending")',[$m['id']]))return 'waiting for pending debit';
 if($m['last_attempt_on']===$today)return 'already tried today';
 if(!$m['predebit_debit_on']){
  if($m['next_debit_date']>date('Y-m-d',strtotime('+3 days')))return 'not due';
  $debitOn=max((string)$m['next_debit_date'],date('Y-m-d',strtotime('+2 days')));
  $invoice='TBI'.$m['id'].'S'.$seq.'X'.bin2hex(random_bytes(3));
  $var=['authpayuid'=>$m['authpayuid'],'requestId'=>$invoice,'debitDate'=>$debitOn,'amount'=>money2((float)$m['amount']),'invoiceDisplayNumber'=>$invoice];
  if($upi)$var['mandateSeqNo']=$seq;
  $res=payuApi('pre_debit_SI',json_encode($var,JSON_UNESCAPED_SLASHES));
  if((string)($res['status']??'')!=='1'){error_log('PayU pre-debit failed for mandate '.$m['id'].': '.json_encode($res));autopayFailedAttempt((int)$m['id']);return 'pre-debit failed';}
  execq('UPDATE autopay_mandates SET predebit_debit_on=?,invoice_no=? WHERE id=?',[$debitOn,$invoice,$m['id']]);
  return 'pre-debit sent for '.$debitOn;
 }
 if($m['predebit_debit_on']>$today)return 'debit on '.$m['predebit_debit_on'];
 $txnid=payuTxnid('TR',(int)$m['restaurant_id']);
 execq('INSERT INTO payu_transactions(restaurant_id,mandate_id,txnid,kind,plan,amount) VALUES(?,?,?,?,?,?)',[$m['restaurant_id'],$m['id'],$txnid,'recurring','autopay6',$m['amount']]);
 execq('UPDATE autopay_mandates SET last_attempt_on=CURDATE() WHERE id=?',[$m['id']]);
 $var=['authpayuid'=>$m['authpayuid'],'amount'=>money2((float)$m['amount']),'txnid'=>$txnid,'email'=>(string)($m['email']??''),'invoiceDisplayNumber'=>$m['invoice_no']];
 if($upi)$var['mandateSeqNo']=$seq;
 $res=payuApi('si_transaction',json_encode($var,JSON_UNESCAPED_SLASHES));
 $st=strtolower((string)($res['details'][$txnid]['status']??''));
 if($st==='captured'||$st==='pending'||$st==='in-progress')return 'debit '.$st.', settled: '.payuSettle($txnid);
 error_log('PayU recurring debit failed for mandate '.$m['id'].': '.json_encode($res));
 payuMarkFailed(one('SELECT * FROM payu_transactions WHERE txnid=?',[$txnid]));
 return 'debit failed';
}
