<?php
/**
 * Search suggestions for the header search box (main.js calls this).
 * GET q -> {ok, results:[{name,url,image,price_text}]} (max 6)
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

$q = input_str('q', 80, 'get');
if (mb_strlen($q) < 2) {
    json_response(['ok' => true, 'results' => []]);
}

// Escape LIKE wildcards typed by the visitor so "%" and "_" are searched literally.
$like = '%' . addcslashes($q, '%_\\') . '%';
$rows = db_all(
    product_list_select() . ' WHERE p.is_active = 1 AND (p.name LIKE ? OR p.tags LIKE ? OR p.short_description LIKE ?)
     ORDER BY p.is_featured DESC, p.sold_count DESC, p.name LIMIT 6',
    [$like, $like, $like]
);

json_response([
    'ok' => true,
    'results' => array_map(static function (array $p): array {
        return [
            'name'       => $p['name'],
            'url'        => product_url($p),
            'image'      => product_image_url($p['image_path'] ?? null, (string)$p['name'], 0),
            'price_text' => money(effective_price($p)),
        ];
    }, $rows),
]);
