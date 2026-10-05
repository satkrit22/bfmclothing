<?php
/**
 * Reusable HTML components.
 *
 * product_card() expects a row with these columns (see product_list_select() below):
 *   id, slug, name, price, sale_price, rating_avg, rating_count, is_new,
 *   category_name, image_path, total_stock
 */

declare(strict_types=1);

/**
 * Base SELECT used by every product listing (home, shop, search, related...).
 * Append WHERE / ORDER BY / LIMIT after it. Uses the "p" alias for products.
 */
function product_list_select(): string
{
    return "SELECT p.id, p.slug, p.name, p.sku, p.gender, p.short_description, p.price, p.sale_price,
                   p.rating_avg, p.rating_count, p.is_new, p.is_featured, p.sold_count, p.created_at,
                   c.name AS category_name, c.slug AS category_slug,
                   (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id
                     ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image_path,
                   (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id) AS total_stock
            FROM products p
            JOIN categories c ON c.id = p.category_id AND c.is_active = 1 ";
}

function product_card(array $p): string
{
    $eff      = effective_price($p);
    $discount = discount_percent($p);
    $soldOut  = (int)$p['total_stock'] <= 0;
    $wished   = in_array((int)$p['id'], wishlist_ids(), true);
    $href     = product_url($p);
    $img1     = product_image_url($p['image_path'] ?? null, (string)$p['name'], 0);
    $img2     = product_image_url(null, (string)$p['name'], 1);
    $hasPhoto = !empty($p['image_path']);

    ob_start(); ?>
    <article class="product-card" data-product-id="<?= (int)$p['id'] ?>">
        <div class="product-media">
            <a href="<?= e($href) ?>" class="product-link" aria-label="<?= e($p['name']) ?>">
                <img src="<?= e($img1) ?>" alt="<?= e($p['name']) ?>" loading="lazy" width="600" height="750">
                <?php if (!$hasPhoto): ?>
                    <img class="product-alt" src="<?= e($img2) ?>" alt="" loading="lazy" width="600" height="750" aria-hidden="true">
                <?php endif; ?>
            </a>
            <div class="product-badges">
                <?php if ($soldOut): ?><span class="badge badge-dark">Sold out</span>
                <?php elseif ($discount > 0): ?><span class="badge badge-sale">-<?= $discount ?>%</span><?php endif; ?>
                <?php if (!empty($p['is_new']) && !$soldOut): ?><span class="badge badge-light">New</span><?php endif; ?>
            </div>
            <button type="button" class="icon-btn wish-btn<?= $wished ? ' is-active' : '' ?>" data-wishlist="<?= (int)$p['id'] ?>"
                    aria-pressed="<?= $wished ? 'true' : 'false' ?>" aria-label="<?= $wished ? 'Remove from wishlist' : 'Add to wishlist' ?>">
                <?= icon('heart', $wished ? 'fill' : '') ?>
            </button>
            <div class="product-actions">
                <button type="button" class="btn btn-light btn-sm" data-quickview="<?= (int)$p['id'] ?>"><?= icon('eye') ?> Quick view</button>
                <?php if (!$soldOut): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-quickview="<?= (int)$p['id'] ?>"><?= icon('bag') ?> Add to cart</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="product-info">
            <p class="product-cat"><a href="<?= e(url('shop.php?category=' . rawurlencode((string)$p['category_slug']))) ?>"><?= e($p['category_name']) ?></a></p>
            <h3 class="product-name"><a href="<?= e($href) ?>"><?= e($p['name']) ?></a></h3>
            <div class="product-meta">
                <p class="price">
                    <span class="price-now"><?= e(money($eff)) ?></span>
                    <?php if ($discount > 0): ?><s class="price-was"><?= e(money($p['price'])) ?></s><?php endif; ?>
                </p>
                <?php if ((int)$p['rating_count'] > 0): ?>
                    <p class="rating" title="<?= e(number_format((float)$p['rating_avg'], 1)) ?> out of 5">
                        <?= render_stars((float)$p['rating_avg']) ?>
                        <span class="rating-count">(<?= (int)$p['rating_count'] ?>)</span>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
    return (string)ob_get_clean();
}

/** Print a responsive grid of product cards, or an empty state. */
function render_product_grid(array $products, string $emptyTitle = 'No products found', string $emptyText = 'Try changing your filters or search.'): void
{
    if (!$products) {
        echo '<div class="empty-state">' . icon('bag') . '<h3>' . e($emptyTitle) . '</h3><p>' . e($emptyText)
            . '</p><a class="btn btn-primary" href="' . e(url('shop.php')) . '">Browse the shop</a></div>';
        return;
    }
    echo '<div class="grid-products">';
    foreach ($products as $p) {
        echo product_card($p);
    }
    echo '</div>';
}
