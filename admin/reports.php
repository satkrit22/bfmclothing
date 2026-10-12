<?php
/**
 * Sales report: pick a date range, see revenue / orders / items, a chart, breakdowns,
 * and export CSV files. "Revenue" is the order total (after discounts, including delivery).
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/admin-auth.php';
require_once __DIR__ . '/../includes/admin-ui.php';
admin_require('reports.view');

/* ---------------- inputs ---------------- */
$today    = date('Y-m-d');
$from     = admin_get_date('from', date('Y-m-d', strtotime('-29 days')));
$to       = admin_get_date('to', $today);
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
if ((strtotime($to) - strtotime($from)) > 3 * 366 * 86400) {
    $from = date('Y-m-d', strtotime($to . ' -3 years'));
}
$days = (int)round((strtotime($to) - strtotime($from)) / 86400) + 1;

$group = input_str('group', 10, 'get');
if (!in_array($group, ['day', 'week', 'month'], true)) {
    $group = $days > 92 ? 'month' : 'day';
}
if ($group === 'day' && $days > 366) {
    $group = 'week';
}
$basis = input_str('basis', 10, 'get');
if (!in_array($basis, ['valid', 'delivered', 'all'], true)) {
    $basis = 'valid';
}
$basisSql = ['valid' => "o.status <> 'cancelled'", 'delivered' => "o.status = 'delivered'", 'all' => '1 = 1'][$basis];
$basisLabel = ['valid' => 'All orders except cancelled', 'delivered' => 'Delivered orders only', 'all' => 'All orders including cancelled'][$basis];

$startAt = $from . ' 00:00:00';
$endAt   = date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00';
$range   = 'o.created_at >= ? AND o.created_at < ?';
$rp      = [$startAt, $endAt];

// previous period of the same length, for the comparison arrows
$prevTo   = date('Y-m-d', strtotime($from . ' -1 day'));
$prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' days'));
$prevP    = [$prevFrom . ' 00:00:00', $from . ' 00:00:00'];

/* ---------------- data ---------------- */
$sum = db_one(
    "SELECT COUNT(*) AS orders, COALESCE(SUM(o.subtotal),0) AS gross, COALESCE(SUM(o.discount),0) AS discounts,
            COALESCE(SUM(o.shipping),0) AS shipping, COALESCE(SUM(o.total),0) AS net,
            COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total END),0) AS collected,
            COUNT(DISTINCT o.email) AS customers
     FROM orders o WHERE $range AND $basisSql",
    $rp
) ?? [];
$prev = db_one("SELECT COUNT(*) AS orders, COALESCE(SUM(o.total),0) AS net FROM orders o WHERE $range AND $basisSql", $prevP) ?? ['orders' => 0, 'net' => 0];
$items = (int)db_value("SELECT COALESCE(SUM(i.quantity),0) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE $range AND $basisSql", $rp);
$cancelled = db_one("SELECT COUNT(*) AS c, COALESCE(SUM(o.total),0) AS t FROM orders o WHERE $range AND o.status = 'cancelled'", $rp) ?? ['c' => 0, 't' => 0];

$orders   = (int)$sum['orders'];
$net      = (float)$sum['net'];
$aov      = $orders > 0 ? $net / $orders : 0.0;
$outstanding = $net - (float)$sum['collected'];

$periodExpr = ['day' => 'DATE(o.created_at)', 'week' => "DATE_FORMAT(o.created_at, '%x-W%v')", 'month' => "DATE_FORMAT(o.created_at, '%Y-%m')"][$group];
$series = db_all(
    "SELECT $periodExpr AS p, COUNT(*) AS orders, COALESCE(SUM(o.subtotal),0) AS gross, COALESCE(SUM(o.discount),0) AS discounts,
            COALESCE(SUM(o.shipping),0) AS shipping, COALESCE(SUM(o.total),0) AS revenue
     FROM orders o WHERE $range AND $basisSql GROUP BY p ORDER BY p",
    $rp
);
$byPeriod = [];
foreach ($series as $r) {
    $byPeriod[(string)$r['p']] = $r;
}
// Fill empty days / months with zeros so the chart has no gaps.
$empty = ['orders' => 0, 'gross' => 0, 'discounts' => 0, 'shipping' => 0, 'revenue' => 0];
$rows = [];
if ($group === 'day') {
    for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime('+1 day', $t)) {
        $k = date('Y-m-d', $t);
        $rows[$k] = ($byPeriod[$k] ?? $empty) + ['p' => $k];
    }
} elseif ($group === 'month') {
    for ($t = strtotime(date('Y-m-01', strtotime($from))); $t <= strtotime($to); $t = strtotime('+1 month', $t)) {
        $k = date('Y-m', $t);
        $rows[$k] = ($byPeriod[$k] ?? $empty) + ['p' => $k];
    }
} else {
    $rows = $byPeriod;
}
$periodLabel = static function (string $p) use ($group): string {
    if ($group === 'day') {
        return date('j M', strtotime($p));
    }
    if ($group === 'month') {
        return date('M Y', strtotime($p . '-01'));
    }
    return 'W' . substr($p, -2) . ' ' . substr($p, 0, 4);
};

$topProducts = db_all(
    "SELECT i.product_name AS name, COALESCE(MAX(i.sku), '') AS sku, SUM(i.quantity) AS qty, SUM(i.line_total) AS rev, COUNT(DISTINCT i.order_id) AS orders
     FROM order_items i JOIN orders o ON o.id = i.order_id
     WHERE $range AND $basisSql GROUP BY i.product_id, i.product_name ORDER BY rev DESC",
    $rp
);
$byCategory = db_all(
    "SELECT COALESCE(c.name, 'Deleted products') AS name, SUM(i.quantity) AS qty, SUM(i.line_total) AS rev
     FROM order_items i JOIN orders o ON o.id = i.order_id
     LEFT JOIN products p ON p.id = i.product_id LEFT JOIN categories c ON c.id = p.category_id
     WHERE $range AND $basisSql GROUP BY c.id, c.name ORDER BY rev DESC",
    $rp
);
$byStatus = db_all("SELECT o.status, COUNT(*) AS c, COALESCE(SUM(o.total),0) AS t FROM orders o WHERE $range GROUP BY o.status", $rp);
$byCoupon = db_all("SELECT o.coupon_code AS code, COUNT(*) AS c, COALESCE(SUM(o.discount),0) AS d FROM orders o WHERE $range AND $basisSql AND o.coupon_code IS NOT NULL GROUP BY o.coupon_code ORDER BY d DESC LIMIT 8", $rp);
$topCustomers = db_all("SELECT o.email, MAX(o.customer_name) AS name, COUNT(*) AS c, COALESCE(SUM(o.total),0) AS s FROM orders o WHERE $range AND $basisSql GROUP BY o.email ORDER BY s DESC LIMIT 8", $rp);
$byCity = db_all("SELECT o.city, COUNT(*) AS c, COALESCE(SUM(o.total),0) AS s FROM orders o WHERE $range AND $basisSql GROUP BY o.city ORDER BY s DESC LIMIT 8", $rp);

/* ---------------- CSV exports ---------------- */
$export = input_str('export', 12, 'get');
if ($export === 'summary') {
    $out = [];
    foreach ($rows as $r) {
        $out[] = [$r['p'], (int)$r['orders'], $r['gross'], $r['discounts'], $r['shipping'], $r['revenue']];
    }
    admin_send_csv("sales-by-$group-$from-to-$to.csv", ['Period', 'Orders', 'Items subtotal', 'Discounts', 'Delivery', 'Revenue'], $out);
}
if ($export === 'products') {
    $out = [];
    foreach ($topProducts as $r) {
        $out[] = [$r['name'], $r['sku'], (int)$r['qty'], (int)$r['orders'], $r['rev']];
    }
    admin_send_csv("sales-by-product-$from-to-$to.csv", ['Product', 'SKU', 'Units sold', 'Orders', 'Revenue'], $out);
}
if ($export === 'orders') {
    $out = [];
    foreach (db_all("SELECT o.order_number, o.created_at, o.customer_name, o.email, o.phone, o.city, o.coupon_code, o.subtotal, o.discount, o.shipping, o.total, o.payment_status, o.status FROM orders o WHERE $range AND $basisSql ORDER BY o.created_at", $rp) as $r) {
        $out[] = array_values($r);
    }
    admin_send_csv("orders-$from-to-$to.csv", ['Order', 'Date (UTC)', 'Customer', 'Email', 'Phone', 'City', 'Coupon', 'Subtotal', 'Discount', 'Delivery', 'Total', 'Payment', 'Status'], $out);
}

/* ---------------- page ---------------- */
$points = [];
foreach ($rows as $r) {
    $label = $periodLabel((string)$r['p']);
    $points[] = ['label' => $label, 'value' => (float)$r['revenue'], 'tip' => $label . ': ' . money($r['revenue']) . ' (' . (int)$r['orders'] . ' orders)'];
}
$preset = static fn(string $f, string $t): string => admin_url('reports.php', ['from' => $f, 'to' => $t, 'group' => $group, 'basis' => $basis]);
$monthStart = date('Y-m-01');
$lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
$lastMonthEnd = date('Y-m-t', strtotime('first day of last month'));
$statusMap = [];
foreach ($byStatus as $r) {
    $statusMap[$r['status']] = $r;
}
$totals = ['orders' => 0, 'gross' => 0.0, 'discounts' => 0.0, 'shipping' => 0.0, 'revenue' => 0.0];
foreach ($rows as $r) {
    $totals['orders']    += (int)$r['orders'];
    $totals['gross']     += (float)$r['gross'];
    $totals['discounts'] += (float)$r['discounts'];
    $totals['shipping']  += (float)$r['shipping'];
    $totals['revenue']   += (float)$r['revenue'];
}

admin_header('Sales report', 'reports', [
    'subtitle' => format_date($from) . ' to ' . format_date($to) . ' · ' . $basisLabel,
    'actions'  => '<a class="adm-btn" href="#" data-print>' . ai('printer', 16) . ' Print</a>'
                . '<a class="adm-btn" href="' . e(admin_url('reports.php', ['from' => $from, 'to' => $to, 'group' => $group, 'basis' => $basis, 'export' => 'summary'])) . '">' . ai('download', 16) . ' Export summary</a>'
                . '<a class="adm-btn" href="' . e(admin_url('reports.php', ['from' => $from, 'to' => $to, 'group' => $group, 'basis' => $basis, 'export' => 'orders'])) . '">' . ai('download', 16) . ' Export orders</a>',
]);
?>
<section class="adm-card adm-noprint" style="margin-bottom:18px">
    <form class="adm-filters" method="get">
        <div class="adm-field"><label for="from">From</label><input class="adm-input" type="date" id="from" name="from" value="<?= e($from) ?>" max="<?= e($today) ?>"></div>
        <div class="adm-field"><label for="to">To</label><input class="adm-input" type="date" id="to" name="to" value="<?= e($to) ?>" max="<?= e($today) ?>"></div>
        <div class="adm-field"><label for="group">Group by</label>
            <select class="adm-select" id="group" name="group">
                <option value="day"<?= adm_sel($group, 'day') ?>>Day</option><option value="week"<?= adm_sel($group, 'week') ?>>Week</option><option value="month"<?= adm_sel($group, 'month') ?>>Month</option>
            </select></div>
        <div class="adm-field"><label for="basis">Count</label>
            <select class="adm-select" id="basis" name="basis">
                <option value="valid"<?= adm_sel($basis, 'valid') ?>>All except cancelled</option><option value="delivered"<?= adm_sel($basis, 'delivered') ?>>Delivered only</option><option value="all"<?= adm_sel($basis, 'all') ?>>Everything</option>
            </select></div>
        <div class="adm-field"><label>&nbsp;</label><button class="adm-btn adm-btn-primary" type="submit">Apply</button></div>
    </form>
    <div class="adm-filters" style="border-bottom:0;padding-top:0">
        <span class="adm-muted">Quick ranges:</span>
        <a class="adm-btn adm-btn-sm" href="<?= e($preset($today, $today)) ?>">Today</a>
        <a class="adm-btn adm-btn-sm" href="<?= e($preset(date('Y-m-d', strtotime('-6 days')), $today)) ?>">7 days</a>
        <a class="adm-btn adm-btn-sm" href="<?= e($preset(date('Y-m-d', strtotime('-29 days')), $today)) ?>">30 days</a>
        <a class="adm-btn adm-btn-sm" href="<?= e($preset($monthStart, $today)) ?>">This month</a>
        <a class="adm-btn adm-btn-sm" href="<?= e($preset($lastMonthStart, $lastMonthEnd)) ?>">Last month</a>
        <a class="adm-btn adm-btn-sm" href="<?= e($preset(date('Y-01-01'), $today)) ?>">This year</a>
    </div>
</section>

<div class="adm-kpis">
    <?php
    $d1 = admin_delta($net, (float)$prev['net']);
    $d2 = admin_delta((float)$orders, (float)$prev['orders']);
    ?>
    <div class="adm-card adm-kpi"><small><?= ai('dollar', 14) ?>Revenue</small><strong><?= e(money($net)) ?></strong><span><?php if ($d1): ?><b class="<?= e($d1['class']) ?>"><?= e($d1['text']) ?></b> <?php endif; ?>vs previous <?= (int)$days ?> days</span></div>
    <div class="adm-card adm-kpi"><small><?= ai('bag', 14) ?>Orders</small><strong><?= (int)$orders ?></strong><span><?php if ($d2): ?><b class="<?= e($d2['class']) ?>"><?= e($d2['text']) ?></b> <?php endif; ?>avg. order <?= e(money($aov)) ?></span></div>
    <div class="adm-card adm-kpi"><small><?= ai('box', 14) ?>Items sold</small><strong><?= (int)$items ?></strong><span><?= $orders > 0 ? e(number_format($items / $orders, 1)) : '0' ?> per order</span></div>
    <div class="adm-card adm-kpi"><small><?= ai('users', 14) ?>Buyers</small><strong><?= (int)$sum['customers'] ?></strong><span>unique email addresses</span></div>
    <div class="adm-card adm-kpi"><small><?= ai('percent', 14) ?>Discounts given</small><strong><?= e(money($sum['discounts'])) ?></strong><span>Delivery charged <?= e(money($sum['shipping'])) ?></span></div>
    <div class="adm-card adm-kpi"><small><?= ai('check', 14) ?>Cash collected</small><strong><?= e(money($sum['collected'])) ?></strong><span><?= e(money($outstanding)) ?> still to collect</span></div>
</div>

<section class="adm-card" style="margin-bottom:18px">
    <div class="adm-card-head"><h2>Revenue by <?= e($group) ?></h2><span class="adm-muted"><?= (int)$cancelled['c'] ?> cancelled (<?= e(money($cancelled['t'])) ?>) not counted<?= $basis === 'all' ? ' separately' : '' ?></span></div>
    <div class="adm-card-body"><?= admin_bar_chart($points, 'Revenue per ' . $group) ?></div>
    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead><tr><th><?= e(ucfirst($group)) ?></th><th class="adm-num">Orders</th><th class="adm-num">Items subtotal</th><th class="adm-num">Discounts</th><th class="adm-num">Delivery</th><th class="adm-num">Revenue</th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($rows, true) as $r): ?>
                <?php if ($group === 'day' && $days > 62 && (int)$r['orders'] === 0) { continue; } ?>
                <tr>
                    <td><?= e($periodLabel((string)$r['p'])) ?><?= $group === 'day' ? ' <small class="adm-muted">' . e(date('D', strtotime((string)$r['p']))) . '</small>' : '' ?></td>
                    <td class="adm-num"><?= (int)$r['orders'] ?></td>
                    <td class="adm-num"><?= e(money($r['gross'])) ?></td>
                    <td class="adm-num"><?= (float)$r['discounts'] > 0 ? '- ' . e(money($r['discounts'])) : '-' ?></td>
                    <td class="adm-num"><?= e(money($r['shipping'])) ?></td>
                    <td class="adm-num"><strong><?= e(money($r['revenue'])) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td>Total</td><td class="adm-num"><?= (int)$totals['orders'] ?></td><td class="adm-num"><?= e(money($totals['gross'])) ?></td><td class="adm-num">- <?= e(money($totals['discounts'])) ?></td><td class="adm-num"><?= e(money($totals['shipping'])) ?></td><td class="adm-num"><?= e(money($totals['revenue'])) ?></td></tr></tfoot>
        </table>
    </div>
</section>

<div class="adm-split-even">
    <section class="adm-card">
        <div class="adm-card-head"><h2>Top products</h2><a class="adm-noprint" href="<?= e(admin_url('reports.php', ['from' => $from, 'to' => $to, 'group' => $group, 'basis' => $basis, 'export' => 'products'])) ?>">Export CSV</a></div>
        <?php if (!$topProducts): ?><div class="adm-empty"><p>No sales in this period.</p></div><?php else: ?>
        <div class="adm-table-wrap"><table class="adm-table">
            <thead><tr><th>Product</th><th class="adm-num">Units</th><th class="adm-num">Revenue</th></tr></thead>
            <tbody>
            <?php foreach (array_slice($topProducts, 0, 10) as $r): ?>
                <tr><td><?= e($r['name']) ?></td><td class="adm-num"><?= (int)$r['qty'] ?></td><td class="adm-num"><?= e(money($r['rev'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>
    <section class="adm-card">
        <div class="adm-card-head"><h2>Sales by category</h2></div>
        <div class="adm-card-body"><?= admin_share_bars(array_map(static fn(array $r): array => ['label' => $r['name'], 'value' => (float)$r['rev'], 'note' => money($r['rev']) . ' · ' . (int)$r['qty'] . ' units'], $byCategory)) ?></div>
    </section>
</div>

<div class="adm-split-even">
    <section class="adm-card">
        <div class="adm-card-head"><h2>Orders by status</h2><span class="adm-muted">all statuses in this period</span></div>
        <div class="adm-table-wrap"><table class="adm-table">
            <thead><tr><th>Status</th><th class="adm-num">Orders</th><th class="adm-num">Value</th></tr></thead>
            <tbody>
            <?php foreach (order_statuses() as $s): $r = $statusMap[$s] ?? null; ?>
                <tr><td><?= adm_status_badge($s) ?></td><td class="adm-num"><?= (int)($r['c'] ?? 0) ?></td><td class="adm-num"><?= e(money($r['t'] ?? 0)) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
    <section class="adm-card">
        <div class="adm-card-head"><h2>Top customers</h2></div>
        <?php if (!$topCustomers): ?><div class="adm-empty"><p>No sales in this period.</p></div><?php else: ?>
        <div class="adm-table-wrap"><table class="adm-table">
            <thead><tr><th>Customer</th><th class="adm-num">Orders</th><th class="adm-num">Spent</th></tr></thead>
            <tbody>
            <?php foreach ($topCustomers as $r): ?>
                <tr><td><?= e($r['name']) ?><br><small class="adm-muted"><?= e($r['email']) ?></small></td><td class="adm-num"><?= (int)$r['c'] ?></td><td class="adm-num"><?= e(money($r['s'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>
</div>

<div class="adm-split-even">
    <section class="adm-card">
        <div class="adm-card-head"><h2>Sales by city</h2></div>
        <div class="adm-card-body"><?= admin_share_bars(array_map(static fn(array $r): array => ['label' => $r['city'], 'value' => (float)$r['s'], 'note' => money($r['s']) . ' · ' . (int)$r['c'] . ' orders'], $byCity)) ?></div>
    </section>
    <section class="adm-card">
        <div class="adm-card-head"><h2>Coupons used</h2></div>
        <?php if (!$byCoupon): ?><div class="adm-empty"><p>No coupons were used in this period.</p></div><?php else: ?>
        <div class="adm-table-wrap"><table class="adm-table">
            <thead><tr><th>Code</th><th class="adm-num">Orders</th><th class="adm-num">Discount given</th></tr></thead>
            <tbody>
            <?php foreach ($byCoupon as $r): ?>
                <tr><td><span class="adm-mono"><?= e($r['code']) ?></span></td><td class="adm-num"><?= (int)$r['c'] ?></td><td class="adm-num"><?= e(money($r['d'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>
</div>
<p class="adm-muted">Times are in UTC. Revenue is the order total after discounts, including delivery charges.</p>
<?php admin_footer(); ?>
