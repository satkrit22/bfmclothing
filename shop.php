<?php
require_once __DIR__ . '/includes/init.php';
$page_title = 'Shop';
$page_description = 'Explore BFM Clothing everyday essentials, available in carefully considered fits and colours.';
require __DIR__ . '/includes/header.php';

$category = input_str('category', 100, 'get');
$q = input_str('q', 100, 'get');
$sort = input_str('sort', 20, 'get');
$where = ['p.is_active = 1'];
$params = [];
if ($category !== '') { $where[] = 'c.slug = ?'; $params[] = $category; }
if ($q !== '') { $where[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.tags LIKE ?)'; $term = '%' . $q . '%'; $params = array_merge($params, [$term, $term, $term]); }
$order = match ($sort) { 'price_low' => 'p.price ASC', 'price_high' => 'p.price DESC', 'popular' => 'p.sold_count DESC', default => 'p.created_at DESC' };
$products = db_all(product_list_select() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order, $params);
$categories = nav_categories();
?>
<section class="page-hero"><div class="container"><p class="eyebrow">The collection</p><h1>Everyday, elevated.</h1><p class="lead">Quietly confident essentials designed to work hard and wear well.</p></div></section>
<section class="section"><div class="container shop-layout"><aside class="shop-sidebar"><p class="eyebrow">Browse</p><a class="filter-link<?= $category === '' ? ' active' : '' ?>" href="<?= e(url('shop.php')) ?>">All pieces</a><?php foreach ($categories as $cat): ?><a class="filter-link<?= $category === $cat['slug'] ? ' active' : '' ?>" href="<?= e(url('shop.php?category=' . rawurlencode($cat['slug']))) ?>"><?= e($cat['name']) ?></a><?php endforeach; ?></aside><div class="shop-results"><div class="section-head"><div><p class="eyebrow"><?= count($products) ?> pieces</p><h2><?= $q !== '' ? 'Results for “' . e($q) . '”' : 'Shop all' ?></h2></div><form class="sort-form" method="get"><input type="hidden" name="category" value="<?= e($category) ?>"><label class="sr-only" for="sort">Sort products</label><select class="form-control" id="sort" name="sort" onchange="this.form.submit()"><option value="newest"<?= $sort === 'newest' || $sort === '' ? ' selected' : '' ?>>Newest</option><option value="popular"<?= $sort === 'popular' ? ' selected' : '' ?>>Most popular</option><option value="price_low"<?= $sort === 'price_low' ? ' selected' : '' ?>>Price: low to high</option><option value="price_high"<?= $sort === 'price_high' ? ' selected' : '' ?>>Price: high to low</option></select></form></div><?php render_product_grid($products); ?></div></div></section>
<?php require __DIR__ . '/includes/footer.php'; ?>
