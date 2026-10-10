<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_login(); require_ajax_post();
$productId=input_int('product_id');
if($productId<1 || !db_value('SELECT id FROM products WHERE id=? AND is_active=1',[$productId])) json_response(['ok'=>false,'message'=>'Product not found.'],404);
$uid=(int)$_SESSION['user_id'];
$exists=db_value('SELECT id FROM wishlist WHERE user_id=? AND product_id=?',[$uid,$productId]);
if($exists){db_query('DELETE FROM wishlist WHERE user_id=? AND product_id=?',[$uid,$productId]);$wishlisted=false;}else{db_query('INSERT INTO wishlist(user_id,product_id) VALUES(?,?)',[$uid,$productId]);$wishlisted=true;}
json_response(['ok'=>true,'wishlisted'=>$wishlisted,'count'=>(int)db_value('SELECT COUNT(*) FROM wishlist WHERE user_id=?',[$uid])]);
?>
