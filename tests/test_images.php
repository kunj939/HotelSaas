<?php
declare(strict_types=1);
require dirname(__DIR__).'/public/api/images.php';
$cases=[
 ['https://example.com/dish.jpg',true],
 ['https://cdn.example.com/photo?id=12&size=large',true],
 ['https://upload.wikimedia.org/photo.png',true],
 ['http://example.com/photo.jpg',false],
 ['javascript:alert(1)',false],
 ['data:image/png;base64,abc',false],
 ['//example.com/a.jpg',false],
 ['https://user:pass@example.com/a.jpg',false],
 ['https://localhost/photo',false],
 ['https://127.0.0.1/photo',false],
 ['https://192.168.1.1/photo',false],
 ["https://example.com/a\nb",false],
 ['https://example.com/'.str_repeat('x',800),false],
];
foreach($cases as [$url,$expected])if(remoteImageUrl($url)!==$expected)throw new RuntimeException('Unexpected URL validation: '.$url);
if(!dishImagePath('')||!dishImagePath('/uploads/'.str_repeat('a',32).'.webp')||dishImagePath('/uploads/../../secret.php'))throw new RuntimeException('Local path validation failed');
echo "PASS: image URL validation (16 cases)\n";
