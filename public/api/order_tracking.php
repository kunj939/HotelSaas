<?php
declare(strict_types=1);
if(!isset($route)){http_response_code(404);exit;}
// Token numbers are public queue references, not credentials. Never include guest
// contact details, kitchen notes or the private tracking key in this response.
if($route==='public/token'){
 if($_SERVER['REQUEST_METHOD']!=='GET')fail('Method not allowed',405);
 $slug=val($_GET['slug']??'',90);$token=(string)($_GET['token']??'');$day=(string)($_GET['day']??date('Y-m-d'));
 if(!preg_match('/^[0-9]{1,10}$/D',$token)||(int)$token<1||(int)$token>4294967295)fail('Enter the token number shown after placing your order');
 $date=DateTimeImmutable::createFromFormat('!Y-m-d',$day);
 if(!$date||$date->format('Y-m-d')!==$day||$day<'2000-01-01'||$day>date('Y-m-d'))fail('Choose today or the date you placed your order');
 $o=one('SELECT o.id,o.token_no,o.token_day,o.table_no,o.status,o.call_count,o.called_at,o.total,o.created_at,o.updated_at
  FROM orders o JOIN restaurants r ON r.id=o.restaurant_id
  WHERE r.slug=? AND r.deleted_at IS NULL AND o.token_no=? AND o.token_day=?',[$slug,(int)$token,$day]);
 if(!$o)fail('No order found for this token on this date at this restaurant. Check the number and order date.',404);
 respond(['order'=>orderRow($o)]);
}
