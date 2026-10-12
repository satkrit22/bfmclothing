<?php
/**
 * Order confirmation. Only the customer who placed the order can see it.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_login();

$number = input_str('order', 24, 'get');
$order  = $number !== '' ? db_one('SELECT * FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1', [$number, (int)$_SESSION['user_id']]) : null;
if (!$order) {
    redirect('orders.php');
}
$items = db_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [(int)$order['id']]);

$page_title  = 'Order confirmed';
$page_robots = 'noindex,nofollow';
require __DIR__ . '/includes/header.php';
?>
<section class="container page-section order-success">
    <div class="success-mark"><?= icon('check') ?></div>
    <p class="eyebrow">Thank you, <?= e(explode(' ', (string)$order['customer_name'])[0]) ?></p>
    <h1>Your order is confirmed</h1>
    <p class="lead">Order number <strong><?= e($order['order_number']) ?></strong>. We will contact you on <?= e($order['phone']) ?> if we need anything. Pay in cash when it arrives.</p>

    <div class="checkout-layout">
        <div>
            <h2>Items</h2>
            <ul class="summary-items">
                <?php foreach ($items as $item): ?>
                    <li>
                        <div>
                            <strong><?= e($item['product_name']) ?></strong>
                            <span><?= e($item['color']) ?> &middot; <?= e($item['size']) ?> &times; <?= (int)$item['quantity'] ?></span>
                        </div>
                        <b><?= e(money($item['line_total'])) ?></b>
                    </li>
                <?php endforeach; ?>
            </ul>
            <h2>Delivering to</h2>
            <p><?= e($order['customer_name']) ?><br><?= e($order['address_line']) ?><br><?= e($order['city']) ?>, <?= e($order['state']) ?> <?= e($order['postal_code']) ?><br><?= e($order['country']) ?></p>
        </div>
        <aside class="cart-summary">
            <h2>Summary</h2>
            <dl class="summary-lines">
                <div><dt>Subtotal</dt><dd><?= e(money($order['subtotal'])) ?></dd></div>
                <?php if ((float)$order['discount'] > 0): ?>
                    <div class="is-discount"><dt>Discount<?= $order['coupon_code'] ? ' (' . e($order['coupon_code']) . ')' : '' ?></dt><dd>- <?= e(money($order['discount'])) ?></dd></div>
                <?php endif; ?>
                <div><dt>Delivery</dt><dd><?= (float)$order['shipping'] > 0 ? e(money($order['shipping'])) : 'Free' ?></dd></div>
                <div class="is-total"><dt>Total to pay</dt><dd><?= e(money($order['total'])) ?></dd></div>
            </dl>
            <p class="summary-note">Payment: Cash on delivery</p>
            <a class="btn btn-primary btn-block" href="<?= e(url('orders.php')) ?>">View my orders</a>
            <a class="link-arrow summary-continue" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
