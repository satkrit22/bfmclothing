<?php
/** Admin dashboard: key numbers, 30-day sales chart, recent orders, best sellers, low stock. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('dashboard.view');

$threshold = max(0, (int)setting('low_stock_threshold', '5'));
$todayStart = date('Y-m-d 00:00:00');
$start30    = date('Y-m-d 00:00:00', strtotime('-29 days'));
$start60    = date('Y-m-d 00:00:00', strtotime('-59 days'));

$revenue = static fn(string $from, ?string $to = null): float => (float)db_value(
    "SELECT COALESCE(SUM(total), 0) FROM orders WHERE status <> 'cancelled' AND created_at >= ?" . ($to ? ' AND created_at < ?' : ''),
    $to ? [$from, $to] : [$from]
);
$ordersIn = static fn(string $from, ?string $to = null): int => (int)db_value(
    "SELECT COUNT(*) FROM orders WHERE status <> 'cancelled' AND created_at >= ?" . ($to ? ' AND created_at < ?' : ''),
    $to ? [$from, $to] : [$from]
);

$rev30   = $revenue($start30);
$revPrev = $revenue($start60, $start30);
$ord30   = $ordersIn($start30);
$ordPrev = $ordersIn($start60, $start30);
$revToday = $revenue($todayStart);
$ordToday = $ordersIn($todayStart);
$aov30   = $ord30 > 0 ? $rev30 / $ord30 : 0.0;

$pending    = (int)db_value("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$toShip     = (int)db_value("SELECT COUNT(*) FROM orders WHERE status IN ('confirmed','processing')");
$customers  = (int)db_value("SELECT COUNT(*) FROM users WHERE role = 'customer' AND is_active = 1");
$newCust30  = (int)db_value("SELECT COUNT(*) FROM users WHERE role = 'customer' AND created_at >= ?", [$start30]);
$products   = (int)db_value('SELECT COUNT(*) FROM products WHERE is_active = 1');
$lowCount   = (int)db_value('SELECT COUNT(*) FROM product_variants v JOIN products p ON p.id = v.product_id WHERE p.is_active = 1 AND v.stock <= ?', [$threshold]);
$unpaidCod  = (float)db_value("SELECT COALESCE(SUM(total), 0) FROM orders WHERE payment_status = 'unpaid' AND status NOT IN ('cancelled')");

// 30-day chart (missing days are zero)
$byDay = [];
for ($i = 29; $i >= 0; $i--) {
    $byDay[date('Y-m-d', strtotime("-$i days"))] = ['revenue' => 0.0, 'orders' => 0];
}
foreach (db_all("SELECT DATE(created_at) AS d, COALESCE(SUM(total), 0) AS revenue, COUNT(*) AS c FROM orders WHERE status <> 'cancelled' AND created_at >= ? GROUP BY DATE(created_at)", [$start30]) as $row) {
    if (isset($byDay[$row['d']])) {
        $byDay[$row['d']] = ['revenue' => (float)$row['revenue'], 'orders' => (int)$row['c']];
    }
}
$points = [];
foreach ($byDay as $d => $v) {
    $label = date('j M', strtotime($d));
    $points[] = ['label' => $label, 'value' => $v['revenue'], 'tip' => $label . ': ' . money($v['revenue']) . ' (' . $v['orders'] . ' orders)'];
}

$recent = db_all('SELECT id, order_number, customer_name, total, status, payment_status, created_at FROM orders ORDER BY created_at DESC, id DESC LIMIT 8');
$top = db_all(
    "SELECT i.product_name, SUM(i.quantity) AS qty, SUM(i.line_total) AS rev
     FROM order_items i JOIN orders o ON o.id = i.order_id
     WHERE o.status <> 'cancelled' AND o.created_at >= ?
     GROUP BY i.product_id, i.product_name ORDER BY rev DESC LIMIT 5",
    [$start30]
);
$low = db_all(
    'SELECT v.id, v.size, v.color, v.stock, p.id AS product_id, p.name
     FROM product_variants v JOIN products p ON p.id = v.product_id
     WHERE p.is_active = 1 AND v.stock <= ? ORDER BY v.stock, p.name LIMIT 8',
    [$threshold]
);
$statusRows = db_all('SELECT status, COUNT(*) AS c FROM orders GROUP BY status');
$statusCount = array_fill_keys(order_statuses(), 0);
foreach ($statusRows as $r) {
    $statusCount[$r['status']] = (int)$r['c'];
}

$kpi = static function (string $icon, string $label, string $value, string $sub = '', ?array $delta = null): void {
    echo '<div class="adm-card adm-kpi"><small>' . ai($icon, 14) . e($label) . '</small><strong>' . e($value) . '</strong><span>';
    if ($delta) {
        echo '<b class="' . e($delta['class']) . '">' . e($delta['text']) . '</b> ';
    }
    echo e($sub) . '</span></div>';
};

admin_header('Dashboard', 'dashboard', [
    'subtitle' => 'Welcome back, ' . ($admin = current_admin())['first'] . '. Here is how the store is doing.',
    'actions'  => (admin_can('reports.view') ? '<a class="adm-btn" href="' . e(admin_url('reports.php')) . '">' . ai('chart', 16) . ' Sales report</a>' : '')
                . (admin_can('products.manage') ? '<a class="adm-btn adm-btn-primary" href="' . e(admin_url('product-edit.php')) . '">' . ai('plus', 16) . ' Add product</a>' : ''),
]);
?>
<div class="adm-kpis">
    <?php
    $kpi('dollar', 'Revenue (30 days)', money($rev30), 'vs previous 30 days', admin_delta($rev30, $revPrev));
    $kpi('bag', 'Orders (30 days)', (string)$ord30, 'avg. order ' . money($aov30), admin_delta((float)$ord30, (float)$ordPrev));
    $kpi('trend', 'Today', money($revToday), $ordToday . ' order' . ($ordToday === 1 ? '' : 's') . ' so far');
    $kpi('alert', 'Needs action', (string)$pending, $toShip . ' more to pack and ship');
    $kpi('users', 'Customers', (string)$customers, $newCust30 . ' new in 30 days');
    $kpi('package', 'Active products', (string)$products, $lowCount . ' variant' . ($lowCount === 1 ? '' : 's') . ' low or out of stock');
    ?>
</div>

<div class="adm-split">
    <div class="adm-stack">
        <section class="adm-card">
            <div class="adm-card-head"><h2>Revenue, last 30 days</h2><span class="adm-muted"><?= e(money($rev30)) ?> total</span></div>
            <div class="adm-card-body"><?= admin_bar_chart($points, 'Revenue per day for the last 30 days') ?></div>
        </section>

        <section class="adm-card">
            <div class="adm-card-head"><h2>Recent orders</h2><?php if (admin_can('orders.view')): ?><a href="<?= e(admin_url('orders.php')) ?>">View all</a><?php endif; ?></div>
            <?php if (!$recent): ?>
                <div class="adm-empty"><?= ai('bag', 28) ?><p>No orders yet. They will show up here as soon as customers check out.</p></div>
            <?php else: ?>
                <div class="adm-table-wrap"><table class="adm-table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Payment</th><th>Status</th><th class="adm-num">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $o): ?>
                        <tr>
                            <td><a href="<?= e(admin_url('order.php', ['id' => $o['id']])) ?>"><strong><?= e($o['order_number'] ?: '#' . $o['id']) ?></strong></a></td>
                            <td><?= e($o['customer_name']) ?></td>
                            <td class="adm-nowrap adm-muted"><?= e(format_date($o['created_at'], 'd M, H:i')) ?></td>
                            <td><?= adm_payment_badge((string)$o['payment_status']) ?></td>
                            <td><?= adm_status_badge((string)$o['status']) ?></td>
                            <td class="adm-num"><?= e(money($o['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </section>
    </div>

    <aside class="adm-stack">
        <section class="adm-card">
            <div class="adm-card-head"><h2>Order pipeline</h2></div>
            <div class="adm-card-body">
                <ul class="adm-bars">
                    <?php foreach ($statusCount as $s => $c): ?>
                        <li><div class="adm-bars-row"><span><?= adm_status_badge($s) ?></span><b><?= (int)$c ?></b></div></li>
                    <?php endforeach; ?>
                </ul>
                <p class="adm-muted" style="margin-top:14px">Cash still to collect: <strong><?= e(money($unpaidCod)) ?></strong></p>
            </div>
        </section>

        <section class="adm-card">
            <div class="adm-card-head"><h2>Best sellers (30 days)</h2></div>
            <div class="adm-card-body">
                <?= admin_share_bars(array_map(static fn(array $r): array => ['label' => $r['product_name'], 'value' => (float)$r['rev'], 'note' => money($r['rev']) . ' · ' . (int)$r['qty'] . ' sold'], $top)) ?>
            </div>
        </section>

        <section class="adm-card">
            <div class="adm-card-head"><h2>Low stock</h2><span class="adm-muted">&le; <?= (int)$threshold ?> units</span></div>
            <?php if (!$low): ?>
                <div class="adm-empty"><p>Everything is well stocked.</p></div>
            <?php else: ?>
                <div class="adm-table-wrap"><table class="adm-table"><tbody>
                    <?php foreach ($low as $v): ?>
                        <tr>
                            <td><a href="<?= e(admin_url('product-edit.php', ['id' => $v['product_id']])) ?>"><?= e($v['name']) ?></a><br><small class="adm-muted"><?= e($v['color']) ?> &middot; <?= e($v['size']) ?></small></td>
                            <td class="adm-num <?= (int)$v['stock'] === 0 ? 'adm-stock-out' : 'adm-stock-low' ?>"><?= (int)$v['stock'] === 0 ? 'Sold out' : (int)$v['stock'] . ' left' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            <?php endif; ?>
        </section>
    </aside>
</div>
<?php admin_footer(); ?>
