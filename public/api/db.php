<?php
declare(strict_types=1);
// Database helpers shared by index.php, payu_return.php and payu_cron.php. Expects $c (config) to be loaded.
// PHP and MySQL both use the restaurant timezone, so "today", daily tokens and order times agree.
date_default_timezone_set((string)($c['timezone']??'Asia/Kolkata'));
function db(): PDO {
 global $c;
 static $db;
 if (!$db) {
  $db = new PDO('mysql:host='.$c['db_host'].';dbname='.$c['db_name'].';charset=utf8mb4', $c['db_user'],$c['db_password'],[
   PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
   PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
   PDO::ATTR_EMULATE_PREPARES=>false,
  ]);
  $db->exec("SET time_zone='".date('P')."'");
 }
 return $db;
}
function rows(string $sql,array $p=[]):array { $s=db()->prepare($sql);$s->execute($p);return $s->fetchAll(); }
function one(string $sql,array $p=[]):?array { $s=db()->prepare($sql);$s->execute($p);$x=$s->fetch();return $x?:null; }
function execq(string $sql,array $p=[]):int { $s=db()->prepare($sql);$s->execute($p);return $s->rowCount(); }
