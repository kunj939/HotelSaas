<?php
// Run from any directory: php tools/check_setup.php. Prints no configuration secrets.
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$failed=false;
function check(bool $ok,string $message):void {global $failed;echo ($ok?'PASS':'FAIL').' '.$message.PHP_EOL;if(!$ok)$failed=true;}
check(version_compare(PHP_VERSION,'8.2','>='),'PHP 8.2 or newer');
foreach(['pdo_mysql','mbstring','fileinfo'] as $extension)check(extension_loaded($extension),'PHP extension: '.$extension);
$root=dirname(__DIR__);$file=$root.'/public/api/config.local.php';
check(is_file($file),'Private configuration exists');
check(is_dir($root.'/public/uploads')&&is_writable($root.'/public/uploads'),'Uploads directory is writable');
if(is_file($file)){
 $c=require $file;
 check(is_array($c),'Configuration returns an array');
 if(is_array($c)){
  $secret=(string)($c['app_secret']??'');
  check(strlen($secret)>=32&&!str_starts_with($secret,'CHANGE_'),'Application secret is configured');
  check(filter_var($c['app_url']??'',FILTER_VALIDATE_URL)!==false&&str_starts_with($c['app_url']??'','https://'),'Public app URL uses HTTPS');
  check(in_array($c['timezone']??'',DateTimeZone::listIdentifiers(),true),'Valid timezone configured');
  try {
   require $root.'/public/api/db.php';
   db();check(true,'Database connection');
   foreach(['restaurants'=>['qr_mode','deleted_at'],'users'=>['role'],'menu_items'=>['options_json'],'orders'=>['customer_name','token_no','call_count'],'restaurant_access'=>['user_id'],'token_counters'=>['last_no'],'autopay_mandates'=>['status'],'payu_transactions'=>['id'],'upi_payments'=>['utr'],'platform_settings'=>['k'],'subscription_payments'=>['amount'],'audit_logs'=>['action'],'auth_attempts'=>['ip_hash']] as $table=>$columns){
    rows('SELECT '.implode(',',$columns).' FROM '.$table.' LIMIT 0');
    check(true,'Schema: '.$table);
   }
   $installed=(bool)one('SELECT id FROM users WHERE role="super" LIMIT 1');
   check($installed,'Super Admin exists (use api/install_once.php for first setup)');
   if($installed){check(empty($c['install_key']),'Installation key removed');check(!is_file($root.'/public/api/install_once.php'),'Web installer deleted after setup');}
  }catch(Throwable $e){check(false,'Database or schema check failed; check credentials and import schema / required migrations. Details are intentionally omitted to protect secrets.');}
 }
}
exit($failed?1:0);
