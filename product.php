<?php
/**
 * Product page: gallery, variant picker (size + colour), add to cart / buy now,
 * reviews and related products. The picker is driven by main.js (initVariantForm).
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$slug    = input_str('slug', 190, 'get');
$product = $slug !== '' ? db_one(product_list_select() . ' WHERE p.slug = ? AND p.is_active = 1 LIMIT 1', [$slug]) : null;
if (!$product) {
    render_error_page(404, 'Product not found', 'That piece is no longer available.');
}
$pid = (int)$product['id'];

$full = db_one('SELECT description FROM products WHERE id = ?', [$pid]);
$images = db_all('SELECT image_path, alt_text FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order, id', [$pid]);
$variants = db_all('SELECT id, size, color, color_hex, stock FROM product_variants WHERE product_id = ? ORDER BY id', [$pid]);

$sizes = [];
$colors = [];
$stockByColor = [];
foreach ($variants as $v) {
    $sizes[$v['size']] = true;
    $colors[$v['color']] = $v['color_hex'];
    $stockByColor[$v['color']] = ($stockByColor[$v['color']] ?? 0) + (int)$v['stock'];
}
$defaultColor = '';
foreach ($colors as $name => $hex) {
    if (($stockByColor[$name] ?? 0) > 0) {
        $defaultColor = (string)$name;
        break;
    }
}
$defaultSize = count($sizes) === 1 ? (string)array_key_first($sizes) : '';
$soldOut = (int)$product['total_stock'] <= 0;

$price    = effective_price($product);
$discount = discount_percent($product);

$gallery = [];
foreach ($images as $i => $img) {
    $gallery[] = ['url' => product_image_url($img['image_path'], (string)$product['name'], $i), 'alt' => $img['alt_text'] ?: $product['name']];
}
if (!$gallery) {
    $gallery[] = ['url' => product_image_url(null, (string)$product['name'], 0), 'alt' => (string)$product['name']];
    $gallery[] = ['url' => product_image_url(null, (string)$product['name'], 1), 'alt' => (string)$product['name']];
}

$reviews = db_all(
    'SELECT r.rating, r.title, r.comment, r.created_at, u.first_name
     FROM reviews r JOIN users u ON u.id = r.user_id
     WHERE r.product_id = ? AND r.is_approved = 1 ORDER BY r.created_at DESC LIMIT 10',
    [$pid]
);
$related = db_all(
    product_list_select() . ' WHERE p.is_active = 1 AND p.category_id = (SELECT category_id FROM products WHERE id = ?) AND p.id <> ?
     ORDER BY p.sold_count DESC LIMIT 4',
    [$pid, $pid]
);

$page_title       = (string)$product['name'];
$page_description = (string)($product['short_description'] ?: 'Discover ' . $product['name'] . ' from BFM Clothing.');
$page_image       = $gallery[0]['url'];
require __DIR__ . '/includes/header.php';
?>
<section class="section">
    <div class="container product-layout">
        <div class="gallery">
            <div class="gallery-main"><img src="<?= e($gallery[0]['url']) ?>" alt="<?= e($gallery[0]['alt']) ?>" width="900" height="1125" data-gallery-main></div>
            <?php if (count($gallery) > 1): ?>
                <div class="gallery-thumbs">
                    <?php foreach ($gallery as $i => $g): ?>
                        <button type="button" class="gallery-thumb<?= $i === 0 ? ' is-active' : '' ?>" data-gallery-thumb data-src="<?= e($g['url']) ?>" data-alt="<?= e($g['alt']) ?>" aria-label="Show image <?= $i + 1 ?>">
                            <img src="<?= e($g['url']) ?>" alt="" width="72" height="90" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="product-summary">
            <p class="eyebrow"><a href="<?= e(url('shop.php?category=' . rawurlencode((string)$product['category_slug']))) ?>"><?= e($product['category_name']) ?></a></p>
            <h1><?= e($product['name']) ?></h1>
            <?php if ((int)$product['rating_count'] > 0): ?>
                <p class="rating"><?= render_stars((float)$product['rating_avg']) ?> <span class="rating-count"><?= e(number_format((float)$product['rating_avg'], 1)) ?> (<?= (int)$product['rating_count'] ?> reviews)</span></p>
            <?php endif; ?>
            <p class="product-price">
                <span class="price-now"><?= e(money($price)) ?></span>
                <?php if ($discount > 0): ?><s class="price-was"><?= e(money($product['price'])) ?></s><span class="badge badge-sale">-<?= $discount ?>%</span><?php endif; ?>
            </p>
            <p class="lead"><?= e($product['short_description']) ?></p>

            <?php if ($soldOut || !$variants): ?>
                <div class="alert alert-warning">This piece is currently sold out.</div>
            <?php else: ?>
                <form class="variant-form" data-variants='<?= e(json_encode(array_map(static fn(array $v): array => ['id' => (int)$v['id'], 'size' => (string)$v['size'], 'color' => (string)$v['color'], 'stock' => (int)$v['stock']], $variants))) ?>'>
                    <input type="hidden" name="variant_id" value="">

                    <div class="option-group">
                        <div class="option-label">Colour</div>
                        <div class="chip-list">
                            <?php foreach ($colors as $name => $hex): ?>
                                <label class="swatch" title="<?= e($name) ?>">
                                    <input type="radio" name="color" value="<?= e($name) ?>" aria-label="<?= e($name) ?>"<?= (string)$name === $defaultColor ? ' checked' : '' ?>>
                                    <span style="background: <?= e(preg_match('/^#[0-9a-fA-F]{6}$/', (string)$hex) ? $hex : '#111111') ?>"></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="option-group">
                        <div class="option-label">Size</div>
                        <div class="chip-list">
                            <?php foreach (array_keys($sizes) as $size): ?>
                                <label class="chip">
                                    <input type="radio" name="size" value="<?= e($size) ?>"<?= (string)$size === $defaultSize ? ' checked' : '' ?>>
                                    <span><?= e($size) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <p class="stock-status" data-stock-status>Select a size and colour</p>

                    <div class="buy-row">
                        <div class="qty">
                            <button type="button" data-qty-step="-1" aria-label="Decrease quantity"><?= icon('minus') ?></button>
                            <input type="number" name="quantity" value="1" min="1" max="10" inputmode="numeric" aria-label="Quantity">
                            <button type="button" data-qty-step="1" aria-label="Increase quantity"><?= icon('plus') ?></button>
                        </div>
                        <button class="btn btn-primary" type="button" data-add-to-cart><?= icon('bag') ?> Add to cart</button>
                    </div>
                    <button class="btn btn-outline btn-block" type="button" data-buy-now>Buy it now</button>
                </form>
            <?php endif; ?>

            <div class="product-description"><?= nl2br(e($full['description'] ?: 'Designed for daily rotation with a considered fit and comfortable finish.')) ?></div>
            <ul class="product-facts">
                <li><strong>SKU</strong> <?= e($product['sku']) ?></li>
                <li><strong>Delivery</strong> Free over <?= e(money((float)setting('free_shipping_threshold', '3000'))) ?>, otherwise <?= e(money((float)setting('shipping_flat_rate', '150'))) ?></li>
                <li><strong>Payment</strong> Cash on delivery</li>
            </ul>
        </div>
    </div>
</section>

<?php if ($reviews): ?>
<section class="section section-alt">
    <div class="container">
        <div class="section-head"><div><p class="eyebrow">Reviews</p><h2>What customers say</h2></div></div>
        <div class="review-list">
            <?php foreach ($reviews as $r): ?>
                <article class="review">
                    <?= render_stars((float)$r['rating']) ?>
                    <?php if ($r['title']): ?><h3><?= e($r['title']) ?></h3><?php endif; ?>
                    <p><?= e($r['comment']) ?></p>
                    <small><?= e($r['first_name']) ?> &middot; <?= e(format_date($r['created_at'], 'M j, Y')) ?></small>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($related): ?>
<section class="section">
    <div class="container">
        <div class="section-head"><div><p class="eyebrow">You may also like</p><h2>More <?= e($product['category_name']) ?></h2></div></div>
        <?php render_product_grid($related); ?>
    </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
