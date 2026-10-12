<?php
/**
 * Shopping cart. All prices, stock checks, coupons and delivery fees come from
 * cart_summary() so what is shown here is exactly what checkout will charge.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (is_post()) {
    require_csrf();
    $action = input_str('action', 20);
    $vid    = input_int('variant_id');
    $cart   = cart_items();

    if ($action === 'remove') {
        unset($cart[$vid]);
        flash('success', 'Item removed from your cart.');

    } elseif ($action === 'update' && isset($cart[$vid])) {
        $qty   = max(1, min(10, input_int('quantity', 1)));
        $stock = (int)db_value('SELECT stock FROM product_variants WHERE id = ?', [$vid]);
        if ($stock > 0 && $qty > $stock) {
            $qty = $stock;
            flash('info', 'Only ' . $stock . ' available, so we adjusted your quantity.');
        }
        $cart[$vid] = $qty;

    } elseif ($action === 'apply_coupon') {
        $code   = strtoupper(input_str('coupon', 40));
        $coupon = $code !== '' ? coupon_find($code) : null;
        $_SESSION['cart'] = $cart;           // so cart_summary() prices the current cart
        $subtotal = cart_summary()['subtotal'];
        if (!$coupon) {
            flash('error', 'That coupon code is not valid.');
        } elseif (($err = coupon_error($coupon, $subtotal)) !== null) {
            flash('error', $err);
        } else {
            $_SESSION['coupon'] = $coupon['code'];
            flash('success', 'Coupon ' . $coupon['code'] . ' applied.');
        }

    } elseif ($action === 'remove_coupon') {
        unset($_SESSION['coupon']);
        flash('info', 'Coupon removed.');
    }

    $_SESSION['cart'] = $cart;
    cart_persist();
    redirect('cart.php');
}

$data = cart_summary();
$freeFrom  = (float)setting('free_shipping_threshold', '3000');
$remaining = ($freeFrom > 0 && $data['subtotal'] > 0 && $data['subtotal'] < $freeFrom) ? $freeFrom - $data['subtotal'] : 0.0;
$checkoutUrl = is_logged_in()
    ? url('checkout.php')
    : url('login.php?next=' . rawurlencode(url('checkout.php')));

$page_title  = 'Shopping cart';
$page_robots = 'noindex,nofollow';
require __DIR__ . '/includes/header.php';
?>
<section class="container page-section">
    <p class="eyebrow">Your selection</p>
    <h1>Shopping cart</h1>

    <?php if ($data['coupon_error']): ?>
        <div class="alert alert-warning"><?= e($data['coupon_error']) ?> Your coupon was removed.</div>
    <?php endif; ?>

    <?php if (!$data['lines']): ?>
        <div class="empty-state">
            <?= icon('bag') ?>
            <h3>Your cart is empty</h3>
            <p>Add something from the BFM collection to get started.</p>
            <a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
        </div>
    <?php else: ?>
        <?php foreach (array_unique($data['issues']) as $issue): ?>
            <div class="alert alert-warning"><?= e($issue) ?></div>
        <?php endforeach; ?>

        <div class="cart-layout" id="cart-root">
            <div class="cart-list">
                <?php foreach ($data['lines'] as $line): ?>
                    <article class="cart-row">
                        <a class="cart-thumb" href="<?= e($line['url']) ?>">
                            <img src="<?= e($line['image_url']) ?>" alt="<?= e($line['name']) ?>" width="96" height="120" loading="lazy">
                        </a>
                        <div class="cart-info">
                            <h2><a href="<?= e($line['url']) ?>"><?= e($line['name']) ?></a></h2>
                            <p><?= e($line['color']) ?> &middot; <?= e($line['size']) ?></p>
                            <p class="cart-unit"><?= e(money($line['unit_price'])) ?> each</p>
                            <?php if (!empty($line['issue'])): ?><span class="badge badge-sale"><?= e($line['issue']) ?></span><?php endif; ?>
                        </div>
                        <form method="post" class="cart-controls">
                            <?= csrf_field() ?>
                            <input type="hidden" name="variant_id" value="<?= (int)$line['variant_id'] ?>">
                            <label class="sr-only" for="qty-<?= (int)$line['variant_id'] ?>">Quantity</label>
                            <input id="qty-<?= (int)$line['variant_id'] ?>" type="number" name="quantity" min="1" max="<?= max(1, min(10, (int)$line['stock'])) ?>" value="<?= (int)$line['quantity'] ?>">
                            <button class="btn btn-outline btn-sm" name="action" value="update">Update</button>
                            <button class="link-button" name="action" value="remove" data-confirm="Remove this item from your cart?" data-confirm-title="Remove item" data-confirm-yes="Remove">Remove</button>
                        </form>
                        <strong class="cart-line"><?= e(money($line['line_total'])) ?></strong>
                    </article>
                <?php endforeach; ?>
            </div>

            <aside class="cart-summary">
                <h2>Order summary</h2>

                <?php if ($data['coupon']): ?>
                    <form method="post" class="coupon-applied">
                        <?= csrf_field() ?>
                        <span><?= icon('check') ?> <strong><?= e($data['coupon']['code']) ?></strong> applied</span>
                        <button class="link-button" name="action" value="remove_coupon">Remove</button>
                    </form>
                <?php else: ?>
                    <form method="post" class="coupon-form">
                        <?= csrf_field() ?>
                        <label class="sr-only" for="coupon">Coupon code</label>
                        <input id="coupon" name="coupon" maxlength="40" placeholder="Coupon code" autocomplete="off">
                        <button class="btn btn-outline btn-sm" name="action" value="apply_coupon">Apply</button>
                    </form>
                <?php endif; ?>

                <dl class="summary-lines">
                    <div><dt>Subtotal</dt><dd><?= e(money($data['subtotal'])) ?></dd></div>
                    <?php if ($data['discount'] > 0): ?>
                        <div class="is-discount"><dt>Discount</dt><dd>- <?= e(money($data['discount'])) ?></dd></div>
                    <?php endif; ?>
                    <div><dt>Delivery</dt><dd><?= $data['shipping'] > 0 ? e(money($data['shipping'])) : 'Free' ?></dd></div>
                    <div class="is-total"><dt>Total</dt><dd><?= e(money($data['total'])) ?></dd></div>
                </dl>

                <?php if ($remaining > 0): ?>
                    <p class="summary-note">Add <?= e(money($remaining)) ?> more for free delivery.</p>
                <?php endif; ?>

                <?php if ($data['issues']): ?>
                    <button class="btn btn-primary btn-block" type="button" disabled>Fix the items above to checkout</button>
                <?php else: ?>
                    <a class="btn btn-primary btn-block" href="<?= e($checkoutUrl) ?>">Checkout</a>
                <?php endif; ?>
                <a class="link-arrow summary-continue" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
            </aside>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
