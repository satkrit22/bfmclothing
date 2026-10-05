<?php
require_once __DIR__ . '/includes/init.php';

$page_title = 'Modern essentials, made to move';
$page_description = 'BFM Clothing creates confident everyday essentials with considered fits, quality fabrics and a clean, contemporary point of view.';
require __DIR__ . '/includes/header.php';

$featured = db_all(product_list_select() . ' WHERE p.is_active = 1 AND p.is_featured = 1 ORDER BY p.created_at DESC LIMIT 4');
$newArrivals = db_all(product_list_select() . ' WHERE p.is_active = 1 ORDER BY p.created_at DESC LIMIT 4');
$categories = nav_categories();
?>

<section class="hero" aria-labelledby="hero-title">
    <img class="hero-bg" src="<?= e(url('placeholder.php?t=BFM%20Studio&i=5&w=1800&h=1200')) ?>" alt="" aria-hidden="true">
    <div class="container hero-inner">
        <p class="eyebrow">The everyday uniform / 2026</p>
        <h1 id="hero-title">Wear your point of view.</h1>
        <p>Elevated essentials designed for the pace of real life. Clean silhouettes, considered details, no unnecessary noise.</p>
        <div class="btn-row">
            <a class="btn btn-light" href="<?= e(url('shop.php')) ?>">Shop the collection <?= icon('arrow') ?></a>
            <a class="btn btn-outline" href="<?= e(url('shop.php?new=1&sort=newest')) ?>">Explore new arrivals</a>
        </div>
    </div>
</section>

<section class="section section-tight trust-strip" aria-label="BFM promises">
    <div class="container trust-grid">
        <div><strong>01 / Thoughtful design</strong><span>Purposeful pieces, refined daily.</span></div>
        <div><strong>02 / Quality first</strong><span>Better fabrics. Better wear.</span></div>
        <div><strong>03 / Made for movement</strong><span>Comfort that keeps up.</span></div>
    </div>
</section>

<?php if ($featured): ?>
<section class="section" aria-labelledby="featured-title">
    <div class="container">
        <div class="section-head"><div><p class="eyebrow">Curated for now</p><h2 id="featured-title">The edit</h2></div><a class="link-arrow" href="<?= e(url('shop.php')) ?>">View all <?= icon('arrow') ?></a></div>
        <?php render_product_grid($featured); ?>
    </div>
</section>
<?php endif; ?>

<section class="section section-dark editorial-panel" aria-labelledby="manifesto-title">
    <div class="container editorial-grid">
        <div><p class="eyebrow">A different kind of essential</p><h2 id="manifesto-title">Less, but better.</h2></div>
        <div><p>We believe the best wardrobe is built slowly: pieces with enough presence to stand alone, enough restraint to work together, and enough quality to become part of your routine.</p><a class="link-arrow" href="<?= e(url('about.php')) ?>">Our story <?= icon('arrow') ?></a></div>
    </div>
</section>

<?php if ($categories): ?>
<section class="section" aria-labelledby="categories-title">
    <div class="container"><div class="section-head"><div><p class="eyebrow">Find your uniform</p><h2 id="categories-title">Shop by category</h2></div></div>
        <div class="cat-grid">
            <?php foreach (array_slice($categories, 0, 4) as $index => $category): ?>
                <a class="cat-card" href="<?= e(url('shop.php?category=' . rawurlencode($category['slug']))) ?>">
                    <img src="<?= e(url('placeholder.php?t=' . rawurlencode($category['name']) . '&i=' . ($index + 1) . '&w=800&h=1000')) ?>" alt="<?= e($category['name']) ?>" loading="lazy" width="800" height="1000">
                    <span><?= e($category['name']) ?> <?= icon('arrow-up-right') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($newArrivals): ?>
<section class="section section-alt" aria-labelledby="new-title">
    <div class="container"><div class="section-head"><div><p class="eyebrow">Just in</p><h2 id="new-title">New arrivals</h2></div><a class="link-arrow" href="<?= e(url('shop.php?new=1&sort=newest')) ?>">Shop new <?= icon('arrow') ?></a></div><?php render_product_grid($newArrivals); ?></div>
</section>
<?php endif; ?>

<section class="section newsletter" aria-labelledby="newsletter-title"><div class="container"><p class="eyebrow">Stay in the know</p><h2 id="newsletter-title">Good things, occasionally.</h2><p>Get first access to new drops, limited edits and notes from the studio.</p><form class="newsletter-form" data-newsletter novalidate><label class="sr-only" for="homeEmail">Email address</label><input type="email" id="homeEmail" name="email" placeholder="Email address" autocomplete="email" maxlength="190" required><button class="btn btn-primary" type="submit">Subscribe</button></form></div></section>

<?php require __DIR__ . '/includes/footer.php'; ?>
