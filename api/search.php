<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
$q = input_str('q', 80, 'get');
if ($q === '') { json_response(['ok'=>true,'products'=>[]]); }
$like = '%'.$q.'%';
$rows = db_all('SELECT p.id,p.name,p.slug,p.price,p.sale_price,COALESCE((SELECT image_path FROM product_images WHERE product_id=p.id ORDER BY is_primary DESC,sort_order ASC LIMIT 1), "") image FROM products p WHERE p.is_active=1 AND (p.name LIKE ? OR p.tags LIKE ? OR p.short_description LIKE ?) ORDER BY p.is_featured DESC,p.name LIMIT 8', [$like,$like,$like]);
json_response(['ok'=>true,'products'=>array_map(static fn(array $p): array => ['id'=>(int)$p['id'],'name'=>$p['name'],'slug'=>$p['slug'],'price'=>(float)$p['price'],'sale_price'=>$p['sale_price'] !== null ? (float)$p['sale_price'] : null,'image'=>$p['image'] ? url($p['image']) : url('placeholder.php')], $rows)]);
