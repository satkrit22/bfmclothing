<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/init.php';
require_ajax_post();
$action = input_str('action', 20);
$variantId = input_int('variant_id');
if ($variantId < 1 || !in_array($action, ['add','update','remove'], true)) json_response(['ok'=>false,'message'=>'Invalid cart request.'], 422);
$variant = db_one('SELECT v.id, v.stock, p.is_active, p.name FROM product_variants v JOIN products p ON p.id=v.product_id WHERE v.id=? LIMIT 1', [$variantId]);
if (!$variant && $action === 'add') {
    $productId = input_int('product_id');
    if ($productId > 0) {
        $variant = db_one('SELECT v.id, v.stock, p.is_active, p.name FROM product_variants v JOIN products p ON p.id=v.product_id WHERE p.id=? AND p.is_active=1 AND v.stock>0 ORDER BY v.id LIMIT 1', [$productId]);
        if ($variant) $variantId = (int)$variant['id'];
    }
}
if (!$variant || !(int)$variant['is_active']) json_response(['ok'=>false,'message'=>'This product is unavailable.'], 422);
$cart = cart_items();
if ($action === 'remove') unset($cart[$variantId]);
else {
  $qty = input_int('quantity', 1);
  if ($qty < 1 || $qty > 10) json_response(['ok'=>false,'message'=>'Quantity must be between 1 and 10.'], 422);
  if ($qty > (int)$variant['stock']) json_response(['ok'=>false,'message'=>'Only '.$variant['stock'].' available.'], 422);
  $cart[$variantId] = $action === 'add' ? min(10, (int)($cart[$variantId] ?? 0) + $qty) : $qty;
}
$_SESSION['cart'] = $cart;
cart_persist();
json_response(['ok'=>true,'message'=>$action === 'remove' ? 'Item removed.' : 'Added to your cart.','cart_count'=>cart_count()]);
?>
