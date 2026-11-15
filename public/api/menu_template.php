<?php
declare(strict_types=1);

function bundledMenuPhoto(string $path): bool {
 return (bool)preg_match('~^assets/photos/(?:kaku/)?[a-z0-9-]+\.(?:jpg|jpeg|png|webp)$~D', $path)
  && is_file(dirname(__DIR__).'/'.$path);
}

/** Seed the explicitly selected Kaku menu inside the account-creation transaction. */
function seedStarterMenu(PDO $db, int $restaurantId): array {
 if (!$db->inTransaction()) throw new LogicException('Starter menu requires the account transaction');
 $existing = $db->prepare('SELECT COUNT(*) FROM categories WHERE restaurant_id=?');
 $existing->execute([$restaurantId]);
 $existingItems = $db->prepare('SELECT COUNT(*) FROM menu_items WHERE restaurant_id=?');
 $existingItems->execute([$restaurantId]);
 if ((int)$existing->fetchColumn() || (int)$existingItems->fetchColumn()) {
  throw new LogicException('Starter menu must not replace an existing menu');
 }
 $file = dirname(__DIR__).'/assets/kaku_menu.json';
 $template = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
 if (empty($template['categories']) || empty($template['items'])) throw new RuntimeException('Starter menu is empty');
 $categoryIds = [];
 $categoryInsert = $db->prepare('INSERT INTO categories(restaurant_id,name,sort_order) VALUES(?,?,?)');
 foreach ($template['categories'] as $position => $name) {
  $categoryInsert->execute([$restaurantId, $name, $position]);
  $categoryIds[$name] = (int)$db->lastInsertId();
 }
 $itemInsert = $db->prepare('INSERT INTO menu_items(restaurant_id,category_id,name,description,food_type,base_price,options_json,addons_json,image_url,image_credit,image_license,image_source_url,available,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 foreach ($template['items'] as $position => $item) {
  if (!isset($categoryIds[$item['category']]) || !bundledMenuPhoto($item['image_url'])) {
   throw new RuntimeException('Starter menu category or photo is missing');
  }
  $itemInsert->execute([
   $restaurantId, $categoryIds[$item['category']], $item['name'], $item['description'],
   $item['food_type'], $item['base_price'], json_encode($item['options'], JSON_THROW_ON_ERROR),
   json_encode($item['addons'], JSON_THROW_ON_ERROR), $item['image_url'], $item['image_credit'],
   $item['image_license'], $item['image_source_url'], 1, $position,
  ]);
 }
 return ['template'=>$template['id'], 'items'=>count($template['items']), 'categories'=>count($categoryIds)];
}
