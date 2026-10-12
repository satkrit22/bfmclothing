<?php
/**
 * Customer wishlist. (The heart buttons use api/wishlist.php; this page also
 * accepts a plain POST so it works without JavaScript.)
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_login();

$uid = (int)$_SESSION['user_id'];

if (is_post()) {
    require_csrf();
    $id = input_int('product_id');
    if ($id > 0 && db_value('SELECT id FROM products WHERE id = ? AND is_active = 1', [$id])) {
        if (db_value('SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?', [$uid, $id])) {
            db_query('DELETE FROM wishlist WHERE user_id = ? AND product_id = ?', [$uid, $id]);
        } else {
            db_query('INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)', [$uid, $id]);
        }
    }
    redirect('wishlist.php');
}

$ids      = wishlist_ids();
$products = [];
if ($ids) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $products = db_all(product_list_select() . " WHERE p.is_active = 1 AND p.id IN ($in) ORDER BY p.name", $ids);
}

$page_title  = 'Wishlist';
$page_robots = 'noindex,nofollow';
require __DIR__ . '/includes/header.php';
?>
<section class="container page-section" data-wishlist-page>
    <p class="eyebrow">Saved for later</p>
    <h1>Your wishlist</h1>
    <?php render_product_grid($products, 'Your wishlist is empty', 'Save pieces you love while browsing the shop.'); ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
