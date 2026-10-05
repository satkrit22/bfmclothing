<?php
require_once __DIR__ . '/includes/init.php';
$slug = input_str('slug', 190, 'get');
$product = db_one(product_list_select() . ' WHERE p.slug = ? AND p.is_active = 1 LIMIT 1', [$slug]);
if (!$product) { render_error_page(404, 'Product not found', 'That piece is no longer available.'); }
$page_title = (string)$product['name'];
$page_description = (string)($product['short_description'] ?: 'Discover ' . $product['name'] . ' from BFM Clothing.');
require __DIR__ . '/includes/header.php';
$variants = db_all('SELECT id, size, color, color_hex, stock FROM product_variants WHERE product_id = ? ORDER BY size, color', [(int)$product['id']]);
$colors = []; $sizes = [];
foreach ($variants as $variant) { $colors[$variant['color']] = $variant['color_hex']; $sizes[$variant['size']] = true; }
$price = effective_price($product);
?>
<section class="section"><div class="container product-detail"><div class="product-gallery"><div class="product-large-image"><img src="<?= e(product_image_url($product['image_path'] ?? null, $product['name'], 0)) ?>" alt="<?= e($product['name']) ?>" width="900" height="1125"></div></div><div class="product-copy"><p class="eyebrow"><?= e($product['category_name']) ?></p><h1><?= e($product['name']) ?></h1><p class="price product-price"><span class="price-now"><?= e(money($price)) ?></span><?php if (discount_percent($product) > 0): ?><s class="price-was"><?= e(money($product['price'])) ?></s><?php endif; ?></p><p class="lead"><?= e($product['short_description']) ?></p><div class="product-description"><?= nl2br(e($product['description'] ?: 'Designed for daily rotation with a considered fit and comfortable finish.')) ?></div><form class="variant-form" data-variants='<?= e(json_encode($variants)) ?>'><input type="hidden" name="variant_id"><fieldset><legend>Colour</legend><div class="swatches"><?php foreach ($colors as $color => $hex): ?><label class="swatch"><input type="radio" name="color" value="<?= e($color) ?>"><span style="--swatch: <?= e($hex) ?>" title="<?= e($color) ?>"></span><b><?= e($color) ?></b></label><?php endforeach; ?></div></fieldset><fieldset><legend>Size</legend><div class="size-options"><?php foreach (array_keys($sizes) as $size): ?><label><input type="radio" name="size" value="<?= e($size) ?>"><span><?= e($size) ?></span></label><?php endforeach; ?></div></fieldset><p class="stock-status" data-stock-status>Select a size and colour</p><div class="qty-row"><label for="quantity">Quantity</label><input class="form-control" id="quantity" name="quantity" type="number" value="1" min="1" max="10"></div><button class="btn btn-primary btn-block" type="button" data-add-to-cart>Add to bag</button></form></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
