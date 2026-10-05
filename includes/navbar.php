<?php
/** Navbar. Included by header.php. */

declare(strict_types=1);

$navLinks = [
    ['Home',         url(),                                     'index.php'],
    ['Shop',         url('shop.php'),                           'shop.php'],
    ['Men',          url('shop.php?gender=men'),                ''],
    ['Women',        url('shop.php?gender=women'),              ''],
    ['New Arrivals', url('shop.php?new=1&sort=newest'),         ''],
    ['Collections',  url('categories.php'),                     'categories.php'],
    ['About',        url('about.php'),                          'about.php'],
    ['Contact',      url('contact.php'),                        'contact.php'],
];
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isActive = function (string $file) use ($currentScript): bool {
    return $file !== '' && $file === $currentScript && empty($_GET);
};
$promo     = setting('promo_banner');
$cartCount = cart_count();
$wishCount = wishlist_count();
?>
<?php if ($promo !== ''): ?>
<div class="promo-bar"><p><?= e($promo) ?></p></div>
<?php endif; ?>

<header class="site-header" id="siteHeader">
    <div class="container header-inner">
        <button class="icon-btn nav-toggle" type="button" data-nav-open aria-controls="mobileNav" aria-expanded="false" aria-label="Open menu">
            <?= icon('menu') ?>
        </button>

        <a class="logo" href="<?= e(url()) ?>" aria-label="BFM Clothing - home">BFM<span class="logo-sub">Clothing</span></a>

        <nav class="primary-nav" aria-label="Primary">
            <ul>
                <?php foreach ($navLinks as [$label, $href, $file]): ?>
                    <li><a href="<?= e($href) ?>"<?= $isActive($file) ? ' class="active" aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="header-actions">
            <button class="icon-btn" type="button" data-search-open aria-label="Search"><?= icon('search') ?></button>
            <a class="icon-btn hide-sm" href="<?= e(url(is_logged_in() ? 'account.php' : 'login.php')) ?>" aria-label="<?= is_logged_in() ? 'My account' : 'Log in' ?>"><?= icon('user') ?></a>
            <a class="icon-btn" href="<?= e(url('wishlist.php')) ?>" aria-label="Wishlist">
                <?= icon('heart') ?><span class="count-badge" data-wishlist-count<?= $wishCount ? '' : ' hidden' ?>><?= (int)$wishCount ?></span>
            </a>
            <a class="icon-btn" href="<?= e(url('cart.php')) ?>" aria-label="Shopping cart">
                <?= icon('bag') ?><span class="count-badge" data-cart-count<?= $cartCount ? '' : ' hidden' ?>><?= (int)$cartCount ?></span>
            </a>
        </div>
    </div>
</header>

<!-- Mobile navigation drawer -->
<div class="overlay" data-overlay hidden></div>
<aside class="drawer drawer-left" id="mobileNav" aria-label="Menu" aria-hidden="true">
    <div class="drawer-head">
        <a class="logo" href="<?= e(url()) ?>">BFM</a>
        <button class="icon-btn" type="button" data-drawer-close aria-label="Close menu"><?= icon('x') ?></button>
    </div>
    <nav class="mobile-nav" aria-label="Mobile">
        <ul>
            <?php foreach ($navLinks as [$label, $href]): ?>
                <li><a href="<?= e($href) ?>"><?= e($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <?php if (nav_categories()): ?>
            <p class="mobile-nav-title">Shop by category</p>
            <ul class="mobile-nav-sub">
                <?php foreach (nav_categories() as $cat): ?>
                    <li><a href="<?= e(url('shop.php?category=' . rawurlencode($cat['slug']))) ?>"><?= e($cat['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <div class="mobile-nav-account">
            <?php if (is_logged_in()): ?>
                <a class="btn btn-outline btn-block" href="<?= e(url('account.php')) ?>">My account</a>
            <?php else: ?>
                <a class="btn btn-primary btn-block" href="<?= e(url('login.php')) ?>">Log in</a>
                <a class="btn btn-outline btn-block" href="<?= e(url('register.php')) ?>">Create account</a>
            <?php endif; ?>
        </div>
    </nav>
</aside>

<!-- Search overlay -->
<div class="search-panel" id="searchPanel" role="dialog" aria-label="Search" aria-hidden="true" hidden>
    <div class="container">
        <form class="search-form" action="<?= e(url('search.php')) ?>" method="get" role="search">
            <?= icon('search') ?>
            <input type="search" name="q" id="searchInput" placeholder="Search tees, hoodies, jackets..." autocomplete="off" maxlength="100" aria-label="Search products" required>
            <button type="button" class="icon-btn" data-search-close aria-label="Close search"><?= icon('x') ?></button>
        </form>
        <ul class="search-suggest" id="searchSuggest" aria-live="polite"></ul>
    </div>
</div>
