<?php
/**
 * Shop: category / gender / new-arrivals / search filters, sorting and pagination.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$category = input_str('category', 100, 'get');
$gender   = input_str('gender', 10, 'get');
$q        = input_str('q', 100, 'get');
$sort     = input_str('sort', 20, 'get');
$onlyNew  = input_int('new', 0, 'get') === 1;
if (!in_array($gender, ['men', 'women'], true)) {
    $gender = '';
}

$where  = ['p.is_active = 1'];
$params = [];
if ($category !== '') {
    $where[] = 'c.slug = ?';
    $params[] = $category;
}
if ($gender !== '') {
    // "unisex" pieces belong in both the Men and Women edits
    $where[] = "p.gender IN (?, 'unisex')";
    $params[] = $gender;
}
if ($onlyNew) {
    $where[] = 'p.is_new = 1';
}
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.tags LIKE ?)';
    $term = '%' . addcslashes($q, '%_\\') . '%';
    array_push($params, $term, $term, $term);
}

$effective = 'IF(p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price, p.sale_price, p.price)';
$order = match ($sort) {
    'price_low'  => $effective . ' ASC',
    'price_high' => $effective . ' DESC',
    'popular'    => 'p.sold_count DESC',
    default      => 'p.created_at DESC, p.id DESC',
};

$whereSql = implode(' AND ', $where);
$total = (int)db_value(
    'SELECT COUNT(*) FROM products p JOIN categories c ON c.id = p.category_id AND c.is_active = 1 WHERE ' . $whereSql,
    $params
);
$pg = paginate($total);
$products = db_all(
    product_list_select() . ' WHERE ' . $whereSql . ' ORDER BY ' . $order . ' LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset'],
    $params
);
$categories = nav_categories();

$heading = $q !== '' ? 'Results for “' . $q . '”' : ($gender !== '' ? ucfirst($gender) : ($onlyNew ? 'New arrivals' : 'Shop all'));

// keep the other filters when changing category / sorting
$keep = array_filter(['gender' => $gender, 'new' => $onlyNew ? '1' : '', 'q' => $q, 'sort' => $sort], static fn($v) => $v !== '');
$catLink = static fn(string $slug): string => url('shop.php?' . http_build_query($slug !== '' ? $keep + ['category' => $slug] : $keep));

$page_title       = $heading === 'Shop all' ? 'Shop' : $heading;
$page_description = 'Explore BFM Clothing everyday essentials, available in carefully considered fits and colours.';
require __DIR__ . '/includes/header.php';
?>
<section class="page-hero"><div class="container"><p class="eyebrow">The collection</p><h1>Everyday, elevated.</h1><p class="lead">Quietly confident essentials designed to work hard and wear well.</p></div></section>
<section class="section">
    <div class="container shop-layout">
        <aside class="shop-sidebar">
            <p class="eyebrow">Browse</p>
            <a class="filter-link<?= $category === '' ? ' active' : '' ?>" href="<?= e($catLink('')) ?>">All pieces</a>
            <?php foreach ($categories as $cat): ?>
                <a class="filter-link<?= $category === $cat['slug'] ? ' active' : '' ?>" href="<?= e($catLink((string)$cat['slug'])) ?>"><?= e($cat['name']) ?></a>
            <?php endforeach; ?>
        </aside>
        <div class="shop-results">
            <div class="section-head">
                <div><p class="eyebrow"><?= (int)$total ?> <?= $total === 1 ? 'piece' : 'pieces' ?></p><h2><?= e($heading) ?></h2></div>
                <form class="sort-form" method="get">
                    <?php foreach (['category' => $category, 'gender' => $gender, 'q' => $q, 'new' => $onlyNew ? '1' : ''] as $k => $v): ?>
                        <?php if ($v !== ''): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; ?>
                    <?php endforeach; ?>
                    <label class="sr-only" for="sort">Sort products</label>
                    <select class="form-control" id="sort" name="sort" onchange="this.form.submit()">
                        <option value="newest"<?= $sort === 'newest' || $sort === '' ? ' selected' : '' ?>>Newest</option>
                        <option value="popular"<?= $sort === 'popular' ? ' selected' : '' ?>>Most popular</option>
                        <option value="price_low"<?= $sort === 'price_low' ? ' selected' : '' ?>>Price: low to high</option>
                        <option value="price_high"<?= $sort === 'price_high' ? ' selected' : '' ?>>Price: high to low</option>
                    </select>
                </form>
            </div>
            <?php render_product_grid($products); ?>
            <?= render_pagination($pg) ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
