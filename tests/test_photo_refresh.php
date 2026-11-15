<?php
declare(strict_types=1);
require __DIR__.'/../public/api/menu_template.php';
function checkPhoto(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
$upgrades=json_decode(file_get_contents(__DIR__.'/../public/assets/kaku_photo_upgrade.json'),true,512,JSON_THROW_ON_ERROR);
checkPhoto(count($upgrades)===194,'All starter dishes must have an upgrade mapping');
$name='Cheese Capsicum Pizza';$upgrade=$upgrades[$name];
$original=array_merge(['id'=>15,'restaurant_id'=>9,'name'=>$name,'base_price'=>175,'available'=>0,'featured'=>1],$upgrade['previous']);
$uploaded=array_replace($original,['image_url'=>'/uploads/'.str_repeat('a',32).'.jpg']);
$linked=array_replace($original,['image_url'=>'https://example.test/my-pizza.jpg']);
$removed=array_replace($original,['image_url'=>'']);
$renamed=array_replace($original,['name'=>'Owner recipe']);
$editedCredit=array_replace($original,['image_credit'=>'Owner supplied photo']);
$newPhoto=array_replace($original,$upgrade['current']);
$input=[$original,$uploaded,$linked,$removed,$renamed,$editedCredit,$newPhoto];
$result=refreshedStarterPhotos($input);
checkPhoto($result[0]===array_replace($original,$upgrade['current']),'Only starter photo fields should change');
foreach([1,2,3,4,5,6] as $index)checkPhoto($result[$index]===$input[$index],'Preserve owner edit '.$index);
checkPhoto($input[0]===$original,'Read-only refresh must not mutate caller data');
foreach($upgrades as $dish=>$row)checkPhoto(bundledMenuPhoto($row['current']['image_url']),'Missing dish image: '.$dish);
// Accounts created with either earlier catalog version must receive the latest art.
foreach($upgrades as $dish=>$row){
 foreach($row['previous_versions']??[] as $previous){
  $saved=array_merge(['name'=>$dish,'base_price'=>199,'available'=>0,'featured'=>1],$previous);
  checkPhoto(refreshedStarterPhotos([$saved])[0]===array_replace($saved,$row['current']),'Refresh second-version image: '.$dish);
  foreach(['image_url','image_credit','image_license','image_source_url'] as $field){
   $custom=array_replace($saved,[$field=>$field==='image_url'?'':'Owner edit']);
   checkPhoto(refreshedStarterPhotos([$custom])[0]===$custom,'Preserve customized second-version field: '.$field);
  }
 }
}
echo "PASS: existing-menu photo refresh, independent owner edits, prices, sold-out status and all assets\n";
