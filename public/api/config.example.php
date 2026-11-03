<?php
// COPY to config.local.php, then fill these values. Never commit the real file.
return [
 'db_host' => 'localhost',
 'db_name' => 'CHANGE_ME',
 'db_user' => 'CHANGE_ME',
 'db_password' => 'CHANGE_ME',
 'app_url' => 'https://menu.yourdomain.com', // no trailing slash
 'timezone' => 'Asia/Kolkata', // daily token numbers restart at midnight in this timezone
 'app_secret' => 'CHANGE_TO_A_LONG_RANDOM_64_CHARACTER_VALUE',
 'install_key' => 'CHANGE_TO_ANOTHER_LONG_RANDOM_STRING', // remove after setup
 // PayU dashboard → Developers → Key and Salt. Use 'test' keys first, then switch mode to 'live'.
 'payu' => [
  'mode' => 'test',
  'key' => 'CHANGE_ME',
  'salt' => 'CHANGE_ME',
  'merchant_name' => '', // optional: the business name PayU shows at checkout, if it is not TableBloom
 ],
];
