<?php
// ONE-TIME FIRST ADMIN BOOTSTRAP. Delete this file immediately after use.
declare(strict_types=1);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$configPath=__DIR__.'/config.local.php';
if(!is_file($configPath)){http_response_code(503);exit('Configure api/config.local.php first.');}
$c=require $configPath;
if(empty($c['install_key'])||strlen($c['install_key'])<24){http_response_code(403);exit('Set an install_key of at least 24 random characters.');}
try{$db=new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4',$c['db_user'],$c['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
catch(Throwable $e){http_response_code(500);exit('Database unavailable. Import database/schema.sql in phpMyAdmin first.');}
$count=(int)$db->query('SELECT COUNT(*) FROM users WHERE role="super"')->fetchColumn();
if($count){http_response_code(403);exit('Super Admin already exists. Delete install_once.php.');}
$error='';$success=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
 $key=(string)($_POST['install_key']??'');$email=trim(strtolower((string)($_POST['email']??'')));$pw=(string)($_POST['password']??'');$name=trim((string)($_POST['name']??''));
 if(!hash_equals((string)$c['install_key'],$key)){$error='Incorrect installation key.';}
 elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pw)<14||strlen($name)<2){$error='Use valid email, a name and a password of at least 14 characters.';}
 else{try{$stmt=$db->prepare('INSERT INTO users(restaurant_id,role,full_name,email,password_hash) VALUES(NULL,"super",?,?,?)');$stmt->execute([$name,$email,password_hash($pw,PASSWORD_DEFAULT)]);$success=true;}catch(Throwable $e){$error='Could not create admin. Check server logs.';error_log($e->getMessage());}}
}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>TableBloom Setup</title><style>body{font:15px system-ui;background:#f5f8f4;color:#1c382f;display:grid;min-height:95vh;place-items:center}.box{background:white;padding:32px;border-radius:18px;max-width:450px;width:90%;box-shadow:0 18px 40px #0001}input{display:block;width:100%;padding:12px;margin:7px 0 16px;box-sizing:border-box;border:1px solid #ccded2;border-radius:9px}button{border:0;background:#176c52;color:white;border-radius:10px;padding:13px 20px;font-weight:800}p{line-height:1.6}</style><div class="box"><h1>TableBloom · First Setup</h1><?php if($success):?><h2>✅ Super Admin created</h2><p><b>Immediately delete <code>public_html/api/install_once.php</code>.</b> Then open your domain, click Super Admin and log in.</p><?php else:?><?php if($error):?><p style="color:#ac312b"><?=e($error)?></p><?php endif;?><p>Import schema first. This form creates the platform Super Admin, once.</p><form method="post" autocomplete="off"><label>Secret install key<input name="install_key" type="password" required></label><label>Your name<input name="name" required></label><label>Admin email<input name="email" type="email" required></label><label>Strong password (14+ characters)<input name="password" type="password" minlength="14" required></label><button>Create Super Admin</button></form><p style="color:#777;font-size:12px">Never leave this setup script public after installation.</p><?php endif;?></div></html>
