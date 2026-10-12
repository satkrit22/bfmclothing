<?php
/**
 * Quick view API: GET id -> product + variants for the quick view modal.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$id = input_int('id', 0, 'get');
if ($id < 1) {
    json_response(['ok' => false, 'message' => 'Invalid product.'], 422);
}

$p = db_one(product_list_select() . ' WHERE p.id = ? AND p.is_active = 1 LIMIT 1', [$id]);
if (!$p) {
    json_response(['ok' => false, 'message' => 'Product not found.'], 404);
}

$variants = db_all(
    'SELECT id, size, color, color_hex, stock FROM product_variants WHERE product_id = ? ORDER BY id',
    [$id]
);

json_response([
    'ok' => true,
    'product' => [
        'id'                => (int)$p['id'],
        'name'              => $p['name'],
        'short_description' => $p['short_description'],
        'price_text'        => money(effective_price($p)),
        'was_text'          => discount_percent($p) > 0 ? money($p['price']) : '',
        'image'             => product_image_url($p['image_path'] ?? null, (string)$p['name'], 0),
        'url'               => product_url($p),
        'discount'          => discount_percent($p),
        'variants'          => array_map(static function (array $v): array {
            return [
                'id'    => (int)$v['id'],
                'size'  => (string)$v['size'],
                'color' => (string)$v['color'],
                'hex'   => (string)$v['color_hex'],
                'stock' => (int)$v['stock'],
            ];
        }, $variants),
    ],
]);
