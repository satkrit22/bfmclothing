<?php
/**
 * Customer order history, with the items of each order.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_login();

$uid    = (int)$_SESSION['user_id'];
$orders = db_all('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC', [$uid]);

$itemsByOrder = [];
if ($orders) {
    $ids = array_map(static fn(array $o): int => (int)$o['id'], $orders);
    $in  = implode(',', array_fill(0, count($ids), '?'));
    foreach (db_all("SELECT * FROM order_items WHERE order_id IN ($in) ORDER BY id", $ids) as $item) {
        $itemsByOrder[(int)$item['order_id']][] = $item;
    }
}

$page_title  = 'Your orders';
$page_robots = 'noindex,nofollow';
require __DIR__ . '/includes/header.php';
?>
<section class="container page-section">
    <p class="eyebrow">Account</p>
    <h1>Your orders</h1>

    <?php if (!$orders): ?>
        <div class="empty-state">
            <h2>No orders yet</h2>
            <p>Your purchases will appear here.</p>
            <a class="btn btn-primary" href="<?= e(url('shop.php')) ?>">Shop now</a>
        </div>
    <?php else: ?>
        <div class="orders-list">
            <?php foreach ($orders as $o): ?>
                <details class="order-card"<?= (string)($_GET['order'] ?? '') === (string)$o['order_number'] ? ' open' : '' ?>>
                    <summary>
                        <span class="order-no"><?= e($o['order_number'] ?: '#' . $o['id']) ?></span>
                        <span class="order-date"><?= e(format_date($o['created_at'], 'M j, Y')) ?></span>
                        <?= status_badge((string)$o['status']) ?>
                        <strong class="order-total"><?= e(money($o['total'])) ?></strong>
                    </summary>
                    <div class="order-body">
                        <ul class="summary-items">
                            <?php foreach ($itemsByOrder[(int)$o['id']] ?? [] as $item): ?>
                                <li>
                                    <div>
                                        <strong><?= e($item['product_name']) ?></strong>
                                        <span><?= e($item['color']) ?> &middot; <?= e($item['size']) ?> &times; <?= (int)$item['quantity'] ?></span>
                                    </div>
                                    <b><?= e(money($item['line_total'])) ?></b>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="order-meta">
                            Delivery to <?= e($o['address_line']) ?>, <?= e($o['city']) ?> &middot;
                            Payment: <?= e(strtoupper((string)$o['payment_method'])) ?> (<?= e($o['payment_status']) ?>)
                        </p>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
