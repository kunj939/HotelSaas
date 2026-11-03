<?php
declare(strict_types=1);
// PayU sends the owner's browser here after checkout (surl and furl). Add ?webhook=1 to use the same URL as a PayU webhook.
// The posted fields are only used to find the txnid; payuSettle() asks PayU for the real status.
ini_set('display_errors','0');
$configFile=__DIR__.'/config.local.php';
if(!is_file($configFile)){http_response_code(503);exit('Setup required');}
$c=require $configFile;
require __DIR__.'/db.php';
require __DIR__.'/payu.php';
$txnid=preg_replace('/[^A-Za-z0-9]/','',(string)($_POST['txnid']??''));
$result='failed';
try { if($txnid!=='')$result=payuSettle(substr($txnid,0,40)); }
catch(Throwable $e){error_log('PayU return error: '.$e->getMessage());$result='pending';}
if(isset($_GET['webhook'])){header('Content-Type: text/plain');echo 'OK';exit;}
header('Cache-Control: no-store');
header('Location: '.rtrim((string)$c['app_url'],'/').'/#/owner?payment='.$result,true,303);
