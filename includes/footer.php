<?php
/** Storefront footer. Closes <main>, prints footer, shared dialogs and scripts. */

declare(strict_types=1);

$socials = [
    ['instagram', 'Instagram', setting('instagram_url')],
    ['facebook',  'Facebook',  setting('facebook_url')],
    ['tiktok',    'TikTok',    setting('tiktok_url')],
];
$bfmConfig = [
    'base'     => BASE_PATH,
    'csrf'     => csrf_token(),
    'loggedIn' => is_logged_in(),
    'currency' => setting('currency_symbol', 'Rs.'),
];
?>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand">
            <a class="logo logo-light" href="<?= e(url()) ?>">BFM<span class="logo-sub">Clothing</span></a>
            <p><?= e(setting('tagline', 'Modern essentials for everyday confidence.')) ?></p>
            <ul class="social-links">
                <?php foreach ($socials as [$ico, $label, $link]): ?>
                    <?php if (preg_match('#^https?://#i', $link)): ?>
                        <li><a href="<?= e($link) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($label) ?>"><?= icon($ico) ?></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>

        <nav class="footer-col" aria-label="Shop">
            <h2>Shop</h2>
            <ul>
                <li><a href="<?= e(url('shop.php')) ?>">All products</a></li>
                <li><a href="<?= e(url('shop.php?new=1&sort=newest')) ?>">New arrivals</a></li>
                <li><a href="<?= e(url('shop.php?gender=men')) ?>">Men</a></li>
                <li><a href="<?= e(url('shop.php?gender=women')) ?>">Women</a></li>
                <li><a href="<?= e(url('categories.php')) ?>">Collections</a></li>
            </ul>
        </nav>

        <nav class="footer-col" aria-label="Customer service">
            <h2>Customer service</h2>
            <ul>
                <li><a href="<?= e(url('account.php')) ?>">My account</a></li>
                <li><a href="<?= e(url('account.php?tab=orders')) ?>">Track my order</a></li>
                <li><a href="<?= e(url('faq.php')) ?>">FAQ</a></li>
                <li><a href="<?= e(url('faq.php#shipping')) ?>">Shipping &amp; returns</a></li>
                <li><a href="<?= e(url('contact.php')) ?>">Contact us</a></li>
            </ul>
        </nav>

        <div class="footer-col">
            <h2>Stay in the loop</h2>
            <p class="footer-note">New drops and offers. No spam.</p>
            <form class="newsletter-form" data-newsletter novalidate>
                <label class="sr-only" for="newsletterEmail">Email address</label>
                <input type="email" id="newsletterEmail" name="email" placeholder="Your email" autocomplete="email" maxlength="190" required>
                <button class="btn btn-light" type="submit">Subscribe</button>
            </form>
            <address class="footer-contact">
                <span><?= icon('mail') ?> <?= e(setting('contact_email')) ?></span>
                <span><?= icon('phone') ?> <?= e(setting('contact_phone')) ?></span>
                <span><?= icon('pin') ?> <?= e(setting('contact_address')) ?></span>
            </address>
        </div>
    </div>
    <div class="container footer-bottom">
        <p>&copy; <?= date('Y') ?> <?= e(setting('site_name', APP_NAME)) ?>. All rights reserved.</p>
        <ul>
            <li><a href="<?= e(url('privacy-policy.php')) ?>">Privacy Policy</a></li>
            <li><a href="<?= e(url('terms.php')) ?>">Terms &amp; Conditions</a></li>
        </ul>
    </div>
</footer>

<!-- Quick view modal -->
<dialog class="modal" id="quickViewModal" aria-label="Quick view">
    <button class="icon-btn modal-close" type="button" data-modal-close aria-label="Close"><?= icon('x') ?></button>
    <div class="modal-body" id="quickViewBody"></div>
</dialog>

<!-- Confirm dialog (replaces window.confirm) -->
<dialog class="modal modal-sm" id="confirmModal" aria-label="Please confirm">
    <div class="modal-body">
        <h2 class="modal-title" id="confirmTitle">Are you sure?</h2>
        <p id="confirmMessage"></p>
        <div class="modal-actions">
            <button class="btn btn-outline" type="button" data-confirm-no>Cancel</button>
            <button class="btn btn-primary" type="button" data-confirm-yes>Confirm</button>
        </div>
    </div>
</dialog>

<div class="toast-region" id="toastRegion" aria-live="polite" aria-atomic="false"></div>

<script id="bfm-flash" type="application/json"><?= json_encode(flash_pull(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script>window.BFM = <?= json_encode($bfmConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= e(asset('js/main.js')) ?>" defer></script>
<?= $extra_scripts ?? '' ?>
</body>
</html>
