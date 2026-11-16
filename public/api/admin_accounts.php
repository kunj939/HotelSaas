<?php
declare(strict_types=1);
// Backwards-compatible rollout: existing logins work before the first admin is created.
// Only the Super Admin's authenticated create action may install these additions.
function adminAccountsReady(bool $refresh=false):bool {
 static $ready=null;
 if($ready!==null&&!$refresh)return $ready;
 $columns=array_column(rows('SHOW COLUMNS FROM users'),null,'Field');
 return $ready=isset($columns['username'],$columns['auth_version'])&&str_contains($columns['role']['Type'],"'admin'")&&(bool)one('SELECT 1 AS ready FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',['restaurant_onboardings']);
}
function ensureAdminAccountsSchema():void {
 if(adminAccountsReady())return;
 if((int)one("SELECT GET_LOCK('tablebloom_admin_accounts',10) AS acquired")['acquired']!==1)throw new RuntimeException('Admin account setup is busy. Please retry.');
 try{
  $columns=array_column(rows('SHOW COLUMNS FROM users'),null,'Field');
  if(!str_contains($columns['role']['Type'],"'admin'")){
   $type=$columns['role']['Type'];if(!preg_match('/^enum\(.*\)$/i',$type))throw new RuntimeException('Unexpected user role schema');
   db()->exec('ALTER TABLE users MODIFY role '.substr($type,0,-1).",'admin') NOT NULL");
  }
  if(!isset($columns['username']))db()->exec('ALTER TABLE users ADD COLUMN username VARCHAR(40) DEFAULT NULL, ADD UNIQUE KEY uq_users_username(username)');
  if(!isset($columns['auth_version']))db()->exec('ALTER TABLE users ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 0');
  db()->exec('CREATE TABLE IF NOT EXISTS restaurant_onboardings (
   id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
   restaurant_id BIGINT UNSIGNED DEFAULT NULL UNIQUE,
   admin_user_id BIGINT UNSIGNED NOT NULL,
   restaurant_name VARCHAR(160) NOT NULL,
   restaurant_slug VARCHAR(90) NOT NULL,
   created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
   INDEX idx_onboarding_admin (admin_user_id,created_at),
   CONSTRAINT fk_onboarding_rest FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE SET NULL,
   CONSTRAINT fk_onboarding_admin FOREIGN KEY (admin_user_id) REFERENCES users(id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
  if(!adminAccountsReady(true))throw new RuntimeException('Admin account setup did not complete');
 }finally{one("SELECT RELEASE_LOCK('tablebloom_admin_accounts')");}
}
