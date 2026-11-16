<?php
declare(strict_types=1);
// Optional CLI upgrade. The first Super Admin -> Add admin action also runs this upgrade.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$file=getenv('TABLEBLOOM_TEST_CONFIG')?:__DIR__.'/../public/api/config.local.php';
$c=require $file;
require __DIR__.'/../public/api/db.php';
require __DIR__.'/../public/api/admin_accounts.php';
ensureAdminAccountsSchema();
echo "Admin accounts schema ready\n";
