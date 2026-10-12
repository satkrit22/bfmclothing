<?php
/**
 * Cart API (used by main.js).
 *   POST action=add|update|remove, variant_id, quantity   (CSRF token in X-CSRF-Token)
 * The browser is never trusted: stock, price and availability are re-read from the database.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_ajax_post();

$action    = input_str('action', 20);
$variantId = input_int('variant_id');

if ($variantId < 1 || !in_array($action, ['add', 'update', 'remove'], true)) {
    json_response(['ok' => false, 'message' => 'Invalid cart request.'], 422);
}

$cart = cart_items();

if ($action === 'remove') {
    unset($cart[$variantId]);
    $message = 'Item removed.';
} else {
    $variant = db_one(
        'SELECT v.id, v.stock, v.size, v.color, p.is_active, p.name
         FROM product_variants v JOIN products p ON p.id = v.product_id
         WHERE v.id = ? LIMIT 1',
        [$variantId]
    );
    if (!$variant || !(int)$variant['is_active']) {
        json_response(['ok' => false, 'message' => 'This product is unavailable.'], 422);
    }

    $qty = input_int('quantity', 1);
    if ($qty < 1 || $qty > 10) {
        json_response(['ok' => false, 'message' => 'Quantity must be between 1 and 10.'], 422);
    }

    $stock   = (int)$variant['stock'];
    $inCart  = (int)($cart[$variantId] ?? 0);
    $wanted  = $action === 'add' ? $inCart + $qty : $qty;

    if ($stock <= 0) {
        json_response(['ok' => false, 'message' => 'Sorry, that size and colour is sold out.'], 422);
    }
    if ($wanted > 10) {
        json_response(['ok' => false, 'message' => 'You can order up to 10 of the same item.'], 422);
    }
    if ($wanted > $stock) {
        $msg = $inCart > 0 && $action === 'add'
            ? 'Only ' . $stock . ' available, and you already have ' . $inCart . ' in your cart.'
            : 'Only ' . $stock . ' available.';
        json_response(['ok' => false, 'message' => $msg], 422);
    }

    $cart[$variantId] = $wanted;
    $message = $action === 'add' ? 'Added to your cart.' : 'Cart updated.';
}

$_SESSION['cart'] = $cart;
cart_persist();

json_response([
    'ok'         => true,
    'message'    => $message,
    'cart_count' => cart_count(),
]);
