<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; form-action 'self'; frame-ancestors 'none'");
$configFile=__DIR__.'/config.local.php';
if(!is_file($configFile)){http_response_code(503);exit('First copy config.example.php to config.local.php and configure your database and secrets.');}
$c=require $configFile;
$key=(string)($c['install_key']??'');
if(strlen($key)<24 || str_starts_with($key,'CHANGE_')){http_response_code(403);exit('Installer disabled. Configure a random install_key of at least 24 characters to enable it.');}
require __DIR__.'/db.php';
$message='';$done=false;
try {
 if(one('SELECT id FROM users WHERE role="super" LIMIT 1')){http_response_code(403);exit('Installation already complete. Delete api/install_once.php and remove install_key from config.local.php.');}
 if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  if(!hash_equals($key,(string)($_POST['key']??''))){http_response_code(403);exit('Invalid installation key.');}
  $name=trim((string)($_POST['name']??''));$email=strtolower(trim((string)($_POST['email']??'')));$password=(string)($_POST['password']??'');
  if($name===''||mb_strlen($name)>120||strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<14)throw new RuntimeException('Enter a name, valid email, and password of at least 14 characters.');
  // Serialize concurrent installations; the second request rechecks after obtaining the lock.
  if((int)one("SELECT GET_LOCK('tablebloom_install',5) AS acquired")['acquired']!==1)throw new RuntimeException('Another installation is running. Try again.');
  try {
   if(one('SELECT id FROM users WHERE role="super" LIMIT 1'))throw new RuntimeException('Installation already complete.');
   execq('INSERT INTO users(role,full_name,email,password_hash) VALUES("super",?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
   $done=true;
  } finally {one("SELECT RELEASE_LOCK('tablebloom_install')");}
 }
} catch(PDOException $e){error_log('Installer database error: '.$e->getMessage());$message='Database setup failed. Import database/schema.sql and check the database configuration and server logs.';http_response_code(503);}
catch(RuntimeException $e){$message=$e->getMessage();http_response_code(400);}
?>
<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>TableBloom setup</title>
<h1>Set up TableBloom</h1>
<?php if($done): ?>
<p>Administrator created. Delete api/install_once.php and remove install_key from config.local.php.</p><p><a href="../index.html#/admin">Sign in and add your first restaurant</a></p>
<?php else: ?>
<p>Import database/schema.sql first. Use the installation key from your private configuration.</p>
<p role="alert"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></p>
<form method="post"><p><label>Installation key <input name="key" type="password" required autocomplete="off"></label></p><p><label>Your name <input name="name" required maxlength="120" autocomplete="name"></label></p><p><label>Email <input name="email" type="email" required maxlength="190" autocomplete="username"></label></p><p><label>Password <input name="password" type="password" required minlength="14" autocomplete="new-password"></label></p><button>Create administrator</button></form>
<?php endif; ?></html>
