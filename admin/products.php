<?php
/** Products list: search, filter, sort, publish/hide, delete. Add/edit lives in product-edit.php. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('products.manage');

$threshold = max(0, (int)setting('low_stock_threshold', '5'));

if (is_post()) {
    require_csrf();
    $action = input_str('action', 12);
    $pid    = input_int('id');
    $p = $pid > 0 ? db_one('SELECT id, name, is_active FROM products WHERE id = ?', [$pid]) : null;
    if (!$p) {
        flash('error', 'Product not found.');
    } elseif ($action === 'toggle') {
        $new = (int)$p['is_active'] ? 0 : 1;
        db_query('UPDATE products SET is_active = ? WHERE id = ?', [$new, $pid]);
        audit_log($new ? 'product.publish' : 'product.hide', 'product', $pid, ['name' => $p['name']]);
        flash('success', '"' . $p['name'] . '" is now ' . ($new ? 'visible in the store.' : 'hidden from the store.'));
    } elseif ($action === 'delete') {
        $files = array_column(db_all('SELECT image_path FROM product_images WHERE product_id = ?', [$pid]), 'image_path');
        try {
            db_query('DELETE FROM products WHERE id = ?', [$pid]);
            foreach ($files as $f) {
                admin_delete_upload((string)$f);
            }
            audit_log('product.delete', 'product', $pid, ['name' => $p['name']]);
            flash('success', '"' . $p['name'] . '" was deleted. Past orders keep their item details.');
        } catch (Throwable $e) {
            error_log('[product delete] ' . $e->getMessage());
            flash('error', 'The product could not be deleted. Hide it instead.');
        }
    }
    redirect('admin/products.php' . admin_qs());
}

$q      = input_str('q', 80, 'get');
$cat    = input_int('category', 0, 'get');
$state  = input_str('state', 10, 'get');
$stock  = input_str('stock', 10, 'get');
$sort   = input_str('sort', 12, 'get');
$sorts = [
    'newest' => 'p.created_at DESC, p.id DESC', 'name' => 'p.name ASC', 'price_asc' => 'COALESCE(p.sale_price, p.price) ASC',
    'price_desc' => 'COALESCE(p.sale_price, p.price) DESC', 'sold' => 'p.sold_count DESC', 'stock' => 'stock ASC',
];
if (!isset($sorts[$sort])) {
    $sort = 'newest';
}

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.tags LIKE ?)';
    $like = admin_like($q);
    array_push($params, $like, $like, $like);
}
if ($cat > 0) {
    $where[] = 'p.category_id = ?';
    $params[] = $cat;
}
if ($state === 'active') {
    $where[] = 'p.is_active = 1';
} elseif ($state === 'hidden') {
    $where[] = 'p.is_active = 0';
}
$stockExpr = '(SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id)';
if ($stock === 'low') {
    $where[] = 'EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.stock <= ?)';
    $params[] = $threshold;
} elseif ($stock === 'out') {
    $where[] = "$stockExpr = 0";
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

if (input_str('export', 5, 'get') === 'csv') {
    $rows = db_all(
        "SELECT p.sku, p.name, c.name AS category, p.gender, p.price, p.sale_price, $stockExpr AS stock, p.sold_count, IF(p.is_active, 'Visible', 'Hidden') AS state
         FROM products p JOIN categories c ON c.id = p.category_id $whereSql ORDER BY {$sorts[$sort]}",
        $params
    );
    admin_send_csv('products-' . date('Y-m-d') . '.csv', ['SKU', 'Name', 'Category', 'Gender', 'Price', 'Sale price', 'Stock', 'Sold', 'Status'], array_map('array_values', $rows));
}

$total = (int)db_value("SELECT COUNT(*) FROM products p $whereSql", $params);
$pg    = paginate($total, 15);
$products = db_all(
    "SELECT p.id, p.name, p.sku, p.price, p.sale_price, p.is_active, p.is_featured, p.is_new, p.sold_count, c.name AS category,
            (SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.is_primary DESC, pi.sort_order, pi.id LIMIT 1) AS image_path,
            $stockExpr AS stock,
            (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variants
     FROM products p JOIN categories c ON c.id = p.category_id $whereSql
     ORDER BY {$sorts[$sort]} LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);
$cats = db_all('SELECT id, name FROM categories ORDER BY sort_order, name');

admin_header('Products', 'products', [
    'subtitle' => number_format($total) . ' product' . ($total === 1 ? '' : 's'),
    'actions'  => '<a class="adm-btn" href="' . e(admin_qs(['export' => 'csv'])) . '">' . ai('download', 16) . ' Export CSV</a>'
                . '<a class="adm-btn adm-btn-primary" href="' . e(admin_url('product-edit.php')) . '">' . ai('plus', 16) . ' Add product</a>',
]);
?>
<section class="adm-card">
    <form class="adm-filters" method="get">
        <input class="adm-input adm-search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, SKU or tag" aria-label="Search products">
        <select class="adm-select" name="category" aria-label="Category">
            <option value="">All categories</option>
            <?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"<?= adm_sel($cat, $c['id']) ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <select class="adm-select" name="state" aria-label="Visibility">
            <option value="">Any visibility</option><option value="active"<?= adm_sel($state, 'active') ?>>Visible</option><option value="hidden"<?= adm_sel($state, 'hidden') ?>>Hidden</option>
        </select>
        <select class="adm-select" name="stock" aria-label="Stock">
            <option value="">Any stock</option><option value="low"<?= adm_sel($stock, 'low') ?>>Low stock</option><option value="out"<?= adm_sel($stock, 'out') ?>>Sold out</option>
        </select>
        <select class="adm-select" name="sort" aria-label="Sort">
            <option value="newest"<?= adm_sel($sort, 'newest') ?>>Newest</option><option value="name"<?= adm_sel($sort, 'name') ?>>Name A-Z</option>
            <option value="price_asc"<?= adm_sel($sort, 'price_asc') ?>>Price low-high</option><option value="price_desc"<?= adm_sel($sort, 'price_desc') ?>>Price high-low</option>
            <option value="sold"<?= adm_sel($sort, 'sold') ?>>Best selling</option><option value="stock"<?= adm_sel($sort, 'stock') ?>>Lowest stock</option>
        </select>
        <button class="adm-btn adm-btn-primary" type="submit">Filter</button>
        <?php if ($q !== '' || $cat || $state !== '' || $stock !== '' || $sort !== 'newest'): ?><a class="adm-btn" href="<?= e(url('admin/products.php')) ?>">Reset</a><?php endif; ?>
    </form>

    <?php if (!$products): ?>
        <div class="adm-empty"><?= ai('package', 30) ?><p>No products match these filters.</p></div>
    <?php else: ?>
        <div class="adm-table-wrap"><table class="adm-table">
            <thead><tr><th>Product</th><th>Category</th><th class="adm-num">Price</th><th class="adm-num">Stock</th><th class="adm-num">Sold</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): $s = (int)$p['stock']; ?>
                <tr>
                    <td><div class="adm-prod">
                        <img class="adm-thumb" src="<?= e(product_image_url($p['image_path'], (string)$p['name'], 0)) ?>" alt="" loading="lazy" width="44" height="54">
                        <div><a href="<?= e(admin_url('product-edit.php', ['id' => $p['id']])) ?>"><strong><?= e($p['name']) ?></strong></a>
                            <small class="adm-mono"><?= e($p['sku']) ?></small>
                            <?php if ($p['is_featured']): ?> <?= adm_badge('Featured', 'purple') ?><?php endif; ?><?php if ($p['is_new']): ?> <?= adm_badge('New', 'info') ?><?php endif; ?></div>
                    </div></td>
                    <td><?= e($p['category']) ?></td>
                    <td class="adm-num"><?php if ($p['sale_price'] !== null && (float)$p['sale_price'] > 0 && (float)$p['sale_price'] < (float)$p['price']): ?><strong><?= e(money($p['sale_price'])) ?></strong><br><s class="adm-muted"><?= e(money($p['price'])) ?></s><?php else: ?><?= e(money($p['price'])) ?><?php endif; ?></td>
                    <td class="adm-num <?= $s === 0 ? 'adm-stock-out' : ($s <= $threshold * (int)max(1, $p['variants']) ? 'adm-stock-low' : '') ?>"><?= $s === 0 ? 'Sold out' : $s ?><br><small class="adm-muted"><?= (int)$p['variants'] ?> variants</small></td>
                    <td class="adm-num"><?= (int)$p['sold_count'] ?></td>
                    <td><?= $p['is_active'] ? adm_badge('Visible', 'ok') : adm_badge('Hidden', 'neutral') ?></td>
                    <td><div class="adm-row-actions">
                        <a class="adm-btn adm-btn-sm" href="<?= e(admin_url('product-edit.php', ['id' => $p['id']])) ?>"><?= ai('edit', 14) ?> Edit</a>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="adm-btn adm-btn-sm" type="submit"><?= $p['is_active'] ? 'Hide' : 'Publish' ?></button></form>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="adm-btn adm-btn-sm adm-btn-danger" type="submit" aria-label="Delete <?= e($p['name']) ?>" data-confirm="Delete &quot;<?= e($p['name']) ?>&quot; and all of its photos and variants? Past orders keep their details. Hide it instead if you may sell it again." data-confirm-yes="Delete product"><?= ai('trash', 14) ?></button></form>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?= admin_pager($pg) ?>
    <?php endif; ?>
</section>
<?php admin_footer(); ?>
