<?php
declare(strict_types=1);
if(!isset($route)){http_response_code(404);exit;}
// Included only after index.php has checked the admin/agent namespace role.
if($route==='admin/team'){
 $members=adminAccountsReady()?rows('SELECT u.id,u.full_name,u.username,u.active,u.created_at,
  (SELECT COUNT(*) FROM restaurant_onboardings o WHERE o.admin_user_id=u.id) AS restaurants_added,
  (SELECT COUNT(*) FROM restaurant_onboardings o WHERE o.admin_user_id=u.id AND o.created_at>=DATE_FORMAT(NOW(),"%Y-%m-01")) AS added_this_month
  FROM users u WHERE u.role="admin" ORDER BY u.created_at DESC,u.id DESC'):[];
 respond(['admins'=>$members]);
}
if($route==='admin/team/create'){
 mutation();$b=body();$name=val($b['full_name']??'',120);$username=strtolower(trim((string)($b['username']??'')));$password=(string)($b['password']??'');
 if(!$name||!preg_match('/^[a-z][a-z0-9_.-]{2,39}$/D',$username))fail('Enter a name and a username of 3–40 letters, numbers, dots, underscores or hyphens, starting with a letter');
 if(strlen($password)<12||strlen($password)>72||str_contains($password,"\0"))fail('Use a password of 12–72 characters');
 ensureAdminAccountsSchema();
 try{
  execq('INSERT INTO users(role,full_name,email,username,password_hash) VALUES("admin",?,?,?,?)',[$name,'admin-'.bin2hex(random_bytes(16)).'@accounts.invalid',$username,password_hash($password,PASSWORD_DEFAULT)]);
 }catch(PDOException $e){if($e->getCode()==='23000')fail('That username is already taken');throw $e;}
 $id=(int)db()->lastInsertId();audit('admin.account.create',null,$id.' '.$username);
 respond(['ok'=>true,'id'=>$id,'username'=>$username],201);
}
if($route==='admin/team/access'||$route==='admin/team/reset-password'){
 mutation();$b=body();$id=(int)($b['id']??0);
 if(!adminAccountsReady()||!one('SELECT id FROM users WHERE id=? AND role="admin"',[$id]))fail('Admin account not found',404);
 if($route==='admin/team/access'){
  if(!in_array($b['active']??null,[true,false,0,1],true))fail('Choose active or disabled');
  execq('UPDATE users SET active=?,auth_version=auth_version+1 WHERE id=? AND role="admin"',[(int)$b['active'],$id]);audit('admin.account.access',null,$id.' '.(int)$b['active']);
 }else{
  $password=(string)($b['password']??'');if(strlen($password)<12||strlen($password)>72||str_contains($password,"\0"))fail('Use a password of 12–72 characters');
  execq('UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE id=? AND role="admin"',[password_hash($password,PASSWORD_DEFAULT),$id]);audit('admin.account.password',null,(string)$id);
 }
 respond(['ok'=>true]);
}
if($route==='agent/overview'){
 $uid=(int)$agent['id'];$page=max(1,(int)($_GET['page']??1));$limit=50;
 $stats=one('SELECT COUNT(*) AS total,COALESCE(SUM(o.created_at>=DATE_FORMAT(NOW(),"%Y-%m-01")),0) AS this_month,
  COALESCE(SUM(r.status="active" AND r.deleted_at IS NULL AND r.subscription_ends>=CURDATE()),0) AS active
  FROM restaurant_onboardings o LEFT JOIN restaurants r ON r.id=o.restaurant_id WHERE o.admin_user_id=?',[$uid]);
 $pages=max(1,(int)ceil((int)$stats['total']/$limit));$page=min($page,$pages);$offset=($page-1)*$limit;
 $list=rows('SELECT COALESCE(r.name,o.restaurant_name) AS name,COALESCE(r.slug,o.restaurant_slug) AS slug,o.created_at,
  CASE WHEN r.id IS NULL OR r.deleted_at IS NOT NULL THEN "removed" WHEN r.status="suspended" THEN "stopped" WHEN r.subscription_ends<CURDATE() THEN "expired" ELSE r.status END AS status
  FROM restaurant_onboardings o LEFT JOIN restaurants r ON r.id=o.restaurant_id WHERE o.admin_user_id=? ORDER BY o.id DESC LIMIT '.$limit.' OFFSET '.$offset,[$uid]);
 respond(['stats'=>$stats,'restaurants'=>$list,'page'=>$page,'pages'=>$pages]);
}
if($route==='agent/password'){
 mutation();$b=body();$old=(string)($b['current_password']??'');$password=(string)($b['new_password']??'');
 if(strlen($password)<12||strlen($password)>72||str_contains($password,"\0"))fail('Use a password of 12–72 characters');
 $u=one('SELECT password_hash FROM users WHERE id=? AND role="admin"',[$agent['id']]);
 if(!$u||!password_verify($old,$u['password_hash']))fail('Current password is incorrect',403);
 execq('UPDATE users SET password_hash=?,auth_version=auth_version+1 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$agent['id']]);
 $_SESSION['auth_version']=(int)one('SELECT auth_version FROM users WHERE id=?',[$agent['id']])['auth_version'];
 audit('admin.account.password.self',null,(string)$agent['id']);respond(['ok'=>true]);
}
